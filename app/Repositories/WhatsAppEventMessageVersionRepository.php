<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class WhatsAppEventMessageVersionRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_event_message_versions WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listLatest(int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_event_message_versions ORDER BY updated_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function save(array $payload): int
    {
        $id = (int) ($payload['id'] ?? 0);
        $isCurrent = !empty($payload['is_current']);
        $eventKey = (string) ($payload['event_key'] ?? '');

        $this->db->beginTransaction();
        try {
            if ($id > 0) {
                $sql = 'UPDATE whatsapp_event_message_versions
                        SET event_key = :event_key,
                            source_draft_id = :source_draft_id,
                            version_label = :version_label,
                            template_name = :template_name,
                            language_code = :language_code,
                            category = :category,
                            header_type = :header_type,
                            header_text = :header_text,
                            body_text = :body_text,
                            footer_text = :footer_text,
                            buttons_json = :buttons_json,
                            sample_variables_json = :sample_variables_json,
                            example_media_handle = :example_media_handle,
                            source_template_uid = :source_template_uid,
                            meta_template_uid = :meta_template_uid,
                            meta_status = :meta_status,
                            is_current = :is_current,
                            updated_by = :updated_by
                        WHERE id = :id';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($this->bindPayload($payload) + [':id' => $id]);
            } else {
                $sql = 'INSERT INTO whatsapp_event_message_versions (
                            event_key, source_draft_id, version_label, template_name, language_code,
                            category, header_type, header_text, body_text, footer_text,
                            buttons_json, sample_variables_json, example_media_handle,
                            source_template_uid, meta_template_uid, meta_status,
                            is_current, created_by, updated_by
                        ) VALUES (
                            :event_key, :source_draft_id, :version_label, :template_name, :language_code,
                            :category, :header_type, :header_text, :body_text, :footer_text,
                            :buttons_json, :sample_variables_json, :example_media_handle,
                            :source_template_uid, :meta_template_uid, :meta_status,
                            :is_current, :created_by, :updated_by
                        )';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($this->bindPayload($payload));
                $id = (int) $this->db->lastInsertId();
            }

            if ($isCurrent && $eventKey !== '') {
                $stmt = $this->db->prepare('UPDATE whatsapp_event_message_versions SET is_current = 0 WHERE event_key = :event_key AND id <> :id');
                $stmt->execute([
                    ':event_key' => $eventKey,
                    ':id' => $id,
                ]);
            }

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function markMetaSubmissionByDraftId(int $draftId, string $metaTemplateUid, string $metaStatus, string $updatedBy): void
    {
        $stmt = $this->db->prepare('UPDATE whatsapp_event_message_versions
            SET meta_template_uid = :meta_template_uid,
                meta_status = :meta_status,
                updated_by = :updated_by
            WHERE source_draft_id = :source_draft_id');
        $stmt->execute([
            ':meta_template_uid' => $metaTemplateUid,
            ':meta_status' => $metaStatus,
            ':updated_by' => $updatedBy,
            ':source_draft_id' => $draftId,
        ]);
    }

    public function syncMetaByTemplate(string $templateName, string $languageCode, string $metaTemplateUid, string $metaStatus): void
    {
        $stmt = $this->db->prepare('UPDATE whatsapp_event_message_versions
            SET meta_template_uid = :meta_template_uid,
                meta_status = :meta_status
            WHERE template_name = :template_name AND language_code = :language_code');
        $stmt->execute([
            ':meta_template_uid' => $metaTemplateUid,
            ':meta_status' => $metaStatus,
            ':template_name' => $templateName,
            ':language_code' => $languageCode,
        ]);
    }

    private function bindPayload(array $payload): array
    {
        return [
            ':event_key' => (string) ($payload['event_key'] ?? ''),
            ':source_draft_id' => $payload['source_draft_id'] ?? null,
            ':version_label' => (string) ($payload['version_label'] ?? ''),
            ':template_name' => (string) ($payload['template_name'] ?? ''),
            ':language_code' => (string) ($payload['language_code'] ?? 'en'),
            ':category' => (string) ($payload['category'] ?? 'UTILITY'),
            ':header_type' => (string) ($payload['header_type'] ?? 'NONE'),
            ':header_text' => (string) ($payload['header_text'] ?? ''),
            ':body_text' => (string) ($payload['body_text'] ?? ''),
            ':footer_text' => (string) ($payload['footer_text'] ?? ''),
            ':buttons_json' => $payload['buttons_json'] ?? null,
            ':sample_variables_json' => $payload['sample_variables_json'] ?? null,
            ':example_media_handle' => (string) ($payload['example_media_handle'] ?? ''),
            ':source_template_uid' => (string) ($payload['source_template_uid'] ?? ''),
            ':meta_template_uid' => (string) ($payload['meta_template_uid'] ?? ''),
            ':meta_status' => (string) ($payload['meta_status'] ?? ''),
            ':is_current' => !empty($payload['is_current']) ? 1 : 0,
            ':created_by' => (string) ($payload['created_by'] ?? ''),
            ':updated_by' => (string) ($payload['updated_by'] ?? ''),
        ];
    }
}