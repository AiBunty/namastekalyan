<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class MenuRepository
{
    private PDO $db;
    private const SNAPSHOT_LIMIT_PER_SHEET = 10;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getSchema(string $sheetType): array
    {
        $stmt = $this->db->prepare('SELECT headers FROM menu_schema WHERE sheet_type = :sheet_type LIMIT 1');
        $stmt->execute([':sheet_type' => $sheetType]);
        $row = $stmt->fetch();
        if (!$row) {
            return [];
        }

        $headers = $row['headers'] ?? [];
        if (is_string($headers)) {
            $decoded = json_decode($headers, true);
            $headers = is_array($decoded) ? $decoded : [];
        }

        return is_array($headers) ? $headers : [];
    }

    public function saveSchema(string $sheetType, array $headers): void
    {
        $sql = 'INSERT INTO menu_schema (sheet_type, headers)
                VALUES (:sheet_type, :headers)
                ON DUPLICATE KEY UPDATE headers = VALUES(headers)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sheet_type' => $sheetType,
            ':headers'    => json_encode(array_values($headers), JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function listItems(string $sheetType): array
    {
        $sql = 'SELECT *
                FROM menu_items
                WHERE sheet_type = :sheet_type
                ORDER BY category ASC, sort_order ASC, id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':sheet_type' => $sheetType]);

        return $stmt->fetchAll() ?: [];
    }

    public function addItem(array $payload): int
    {
        $sql = 'INSERT INTO menu_items (
                    sheet_type, category, sub_category, item_name, is_available,
                    base_price, price_columns, food_category, sort_order, created_at
                ) VALUES (
                    :sheet_type, :category, :sub_category, :item_name, :is_available,
                    :base_price, :price_columns, :food_category, :sort_order, :created_at
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sheet_type'    => $payload['sheet_type'],
            ':category'      => $payload['category'] ?? '',
            ':sub_category'  => $payload['sub_category'] ?? '',
            ':item_name'     => $payload['item_name'],
            ':is_available'  => !empty($payload['is_available']) ? 1 : 0,
            ':base_price'    => $payload['base_price'] ?? null,
            ':price_columns' => json_encode($payload['price_columns'] ?? [], JSON_UNESCAPED_UNICODE),
            ':food_category' => $payload['food_category'] ?? '',
            ':sort_order'    => (int) ($payload['sort_order'] ?? 0),
            ':created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateItem(int $id, array $payload): void
    {
        $sql = 'UPDATE menu_items
                SET category = :category,
                    sub_category = :sub_category,
                    item_name = :item_name,
                    is_available = :is_available,
                    base_price = :base_price,
                    price_columns = :price_columns,
                    food_category = :food_category,
                    sort_order = :sort_order,
                    updated_at = :updated_at
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':category'      => $payload['category'] ?? '',
            ':sub_category'  => $payload['sub_category'] ?? '',
            ':item_name'     => $payload['item_name'],
            ':is_available'  => !empty($payload['is_available']) ? 1 : 0,
            ':base_price'    => $payload['base_price'] ?? null,
            ':price_columns' => json_encode($payload['price_columns'] ?? [], JSON_UNESCAPED_UNICODE),
            ':food_category' => $payload['food_category'] ?? '',
            ':sort_order'    => (int) ($payload['sort_order'] ?? 0),
            ':updated_at'    => date('Y-m-d H:i:s'),
            ':id'            => $id,
        ]);
    }

    public function deleteItems(string $sheetType, array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "DELETE FROM menu_items WHERE sheet_type = ? AND id IN ({$placeholders})";
        $stmt = $this->db->prepare($sql);

        $params = array_merge([$sheetType], $ids);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function setAvailability(string $sheetType, array $ids, bool $isAvailable): int
    {
        if (empty($ids)) {
            return 0;
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sql = "UPDATE menu_items
                SET is_available = ?, updated_at = ?
                WHERE sheet_type = ? AND id IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);

        $params = array_merge(
            [$isAvailable ? 1 : 0, date('Y-m-d H:i:s'), $sheetType],
            $ids
        );

        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function deleteAllItems(string $sheetType): int
    {
        $stmt = $this->db->prepare('DELETE FROM menu_items WHERE sheet_type = :sheet_type');
        $stmt->execute([':sheet_type' => $sheetType]);
        return $stmt->rowCount();
    }

    public function createSnapshot(string $sheetType, string $label = '', string $triggeredBy = 'import', ?string $createdBy = null): int
    {
        $rows = $this->listItems($sheetType);
        $normalizedTrigger = in_array($triggeredBy, ['import', 'manual', 'schedule'], true) ? $triggeredBy : 'manual';
        $finalLabel = trim($label) !== '' ? trim($label) : sprintf('Snapshot %s', date('Y-m-d H:i:s'));

        $stmt = $this->db->prepare(
            'INSERT INTO menu_snapshots (sheet_type, label, snapshot_data, row_count, triggered_by, created_by)
             VALUES (:sheet_type, :label, :snapshot_data, :row_count, :triggered_by, :created_by)'
        );
        $stmt->execute([
            ':sheet_type'    => $sheetType,
            ':label'         => $finalLabel,
            ':snapshot_data' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':row_count'     => count($rows),
            ':triggered_by'  => $normalizedTrigger,
            ':created_by'    => $createdBy,
        ]);

        $snapshotId = (int) $this->db->lastInsertId();
        $this->trimSnapshots($sheetType);

        return $snapshotId;
    }

    public function listSnapshots(string $sheetType): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, sheet_type, label, row_count, triggered_by, created_by, created_at
             FROM menu_snapshots
             WHERE sheet_type = :sheet_type
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([':sheet_type' => $sheetType]);
        return $stmt->fetchAll() ?: [];
    }

    public function listAllSnapshots(): array
    {
        $stmt = $this->db->query(
            'SELECT id, sheet_type, label, row_count, triggered_by, created_by, created_at
             FROM menu_snapshots
             ORDER BY created_at DESC, id DESC'
        );
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function getSnapshot(int $snapshotId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM menu_snapshots WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $snapshotId]);
        $snapshot = $stmt->fetch();
        if (!$snapshot) {
            return null;
        }

        $snapshot['snapshot_data'] = $this->decodeSnapshotData($snapshot['snapshot_data'] ?? '[]');
        return $snapshot;
    }

    public function restoreSnapshot(int $snapshotId, string $sheetType): int
    {
        $snapshot = $this->getSnapshot($snapshotId);
        if ($snapshot === null) {
            throw new \RuntimeException('Snapshot not found.');
        }

        if (($snapshot['sheet_type'] ?? '') !== $sheetType) {
            throw new \RuntimeException('Snapshot sheet type mismatch.');
        }

        $rows = is_array($snapshot['snapshot_data'] ?? null) ? $snapshot['snapshot_data'] : [];

        $this->db->beginTransaction();
        try {
            $this->deleteAllItems($sheetType);
            if (!empty($rows)) {
                $insert = $this->db->prepare(
                    'INSERT INTO menu_items (
                        id, sheet_type, category, sub_category, item_name, is_available,
                        base_price, price_columns, food_category, sort_order, created_at, updated_at
                    ) VALUES (
                        :id, :sheet_type, :category, :sub_category, :item_name, :is_available,
                        :base_price, :price_columns, :food_category, :sort_order, :created_at, :updated_at
                    )'
                );

                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $insert->execute([
                        ':id'           => (int) ($row['id'] ?? 0),
                        ':sheet_type'   => $sheetType,
                        ':category'     => (string) ($row['category'] ?? ''),
                        ':sub_category' => (string) ($row['sub_category'] ?? ''),
                        ':item_name'    => (string) ($row['item_name'] ?? ''),
                        ':is_available' => !empty($row['is_available']) ? 1 : 0,
                        ':base_price'   => $row['base_price'] === '' ? null : ($row['base_price'] ?? null),
                        ':price_columns'=> $this->encodePriceColumns($row['price_columns'] ?? null),
                        ':food_category'=> (string) ($row['food_category'] ?? ''),
                        ':sort_order'   => (int) ($row['sort_order'] ?? 0),
                        ':created_at'   => $row['created_at'] ?? date('Y-m-d H:i:s'),
                        ':updated_at'   => $row['updated_at'] ?? null,
                    ]);
                }
            }

            $maxId = (int) $this->db->query('SELECT COALESCE(MAX(id), 0) FROM menu_items')->fetchColumn();
            $this->db->exec('ALTER TABLE menu_items AUTO_INCREMENT = ' . ($maxId + 1));
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return count($rows);
    }

    public function replaceAll(string $sheetType, array $rows): void
    {
        $this->db->beginTransaction();
        try {
            $this->deleteAllItems($sheetType);

            foreach ($rows as $row) {
                $this->addItem(array_merge($row, ['sheet_type' => $sheetType]));
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function trimSnapshots(string $sheetType): void
    {
        $stmt = $this->db->prepare(
            'SELECT id
             FROM menu_snapshots
             WHERE sheet_type = :sheet_type
             ORDER BY created_at DESC, id DESC
             LIMIT 18446744073709551615 OFFSET ' . self::SNAPSHOT_LIMIT_PER_SHEET
        );
        $stmt->execute([':sheet_type' => $sheetType]);
        $ids = array_map('intval', array_column($stmt->fetchAll() ?: [], 'id'));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $delete = $this->db->prepare("DELETE FROM menu_snapshots WHERE id IN ({$placeholders})");
        $delete->execute($ids);
    }

    private function decodeSnapshotData(mixed $snapshotData): array
    {
        if (is_array($snapshotData)) {
            return $snapshotData;
        }

        if (!is_string($snapshotData) || trim($snapshotData) === '') {
            return [];
        }

        $decoded = json_decode($snapshotData, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function encodePriceColumns(mixed $priceColumns): ?string
    {
        if ($priceColumns === null || $priceColumns === '') {
            return null;
        }

        if (is_string($priceColumns)) {
            return $priceColumns;
        }

        return json_encode($priceColumns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
