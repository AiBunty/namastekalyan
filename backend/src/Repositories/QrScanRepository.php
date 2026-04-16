<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class QrScanRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getLastScanNumber(): int
    {
        $stmt = $this->db->query('SELECT scan_number FROM qr_scans ORDER BY id DESC LIMIT 1');
        $value = $stmt->fetchColumn();
        return $value ? (int) $value : 0;
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO qr_scans (
                    scanned_at, user_agent, referer, ip_address, scan_number,
                    city, region, country, device, browser, os, language, screen
                ) VALUES (
                    :scanned_at, :user_agent, :referer, :ip_address, :scan_number,
                    :city, :region, :country, :device, :browser, :os, :language, :screen
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':scanned_at' => $payload['scanned_at'] ?? date('Y-m-d H:i:s'),
            ':user_agent' => $payload['user_agent'] ?? '',
            ':referer'    => $payload['referer'] ?? '',
            ':ip_address' => $payload['ip_address'] ?? '',
            ':scan_number'=> (int) ($payload['scan_number'] ?? 0),
            ':city'       => $payload['city'] ?? '',
            ':region'     => $payload['region'] ?? '',
            ':country'    => $payload['country'] ?? '',
            ':device'     => $payload['device'] ?? '',
            ':browser'    => $payload['browser'] ?? '',
            ':os'         => $payload['os'] ?? '',
            ':language'   => $payload['language'] ?? '',
            ':screen'     => $payload['screen'] ?? '',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listLatest(int $limit = 100): array
    {
        $sql = 'SELECT * FROM qr_scans ORDER BY id DESC LIMIT :lim';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function countRows(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM qr_scans');
        $value = $stmt->fetchColumn();
        return $value ? (int) $value : 0;
    }
}
