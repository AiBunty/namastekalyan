<?php

declare(strict_types=1);

namespace NK\Services\Menu;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * FoodMenuExcelImporter
 *
 * Reads the `AWGNK MENU` sheet from an .xlsx workbook and produces a
 * structured array of food-menu rows suitable for FoodMenuRepository::replaceAll().
 *
 * Two public entry-points:
 *   preview($path): returns diagnostics + sample rows (no DB writes).
 *   execute($path): returns the data array ready for persistence.
 *
 * Diet inference rules
 * ──────────────────
 *   is_veg       = true if price_veg column has a value AND price_chicken / mutton /
 *                  basa / prawns / surmai / pomfret / crab / egg are all empty.
 *   is_nonveg    = true if any of price_chicken, price_mutton, price_basa,
 *                  price_prawns, price_surmai, price_pomfret, price_crab, price_egg
 *                  has a value.
 *   is_jain      = true if price_jain column has a value.
 *   is_universal = true if category is in ExcelHeaderNormalizer::UNIVERSAL_FOOD_CATEGORIES.
 *
 * Pricing mode detection
 * ──────────────────────
 *   Portion/variant columns can exist on the sheet globally, but pricing_mode
 *   is still decided per row. Only rows with at least one populated variant
 *   value are stored as custom_variants.
 *
 * NOTE: variant rows are returned inside each item as $item['_variants'] = [
 *   ['variant_label'=>'...', 'price'=>0.0, 'variant_sort_order'=>0], ...
 * ]
 */
class FoodMenuExcelImporter
{
    public const SHEET_NAME = 'AWGNK MENU';

    /** Known non-price meta columns (DB fields inferred from normalizer) */
    private const META_KEY_SET = [
        'category', 'item_name', 'description',
        'image_url', 'is_available', 'is_chef_special',
        'spice_level', 'serving_unit',
    ];

    /** ── Public API ────────────────────────────────────────────────────── */

    /**
     * Return diagnostic summary without writing anything.
     *
     * @return array{
     *   sheet_found: bool,
     *   sheet_name: string,
     *   total_rows: int,
     *   data_rows: int,
     *   blank_rows_skipped: int,
     *   warnings: string[],
     *   errors: string[],
     *   mapped_columns: array<string,string>,
     *   unmapped_columns: string[],
     *   sample_rows: array,
     *   pricing_mode: string,
     *   categories: string[],
     *   variant_columns: string[]
     * }
     */
    public static function preview(string $path): array
    {
        [$warnings, $errors, $items, $meta] = self::parse($path);

        $sample = array_slice($items, 0, 5);

        return [
            'sheet_found'         => $meta['sheet_found'],
            'sheet_name'          => self::SHEET_NAME,
            'total_rows'          => $meta['total_rows'],
            'data_rows'           => count($items),
            'blank_rows_skipped'  => $meta['blank_rows_skipped'],
            'warnings'            => $warnings,
            'errors'              => $errors,
            'mapped_columns'      => $meta['mapped_columns'],
            'unmapped_columns'    => $meta['unmapped_columns'],
            'sample_rows'         => $sample,
            'pricing_mode'        => $meta['pricing_mode'],
            'categories'          => $meta['categories'],
            'variant_columns'     => $meta['variant_columns'],
        ];
    }

    /**
     * Parse and return the full item array (with _variants) for persistence.
     * Throws \RuntimeException on unrecoverable errors.
     *
     * @return array[]  Each element = flat DB row + optional '_variants' key.
     */
    public static function execute(string $path): array
    {
        [$warnings, $errors, $items] = self::parse($path);

        if (!empty($errors)) {
            throw new \RuntimeException(
                'FoodMenuExcelImporter errors: ' . implode('; ', $errors)
            );
        }

        return $items;
    }

    /** ── Core parser ─────────────────────────────────────────────────── */

