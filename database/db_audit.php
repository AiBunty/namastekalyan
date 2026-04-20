<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');

$pdo = new PDO('mysql:host=127.0.0.1;port=3308;dbname=namastekalyan_local;charset=utf8mb4',
    'namastes','LocalPass@123',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

// ── 1. List all tables ───────────────────────────────────────────────────────
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "=== TABLES IN DB ===\n";
foreach ($tables as $t) echo "  $t\n";

// ── 2. Row counts ────────────────────────────────────────────────────────────
$checkTables = [
    'food_menu_items','food_menu_item_variants',
    'bar_menu_items','bar_menu_item_variants',
    'events','event_transactions',
    'leads','qr_scans',
    'admin_cash_ledger','superadmin_cash_ledger',
    'users','auth_audit','revoked_tokens',
    'api_settings','migrations'
];

echo "\n=== ROW COUNTS ===\n";
foreach ($checkTables as $t) {
    if (in_array($t, $tables)) {
        $n = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        echo "  $t: $n rows\n";
    } else {
        echo "  $t: [MISSING]\n";
    }
}

// ── 3. Column list for each existing table ───────────────────────────────────
echo "\n=== COLUMNS PER TABLE ===\n";
foreach ($tables as $t) {
    $cols = $pdo->query("DESCRIBE `$t`")->fetchAll();
    $colNames = array_column($cols, 'Field');
    echo "\n[$t]\n  " . implode(', ', $colNames) . "\n";
}
