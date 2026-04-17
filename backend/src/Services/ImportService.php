<?php

declare(strict_types=1);

namespace NK\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use NK\Config\Constants;
use NK\Models\MenuItem;
use NK\Repositories\MenuRepository;

class ImportService
{
    /** Categories whose items are never meat/fish — treated as universal when not marked veg/jain */
    private const UNIVERSAL_CATEGORIES = [
        'Mocktails', 'Shakes & Smoothies', 'Iced Tea', 'Lemonades',
        'Cold Ones', 'Breads', 'Dessert', 'Desserts',
    ];

    /** Allowed primary diet values */
    private const ALLOWED_DIETS = ['veg', 'nonveg', 'jain', 'mixed', 'universal', 'bar', ''];

    private MenuRepository $repo;

    public function __construct()
    {
        $this->repo = new MenuRepository();
    }

    // ─────────────────────────────────────────────────────────── Public API ──

    /**
     * Parse an uploaded Excel file and return a preview (first 50 rows) without touching the DB.
     */
    public function previewImport(string $tmpPath, string $sheetType): array
    {
        $rows    = $this->readXlsx($tmpPath, $sheetType);
        $preview = array_slice($rows, 0, 50);

        // Persist the uploaded file so the execute step can reference it
        $tmpDir = __DIR__ . '/../../storage/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0750, true);
        }
        $savedPath = $tmpDir . '/' . $sheetType . '_import_' . bin2hex(random_bytes(8)) . '.xlsx';
        copy($tmpPath, $savedPath);

