<?php

declare(strict_types=1);

namespace NK\Services\Menu;

use NK\Config\Database;
use NK\Repositories\MenuCategoryRepository;

class MenuCategoryService
{
    private MenuCategoryRepository $repo;
    private ?bool $masterTablesAvailable = null;

    public function __construct()
    {
        $this->repo = new MenuCategoryRepository();
    }

    public function listOptions(string $menuType): array
    {
        if (!$this->hasMasterTables()) {
            return $this->fallbackOptions($menuType);
        }

        $this->syncFromLiveItems($menuType);

        return array_map(static function (array $row): array {
            return [
                'id'          => (int) ($row['id'] ?? 0),
                'name'        => (string) ($row['display_name'] ?? ''),
                'canonical'   => (string) ($row['canonical_name'] ?? ''),
                'slug'        => (string) ($row['slug'] ?? ''),
                'sortOrder'   => (int) ($row['sort_order'] ?? 0),
                'isActive'    => !empty($row['is_active']),
            ];
        }, $this->repo->listAll($menuType));
    }

    public function syncFromLiveItems(string $menuType): void
    {
        if (!$this->hasMasterTables()) {
            return;
        }

        $table = $menuType === 'bar' ? 'bar_menu_items' : 'food_menu_items';
        $db = Database::connection();
        $stmt = $db->query(
            "SELECT TRIM(category) AS category_name, MIN(category_sort_order) AS sort_order
             FROM {$table}
             WHERE TRIM(category) <> ''
             GROUP BY TRIM(category)
             ORDER BY MIN(category_sort_order) ASC, TRIM(category) ASC"
        );
        $rows = $stmt->fetchAll() ?: [];

        foreach ($rows as $row) {
            $displayName = trim((string) ($row['category_name'] ?? ''));
            if ($displayName === '') {
                continue;
            }

            $normalized = self::normalizeName($displayName);
            $existing = $this->repo->findByCanonical($menuType, $normalized)
                ?: $this->repo->findBySlug($menuType, $this->slugify($displayName))
                ?: $this->repo->findByNormalizedAlias($menuType, $normalized);

            if ($existing) {
                $this->repo->upsertAlias((int) $existing['id'], $displayName, $normalized);
                continue;
            }

            $categoryId = $this->repo->insertCategory([
                'menu_type'      => $menuType,
                'canonical_name' => $normalized,
                'display_name'   => $displayName,
                'slug'           => $this->uniqueSlug($menuType, $displayName),
                'sort_order'     => (int) ($row['sort_order'] ?? $this->repo->nextSortOrder($menuType)),
                'is_active'      => 1,
            ]);
            $this->repo->upsertAlias($categoryId, $displayName, $normalized);
        }
    }

    public function resolveName(string $menuType, string $input): ?string
    {
        $name = trim($input);
        if ($name === '') {
            return null;
        }

        if (!$this->hasMasterTables()) {
            foreach ($this->fallbackOptions($menuType) as $option) {
                $candidate = (string) ($option['name'] ?? '');
                if (self::normalizeName($candidate) === self::normalizeName($name)) {
                    return $candidate;
                }
            }
            return null;
        }

        $this->syncFromLiveItems($menuType);
        $normalized = self::normalizeName($name);
        $record = $this->repo->findByCanonical($menuType, $normalized)
            ?: $this->repo->findBySlug($menuType, $this->slugify($name))
            ?: $this->repo->findByNormalizedAlias($menuType, $normalized);

        return $record ? (string) $record['display_name'] : null;
    }

    public function ensureCategory(string $menuType, string $name): string
    {
        if (!$this->hasMasterTables()) {
            return trim($name);
        }

        $resolved = $this->resolveName($menuType, $name);
        if ($resolved !== null) {
            return $resolved;
        }

        $displayName = trim($name);
        $normalized = self::normalizeName($displayName);
        $categoryId = $this->repo->insertCategory([
            'menu_type'      => $menuType,
            'canonical_name' => $normalized,
            'display_name'   => $displayName,
            'slug'           => $this->uniqueSlug($menuType, $displayName),
            'sort_order'     => $this->repo->nextSortOrder($menuType),
            'is_active'      => 1,
        ]);
        $this->repo->upsertAlias($categoryId, $displayName, $normalized);
        return $displayName;
    }

    public function validateCategoryInputs(string $menuType, array $rawNames, array $createAllowed = []): array
    {
        $resolved = [];
        $unknown = [];
        $allowedLookup = [];
        foreach ($createAllowed as $value) {
            $allowedLookup[self::normalizeName((string) $value)] = true;
        }

        foreach ($rawNames as $rawName) {
            $raw = trim((string) $rawName);
            if ($raw === '') {
                continue;
            }

            $resolvedName = $this->resolveName($menuType, $raw);
            if ($resolvedName !== null) {
                $resolved[$raw] = $resolvedName;
                continue;
            }

            $normalized = self::normalizeName($raw);
            if (isset($allowedLookup[$normalized])) {
                $resolved[$raw] = $this->ensureCategory($menuType, $raw);
                continue;
            }

            $unknown[] = [
                'input'       => $raw,
                'normalized'  => $normalized,
                'suggestions' => $this->suggest($menuType, $raw),
            ];
        }

        return [
            'resolved' => $resolved,
            'unknown'  => $unknown,
        ];
    }

