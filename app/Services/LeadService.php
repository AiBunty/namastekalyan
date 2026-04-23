<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Config\Database;
use NK\Middleware\AuthMiddleware;
use NK\Repositories\ContactRepository;
use NK\Repositories\CrmPushLogRepository;
use NK\Repositories\EventRepository;
use NK\Repositories\LeadRepository;
use NK\Repositories\QrRedirectRepository;
use NK\Repositories\QrRedirectSettingsRepository;
use NK\Repositories\QrScanRepository;
use NK\Support\Logger;
use NK\Support\SiteUrl;
use NK\Support\Validator;

class LeadService
{
    private LeadRepository $leads;
    private ContactRepository $contacts;
    private CrmPushLogRepository $crmPushLogs;
    private CrmService $crm;
    private EventRepository $events;
    private QrScanRepository $qrScans;
    private QrRedirectSettingsRepository $qrRedirectSettings;
    private QrRedirectRepository $qrRedirects;
    private WhatsAppCloudService $whatsapp;

    public function __construct()
    {
        $this->leads = new LeadRepository();
        $this->contacts = new ContactRepository();
        $this->crmPushLogs = new CrmPushLogRepository();
        $this->crm = new CrmService();
        $this->events = new EventRepository();
        $this->qrScans = new QrScanRepository();
        $this->qrRedirectSettings = new QrRedirectSettingsRepository();
        $this->qrRedirects = new QrRedirectRepository();
        $this->whatsapp = new WhatsAppCloudService();
    }

    public function submitLead(array $data): array
    {
        $prepared = $this->prepareLeadInput($data);
        $name = $prepared['name'];
        $countryCode = $prepared['countryCode'];
        $phone = $prepared['phone'];

        if ($name === '' || !Validator::phone($phone)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Valid name and 10-digit phone are required.',
            ];
        }

        $cooldownLead = $this->leads->findLatestCompletedByPhone($phone);
        $cooldown = $this->buildSpinCooldownState($cooldownLead);
        if ($cooldown['active']) {
            return [
                'ok' => false,
                'error' => 'COOLDOWN_ACTIVE',
                'message' => 'This number has already completed a spin in the last 24 hours.',
                'retryAfter' => $cooldown['retryAfter'],
                'retryAfterEpochMs' => $cooldown['retryAfterEpochMs'],
                'remainingMs' => $cooldown['remainingMs'],
                'remainingMinutes' => $cooldown['remainingMinutes'],
                'existingLead' => $cooldownLead ? $this->formatSpinLeadSummary($cooldownLead) : null,
            ];
        }

        $existing = $this->leads->findLatestByPhone($phone);
        $visitCount = $existing ? ((int) ($existing['visit_count'] ?? 0) + 1) : 1;
        $leadNumber = $this->leads->countRows() + 1;

        $prize = $this->pickPrizeByLeadNumber($leadNumber);
        $couponCode = $this->isWinningPrize($prize) ? $this->generateCouponCode($phone) : '';
        $dateOfBirth = $prepared['dateOfBirth'];
        $dateOfAnniversary = $prepared['dateOfAnniversary'];
        $source = $prepared['source'];

        $createdAt = date('Y-m-d H:i:s');

        $leadId = $this->leads->create([
            'created_at'          => $createdAt,
            'spin_completed_at'   => null,
            'name'                => $name,
            'phone'               => $phone,
            'prize'               => $prize,
            'status'              => 'Unredeemed',
            'date_of_birth'       => $dateOfBirth,
            'date_of_anniversary' => $dateOfAnniversary,
            'source'              => $source,
            'visit_count'         => $visitCount,
            'coupon_code'         => $couponCode,
            'crm_sync_status'     => 'Pending',
        ]);

        $crmSync = $this->syncLeadToCrm($leadId, [
            'name' => $name,
            'phone' => $phone,
            'country_code' => $countryCode,
            'prize' => $prize,
            'status' => 'Unredeemed',
            'source' => $source,
            'visit_count' => $visitCount,
            'date_of_birth' => $dateOfBirth,
            'date_of_anniversary' => $dateOfAnniversary,
            'created_at' => $createdAt,
        ], $this->upsertCanonicalContact($leadId, [
            'name' => $name,
            'phone' => $phone,
            'source' => $source,
            'date_of_birth' => $dateOfBirth,
            'date_of_anniversary' => $dateOfAnniversary,
            'created_at' => $createdAt,
            'crm_sync_status' => 'Pending',
            'crm_sync_code' => '',
            'crm_sync_message' => '',
        ]));

