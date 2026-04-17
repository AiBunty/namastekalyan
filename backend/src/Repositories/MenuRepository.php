<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;
use PDOException;

class MenuRepository
{
    private PDO $db;

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

    /**
     * Rename a key inside the price_columns JSON for all items of a given sheet.
     * Returns the number of rows updated.
     */
    public function renamePriceColumnKey(string $sheetType, string $oldKey, string $newKey): int
    {
        // Build JSON paths — use double-quoted key format for MySQL's JSON path syntax
        $oldPath = '$."' . str_replace('"', '\\"', $oldKey) . '"';
        $newPath = '$."' . str_replace('"', '\\"', $newKey) . '"';

        $sql = 'UPDATE menu_items
                SET price_columns = JSON_SET(
                    JSON_REMOVE(price_columns, ?),
                    ?,
                    JSON_EXTRACT(price_columns, ?)
                )
                WHERE sheet_type = ?
                  AND JSON_EXTRACT(price_columns, ?) IS NOT NULL';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$oldPath, $newPath, $oldPath, $sheetType, $oldPath]);
        return $stmt->rowCount();
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

    public function getDesignerCategoryOrder(string $sheetType): array
    {
        try {
            $sql = 'SELECT scope_key, sort_order
                    FROM menu_designer_order
                    WHERE sheet_type = :sheet_type
                      AND scope_type = :scope_type
                    ORDER BY sort_order ASC, id ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sheet_type' => $sheetType,
                ':scope_type' => 'category',
            ]);

            $rows = $stmt->fetchAll() ?: [];
            $ordered = [];
            foreach ($rows as $row) {
                $name = trim((string) ($row['scope_key'] ?? ''));
                if ($name !== '') {
                    $ordered[] = $name;
                }
            }

            return $ordered;
        } catch (PDOException $e) {
            if ($this->isMissingDesignerOrderTable($e)) {
                return [];
            }
            throw $e;
        }
    }

    public function getDesignerItemOrder(string $sheetType): array
    {
        try {
            $sql = 'SELECT scope_key, item_id, sort_order
                    FROM menu_designer_order
                    WHERE sheet_type = :sheet_type
                      AND scope_type = :scope_type
                    ORDER BY scope_key ASC, sort_order ASC, id ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':sheet_type' => $sheetType,
                ':scope_type' => 'item',
            ]);

            $rows = $stmt->fetchAll() ?: [];
            $out = [];
            foreach ($rows as $row) {
                $category = trim((string) ($row['scope_key'] ?? ''));
                $itemId = (int) ($row['item_id'] ?? 0);
                if ($category === '' || $itemId <= 0) {
                    continue;
                }
                if (!isset($out[$category])) {
                    $out[$category] = [];
                }
                $out[$category][] = $itemId;
            }

            return $out;
        } catch (PDOException $e) {
            if ($this->isMissingDesignerOrderTable($e)) {
                return [];
            }
            throw $e;
        }
    }

    private function isMissingDesignerOrderTable(PDOException $e): bool
    {
        $code = (string) $e->getCode();
        $message = (string) $e->getMessage();

        return $code === '42S02' && stripos($message, 'menu_designer_order') !== false;
    }

    public function saveDesignerCategoryOrder(string $sheetType, array $categories): void
    {
        $sql = 'INSERT INTO menu_designer_order (sheet_type, scope_type, scope_key, item_id, sort_order, updated_at)
                VALUES (:sheet_type, :scope_type, :scope_key, :item_id, :sort_order, :updated_at)
                ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = VALUES(updated_at)';

        $stmt = $this->db->prepare($sql);
        $now = date('Y-m-d H:i:s');
        foreach (array_values($categories) as $idx => $category) {
            $name = trim((string) $category);
            if ($name === '') {
                continue;
            }
            $stmt->execute([
                ':sheet_type' => $sheetType,
                ':scope_type' => 'category',
                ':scope_key'  => $name,
                ':item_id'    => 0,
                ':sort_order' => $idx,
                ':updated_at' => $now,
            ]);
        }
    }

    public function saveDesignerItemOrder(string $sheetType, string $category, array $itemIds): void
    {
        $name = trim($category);
        if ($name === '') {
            return;
        }

        $sql = 'INSERT INTO menu_designer_order (sheet_type, scope_type, scope_key, item_id, sort_order, updated_at)
                VALUES (:sheet_type, :scope_type, :scope_key, :item_id, :sort_order, :updated_at)
                ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order), updated_at = VALUES(updated_at)';

        $stmt = $this->db->prepare($sql);
        $now = date('Y-m-d H:i:s');
        $ids = array_values(array_unique(array_map('intval', $itemIds)));
        foreach ($ids as $idx => $itemId) {
            if ($itemId <= 0) {
                continue;
            }
            $stmt->execute([
                ':sheet_type' => $sheetType,
                ':scope_type' => 'item',
                ':scope_key'  => $name,
                ':item_id'    => $itemId,
                ':sort_order' => $idx,
                ':updated_at' => $now,
            ]);
        }
    }

    public function addItem(array $payload): int
    {
        $sql = 'INSERT INTO menu_items (
                    sheet_type, category, sub_category, item_name, description, image_url,
                    is_available, is_jain, is_veg, is_nonveg, is_universal, is_chef_special, spice_level, serving_unit,
                    base_price, price_columns, food_category, primary_diet, meta_json, sort_order, created_at
                ) VALUES (
                    :sheet_type, :category, :sub_category, :item_name, :description, :image_url,
                    :is_available, :is_jain, :is_veg, :is_nonveg, :is_universal, :is_chef_special, :spice_level, :serving_unit,
                    :base_price, :price_columns, :food_category, :primary_diet, :meta_json, :sort_order, :created_at
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sheet_type'     => $payload['sheet_type'],
            ':category'       => $payload['category'] ?? '',
            ':sub_category'   => $payload['sub_category'] ?? '',
            ':item_name'      => $payload['item_name'],
            ':description'    => $payload['description'] ?? '',
            ':image_url'      => $payload['image_url'] ?? '',
            ':is_available'   => !empty($payload['is_available']) ? 1 : 0,
            ':is_jain'        => !empty($payload['is_jain']) ? 1 : 0,
            ':is_veg'         => !empty($payload['is_veg']) ? 1 : 0,
            ':is_nonveg'      => !empty($payload['is_nonveg']) ? 1 : 0,
            ':is_universal'   => !empty($payload['is_universal']) ? 1 : 0,
            ':is_chef_special'=> !empty($payload['is_chef_special']) ? 1 : 0,
            ':spice_level'    => $payload['spice_level'] ?? '',
            ':serving_unit'   => $payload['serving_unit'] ?? '',
            ':base_price'     => $payload['base_price'] ?? null,
            ':price_columns'  => json_encode($payload['price_columns'] ?? [], JSON_UNESCAPED_UNICODE),
            ':food_category'  => $payload['food_category'] ?? '',
            ':primary_diet'   => $payload['primary_diet'] ?? '',
            ':meta_json'      => json_encode($payload['meta_json'] ?? [], JSON_UNESCAPED_UNICODE),
            ':sort_order'     => (int) ($payload['sort_order'] ?? 0),
            ':created_at'     => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateItem(int $id, array $payload): int
    {
        $sets = [
            'category = :category',
            'sub_category = :sub_category',
            'item_name = :item_name',
            'description = :description',
            'image_url = :image_url',
            'is_available = :is_available',
            'is_jain = :is_jain',
            'is_veg = :is_veg',
            'is_nonveg = :is_nonveg',
            'is_universal = :is_universal',
            'is_chef_special = :is_chef_special',
            'spice_level = :spice_level',
            'serving_unit = :serving_unit',
            'base_price = :base_price',
            'price_columns = :price_columns',
            'food_category = :food_category',
            'meta_json = :meta_json',
            'sort_order = :sort_order',
            'updated_at = :updated_at',
        ];

        $params = [
            ':category'       => $payload['category'] ?? '',
            ':sub_category'   => $payload['sub_category'] ?? '',
            ':item_name'      => $payload['item_name'] ?? '',
            ':description'    => $payload['description'] ?? '',
            ':image_url'      => $payload['image_url'] ?? '',
            ':is_available'   => !empty($payload['is_available']) ? 1 : 0,
            ':is_jain'        => !empty($payload['is_jain']) ? 1 : 0,
            ':is_veg'         => !empty($payload['is_veg']) ? 1 : 0,
            ':is_nonveg'      => !empty($payload['is_nonveg']) ? 1 : 0,
            ':is_universal'   => !empty($payload['is_universal']) ? 1 : 0,
            ':is_chef_special'=> !empty($payload['is_chef_special']) ? 1 : 0,
            ':spice_level'    => $payload['spice_level'] ?? '',
            ':serving_unit'   => $payload['serving_unit'] ?? '',
            ':base_price'     => $payload['base_price'] ?? null,
            ':price_columns'  => json_encode($payload['price_columns'] ?? [], JSON_UNESCAPED_UNICODE),
            ':food_category'  => $payload['food_category'] ?? '',
            ':meta_json'      => json_encode($payload['meta_json'] ?? [], JSON_UNESCAPED_UNICODE),
            ':sort_order'     => (int) ($payload['sort_order'] ?? 0),
            ':updated_at'     => date('Y-m-d H:i:s'),
            ':id'             => $id,
        ];

        // Optional columns added by later migrations — only set if column exists in payload
        if (array_key_exists('primary_diet', $payload)) {
            $sets[] = 'primary_diet = :primary_diet';
            $allowed = ['veg', 'nonveg', 'jain', 'mixed', 'universal', 'bar', ''];
            $pd = in_array($payload['primary_diet'], $allowed, true) ? $payload['primary_diet'] : '';
            $params[':primary_diet'] = $pd;
        }
        if (array_key_exists('manually_edited', $payload)) {
            $sets[] = 'manually_edited = :manually_edited';
            $params[':manually_edited'] = !empty($payload['manually_edited']) ? 1 : 0;
        }

        $sql = 'UPDATE menu_items SET ' . implode(', ', $sets) . ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function getIdByRowNumber(string $sheetType, int $rowNumber): int
    {
        // rowNumber uses 1-based offset where row 1 is the header,
        // so data rows start at rowNumber=2 → list index 0
        $index = $rowNumber - 2;
        if ($index < 0) {
            return 0;
        }

        $sql = 'SELECT id FROM menu_items
                WHERE sheet_type = :sheet_type
                ORDER BY category ASC, sort_order ASC, id ASC
                LIMIT 1 OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sheet_type', $sheetType, \PDO::PARAM_STR);
        $stmt->bindValue(':offset', $index, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : 0;
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

    public function setAvailabilityByCategory(string $sheetType, string $category, bool $isAvailable): int
    {
        $sql = 'UPDATE menu_items
                SET is_available = :is_available,
                    updated_at = :updated_at
                WHERE sheet_type = :sheet_type
                  AND category = :category';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':is_available' => $isAvailable ? 1 : 0,
            ':updated_at'   => date('Y-m-d H:i:s'),
            ':sheet_type'   => $sheetType,
            ':category'     => trim($category),
        ]);

        return $stmt->rowCount();
    }

    public function replaceAll(string $sheetType, array $rows): void
    {
        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare('DELETE FROM menu_items WHERE sheet_type = :sheet_type');
            $del->execute([':sheet_type' => $sheetType]);

            foreach ($rows as $row) {
                $this->addItem(array_merge($row, ['sheet_type' => $sheetType]));
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Snapshot methods ──────────────────────────────────────────────────────

    public function createSnapshot(string $sheetType, string $label, string $triggeredBy, string $createdBy): int
    {
        $rows = $this->listItems($sheetType);
        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sql = 'INSERT INTO menu_snapshots (sheet_type, label, snapshot_data, row_count, triggered_by, created_by)
                VALUES (:sheet_type, :label, :snapshot_data, :row_count, :triggered_by, :created_by)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sheet_type'    => $sheetType,
            ':label'         => $label,
            ':snapshot_data' => $json,
            ':row_count'     => count($rows),
            ':triggered_by'  => $triggeredBy,
            ':created_by'    => $createdBy,
        ]);
        $id = (int) $this->db->lastInsertId();

        // Keep only last 10 snapshots per sheet_type
        $this->pruneSnapshots($sheetType, 10);

        return $id;
    }

    public function listSnapshots(string $sheetType): array
    {
        $sql = 'SELECT id, sheet_type, label, row_count, triggered_by, created_by, created_at
                FROM menu_snapshots
                WHERE sheet_type = :sheet_type
                ORDER BY created_at DESC
                LIMIT 20';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':sheet_type' => $sheetType]);
        return $stmt->fetchAll() ?: [];
    }

    public function listAllSnapshots(): array
    {
        $sql = 'SELECT id, sheet_type, label, row_count, triggered_by, created_by, created_at
                FROM menu_snapshots
                ORDER BY created_at DESC
                LIMIT 40';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getSnapshot(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM menu_snapshots WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function restoreSnapshot(int $snapshotId, string $sheetType): int
    {
        $snap = $this->getSnapshot($snapshotId);
        if (!$snap || $snap['sheet_type'] !== $sheetType) {
            return 0;
        }

        $rows = json_decode((string) $snap['snapshot_data'], true);
        if (!is_array($rows)) {
            return 0;
        }

        $this->replaceAll($sheetType, $rows);
        return count($rows);
    }

    public function deleteAllItems(string $sheetType): int
    {
        $stmt = $this->db->prepare('DELETE FROM menu_items WHERE sheet_type = ?');
        $stmt->execute([$sheetType]);
        return $stmt->rowCount();
    }

    public function upsertItemImage(int $itemId, string $fullPath, string $thumbPath): void
    {
        $sql = 'INSERT INTO menu_item_images (item_id, full_path, thumb_path, sort_order, created_at, updated_at)
                VALUES (:item_id, :full_path, :thumb_path, 0, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    full_path  = VALUES(full_path),
                    thumb_path = VALUES(thumb_path),
                    updated_at = NOW()';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':item_id',    $itemId,    \PDO::PARAM_INT);
        $stmt->bindValue(':full_path',  $fullPath,  \PDO::PARAM_STR);
        $stmt->bindValue(':thumb_path', $thumbPath, \PDO::PARAM_STR);
        $stmt->execute();
    }

    private function pruneSnapshots(string $sheetType, int $keepCount): void
    {
        $sql = 'DELETE FROM menu_snapshots
                WHERE sheet_type = :sheet_type
                  AND id NOT IN (
                      SELECT id FROM (
                          SELECT id FROM menu_snapshots
                          WHERE sheet_type = :sheet_type2
                          ORDER BY created_at DESC
                          LIMIT :keep_count
                      ) AS t
                  )';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':sheet_type',  $sheetType, \PDO::PARAM_STR);
        $stmt->bindValue(':sheet_type2', $sheetType, \PDO::PARAM_STR);
        $stmt->bindValue(':keep_count',  $keepCount, \PDO::PARAM_INT);
        $stmt->execute();
    }
}
