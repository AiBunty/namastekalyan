<?php

/**
 * database/import_xlsx_to_db.php
 *
 * CLI-only.  Reads "Namaste Kalyan Menu.xlsx" from the same directory and
 * populates the new schema tables:
 *   food_menu_items, food_menu_item_variants
 *   bar_menu_items,  bar_menu_item_variants
 *
 * No PhpSpreadsheet required — uses PHP's built-in ZipArchive + SimpleXML.
 *
 * Usage:
 *   php database/import_xlsx_to_db.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('403 – CLI only');
}

date_default_timezone_set('Asia/Kolkata');

// ── Paths ─────────────────────────────────────────────────────────────────────

$scriptDir   = __DIR__;                                    // …/database/
$projectRoot = dirname($scriptDir);                        // …/namastekalyan/
$xlsxFile    = $scriptDir . DIRECTORY_SEPARATOR . 'Namaste Kalyan Menu.xlsx';

if (!file_exists($xlsxFile)) {
    fwrite(STDERR, "ERROR: Excel file not found:\n  $xlsxFile\n");
    exit(1);
}

// ── Load .env ─────────────────────────────────────────────────────────────────

$envPath = $projectRoot . '/.env';
$env     = [];

if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
}

$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_NAME'] ?? '';
$dbUser = $env['DB_USER'] ?? '';
$dbPass = $env['DB_PASS'] ?? '';

// ── DB connection ─────────────────────────────────────────────────────────────

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (\PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Connected to DB: {$dbName} @ {$dbHost}:{$dbPort}\n\n";

// ══════════════════════════════════════════════════════════════════════════════
// XLSX READER
// ══════════════════════════════════════════════════════════════════════════════

class XlsxReader
{
    private ZipArchive $zip;
    /** @var string[] */
    private array $sharedStrings = [];
    /** @var array<string,string>|null  sheet-name → xl/worksheets/sheetN.xml */
    private ?array $sheetMap = null;

    public function open(string $path): void
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($path) !== true) {
            throw new RuntimeException("Cannot open XLSX: {$path}");
        }
        $this->sharedStrings = $this->loadSharedStrings();
    }

    public function close(): void
    {
        $this->zip->close();
    }

    // ── Shared strings ────────────────────────────────────────────────────────

    private function loadSharedStrings(): array
    {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        // Suppress errors from malformed inner XML
        $d = @simplexml_load_string($xml);
        if ($d === false) {
            return [];
        }

        $strings = [];
        foreach ($d->si as $si) {
            if (isset($si->t)) {
                // Plain text <si><t>…</t></si>
                $strings[] = (string) $si->t;
            } else {
                // Rich text <si><r><t>…</t></r>…</si>
                $text = '';
                foreach ($si->r ?? [] as $r) {
                    $text .= (string) ($r->t ?? '');
                }
                $strings[] = $text;
            }
        }

        return $strings;
    }

    // ── Sheet map ─────────────────────────────────────────────────────────────

    private function loadSheetMap(): array
    {
        if ($this->sheetMap !== null) {
            return $this->sheetMap;
        }

        // workbook.xml lists sheets with their r:id
        $wbXml = $this->zip->getFromName('xl/workbook.xml');
        if ($wbXml === false) {
            throw new RuntimeException('xl/workbook.xml not found in archive.');
        }
        $wb = simplexml_load_string($wbXml);

        // workbook.xml.rels maps r:id → relative file path
        $relsXml = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($relsXml === false) {
            throw new RuntimeException('xl/_rels/workbook.xml.rels not found.');
        }
        $rels = simplexml_load_string($relsXml);

        $idToFile = [];
        foreach ($rels->Relationship as $rel) {
            $a = $rel->attributes();
            $idToFile[(string) $a['Id']] = (string) $a['Target'];
        }

        $rNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        $this->sheetMap = [];
        foreach ($wb->sheets->sheet as $sheet) {
            $name = (string) $sheet->attributes()['name'];
            $rid  = (string) $sheet->attributes($rNs)['id'];
            if (isset($idToFile[$rid])) {
                $target = ltrim($idToFile[$rid], '/');
                // Target may be "worksheets/sheet1.xml" (relative to xl/) or already absolute
                $this->sheetMap[$name] = (strpos($target, 'xl/') === 0)
                    ? $target
                    : 'xl/' . $target;
            }
        }

        return $this->sheetMap;
    }

    public function getSheetNames(): array
    {
        return array_keys($this->loadSheetMap());
    }

    // ── Read sheet → 2-D array of strings ────────────────────────────────────

    /**
     * Returns a 0-indexed array of rows; each row is a 0-indexed array of
     * cell values (always strings).  Empty cells are represented as ''.
     * The result is dense: no gaps in column indices within a row.
     */
    public function readSheet(string $name): array
    {
        $map = $this->loadSheetMap();
        if (!isset($map[$name])) {
            $avail = implode(', ', array_keys($map));
            throw new RuntimeException("Sheet '{$name}' not found. Available: {$avail}");
        }

        $xml = $this->zip->getFromName($map[$name]);
        if ($xml === false) {
            throw new RuntimeException("Cannot read sheet file: {$map[$name]}");
        }

        $d = simplexml_load_string($xml);
        if ($d === false) {
            throw new RuntimeException("Failed to parse XML for sheet '{$name}'");
        }

        $sparseRows = [];
        foreach ($d->sheetData->row as $row) {
            $rowIdx = (int) $row->attributes()['r'] - 1; // 0-based
            $cells  = [];
            foreach ($row->c as $c) {
                $colIdx            = $this->colLetterToIndex((string) $c->attributes()['r']);
                $cells[$colIdx]    = $this->cellValue($c);
            }
            if (!empty($cells)) {
                $sparseRows[$rowIdx] = $cells;
            }
        }

        if (empty($sparseRows)) {
            return [];
        }

        // Dense-fill: ensure every row is 0..maxCol with '' for missing cells
        $denseRows = [];
        foreach ($sparseRows as $rIdx => $cells) {
            $maxCol = max(array_keys($cells));
            $dense  = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $dense[$i] = $cells[$i] ?? '';
            }
            $denseRows[] = $dense;
        }

        return $denseRows;
    }

    // ── Cell helpers ─────────────────────────────────────────────────────────

    /** Convert cell-ref like "AB12" → 0-based column index */
    private function colLetterToIndex(string $cellRef): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($cellRef), $m);
        $letters = $m[1] ?? 'A';
        $idx = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }
        return $idx - 1;
    }

    private function cellValue(\SimpleXMLElement $c): string
    {
        $t = (string) ($c->attributes()['t'] ?? '');
        $v = (string) ($c->v ?? '');

        return match ($t) {
            's'         => $this->sharedStrings[(int) $v] ?? '',
            'inlineStr' => (string) ($c->is->t ?? ''),
            'b'         => ($v === '1') ? '1' : '0',
            default     => $v,  // numeric value as raw string
        };
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// COLUMN MAPPINGS  (mirrors ExcelHeaderNormalizer)
// ══════════════════════════════════════════════════════════════════════════════

const FOOD_PRICE_MAP = [
    'veg'     => 'price_veg',    'jain'    => 'price_jain',
    'chicken' => 'price_chicken','mutton'  => 'price_mutton',
    'basa'    => 'price_basa',   'prawns'  => 'price_prawns',
    'prawn'   => 'price_prawns', 'prawans' => 'price_prawns',
    'surmai'  => 'price_surmai', 'pomfret' => 'price_pomfret',
    'crab'    => 'price_crab',   'egg'     => 'price_egg',
    'half'    => 'price_half',   'full'    => 'price_full',
    'plain'   => 'price_plain',  'butter'  => 'price_butter',
    'medium'  => 'price_medium', 'large'   => 'price_large',
    'direct'  => 'price_direct',
];

const FOOD_META_MAP = [
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
];

const BAR_META_MAP = [
    'category'          => 'category',
    'item name'         => 'item_name',
    'description'       => 'description',
    'image url'         => 'image_url',
    'availability'      => 'is_available',
    'bar man special'   => 'barman_special',
    'barman special'    => 'barman_special',
    "bar man's special" => 'barman_special',
];

/** Canonical label for each bar variant column (keyed by lowercase) */
const BAR_VARIANT_CANONICAL = [
    'mocktail'        => 'Mocktail',
    '30ml'            => '30Ml',
    '330ml'           => '330Ml',
    '275ml'           => '275Ml',
    'glass'           => 'Glass',
    'silver'          => 'Silver',
    'repesado'        => 'Repesado',
    'anezo'           => 'Anezo',
    'btl'             => 'Btl',
    'pitcher(1.5 lit)'=> 'Pitcher(1.5 Lit)',
];

/** Sort order for bar variant columns */
const BAR_VARIANT_SORT = [
    'mocktail' => 0, '30ml' => 1,  '330ml' => 2, '275ml' => 3,
    'glass'    => 4, 'silver' => 5,'repesado' => 6,'anezo' => 7,
    'btl'      => 8, 'pitcher(1.5 lit)' => 9,
];

/** Food categories that show regardless of veg/nonveg filter */
const UNIVERSAL_FOOD_CATS = [
    'breads', 'bread',
    'mocktails', 'mocktail',
    'shakes & smoothies', 'shakes and smoothies',
    'iced tea', 'lemonades', 'cold ones',
    'dessert', 'desserts',
    'icy virgin margaritas',
];

/** Exact food category order required by the live menu/admin experience. */
const FOOD_CATEGORY_ORDER = [
    'sandwiches',
    'sancks',
    'pehels kadam',
    'salads',
    'crackers & dahi',
    'soup',
    'koyle ka kamal',
    'indian mains bageeche se',
    'dal wali gali',
    'murg curry specialities',
    'ghosht curry specialities',
    'fish curry specialities',
    'breads',
    'rice',
    'dimsum',
    'bao',
    'chicken wings',
    'rolls',
    'lettuce cups',
    'deep fry',
    'from the grill',
    'skewers',
    'sizzling plate',
    'wok tossed',
    'sizzlers',
    'curries',
    'main course',
    'bowls',
    'noodles',
    'western specialities',
    'pasta',
    'pizza',
    'dessert',
    'mocktails',
    'icy virgin margaritas',
    'shakes& smoothies',
    'all time favourite',
    'iced tea',
    'lemonades',
    'cold ones',
];

// ══════════════════════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════════════════════

function nk(string $raw): string
{
    return strtolower(trim((string) preg_replace('/\s+/', ' ', $raw)));
}

function parsePrice(string $v): ?float
{
    $v = trim($v);
    if ($v === '' || $v === '-' || strtolower($v) === 'n/a' || strtolower($v) === 'na') {
        return null;
    }
    $cleaned = preg_replace('/[^\d.]/', '', $v);
    if ($cleaned === '' || $cleaned === '.') {
        return null;
    }
    $f = (float) $cleaned;
    return $f > 0 ? $f : null;
}

function parseBool(string $v, bool $default): int
{
    $lower = strtolower(trim($v));
    if ($lower === '') {
        return $default ? 1 : 0;
    }
    if (in_array($lower, ['no', 'false', '0', 'n', 'unavailable'], true)) {
        return 0;
    }
    if (in_array($lower, ['yes', 'true', '1', 'y', 'available'], true)) {
        return 1;
    }
    return $default ? 1 : 0;
}

function isUniversalCat(string $cat): bool
{
    return in_array(nk($cat), UNIVERSAL_FOOD_CATS, true);
}

function isDumSumCat(string $cat): bool
{
    $lower = strtolower($cat);
    return str_contains($lower, 'dum sum') || str_contains($lower, 'dimsum') || str_contains($lower, 'dim sum');
}

function foodCategorySortOrder(string $category, int $fallback): int
{
    static $orderMap = null;
    if ($orderMap === null) {
        $orderMap = [];
        foreach (FOOD_CATEGORY_ORDER as $index => $name) {
            $orderMap[nk($name)] = $index + 1;
        }
    }

    $key = nk($category);
    return $orderMap[$key] ?? $fallback;
}

// ══════════════════════════════════════════════════════════════════════════════
// FOOD SHEET PARSER  (AWGNK MENU)
// ══════════════════════════════════════════════════════════════════════════════

function parseFoodSheet(array $rows): array
{
    // ── Find header row ───────────────────────────────────────────────────────

    $headerIdx = -1;
    $rawHeaders = [];

    foreach ($rows as $i => $row) {
        foreach ($row as $cell) {
            if (nk((string) $cell) === 'category') {
                $headerIdx  = $i;
                $rawHeaders = $row;
                break 2;
            }
        }
    }

    if ($headerIdx < 0) {
        echo "  WARNING: No header row found in AWGNK MENU sheet.\n";
        return [];
    }

    echo "  Header row at index {$headerIdx}: " . implode(', ', array_filter($rawHeaders)) . "\n";

    // ── Build column type map ─────────────────────────────────────────────────
    // colIdx => ['type' => 'meta'|'price'|'variant', 'key' => dbField|label]

    $colMap = [];

    foreach ($rawHeaders as $col => $raw) {
        $raw = (string) $raw;
        $k   = nk($raw);
        if ($k === '') {
            continue;
        }

        if (isset(FOOD_META_MAP[$k])) {
            $colMap[$col] = ['type' => 'meta',    'key' => FOOD_META_MAP[$k]];
        } elseif (isset(FOOD_PRICE_MAP[$k])) {
            $colMap[$col] = ['type' => 'price',   'key' => FOOD_PRICE_MAP[$k]];
        } else {
            // Unknown column — potential Dum Sum variant or ignored field
            $colMap[$col] = ['type' => 'variant', 'key' => trim($raw)];
        }
    }

    $unmappedHeaders = array_map(
        fn($c) => $rawHeaders[$c],
        array_keys(array_filter($colMap, fn($d) => $d['type'] === 'variant'))
    );
    if (!empty($unmappedHeaders)) {
        echo "  Unmapped columns (treated as Dum Sum variants if applicable): "
            . implode(', ', $unmappedHeaders) . "\n";
    }

    // ── Parse data rows ───────────────────────────────────────────────────────

    $items       = [];
    $catSortMap  = [];   // category => sort order (first appearance)
    $catCounter  = 0;
    $itemInCat   = [];   // category => item ordinal within category
    $lastCat     = '';
    $blankSkipped = 0;
    $skippedRows  = [];

    for ($i = $headerIdx + 1, $total = count($rows); $i < $total; $i++) {
        $row = $rows[$i];

        // Collect all values for the row using colMap
        $meta    = [];  // dbField => raw string
        $prices  = [];  // dbCol   => ?float
        $varCols = [];  // label   => ?float

        foreach ($colMap as $col => $def) {
            $val = (string) ($row[$col] ?? '');
            if ($def['type'] === 'meta') {
                $meta[$def['key']] = $val;
            } elseif ($def['type'] === 'price') {
                $prices[$def['key']] = parsePrice($val);
            } else {
                $varCols[$def['key']] = parsePrice($val);
            }
        }

        $category = trim($meta['category'] ?? '');
        $itemName = trim($meta['item_name'] ?? '');

        // Inherit category from previous row when merged cells leave it blank
        if ($category === '' && $lastCat !== '') {
            $category = $lastCat;
        }

        // Track category sort order on first appearance
        if ($category !== '' && !isset($catSortMap[$category])) {
            $catSortMap[$category] = foodCategorySortOrder($category, 1000 + $catCounter);
            $catCounter++;
        }

        // Skip rows with no item name
        if ($itemName === '') {
            // Category-only row (section header) — just capture category sort
            if ($category !== '') {
                $lastCat = $category;
            }
            $blankSkipped++;
            continue;
        }

        $lastCat = $category;

        // Item sort order within category
        if (!isset($itemInCat[$category])) {
            $itemInCat[$category] = 0;
        }
        $itemInCat[$category]++;

        // ── Diet flags ────────────────────────────────────────────────────
        $nonVegKeys = [
            'price_chicken','price_mutton','price_basa','price_prawns',
            'price_surmai', 'price_pomfret','price_crab','price_egg',
        ];
        $hasVeg    = ($prices['price_veg']  ?? null) !== null;
        $hasJain   = ($prices['price_jain'] ?? null) !== null;
        $hasNonVeg = false;
        foreach ($nonVegKeys as $nk_) {
            if (($prices[$nk_] ?? null) !== null) {
                $hasNonVeg = true;
                break;
            }
        }

        // if price_veg → is_veg; if any protein → is_nonveg
        $isVeg    = $hasVeg ? 1 : 0;
        $isNonVeg = $hasNonVeg ? 1 : 0;
        $isJain   = $hasJain ? 1 : 0;

        // ── Universal item override ───────────────────────────────────────
        // Universal items (mocktails, shakes, breads, desserts, etc.) carry
        // no dietary classification — visible under ALL filter modes.
        // For items whose only filled price is price_veg (beverages, desserts),
        // promote that value to price_direct so the card renders a single clean
        // price with no diet badge.
        if (isUniversalCat($category)) {
            $isVeg    = 0;
            $isNonVeg = 0;
            $isJain   = 0;

            // Only migrate when price_veg is the sole non-null, non-jain price
            $hasOtherPrice = false;
            foreach ($prices as $pkey => $pval) {
                if ($pkey === 'price_veg' || $pkey === 'price_jain') {
                    continue;
                }
                if ($pval !== null) {
                    $hasOtherPrice = true;
                    break;
                }
            }
            if (!$hasOtherPrice
                && ($prices['price_direct'] ?? null) === null
                && ($prices['price_veg']    ?? null) !== null
            ) {
                $prices['price_direct'] = $prices['price_veg'];
                $prices['price_veg']    = null;
            }
        }

        // ── Dum Sum / custom variants (future-ready, optional) ───────────────
        // isDumSumCat() matches "Dim Sum", "Dum Sum", "DimSum" (all variants).
        // Any column header NOT in FOOD_META_MAP or FOOD_PRICE_MAP is captured as
        // a variant label (e.g. "4 pcs", "6 pcs", "8 pcs", "9 pcs", "12 pcs").
        // If the current workbook has NO such columns, $varCols is empty and this
        // block is a no-op — import proceeds normally without warning.
        $isDumSum      = isDumSumCat($category);
        $pricingMode   = 'standard';
        $itemVariants  = [];

        if ($isDumSum && !empty($varCols)) {
            $varSortIdx = 0;
            foreach ($varCols as $label => $price) {
                if ($price !== null) {
                    $pricingMode = 'custom_variants';
                    $itemVariants[] = [
                        'variant_label'      => $label,   // raw label e.g. "4 pcs"
                        'price'              => $price,
                        'variant_sort_order' => $varSortIdx,
                    ];
                }
                $varSortIdx++;
            }
        }

        // ── Build item row ────────────────────────────────────────────────
        $item = [
            'category'            => $category,
            'item_name'           => $itemName,
            'description'         => ($meta['description'] ?? '') !== '' ? $meta['description'] : null,
            'image_url'           => ($meta['image_url'] ?? '') !== '' ? $meta['image_url'] : null,
            'is_available'        => parseBool($meta['is_available'] ?? '', true),
            'is_veg'              => $isVeg,
            'is_nonveg'           => $isNonVeg,
            'is_jain'             => $isJain,
            'is_universal'        => isUniversalCat($category) ? 1 : 0,
            'is_chef_special'     => parseBool($meta['is_chef_special'] ?? '', false),
            'spice_level'         => ($meta['spice_level'] ?? '') !== '' ? $meta['spice_level'] : null,
            'serving_unit'        => ($meta['serving_unit'] ?? '') !== '' ? $meta['serving_unit'] : null,
            'pricing_mode'        => $pricingMode,
            'price_veg'           => $prices['price_veg']     ?? null,
            'price_jain'          => $prices['price_jain']    ?? null,
            'price_chicken'       => $prices['price_chicken'] ?? null,
            'price_mutton'        => $prices['price_mutton']  ?? null,
            'price_basa'          => $prices['price_basa']    ?? null,
            'price_prawns'        => $prices['price_prawns']  ?? null,
            'price_surmai'        => $prices['price_surmai']  ?? null,
            'price_pomfret'       => $prices['price_pomfret'] ?? null,
            'price_crab'          => $prices['price_crab']    ?? null,
            'price_egg'           => $prices['price_egg']     ?? null,
            'price_half'          => $prices['price_half']    ?? null,
            'price_full'          => $prices['price_full']    ?? null,
            'price_plain'         => $prices['price_plain']   ?? null,
            'price_butter'        => $prices['price_butter']  ?? null,
            'price_medium'        => $prices['price_medium']  ?? null,
            'price_large'         => $prices['price_large']   ?? null,
            'price_direct'        => $prices['price_direct']  ?? null,
            'category_sort_order' => $catSortMap[$category] ?? 0,
            'item_sort_order'     => $itemInCat[$category],
            'source_row'          => $i + 1,  // 1-based Excel row number
            '_variants'           => $itemVariants,
        ];

        $items[] = $item;
    }

    echo "  Parsed: " . count($items) . " food items | skipped (blank/header): {$blankSkipped}\n";
    if (!empty($skippedRows)) {
        echo "  Skipped rows: " . implode(', ', $skippedRows) . "\n";
    }

    return $items;
}

// ══════════════════════════════════════════════════════════════════════════════
// BAR SHEET PARSER  (BAR MENU NK)
// ══════════════════════════════════════════════════════════════════════════════

function parseBarSheet(array $rows): array
{
    // ── Find header row ───────────────────────────────────────────────────────

    $headerIdx = -1;
    $rawHeaders = [];

    foreach ($rows as $i => $row) {
        foreach ($row as $cell) {
            if (nk((string) $cell) === 'category') {
                $headerIdx  = $i;
                $rawHeaders = $row;
                break 2;
            }
        }
    }

    if ($headerIdx < 0) {
        echo "  WARNING: No header row found in BAR MENU NK sheet.\n";
        return [];
    }

    echo "  Header row at index {$headerIdx}: " . implode(', ', array_filter($rawHeaders)) . "\n";

    // ── Build column map ──────────────────────────────────────────────────────
    // Variant columns are anything not in BAR_META_MAP

    $metaCols    = [];  // colIdx => dbField
    $variantCols = [];  // colIdx => ['label' => canonical, 'sort' => int]

    foreach ($rawHeaders as $col => $raw) {
        $raw = (string) $raw;
        $k   = nk($raw);
        if ($k === '') {
            continue;
        }

        if (isset(BAR_META_MAP[$k])) {
            $metaCols[$col] = BAR_META_MAP[$k];
        } else {
            $canonical  = BAR_VARIANT_CANONICAL[$k] ?? trim($raw);
            $sortOrder  = BAR_VARIANT_SORT[$k]      ?? 999;
            $variantCols[$col] = ['label' => $canonical, 'sort' => $sortOrder];
        }
    }

    // Sort variant columns by canonical sort order
    uasort($variantCols, fn($a, $b) => $a['sort'] <=> $b['sort']);

    echo "  Variant columns: " . implode(', ', array_column($variantCols, 'label')) . "\n";

    // ── Parse data rows ───────────────────────────────────────────────────────

    $items        = [];
    $catSortMap   = [];
    $catCounter   = 0;
    $itemInCat    = [];
    $lastCat      = '';
    $blankSkipped = 0;

    for ($i = $headerIdx + 1, $total = count($rows); $i < $total; $i++) {
        $row = $rows[$i];

        // Extract meta
        $meta = [];
        foreach ($metaCols as $col => $field) {
            $meta[$field] = (string) ($row[$col] ?? '');
        }

        $category = trim($meta['category'] ?? '');
        $itemName = trim($meta['item_name'] ?? '');

        // Inherit category from previous row
        if ($category === '' && $lastCat !== '') {
            $category = $lastCat;
        }

        if ($category !== '' && !isset($catSortMap[$category])) {
            $catSortMap[$category] = $catCounter++;
        }

        if ($itemName === '') {
            if ($category !== '') {
                $lastCat = $category;
            }
            $blankSkipped++;
            continue;
        }

        $lastCat = $category;

        if (!isset($itemInCat[$category])) {
            $itemInCat[$category] = 0;
        }
        $itemInCat[$category]++;

        // ── Variants ──────────────────────────────────────────────────────
        $variants = [];
        foreach ($variantCols as $col => $def) {
            $price = parsePrice((string) ($row[$col] ?? ''));
            if ($price !== null) {
                $variants[] = [
                    'variant_label'      => $def['label'],
                    'price'              => $price,
                    'variant_sort_order' => $def['sort'],
                ];
            }
        }

        $items[] = [
            'category'            => $category,
            'item_name'           => $itemName,
            'description'         => ($meta['description'] ?? '') !== '' ? $meta['description'] : null,
            'image_url'           => ($meta['image_url'] ?? '') !== '' ? $meta['image_url'] : null,
            'is_available'        => parseBool($meta['is_available'] ?? '', true),
            'barman_special'      => parseBool($meta['barman_special'] ?? '', false),
            'category_sort_order' => $catSortMap[$category] ?? 0,
            'item_sort_order'     => $itemInCat[$category],
            'source_row'          => $i + 1,
            '_variants'           => $variants,
        ];
    }

    echo "  Parsed: " . count($items) . " bar items | skipped (blank/header): {$blankSkipped}\n";

    return $items;
}

// ══════════════════════════════════════════════════════════════════════════════
// DB PERSIST
// ══════════════════════════════════════════════════════════════════════════════

function truncateTables(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('TRUNCATE TABLE food_menu_item_variants');
    $pdo->exec('TRUNCATE TABLE food_menu_items');
    $pdo->exec('TRUNCATE TABLE bar_menu_item_variants');
    $pdo->exec('TRUNCATE TABLE bar_menu_items');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Tables truncated.\n";
}

function persistFoodItems(PDO $pdo, array $items): array
{
    $cols = [
        'category','item_name','description','image_url',
        'is_available','is_veg','is_nonveg','is_jain','is_universal',
        'is_chef_special','spice_level','serving_unit','pricing_mode',
        'price_veg','price_jain','price_chicken','price_mutton',
        'price_basa','price_prawns','price_surmai','price_pomfret',
        'price_crab','price_egg','price_half','price_full',
        'price_plain','price_butter','price_medium','price_large','price_direct',
        'category_sort_order','item_sort_order','source_row',
    ];

    $insertItemSql = 'INSERT INTO food_menu_items (' . implode(',', $cols) . ')'
        . ' VALUES (' . implode(',', array_map(fn($c) => ":{$c}", $cols)) . ')';
    $insertVariantSql = 'INSERT INTO food_menu_item_variants'
        . ' (food_item_id, variant_label, price, variant_sort_order)'
        . ' VALUES (:food_item_id, :variant_label, :price, :variant_sort_order)';

    $itemStmt    = $pdo->prepare($insertItemSql);
    $variantStmt = $pdo->prepare($insertVariantSql);

    $itemsInserted    = 0;
    $variantsInserted = 0;

    $pdo->beginTransaction();
    try {
        foreach ($items as $item) {
            $variants = $item['_variants'];
            $row = [];
            foreach ($cols as $c) {
                $row[":{$c}"] = $item[$c] ?? null;
            }
            $itemStmt->execute($row);
            $newId = (int) $pdo->lastInsertId();
            $itemsInserted++;

            foreach ($variants as $v) {
                $variantStmt->execute([
                    ':food_item_id'       => $newId,
                    ':variant_label'      => $v['variant_label'],
                    ':price'              => $v['price'],
                    ':variant_sort_order' => $v['variant_sort_order'],
                ]);
                $variantsInserted++;
            }
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [$itemsInserted, $variantsInserted];
}

function persistBarItems(PDO $pdo, array $items): array
{
    $cols = [
        'category','item_name','description','image_url',
        'is_available','barman_special',
        'category_sort_order','item_sort_order','source_row',
    ];

    $insertItemSql = 'INSERT INTO bar_menu_items (' . implode(',', $cols) . ')'
        . ' VALUES (' . implode(',', array_map(fn($c) => ":{$c}", $cols)) . ')';
    $insertVariantSql = 'INSERT INTO bar_menu_item_variants'
        . ' (bar_item_id, variant_label, price, variant_sort_order)'
        . ' VALUES (:bar_item_id, :variant_label, :price, :variant_sort_order)';

    $itemStmt    = $pdo->prepare($insertItemSql);
    $variantStmt = $pdo->prepare($insertVariantSql);

    $itemsInserted    = 0;
    $variantsInserted = 0;

    $pdo->beginTransaction();
    try {
        foreach ($items as $item) {
            $variants = $item['_variants'];
            $row = [];
            foreach ($cols as $c) {
                $row[":{$c}"] = $item[$c] ?? null;
            }
            $itemStmt->execute($row);
            $newId = (int) $pdo->lastInsertId();
            $itemsInserted++;

            foreach ($variants as $v) {
                $variantStmt->execute([
                    ':bar_item_id'        => $newId,
                    ':variant_label'      => $v['variant_label'],
                    ':price'              => $v['price'],
                    ':variant_sort_order' => $v['variant_sort_order'],
                ]);
                $variantsInserted++;
            }
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [$itemsInserted, $variantsInserted];
}

// ══════════════════════════════════════════════════════════════════════════════
// MAIN
// ══════════════════════════════════════════════════════════════════════════════

echo "Opening: " . basename($xlsxFile) . "\n";
$reader = new XlsxReader();
$reader->open($xlsxFile);

$sheets = $reader->getSheetNames();
echo "Sheets found: " . implode(', ', $sheets) . "\n\n";

// ── Food sheet ────────────────────────────────────────────────────────────────
echo "=== AWGNK MENU (food) ===\n";
$foodRows  = $reader->readSheet('AWGNK MENU');
echo "  Raw rows from sheet: " . count($foodRows) . "\n";
$foodItems = parseFoodSheet($foodRows);

// ── Bar sheet ─────────────────────────────────────────────────────────────────
echo "\n=== BAR MENU NK (bar) ===\n";
$barRows  = $reader->readSheet('BAR MENU NK');
echo "  Raw rows from sheet: " . count($barRows) . "\n";
$barItems = parseBarSheet($barRows);

$reader->close();

// ── Truncate ──────────────────────────────────────────────────────────────────
echo "\n=== DB: Truncating tables ===\n";
truncateTables($pdo);

// ── Insert food ───────────────────────────────────────────────────────────────
echo "\n=== DB: Inserting food items ===\n";
[$foodInserted, $foodVariants] = persistFoodItems($pdo, $foodItems);
echo "  food_menu_items:         {$foodInserted}\n";
echo "  food_menu_item_variants: {$foodVariants}\n";

// ── Insert bar ────────────────────────────────────────────────────────────────
echo "\n=== DB: Inserting bar items ===\n";
[$barInserted, $barVariants] = persistBarItems($pdo, $barItems);
echo "  bar_menu_items:          {$barInserted}\n";
echo "  bar_menu_item_variants:  {$barVariants}\n";

// ══════════════════════════════════════════════════════════════════════════════
// VALIDATION
// ══════════════════════════════════════════════════════════════════════════════

echo "\n=== VALIDATION ===\n";

$dbFoodCount = (int) $pdo->query('SELECT COUNT(*) FROM food_menu_items')->fetchColumn();
$dbBarCount  = (int) $pdo->query('SELECT COUNT(*) FROM bar_menu_items')->fetchColumn();
$dbFoodVar   = (int) $pdo->query('SELECT COUNT(*) FROM food_menu_item_variants')->fetchColumn();
$dbBarVar    = (int) $pdo->query('SELECT COUNT(*) FROM bar_menu_item_variants')->fetchColumn();

echo "DB row counts:\n";
echo "  food_menu_items:         {$dbFoodCount}\n";
echo "  food_menu_item_variants: {$dbFoodVar}\n";
echo "  bar_menu_items:          {$dbBarCount}\n";
echo "  bar_menu_item_variants:  {$dbBarVar}\n";

$nullCatFood = (int) $pdo->query("SELECT COUNT(*) FROM food_menu_items WHERE category = '' OR category IS NULL")->fetchColumn();
$nullCatBar  = (int) $pdo->query("SELECT COUNT(*) FROM bar_menu_items  WHERE category = '' OR category IS NULL")->fetchColumn();
echo "\n  Null/blank category (food): {$nullCatFood}\n";
echo "  Null/blank category (bar):  {$nullCatBar}\n";

echo "\n--- Sample food items (first 5) ---\n";
$stmt = $pdo->query(
    'SELECT id, category, item_name, price_veg, price_chicken, is_veg, is_nonveg, pricing_mode
       FROM food_menu_items
      ORDER BY category_sort_order ASC, item_sort_order ASC
      LIMIT 5'
);
foreach ($stmt->fetchAll() as $row) {
    printf(
        "  [%d] %-25s / %-35s  veg:%-5s chk:%-5s is_veg:%d is_nv:%d mode:%s\n",
        $row['id'],
        substr($row['category'], 0, 25),
        substr($row['item_name'], 0, 35),
        $row['price_veg']     ?? '-',
        $row['price_chicken'] ?? '-',
        $row['is_veg'],
        $row['is_nonveg'],
        $row['pricing_mode']
    );
}

echo "\n--- Sample bar items (first 5 with variants) ---\n";
$stmt = $pdo->query(
    'SELECT b.id, b.category, b.item_name, b.barman_special,
            GROUP_CONCAT(CONCAT(v.variant_label,":",ROUND(v.price)) ORDER BY v.variant_sort_order SEPARATOR ", ") AS variants
       FROM bar_menu_items b
  LEFT JOIN bar_menu_item_variants v ON v.bar_item_id = b.id
      GROUP BY b.id
      ORDER BY b.category_sort_order ASC, b.item_sort_order ASC
      LIMIT 5'
);
foreach ($stmt->fetchAll() as $row) {
    printf(
        "  [%d] %-25s / %-30s  barman:%d  → %s\n",
        $row['id'],
        substr($row['category'], 0, 25),
        substr($row['item_name'], 0, 30),
        $row['barman_special'],
        $row['variants'] ?? '(no variants)'
    );
}

echo "\n--- Food category breakdown ---\n";
$cats = $pdo->query(
    'SELECT category, COUNT(*) AS cnt,
            SUM(is_veg) AS veg, SUM(is_nonveg) AS nonveg, SUM(is_universal) AS univ
       FROM food_menu_items
      GROUP BY category
      ORDER BY category_sort_order ASC'
)->fetchAll();
foreach ($cats as $c) {
    printf("  %-35s  items:%-4d  veg:%-3d  nonveg:%-3d  univ:%d\n",
        substr($c['category'], 0, 35), $c['cnt'], $c['veg'], $c['nonveg'], $c['univ']);
}

echo "\n--- Bar category breakdown ---\n";
$barCats = $pdo->query(
    'SELECT category, COUNT(*) AS cnt
       FROM bar_menu_items
      GROUP BY category
      ORDER BY category_sort_order ASC'
)->fetchAll();
foreach ($barCats as $c) {
    printf("  %-35s  items:%d\n", substr($c['category'], 0, 35), $c['cnt']);
}

// ── Summary ───────────────────────────────────────────────────────────────────
echo "\n";
echo "════════════════════════════════════════════\n";
echo " IMPORT COMPLETE\n";
echo "════════════════════════════════════════════\n";
printf(" Food items inserted  : %d\n", $foodInserted);
printf(" Food variants        : %d\n", $foodVariants);
printf(" Bar items inserted   : %d\n", $barInserted);
printf(" Bar variants         : %d\n", $barVariants);
printf(" Total records        : %d\n", $foodInserted + $barInserted + $foodVariants + $barVariants);
echo "════════════════════════════════════════════\n";
