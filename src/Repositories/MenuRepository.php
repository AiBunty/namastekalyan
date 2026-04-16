<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

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
}
