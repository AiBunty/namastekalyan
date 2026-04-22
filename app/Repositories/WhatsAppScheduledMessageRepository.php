<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class WhatsAppScheduledMessageRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function upsertByEventAndTransaction(array $payload): void
    {
        $sql = 'INSERT INTO whatsapp_scheduled_messages (
                    event_key, transaction_id, lead_id, phone, customer_name,
                    event_id, event_title, due_at, status, payload_json,
                    last_result_code, last_result_message, sent_at, cancelled_at
                ) VALUES (
                    :event_key, :transaction_id, :lead_id, :phone, :customer_name,
                    :event_id, :event_title, :due_at, :status, :payload_json,
                    :last_result_code, :last_result_message, :sent_at, :cancelled_at
                )
                ON DUPLICATE KEY UPDATE
                    lead_id = VALUES(lead_id),
                    phone = VALUES(phone),
                    customer_name = VALUES(customer_name),
                    event_id = VALUES(event_id),
                    event_title = VALUES(event_title),
                    due_at = VALUES(due_at),
                    status = VALUES(status),
                    payload_json = VALUES(payload_json),
                    last_result_code = VALUES(last_result_code),
                    last_result_message = VALUES(last_result_message),
                    sent_at = VALUES(sent_at),
                    cancelled_at = VALUES(cancelled_at),
                    updated_at = CURRENT_TIMESTAMP';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':event_key' => (string) ($payload['event_key'] ?? ''),
            ':transaction_id' => $payload['transaction_id'] ?? null,
            ':lead_id' => $payload['lead_id'] ?? null,
            ':phone' => (string) ($payload['phone'] ?? ''),
            ':customer_name' => (string) ($payload['customer_name'] ?? ''),
            ':event_id' => (string) ($payload['event_id'] ?? ''),
            ':event_title' => (string) ($payload['event_title'] ?? ''),
            ':due_at' => (string) ($payload['due_at'] ?? date('Y-m-d H:i:s')),
            ':status' => (string) ($payload['status'] ?? 'pending'),
            ':payload_json' => $payload['payload_json'] ?? null,
            ':last_result_code' => (string) ($payload['last_result_code'] ?? ''),
            ':last_result_message' => (string) ($payload['last_result_message'] ?? ''),
            ':sent_at' => $payload['sent_at'] ?? null,
            ':cancelled_at' => $payload['cancelled_at'] ?? null,
        ]);
    }

    public function listDue(string $dueAt, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_scheduled_messages WHERE status = :status AND due_at <= :due_at ORDER BY due_at ASC, id ASC LIMIT :limit');
        $stmt->bindValue(':status', 'pending');
        $stmt->bindValue(':due_at', $dueAt);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function listUpcoming(int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM whatsapp_scheduled_messages ORDER BY due_at ASC, id ASC LIMIT :limit');
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function cancelPendingByTransaction(string $transactionId, array $eventKeys, string $reason): void
    {
        $keys = array_values(array_filter(array_map(static fn($value): string => trim((string) $value), $eventKeys), static fn(string $value): bool => $value !== ''));
        if ($transactionId === '' || $keys === []) {
            return;
        }

        $params = [
            ':transaction_id' => $transactionId,
            ':status' => 'cancelled',
            ':last_result_message' => substr($reason, 0, 500),
        ];
        $placeholders = [];
        foreach ($keys as $index => $key) {
            $placeholder = ':event_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $key;
        }

        $sql = 'UPDATE whatsapp_scheduled_messages
                SET status = :status,
                    cancelled_at = NOW(),
                    last_result_message = :last_result_message
                WHERE transaction_id = :transaction_id
                  AND status = "pending"
                  AND event_key IN (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function markSent(int $id, string $code, string $message): void
    {
        $stmt = $this->db->prepare('UPDATE whatsapp_scheduled_messages
            SET status = :status,
                attempt_count = attempt_count + 1,
                last_result_code = :code,
                last_result_message = :message,
                sent_at = NOW()
            WHERE id = :id');
        $stmt->execute([
            ':status' => 'sent',
            ':code' => $code,
            ':message' => substr($message, 0, 500),
            ':id' => $id,
        ]);
    }

    public function markFailed(int $id, string $status, string $code, string $message): void
    {
        $stmt = $this->db->prepare('UPDATE whatsapp_scheduled_messages
            SET status = :status,
                attempt_count = attempt_count + 1,
                last_result_code = :code,
                last_result_message = :message
            WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':code' => $code,
            ':message' => substr($message, 0, 500),
            ':id' => $id,
        ]);
    }

    public function summaryCounts(): array
    {
        $rows = $this->db->query('SELECT status, COUNT(*) AS total FROM whatsapp_scheduled_messages GROUP BY status')->fetchAll() ?: [];
        $summary = [];
        foreach ($rows as $row) {
            $summary[(string) ($row['status'] ?? '')] = (int) ($row['total'] ?? 0);
        }
        return $summary;
    }
}