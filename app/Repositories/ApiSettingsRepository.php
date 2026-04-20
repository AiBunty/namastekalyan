<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class ApiSettingsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getValue(string $key): string
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM api_settings WHERE setting_key = :setting_key LIMIT 1');
        $stmt->execute([':setting_key' => $key]);
        $value = $stmt->fetchColumn();
        return is_string($value) ? $value : '';
    }

    public function getValues(array $keys): array
    {
        $normalizedKeys = array_values(array_filter(array_map(
            static fn($key): string => trim((string) $key),
            $keys
        ), static fn(string $key): bool => $key !== ''));

        if ($normalizedKeys === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($normalizedKeys as $index => $key) {
            $placeholder = ':key_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $key;
        }

        $sql = 'SELECT setting_key, setting_value
                FROM api_settings
                WHERE setting_key IN (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];

        $indexed = [];
        foreach ($rows as $row) {
            $settingKey = trim((string) ($row['setting_key'] ?? ''));
            if ($settingKey !== '') {
                $indexed[$settingKey] = (string) ($row['setting_value'] ?? '');
            }
        }

        return $indexed;
    }

    public function upsertMany(array $settings): void
    {
        if ($settings === []) {
            return;
        }

        $sql = 'INSERT INTO api_settings (setting_key, setting_value, updated_at)
                VALUES (:setting_key, :setting_value, :updated_at)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_at = VALUES(updated_at)';

        $stmt = $this->db->prepare($sql);
        $updatedAt = date('Y-m-d H:i:s');

        foreach ($settings as $key => $value) {
            $settingKey = trim((string) $key);
            if ($settingKey === '') {
                continue;
            }

            $stmt->execute([
                ':setting_key' => $settingKey,
                ':setting_value' => (string) $value,
                ':updated_at' => $updatedAt,
            ]);
        }
    }
}