        return [
            'ok'         => true,
            'result'     => 'success',
            'row'        => $leadId,
            'name'       => $name,
            'prize'      => $prize,
            'leadNumber' => $leadNumber,
            'leadId'     => $leadId,
            'visitCount' => $visitCount,
            'phone'      => $phone,
            'countryCode'=> $countryCode,
            'couponCode' => $couponCode,
            'crmSync'    => $crmSync,
        ];
    }

    public function completeSpin(array $data): array
    {
        $leadId = (int) ($data['leadId'] ?? $data['row'] ?? 0);
        $phone = Validator::digitsOnly((string) ($data['phone'] ?? ''), 10);

        if ($leadId <= 0 || !Validator::phone($phone)) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'Valid leadId and phone are required.',
            ];
        }

        $lead = $this->leads->findById($leadId);
        if (!$lead || (string) ($lead['phone'] ?? '') !== $phone) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Lead record not found for this phone.',
            ];
        }

        $existingCompletedAt = trim((string) ($lead['spin_completed_at'] ?? ''));
        if ($existingCompletedAt !== '') {
            $cooldown = $this->buildSpinCooldownState($lead);
            return [
                'ok' => true,
                'result' => 'already_completed',
                'leadId' => $leadId,
                'spinCompletedAt' => $existingCompletedAt,
                'retryAfter' => $cooldown['retryAfter'],
                'retryAfterEpochMs' => $cooldown['retryAfterEpochMs'],
            ];
        }

        $completedAt = date('Y-m-d H:i:s');
        $this->leads->markSpinCompleted($leadId, $completedAt);

        $whatsAppResult = null;
        if ($this->isWinningPrize((string) ($lead['prize'] ?? '')) && trim((string) ($lead['coupon_code'] ?? '')) !== '') {
            $whatsAppResult = $this->whatsapp->triggerEvent('winner_coupon_issued', (string) ($lead['phone'] ?? ''), [
                'customerName' => (string) ($lead['name'] ?? ''),
                'rewardLabel' => (string) ($lead['prize'] ?? ''),
                'couponCode' => (string) ($lead['coupon_code'] ?? ''),
            ], [
                'leadId' => $leadId,
                'countryCode' => '91',
            ]);
        }

        return [
            'ok' => true,
            'result' => 'spin_completed',
            'leadId' => $leadId,
            'spinCompletedAt' => $completedAt,
            'retryAfter' => date('Y-m-d H:i:s', strtotime($completedAt . ' +' . Constants::SPIN_COOLDOWN_HOURS . ' hours')),
            'retryAfterEpochMs' => (strtotime($completedAt . ' +' . Constants::SPIN_COOLDOWN_HOURS . ' hours') ?: time()) * 1000,
            'whatsapp' => $whatsAppResult,
        ];
    }

    public function verify(array $query): array
    {
        $phone = Validator::digitsOnly((string) ($query['phone'] ?? ''), 10);
        if (!Validator::phone($phone)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Valid 10-digit phone is required.',
            ];
        }

        $lead = $this->leads->findLatestByPhone($phone);
        if (!$lead) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'No record found for this phone.',
            ];
        }

        $reward = $this->activeRewardState($lead);

        return [
            'ok'        => true,
            'phone'     => $lead['phone'],
            'name'      => $lead['name'],
            'prize'     => $lead['prize'],
            'originalPrize' => $lead['prize'],
            'status'    => $lead['status'],
            'couponCode'=> $reward['couponCode'],
            'winnerCouponCode' => (string) ($lead['coupon_code'] ?? ''),
            'surpriseCouponCode' => (string) ($lead['surprise_coupon_code'] ?? ''),
            'activeRewardLabel' => $reward['label'],
            'activeRewardSource' => $reward['source'],
            'canRedeem' => $reward['canRedeem'],
            'canIssueSurprise' => $this->canIssueSurprise($lead),
            'canRegenerateWinnerCoupon' => $this->isWinningPrize((string) ($lead['prize'] ?? '')),
            'surpriseIssuedAt' => (string) ($lead['surprise_issued_at'] ?? ''),
            'surpriseIssuedBy' => (string) ($lead['surprise_issued_by'] ?? ''),
            'visitCount'=> (int) ($lead['visit_count'] ?? 0),
            'dob' => (string) ($lead['date_of_birth'] ?? ''),
            'anniversary' => (string) ($lead['date_of_anniversary'] ?? ''),
            'source' => (string) ($lead['source'] ?? ''),
            'timestamp' => (string) ($lead['created_at'] ?? ''),
        ];
    }

    public function redeem(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'verification')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Verification permission required.',
            ];
        }

        $phone = Validator::digitsOnly((string) ($data['phone'] ?? ''), 10);
        if (!Validator::phone($phone)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Valid 10-digit phone is required.',
            ];
        }

        $lead = $this->leads->findLatestByPhone($phone);
        if (!$lead) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'No record found for this phone.',
            ];
        }

        $reward = $this->activeRewardState($lead);
        if ($reward['source'] === 'none') {
            return [
                'ok' => false,
                'error' => 'NO_REWARD',
                'message' => 'There is no redeemable reward for this mobile number.',
            ];
        }

        if (!$reward['canRedeem']) {
            return [
                'ok' => false,
                'error' => 'ALREADY_REDEEMED',
                'message' => 'This reward has already been redeemed.',
            ];
        }

        if ($reward['source'] === 'surprise') {
            $this->leads->redeemSurpriseReward((int) $lead['id']);
        } else {
            $this->leads->updateRedemption((int) $lead['id'], true);
        }

        $whatsAppResult = $this->whatsapp->triggerEvent('coupon_redeemed', (string) ($lead['phone'] ?? ''), [
            'customerName' => (string) ($lead['name'] ?? ''),
            'rewardLabel' => $reward['label'],
            'couponCode' => $reward['couponCode'],
        ], [
            'leadId' => (int) ($lead['id'] ?? 0),
            'countryCode' => '91',
        ]);

        return [
            'ok'      => true,
            'action'  => 'redeem',
            'message' => 'Coupon redeemed successfully.',
            'rewardLabel' => $reward['label'],
            'rewardSource' => $reward['source'],
            'couponCode' => $reward['couponCode'],
            'whatsapp' => $whatsAppResult,
        ];
    }

    public function regenCoupon(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'verification')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Verification permission required.',
            ];
        }

        $phone = Validator::digitsOnly((string) ($data['phone'] ?? ''), 10);
        if (!Validator::phone($phone)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Valid 10-digit phone is required.',
            ];
        }

        $lead = $this->leads->findLatestByPhone($phone);
        if (!$lead) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'No record found for this phone.',
            ];
        }

        $reward = $this->activeRewardState($lead);

        if ($this->isWinningPrize((string) ($lead['prize'] ?? ''))) {
            $couponCode = $this->generateCouponCode($phone);
            $this->leads->updateCouponCode((int) $lead['id'], $couponCode);
            return [
                'ok' => true,
                'action' => 'regen_coupon',
                'couponCode' => $couponCode,
                'prize' => (string) ($lead['prize'] ?? ''),
                'rewardSource' => 'winner',
                'message' => 'Winner coupon regenerated.',
            ];
        }

        $selectedGift = trim((string) ($data['giftItem'] ?? $data['giftOverride'] ?? ''));
        if ($selectedGift === '' && $reward['source'] !== 'surprise') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'Select a surprise reward before issuing a coupon to a Try Again customer.',
            ];
        }

        $rewardLabel = $selectedGift !== '' ? $selectedGift : $reward['label'];
        $couponCode = $this->generateCouponCode($phone);
        $issuedBy = (string) ($auth['user']['username'] ?? 'system');
        $this->leads->issueSurpriseReward((int) $lead['id'], $rewardLabel, $couponCode, $issuedBy);
        $whatsAppResult = $this->whatsapp->triggerEvent('try_again_surprise_issued', (string) ($lead['phone'] ?? ''), [
            'customerName' => (string) ($lead['name'] ?? ''),
            'rewardLabel' => $rewardLabel,
            'couponCode' => $couponCode,
        ], [
            'leadId' => (int) ($lead['id'] ?? 0),
            'countryCode' => '91',
        ]);

        return [
            'ok' => true,
            'action' => 'issue_surprise_coupon',
            'couponCode' => $couponCode,
            'prize' => $rewardLabel,
            'rewardSource' => 'surprise',
            'whatsapp' => $whatsAppResult,
            'message' => ($whatsAppResult['success'] ?? false)
                ? 'Surprise coupon issued and WhatsApp message sent.'
                : 'Surprise coupon issued. WhatsApp message was not sent automatically.',
        ];
    }

    public function counter(): array
    {
        return [
            'ok'    => true,
            'count' => $this->leads->countRows(),
        ];
    }

    public function qrScanClient(array $data): array
    {
        $last = $this->qrScans->getLastScanNumber();
        $next = $last + 1;

        $channel = $this->normalizeQrChannel((string) ($data['channel'] ?? 'customer'));
        $qrSlug = $this->normalizeQrSlug((string) ($data['qrSlug'] ?? $data['qr_slug'] ?? ''));
        $qrId = isset($data['qrId']) ? (int) $data['qrId'] : (isset($data['qr_id']) ? (int) $data['qr_id'] : 0);
        $destinationKey = trim((string) ($data['destinationKey'] ?? $data['destination_key'] ?? ''));
        $destinationLabel = trim((string) ($data['destinationLabel'] ?? $data['destination_label'] ?? ''));
        $resolvedUrl = trim((string) ($data['resolvedUrl'] ?? $data['resolved_url'] ?? ''));

        $this->qrScans->create([
            'scan_number' => $next,
            'channel'     => $channel,
            'qr_id'       => $qrId > 0 ? $qrId : null,
            'qr_slug'     => $qrSlug !== '' ? $qrSlug : null,
            'destination_key' => $destinationKey !== '' ? $destinationKey : null,
            'destination_label' => $destinationLabel !== '' ? $destinationLabel : null,
            'resolved_url' => $resolvedUrl !== '' ? $resolvedUrl : null,
            'user_agent'  => (string) ($data['userAgent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? ''),
            'referer'     => (string) ($data['referer'] ?? $_SERVER['HTTP_REFERER'] ?? ''),
            'ip_address'  => (string) ($data['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? ''),
            'city'        => (string) ($data['city'] ?? ''),
            'region'      => (string) ($data['region'] ?? ''),
            'country'     => (string) ($data['country'] ?? ''),
            'device'      => (string) ($data['device'] ?? ''),
            'browser'     => (string) ($data['browser'] ?? ''),
            'os'          => (string) ($data['os'] ?? ''),
            'language'    => (string) ($data['language'] ?? ''),
            'screen'      => (string) ($data['screen'] ?? ''),
        ]);

        return [
            'ok'         => true,
            'action'     => 'qr_scan_client',
            'scanNumber' => $next,
            'channel'    => $channel,
            'qrSlug'     => $qrSlug,
            'emailTriggerInterval' => Constants::EMAIL_SCAN_INTERVAL,
        ];
    }

    public function resolveQrRedirect(array $data): array
    {
        $slug = $this->normalizeQrSlug((string) ($data['slug'] ?? $data['qrSlug'] ?? $data['qr_slug'] ?? ''));
        if ($slug !== '') {
            $resolved = $this->resolveQrSlug($slug);

            return [
                'ok' => true,
                'action' => 'qr_redirect_resolve',
                'slug' => $slug,
                'qrId' => (int) ($resolved['qrId'] ?? 0),
                'qrSlug' => (string) ($resolved['qrSlug'] ?? $slug),
                'qrName' => (string) ($resolved['qrName'] ?? ''),
                'channel' => (string) ($resolved['channel'] ?? 'customer'),
                'destinationMode' => $resolved['destinationMode'],
                'destinationKey' => $resolved['destinationKey'],
                'destinationLabel' => $resolved['destinationLabel'],
                'resolvedUrl' => $resolved['resolvedUrl'],
                'manualUrl' => $resolved['manualUrl'],
                'fallbackUsed' => $resolved['fallbackUsed'],
            ];
        }

        $channel = $this->normalizeQrChannel((string) ($data['channel'] ?? 'customer'));
        $resolved = $this->resolveQrChannel($channel);

        return [
            'ok' => true,
            'action' => 'qr_redirect_resolve',
            'channel' => $channel,
            'destinationMode' => $resolved['destinationMode'],
            'destinationKey' => $resolved['destinationKey'],
            'destinationLabel' => $resolved['destinationLabel'],
            'resolvedUrl' => $resolved['resolvedUrl'],
            'manualUrl' => $resolved['manualUrl'],
            'fallbackUsed' => $resolved['fallbackUsed'],
        ];
    }

    public function qrReport(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        $isAdmin = (bool) ($auth['ok'] ?? false);

        $rows = $this->qrScans->listLatest(500);
        $groupedRows = $this->qrScans->listGroupedByQr();
        $totalScans = $this->qrScans->countRows();
        $channelCounts = $this->qrScans->getChannelCounts();
        $source = 'mysql';

        $recentScans = array_map([$this, 'formatQrRowForReport'], $rows);
        $qrSummary = array_map([$this, 'formatQrSummaryRowForReport'], $groupedRows);

        $response = [
            'ok'    => true,
            'count' => $totalScans,
            'totalScans' => $totalScans,
            'channelCounts' => $channelCounts,
            'qrSummary' => $qrSummary,
            'recentScans' => $recentScans,
            'source' => $source,
        ];

        // Keep detailed associative rows restricted to authenticated admins.
        if ($isAdmin) {
            $response['rows'] = $rows;
        }

        return $response;
    }

    public function initSchema(): array
    {
        $db = Database::connection();
        $tables = ['leads', 'qr_scans', 'events', 'event_transactions'];
        $schema = [];

        foreach ($tables as $table) {
            $stmt = $db->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table ORDER BY ORDINAL_POSITION');
            $stmt->execute([':table' => $table]);
            $cols = $stmt->fetchAll() ?: [];
            $schema[$table] = array_values(array_map(
                static fn(array $row): string => (string) ($row['COLUMN_NAME'] ?? ''),
                $cols
            ));
        }

        return [
            'ok' => true,
            'result' => 'schema_initialized',
            'headers' => $schema,
        ];
    }

    public function ensureQrSheet(): array
    {
        return [
            'ok' => true,
            'result' => 'qr_sheet_ready',
            'sheetName' => 'qr_scans',
            'totalScans' => $this->qrScans->countRows(),
        ];
    }

    public function dashboardStats(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        return [
            'ok'    => true,
            'stats' => [
                'todayScans'      => $this->qrScans->countToday(),
                'totalScans'      => $this->qrScans->countRows(),
                'customerScans'   => $this->qrScans->countRows('customer'),
                'adminScans'      => $this->qrScans->countRows('admin'),
                'totalTryagin'    => $this->leads->countTryagin(),
                'totalCouponsWon' => $this->leads->countCouponsWon(),
                'totalRedeemed'   => $this->leads->countRedeemed(),
            ],
        ];
    }

    public function addTestQrScan(array $data): array
    {
        $scan = $this->qrScanClient([
            'userAgent' => (string) ($data['userAgent'] ?? 'TestAgent/1.0'),
            'referer' => (string) ($data['referer'] ?? 'https://localhost/test'),
            'ip' => (string) ($data['ip'] ?? '127.0.0.1'),
            'city' => (string) ($data['city'] ?? 'Kalyan'),
            'region' => (string) ($data['region'] ?? 'Maharashtra'),
            'country' => (string) ($data['country'] ?? 'IN'),
            'device' => (string) ($data['device'] ?? 'desktop'),
            'browser' => (string) ($data['browser'] ?? 'Chrome'),
            'os' => (string) ($data['os'] ?? 'Windows'),
            'language' => (string) ($data['language'] ?? 'en-IN'),
            'screen' => (string) ($data['screen'] ?? '1920x1080'),
        ]);

        return [
            'ok' => (bool) ($scan['ok'] ?? false),
            'result' => 'added',
            'scanNumber' => (int) ($scan['scanNumber'] ?? 0),
        ];
    }

    public function addTest25Coupon(array $data): array
    {
        $phone = $this->testPhone((string) ($data['phone'] ?? ''), '8');
        $name = trim((string) ($data['name'] ?? 'Test 25 Coupon'));
        $source = trim((string) ($data['source'] ?? 'manual-test-25'));

        $leadId = $this->leads->create([
            'name' => $name,
            'phone' => $phone,
            'prize' => '25% OFF',
            'status' => 'Unredeemed',
            'date_of_birth' => $this->safeDate($data['dateOfBirth'] ?? '1998-01-01'),
            'date_of_anniversary' => $this->safeDate($data['dateOfAnniversary'] ?? '2021-01-01'),
            'source' => $source,
            'visit_count' => 1,
            'coupon_code' => $this->generateCouponCode($phone),
            'crm_sync_status' => 'Skipped',
            'crm_sync_code' => 'MANUAL_TEST',
            'crm_sync_message' => 'Manually seeded test coupon in PHP runtime.',
        ]);

        $lead = $this->leads->findLatestByPhone($phone);

        return [
            'ok' => true,
            'row' => $leadId,
            'name' => $name,
            'phone' => $phone,
            'prize' => '25% OFF',
            'status' => 'Unredeemed',
            'couponCode' => (string) ($lead['coupon_code'] ?? ''),
            'visitCount' => 1,
        ];
    }

    public function addTestLead(array $data): array
    {
        $name = trim((string) ($data['name'] ?? 'Test'));
        $phone = $this->testPhone((string) ($data['phone'] ?? ''), '9');
        $source = trim((string) ($data['source'] ?? 'manual-crm-confirmed'));

        $leadId = $this->leads->create([
            'name' => $name,
            'phone' => $phone,
            'prize' => $this->pickPrizeByVisitCount(1),
            'status' => 'Unredeemed',
            'source' => $source,
            'visit_count' => 1,
            'coupon_code' => $this->generateCouponCode($phone),
            'crm_sync_status' => (string) ($data['crmStatus'] ?? 'Success'),
            'crm_sync_code' => (string) ($data['crmCode'] ?? '200'),
            'crm_sync_message' => (string) ($data['crmMessage'] ?? 'Manual entry after CRM API success'),
        ]);

        return [
            'ok' => true,
            'result' => 'added',
            'row' => $leadId,
            'phone' => $phone,
            'name' => $name,
            'crmSync' => [
                'attempted' => false,
                'success' => true,
                'status' => (string) ($data['crmCode'] ?? '200'),
                'message' => (string) ($data['crmMessage'] ?? 'Manual entry after CRM API success'),
            ],
        ];
    }

    public function adminCrmPanelStatus(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        return [
            'ok' => true,
            'result' => 'crm_panel_status',
            'config' => [
                'endpoint' => $this->crm->getEndpoint(),
                'tokenConfigured' => $this->crm->isConfigured(),
                'publicSiteUrl' => SiteUrl::resolve('home'),
                'adminPanelUrl' => SiteUrl::resolve('admin'),
                'caBundleConfigured' => $this->crm->hasCaBundle(),
                'caBundleLabel' => $this->crm->hasCaBundle()
                    ? 'Configured for server TLS validation'
                    : 'Missing',
            ],
            'summary' => [
                'contacts' => $this->contacts->countFiltered([]),
                'pushLogs' => $this->crmPushLogs->countFiltered([]),
            ],
        ];
    }

    public function adminTestCrmSync(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $prepared = $this->prepareLeadInput($data, 'admin-crm-panel');
        if ($prepared['name'] === '' || !Validator::phone($prepared['phone'])) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'Valid name and 10-digit phone are required.',
            ];
        }

        $submitPayload = [
            'name' => $prepared['name'],
            'phone' => $prepared['phone'],
            'countryCode' => $prepared['countryCode'],
            'dateOfBirth' => $prepared['dateOfBirth'],
            'dateOfAnniversary' => $prepared['dateOfAnniversary'],
            'source' => $prepared['source'],
        ];

        $preview = $this->crm->previewLeadPayload([
            'name' => $prepared['name'],
            'phone' => $prepared['phone'],
            'country_code' => $prepared['countryCode'],
            'status' => 'Unredeemed',
            'source' => $prepared['source'],
            'visit_count' => 1,
            'date_of_birth' => $prepared['dateOfBirth'],
            'date_of_anniversary' => $prepared['dateOfAnniversary'],
        ]);

        $result = $this->submitLead($submitPayload);
        if (!($result['ok'] ?? false)) {
            return $result;
        }

        $storedLead = $this->leads->findById((int) ($result['row'] ?? 0));

        return [
            'ok' => true,
            'result' => 'crm_test_submitted',
            'received' => [
                'name' => $prepared['name'],
                'phone' => $prepared['phone'],
                'countryCode' => $prepared['countryCode'],
                'dateOfBirth' => $prepared['dateOfBirth'],
                'dateOfAnniversary' => $prepared['dateOfAnniversary'],
                'source' => $prepared['source'],
            ],
            'crmRequest' => $preview,
            'storedLead' => $storedLead ? $this->formatLeadForCrmPanel($storedLead) : null,
            'storedContact' => $this->contacts->findByPhone($prepared['phone']),
            'crmSync' => $result['crmSync'] ?? null,
        ];
    }

    public function adminDeleteCrmTestLead(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $leadId = (int) ($data['leadId'] ?? 0);
        if ($leadId <= 0) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'Valid leadId is required.',
            ];
        }

        $lead = $this->leads->findById($leadId);
        if (!$lead) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Lead not found.',
            ];
        }

        $source = strtolower(trim((string) ($lead['source'] ?? '')));
        $allowedSources = ['admin-crm-panel', 'admin-crm-panel-cli'];
        if (!in_array($source, $allowedSources, true)) {
            return [
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Only CRM panel test leads can be deleted with this action.',
            ];
        }

        $leadSummary = $this->formatLeadForCrmPanel($lead);
        $this->leads->deleteById($leadId);
        $remainingLead = $this->leads->findLatestByPhone((string) ($lead['phone'] ?? ''));
        if ($remainingLead) {
            $this->upsertCanonicalContact((int) ($remainingLead['id'] ?? 0), [
                'name' => (string) ($remainingLead['name'] ?? ''),
                'phone' => (string) ($remainingLead['phone'] ?? ''),
                'source' => (string) ($remainingLead['source'] ?? 'menu-blocker-web'),
                'date_of_birth' => $this->safeDate($remainingLead['date_of_birth'] ?? null),
                'date_of_anniversary' => $this->safeDate($remainingLead['date_of_anniversary'] ?? null),
                'created_at' => (string) ($remainingLead['created_at'] ?? date('Y-m-d H:i:s')),
                'crm_sync_status' => (string) ($remainingLead['crm_sync_status'] ?? 'Pending'),
                'crm_sync_code' => (string) ($remainingLead['crm_sync_code'] ?? ''),
                'crm_sync_message' => (string) ($remainingLead['crm_sync_message'] ?? ''),
            ]);
        } else {
            $this->contacts->deleteByPhone((string) ($lead['phone'] ?? ''));
        }

        return [
            'ok' => true,
            'result' => 'crm_test_lead_deleted',
            'deletedLead' => $leadSummary,
        ];
    }

    public function adminListCrmContacts(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $page = max(1, (int) ($data['page'] ?? 1));
        $pageSize = min(100, max(10, (int) ($data['pageSize'] ?? 25)));
        $filters = $this->extractCrmFilters($data);
        $total = $this->contacts->countFiltered($filters);
        $rows = $this->contacts->listFiltered($filters, $page, $pageSize);

        return [
            'ok' => true,
            'result' => 'crm_contacts_list',
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $pageSize)),
            ],
            'contacts' => array_map([$this, 'formatContactForPanel'], $rows),
        ];
    }

    public function adminListCrmPushLogs(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $page = max(1, (int) ($data['page'] ?? 1));
        $pageSize = min(100, max(10, (int) ($data['pageSize'] ?? 25)));
        $filters = $this->extractCrmFilters($data);
        $total = $this->crmPushLogs->countFiltered($filters);
        $rows = $this->crmPushLogs->listFiltered($filters, $page, $pageSize);

        return [
            'ok' => true,
            'result' => 'crm_push_logs_list',
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $pageSize)),
            ],
            'logs' => array_map([$this, 'formatCrmPushLogForPanel'], $rows),
        ];
    }

    public function adminCrmLeadsStatus(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $filters = $this->extractCrmLeadFilters($data);
        $summary = $this->leads->getSummaryFiltered($filters);

        return [
            'ok' => true,
            'result' => 'crm_leads_status',
            'filters' => $filters,
            'summary' => [
                'totalLeads' => (int) ($summary['total_leads'] ?? 0),
                'totalWon' => (int) ($summary['total_won'] ?? 0),
                'totalTryAgain' => (int) ($summary['total_try_again'] ?? 0),
                'totalRedeemed' => (int) ($summary['total_redeemed'] ?? 0),
            ],
        ];
    }

    public function adminListCrmLeads(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $page = max(1, (int) ($data['page'] ?? 1));
        $pageSize = min(100, max(10, (int) ($data['pageSize'] ?? 25)));
        $filters = $this->extractCrmLeadFilters($data);
        $total = $this->leads->countFiltered($filters);
        $rows = $this->leads->listFiltered($filters, $page, $pageSize);

        return [
            'ok' => true,
            'result' => 'crm_leads_list',
            'filters' => $filters,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $pageSize)),
            ],
            'leads' => array_map([$this, 'formatLeadForCrmLeadsPanel'], $rows),
        ];
    }

    public function adminBackfillCrmContacts(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        $rows = $this->leads->listLatestByPhoneSummaries();
        $processed = 0;
        foreach ($rows as $row) {
            $this->upsertCanonicalContact((int) ($row['id'] ?? 0), [
                'name' => (string) ($row['name'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'source' => (string) ($row['source'] ?? 'menu-blocker-web'),
                'date_of_birth' => $this->safeDate($row['date_of_birth'] ?? null),
                'date_of_anniversary' => $this->safeDate($row['date_of_anniversary'] ?? null),
                'created_at' => (string) ($row['created_at'] ?? date('Y-m-d H:i:s')),
                'crm_sync_status' => (string) ($row['crm_sync_status'] ?? 'Pending'),
                'crm_sync_code' => (string) ($row['crm_sync_code'] ?? ''),
                'crm_sync_message' => (string) ($row['crm_sync_message'] ?? ''),
                'total_submissions' => (int) ($row['total_submissions'] ?? 1),
                'first_seen_at' => $row['first_seen_at'] ?? null,
                'last_seen_at' => $row['last_seen_at'] ?? null,
            ]);
            $processed++;
        }

        return [
            'ok' => true,
            'result' => 'crm_contacts_backfilled',
            'processed' => $processed,
        ];
    }

    public function syncCrmByPhone(array $data): array
    {
        $phone = Validator::digitsOnly((string) ($data['phone'] ?? ''), 10);
        if (!Validator::phone($phone)) {
            return [
                'ok' => false,
                'error' => 'INVALID_PHONE',
                'message' => 'Valid 10-digit phone is required.',
            ];
        }

        $lead = $this->leads->findLatestByPhone($phone);
        if (!$lead) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Lead not found for given phone.',
            ];
        }

        $crmSync = $this->syncLeadToCrm((int) $lead['id'], [
            'name' => (string) ($lead['name'] ?? ''),
            'phone' => (string) ($lead['phone'] ?? ''),
            'country_code' => (string) ($data['countryCode'] ?? '91'),
            'prize' => (string) ($lead['prize'] ?? ''),
            'status' => (string) ($lead['status'] ?? 'Unredeemed'),
            'source' => (string) ($lead['source'] ?? 'menu-blocker-web'),
            'visit_count' => (int) ($lead['visit_count'] ?? 1),
            'date_of_birth' => $this->safeDate($lead['date_of_birth'] ?? null),
            'date_of_anniversary' => $this->safeDate($lead['date_of_anniversary'] ?? null),
            'created_at' => (string) ($lead['created_at'] ?? date('Y-m-d H:i:s')),
        ], $this->upsertCanonicalContact((int) ($lead['id'] ?? 0), [
            'name' => (string) ($lead['name'] ?? ''),
            'phone' => (string) ($lead['phone'] ?? ''),
            'source' => (string) ($lead['source'] ?? 'menu-blocker-web'),
            'date_of_birth' => $this->safeDate($lead['date_of_birth'] ?? null),
            'date_of_anniversary' => $this->safeDate($lead['date_of_anniversary'] ?? null),
            'created_at' => (string) ($lead['created_at'] ?? date('Y-m-d H:i:s')),
            'crm_sync_status' => (string) ($lead['crm_sync_status'] ?? 'Pending'),
            'crm_sync_code' => (string) ($lead['crm_sync_code'] ?? ''),
            'crm_sync_message' => (string) ($lead['crm_sync_message'] ?? ''),
        ]));

        return [
            'ok' => true,
            'result' => 'crm_sync_attempted',
            'row' => (int) $lead['id'],
            'phone' => (string) $lead['phone'],
            'crmSync' => $crmSync,
        ];
    }

    private function syncLeadToCrm(int $leadId, array $lead, ?array $contact = null): array
    {
        $crmPreview = $this->crm->previewLeadPayload($lead);
        try {
            $crmSync = $this->crm->pushLead($lead);
        } catch (\Throwable $e) {
            Logger::error('CRM sync threw an exception.', [
                'leadId' => $leadId,
                'error' => $e->getMessage(),
            ]);

            $crmSync = [
                'attempted' => true,
                'success' => false,
                'status' => '',
                'code' => 'EXCEPTION',
                'message' => substr($e->getMessage(), 0, 500),
                'attempts' => [],
            ];
        }

        $status = 'Skipped';
        if ((bool) ($crmSync['attempted'] ?? false)) {
            $status = (bool) ($crmSync['success'] ?? false) ? 'Success' : 'Failed';
        }

        $code = trim((string) ($crmSync['code'] ?? $crmSync['status'] ?? ''));
        $message = trim((string) ($crmSync['message'] ?? ''));
        $this->leads->updateCrmSync($leadId, $status, $code, $message);

        $contactId = (int) ($contact['id'] ?? 0);
        $attemptTimestamp = (bool) ($crmSync['attempted'] ?? false) ? date('Y-m-d H:i:s') : null;
        $successTimestamp = (bool) ($crmSync['success'] ?? false) ? date('Y-m-d H:i:s') : null;
        if ($contactId > 0) {
            $this->contacts->updateLatestSync($contactId, [
                'latest_crm_sync_status' => $status,
                'latest_crm_sync_code' => $code,
                'latest_crm_sync_message' => $message,
                'last_crm_attempted_at' => $attemptTimestamp,
                'last_crm_pushed_at' => $successTimestamp,
            ]);
        }

        $attempts = is_array($crmSync['attempts'] ?? null) ? $crmSync['attempts'] : [];
        $this->crmPushLogs->create([
            'contact_id' => $contactId > 0 ? $contactId : null,
            'lead_id' => $leadId,
            'phone' => (string) ($lead['phone'] ?? ''),
            'contact_name' => (string) ($lead['name'] ?? ''),
            'trigger_source' => (string) ($lead['source'] ?? 'menu-blocker-web'),
            'crm_endpoint' => (string) ($crmPreview['endpoint'] ?? ''),
            'attempted' => (bool) ($crmSync['attempted'] ?? false),
            'success' => (bool) ($crmSync['success'] ?? false),
            'http_code' => $code,
            'retry_count' => max(0, count($attempts) - 1),
            'attempt_count' => count($attempts),
            'response_message' => $message,
            'request_payload_json' => json_encode($crmPreview['payload'] ?? [], JSON_UNESCAPED_SLASHES),
            'attempts_json' => json_encode($attempts, JSON_UNESCAPED_SLASHES),
        ]);

        return [
            'attempted' => (bool) ($crmSync['attempted'] ?? false),
            'success' => (bool) ($crmSync['success'] ?? false),
            'status' => (string) ($crmSync['status'] ?? ''),
            'code' => $code,
            'message' => $message,
            'attempts' => is_array($crmSync['attempts'] ?? null) ? $crmSync['attempts'] : [],
        ];
    }

    private function upsertCanonicalContact(int $leadId, array $lead): array
    {
        $phone = (string) ($lead['phone'] ?? '');
        $stats = $this->leads->getPhoneStats($phone);

        return $this->contacts->upsertByPhone([
            'phone' => $phone,
            'name' => (string) ($lead['name'] ?? ''),
            'date_of_birth' => $lead['date_of_birth'] ?? null,
            'date_of_anniversary' => $lead['date_of_anniversary'] ?? null,
            'first_seen_at' => $lead['first_seen_at'] ?? $stats['first_seen_at'] ?? $lead['created_at'] ?? date('Y-m-d H:i:s'),
            'last_seen_at' => $lead['last_seen_at'] ?? $stats['last_seen_at'] ?? $lead['created_at'] ?? date('Y-m-d H:i:s'),
            'latest_source' => (string) ($lead['source'] ?? 'menu-blocker-web'),
            'latest_lead_id' => $leadId > 0 ? $leadId : null,
            'latest_lead_created_at' => $lead['created_at'] ?? null,
            'total_submissions' => (int) ($lead['total_submissions'] ?? $stats['total_submissions'] ?? 1),
            'latest_crm_sync_status' => (string) ($lead['crm_sync_status'] ?? 'Pending'),
            'latest_crm_sync_code' => (string) ($lead['crm_sync_code'] ?? ''),
            'latest_crm_sync_message' => (string) ($lead['crm_sync_message'] ?? ''),
            'last_crm_attempted_at' => $lead['last_crm_attempted_at'] ?? null,
            'last_crm_pushed_at' => $lead['last_crm_pushed_at'] ?? null,
        ]);
    }

    private function extractCrmFilters(array $data): array
    {
        return [
            'search' => trim((string) ($data['search'] ?? '')),
            'source' => trim((string) ($data['source'] ?? '')),
            'syncStatus' => trim((string) ($data['syncStatus'] ?? '')),
            'fromDate' => trim((string) ($data['fromDate'] ?? '')),
            'toDate' => trim((string) ($data['toDate'] ?? '')),
        ];
    }

    private function extractCrmLeadFilters(array $data): array
    {
        return [
            'search' => trim((string) ($data['search'] ?? '')),
            'source' => trim((string) ($data['source'] ?? '')),
            'syncStatus' => trim((string) ($data['syncStatus'] ?? '')),
            'leadStatus' => trim((string) ($data['leadStatus'] ?? '')),
            'outcome' => trim((string) ($data['outcome'] ?? '')),
            'fromDate' => trim((string) ($data['fromDate'] ?? '')),
            'toDate' => trim((string) ($data['toDate'] ?? '')),
        ];
    }

    private function prepareLeadInput(array $data, string $defaultSource = 'menu-blocker-web'): array
    {
        $source = trim((string) ($data['source'] ?? $defaultSource));
        if ($source === '') {
            $source = $defaultSource;
        }

        return [
            'name' => trim((string) ($data['name'] ?? '')),
            'countryCode' => Validator::digitsOnly((string) ($data['countryCode'] ?? '91'), 4),
            'phone' => Validator::digitsOnly((string) ($data['phone'] ?? ''), 10),
            'dateOfBirth' => $this->safeDate($data['dateOfBirthIso'] ?? $data['dateOfBirth'] ?? null),
            'dateOfAnniversary' => $this->safeDate($data['dateOfAnniversaryIso'] ?? $data['dateOfAnniversary'] ?? null),
            'source' => $source,
        ];
    }

    private function buildSpinCooldownState(?array $lead): array
    {
        if (!$lead) {
            return [
                'active' => false,
                'retryAfter' => '',
                'retryAfterEpochMs' => 0,
                'remainingMs' => 0,
                'remainingMinutes' => 0,
            ];
        }

        $completedAtRaw = trim((string) ($lead['spin_completed_at'] ?? ''));
        $completedAtTs = $completedAtRaw !== '' ? strtotime($completedAtRaw) : false;
        if ($completedAtTs === false) {
            return [
                'active' => false,
                'retryAfter' => '',
                'retryAfterEpochMs' => 0,
                'remainingMs' => 0,
                'remainingMinutes' => 0,
            ];
        }

        $retryAfterTs = strtotime('+' . Constants::SPIN_COOLDOWN_HOURS . ' hours', $completedAtTs);
        $remainingMs = max(0, (($retryAfterTs ?: $completedAtTs) * 1000) - ((int) round(microtime(true) * 1000)));

        return [
            'active' => $remainingMs > 0,
            'retryAfter' => date('Y-m-d H:i:s', $retryAfterTs ?: $completedAtTs),
            'retryAfterEpochMs' => ($retryAfterTs ?: $completedAtTs) * 1000,
            'remainingMs' => $remainingMs,
            'remainingMinutes' => (int) ceil($remainingMs / 60000),
        ];
    }

    private function formatSpinLeadSummary(array $lead): array
    {
        return [
            'id' => (int) ($lead['id'] ?? 0),
            'name' => (string) ($lead['name'] ?? ''),
            'phone' => (string) ($lead['phone'] ?? ''),
            'prize' => (string) ($lead['prize'] ?? ''),
            'couponCode' => (string) ($lead['coupon_code'] ?? ''),
            'status' => (string) ($lead['status'] ?? ''),
            'createdAt' => (string) ($lead['created_at'] ?? ''),
            'spinCompletedAt' => (string) ($lead['spin_completed_at'] ?? ''),
        ];
    }

    private function formatLeadForCrmPanel(array $lead): array
    {
        return [
            'id' => (int) ($lead['id'] ?? 0),
            'createdAt' => (string) ($lead['created_at'] ?? ''),
            'name' => (string) ($lead['name'] ?? ''),
            'phone' => (string) ($lead['phone'] ?? ''),
            'prize' => (string) ($lead['prize'] ?? ''),
            'status' => (string) ($lead['status'] ?? ''),
            'couponCode' => (string) ($lead['coupon_code'] ?? ''),
            'visitCount' => (int) ($lead['visit_count'] ?? 0),
            'dateOfBirth' => (string) ($lead['date_of_birth'] ?? ''),
            'dateOfAnniversary' => (string) ($lead['date_of_anniversary'] ?? ''),
            'source' => (string) ($lead['source'] ?? ''),
            'crmSyncStatus' => (string) ($lead['crm_sync_status'] ?? ''),
            'crmSyncCode' => (string) ($lead['crm_sync_code'] ?? ''),
            'crmSyncMessage' => (string) ($lead['crm_sync_message'] ?? ''),
        ];
    }

    private function formatLeadForCrmLeadsPanel(array $lead): array
    {
        $prize = (string) ($lead['prize'] ?? '');

        return [
            'id' => (int) ($lead['id'] ?? 0),
            'createdAt' => (string) ($lead['created_at'] ?? ''),
            'name' => (string) ($lead['name'] ?? ''),
            'phone' => (string) ($lead['phone'] ?? ''),
            'prize' => $prize,
            'outcomeBadge' => $this->deriveLeadOutcomeBadge($prize),
            'status' => (string) ($lead['status'] ?? ''),
            'couponCode' => (string) ($lead['coupon_code'] ?? ''),
            'visitCount' => (int) ($lead['visit_count'] ?? 0),
            'dateOfBirth' => (string) ($lead['date_of_birth'] ?? ''),
            'dateOfAnniversary' => (string) ($lead['date_of_anniversary'] ?? ''),
            'source' => (string) ($lead['source'] ?? ''),
            'redeemedAt' => (string) ($lead['redeemed_at'] ?? ''),
            'crmSyncStatus' => (string) ($lead['crm_sync_status'] ?? ''),
            'crmSyncCode' => (string) ($lead['crm_sync_code'] ?? ''),
            'crmSyncMessage' => (string) ($lead['crm_sync_message'] ?? ''),
        ];
    }

    private function formatContactForPanel(array $contact): array
    {
        return [
            'id' => (int) ($contact['id'] ?? 0),
            'phone' => (string) ($contact['phone'] ?? ''),
            'name' => (string) ($contact['name'] ?? ''),
            'dateOfBirth' => (string) ($contact['date_of_birth'] ?? ''),
            'dateOfAnniversary' => (string) ($contact['date_of_anniversary'] ?? ''),
            'firstSeenAt' => (string) ($contact['first_seen_at'] ?? ''),
            'lastSeenAt' => (string) ($contact['last_seen_at'] ?? ''),
            'latestSource' => (string) ($contact['latest_source'] ?? ''),
            'latestLeadId' => (int) ($contact['latest_lead_id'] ?? 0),
            'latestLeadCreatedAt' => (string) ($contact['latest_lead_created_at'] ?? ''),
            'totalSubmissions' => (int) ($contact['total_submissions'] ?? 0),
            'latestCrmSyncStatus' => (string) ($contact['latest_crm_sync_status'] ?? ''),
            'latestCrmSyncCode' => (string) ($contact['latest_crm_sync_code'] ?? ''),
            'latestCrmSyncMessage' => (string) ($contact['latest_crm_sync_message'] ?? ''),
            'lastCrmAttemptedAt' => (string) ($contact['last_crm_attempted_at'] ?? ''),
            'lastCrmPushedAt' => (string) ($contact['last_crm_pushed_at'] ?? ''),
        ];
    }

    private function formatCrmPushLogForPanel(array $log): array
    {
        $payload = $this->decodeJsonField($log['request_payload_json'] ?? null);
        $attempts = $this->decodeJsonField($log['attempts_json'] ?? null);

        return [
            'id' => (int) ($log['id'] ?? 0),
            'createdAt' => (string) ($log['created_at'] ?? ''),
            'contactId' => (int) ($log['contact_id'] ?? 0),
            'leadId' => (int) ($log['lead_id'] ?? 0),
            'phone' => (string) ($log['phone'] ?? ''),
            'contactName' => (string) ($log['contact_name'] ?? ''),
            'triggerSource' => (string) ($log['trigger_source'] ?? ''),
            'crmEndpoint' => (string) ($log['crm_endpoint'] ?? ''),
            'attempted' => (bool) ($log['attempted'] ?? false),
            'success' => (bool) ($log['success'] ?? false),
            'httpCode' => (string) ($log['http_code'] ?? ''),
            'retryCount' => (int) ($log['retry_count'] ?? 0),
            'attemptCount' => (int) ($log['attempt_count'] ?? 0),
            'responseMessage' => (string) ($log['response_message'] ?? ''),
            'requestPayload' => $payload,
            'attempts' => $attempts,
        ];
    }

    private function decodeJsonField($value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function deriveLeadOutcomeBadge(string $prize): string
    {
        $normalized = trim($prize);
        if ($normalized === '') {
            return 'Pending';
        }

        if (stripos($normalized, 'Try Again') !== false) {
            return 'Try Again';
        }

        return 'Won';
    }

    private function activeRewardState(array $lead): array
    {
        $surpriseRewardLabel = trim((string) ($lead['surprise_reward_label'] ?? ''));
        $surpriseCouponCode = trim((string) ($lead['surprise_coupon_code'] ?? ''));
        $surpriseRedeemedAt = trim((string) ($lead['surprise_redeemed_at'] ?? ''));

        if ($surpriseRewardLabel !== '' && $surpriseCouponCode !== '') {
            return [
                'source' => 'surprise',
                'label' => $surpriseRewardLabel,
                'couponCode' => $surpriseCouponCode,
                'canRedeem' => $surpriseRedeemedAt === '',
            ];
        }

        $prize = trim((string) ($lead['prize'] ?? ''));
        if ($this->isWinningPrize($prize)) {
            return [
                'source' => 'winner',
                'label' => $prize,
                'couponCode' => trim((string) ($lead['coupon_code'] ?? '')),
                'canRedeem' => strtolower(trim((string) ($lead['status'] ?? ''))) !== 'redeemed',
            ];
        }

        return [
            'source' => 'none',
            'label' => '',
            'couponCode' => '',
            'canRedeem' => false,
        ];
    }

    private function canIssueSurprise(array $lead): bool
    {
        if ($this->isWinningPrize((string) ($lead['prize'] ?? ''))) {
            return false;
        }

        return trim((string) ($lead['surprise_reward_label'] ?? '')) === ''
            || trim((string) ($lead['surprise_redeemed_at'] ?? '')) !== '';
    }

    private function formatQrRowForReport(array $row): array
    {
        return [
            (string) ($row['scanned_at'] ?? ''),
            (string) ($row['user_agent'] ?? ''),
            (string) ($row['referer'] ?? ''),
            (string) ($row['ip_address'] ?? ''),
            (string) ($row['scan_number'] ?? ''),
            (string) ($row['channel'] ?? ''),
            (string) ($row['qr_slug'] ?? ''),
            (string) ($row['destination_key'] ?? ''),
            (string) ($row['destination_label'] ?? ''),
            (string) ($row['resolved_url'] ?? ''),
            (string) ($row['city'] ?? ''),
            (string) ($row['region'] ?? ''),
            (string) ($row['country'] ?? ''),
            (string) ($row['device'] ?? ''),
            (string) ($row['browser'] ?? ''),
            (string) ($row['os'] ?? ''),
            (string) ($row['language'] ?? ''),
            (string) ($row['screen'] ?? ''),
        ];
    }

    private function formatQrSummaryRowForReport(array $row): array
    {
        $slug = trim((string) ($row['qr_slug'] ?? ''));
        $channel = trim((string) ($row['channel'] ?? ''));
        $label = trim((string) ($row['destination_label'] ?? ''));

        if ($label === '') {
            $label = $slug !== '' ? $slug : ($channel !== '' ? strtoupper($channel) . ' QR' : 'Unknown QR');
        }

        return [
            'qrKey' => (string) ($row['qr_key'] ?? ''),
            'qrId' => isset($row['qr_id']) ? (int) $row['qr_id'] : 0,
            'qrSlug' => $slug,
            'channel' => $channel,
            'destinationKey' => (string) ($row['destination_key'] ?? ''),
            'destinationLabel' => $label,
            'resolvedUrl' => (string) ($row['resolved_url'] ?? ''),
            'totalScans' => isset($row['total_scans']) ? (int) $row['total_scans'] : 0,
            'lastScannedAt' => (string) ($row['last_scanned_at'] ?? ''),
            'firstScannedAt' => (string) ($row['first_scanned_at'] ?? ''),
        ];
    }

    private function safeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $matches) === 1) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
            return null;
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $raw, $matches) === 1) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) $matches[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function pickPrizeByLeadNumber(int $leadNumber): string
    {
        if ($leadNumber > 0 && $leadNumber % 500 === 0) {
            return '25% OFF';
        }

        if ($leadNumber > 0 && $leadNumber % 300 === 0) {
            return '20% OFF';
        }

        if ($leadNumber > 0 && $leadNumber % 125 === 0) {
            return '15% OFF';
        }

        if ($leadNumber > 0 && $leadNumber % 51 === 0) {
            return '10% OFF';
        }

        if ($leadNumber > 0 && $leadNumber % 50 === 49) {
            return 'Starter on the House';
        }

        if ($leadNumber >= 18 && ($leadNumber - 18) % 10 === 0) {
            $cycleIndex = (int) floor(($leadNumber - 18) / 10);
            return $cycleIndex % 2 === 0 ? 'Dessert on the House' : 'Aerated Drink on the House';
        }

        if ($leadNumber > 0 && $leadNumber % 10 === 0) {
            return 'Mocktail on the House';
        }

        return 'Try Again';
    }

    private function isWinningPrize(string $prize): bool
    {
        return stripos(trim($prize), 'Try Again') === false && trim($prize) !== '';
    }

    private function generateCouponCode(string $phone): string
    {
        $suffix = substr($phone, -4);
        $rand = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        return 'NK' . $suffix . $rand;
    }

    private function testPhone(string $incoming, string $leadingDigit): string
    {
        $digits = Validator::digitsOnly($incoming, 10);
        if (Validator::phone($digits)) {
            return $digits;
        }

        $randomTail = str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        return $leadingDigit . $randomTail;
    }

    private function normalizeQrChannel(string $channel): string
    {
        $normalized = strtolower(trim($channel));
        return $normalized === 'admin' ? 'admin' : 'customer';
    }

    private function normalizeQrSlug(string $slug): string
    {
        $normalized = strtolower(trim($slug));
        $normalized = preg_replace('/[^a-z0-9\-]+/i', '-', $normalized) ?? '';
        return trim($normalized, '-');
    }

    private function resolveQrChannel(string $channel): array
    {
        $registryRow = $this->qrRedirects->findByLegacyChannel($channel);
        if ($registryRow) {
            $resolved = $this->resolveQrRecord($registryRow);
            $resolved['channel'] = $channel;
            return $resolved;
        }

        $row = $this->qrRedirectSettings->findByChannel($channel);
        $fallbackKey = $channel === 'admin' ? 'admin' : 'menu';
        $mode = strtolower(trim((string) ($row['destination_mode'] ?? 'preset')));
        $key = strtolower(trim((string) ($row['destination_key'] ?? $fallbackKey)));
        $manualUrl = trim((string) ($row['manual_url'] ?? ''));
        $fallbackUsed = false;

        $presetMap = [
            'home' => ['label' => 'Home Page', 'url' => SiteUrl::resolve('home')],
            'menu' => ['label' => 'Food Menu', 'url' => SiteUrl::resolve('menu')],
            'cocktail' => ['label' => 'Cocktail Menu', 'url' => SiteUrl::resolve('cocktail')],
            'admin' => ['label' => 'Admin Portal', 'url' => SiteUrl::resolve('admin')],
        ];

        if ($mode === 'manual' && $this->isValidHttpsUrl($manualUrl)) {
            return [
                'destinationMode' => 'manual',
                'destinationKey' => 'manual',
                'destinationLabel' => 'Manual URL',
                'resolvedUrl' => $manualUrl,
                'manualUrl' => $manualUrl,
                'fallbackUsed' => false,
            ];
        }

        $allowedKeys = $channel === 'admin' ? ['admin'] : ['home', 'menu', 'cocktail'];
        if (!in_array($key, $allowedKeys, true)) {
            $key = $fallbackKey;
            $fallbackUsed = true;
        }

        $preset = $presetMap[$key] ?? $presetMap[$fallbackKey];

        return [
            'destinationMode' => 'preset',
            'destinationKey' => $key,
            'destinationLabel' => $preset['label'],
            'resolvedUrl' => $preset['url'],
            'manualUrl' => '',
            'fallbackUsed' => $fallbackUsed || ($mode === 'manual' && $manualUrl !== ''),
            'channel' => $channel,
            'qrId' => 0,
            'qrSlug' => '',
            'qrName' => '',
        ];
    }

    private function resolveQrSlug(string $slug): array
    {
        $row = $this->qrRedirects->findBySlug($slug);
        if ($row) {
            return $this->resolveQrRecord($row);
        }

        return [
            'destinationMode' => 'preset',
            'destinationKey' => 'menu',
            'destinationLabel' => 'Food Menu',
            'resolvedUrl' => SiteUrl::resolve('menu'),
            'manualUrl' => '',
            'fallbackUsed' => true,
            'channel' => 'customer',
            'qrId' => 0,
            'qrSlug' => $slug,
            'qrName' => '',
        ];
    }

    private function resolveQrRecord(array $row): array
    {
        $fallbackKey = (($row['legacy_channel'] ?? '') === 'admin') ? 'admin' : 'menu';
        $mode = strtolower(trim((string) ($row['redirect_mode'] ?? 'preset')));
        $key = strtolower(trim((string) ($row['preset_key'] ?? $fallbackKey)));
        $manualUrl = trim((string) ($row['manual_url'] ?? ''));
        $isActive = isset($row['is_active']) ? (bool) $row['is_active'] : true;
        $fallbackUsed = false;
        $presetMap = $this->getQrPresetMap();

        if (!$isActive) {
            $mode = 'preset';
            $key = $fallbackKey;
            $manualUrl = '';
            $fallbackUsed = true;
        }

        if ($mode === 'manual' && $this->isValidHttpsUrl($manualUrl)) {
            return [
                'destinationMode' => 'manual',
                'destinationKey' => 'manual',
                'destinationLabel' => 'Manual URL',
                'resolvedUrl' => $manualUrl,
                'manualUrl' => $manualUrl,
                'fallbackUsed' => $fallbackUsed,
                'channel' => (($row['legacy_channel'] ?? '') === 'admin') ? 'admin' : 'customer',
                'qrId' => (int) ($row['id'] ?? 0),
                'qrSlug' => (string) ($row['slug'] ?? ''),
                'qrName' => (string) ($row['name'] ?? ''),
            ];
        }

        if (!array_key_exists($key, $presetMap)) {
            $key = $fallbackKey;
            $fallbackUsed = true;
        }

        $preset = $presetMap[$key] ?? $presetMap[$fallbackKey];

        return [
            'destinationMode' => 'preset',
            'destinationKey' => $key,
            'destinationLabel' => $preset['label'],
            'resolvedUrl' => $preset['url'],
            'manualUrl' => '',
            'fallbackUsed' => $fallbackUsed || ($mode === 'manual' && $manualUrl !== ''),
            'channel' => (($row['legacy_channel'] ?? '') === 'admin') ? 'admin' : 'customer',
            'qrId' => (int) ($row['id'] ?? 0),
            'qrSlug' => (string) ($row['slug'] ?? ''),
            'qrName' => (string) ($row['name'] ?? ''),
        ];
    }

    private function getQrPresetMap(): array
    {
        return array_merge([
            'home' => ['label' => 'Home Page', 'url' => SiteUrl::resolve('home')],
            'menu' => ['label' => 'Food Menu', 'url' => SiteUrl::resolve('menu')],
            'cocktail' => ['label' => 'Cocktail Menu', 'url' => SiteUrl::resolve('cocktail')],
            'admin' => ['label' => 'Admin Portal', 'url' => SiteUrl::resolve('admin')],
            'events' => ['label' => 'All Active Events', 'url' => $this->buildEventsListingUrl()],
        ], $this->buildActiveEventPresetMap());
    }

    private function buildActiveEventPresetMap(): array
    {
        $presets = [];
        foreach ($this->events->listAllActive() as $row) {
            if (!$this->isEventAvailableForQrPreset($row)) {
                continue;
            }

            $eventId = trim((string) ($row['event_id'] ?? ''));
            if ($eventId === '') {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $presets['event:' . $eventId] = [
                'label' => $title !== '' ? ('Event: ' . $title) : ('Event: ' . $eventId),
                'url' => $this->buildEventDetailUrl($eventId),
            ];
        }

        return $presets;
    }

    private function isEventAvailableForQrPreset(array $row): bool
    {
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return false;
        }

        $endDate = trim((string) ($row['end_date'] ?? ''));
        $endTime = trim((string) ($row['end_time'] ?? ''));
        $startDate = trim((string) ($row['start_date'] ?? ''));
        $startTime = trim((string) ($row['start_time'] ?? ''));
        $cutoffDate = $endDate !== '' ? $endDate : $startDate;
        $cutoffTime = $endDate !== '' ? ($endTime !== '' ? $endTime : '23:59:59') : ($startTime !== '' ? $startTime : '23:59:59');

        if ($cutoffDate === '') {
            return true;
        }

        try {
            $cutoff = new \DateTimeImmutable($cutoffDate . ' ' . $cutoffTime, new \DateTimeZone('Asia/Kolkata'));
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
            return $cutoff >= $now;
        } catch (\Throwable) {
            return true;
        }
    }

    private function buildEventsListingUrl(): string
    {
        return rtrim(SiteUrl::resolve('home'), '/') . '/events/';
    }

    private function buildEventDetailUrl(string $eventId): string
    {
        return rtrim(SiteUrl::resolve('home'), '/') . '/events/event.html?eventId=' . rawurlencode($eventId);
    }

    private function isValidHttpsUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $validated = filter_var($url, FILTER_VALIDATE_URL);
        if ($validated === false) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? '')));
        if ($host === '') {
            return false;
        }

        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }
}
