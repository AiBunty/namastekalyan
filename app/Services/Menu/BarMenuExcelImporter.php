<?php

declare(strict_types=1);

namespace NK\Services\Menu;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * BarMenuExcelImporter
 *
 * Reads the `BAR MENU NK` sheet from an .xlsx workbook and produces a
 * structured array of bar-menu item rows for BarMenuRepository::replaceAll().
 *
 * Variants
 * ────────
 * Every column that is NOT recognised as a meta field (category, item_name,
 * description, availability, image_url, barman_special) is treated as a
 * price-variant column.  The column header becomes the variant_label.
 * Known canonical labels (ExcelHeaderNormalizer::BAR_VARIANT_LABELS) get
 * their canonical casing and sort order; unknown labels are appended last in
 * spreadsheet column order.
 *
 * Each variant row (non-empty price) is returned inside the item as:
 *   $item['_variants'] = [
 *     ['variant_label' => '30Ml', 'price' => 200.0, 'variant_sort_order' => 1],
 *     ...
 *   ]
 */
class BarMenuExcelImporter
{
    public const SHEET_NAME = 'BAR MENU NK';

    /** ── Public API ─────────────────────────────────────────────────── */

    /**
     * @return array{
     *   sheet_found: bool,
     *   sheet_name: string,
     *   total_rows: int,
     *   data_rows: int,
     *   blank_rows_skipped: int,
     *   warnings: string[],
     *   errors: string[],
     *   mapped_meta_columns: array<string,string>,
     *   variant_columns: string[],
     *   sample_rows: array,
     *   categories: string[]
     * }
     */
    public static function preview(string $path): array
    {
        [$warnings, $errors, $items, $meta] = self::parse($path);
        $sample = array_slice($items, 0, 5);

        return [
            'sheet_found'          => $meta['sheet_found'],
            'sheet_name'           => self::SHEET_NAME,
            'total_rows'           => $meta['total_rows'],
            'data_rows'            => count($items),
            'blank_rows_skipped'   => $meta['blank_rows_skipped'],
            'warnings'             => $warnings,
            'errors'               => $errors,
            'mapped_meta_columns'  => $meta['mapped_meta'],
            'variant_columns'      => $meta['variant_cols'],
            'sample_rows'          => $sample,
            'categories'           => $meta['categories'],
        ];
    }

    /**
     * Parse and return full item array (with _variants) for persistence.
     * Throws \RuntimeException on unrecoverable errors.
     */
    public static function execute(string $path): array
    {
        [$warnings, $errors, $items] = self::parse($path);

        if (!empty($errors)) {
            throw new \RuntimeException(
                'BarMenuExcelImporter errors: ' . implode('; ', $errors)
            );
        }

        return $items;
    }

    /** ── Core parser ─────────────────────────────────────────────────── */

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
        $highestColLetter = $sheet->getHighestColumn();
        $highestRow       = (int) $sheet->getHighestRow();
        $meta['total_rows'] = $highestRow - 1;

        $colCount = Coordinate::columnIndexFromString($highestColLetter);

        $metaCols    = [];   // colIdx => DB field
        $variantCols = [];   // colIdx => variant_label (canonical string)

        for ($c = 1; $c <= $colCount; $c++) {
            $raw = trim((string) self::cellValue($sheet, $c, 1));
            if ($raw === '') {
                continue;
            }

            $dbField = ExcelHeaderNormalizer::barMetaField($raw);
            if ($dbField !== null) {
                $metaCols[$c] = $dbField;
                $meta['mapped_meta'][$raw] = $dbField;
            } else {
                // Price/variant column — get canonical label if known, else use raw
                $canonical = ExcelHeaderNormalizer::barVariantLabel($raw);
                $label = $canonical ?? $raw;
                $variantCols[$c] = $label;
                $meta['variant_cols'][] = $label;
            }
        }

        if (empty($variantCols)) {
            $warnings[] = 'No price/variant columns detected in bar sheet.';
        }

        // ── Item name column index ────────────────────────────────────────
        $itemNameColIdx = null;
        foreach ($metaCols as $c => $f) {
            if ($f === 'item_name') {
                $itemNameColIdx = $c;
                break;
            }
        }

        // ── Read data rows ────────────────────────────────────────────────
        $items = [];

        $categorySortOrder = 0;
        $itemSortOrder     = 0;
        $lastCategory      = '';
        $blankSkipped      = 0;

        // Build variant sort order map once
        $variantSortMap = [];
        foreach ($variantCols as $c => $label) {
            $variantSortMap[$c] = ExcelHeaderNormalizer::barVariantSortOrder($label);
        }
        // Re-sort variant columns by their canonical sort order for consistent iteration
        asort($variantSortMap);
        $orderedVariantCols = array_keys($variantSortMap);  // colIdx[] sorted by sort order

        for ($row = 2; $row <= $highestRow; $row++) {
            // Blank-row skip
            if ($itemNameColIdx !== null) {
                $nameVal = trim((string) self::cellValue($sheet, $itemNameColIdx, $row));
                if ($nameVal === '') {
                    $blankSkipped++;
                    continue;
                }
            }

            $item = [
                'category'            => '',
                'item_name'           => '',
                'description'         => null,
                'image_url'           => null,
                'is_available'        => 1,
                'barman_special'      => 0,
                'category_sort_order' => 0,
                'item_sort_order'     => 0,
                'source_row'          => $row,
                '_variants'           => [],
            ];

            // ── Meta columns ─────────────────────────────────────────────
            foreach ($metaCols as $c => $field) {
                $val = self::cellValue($sheet, $c, $row);

                if ($field === 'is_available') {
                    $item[$field] = self::parseBool($val, true);
                } elseif ($field === 'barman_special') {
                    $item[$field] = self::parseBool($val, false);
                } else {
                    $item[$field] = trim((string) $val);
                }
            }

            // ── Variant columns (sorted) ──────────────────────────────────
            $varSortEphemeral = 0;
            foreach ($orderedVariantCols as $c) {
                $label = $variantCols[$c];
                $val   = self::cellValue($sheet, $c, $row);
                $price = self::parsePrice($val);

                if ($price !== null) {
                    $item['_variants'][] = [
                        'variant_label'      => $label,
                        'price'              => $price,
                        'variant_sort_order' => $variantSortMap[$c],
                    ];
                }
                $varSortEphemeral++;
            }

            // ── Category sort tracking ────────────────────────────────────
            $cat = $item['category'];
            if ($cat !== $lastCategory) {
                $categorySortOrder++;
                $itemSortOrder = 0;
                $lastCategory  = $cat;
            }
            $itemSortOrder++;
            $item['category_sort_order'] = $categorySortOrder;
            $item['item_sort_order']     = $itemSortOrder;

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
        if (in_array($lower, ['no', 'false', '0', 'n', 'unavailable'], true)) {
            return 0;
        }
        if (in_array($lower, ['yes', 'true', '1', 'y', 'available'], true)) {
            return 1;
        }
        return $default ? 1 : 0;
    }

    private static function emptyMeta(bool $sheetFound = false): array
    {
        return [
            'sheet_found'    => $sheetFound,
            'total_rows'     => 0,
            'blank_rows_skipped' => 0,
            'mapped_meta'    => [],
            'variant_cols'   => [],
            'categories'     => [],
        ];
    }

    private static function cellValue(Worksheet $sheet, int $columnIndex, int $rowIndex): mixed
    {
        return $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex)->getValue();
    }
}
