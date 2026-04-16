<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Middleware\AuthMiddleware;
use NK\Repositories\LeadRepository;
use NK\Repositories\QrScanRepository;
use NK\Support\Validator;
use Throwable;

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

        if ($totalScans === 0) {
            $fallback = $this->fetchQrStatsFromLegacySheet();
            if (is_array($fallback)) {
                $rows = $fallback['rows'];
                $totalScans = (int) $fallback['totalScans'];
                $source = 'google_sheets';
            }
        }

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

    private function fetchQrStatsFromLegacySheet(): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $legacyUrl = $this->resolveLegacyAppsScriptUrl();
        if ($legacyUrl === '') {
            return null;
        }

        $json = $this->fetchJson($legacyUrl . '?action=qr_report');
        if (!is_array($json) || empty($json['ok'])) {
            return null;
        }

        $recent = [];
        $incomingRecent = $json['recentScans'] ?? [];
        if (is_array($incomingRecent)) {
            foreach ($incomingRecent as $row) {
                if (is_array($row)) {
                    $recent[] = [
                        'scanned_at' => (string) ($row[0] ?? ''),
                        'user_agent' => (string) ($row[1] ?? ''),
                        'referer' => (string) ($row[2] ?? ''),
                        'ip_address' => (string) ($row[3] ?? ''),
                        'scan_number' => (string) ($row[4] ?? ''),
                        'city' => (string) ($row[5] ?? ''),
                        'region' => (string) ($row[6] ?? ''),
                        'country' => (string) ($row[7] ?? ''),
                        'device' => (string) ($row[8] ?? ''),
                        'browser' => (string) ($row[9] ?? ''),
                        'os' => (string) ($row[10] ?? ''),
                        'language' => (string) ($row[11] ?? ''),
                        'screen' => (string) ($row[12] ?? ''),
                    ];
                }
            }
        }

        return [
            'totalScans' => (int) ($json['totalScans'] ?? count($recent)),
            'rows' => $recent,
        ];
    }

    private function resolveLegacyAppsScriptUrl(): string
    {
        $envUrl = trim((string) ($_ENV['NK_APPS_SCRIPT_URL'] ?? ''));
        if ($this->isAppsScriptUrl($envUrl)) {
            return preg_replace('/\?.*$/', '', $envUrl) ?? $envUrl;
        }

        $dataConfigPath = dirname(__DIR__, 3) . '/data-config.js';
        if (!is_file($dataConfigPath)) {
            return '';
        }

        $content = (string) file_get_contents($dataConfigPath);
        if ($content === '') {
            return '';
        }

        if (preg_match("/legacyAppsScriptUrl\s*:\s*'([^']+)'/", $content, $m)) {
            $value = ltrim(trim((string) ($m[1] ?? '')), '#');
            if ($this->isAppsScriptUrl($value)) {
                return preg_replace('/\?.*$/', '', $value) ?? $value;
            }
        }

        if (preg_match("/appsScriptUrl\s*:\s*'([^']+)'/", $content, $m)) {
            $value = ltrim(trim((string) ($m[1] ?? '')), '#');
            if ($this->isAppsScriptUrl($value)) {
                return preg_replace('/\?.*$/', '', $value) ?? $value;
            }
        }

        return '';
    }

    private function isAppsScriptUrl(string $value): bool
    {
        return $value !== '' && str_contains(strtolower($value), 'script.google.com');
    }

    private function fetchJson(string $url): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $status < 200 || $status >= 300 || $error !== '') {
            return null;
        }

        try {
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
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
}
