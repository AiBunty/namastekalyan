<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Config\Database;
use NK\Middleware\AuthMiddleware;
use NK\Repositories\LeadRepository;
use NK\Repositories\QrScanRepository;
use NK\Support\Validator;

class LeadService
{
    private LeadRepository $leads;
    private QrScanRepository $qrScans;

    public function __construct()
    {
        $this->leads = new LeadRepository();
        $this->qrScans = new QrScanRepository();
    }

    public function submitLead(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $countryCode = Validator::digitsOnly((string) ($data['countryCode'] ?? '91'), 4);
        $phone = Validator::digitsOnly((string) ($data['phone'] ?? ''), 10);

        if ($name === '' || !Validator::phone($phone)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Valid name and 10-digit phone are required.',
            ];
        }

        $existing = $this->leads->findLatestByPhone($phone);
        $visitCount = $existing ? ((int) ($existing['visit_count'] ?? 0) + 1) : 1;

        // Keep current behavior deterministic and simple for now.
        $prize = $this->pickPrizeByVisitCount($visitCount);
        $couponCode = $this->generateCouponCode($phone);

        $leadId = $this->leads->create([
            'name'                => $name,
            'phone'               => $phone,
            'prize'               => $prize,
            'status'              => 'Unredeemed',
            'date_of_birth'       => $this->safeDate($data['dateOfBirthIso'] ?? $data['dateOfBirth'] ?? null),
            'date_of_anniversary' => $this->safeDate($data['dateOfAnniversaryIso'] ?? $data['dateOfAnniversary'] ?? null),
            'source'              => (string) ($data['source'] ?? 'menu-blocker-web'),
            'visit_count'         => $visitCount,
            'coupon_code'         => $couponCode,
            'crm_sync_status'     => 'Pending',
        ]);

        return [
            'ok'         => true,
            'result'     => 'success',
            'row'        => $leadId,
            'prize'      => $prize,
            'visitCount' => $visitCount,
            'phone'      => $phone,
            'countryCode'=> $countryCode,
            'couponCode' => $couponCode,
            'crmSync'    => ['status' => 'pending'],
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

        return [
            'ok'        => true,
            'phone'     => $lead['phone'],
            'name'      => $lead['name'],
            'prize'     => $lead['prize'],
            'status'    => $lead['status'],
            'couponCode'=> $lead['coupon_code'],
            'visitCount'=> (int) ($lead['visit_count'] ?? 0),
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

        $this->leads->updateRedemption((int) $lead['id'], true);

        return [
            'ok'      => true,
            'action'  => 'redeem',
            'message' => 'Coupon redeemed successfully.',
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

        $couponCode = $this->generateCouponCode($phone);
        $this->leads->updateCouponCode((int) $lead['id'], $couponCode);

        return [
            'ok'         => true,
            'action'     => 'regen_coupon',
            'couponCode' => $couponCode,
            'message'    => 'Coupon regenerated.',
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

        $this->qrScans->create([
            'scan_number' => $next,
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
            'emailTriggerInterval' => Constants::EMAIL_SCAN_INTERVAL,
        ];
    }

    public function qrReport(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        $isAdmin = (bool) ($auth['ok'] ?? false);

        $rows = $this->qrScans->listLatest(500);
        $totalScans = $this->qrScans->countRows();
        $source = 'mysql';

        $recentScans = array_map([$this, 'formatQrRowForReport'], $rows);

        $response = [
            'ok'    => true,
            'count' => $totalScans,
            'totalScans' => $totalScans,
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

        $this->leads->updateCrmSync(
            (int) $lead['id'],
            'Skipped',
            'PHP_ONLY',
            'CRM sync by phone is deprecated in PHP-only runtime.'
        );

        return [
            'ok' => true,
            'result' => 'crm_sync_attempted',
            'row' => (int) $lead['id'],
            'phone' => (string) $lead['phone'],
            'crmSync' => [
                'attempted' => true,
                'success' => false,
                'status' => 'PHP_ONLY',
                'message' => 'CRM sync by phone is deprecated in PHP-only runtime.',
                'attempts' => [],
            ],
        ];
    }

    private function formatQrRowForReport(array $row): array
    {
        return [
            (string) ($row['scanned_at'] ?? ''),
            (string) ($row['user_agent'] ?? ''),
            (string) ($row['referer'] ?? ''),
            (string) ($row['ip_address'] ?? ''),
            (string) ($row['scan_number'] ?? ''),
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

    private function safeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $ts = strtotime((string) $value);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    private function pickPrizeByVisitCount(int $visitCount): string
    {
        // Keep predictable behavior while still giving variety.
        $cycle = [
            '10% Off on Food Bill',
            'Free Mocktail',
            '15% Off on Main Course',
            'Buy 1 Get 1 on Selected Cocktails',
        ];

        $idx = ($visitCount - 1) % count($cycle);
        return $cycle[$idx];
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
}
