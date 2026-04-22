<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class WhatsAppEventMappingRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByEventKey(string $eventKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_event_mappings WHERE event_key = :event_key LIMIT 1');
        $stmt->execute([':event_key' => $eventKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listAllIndexed(): array
    {
        $stmt = $this->db->query('SELECT * FROM whatsapp_event_mappings ORDER BY event_key ASC');
        $rows = $stmt->fetchAll() ?: [];
        $indexed = [];
        foreach ($rows as $row) {
            $eventKey = trim((string) ($row['event_key'] ?? ''));
            if ($eventKey !== '') {
                $indexed[$eventKey] = $row;
            }
        }

        return $indexed;
    }

    public function upsert(array $mapping): void
    {
        $sql = 'INSERT INTO whatsapp_event_mappings (
                    event_key, template_name, language_code, is_enabled, updated_by, updated_at
                ) VALUES (
                    :event_key, :template_name, :language_code, :is_enabled, :updated_by, :updated_at
                )
                ON DUPLICATE KEY UPDATE
                    template_name = VALUES(template_name),
                    language_code = VALUES(language_code),
                    is_enabled = VALUES(is_enabled),
                    updated_by = VALUES(updated_by),
                    updated_at = VALUES(updated_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':event_key' => (string) ($mapping['event_key'] ?? ''),
            ':template_name' => (string) ($mapping['template_name'] ?? ''),
            ':language_code' => (string) ($mapping['language_code'] ?? ''),
            ':is_enabled' => !empty($mapping['is_enabled']) ? 1 : 0,
            ':updated_by' => (string) ($mapping['updated_by'] ?? ''),
            ':updated_at' => (string) ($mapping['updated_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }
}