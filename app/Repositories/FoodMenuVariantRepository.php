<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class FoodMenuVariantRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Get all variants for a single food item, ordered by variant_sort_order.
     */
    public function getVariantsForItem(int $foodItemId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM food_menu_item_variants
             WHERE food_item_id = :id
             ORDER BY variant_sort_order ASC, id ASC'
        );
        $stmt->execute([':id' => $foodItemId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all variants for multiple food items in one query.
     * Returns an array keyed by food_item_id.
     */
    public function getVariantsForItems(array $foodItemIds): array
    {
        if (empty($foodItemIds)) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($foodItemIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM food_menu_item_variants
             WHERE food_item_id IN ({$placeholders})
             ORDER BY food_item_id ASC, variant_sort_order ASC, id ASC"
        );
        $stmt->execute(array_values($foodItemIds));
        $rows = $stmt->fetchAll() ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['food_item_id']][] = $row;
        }
        return $grouped;
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Insert a single variant. Returns the new ID.
     */
    public function insertVariant(int $foodItemId, string $label, float $price, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO food_menu_item_variants
             (food_item_id, variant_label, price, variant_sort_order)
             VALUES (:food_item_id, :label, :price, :sort_order)'
        );
        $stmt->execute([
            ':food_item_id' => $foodItemId,
            ':label'        => $label,
            ':price'        => $price,
            ':sort_order'   => $sortOrder,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Replace all variants for a food item (delete then insert).
     * $variants = [['label' => string, 'price' => float, 'sort_order' => int], ...]
     */
    public function replaceVariantsForItem(int $foodItemId, array $variants): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM food_menu_item_variants WHERE food_item_id = :id'
        );
        $stmt->execute([':id' => $foodItemId]);

        foreach ($variants as $i => $v) {
            $this->insertVariant(
                $foodItemId,
                (string) ($v['label'] ?? ''),
                (float)  ($v['price'] ?? 0),
                (int)    ($v['sort_order'] ?? $i)
            );
        }
    }

    /**
     * Delete all variants for a list of food_item_ids.
     * Called before replaceAll on the parent table.
     */
    public function deleteForItems(array $foodItemIds): void
    {
        if (empty($foodItemIds)) {
            return;
        }
        $placeholders = implode(', ', array_fill(0, count($foodItemIds), '?'));
        $stmt = $this->db->prepare(
            "DELETE FROM food_menu_item_variants WHERE food_item_id IN ({$placeholders})"
        );
        $stmt->execute(array_values($foodItemIds));
    }
}
