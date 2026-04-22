<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class WhatsAppMessageLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO whatsapp_message_logs (
                    lead_id, event_key, phone, template_name, language_code,
                    provider_message_id, delivery_status, status_updated_at,
                    attempted, success, http_code, response_message,
                    request_payload_json, response_payload_json
                ) VALUES (
                    :lead_id, :event_key, :phone, :template_name, :language_code,
                    :provider_message_id, :delivery_status, :status_updated_at,
                    :attempted, :success, :http_code, :response_message,
                    :request_payload_json, :response_payload_json
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':lead_id' => $payload['lead_id'] ?? null,
            ':event_key' => (string) ($payload['event_key'] ?? ''),
            ':phone' => (string) ($payload['phone'] ?? ''),
            ':template_name' => (string) ($payload['template_name'] ?? ''),
            ':language_code' => (string) ($payload['language_code'] ?? ''),
            ':provider_message_id' => $payload['provider_message_id'] ?? null,
            ':delivery_status' => (string) ($payload['delivery_status'] ?? ''),
            ':status_updated_at' => $payload['status_updated_at'] ?? null,
            ':attempted' => !empty($payload['attempted']) ? 1 : 0,
            ':success' => !empty($payload['success']) ? 1 : 0,
            ':http_code' => (string) ($payload['http_code'] ?? ''),
            ':response_message' => (string) ($payload['response_message'] ?? ''),
            ':request_payload_json' => $payload['request_payload_json'] ?? null,
            ':response_payload_json' => $payload['response_payload_json'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listLatest(int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_message_logs ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findLatestByProviderMessageId(string $providerMessageId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_message_logs WHERE provider_message_id = :provider_message_id ORDER BY id DESC LIMIT 1');
        $stmt->execute([':provider_message_id' => $providerMessageId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateDeliveryStatus(int $id, string $deliveryStatus, string $responseMessage, ?string $statusUpdatedAt = null): void
    {
        $stmt = $this->db->prepare('UPDATE whatsapp_message_logs
            SET delivery_status = :delivery_status,
                response_message = :response_message,
                status_updated_at = :status_updated_at
            WHERE id = :id');
        $stmt->execute([
            ':delivery_status' => $deliveryStatus,
            ':response_message' => $responseMessage,
            ':status_updated_at' => $statusUpdatedAt ?? date('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }
}