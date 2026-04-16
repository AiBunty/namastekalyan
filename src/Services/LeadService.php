<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
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
        if (!$auth['ok']) {
            return $auth;
        }

        $rows = $this->qrScans->listLatest(500);

        return [
            'ok'    => true,
            'rows'  => $rows,
            'count' => count($rows),
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
}