    /**
     * @return array{ 0: string[], 1: string[], 2: array[], 3: array }
     */
    private static function parse(string $path): array
    {
        $warnings = [];
        $errors   = [];

        // ── Load workbook ────────────────────────────────────────────────
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Exception $e) {
            return [[], ["Cannot open file: {$e->getMessage()}"], [], self::emptyMeta()];
        }

        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);

        if ($sheet === null) {
            $available = implode(', ', $spreadsheet->getSheetNames());
            return [
                [],
                ["Sheet '" . self::SHEET_NAME . "' not found. Available: {$available}"],
                [],
                self::emptyMeta(false),
            ];
        }

        $meta = self::emptyMeta(true);

        // ── Read headers (row 1) ─────────────────────────────────────────
        $highestCol = $sheet->getHighestColumn();
        $highestRow = (int) $sheet->getHighestRow();
        $meta['total_rows'] = $highestRow - 1; // exclude header

        $headers    = [];  // colIndex => raw header string
        $colMapping = [];  // colIndex => DB key OR 'variant:Label'
        $unmapped   = [];  // raw headers we couldn't map

        for ($c = 1; $c <= Coordinate::columnIndexFromString($highestCol); $c++) {
            $raw = (string) self::cellValue($sheet, $c, 1);
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            $headers[$c] = $raw;
        }

        $priceCols   = [];  // colIndex => db column like price_veg
        $variantCols = [];  // colIndex => variant_label (custom_variants mode)
        $metaCols    = [];  // colIndex => db field name
        $ignoredMetaCols = []; // colIndex => helper column ignored on import

        foreach ($headers as $c => $raw) {
            if (($dbField = ExcelHeaderNormalizer::foodMetaField($raw)) !== null) {
                $metaCols[$c]    = $dbField;
                $meta['mapped_columns'][$raw] = $dbField;
            } elseif (($ignoredField = ExcelHeaderNormalizer::foodIgnoredMetaField($raw)) !== null) {
                $ignoredMetaCols[$c] = $ignoredField;
                $meta['mapped_columns'][$raw] = $ignoredField;
            } elseif (($dbCol = ExcelHeaderNormalizer::foodPriceColumn($raw)) !== null) {
                $priceCols[$c]   = $dbCol;
                $meta['mapped_columns'][$raw] = $dbCol;
            } elseif (($variantLabel = ExcelHeaderNormalizer::foodVariantLabel($raw)) !== null) {
                $variantCols[$c]            = $variantLabel;
                $meta['mapped_columns'][$raw] = "variant:{$variantLabel}";
            } else {
                // Unknown header — treat as custom variant column
                $variantCols[$c]            = $raw;
                $meta['mapped_columns'][$raw] = "variant:{$raw}";
                $unmapped[] = $raw;
            }
        }

        $meta['unmapped_columns'] = $unmapped;

        // Determine sheet capability; row mode is computed later.
        $pricingMode = count($variantCols) > 0 ? 'mixed' : 'standard';
        $meta['pricing_mode'] = $pricingMode;
        $meta['variant_columns'] = array_values($variantCols);

        if (!empty($variantCols)) {
            $warnings[] = 'Custom variant columns detected: ' . implode(', ', $variantCols)
                . '; rows with populated variant prices will be stored as custom_variants.';
        }

        // ── Read data rows ───────────────────────────────────────────────
        $items = [];

        $categorySortOrder = 0;
        $itemSortOrder     = 0;
        $lastCategory      = '';
        $blankSkipped      = 0;

        for ($row = 2; $row <= $highestRow; $row++) {
            // Blank row detection: check item_name cell
            $itemNameColIdx = null;
            foreach ($metaCols as $c => $f) {
                if ($f === 'item_name') {
                    $itemNameColIdx = $c;
                    break;
                }
            }

            if ($itemNameColIdx !== null) {
                $itemName = trim((string) self::cellValue($sheet, $itemNameColIdx, $row));
                if ($itemName === '') {
                    $blankSkipped++;
                    continue;
                }
            }

            $item = [
                'pricing_mode'        => 'standard',
                'category'            => '',
                'item_name'           => '',
                'description'         => null,
                'image_url'           => null,
                'is_available'        => 1,
                'is_chef_special'     => 0,
                'spice_level'         => null,
                'serving_unit'        => null,
                'is_veg'              => 0,
                'is_nonveg'           => 0,
                'is_jain'             => 0,
                'is_universal'        => 0,
                'category_sort_order' => 0,
                'item_sort_order'     => 0,
                'source_row'          => $row,
                '_variants'           => [],
            ];

            // ── Fill price columns ───────────────────────────────────────
            foreach ($priceCols as $c => $dbCol) {
                $val = self::cellValue($sheet, $c, $row);
                $item[$dbCol] = self::parsePrice($val);
            }

            // ── Fill meta columns ────────────────────────────────────────
            foreach ($metaCols as $c => $field) {
                $val = self::cellValue($sheet, $c, $row);

                if ($field === 'is_available') {
                    $item[$field] = self::parseBool($val, true);
                } elseif ($field === 'is_chef_special') {
                    $item[$field] = self::parseBool($val, false);
                } else {
                    $item[$field] = trim((string) $val);
                }
            }

            foreach ($ignoredMetaCols as $c => $_ignoredField) {
                self::cellValue($sheet, $c, $row);
            }

            // ── Fill custom variant columns ──────────────────────────────
            $varSortIdx = 0;
            foreach ($variantCols as $c => $label) {
                $val = self::cellValue($sheet, $c, $row);
                $price = self::parsePrice($val);
                if ($price !== null) {
                    $item['_variants'][] = [
                        'variant_label'      => $label,
                        'price'              => $price,
                        'variant_sort_order' => $varSortIdx,
                    ];
                }
                $varSortIdx++;
            }
            if (!empty($item['_variants'])) {
                $item['pricing_mode'] = 'custom_variants';
            }

            // ── Category sort tracking ───────────────────────────────────
            $cat = $item['category'];
            if ($cat !== $lastCategory) {
                $categorySortOrder++;
                $itemSortOrder = 0;
                $lastCategory  = $cat;
            }
            $itemSortOrder++;
            $item['category_sort_order'] = $categorySortOrder;
            $item['item_sort_order']     = $itemSortOrder;

            // ── Infer diet flags ─────────────────────────────────────────
            $item['is_universal'] = ExcelHeaderNormalizer::isUniversalFoodCategory($cat) ? 1 : 0;

            if ($item['pricing_mode'] === 'standard') {
                $nonVegCols = [
                    'price_chicken', 'price_mutton', 'price_basa',
                    'price_prawns',  'price_surmai', 'price_pomfret',
                    'price_crab',    'price_egg',
                ];

                $hasVeg    = isset($item['price_veg'])  && $item['price_veg'] !== null;
                $hasJain   = isset($item['price_jain']) && $item['price_jain'] !== null;
                $hasNonVeg = false;

                foreach ($nonVegCols as $col) {
                    if (isset($item[$col]) && $item[$col] !== null) {
                        $hasNonVeg = true;
                        break;
                    }
                }

                $item['is_veg']    = ($hasVeg && !$hasNonVeg) ? 1 : 0;
                $item['is_nonveg'] = $hasNonVeg ? 1 : 0;
                $item['is_jain']   = $hasJain ? 1 : 0;
            }

            $items[] = $item;
        }

        $meta['blank_rows_skipped'] = $blankSkipped;
        $meta['categories'] = array_values(array_unique(array_filter(array_map(
            static fn(array $item): string => trim((string) ($item['category'] ?? '')),
            $items
        ))));

        return [$warnings, $errors, $items, $meta];
    }

    /** ── Helpers ─────────────────────────────────────────────────────── */

    private static function parsePrice(mixed $val): ?float
    {
        if ($val === null || $val === '') {
            return null;
        }
        // Remove currency symbols and whitespace
        $val = preg_replace('/[^\d.]/', '', (string) $val);
        if ($val === '' || $val === '.') {
            return null;
        }
        $f = (float) $val;
        return $f > 0 ? $f : null;
    }

    private static function parseBool(mixed $val, bool $default): int
    {
        if ($val === null || $val === '') {
            return $default ? 1 : 0;
        }
        $lower = strtolower(trim((string) $val));
        if (in_array($lower, ['no', 'false', '0', 'unavailable', 'n'], true)) {
            return 0;
        }
        if (in_array($lower, ['yes', 'true', '1', 'available', 'y'], true)) {
            return 1;
        }
        return $default ? 1 : 0;
    }

    private static function emptyMeta(bool $sheetFound = false): array
    {
        return [
            'sheet_found'       => $sheetFound,
            'total_rows'        => 0,
            'blank_rows_skipped'=> 0,
            'mapped_columns'    => [],
            'unmapped_columns'  => [],
            'pricing_mode'      => 'standard',
            'categories'        => [],
            'variant_columns'   => [],
        ];
    }

    private static function cellValue(Worksheet $sheet, int $columnIndex, int $rowIndex): mixed
    {
        return $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getValue();
    }
}
