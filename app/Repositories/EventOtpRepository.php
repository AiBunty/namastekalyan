<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class EventOtpRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByEventAndEmail(string $eventId, string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM event_otp_verifications WHERE event_id = :event_id AND email = :email LIMIT 1');
        $stmt->execute([
            ':event_id' => trim($eventId),
            ':email' => strtolower(trim($email)),
        ]);

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function storeOtp(
        string $eventId,
        string $email,
        string $customerName,
        string $otpHash,
        string $otpRequestedAt,
        string $resendAllowedAt,
        string $expiresAt
    ): void {
        $sql = 'INSERT INTO event_otp_verifications (
                    event_id, email, customer_name, otp_hash,
                    attempt_count, otp_requested_at, resend_allowed_at, expires_at,
                    verified_at, verification_token_hash, verification_expires_at
                ) VALUES (
                    :event_id, :email, :customer_name, :otp_hash,
                    0, :otp_requested_at, :resend_allowed_at, :expires_at,
                    NULL, "", NULL
                )
                ON DUPLICATE KEY UPDATE
                    customer_name = VALUES(customer_name),
                    otp_hash = VALUES(otp_hash),
                    attempt_count = 0,
                    otp_requested_at = VALUES(otp_requested_at),
                    resend_allowed_at = VALUES(resend_allowed_at),
                    expires_at = VALUES(expires_at),
                    verified_at = NULL,
                    verification_token_hash = "",
                    verification_expires_at = NULL';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':event_id' => trim($eventId),
            ':email' => strtolower(trim($email)),
            ':customer_name' => trim($customerName),
            ':otp_hash' => $otpHash,
            ':otp_requested_at' => $otpRequestedAt,
            ':resend_allowed_at' => $resendAllowedAt,
            ':expires_at' => $expiresAt,
        ]);
    }

    public function incrementAttempts(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE event_otp_verifications SET attempt_count = attempt_count + 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function markVerified(int $id, string $verificationTokenHash, string $verifiedAt, string $verificationExpiresAt): void
    {
        $stmt = $this->db->prepare('UPDATE event_otp_verifications
            SET verified_at = :verified_at,
                verification_token_hash = :verification_token_hash,
                verification_expires_at = :verification_expires_at,
                attempt_count = 0
            WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':verified_at' => $verifiedAt,
            ':verification_token_hash' => $verificationTokenHash,
            ':verification_expires_at' => $verificationExpiresAt,
        ]);
    }
}