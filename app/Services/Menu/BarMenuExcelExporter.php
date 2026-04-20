<?php

declare(strict_types=1);

namespace NK\Services\Menu;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * BarMenuExcelExporter
 *
 * Builds an .xlsx workbook from bar_menu_items + bar_menu_item_variants rows.
 *
 * Variant columns are derived from the supplied $variants array.  Their order
 * is determined by variant_sort_order, then alphabetically for ties.
 *
 * Usage:
 *   BarMenuExcelExporter::toXlsxStream($items, $variants);
 *   // items    = rows from BarMenuRepository::listItems()
 *   // variants = keyed array from BarMenuVariantRepository::getVariantsForItems()
 */
class BarMenuExcelExporter
{
    private const SHEET_NAME = 'BAR MENU NK';

    /** Fixed meta columns — rendered before price columns */
    private const META_COLS = [
        'Category'        => 'category',
        'Item Name'       => 'item_name',
        'Description'     => 'description',
        'Availability'    => 'is_available',
        'Bar Man Special' => 'barman_special',
        'Image URL'       => 'image_url',
    ];

    /**
     * Stream an .xlsx response.
     *
     * @param array[] $items    Rows from bar_menu_items ordered by sort fields.
     * @param array   $variants Keyed by bar_item_id → array of variant rows.
     */
    public static function toXlsxStream(array $items, array $variants = []): void
    {
        $spreadsheet = self::build($items, $variants);
        (new Xlsx($spreadsheet))->save('php://output');
    }

    /**
     * Save to a file path on disk.
     */
    public static function toFile(array $items, array $variants, string $outputPath): void
    {
        $spreadsheet = self::build($items, $variants);
        (new Xlsx($spreadsheet))->save($outputPath);
    }

    // ── Builder ──────────────────────────────────────────────────────────────

    private static function build(array $items, array $variants): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(self::SHEET_NAME);

        // ── Derive ordered variant label list from all items ─────────────
        // variant_sort_order is stored per-row; collect unique labels ordered
        // by their first-seen sort order.
        $variantOrder = [];  // label => min sort_order seen
        foreach ($variants as $itemVariants) {
            foreach ($itemVariants as $v) {
                $label = $v['variant_label'];
                $ord   = (int) ($v['variant_sort_order'] ?? 999);
                if (!isset($variantOrder[$label]) || $ord < $variantOrder[$label]) {
                    $variantOrder[$label] = $ord;
                }
            }
        }
        asort($variantOrder);
        $orderedLabels = ExcelHeaderNormalizer::BAR_VARIANT_LABELS;
        foreach (array_keys($variantOrder) as $label) {
            if (!in_array($label, $orderedLabels, true)) {
                $orderedLabels[] = $label;
            }
        }

        // ── Build header row ──────────────────────────────────────────────
        $headers = array_keys(self::META_COLS);
        foreach ($orderedLabels as $label) {
            $headers[] = $label;
        }

        foreach ($headers as $colIdx => $header) {
            $sheet->getCell(self::cellAddress($colIdx + 1, 1))->setValue($header);
        }

        // Style headers
        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange   = "A1:{$lastColLetter}1";
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A237E']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setAutoFilter($headerRange);
        $sheet->freezePane('A2');

        // ── Write data rows ───────────────────────────────────────────────
        $rowNum = 2;
        $metaFields = array_values(self::META_COLS);
        $metaHeaders = array_keys(self::META_COLS);

        foreach ($items as $item) {
            $id = $item['id'];
            $itemVariants = $variants[$id] ?? [];

            // Index variants by label for quick lookup
            $varByLabel = [];
            foreach ($itemVariants as $v) {
                $varByLabel[$v['variant_label']] = $v['price'];
            }

            // Write meta cols
            foreach ($metaFields as $colidx => $field) {
                $raw = $item[$field] ?? null;
                if ($field === 'is_available') {
                    $val = $raw ? 'Available' : 'Unavailable';
                } elseif ($field === 'barman_special') {
                    $val = $raw ? 'Yes' : '';
                } else {
                    $val = $raw;
                }
                if ($val !== null && $val !== '') {
                    $sheet->getCell(self::cellAddress($colidx + 1, $rowNum))->setValue($val);
                }
            }

            // Write variant price cols
            $metaCount = count($metaHeaders);
            foreach ($orderedLabels as $labelIdx => $label) {
                $price = $varByLabel[$label] ?? null;
                if ($price !== null) {
                    $sheet->getCell(self::cellAddress($metaCount + $labelIdx + 1, $rowNum))->setValue($price);
                }
            }

            $rowNum++;
        }

        // Auto-size meta columns
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
