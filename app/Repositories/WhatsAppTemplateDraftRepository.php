<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class WhatsAppTemplateDraftRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO whatsapp_template_drafts (
                    draft_name, template_name, category, language_code, header_type,
                    header_text, body_text, footer_text, buttons_json, sample_variables_json,
                    example_media_handle, status, meta_template_id, submitted_at, last_synced_at,
                    rejection_reason, created_by, updated_by
                ) VALUES (
                    :draft_name, :template_name, :category, :language_code, :header_type,
                    :header_text, :body_text, :footer_text, :buttons_json, :sample_variables_json,
                    :example_media_handle, :status, :meta_template_id, :submitted_at, :last_synced_at,
                    :rejection_reason, :created_by, :updated_by
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':draft_name' => (string) ($payload['draft_name'] ?? ''),
            ':template_name' => (string) ($payload['template_name'] ?? ''),
            ':category' => (string) ($payload['category'] ?? 'UTILITY'),
            ':language_code' => (string) ($payload['language_code'] ?? 'en'),
            ':header_type' => (string) ($payload['header_type'] ?? 'NONE'),
            ':header_text' => (string) ($payload['header_text'] ?? ''),
            ':body_text' => (string) ($payload['body_text'] ?? ''),
            ':footer_text' => (string) ($payload['footer_text'] ?? ''),
            ':buttons_json' => $payload['buttons_json'] ?? null,
            ':sample_variables_json' => $payload['sample_variables_json'] ?? null,
            ':example_media_handle' => (string) ($payload['example_media_handle'] ?? ''),
            ':status' => (string) ($payload['status'] ?? 'draft'),
            ':meta_template_id' => (string) ($payload['meta_template_id'] ?? ''),
            ':submitted_at' => $payload['submitted_at'] ?? null,
            ':last_synced_at' => $payload['last_synced_at'] ?? null,
            ':rejection_reason' => (string) ($payload['rejection_reason'] ?? ''),
            ':created_by' => (string) ($payload['created_by'] ?? ''),
            ':updated_by' => (string) ($payload['updated_by'] ?? ''),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = 'UPDATE whatsapp_template_drafts
                SET draft_name = :draft_name,
                    template_name = :template_name,
                    category = :category,
                    language_code = :language_code,
                    header_type = :header_type,
                    header_text = :header_text,
                    body_text = :body_text,
                    footer_text = :footer_text,
                    buttons_json = :buttons_json,
                    sample_variables_json = :sample_variables_json,
                    example_media_handle = :example_media_handle,
                    status = :status,
                    meta_template_id = :meta_template_id,
                    submitted_at = :submitted_at,
                    last_synced_at = :last_synced_at,
                    rejection_reason = :rejection_reason,
                    updated_by = :updated_by
                WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':draft_name' => (string) ($payload['draft_name'] ?? ''),
            ':template_name' => (string) ($payload['template_name'] ?? ''),
            ':category' => (string) ($payload['category'] ?? 'UTILITY'),
            ':language_code' => (string) ($payload['language_code'] ?? 'en'),
            ':header_type' => (string) ($payload['header_type'] ?? 'NONE'),
            ':header_text' => (string) ($payload['header_text'] ?? ''),
            ':body_text' => (string) ($payload['body_text'] ?? ''),
            ':footer_text' => (string) ($payload['footer_text'] ?? ''),
            ':buttons_json' => $payload['buttons_json'] ?? null,
            ':sample_variables_json' => $payload['sample_variables_json'] ?? null,
            ':example_media_handle' => (string) ($payload['example_media_handle'] ?? ''),
            ':status' => (string) ($payload['status'] ?? 'draft'),
            ':meta_template_id' => (string) ($payload['meta_template_id'] ?? ''),
            ':submitted_at' => $payload['submitted_at'] ?? null,
            ':last_synced_at' => $payload['last_synced_at'] ?? null,
            ':rejection_reason' => (string) ($payload['rejection_reason'] ?? ''),
            ':updated_by' => (string) ($payload['updated_by'] ?? ''),
            ':id' => $id,
        ]);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_template_drafts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listLatest(int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_template_drafts ORDER BY updated_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findByNameAndLanguage(string $templateName, string $languageCode): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_template_drafts WHERE template_name = :template_name AND language_code = :language_code ORDER BY id DESC LIMIT 1');
        $stmt->execute([
            ':template_name' => $templateName,
            ':language_code' => $languageCode,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}