        return [
            'ok'        => true,
            'sheetType' => $sheetType,
            'totalRows' => count($rows),
            'rows'      => $preview,
            'tmpPath'   => $savedPath,
        ];
    }

    /**
     * Execute a full import: optionally take a snapshot first, then replace all rows for $sheetType.
     */
    public function executeImport(string $tmpPath, string $sheetType, bool $takeSnapshot = true): array
    {
        $rows = $this->readXlsx($tmpPath, $sheetType);

        if (empty($rows)) {
            return ['ok' => false, 'error' => 'EMPTY_FILE', 'message' => 'No data rows found in the uploaded file.'];
        }

        if ($takeSnapshot) {
            $this->repo->createSnapshot($sheetType, 'Pre-import snapshot', 'import', 'system');
        }

        $inserted = 0;
        $skipped  = 0;

        // Delete all existing rows for this sheet and re-insert
        $this->repo->deleteAllItems($sheetType);

        foreach ($rows as $row) {
            if (trim((string) ($row['item_name'] ?? '')) === '') {
                $skipped++;
                continue;
            }
            $row['sheet_type'] = $sheetType;
            $this->repo->addItem($row);
            $inserted++;
        }

        return [
            'ok'       => true,
            'inserted' => $inserted,
            'skipped'  => $skipped,
            'message'  => "Import complete: {$inserted} rows inserted, {$skipped} skipped.",
        ];
    }

    /**
     * Export the current DB contents for $sheetType to a temp .xlsx file.
     * Returns the full path to the generated file.
     */
    public function exportToXlsx(string $sheetType): string
    {
        $rows    = $this->repo->listItems($sheetType);
        $headers = $this->repo->getSchema($sheetType);

        if (empty($headers)) {
            $headers = $sheetType === Constants::MENU_SHEET_FOOD
                ? ['Category', 'Sub Category', 'Item Name', 'Description', 'Image URL', 'Jain', 'Chef Special', 'Spice Level', 'Unit (Pcs)', 'Price', 'Availability', 'Food Category', 'Primary Diet']
                : ['Category', 'Sub Category', 'Item Name', 'Description', 'Price', 'Availability'];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetType === Constants::MENU_SHEET_FOOD ? 'Food Menu' : 'Bar Menu');

        // Header row styling
        $col = 1;
        foreach ($headers as $header) {
            $cell = $sheet->getCellByColumnAndRow($col, 1);
            $cell->setValue($header);
            $col++;
        }

        $headerRange = 'A1:' . $sheet->getCellByColumnAndRow(count($headers), 1)->getCoordinate();
        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '214038']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Data rows
        $rowIdx = 2;
        foreach ($rows as $row) {
            $item = MenuItem::fromDb($row)->toArray();
            $cells = $this->itemToHeaderCells($item, $headers);

            $col = 1;
            foreach ($headers as $header) {
                $sheet->getCellByColumnAndRow($col, $rowIdx)->setValue($cells[$header] ?? '');
                $col++;
            }
            $rowIdx++;
        }

        // Auto-size columns
        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $tmpDir  = __DIR__ . '/../../storage/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0750, true);
        }
        $outPath = $tmpDir . '/' . $sheetType . '_export_' . date('Ymd_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($outPath);

        return $outPath;
    }

    // ───────────────────────────────────────────────── Private: Excel reader ──
    /**
     * Build a minimal import template XLSX with the schema headers + one annotated sample row.
     * Returns the path to the generated temp file.
     */
    public function buildTemplateXlsx(string $sheetType): string
    {
        $headers = $this->repo->getSchema($sheetType);

        if (empty($headers)) {
            $headers = $sheetType === Constants::MENU_SHEET_FOOD
                ? ['Category', 'Sub Category', 'Item Name', 'Description', 'Image URL', 'Food Category', 'Jain', 'Is Veg', 'Is Nonveg', 'Is Universal', 'Chef Special', 'Spice Level', 'Unit (Pcs)', 'Price', 'Availability', 'Primary Diet']
                : ['Category', 'Sub Category', 'Item Name', 'Description', 'Price', 'Availability'];
        }

        $sampleRow = [];
        foreach ($headers as $header) {
            $lower = strtolower(trim((string) $header));
            $sampleRow[] = match (true) {
                in_array($lower, ['category'], true)                => 'Starters',
                in_array($lower, ['sub category'], true)            => 'Soups',
                in_array($lower, ['item name'], true)               => 'Sample Dish',
                in_array($lower, ['description'], true)             => 'A short description',
                in_array($lower, ['image url'], true)               => '',
                in_array($lower, ['food category'], true)           => 'Veg',
                in_array($lower, ['availability'], true)            => 'Yes',
                in_array($lower, ['jain', 'is jain'], true)         => 'No',
                in_array($lower, ['is veg'], true)                  => 'Yes',
                in_array($lower, ['is nonveg', 'is non veg'], true) => 'No',
                in_array($lower, ['is universal'], true)            => 'No',
                in_array($lower, ['chef special', "chef's special"], true) => 'No',
                in_array($lower, ['spice level', 'spice'], true)    => 'Medium',
                in_array($lower, ['unit (pcs)', 'serving unit'], true) => 'Full',
                in_array($lower, ['primary diet'], true)            => 'veg',
                in_array($lower, ['price', 'base price'], true)     => '250',
                default                                             => '350',
            };
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetType === Constants::MENU_SHEET_FOOD ? 'Food Menu' : 'Bar Menu');

        // Header row
        for ($col = 1; $col <= count($headers); $col++) {
            $sheet->getCellByColumnAndRow($col, 1)->setValue($headers[$col - 1]);
        }
        $headerRange = 'A1:' . $sheet->getCellByColumnAndRow(count($headers), 1)->getCoordinate();
        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '214038']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Sample data row
        for ($col = 1; $col <= count($sampleRow); $col++) {
            $sheet->getCellByColumnAndRow($col, 2)->setValue($sampleRow[$col - 1]);
        }

        for ($c = 1; $c <= count($headers); $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        $tmpDir = __DIR__ . '/../../storage/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0750, true);
        }
        $outPath = $tmpDir . '/' . $sheetType . '_template_' . date('Ymd_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($outPath);

        return $outPath;
    }
    private function readXlsx(string $tmpPath, string $sheetType): array
    {
        $spreadsheet = IOFactory::load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();

        $rowData = $sheet->toArray(null, true, true, true); // keyed A/B/C...

        if (empty($rowData)) {
            return [];
        }

        // First row = headers
        $headerRow = array_values($rowData)[0];
        $headers   = [];
        foreach ($headerRow as $colLetter => $value) {
            $headers[$colLetter] = trim((string) ($value ?? ''));
        }

        $result = [];
        $rowCount = 0;
        foreach (array_slice(array_values($rowData), 1) as $rawRow) {
            $cells = [];
            foreach ($rawRow as $colLetter => $value) {
                $header = $headers[$colLetter] ?? '';
                if ($header !== '') {
                    $cells[$header] = trim((string) ($value ?? ''));
                }
            }

            // Skip fully empty rows
            $hasContent = false;
            foreach ($cells as $v) {
                if ($v !== '') {
                    $hasContent = true;
                    break;
                }
            }
            if (!$hasContent) {
                continue;
            }

            $result[] = $this->cellsToDbPayload($cells, $sheetType);
            $rowCount++;
        }

        return $result;
    }

    /**
     * Convert a header-keyed row from Excel into a DB-ready payload array.
     */
    private function cellsToDbPayload(array $cells, string $sheetType): array
    {
        $headerMap = [
            'category'       => ['category'],
            'sub_category'   => ['sub category', 'subcategory'],
            'item_name'      => ['item name', 'item'],
            'description'    => ['description', 'desc'],
            'image_url'      => ['image url', 'image', 'img url'],
            'is_available'   => ['availability', 'available', 'active'],
            'is_jain'        => ['is jain', 'jain dish', 'jain item'],
            'is_chef_special'=> ['chef special', "chef's special", 'chef special?'],
            'spice_level'    => ['spice level', 'spice'],
            'serving_unit'   => ['unit (pcs)', 'serving unit', 'unit'],
            'food_category'  => ['food category', 'food cat'],
            'primary_diet'   => ['primary diet', 'diet'],
            'is_veg'         => ['is veg', 'veg flag', 'vegetarian'],
            'is_nonveg'      => ['is nonveg', 'is non veg', 'nonveg flag', 'non vegetarian'],
            'is_universal'   => ['is universal', 'universal', 'all diet'],
        ];

        $mapped = [];
        $usedHeaders = [];

        foreach ($headerMap as $dbCol => $aliases) {
            foreach ($cells as $header => $value) {
                if (in_array(strtolower($header), $aliases, true)) {
                    $mapped[$dbCol] = $value;
                    $usedHeaders[] = $header;
                    break;
                }
            }
        }

        // Everything else that looks like a price column
        $priceColumns = [];
        $basePrice = null;
        foreach ($cells as $header => $value) {
            if (in_array($header, $usedHeaders, true)) {
                continue;
            }
            if ($value !== '' && is_numeric($value)) {
                $priceColumns[$header] = (float) $value;
                if ($header === 'Price' || $basePrice === null) {
                    $basePrice = (float) $value;
                }
            }
        }

        // Normalize booleans
        $availability = $this->normalizeBool($mapped['is_available'] ?? 'yes');
        $isJain       = $this->normalizeBool($mapped['is_jain'] ?? '');
        $isVeg        = $this->normalizeBool($mapped['is_veg'] ?? '');
        $isNonveg     = $this->normalizeBool($mapped['is_nonveg'] ?? '');
        $isUniversal  = $this->normalizeBool($mapped['is_universal'] ?? '');
        $isChef       = $this->normalizeBool($mapped['is_chef_special'] ?? '');

        // Food category (Veg/NonVeg/Jain only for food sheet)
        $foodCat = '';
        if ($sheetType === Constants::MENU_SHEET_FOOD) {
            $fc = trim((string) ($mapped['food_category'] ?? ''));
            if (in_array($fc, ['Veg', 'NonVeg', 'Jain'], true)) {
                $foodCat = $fc;
            }
        }

        // Primary diet — classify from available signals if not set
        $primaryDiet = strtolower(trim((string) ($mapped['primary_diet'] ?? '')));
        if (!in_array($primaryDiet, self::ALLOWED_DIETS, true) || $primaryDiet === '') {
            $primaryDiet = $this->classifyDiet(
                $isJain,
                $foodCat,
                $mapped['category'] ?? '',
                $sheetType
            );
        }

        return [
            'category'        => trim((string) ($mapped['category'] ?? '')),
            'sub_category'    => trim((string) ($mapped['sub_category'] ?? '')),
            'item_name'       => trim((string) ($mapped['item_name'] ?? '')),
            'description'     => trim((string) ($mapped['description'] ?? '')),
            'image_url'       => trim((string) ($mapped['image_url'] ?? '')),
            'is_available'    => $availability,
            'is_jain'         => $isJain,
            'is_veg'          => $isVeg,
            'is_nonveg'       => $isNonveg,
            'is_universal'    => $isUniversal,
            'is_chef_special' => $isChef,
            'spice_level'     => trim((string) ($mapped['spice_level'] ?? '')),
            'serving_unit'    => trim((string) ($mapped['serving_unit'] ?? '')),
            'base_price'      => $basePrice,
            'price_columns'   => $priceColumns,
            'food_category'   => $foodCat,
            'primary_diet'    => $primaryDiet,
            'meta_json'       => [],
            'sort_order'      => 0,
        ];
    }

    /**
     * Classify primary diet from available data signals.
     */
    private function classifyDiet(bool $isJain, string $foodCat, string $category, string $sheetType): string
    {
        if ($sheetType === Constants::MENU_SHEET_BAR) {
            return 'bar';
        }
        if ($isJain || strtolower($foodCat) === 'jain') {
            return 'jain';
        }
        if (strtolower($foodCat) === 'veg') {
            return 'veg';
        }
        if (strtolower($foodCat) === 'nonveg') {
            return 'nonveg';
        }
        // Check category against universal list
        foreach (self::UNIVERSAL_CATEGORIES as $uCat) {
            if (stripos($category, $uCat) !== false || stripos($uCat, $category) !== false) {
                return 'universal';
            }
        }
        return 'veg'; // default to veg when ambiguous food category
    }

    private function normalizeBool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'yes', 'true', 'available', 'on', 'active'], true);
    }

    /**
     * Convert a MenuItem toArray() result into header-keyed cells for Excel export.
     */
    private function itemToHeaderCells(array $item, array $headers): array
    {
        $staticMap = [
            'Category'      => $item['category'] ?? '',
            'Sub Category'  => $item['subCategory'] ?? '',
            'Item Name'     => $item['itemName'] ?? '',
            'Description'   => $item['description'] ?? '',
            'Image URL'     => $item['imageUrl'] ?? '',
            'Availability'  => !empty($item['isAvailable']) ? 'Available' : 'Unavailable',
            'Food Category' => $item['foodCategory'] ?? '',
            'Jain'          => !empty($item['isJain']) ? 'Yes' : 'No',
            'Chef Special'  => !empty($item['isChefSpecial']) ? 'Yes' : 'No',
            "Chef's Special"=> !empty($item['isChefSpecial']) ? 'Yes' : 'No',
            'Spice Level'   => $item['spiceLevel'] ?? '',
            'Unit (Pcs)'    => $item['servingUnit'] ?? '',
            'Serving Unit'  => $item['servingUnit'] ?? '',
            'Price'         => $item['basePrice'] !== null ? (string) $item['basePrice'] : '',
            'Base Price'    => $item['basePrice'] !== null ? (string) $item['basePrice'] : '',
            'Primary Diet'  => $item['primaryDiet'] ?? '',
            'Diet Flags'    => implode(', ', $item['computedDiets'] ?? []),
        ];

        $cells = [];
        foreach ($headers as $header) {
            if (array_key_exists($header, $staticMap)) {
                $cells[$header] = $staticMap[$header];
            } elseif (isset($item['priceColumns'][$header])) {
                $cells[$header] = (string) $item['priceColumns'][$header];
            } else {
                $cells[$header] = '';
            }
        }
        return $cells;
    }
}
