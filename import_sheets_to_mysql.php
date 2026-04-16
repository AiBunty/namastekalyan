<?php

/**
 * One-time Google Sheets (via Apps Script endpoint) -> MySQL importer.
 *
 * Usage:
 *   php backend/import_sheets_to_mysql.php
 *
 * Notes:
 * - This is designed for initial migration.
 * - It imports the key modules first: menu sheets, events, leads, users.
 * - If a tab is unavailable, it logs and skips instead of failing the whole run.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('403 Forbidden');
}

require_once __DIR__ . '/bootstrap.php';

use NK\Config\Database;
use NK\Services\AuthService;

$db = Database::connection();

$appsScriptUrl = trim((string) ($_ENV['NK_APPS_SCRIPT_URL'] ?? ''));
if ($appsScriptUrl === '') {
    $dataConfigPath = dirname(__DIR__) . '/data-config.js';
    if (is_file($dataConfigPath)) {
        $content = (string) file_get_contents($dataConfigPath);
        if (preg_match("/appsScriptUrl\s*:\s*'([^']+)'/", $content, $m)) {
            $appsScriptUrl = trim((string) ($m[1] ?? ''));
        }
    }
}

if ($appsScriptUrl === '') {
    fwrite(STDERR, "Could not resolve appsScriptUrl. Set NK_APPS_SCRIPT_URL in .env.\n");
    exit(1);
}

$appsScriptUrl = preg_replace('/\?.*$/', '', $appsScriptUrl);
$snapshotDir = __DIR__ . '/import-data';

if (!is_dir($snapshotDir)) {
    @mkdir($snapshotDir, 0755, true);
}

echo "Using source endpoint: {$appsScriptUrl}\n";
echo "Snapshot directory: {$snapshotDir}\n";

function fetchJson(string $url): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== '' || $status < 200 || $status >= 300 || $raw === false) {
        return null;
    }

    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : null;
}

function tabSnapshotFilename(string $tab): string
{
    $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', trim($tab)));
    $slug = trim($slug, '_');
    if ($slug === '') {
        $slug = 'tab';
    }
    return $slug . '.json';
}

function normalizeRecordsFromJson(array $json): array
{
    if (isset($json['items']) && is_array($json['items'])) {
        return $json['items'];
    }

    if (isset($json['records']) && is_array($json['records'])) {
        return $json['records'];
    }

    if (isset($json['rows']) && is_array($json['rows'])) {
        return $json['rows'];
    }

    if (isset($json['data']) && is_array($json['data'])) {
        if (isset($json['data']['items']) && is_array($json['data']['items'])) {
            return $json['data']['items'];
        }

        return $json['data'];
    }

    if (array_keys($json) === range(0, count($json) - 1)) {
        return $json;
    }

    return [];
}

function decodeJsonLoose(string $raw): ?array
{
    // Strip UTF-8 BOM if present.
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) {
        $raw = substr($raw, 3);
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    // Convert UTF-16 snapshots produced by some PowerShell environments.
    if (strncmp($raw, "\xFF\xFE", 2) === 0) {
        $utf8 = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($raw, 2));
        if (is_string($utf8)) {
            $decoded = json_decode($utf8, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    } elseif (strncmp($raw, "\xFE\xFF", 2) === 0) {
        $utf8 = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($raw, 2));
        if (is_string($utf8)) {
            $decoded = json_decode($utf8, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    return null;
}

function fetchTabRecords(string $baseUrl, string $tab, string $snapshotDir): array
{
    $snapshotFile = $snapshotDir . '/' . tabSnapshotFilename($tab);
    if (is_file($snapshotFile)) {
        $content = (string) file_get_contents($snapshotFile);
        $decoded = decodeJsonLoose($content);
        if (is_array($decoded)) {
            return normalizeRecordsFromJson($decoded);
        }
    }

    $url = $baseUrl . '?tab=' . rawurlencode($tab) . '&shape=records';
    $json = fetchJson($url);
    if (!is_array($json)) {
        return [];
    }

    // Save snapshot for repeatability.
    @file_put_contents($snapshotFile, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

    return normalizeRecordsFromJson($json);
}

function findValue(array $row, array $keys, mixed $default = ''): mixed
{
    $lower = [];
    foreach ($row as $k => $v) {
        $lower[strtolower(trim((string) $k))] = $v;
    }

    foreach ($keys as $key) {
        $k = strtolower(trim((string) $key));
        if (array_key_exists($k, $lower)) {
            return $lower[$k];
        }
    }

    return $default;
}

function asBool(mixed $value): int
{
    if (is_bool($value)) return $value ? 1 : 0;
    $v = strtolower(trim((string) $value));
    return in_array($v, ['1', 'true', 'yes', 'active', 'enabled', 'on'], true) ? 1 : 0;
}

function parseDate(?string $value): ?string
{
    $v = trim((string) $value);
    if ($v === '') return null;
    $ts = strtotime($v);
    return $ts === false ? null : date('Y-m-d', $ts);
}

function parseDateTime(?string $value): ?string
{
    $v = trim((string) $value);
    if ($v === '') return null;
    $ts = strtotime($v);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

function parseAvailability(mixed $value): int
{
    $raw = strtolower(trim((string) $value));
    if ($raw === '') {
        // In sheet data, blank availability means item is available.
        return 1;
    }

    if (in_array($raw, ['0', 'false', 'no', 'unavailable', 'disabled', 'off'], true)) {
        return 0;
    }

    return 1;
}

function deriveFoodCategory(array $row, string $current): string
{
    if (in_array($current, ['Veg', 'NonVeg', 'Jain'], true)) {
        return $current;
    }

    $valueOf = static function (array $source, string $key): string {
        foreach ($source as $k => $v) {
            if (strtolower(trim((string) $k)) === strtolower($key)) {
                return trim((string) $v);
            }
        }

        return '';
    };

    $vegValue = $valueOf($row, 'Veg');
    $jainValue = $valueOf($row, 'Jain');

    $nonVegCols = ['Chicken', 'Mutton', 'Basa', 'Prawns', 'Surmai', 'Pomfret', 'Crab', 'Egg'];
    $hasNonVeg = false;
    foreach ($nonVegCols as $col) {
        if ($valueOf($row, $col) !== '') {
            $hasNonVeg = true;
            break;
        }
    }

    $hasVeg = ($vegValue !== '');
    $hasJain = ($jainValue !== '');

    if ($hasJain && !$hasNonVeg) {
        return 'Jain';
    }

    if ($hasNonVeg && !$hasVeg && !$hasJain) {
        return 'NonVeg';
    }

    if ($hasVeg && !$hasNonVeg) {
        return 'Veg';
    }

    return '';
}

$db->beginTransaction();

try {
    // 1) MENU IMPORT
    echo "Importing menu sheets...\n";

    $menuTabs = [
        'AWGNK MENU' => 'food',
        'BAR MENU NK' => 'bar',
    ];

    $db->exec("DELETE FROM menu_items");

    foreach ($menuTabs as $tabName => $sheetType) {
        $records = fetchTabRecords($appsScriptUrl, $tabName, $snapshotDir);
        echo "  - {$tabName}: " . count($records) . " rows\n";

        $headers = [];
        foreach ($records as $r) {
            foreach ((array) $r as $k => $_) {
                $name = trim((string) $k);
                if ($name !== '' && !in_array($name, $headers, true)) {
                    $headers[] = $name;
                }
            }
        }

        $schemaStmt = $db->prepare('INSERT INTO menu_schema (sheet_type, headers) VALUES (:sheet_type, :headers) ON DUPLICATE KEY UPDATE headers = VALUES(headers)');
        $schemaStmt->execute([
            ':sheet_type' => $sheetType,
            ':headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
        ]);

        $ins = $db->prepare('INSERT INTO menu_items (sheet_type, category, sub_category, item_name, is_available, base_price, price_columns, food_category, sort_order, created_at)
                             VALUES (:sheet_type, :category, :sub_category, :item_name, :is_available, :base_price, :price_columns, :food_category, :sort_order, :created_at)');

        $sort = 1;
        foreach ($records as $row) {
            $row = (array) $row;
            $category = trim((string) findValue($row, ['Category'], ''));
            $subCategory = trim((string) findValue($row, ['Sub Category', 'SubCategory'], ''));
            $itemName = trim((string) findValue($row, ['Item Name', 'Item', 'Name'], ''));
            $availabilityRaw = findValue($row, ['Availability', 'Available'], 'Available');
            $foodCategory = trim((string) findValue($row, ['Food Category', 'FoodCategory', 'Veg/NonVeg/Jain'], ''));

            if ($itemName === '') {
                continue;
            }

            $priceColumns = [];
            $basePrice = null;
            foreach ($row as $key => $value) {
                $keyStr = trim((string) $key);
                if ($keyStr === '') continue;
                $kNorm = strtolower($keyStr);
                if (in_array($kNorm, ['category', 'sub category', 'subcategory', 'item name', 'item', 'name', 'availability', 'available', 'food category', 'foodcategory'], true)) {
                    continue;
                }
                if (is_numeric((string) $value)) {
                    $priceColumns[$keyStr] = (float) $value;
                    if ($basePrice === null) {
                        $basePrice = (float) $value;
                    }
                }
            }

            if ($sheetType !== 'food') {
                $foodCategory = '';
            } else {
                $foodCategory = deriveFoodCategory($row, $foodCategory);
            }

            $ins->execute([
                ':sheet_type' => $sheetType,
                ':category' => $category,
                ':sub_category' => $subCategory,
                ':item_name' => $itemName,
                ':is_available' => parseAvailability($availabilityRaw),
                ':base_price' => $basePrice,
                ':price_columns' => json_encode($priceColumns, JSON_UNESCAPED_UNICODE),
                ':food_category' => $foodCategory,
                ':sort_order' => $sort++,
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    // 2) EVENTS IMPORT
    echo "Importing EVENTS...\n";
    $eventRecords = fetchTabRecords($appsScriptUrl, 'EVENTS', $snapshotDir);
    echo "  - EVENTS: " . count($eventRecords) . " rows\n";

    $db->exec("DELETE FROM events");

    $eventIns = $db->prepare('INSERT INTO events (
        event_id, title, subtitle, description, image_url, video_url, show_video,
        cta_text, cta_url, badge_text,
        start_date, start_time, end_date, end_time, time_display_format,
        is_active, priority, popup_enabled, show_once_per_session,
        popup_delay_hours, popup_cooldown_hours,
        event_type, ticket_price, currency, max_tickets, payment_enabled,
        cancellation_policy, refund_policy, created_at
    ) VALUES (
        :event_id, :title, :subtitle, :description, :image_url, :video_url, :show_video,
        :cta_text, :cta_url, :badge_text,
        :start_date, :start_time, :end_date, :end_time, :time_display_format,
        :is_active, :priority, :popup_enabled, :show_once_per_session,
        :popup_delay_hours, :popup_cooldown_hours,
        :event_type, :ticket_price, :currency, :max_tickets, :payment_enabled,
        :cancellation_policy, :refund_policy, :created_at
    )');

    foreach ($eventRecords as $row) {
        $row = (array) $row;

        $eventId = trim((string) findValue($row, ['Event ID', 'event_id', 'id'], ''));
        $title = trim((string) findValue($row, ['Title'], ''));
        if ($eventId === '' || $title === '') {
            continue;
        }

        $eventIns->execute([
            ':event_id' => $eventId,
            ':title' => $title,
            ':subtitle' => trim((string) findValue($row, ['Subtitle'], '')),
            ':description' => trim((string) findValue($row, ['Description'], '')),
            ':image_url' => trim((string) findValue($row, ['Image URL'], '')),
            ':video_url' => trim((string) findValue($row, ['Video URL'], '')),
            ':show_video' => asBool(findValue($row, ['Show Video'], 0)),
            ':cta_text' => trim((string) findValue($row, ['CTA Text'], '')),
            ':cta_url' => trim((string) findValue($row, ['CTA URL'], '')),
            ':badge_text' => trim((string) findValue($row, ['Badge Text'], '')),
            ':start_date' => parseDate((string) findValue($row, ['Start Date'], '')),
            ':start_time' => trim((string) findValue($row, ['Start Time'], '')) ?: null,
            ':end_date' => parseDate((string) findValue($row, ['End Date'], '')),
            ':end_time' => trim((string) findValue($row, ['End Time'], '')) ?: null,
            ':time_display_format' => strtolower(trim((string) findValue($row, ['Time Display Format'], '12h'))) === '24h' ? '24h' : '12h',
            ':is_active' => asBool(findValue($row, ['Is Active'], 1)),
            ':priority' => (int) findValue($row, ['Priority'], 0),
            ':popup_enabled' => asBool(findValue($row, ['Popup Enabled'], 0)),
            ':show_once_per_session' => asBool(findValue($row, ['Show Once Per Session'], 0)),
            ':popup_delay_hours' => (float) findValue($row, ['Popup Delay Hours'], 0),
            ':popup_cooldown_hours' => (float) findValue($row, ['Popup Cooldown Hours'], 24),
            ':event_type' => strtolower(trim((string) findValue($row, ['Event Type'], 'free'))) === 'paid' ? 'paid' : 'free',
            ':ticket_price' => (float) findValue($row, ['Ticket Price'], 0),
            ':currency' => trim((string) findValue($row, ['Currency'], 'INR')) ?: 'INR',
            ':max_tickets' => (int) findValue($row, ['Max Tickets'], 0),
            ':payment_enabled' => asBool(findValue($row, ['Payment Enabled'], 0)),
            ':cancellation_policy' => trim((string) findValue($row, ['Cancellation Policy Text'], '')),
            ':refund_policy' => trim((string) findValue($row, ['Refund Policy'], 'No refund once pass is purchased.')),
            ':created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // 3) LEADS IMPORT
    echo "Importing Leads...\n";
    $leadRecords = fetchTabRecords($appsScriptUrl, 'Leads', $snapshotDir);
    echo "  - Leads: " . count($leadRecords) . " rows\n";

    $db->exec("DELETE FROM leads");

    $leadIns = $db->prepare('INSERT INTO leads (
        created_at, name, phone, prize, status,
        date_of_birth, date_of_anniversary, source,
        visit_count, coupon_code, crm_sync_status, crm_sync_code, crm_sync_message, redeemed_at
    ) VALUES (
        :created_at, :name, :phone, :prize, :status,
        :date_of_birth, :date_of_anniversary, :source,
        :visit_count, :coupon_code, :crm_sync_status, :crm_sync_code, :crm_sync_message, :redeemed_at
    )');

    foreach ($leadRecords as $row) {
        $row = (array) $row;
        $phone = preg_replace('/\D/', '', (string) findValue($row, ['Phone'], ''));
        if ($phone === '') {
            continue;
        }

        $leadIns->execute([
            ':created_at' => parseDateTime((string) findValue($row, ['Timestamp'], date('Y-m-d H:i:s'))) ?: date('Y-m-d H:i:s'),
            ':name' => trim((string) findValue($row, ['Name'], '')),
            ':phone' => substr($phone, -10),
            ':prize' => trim((string) findValue($row, ['Prize'], '')),
            ':status' => trim((string) findValue($row, ['Status'], 'Unredeemed')) ?: 'Unredeemed',
            ':date_of_birth' => parseDate((string) findValue($row, ['Date Of Birth'], '')),
            ':date_of_anniversary' => parseDate((string) findValue($row, ['Date Of Anniversary'], '')),
            ':source' => trim((string) findValue($row, ['Source'], 'menu-blocker-web')),
            ':visit_count' => (int) findValue($row, ['Visit Count'], 1),
            ':coupon_code' => trim((string) findValue($row, ['Coupon Code'], '')),
            ':crm_sync_status' => trim((string) findValue($row, ['CRM Sync Status'], 'Pending')),
            ':crm_sync_code' => trim((string) findValue($row, ['CRM Sync Code'], '')),
            ':crm_sync_message' => trim((string) findValue($row, ['CRM Sync Message'], '')),
            ':redeemed_at' => null,
        ]);
    }

    // 4) USERS IMPORT (optional)
    echo "Importing Users (optional)...\n";
    $userRecords = fetchTabRecords($appsScriptUrl, 'Users', $snapshotDir);
    echo "  - Users: " . count($userRecords) . " rows\n";

    if (count($userRecords) > 0) {
        $db->exec("DELETE FROM users");

        $insUser = $db->prepare('INSERT INTO users (
            username, display_name, role, password_hash, password_salt, status,
            force_password_change, failed_attempts, lockout_until,
            last_login_at, last_login_ip, created_at, created_by, updated_at, updated_by, permissions
        ) VALUES (
            :username, :display_name, :role, :password_hash, :password_salt, :status,
            :force_password_change, :failed_attempts, :lockout_until,
            :last_login_at, :last_login_ip, :created_at, :created_by, :updated_at, :updated_by, :permissions
        )');

        foreach ($userRecords as $row) {
            $row = (array) $row;
            $username = AuthService::normalizeUsername((string) findValue($row, ['Username'], ''));
            if ($username === '') {
                continue;
            }

            $permissionsRaw = (string) findValue($row, ['Permissions'], '{}');
            $permissions = json_decode($permissionsRaw, true);
            if (!is_array($permissions)) {
                $permissions = [];
            }

            $role = strtolower(trim((string) findValue($row, ['Role'], 'admin')));
            if (!in_array($role, ['admin', 'superadmin'], true)) {
                $role = 'admin';
            }

            $status = strtolower(trim((string) findValue($row, ['Status'], 'active')));
            if (!in_array($status, ['active', 'disabled'], true)) {
                $status = 'active';
            }

            $insUser->execute([
                ':username' => $username,
                ':display_name' => trim((string) findValue($row, ['Display Name'], '')),
                ':role' => $role,
                ':password_hash' => trim((string) findValue($row, ['Password Hash'], '')),
                ':password_salt' => trim((string) findValue($row, ['Password Salt'], '')),
                ':status' => $status,
                ':force_password_change' => asBool(findValue($row, ['Force Password Change'], 0)),
                ':failed_attempts' => (int) findValue($row, ['Failed Attempts'], 0),
                ':lockout_until' => parseDateTime((string) findValue($row, ['Lockout Until'], '')),
                ':last_login_at' => parseDateTime((string) findValue($row, ['Last Login At'], '')),
                ':last_login_ip' => trim((string) findValue($row, ['Last Login IP'], '')),
                ':created_at' => parseDateTime((string) findValue($row, ['Created At'], date('Y-m-d H:i:s'))) ?: date('Y-m-d H:i:s'),
                ':created_by' => trim((string) findValue($row, ['Created By'], 'importer')),
                ':updated_at' => parseDateTime((string) findValue($row, ['Updated At'], '')),
                ':updated_by' => trim((string) findValue($row, ['Updated By'], '')),
                ':permissions' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    $db->commit();
    echo "Import completed successfully.\n";
} catch (Throwable $e) {
    $db->rollBack();
    fwrite(STDERR, 'Import failed: ' . $e->getMessage() . "\n");
    exit(1);
}
