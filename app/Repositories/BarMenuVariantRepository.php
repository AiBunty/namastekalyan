<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class BarMenuVariantRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Get all variants for a single bar item, ordered by variant_sort_order.
     */
    public function getVariantsForItem(int $barItemId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM bar_menu_item_variants
             WHERE bar_item_id = :id
             ORDER BY variant_sort_order ASC, id ASC'
        );
        $stmt->execute([':id' => $barItemId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get all variants for multiple bar items in one query.
     * Returns an array keyed by bar_item_id.
     */
    public function getVariantsForItems(array $barItemIds): array
    {
        if (empty($barItemIds)) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($barItemIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM bar_menu_item_variants
             WHERE bar_item_id IN ({$placeholders})
             ORDER BY bar_item_id ASC, variant_sort_order ASC, id ASC"
        );
        $stmt->execute(array_values($barItemIds));
        $rows = $stmt->fetchAll() ?: [];

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['bar_item_id']][] = $row;
        }
        return $grouped;
    }

    /**
     * Get all distinct variant labels seen across all bar items.
     * Used for column header ordering during export.
     */
    public function getAllDistinctLabels(): array
    {
        $stmt = $this->db->query(
            'SELECT DISTINCT variant_label, MIN(variant_sort_order) AS sort_order
             FROM bar_menu_item_variants
             GROUP BY variant_label
             ORDER BY MIN(variant_sort_order) ASC, variant_label ASC'
        );
        return array_column($stmt->fetchAll() ?: [], 'variant_label');
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /**
     * Insert a single variant. Returns the new ID.
     */
    public function insertVariant(int $barItemId, string $label, float $price, int $sortOrder = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO bar_menu_item_variants
             (bar_item_id, variant_label, price, variant_sort_order)
             VALUES (:bar_item_id, :label, :price, :sort_order)'
        );
        $stmt->execute([
            ':bar_item_id' => $barItemId,
            ':label'       => $label,
            ':price'       => $price,
            ':sort_order'  => $sortOrder,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Replace all variants for a bar item (delete then insert).
     * $variants = [['label' => string, 'price' => float, 'sort_order' => int], ...]
     */
    public function replaceVariantsForItem(int $barItemId, array $variants): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM bar_menu_item_variants WHERE bar_item_id = :id'
        );
        $stmt->execute([':id' => $barItemId]);

        foreach ($variants as $i => $v) {
            $this->insertVariant(
                $barItemId,
                (string) ($v['label'] ?? ''),
                (float)  ($v['price'] ?? 0),
                (int)    ($v['sort_order'] ?? $i)
            );
        }
    }
}
