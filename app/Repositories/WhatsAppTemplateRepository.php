<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class WhatsAppTemplateRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function upsert(array $template): void
    {
        $sql = 'INSERT INTO whatsapp_message_templates (
                    template_uid, template_name, language_code, category,
                    status, quality_score, components_json, last_synced_at
                ) VALUES (
                    :template_uid, :template_name, :language_code, :category,
                    :status, :quality_score, :components_json, :last_synced_at
                )
                ON DUPLICATE KEY UPDATE
                    template_name = VALUES(template_name),
                    language_code = VALUES(language_code),
                    category = VALUES(category),
                    status = VALUES(status),
                    quality_score = VALUES(quality_score),
                    components_json = VALUES(components_json),
                    last_synced_at = VALUES(last_synced_at)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':template_uid' => (string) ($template['template_uid'] ?? ''),
            ':template_name' => (string) ($template['template_name'] ?? ''),
            ':language_code' => (string) ($template['language_code'] ?? ''),
            ':category' => (string) ($template['category'] ?? ''),
            ':status' => (string) ($template['status'] ?? ''),
            ':quality_score' => (string) ($template['quality_score'] ?? ''),
            ':components_json' => $template['components_json'] ?? null,
            ':last_synced_at' => (string) ($template['last_synced_at'] ?? date('Y-m-d H:i:s')),
        ]);
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM whatsapp_message_templates ORDER BY template_name ASC, language_code ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function listApproved(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_message_templates WHERE UPPER(status) = :status ORDER BY template_name ASC, language_code ASC');
        $stmt->execute([':status' => 'APPROVED']);
        return $stmt->fetchAll() ?: [];
    }

    public function findByNameAndLanguage(string $templateName, string $languageCode): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_message_templates WHERE template_name = :template_name AND language_code = :language_code LIMIT 1');
        $stmt->execute([
            ':template_name' => $templateName,
            ':language_code' => $languageCode,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function countAll(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM whatsapp_message_templates');
        return (int) $stmt->fetchColumn();
    }
}