<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class MenuCategoryRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function listActive(string $menuType): array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM menu_categories
             WHERE menu_type = :menu_type AND is_active = 1
             ORDER BY sort_order ASC, display_name ASC, id ASC'
        );
        $stmt->execute([':menu_type' => $menuType]);
        return $stmt->fetchAll() ?: [];
    }

    public function listAll(string $menuType): array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM menu_categories
             WHERE menu_type = :menu_type
             ORDER BY sort_order ASC, display_name ASC, id ASC'
        );
        $stmt->execute([':menu_type' => $menuType]);
        return $stmt->fetchAll() ?: [];
    }

    public function findByCanonical(string $menuType, string $canonicalName): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM menu_categories
             WHERE menu_type = :menu_type AND canonical_name = :canonical_name
             LIMIT 1'
        );
        $stmt->execute([
            ':menu_type'      => $menuType,
            ':canonical_name' => $canonicalName,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBySlug(string $menuType, string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT *
             FROM menu_categories
             WHERE menu_type = :menu_type AND slug = :slug
             LIMIT 1'
        );
        $stmt->execute([
            ':menu_type' => $menuType,
            ':slug'      => $slug,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByNormalizedAlias(string $menuType, string $normalizedAlias): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*
             FROM menu_category_aliases a
             INNER JOIN menu_categories c ON c.id = a.category_id
             WHERE c.menu_type = :menu_type
               AND a.normalized_alias = :normalized_alias
             ORDER BY c.sort_order ASC, c.display_name ASC, c.id ASC
             LIMIT 1'
        );
        $stmt->execute([
            ':menu_type'        => $menuType,
            ':normalized_alias' => $normalizedAlias,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function insertCategory(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO menu_categories
             (menu_type, canonical_name, display_name, slug, sort_order, is_active)
             VALUES
             (:menu_type, :canonical_name, :display_name, :slug, :sort_order, :is_active)'
        );
        $stmt->execute([
            ':menu_type'      => $data['menu_type'],
            ':canonical_name' => $data['canonical_name'],
            ':display_name'   => $data['display_name'],
            ':slug'           => $data['slug'],
            ':sort_order'     => (int) ($data['sort_order'] ?? 0),
            ':is_active'      => !empty($data['is_active']) ? 1 : 0,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function upsertAlias(int $categoryId, string $aliasName, string $normalizedAlias): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO menu_category_aliases (category_id, alias_name, normalized_alias)
             VALUES (:category_id, :alias_name, :normalized_alias)
             ON DUPLICATE KEY UPDATE alias_name = VALUES(alias_name), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            ':category_id'      => $categoryId,
            ':alias_name'       => $aliasName,
            ':normalized_alias' => $normalizedAlias,
        ]);
    }

    public function setActive(int $categoryId, bool $active): void
    {
        $stmt = $this->db->prepare(
            'UPDATE menu_categories
             SET is_active = :is_active
             WHERE id = :id'
        );
        $stmt->execute([
            ':is_active' => $active ? 1 : 0,
            ':id'        => $categoryId,
        ]);
    }

    public function updateSortOrder(string $menuType, array $displayNames): int
    {
        if (empty($displayNames)) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'UPDATE menu_categories
             SET sort_order = :sort_order
             WHERE menu_type = :menu_type AND display_name = :display_name'
        );

        $updated = 0;
        foreach (array_values($displayNames) as $index => $displayName) {
            $stmt->execute([
                ':sort_order'   => $index + 1,
                ':menu_type'    => $menuType,
                ':display_name' => $displayName,
            ]);
            $updated += $stmt->rowCount();
        }
        return $updated;
    }

    public function nextSortOrder(string $menuType): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) FROM menu_categories WHERE menu_type = :menu_type'
        );
        $stmt->execute([':menu_type' => $menuType]);
        return ((int) $stmt->fetchColumn()) + 1;
    }
}
