<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class CrmPushLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO crm_push_logs (
                    contact_id, lead_id, phone, contact_name,
                    trigger_source, crm_endpoint, attempted, success,
                    http_code, retry_count, attempt_count, response_message,
                    request_payload_json, attempts_json
                ) VALUES (
                    :contact_id, :lead_id, :phone, :contact_name,
                    :trigger_source, :crm_endpoint, :attempted, :success,
                    :http_code, :retry_count, :attempt_count, :response_message,
                    :request_payload_json, :attempts_json
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':contact_id' => $payload['contact_id'] ?? null,
            ':lead_id' => $payload['lead_id'] ?? null,
            ':phone' => $payload['phone'] ?? '',
            ':contact_name' => $payload['contact_name'] ?? '',
            ':trigger_source' => $payload['trigger_source'] ?? 'menu-blocker-web',
            ':crm_endpoint' => $payload['crm_endpoint'] ?? '',
            ':attempted' => !empty($payload['attempted']) ? 1 : 0,
            ':success' => !empty($payload['success']) ? 1 : 0,
            ':http_code' => $payload['http_code'] ?? '',
            ':retry_count' => (int) ($payload['retry_count'] ?? 0),
            ':attempt_count' => (int) ($payload['attempt_count'] ?? 0),
            ':response_message' => $payload['response_message'] ?? '',
            ':request_payload_json' => $payload['request_payload_json'] ?? null,
            ':attempts_json' => $payload['attempts_json'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function countFiltered(array $filters): int
    {
        [$whereSql, $params] = $this->buildFilters($filters);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM crm_push_logs' . $whereSql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listFiltered(array $filters, int $page, int $pageSize): array
    {
        [$whereSql, $params] = $this->buildFilters($filters);
        $offset = max(0, ($page - 1) * $pageSize);
        $sql = 'SELECT * FROM crm_push_logs'
            . $whereSql
            . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    private function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(phone LIKE :search OR contact_name LIKE :search OR response_message LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $where[] = 'trigger_source = :source';
            $params[':source'] = $source;
        }

        $syncStatus = trim((string) ($filters['syncStatus'] ?? ''));
        if ($syncStatus !== '') {
            if ($syncStatus === 'Success') {
                $where[] = 'success = 1';
            } elseif ($syncStatus === 'Failed') {
                $where[] = 'attempted = 1 AND success = 0';
            } elseif ($syncStatus === 'Skipped') {
                $where[] = 'attempted = 0';
            }
        }

        $fromDate = trim((string) ($filters['fromDate'] ?? ''));
        if ($fromDate !== '') {
            $where[] = 'DATE(created_at) >= :from_date';
            $params[':from_date'] = $fromDate;
        }

        $toDate = trim((string) ($filters['toDate'] ?? ''));
        if ($toDate !== '') {
            $where[] = 'DATE(created_at) <= :to_date';
            $params[':to_date'] = $toDate;
        }

        return [
            $where ? (' WHERE ' . implode(' AND ', $where)) : '',
            $params,
        ];
    }
}