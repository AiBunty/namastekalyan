<?php

declare(strict_types=1);

namespace NK\Services\Menu;

/**
 * ExcelHeaderNormalizer
 *
 * Converts raw Excel header strings to canonical internal keys.
 *
 * Food columns mapped to DB column names (price_*).
 * Bar columns mapped to variant_label strings (preserved as-is after trim).
 * Static/meta columns mapped to DB field names.
 */
class ExcelHeaderNormalizer
{
    public const FOOD_IGNORED_META_MAP = [
        'image file name' => '_image_file_name',
        'bulk image name' => '_image_file_name',
    ];

    // ── Food price column map (Excel header → DB column name) ─────────────────
    public const FOOD_PRICE_MAP = [
        'veg'      => 'price_veg',
        'jain'     => 'price_jain',
        'chicken'  => 'price_chicken',
        'mutton'   => 'price_mutton',
        'basa'     => 'price_basa',
        'prawns'   => 'price_prawns',
        'prawn'    => 'price_prawns',   // alias
        'prawans'  => 'price_prawns',   // typo alias
        'surmai'   => 'price_surmai',
        'pomfret'  => 'price_pomfret',
        'crab'     => 'price_crab',
        'egg'      => 'price_egg',
        'half'     => 'price_half',
        'full'     => 'price_full',
        'plain'    => 'price_plain',
        'butter'   => 'price_butter',
        'medium'   => 'price_medium',
        'large'    => 'price_large',
        'direct'   => 'price_direct',
        'direct price' => 'price_direct',
    ];

    public const FOOD_VARIANT_LABELS = [
        '4 pcs',
        '6 pcs',
        '8 pcs',
        '9 pcs',
        '12 pcs',
    ];

    // ── Food static column map (Excel header → DB field name) ─────────────────
    public const FOOD_META_MAP = [
        'category'       => 'category',
        'item name'      => 'item_name',
        'description'    => 'description',
        'image url'      => 'image_url',
        'availability'   => 'is_available',
        'chef special'   => 'is_chef_special',
        "chef's special" => 'is_chef_special',
        'spice level'    => 'spice_level',
        'unit (pcs)'     => 'serving_unit',
        'serving unit'   => 'serving_unit',
        'unit'           => 'serving_unit',
    ];

    // ── Bar static column map (Excel header → DB field name) ──────────────────
    public const BAR_META_MAP = [
        'category'       => 'category',
        'item name'      => 'item_name',
        'description'    => 'description',
        'image url'      => 'image_url',
        'availability'   => 'is_available',
        'bar man special'    => 'barman_special',
        'barman special'     => 'barman_special',
        "bar man's special"  => 'barman_special',
    ];

    // ── Bar price/variant column labels (in canonical sort order) ─────────────
    // These are exact variant_label values that will be stored in bar_menu_item_variants.
    public const BAR_VARIANT_LABELS = [
        'Mocktail',
        '30Ml',
        '330Ml',
        '275Ml',
        'Glass',
        'Silver',
        'Repesado',
        'Anezo',
        'Btl',
        'Pitcher(1.5 Lit)',
    ];

    // ── Universal food categories (shown regardless of veg/non-veg filter) ────
    public const UNIVERSAL_FOOD_CATEGORIES = [
        'Breads', 'Bread',
        'Mocktails', 'Mocktail',
        'Shakes & Smoothies', 'Shakes and Smoothies',
        'Iced Tea',
        'Lemonades',
        'Cold Ones',
        'Dessert', 'Desserts',
        'Icy Virgin Margaritas',
    ];

    // ── Normalize a raw header to lowercase-trimmed key ───────────────────────

    public static function normalizeKey(string $raw): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $raw) ?? $raw));
    }

    // ── Map a food Excel header to a DB food_menu_items column ───────────────

    /**
     * Returns the DB column name for a food price column header,
     * or null if the header is not a price column.
     */
    public static function foodPriceColumn(string $raw): ?string
    {
        $key = self::normalizeKey($raw);
        return self::FOOD_PRICE_MAP[$key] ?? null;
    }

    /**
     * Returns the DB field name for a food meta column header,
     * or null if unrecognized.
     */
    public static function foodMetaField(string $raw): ?string
    {
        $key = self::normalizeKey($raw);
        return self::FOOD_META_MAP[$key] ?? null;
    }

    public static function foodIgnoredMetaField(string $raw): ?string
    {
        $key = self::normalizeKey($raw);
        return self::FOOD_IGNORED_META_MAP[$key] ?? null;
    }

    /**
     * Returns the DB field name for a bar meta column header,
     * or null if unrecognized.
     */
    public static function barMetaField(string $raw): ?string
    {
        $key = self::normalizeKey($raw);
        return self::BAR_META_MAP[$key] ?? null;
    }

    /**
     * Returns the canonical variant_label for a bar price column,
     * or null if not a price column.
     * Matches case-insensitively; preserves the canonical casing from BAR_VARIANT_LABELS.
     */
    public static function barVariantLabel(string $raw): ?string
    {
        $key = self::normalizeKey($raw);
        foreach (self::BAR_VARIANT_LABELS as $label) {
            if (self::normalizeKey($label) === $key) {
                return $label;
            }
        }
        // Accept unrecognized numeric price columns as extra variants (raw label preserved)
        return null;
    }

    public static function foodVariantLabel(string $raw): ?string
    {
        $key = self::normalizeKey($raw);
        foreach (self::FOOD_VARIANT_LABELS as $label) {
            if (self::normalizeKey($label) === $key) {
                return $label;
            }
        }
        return null;
    }

    /**
     * Returns the sort position index for a bar variant label (for variant_sort_order).
     * Unknown labels get a large sort index.
     */
    public static function barVariantSortOrder(string $label): int
    {
        $idx = array_search(
            self::normalizeKey($label),
            array_map([self::class, 'normalizeKey'], self::BAR_VARIANT_LABELS)
        );
        return $idx === false ? 999 : $idx;
    }

    /**
     * Returns true if the given food category name is "universal" (shown to all diet filters).
     */
    public static function isUniversalFoodCategory(string $category): bool
    {
        $lower = strtolower(trim($category));
        foreach (self::UNIVERSAL_FOOD_CATEGORIES as $u) {
            if ($lower === strtolower($u)) {
                return true;
            }
        }
        return false;
    }
}
