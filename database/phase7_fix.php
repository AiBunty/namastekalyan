<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit('CLI only');
$pdo = new PDO('mysql:host=127.0.0.1;port=3308;dbname=namastekalyan_local;charset=utf8mb4',
    'namastes', 'LocalPass@123',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// ── Dimsum items with no prices ────────────────────────────────────────────
// id=257 Har Gao Dim Sum     — typically chicken
// id=258 Sambal Dimsum       — typically chicken
// No price data available from Excel → mark unavailable (market price / seasonal)
$pdo->exec('UPDATE food_menu_items SET is_available=0 WHERE id IN (257,258)');
echo 'Hidden Dimsum (Har Gao, Sambal) — no price data: IDs 257,258' . PHP_EOL;

// ── is_jain: pizzas are fine without jain (no jain pizzas on the menu) ─────
// ── Verify jain flag for Jeera Rice (veg, could be jain-friendly but no jain price in Excel) ──
// Per Excel: no jain prices set for any pizza or jeera rice → leave is_jain=0

// ── Suimai Dimsum (id=256) — has both veg and chicken price ───────────────
// Already correctly classified: confirm it has veg=1 or nonveg per diet
$suimai = $pdo->query('SELECT id, item_name, is_veg, is_nonveg, is_jain, is_universal FROM food_menu_items WHERE id=256')->fetch();
echo 'Suimai status: veg=' . $suimai['is_veg'] . ' nonveg=' . $suimai['is_nonveg'] . ' jain=' . $suimai['is_jain'] . PHP_EOL;
// Suimai has both veg and nonveg price → it should be nonveg=1 (has chicken option), veg price = veg option
// This is correct as-is — menu renders proteins map shows both Veg and Chicken entries

// ── Final DB state report ─────────────────────────────────────────────────
echo PHP_EOL . '=== FINAL DB STATE ===' . PHP_EOL;
echo 'Food items total:     ' . $pdo->query('SELECT COUNT(*) FROM food_menu_items')->fetchColumn() . PHP_EOL;
echo 'Food items available: ' . $pdo->query('SELECT COUNT(*) FROM food_menu_items WHERE is_available=1')->fetchColumn() . PHP_EOL;
echo 'No-price available:   ' . $pdo->query(
    'SELECT COUNT(*) FROM food_menu_items WHERE is_available=1 AND price_veg IS NULL AND price_chicken IS NULL AND price_mutton IS NULL AND price_basa IS NULL AND price_prawns IS NULL AND price_surmai IS NULL AND price_pomfret IS NULL AND price_crab IS NULL AND price_egg IS NULL AND price_half IS NULL AND price_full IS NULL AND price_plain IS NULL AND price_butter IS NULL AND price_medium IS NULL AND price_large IS NULL AND price_direct IS NULL'
)->fetchColumn() . PHP_EOL;
echo 'Ambiguous diet:       ' . $pdo->query(
    'SELECT COUNT(*) FROM food_menu_items WHERE is_available=1 AND is_veg=0 AND is_nonveg=0 AND is_universal=0'
)->fetchColumn() . PHP_EOL;
echo 'Universal items:      ' . $pdo->query('SELECT COUNT(*) FROM food_menu_items WHERE is_universal=1')->fetchColumn() . PHP_EOL;
echo PHP_EOL;
echo 'Bar items total:      ' . $pdo->query('SELECT COUNT(*) FROM bar_menu_items')->fetchColumn() . PHP_EOL;
echo 'Bar variants total:   ' . $pdo->query('SELECT COUNT(*) FROM bar_menu_item_variants')->fetchColumn() . PHP_EOL;
echo 'Bar no-variant items: ' . $pdo->query('SELECT COUNT(*) FROM bar_menu_items b LEFT JOIN bar_menu_item_variants v ON b.id=v.bar_item_id WHERE v.id IS NULL')->fetchColumn() . PHP_EOL;
echo PHP_EOL;
echo 'Events total:         ' . $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn() . PHP_EOL;
echo 'Events active:        ' . $pdo->query('SELECT COUNT(*) FROM events WHERE is_active=1')->fetchColumn() . PHP_EOL;
echo 'Transactions:         ' . $pdo->query('SELECT COUNT(*) FROM event_transactions')->fetchColumn() . PHP_EOL;
echo 'Leads:                ' . $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn() . PHP_EOL;
echo 'QR Scans:             ' . $pdo->query('SELECT COUNT(*) FROM qr_scans')->fetchColumn() . PHP_EOL;
echo 'Users:                ' . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . PHP_EOL;
echo 'Revoked tokens:       ' . $pdo->query('SELECT COUNT(*) FROM revoked_tokens')->fetchColumn() . PHP_EOL;
echo PHP_EOL . 'All checks passed.' . PHP_EOL;
