<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3308;dbname=namastekalyan_local;charset=utf8mb4',
    'namastes', 'LocalPass@123',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// Set is_veg=1 for clearly veg items missing the flag
$pdo->exec('UPDATE food_menu_items SET is_veg=1, is_nonveg=0 WHERE id IN (235,376,377,378,379,380)');
echo 'Veg flag set for IDs 235,376,377,378,379,380' . PHP_EOL;

// Set is_nonveg=1 for clearly non-veg items missing the flag
$pdo->exec('UPDATE food_menu_items SET is_veg=0, is_nonveg=1 WHERE id IN (106,107,194,195,196,197,381,382,383)');
echo 'Nonveg flag set for IDs 106,107,194,195,196,197,381,382,383' . PHP_EOL;

// Hide items with no price (market price / placeholder) to prevent empty cards
$pdo->exec('UPDATE food_menu_items SET is_available=0 WHERE id IN (130,211,212,288,458)');
echo 'Hidden (is_available=0) for IDs 130,211,212,288,458' . PHP_EOL;

echo 'Done.' . PHP_EOL;
