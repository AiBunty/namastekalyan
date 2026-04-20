<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class LeadRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findLatestByPhone(string $phone): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM leads WHERE phone = :phone ORDER BY id DESC LIMIT 1');
        $stmt->execute([':phone' => $phone]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getPhoneStats(string $phone): array
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS total_submissions, MIN(created_at) AS first_seen_at, MAX(created_at) AS last_seen_at FROM leads WHERE phone = :phone');
        $stmt->execute([':phone' => $phone]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_submissions' => (int) ($row['total_submissions'] ?? 0),
            'first_seen_at' => $row['first_seen_at'] ?? null,
            'last_seen_at' => $row['last_seen_at'] ?? null,
        ];
    }

    public function listLatestByPhoneSummaries(): array
    {
        $sql = 'SELECT l.*, agg.total_submissions, agg.first_seen_at, agg.last_seen_at
                FROM leads l
                INNER JOIN (
                    SELECT phone,
                           MAX(id) AS max_id,
                           COUNT(*) AS total_submissions,
                           MIN(created_at) AS first_seen_at,
                           MAX(created_at) AS last_seen_at
                    FROM leads
                    GROUP BY phone
                ) agg ON agg.max_id = l.id
                ORDER BY agg.last_seen_at DESC, l.id DESC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM leads WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM leads WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO leads (
                    created_at, name, phone, prize, status,
                    date_of_birth, date_of_anniversary, source,
                    visit_count, coupon_code, crm_sync_status,
                    crm_sync_code, crm_sync_message
                ) VALUES (
                    :created_at, :name, :phone, :prize, :status,
                    :date_of_birth, :date_of_anniversary, :source,
                    :visit_count, :coupon_code, :crm_sync_status,
                    :crm_sync_code, :crm_sync_message
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':created_at'           => $payload['created_at'] ?? date('Y-m-d H:i:s'),
            ':name'                 => $payload['name'],
            ':phone'                => $payload['phone'],
            ':prize'                => $payload['prize'] ?? '',
            ':status'               => $payload['status'] ?? 'Unredeemed',
            ':date_of_birth'        => $payload['date_of_birth'] ?? null,
            ':date_of_anniversary'  => $payload['date_of_anniversary'] ?? null,
            ':source'               => $payload['source'] ?? 'menu-blocker-web',
            ':visit_count'          => (int) ($payload['visit_count'] ?? 1),
            ':coupon_code'          => $payload['coupon_code'] ?? '',
            ':crm_sync_status'      => $payload['crm_sync_status'] ?? 'Pending',
            ':crm_sync_code'        => $payload['crm_sync_code'] ?? '',
            ':crm_sync_message'     => $payload['crm_sync_message'] ?? '',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateRedemption(int $id, bool $redeemed): void
    {
        $sql = 'UPDATE leads
                SET status = :status,
                    redeemed_at = :redeemed_at
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status'      => $redeemed ? 'Redeemed' : 'Unredeemed',
            ':redeemed_at' => $redeemed ? date('Y-m-d H:i:s') : null,
            ':id'          => $id,
        ]);
    }

    public function updateCouponCode(int $id, string $couponCode): void
    {
        $stmt = $this->db->prepare('UPDATE leads SET coupon_code = :coupon_code WHERE id = :id');
        $stmt->execute([
            ':coupon_code' => $couponCode,
            ':id'          => $id,
        ]);
    }

    public function countRows(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM leads');
        return (int) $stmt->fetchColumn();
    }

    public function countTryagin(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM leads WHERE prize LIKE '%Try Again%'");
        return (int) $stmt->fetchColumn();
    }

    public function countCouponsWon(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM leads WHERE prize != '' AND prize NOT LIKE '%Try Again%'");
        return (int) $stmt->fetchColumn();
    }

    public function countRedeemed(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM leads WHERE status = 'Redeemed'");
        return (int) $stmt->fetchColumn();
    }

    public function getSummaryFiltered(array $filters): array
    {
        $params = [];
        $where = $this->buildFilters($filters, $params);
        $whereSql = $where !== [] ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = 'SELECT COUNT(*) AS total_leads,
                   SUM(CASE WHEN prize LIKE :try_again_marker THEN 1 ELSE 0 END) AS total_try_again,
                   SUM(CASE WHEN prize <> \'\' AND prize NOT LIKE :won_exclude_marker THEN 1 ELSE 0 END) AS total_won,
                   SUM(CASE WHEN status = \'Redeemed\' THEN 1 ELSE 0 END) AS total_redeemed
            FROM leads ' . $whereSql;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($params, [
            ':try_again_marker' => '%Try Again%',
            ':won_exclude_marker' => '%Try Again%',
        ]));
        $row = $stmt->fetch() ?: [];

        return [
            'total_leads' => (int) ($row['total_leads'] ?? 0),
            'total_try_again' => (int) ($row['total_try_again'] ?? 0),
            'total_won' => (int) ($row['total_won'] ?? 0),
            'total_redeemed' => (int) ($row['total_redeemed'] ?? 0),
        ];
    }

    public function countFiltered(array $filters): int
    {
        $params = [];
        $where = $this->buildFilters($filters, $params);
        $sql = 'SELECT COUNT(*) FROM leads';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listFiltered(array $filters, int $page, int $pageSize): array
    {
        $params = [];
        $where = $this->buildFilters($filters, $params);
        $offset = max(0, ($page - 1) * $pageSize);

        $sql = 'SELECT * FROM leads';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset';

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
        $params = [];
        $where = $this->buildFilters($filters, $params);

        $sql = 'SELECT * FROM leads';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function updateCrmSync(int $id, string $status, string $code, string $message): void
    {
        $sql = 'UPDATE leads
                SET crm_sync_status = :status,
                    crm_sync_code = :code,
                    crm_sync_message = :message
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':code' => $code,
            ':message' => $message,
            ':id' => $id,
        ]);
    }

    private function buildFilters(array $filters, array &$params): array
    {
        $clauses = [];
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_coupon'] = '%' . $search . '%';
            $params[':search_phone'] = '%' . $search . '%';
            $searchClauses = [
                'name LIKE :search_name',
                'coupon_code LIKE :search_coupon',
                'phone LIKE :search_phone',
            ];

            $digitsOnlySearch = preg_replace('/\D+/', '', $search) ?? '';
            if ($digitsOnlySearch !== '') {
                $params[':search_digits'] = '%' . $digitsOnlySearch . '%';
                $searchClauses[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '') LIKE :search_digits";
            }

            $clauses[] = '(' . implode(' OR ', $searchClauses) . ')';
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $params[':source'] = $source;
            $clauses[] = 'source = :source';
        }

        $syncStatus = trim((string) ($filters['syncStatus'] ?? ''));
        if ($syncStatus !== '') {
            $params[':sync_status'] = $syncStatus;
            $clauses[] = 'crm_sync_status = :sync_status';
        }

        $leadStatus = trim((string) ($filters['leadStatus'] ?? ''));
        if ($leadStatus !== '') {
            $params[':lead_status'] = $leadStatus;
            $clauses[] = 'status = :lead_status';
        }

        $outcome = strtolower(trim((string) ($filters['outcome'] ?? '')));
        if ($outcome === 'won') {
            $params[':outcome_won_exclude'] = '%Try Again%';
            $clauses[] = "prize <> '' AND prize NOT LIKE :outcome_won_exclude";
        } elseif ($outcome === 'try again') {
            $params[':outcome_try_again'] = '%Try Again%';
            $clauses[] = 'prize LIKE :outcome_try_again';
        }

        $fromDate = trim((string) ($filters['fromDate'] ?? ''));
        if ($fromDate !== '') {
            $params[':from_date'] = $fromDate;
            $clauses[] = 'DATE(created_at) >= :from_date';
        }

        $toDate = trim((string) ($filters['toDate'] ?? ''));
        if ($toDate !== '') {
            $params[':to_date'] = $toDate;
            $clauses[] = 'DATE(created_at) <= :to_date';
        }

        return $clauses;
    }
}
