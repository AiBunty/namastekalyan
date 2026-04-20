<?php

declare(strict_types=1);

namespace NK\Services\Menu;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * FoodMenuExcelExporter
 *
 * Builds an .xlsx workbook from food_menu_items + food_menu_item_variants rows.
 *
 * Standard-mode items get one row per item with individual price columns.
 * Variant-backed rows are flattened into explicit portion/custom columns.
 *
 * Usage:
 *   FoodMenuExcelExporter::toXlsxStream($items, $variants);
 *   // items  = rows from FoodMenuRepository::listItems()
 *   // variants = keyed array from FoodMenuVariantRepository::getVariantsForItems()
 */
class FoodMenuExcelExporter
{
    private const SHEET_NAME = 'AWGNK MENU';

    /** Standard price columns header → DB column (in display order) */
    private const STANDARD_PRICE_COLS = [
        'Veg'      => 'price_veg',
        'Jain'     => 'price_jain',
        'Chicken'  => 'price_chicken',
        'Mutton'   => 'price_mutton',
        'Basa'     => 'price_basa',
        'Prawns'   => 'price_prawns',
        'Surmai'   => 'price_surmai',
        'Pomfret'  => 'price_pomfret',
        'Crab'     => 'price_crab',
        'Egg'      => 'price_egg',
        'Half'     => 'price_half',
        'Full'     => 'price_full',
        'Plain'    => 'price_plain',
        'Butter'   => 'price_butter',
        'Medium'   => 'price_medium',
        'Large'    => 'price_large',
        'Direct Price' => 'price_direct',
    ];

    private const FOOD_VARIANT_COLS = [
        '4 pcs',
        '6 pcs',
        '8 pcs',
        '9 pcs',
        '12 pcs',
    ];

    /** Meta columns header → DB field (always included) */
    private const META_COLS = [
        'Category'     => 'category',
        'Item Name'    => 'item_name',
        'Description'  => 'description',
        'Availability' => 'is_available',
        'Chef Special' => 'is_chef_special',
        'Spice Level'  => 'spice_level',
        'Unit'         => 'serving_unit',
        'Image URL'    => 'image_url',
    ];

    private const HELPER_COLS = [
        MenuImageFilename::FOOD_EXPORT_HEADER => '__image_file_name',
    ];

    /**
     * Write an .xlsx file directly to the PHP output stream (for download).
     * Caller must send headers before calling this.
     *
     * @param array[] $items    Rows from food_menu_items, ordered by category_sort_order, item_sort_order.
     * @param array   $variants Keyed by food_item_id → array of variant rows.
     */
    public static function toXlsxStream(array $items, array $variants = []): void
    {
        $spreadsheet = self::build($items, $variants);
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * Save an .xlsx file to $outputPath on disk.
     */
    public static function toFile(array $items, array $variants, string $outputPath): void
    {
        $spreadsheet = self::build($items, $variants);
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);
    }

    // ── Builder ──────────────────────────────────────────────────────────────

    private static function build(array $items, array $variants): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        // ── Determine variant columns across all custom_variant items ────
        $customVariantLabels = [];  // ordered list of variant label strings
        $customVariantLabels = self::FOOD_VARIANT_COLS;
        foreach ($items as $item) {
            $id = $item['id'];
            foreach ($variants[$id] ?? [] as $v) {
                $label = (string) ($v['variant_label'] ?? '');
                if ($label !== '' && !in_array($label, $customVariantLabels, true)) {
                    $customVariantLabels[] = $label;
                }
            }
        }

        // ── Build header row ──────────────────────────────────────────────
        $headers = [];
        $headerSourceMap = [];  // header string => ['type' => 'meta'|'price'|'variant', 'key' => ...]

        foreach (self::META_COLS as $header => $field) {
            $headers[] = $header;
            $headerSourceMap[$header] = ['type' => 'meta', 'key' => $field];
        }

        foreach (self::HELPER_COLS as $header => $field) {
            $headers[] = $header;
            $headerSourceMap[$header] = ['type' => 'helper', 'key' => $field];
        }

        foreach (self::STANDARD_PRICE_COLS as $header => $dbCol) {
            $headers[] = $header;
            $headerSourceMap[$header] = ['type' => 'price', 'key' => $dbCol];
        }

        foreach ($customVariantLabels as $label) {
            $headers[] = $label;
            $headerSourceMap[$label] = ['type' => 'variant', 'key' => $label];
        }

        // Write headers
        foreach ($headers as $colIdx => $header) {
            $cell = $sheet->getCell(self::cellAddress($colIdx + 1, 1));
            $cell->setValue($header);
        }

        // Style headers
        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange   = "A1:{$lastColLetter}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setAutoFilter($headerRange);
        $sheet->freezePane('A2');

        // ── Write data rows ───────────────────────────────────────────────
        $rowNum = 2;
        foreach ($items as $item) {
            $variantsByLabel = [];

            $id = $item['id'];
            foreach ($variants[$id] ?? [] as $v) {
                $variantsByLabel[(string) $v['variant_label']] = $v['price'];
            }

            foreach ($headers as $colIdx => $header) {
                $info = $headerSourceMap[$header];
                $val  = null;

                if ($info['type'] === 'meta') {
                    $raw = $item[$info['key']] ?? null;
                    if ($info['key'] === 'is_available') {
                        $val = $raw ? 'Available' : 'Unavailable';
                    } elseif ($info['key'] === 'is_chef_special') {
                        $val = $raw ? 'Yes' : '';
                    } else {
                        $val = $raw;
                    }
                } elseif ($info['type'] === 'helper') {
                    $val = MenuImageFilename::buildFoodBaseName(
                        (string) ($item['category'] ?? ''),
                        (string) ($item['item_name'] ?? '')
                    );
                } elseif ($info['type'] === 'price') {
                    $val = $item[$info['key']] ?? null;
                } elseif ($info['type'] === 'variant') {
                    $val = $variantsByLabel[$info['key']] ?? null;
                }

                if ($val !== null && $val !== '') {
                    $sheet->getCell(self::cellAddress($colIdx + 1, $rowNum))->setValue($val);
                }
            }

            $rowNum++;
        }

        // Auto-size key columns
        foreach (['A', 'B', 'C'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private static function cellAddress(int $columnIndex, int $rowIndex): string
    {
        return Coordinate::stringFromColumnIndex($columnIndex) . $rowIndex;
    }
}
