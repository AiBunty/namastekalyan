<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class QrRedirectSettingsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByChannel(string $channel): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM qr_redirect_settings WHERE channel = :channel LIMIT 1');
        $stmt->execute([':channel' => $channel]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findAllIndexed(): array
    {
        $stmt = $this->db->query('SELECT * FROM qr_redirect_settings ORDER BY channel ASC');
        $rows = $stmt->fetchAll() ?: [];
        $indexed = [];

        foreach ($rows as $row) {
            $channel = strtolower((string) ($row['channel'] ?? ''));
            if ($channel !== '') {
                $indexed[$channel] = $row;
            }
        }

        return $indexed;
    }

    public function upsert(array $payload): void
    {
        $sql = 'INSERT INTO qr_redirect_settings (
                    channel, destination_mode, destination_key, manual_url, is_active, updated_at, updated_by
                ) VALUES (
                    :channel, :destination_mode, :destination_key, :manual_url, :is_active, :updated_at, :updated_by
                )
                ON DUPLICATE KEY UPDATE
                    destination_mode = VALUES(destination_mode),
                    destination_key = VALUES(destination_key),
                    manual_url = VALUES(manual_url),
                    is_active = VALUES(is_active),
                    updated_at = VALUES(updated_at),
                    updated_by = VALUES(updated_by)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':channel' => $payload['channel'],
            ':destination_mode' => $payload['destination_mode'],
            ':destination_key' => $payload['destination_key'] ?? null,
            ':manual_url' => $payload['manual_url'] ?? null,
            ':is_active' => (int) ($payload['is_active'] ?? 1),
            ':updated_at' => $payload['updated_at'] ?? date('Y-m-d H:i:s'),
            ':updated_by' => $payload['updated_by'] ?? null,
        ]);
    }
}