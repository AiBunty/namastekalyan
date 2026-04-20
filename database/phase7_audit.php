<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');
$pdo = new PDO('mysql:host=127.0.0.1;port=3308;dbname=namastekalyan_local;charset=utf8mb4',
    'namastes', 'LocalPass@123',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// ── EVENT TRANSACTIONS ───────────────────────────────────────────────────────
echo "=== EVENT_TRANSACTIONS ===" . PHP_EOL;
$rows = $pdo->query('SELECT transaction_id, event_id, customer_name, customer_phone, status, amount, created_at FROM event_transactions')->fetchAll();
echo "Count: " . count($rows) . PHP_EOL;
foreach ($rows as $r) {
    echo "  [{$r['status']}] {$r['transaction_id']} | event={$r['event_id']} | {$r['customer_name']} | {$r['amount']} | {$r['created_at']}" . PHP_EOL;
}

// ── EVENTS breakdown ─────────────────────────────────────────────────────────
echo PHP_EOL . "=== EVENTS (total, active, type) ===" . PHP_EOL;
$evts = $pdo->query('SELECT id, title, is_active, event_type, start_date FROM events ORDER BY is_active DESC, id ASC')->fetchAll();
foreach ($evts as $r) {
    echo "  is_active={$r['is_active']} type={$r['event_type']} id={$r['id']} | {$r['title']} | {$r['start_date']}" . PHP_EOL;
}

// ── IS_JAIN audit on newly-fixed veg items ───────────────────────────────────
echo PHP_EOL . "=== IS_JAIN for fixed veg items ===" . PHP_EOL;
$ids = implode(',', [235, 376, 377, 378, 379, 380]);
$veg = $pdo->query("SELECT id, item_name, is_veg, is_nonveg, is_jain FROM food_menu_items WHERE id IN ($ids)")->fetchAll();
foreach ($veg as $r) {
    echo "  id={$r['id']} veg={$r['is_veg']} nonveg={$r['is_nonveg']} jain={$r['is_jain']} | {$r['item_name']}" . PHP_EOL;
}

// ── Food menu item variants ─────────────────────────────────────────────────
echo PHP_EOL . "=== food_menu_item_variants count ===" . PHP_EOL;
echo "  " . $pdo->query('SELECT COUNT(*) FROM food_menu_item_variants')->fetchColumn() . PHP_EOL;

// ── Check Dimsum items have no variants (expected) ──────────────────────────
echo PHP_EOL . "=== Dimsum items pricing check ===" . PHP_EOL;
$dims = $pdo->query("SELECT id, item_name, pricing_mode, price_veg, price_chicken FROM food_menu_items WHERE category='Dimsum'")->fetchAll();
foreach ($dims as $r) {
    echo "  id={$r['id']} {$r['item_name']} | mode={$r['pricing_mode']} | veg={$r['price_veg']} chicken={$r['price_chicken']}" . PHP_EOL;
}

// ── Bar items without variants ──────────────────────────────────────────────
echo PHP_EOL . "=== Bar items with 0 variants ===" . PHP_EOL;
$novar = $pdo->query('SELECT b.id, b.item_name, b.category FROM bar_menu_items b LEFT JOIN bar_menu_item_variants v ON b.id=v.bar_item_id WHERE v.id IS NULL')->fetchAll();
echo "  Count: " . count($novar) . PHP_EOL;