    public function suggest(string $menuType, string $rawName, int $limit = 5): array
    {
        if ($this->hasMasterTables()) {
            $this->syncFromLiveItems($menuType);
            $options = $this->repo->listActive($menuType);
        } else {
            $options = array_map(static function (array $option): array {
                return ['display_name' => (string) ($option['name'] ?? '')];
            }, $this->fallbackOptions($menuType));
        }

        $normalized = self::normalizeName($rawName);
        $ranked = [];

        foreach ($options as $option) {
            $candidate = (string) ($option['display_name'] ?? '');
            $candidateNormalized = self::normalizeName($candidate);
            if ($candidateNormalized === '') {
                continue;
            }

            $distance = levenshtein($normalized, $candidateNormalized);
            similar_text($normalized, $candidateNormalized, $similarity);
            if ($distance > max(4, (int) ceil(strlen($normalized) / 2)) && $similarity < 45.0) {
                continue;
            }

            $ranked[] = [
                'name'       => $candidate,
                'distance'   => $distance,
                'similarity' => round($similarity, 1),
            ];
        }

        usort($ranked, static function (array $left, array $right): int {
            return [$left['distance'], -$left['similarity'], $left['name']]
                <=> [$right['distance'], -$right['similarity'], $right['name']];
        });

        return array_slice(array_map(static function (array $item): array {
            return [
                'name'       => $item['name'],
                'distance'   => $item['distance'],
                'similarity' => $item['similarity'],
            ];
        }, $ranked), 0, $limit);
    }

    public function updateSortOrder(string $menuType, array $displayNames): int
    {
        if (!$this->hasMasterTables()) {
            return 0;
        }
        return $this->repo->updateSortOrder($menuType, $displayNames);
    }

    public function setActiveByName(string $menuType, string $displayName, bool $active): void
    {
        if (!$this->hasMasterTables()) {
            return;
        }

        $resolved = $this->resolveName($menuType, $displayName);
        if ($resolved === null) {
            return;
        }

        foreach ($this->repo->listAll($menuType) as $row) {
            if ((string) ($row['display_name'] ?? '') === $resolved) {
                $this->repo->setActive((int) $row['id'], $active);
                return;
            }
        }
    }

    public static function normalizeName(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return trim($value);
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value !== '' ? $value : 'category';
    }

    private function uniqueSlug(string $menuType, string $value): string
    {
        $base = $this->slugify($value);
        $slug = $base;
        $counter = 2;
        while ($this->repo->findBySlug($menuType, $slug) !== null) {
            $slug = $base . '-' . $counter;
            $counter++;
        }
        return $slug;
    }

    private function hasMasterTables(): bool
    {
        if ($this->masterTablesAvailable !== null) {
            return $this->masterTablesAvailable;
        }

        try {
            $db = Database::connection();
            $tables = [
                (string) $db->query("SHOW TABLES LIKE 'menu_categories'")->fetchColumn(),
                (string) $db->query("SHOW TABLES LIKE 'menu_category_aliases'")->fetchColumn(),
            ];
            $this->masterTablesAvailable = ($tables[0] === 'menu_categories' && $tables[1] === 'menu_category_aliases');
        } catch (\Throwable $e) {
            $this->masterTablesAvailable = false;
        }

        return $this->masterTablesAvailable;
    }

    private function fallbackOptions(string $menuType): array
    {
        $table = $menuType === 'bar' ? 'bar_menu_items' : 'food_menu_items';

        try {
            $db = Database::connection();
            $stmt = $db->query(
                "SELECT TRIM(category) AS category_name, MIN(category_sort_order) AS sort_order
                 FROM {$table}
                 WHERE TRIM(category) <> ''
                 GROUP BY TRIM(category)
                 ORDER BY MIN(category_sort_order) ASC, TRIM(category) ASC"
            );
            $rows = $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $rows = [];
        }

        return array_map(static function (array $row, int $index): array {
            $name = trim((string) ($row['category_name'] ?? ''));
            return [
                'id'        => $index + 1,
                'name'      => $name,
                'canonical' => self::normalizeName($name),
                'slug'      => self::normalizeName(str_replace(' ', '-', $name)),
                'sortOrder' => (int) ($row['sort_order'] ?? ($index + 1)),
                'isActive'  => true,
            ];
        }, $rows, array_keys($rows));
    }
}
