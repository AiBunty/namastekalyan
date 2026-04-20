<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Repositories\EventOtpRepository;

class OtpService
{
    private const OTP_TTL_MINUTES = 10;
    private const OTP_RESEND_COOLDOWN_SECONDS = 60;
    private const OTP_MAX_ATTEMPTS = 5;
    private const VERIFIED_TOKEN_TTL_MINUTES = 30;

    private EventOtpRepository $repo;
    private MailerService $mailer;

    public function __construct()
    {
        $this->repo = new EventOtpRepository();
        $this->mailer = new MailerService();
    }

    public function sendEventOtp(string $eventId, string $email, string $customerName, string $eventTitle): array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $existing = $this->repo->findByEventAndEmail($eventId, $normalizedEmail);
        $now = $this->now();

        if ($existing) {
            $resendAllowedAt = trim((string) ($existing['resend_allowed_at'] ?? ''));
            if ($resendAllowedAt !== '') {
                try {
                    $retryAt = new \DateTimeImmutable($resendAllowedAt, new \DateTimeZone('Asia/Kolkata'));
                    if ($retryAt > $now) {
                        return [
                            'ok' => false,
                            'error' => 'OTP_COOLDOWN',
                            'message' => 'Please wait before requesting another OTP.',
                            'retryAfterSeconds' => max(1, $retryAt->getTimestamp() - $now->getTimestamp()),
                        ];
                    }
                } catch (\Throwable) {
                    // Ignore malformed stored cooldown values.
                }
            }
        }

        $otp = (string) random_int(100000, 999999);
        $emailResult = $this->mailer->sendEventOtp($normalizedEmail, $customerName, $eventTitle, $otp, self::OTP_TTL_MINUTES);
        if (empty($emailResult['ok'])) {
            return $emailResult;
        }

        $requestedAt = $now->format('Y-m-d H:i:s');
        $resendAllowedAt = $now->modify('+' . self::OTP_RESEND_COOLDOWN_SECONDS . ' seconds')->format('Y-m-d H:i:s');
        $expiresAt = $now->modify('+' . self::OTP_TTL_MINUTES . ' minutes')->format('Y-m-d H:i:s');

        $this->repo->storeOtp(
            $eventId,
            $normalizedEmail,
            $customerName,
            $this->hashOtp($eventId, $normalizedEmail, $otp),
            $requestedAt,
            $resendAllowedAt,
            $expiresAt
        );

        return [
            'ok' => true,
            'message' => 'OTP sent to your email address.',
            'expiresInMinutes' => self::OTP_TTL_MINUTES,
            'retryAfterSeconds' => self::OTP_RESEND_COOLDOWN_SECONDS,
        ];
    }

    public function verifyEventOtp(string $eventId, string $email, string $otp): array
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $record = $this->repo->findByEventAndEmail($eventId, $normalizedEmail);
        if (!$record) {
            return ['ok' => false, 'error' => 'OTP_NOT_FOUND', 'message' => 'Request a fresh OTP before verification.'];
        }

        $now = $this->now();
        $expiresAt = trim((string) ($record['expires_at'] ?? ''));
        if ($expiresAt === '') {
            return ['ok' => false, 'error' => 'OTP_EXPIRED', 'message' => 'OTP expired. Please request a fresh OTP.'];
        }

        try {
            $expiry = new \DateTimeImmutable($expiresAt, new \DateTimeZone('Asia/Kolkata'));
            if ($expiry <= $now) {
                return ['ok' => false, 'error' => 'OTP_EXPIRED', 'message' => 'OTP expired. Please request a fresh OTP.'];
            }
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'OTP_EXPIRED', 'message' => 'OTP expired. Please request a fresh OTP.'];
        }

        $attempts = max(0, (int) ($record['attempt_count'] ?? 0));
        if ($attempts >= self::OTP_MAX_ATTEMPTS) {
            return ['ok' => false, 'error' => 'OTP_MAX_ATTEMPTS', 'message' => 'Too many incorrect OTP attempts. Please request a fresh OTP.'];
        }

        $expectedHash = (string) ($record['otp_hash'] ?? '');
        if ($expectedHash === '' || !hash_equals($expectedHash, $this->hashOtp($eventId, $normalizedEmail, trim($otp)))) {
            $this->repo->incrementAttempts((int) ($record['id'] ?? 0));
            $remainingAttempts = max(0, self::OTP_MAX_ATTEMPTS - ($attempts + 1));

            return [
                'ok' => false,
                'error' => 'OTP_INVALID',
                'message' => $remainingAttempts > 0
                    ? 'Incorrect OTP. Please try again.'
                    : 'Too many incorrect OTP attempts. Please request a fresh OTP.',
                'remainingAttempts' => $remainingAttempts,
            ];
        }

        $verificationToken = bin2hex(random_bytes(24));
        $this->repo->markVerified(
            (int) ($record['id'] ?? 0),
            hash('sha256', $verificationToken),
            $now->format('Y-m-d H:i:s'),
            $now->modify('+' . self::VERIFIED_TOKEN_TTL_MINUTES . ' minutes')->format('Y-m-d H:i:s')
        );

        return [
            'ok' => true,
            'message' => 'Email verified successfully.',
            'verificationToken' => $verificationToken,
            'verificationExpiresInMinutes' => self::VERIFIED_TOKEN_TTL_MINUTES,
        ];
    }

    public function hasValidVerificationToken(string $eventId, string $email, string $verificationToken): bool
    {
        $normalizedEmail = $this->normalizeEmail($email);
        $token = trim($verificationToken);
        if ($token === '') {
            return false;
        }

        $record = $this->repo->findByEventAndEmail($eventId, $normalizedEmail);
        if (!$record) {
            return false;
        }

        $tokenHash = trim((string) ($record['verification_token_hash'] ?? ''));
        $expiresAt = trim((string) ($record['verification_expires_at'] ?? ''));
        $verifiedAt = trim((string) ($record['verified_at'] ?? ''));
        if ($tokenHash === '' || $expiresAt === '' || $verifiedAt === '') {
            return false;
        }

        try {
            $expiry = new \DateTimeImmutable($expiresAt, new \DateTimeZone('Asia/Kolkata'));
            if ($expiry <= $this->now()) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return hash_equals($tokenHash, hash('sha256', $token));
    }

    private function hashOtp(string $eventId, string $email, string $otp): string
    {
        return hash('sha256', strtolower(trim($eventId)) . '|' . strtolower(trim($email)) . '|' . trim($otp));
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
    }
}