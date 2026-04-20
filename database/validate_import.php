<?php
// Temporary validation-only script — safe to delete after use
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');

$pdo = new PDO(
    'mysql:host=127.0.0.1;port=3308;dbname=namastekalyan_local;charset=utf8mb4',
    'namastes', 'LocalPass@123',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "=== COUNTS ===\n";
echo 'food_menu_items:         ' . $pdo->query('SELECT COUNT(*) FROM food_menu_items')->fetchColumn() . "\n";
echo 'food_menu_item_variants: ' . $pdo->query('SELECT COUNT(*) FROM food_menu_item_variants')->fetchColumn() . "\n";
echo 'bar_menu_items:          ' . $pdo->query('SELECT COUNT(*) FROM bar_menu_items')->fetchColumn() . "\n";
echo 'bar_menu_item_variants:  ' . $pdo->query('SELECT COUNT(*) FROM bar_menu_item_variants')->fetchColumn() . "\n";

echo "\n=== SAMPLE FOOD ITEMS (first 8) ===\n";
$rows = $pdo->query(
    'SELECT id,category,item_name,price_veg,price_chicken,is_veg,is_nonveg,pricing_mode
       FROM food_menu_items ORDER BY category_sort_order,item_sort_order LIMIT 8'
)->fetchAll();
foreach ($rows as $r) {
    printf("[%d] %-22s | %-30s  veg:%-5s chk:%-5s is_v:%d nv:%d %s\n",
        $r['id'], substr($r['category'], 0, 22), substr($r['item_name'], 0, 30),
        $r['price_veg'] ?? '-', $r['price_chicken'] ?? '-',
        $r['is_veg'], $r['is_nonveg'], $r['pricing_mode']);
}

echo "\n=== SAMPLE BAR ITEMS + VARIANTS (first 8) ===\n";
$rows = $pdo->query(
    'SELECT b.id, b.category, b.item_name,
            GROUP_CONCAT(CONCAT(v.variant_label,":",v.price) ORDER BY v.variant_sort_order SEPARATOR " | ") AS vv
       FROM bar_menu_items b
  LEFT JOIN bar_menu_item_variants v ON v.bar_item_id = b.id
      GROUP BY b.id
      ORDER BY b.category_sort_order, b.item_sort_order LIMIT 8'
)->fetchAll();
foreach ($rows as $r) {
    printf("[%d] %-22s | %-25s  %s\n",
        $r['id'], substr($r['category'], 0, 22), substr($r['item_name'], 0, 25),
        $r['vv'] ?? '(no variants)');
}

echo "\n=== FOOD CATEGORIES ===\n";
$rows = $pdo->query(
    'SELECT category, COUNT(*) AS n, SUM(is_veg) AS v, SUM(is_nonveg) AS nv, MIN(category_sort_order) AS cs
       FROM food_menu_items GROUP BY category ORDER BY MIN(category_sort_order)'
)->fetchAll();
foreach ($rows as $c) {
    printf("  %-38s %3d items  veg:%d  nonveg:%d\n",
        substr($c['category'], 0, 38), $c['n'], $c['v'], $c['nv']);
}

echo "\n=== BAR CATEGORIES ===\n";
$rows = $pdo->query(
    'SELECT category, COUNT(*) AS n FROM bar_menu_items GROUP BY category ORDER BY MIN(category_sort_order)'
)->fetchAll();
foreach ($rows as $c) {
    printf("  %-38s %3d items\n", substr($c['category'], 0, 38), $c['n']);
}
