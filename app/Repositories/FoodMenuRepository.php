<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class FoodMenuRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * List all food items, ordered by Excel import order.
     */
    public function listItems(bool $availableOnly = false): array
    {
        $sql = 'SELECT * FROM food_menu_items';
        if ($availableOnly) {
            $sql .= ' WHERE is_available = 1';
        }
        $sql .= ' ORDER BY category_sort_order ASC, item_sort_order ASC, id ASC';

        return $this->db->query($sql)->fetchAll() ?: [];
    }

    /**
     * Get a single food item by ID.
     */
    public function getItem(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM food_menu_items WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Count total food items.
     */
    public function countItems(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM food_menu_items')->fetchColumn();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Insert a single food item. Returns the new auto-increment ID.
     */
    public function insertItem(array $data): int
    {
        $cols = $this->allowedColumns();
        $filtered = array_intersect_key($data, array_flip($cols));

        if (empty($filtered)) {
            throw new \InvalidArgumentException('No valid columns provided for food_menu_items insert.');
        }

        $colList = implode(', ', array_keys($filtered));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($filtered)));

        $sql = "INSERT INTO food_menu_items ({$colList}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);

        foreach ($filtered as $col => $val) {
            $stmt->bindValue(':' . $col, $val);
        }

        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a food item by ID.
     */
    public function updateItem(int $id, array $data): void
    {
        $cols = $this->allowedColumns();
        $filtered = array_intersect_key($data, array_flip($cols));

        if (empty($filtered)) {
            return;
        }

        $sets = implode(', ', array_map(fn($k) => "`{$k}` = :{$k}", array_keys($filtered)));
        $sql = "UPDATE food_menu_items SET {$sets}, manually_edited = 1 WHERE id = :__id";
        $stmt = $this->db->prepare($sql);

        foreach ($filtered as $col => $val) {
            $stmt->bindValue(':' . $col, $val);
        }
        $stmt->bindValue(':__id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Delete food items by IDs.
     */
    public function deleteItems(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("DELETE FROM food_menu_items WHERE id IN ({$placeholders})");
        $stmt->execute(array_values($ids));
        return $stmt->rowCount();
    }

    /**
     * Set availability for a list of item IDs.
     */
    public function setAvailability(array $ids, bool $available): int
    {
        if (empty($ids)) {
            return 0;
        }
        $val = $available ? 1 : 0;
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "UPDATE food_menu_items SET is_available = {$val} WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_values($ids));
        return $stmt->rowCount();
    }

    /**
     * Set availability for every item in a category.
     */
    public function setCategoryAvailability(string $category, bool $available): int
    {
        $stmt = $this->db->prepare(
            'UPDATE food_menu_items
             SET is_available = :available
             WHERE category = :category'
        );
        $stmt->execute([
            ':available' => $available ? 1 : 0,
            ':category'  => $category,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Set availability for a single item.
     */
    public function setItemAvailability(int $id, bool $available): int
    {
        $stmt = $this->db->prepare(
            'UPDATE food_menu_items
             SET is_available = :available
             WHERE id = :id'
        );
        $stmt->execute([
            ':available' => $available ? 1 : 0,
            ':id'        => $id,
        ]);
        return $stmt->rowCount();
    }

    /**
     * Persist category order by updating category_sort_order for all rows in each category.
     */
    public function updateCategorySortOrder(array $categories): int
    {
        if (empty($categories)) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'UPDATE food_menu_items
             SET category_sort_order = :sort_order
             WHERE category = :category'
        );

        $updated = 0;
        foreach (array_values($categories) as $index => $category) {
            $stmt->execute([
                ':sort_order' => $index + 1,
                ':category'   => (string) $category,
            ]);
            $updated += $stmt->rowCount();
        }

        return $updated;
    }

    /**
     * Persist item order inside a category.
     */
    public function updateItemSortOrder(string $category, array $itemIds): int
    {
        if ($category === '' || empty($itemIds)) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'UPDATE food_menu_items
             SET item_sort_order = :sort_order
             WHERE id = :id AND category = :category'
        );

        $updated = 0;
        foreach (array_values($itemIds) as $index => $itemId) {
            $stmt->execute([
                ':sort_order' => $index + 1,
                ':id'         => (int) $itemId,
                ':category'   => $category,
            ]);
            $updated += $stmt->rowCount();
        }

        return $updated;
    }

    /**
     * Atomic replace-all: truncate then bulk-insert in a transaction.
     * Returns the number of rows inserted.
     */
    public function replaceAll(array $rows): int
    {
        $this->db->beginTransaction();
        try {
            $this->db->exec('DELETE FROM food_menu_items');
            $count = 0;
            foreach ($rows as $row) {
                $this->insertItem($row);
                $count++;
            }
            $this->db->commit();
            return $count;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Columns that may be set from outside (guards against injection).
     */
    private function allowedColumns(): array
    {
        return [
            'category', 'item_name', 'description', 'image_url',
            'is_available',
            'is_veg', 'is_nonveg', 'is_jain', 'is_universal',
            'is_chef_special', 'spice_level', 'serving_unit',
            'pricing_mode',
            'price_veg', 'price_jain', 'price_chicken', 'price_mutton',
            'price_basa', 'price_prawns', 'price_surmai', 'price_pomfret',
            'price_crab', 'price_egg',
            'price_half', 'price_full', 'price_plain', 'price_butter',
            'price_medium', 'price_large', 'price_direct',
            'category_sort_order', 'item_sort_order', 'source_row',
            'manually_edited',
        ];
    }
}
