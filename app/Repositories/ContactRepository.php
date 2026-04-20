<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class ContactRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function upsertByPhone(array $payload): array
    {
        $syncStatus = $this->normalizeSyncStatus((string) ($payload['latest_crm_sync_status'] ?? 'Pending'));

        $sql = 'INSERT INTO crm_contacts (
                    phone, name, date_of_birth, date_of_anniversary,
                    first_seen_at, last_seen_at, latest_source, latest_lead_id,
                    latest_lead_created_at, total_submissions, latest_crm_sync_status,
                    latest_crm_sync_code, latest_crm_sync_message,
                    last_crm_attempted_at, last_crm_pushed_at
                ) VALUES (
                    :phone, :name, :date_of_birth, :date_of_anniversary,
                    :first_seen_at, :last_seen_at, :latest_source, :latest_lead_id,
                    :latest_lead_created_at, :total_submissions, :latest_crm_sync_status,
                    :latest_crm_sync_code, :latest_crm_sync_message,
                    :last_crm_attempted_at, :last_crm_pushed_at
                )
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    date_of_birth = VALUES(date_of_birth),
                    date_of_anniversary = VALUES(date_of_anniversary),
                    first_seen_at = LEAST(first_seen_at, VALUES(first_seen_at)),
                    last_seen_at = GREATEST(last_seen_at, VALUES(last_seen_at)),
                    latest_source = VALUES(latest_source),
                    latest_lead_id = VALUES(latest_lead_id),
                    latest_lead_created_at = VALUES(latest_lead_created_at),
                    total_submissions = VALUES(total_submissions),
                    latest_crm_sync_status = VALUES(latest_crm_sync_status),
                    latest_crm_sync_code = VALUES(latest_crm_sync_code),
                    latest_crm_sync_message = VALUES(latest_crm_sync_message),
                    last_crm_attempted_at = VALUES(last_crm_attempted_at),
                    last_crm_pushed_at = VALUES(last_crm_pushed_at),
                    updated_at = CURRENT_TIMESTAMP';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':phone' => $payload['phone'],
            ':name' => $payload['name'] ?? '',
            ':date_of_birth' => $payload['date_of_birth'] ?? null,
            ':date_of_anniversary' => $payload['date_of_anniversary'] ?? null,
            ':first_seen_at' => $payload['first_seen_at'] ?? date('Y-m-d H:i:s'),
            ':last_seen_at' => $payload['last_seen_at'] ?? date('Y-m-d H:i:s'),
            ':latest_source' => $payload['latest_source'] ?? 'menu-blocker-web',
            ':latest_lead_id' => $payload['latest_lead_id'] ?? null,
            ':latest_lead_created_at' => $payload['latest_lead_created_at'] ?? null,
            ':total_submissions' => (int) ($payload['total_submissions'] ?? 1),
            ':latest_crm_sync_status' => $syncStatus,
            ':latest_crm_sync_code' => $payload['latest_crm_sync_code'] ?? '',
            ':latest_crm_sync_message' => $payload['latest_crm_sync_message'] ?? '',
            ':last_crm_attempted_at' => $payload['last_crm_attempted_at'] ?? null,
            ':last_crm_pushed_at' => $payload['last_crm_pushed_at'] ?? null,
        ]);

        return (array) $this->findByPhone((string) $payload['phone']);
    }

    public function findByPhone(string $phone): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM crm_contacts WHERE phone = :phone LIMIT 1');
        $stmt->execute([':phone' => $phone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteByPhone(string $phone): void
    {
        $stmt = $this->db->prepare('DELETE FROM crm_contacts WHERE phone = :phone');
        $stmt->execute([':phone' => $phone]);
    }

    public function updateLatestSync(int $contactId, array $sync): void
    {
        $syncStatus = $this->normalizeSyncStatus((string) ($sync['latest_crm_sync_status'] ?? 'Skipped'));

        $sql = 'UPDATE crm_contacts
                SET latest_crm_sync_status = :status,
                    latest_crm_sync_code = :code,
                    latest_crm_sync_message = :message,
                    last_crm_attempted_at = :attempted_at,
                    last_crm_pushed_at = :pushed_at
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => $syncStatus,
            ':code' => $sync['latest_crm_sync_code'] ?? '',
            ':message' => $sync['latest_crm_sync_message'] ?? '',
            ':attempted_at' => $sync['last_crm_attempted_at'] ?? null,
            ':pushed_at' => $sync['last_crm_pushed_at'] ?? null,
            ':id' => $contactId,
        ]);
    }

    public function countFiltered(array $filters): int
    {
        [$whereSql, $params] = $this->buildFilters($filters);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM crm_contacts' . $whereSql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listFiltered(array $filters, int $page, int $pageSize): array
    {
        [$whereSql, $params] = $this->buildFilters($filters);
        $offset = max(0, ($page - 1) * $pageSize);
        $sql = 'SELECT * FROM crm_contacts'
            . $whereSql
            . ' ORDER BY last_seen_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $pageSize, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function listForExport(array $filters): array
    {
        [$whereSql, $params] = $this->buildFilters($filters);
        $stmt = $this->db->prepare('SELECT * FROM crm_contacts' . $whereSql . ' ORDER BY last_seen_at DESC, id DESC');
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    private function buildFilters(array $filters): array
    {
        $where = [];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(phone LIKE :search OR name LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $where[] = 'latest_source = :source';
            $params[':source'] = $source;
        }

        $syncStatus = trim((string) ($filters['syncStatus'] ?? ''));
        if ($syncStatus !== '') {
            $where[] = 'latest_crm_sync_status = :sync_status';
            $params[':sync_status'] = $syncStatus;
        }

        $fromDate = trim((string) ($filters['fromDate'] ?? ''));
        if ($fromDate !== '') {
            $where[] = 'DATE(last_seen_at) >= :from_date';
            $params[':from_date'] = $fromDate;
        }

        $toDate = trim((string) ($filters['toDate'] ?? ''));
        if ($toDate !== '') {
            $where[] = 'DATE(last_seen_at) <= :to_date';
            $params[':to_date'] = $toDate;
        }

        return [
            $where ? (' WHERE ' . implode(' AND ', $where)) : '',
            $params,
        ];
    }

    private function normalizeSyncStatus(string $status): string
    {
        $status = trim($status);
        if ($status === '') {
            return 'Skipped';
        }

        $normalized = ucfirst(strtolower($status));
        $allowed = ['Pending', 'Success', 'Failed', 'Skipped'];

        return in_array($normalized, $allowed, true) ? $normalized : 'Skipped';
    }
}