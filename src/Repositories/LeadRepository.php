<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class LeadRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findLatestByPhone(string $phone): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM leads WHERE phone = :phone ORDER BY id DESC LIMIT 1');
        $stmt->execute([':phone' => $phone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO leads (
                    created_at, name, phone, prize, status,
                    date_of_birth, date_of_anniversary, source,
                    visit_count, coupon_code, crm_sync_status,
                    crm_sync_code, crm_sync_message
                ) VALUES (
                    :created_at, :name, :phone, :prize, :status,
                    :date_of_birth, :date_of_anniversary, :source,
                    :visit_count, :coupon_code, :crm_sync_status,
                    :crm_sync_code, :crm_sync_message
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':created_at'           => $payload['created_at'] ?? date('Y-m-d H:i:s'),
            ':name'                 => $payload['name'],
            ':phone'                => $payload['phone'],
            ':prize'                => $payload['prize'] ?? '',
            ':status'               => $payload['status'] ?? 'Unredeemed',
            ':date_of_birth'        => $payload['date_of_birth'] ?? null,
            ':date_of_anniversary'  => $payload['date_of_anniversary'] ?? null,
            ':source'               => $payload['source'] ?? 'menu-blocker-web',
            ':visit_count'          => (int) ($payload['visit_count'] ?? 1),
            ':coupon_code'          => $payload['coupon_code'] ?? '',
            ':crm_sync_status'      => $payload['crm_sync_status'] ?? 'Pending',
            ':crm_sync_code'        => $payload['crm_sync_code'] ?? '',
            ':crm_sync_message'     => $payload['crm_sync_message'] ?? '',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateRedemption(int $id, bool $redeemed): void
    {
        $sql = 'UPDATE leads
                SET status = :status,
                    redeemed_at = :redeemed_at
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status'      => $redeemed ? 'Redeemed' : 'Unredeemed',
            ':redeemed_at' => $redeemed ? date('Y-m-d H:i:s') : null,
            ':id'          => $id,
        ]);
    }

    public function updateCouponCode(int $id, string $couponCode): void
    {
        $stmt = $this->db->prepare('UPDATE leads SET coupon_code = :coupon_code WHERE id = :id');
        $stmt->execute([
            ':coupon_code' => $couponCode,
            ':id'          => $id,
        ]);
    }

    public function countRows(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM leads');
        return (int) $stmt->fetchColumn();
    }
}
