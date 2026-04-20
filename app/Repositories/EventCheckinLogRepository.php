<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class EventCheckinLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO event_checkin_logs (
                    transaction_id, event_id, admitted_count, guest_names_json, verified_by, source, created_at
                ) VALUES (
                    :transaction_id, :event_id, :admitted_count, :guest_names_json, :verified_by, :source, :created_at
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':transaction_id' => (string) ($payload['transaction_id'] ?? ''),
            ':event_id' => (string) ($payload['event_id'] ?? ''),
            ':admitted_count' => max(0, (int) ($payload['admitted_count'] ?? 0)),
            ':guest_names_json' => json_encode($payload['guest_names'] ?? [], JSON_UNESCAPED_UNICODE),
            ':verified_by' => (string) ($payload['verified_by'] ?? ''),
            ':source' => (string) ($payload['source'] ?? 'scanner'),
            ':created_at' => (string) ($payload['created_at'] ?? date('Y-m-d H:i:s')),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listByTransactionIds(array $transactionIds): array
    {
        $ids = array_values(array_filter(array_map(static fn($value) => trim((string) $value), $transactionIds)));
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = ':tx_' . $index;
            $placeholders[] = $key;
            $params[$key] = $id;
        }

        $sql = 'SELECT * FROM event_checkin_logs WHERE transaction_id IN (' . implode(',', $placeholders) . ') ORDER BY created_at ASC, id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }
}