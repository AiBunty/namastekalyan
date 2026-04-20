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
                    channel, qr_id, qr_slug, destination_key, destination_label, resolved_url,
                    city, region, country, device, browser, os, language, screen
                ) VALUES (
                    :scanned_at, :user_agent, :referer, :ip_address, :scan_number,
                    :channel, :qr_id, :qr_slug, :destination_key, :destination_label, :resolved_url,
                    :city, :region, :country, :device, :browser, :os, :language, :screen
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':scanned_at' => $payload['scanned_at'] ?? date('Y-m-d H:i:s'),
            ':user_agent' => $payload['user_agent'] ?? '',
            ':referer'    => $payload['referer'] ?? '',
            ':ip_address' => $payload['ip_address'] ?? '',
            ':scan_number'=> (int) ($payload['scan_number'] ?? 0),
            ':channel'    => $payload['channel'] ?? 'customer',
            ':qr_id' => $payload['qr_id'] ?? null,
            ':qr_slug' => $payload['qr_slug'] ?? null,
            ':destination_key' => $payload['destination_key'] ?? null,
            ':destination_label' => $payload['destination_label'] ?? null,
            ':resolved_url' => $payload['resolved_url'] ?? null,
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

    public function getChannelCounts(): array
    {
        $stmt = $this->db->query('SELECT channel, COUNT(*) AS total FROM qr_scans GROUP BY channel');
        $rows = $stmt->fetchAll() ?: [];

        $counts = [
            'customer' => 0,
            'admin' => 0,
        ];

        foreach ($rows as $row) {
            $channel = strtolower((string) ($row['channel'] ?? ''));
            if ($channel !== '') {
                $counts[$channel] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    public function listLatest(int $limit = 100): array
    {
        $sql = 'SELECT * FROM qr_scans ORDER BY id DESC LIMIT :lim';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function listGroupedByQr(): array
    {
        $sql = 'SELECT
                    COALESCE(NULLIF(qr_slug, \'\'), CONCAT(\'channel:\', channel)) AS qr_key,
                    COALESCE(NULLIF(qr_slug, \'\'), channel) AS qr_slug,
                    MAX(qr_id) AS qr_id,
                    MAX(channel) AS channel,
                    MAX(destination_key) AS destination_key,
                    MAX(destination_label) AS destination_label,
                    MAX(resolved_url) AS resolved_url,
                    COUNT(*) AS total_scans,
                    MAX(scanned_at) AS last_scanned_at,
                    MIN(scanned_at) AS first_scanned_at
                FROM qr_scans
                GROUP BY COALESCE(NULLIF(qr_slug, \'\'), CONCAT(\'channel:\', channel)), COALESCE(NULLIF(qr_slug, \'\'), channel)
                ORDER BY total_scans DESC, last_scanned_at DESC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    public function countRows(?string $channel = null): int
    {
        if ($channel !== null && $channel !== '') {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM qr_scans WHERE channel = :channel');
            $stmt->execute([':channel' => $channel]);
        } else {
            $stmt = $this->db->query('SELECT COUNT(*) FROM qr_scans');
        }
        $value = $stmt->fetchColumn();
        return $value ? (int) $value : 0;
    }

    public function countToday(?string $channel = null): int
    {
        if ($channel !== null && $channel !== '') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM qr_scans WHERE DATE(scanned_at) = CURDATE() AND channel = :channel");
            $stmt->execute([':channel' => $channel]);
        } else {
            $stmt = $this->db->query("SELECT COUNT(*) FROM qr_scans WHERE DATE(scanned_at) = CURDATE()");
        }
        $value = $stmt->fetchColumn();
        return $value ? (int) $value : 0;
    }
}
