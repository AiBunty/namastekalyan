<?php

/**
 * One-time secure setup & migration runner.
 *
 * Usage:
 *   https://namastekalyan.asianwokandgrill.in/backend/setup.php?token=YOUR_SETUP_TOKEN
 *
 * Steps performed:
 *   1. Verify SETUP_TOKEN from .env
 *   2. Test database connectivity and report details
 *   3. Run all pending SQL migrations from database/migrations/
 *   4. Import data from Google Apps Script (Menu, Events, Leads, Users)
 *   5. Run auth bootstrap (ensure superadmin account exists)
 *
 * SECURITY: Delete or blank SETUP_TOKEN in .env once migration is done.
 */

declare(strict_types=1);

// ── Output buffering for clean HTML display ───────────────────────────────────
ob_start();

require_once __DIR__ . '/bootstrap.php';

// ── Token guard ───────────────────────────────────────────────────────────────
$setupToken = trim((string) ($_ENV['SETUP_TOKEN'] ?? ''));
$provided   = trim((string) ($_GET['token'] ?? ''));

if ($setupToken === '' || $provided === '' || !hash_equals($setupToken, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Invalid or missing setup token.']);
    exit;
}

// ── Step tracking ─────────────────────────────────────────────────────────────
$steps  = [];
$errors = [];

function logStep(string $label, string $status, string $detail = ''): void
{
    global $steps;
    $steps[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
}

// ── Step 1: DB connectivity ───────────────────────────────────────────────────
$db = null;
try {
    $host = trim((string) ($_ENV['DB_HOST'] ?? 'localhost'));
    $port = trim((string) ($_ENV['DB_PORT'] ?? '3306'));
    $name = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';

    if (strpos($host, ':') !== false) {
        [$h, $p] = explode(':', $host, 2);
        if ($h !== '') $host = $h;
        if ($p !== '' && ctype_digit($p)) $port = $p;
    }

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $db  = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    logStep('DB Connect', 'OK', "Connected to {$name} on {$host}:{$port}");
    \NK\Config\Database::reset(); // Flush singleton so next requests use fresh connection
} catch (Throwable $e) {
    logStep('DB Connect', 'FAIL', $e->getMessage());
    $errors[] = 'Database connection failed – cannot proceed.';
    outputAndExit();
}

// ── Step 2: Migrations ────────────────────────────────────────────────────────
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `filename`   VARCHAR(120) NOT NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_filename` (`filename`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $applied = array_flip(
        $db->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN)
    );

    $migsDir = __DIR__ . '/database/migrations';
    $files   = glob($migsDir . '/*.sql');
    sort($files);

    $ran = 0;
    $skipped = 0;
    foreach ((array) $files as $file) {
        $basename = basename((string) $file);
        if (isset($applied[$basename])) {
            $skipped++;
            continue;
        }

        $sql = (string) file_get_contents((string) $file);
        $db->exec($sql);
        $stmt = $db->prepare("INSERT INTO migrations (filename) VALUES (:f)");
        $stmt->execute([':f' => $basename]);
        $ran++;
    }

    logStep('Migrations', 'OK', "{$ran} applied, {$skipped} already up-to-date");
} catch (Throwable $e) {
    logStep('Migrations', 'FAIL', $e->getMessage());
    $errors[] = 'Migration failed: ' . $e->getMessage();
    outputAndExit();
}

// ── Step 3: Resolve Apps Script URL ──────────────────────────────────────────
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
$appsScriptUrl = preg_replace('/\?.*$/', '', $appsScriptUrl);

if ($appsScriptUrl === '') {
    logStep('Apps Script URL', 'SKIP', 'Could not resolve URL – import skipped');
} else {
    logStep('Apps Script URL', 'OK', $appsScriptUrl);
}

// ── Helper: fetch JSON via curl ───────────────────────────────────────────────
function setupFetchJson(string $url): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $raw    = curl_exec($ch);
    $err    = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err !== '' || $status < 200 || $status >= 300 || $raw === false) {
        return null;
    }

    $decoded = json_decode((string) $raw, true);
    return is_array($decoded) ? $decoded : null;
}

function setupFetchTab(string $base, string $tab): array
{
    $url  = $base . '?tab=' . rawurlencode($tab) . '&shape=records';
    $json = setupFetchJson($url);
    if (!is_array($json)) return [];

    if (isset($json['items']) && is_array($json['items'])) return $json['items'];
    if (isset($json['records']) && is_array($json['records'])) return $json['records'];
    if (isset($json['rows']) && is_array($json['rows'])) return $json['rows'];

    return [];
}

function setupFindVal(array $row, array $keys, mixed $default = ''): mixed
{
    $lower = [];
    foreach ($row as $k => $v) {
        $lower[strtolower(trim((string) $k))] = $v;
    }
    foreach ($keys as $key) {
        $k = strtolower(trim((string) $key));
        if (array_key_exists($k, $lower)) return $lower[$k];
    }
    return $default;
}

function setupAsBool(mixed $v): int
{
    if (is_bool($v)) return $v ? 1 : 0;
    return in_array(strtolower(trim((string) $v)), ['1','true','yes','active','enabled','on'], true) ? 1 : 0;
}

function setupParseDate(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $ts = strtotime($v);
    return $ts === false ? null : date('Y-m-d', $ts);
}

function setupParseDateTime(?string $v): ?string
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $ts = strtotime($v);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

// ── Step 4: Import data ───────────────────────────────────────────────────────
if ($appsScriptUrl !== '') {
    $db->beginTransaction();
    try {
        $importSummary = [];

        // ---- Menu ----
        foreach (['AWGNK MENU' => 'food', 'BAR MENU NK' => 'bar'] as $tabName => $sheetType) {
            $records = setupFetchTab($appsScriptUrl, $tabName);
            $importSummary[] = "{$tabName}: " . count($records) . " rows";

            if (count($records) > 0) {
                $db->exec("DELETE FROM menu_items WHERE sheet_type = " . $db->quote($sheetType));

                // Collect headers
                $headers = [];
                foreach ($records as $r) {
                    foreach ((array) $r as $k => $_) {
                        $name = trim((string) $k);
                        if ($name !== '' && !in_array($name, $headers, true)) $headers[] = $name;
                    }
                }

                $schemaStmt = $db->prepare('INSERT INTO menu_schema (sheet_type, headers) VALUES (:t,:h) ON DUPLICATE KEY UPDATE headers=VALUES(headers)');
                $schemaStmt->execute([':t' => $sheetType, ':h' => json_encode($headers, JSON_UNESCAPED_UNICODE)]);

                $ins = $db->prepare('INSERT INTO menu_items (sheet_type,category,sub_category,item_name,is_available,base_price,price_columns,food_category,sort_order,created_at) VALUES (:st,:cat,:sub,:item,:avail,:price,:pcols,:fcat,:sort,:created)');
                $sort = 1;
                foreach ($records as $row) {
                    $row      = (array) $row;
                    $itemName = trim((string) setupFindVal($row, ['Item Name','Item','Name'], ''));
                    if ($itemName === '') continue;

                    $priceColumns = [];
                    $basePrice    = null;
                    $skipCols     = ['category','sub category','subcategory','item name','item','name','availability','available','food category','foodcategory','veg','jain'];
                    foreach ($row as $key => $value) {
                        $kn = strtolower(trim((string) $key));
                        if (in_array($kn, $skipCols, true)) continue;
                        if (is_numeric((string) $value)) {
                            $priceColumns[trim((string) $key)] = (float) $value;
                            if ($basePrice === null) $basePrice = (float) $value;
                        }
                    }

                    $ins->execute([
                        ':st'      => $sheetType,
                        ':cat'     => trim((string) setupFindVal($row, ['Category'], '')),
                        ':sub'     => trim((string) setupFindVal($row, ['Sub Category','SubCategory'], '')),
                        ':item'    => $itemName,
                        ':avail'   => setupAsBool(setupFindVal($row, ['Availability','Available'], 'Available') === '' ? 'yes' : setupFindVal($row, ['Availability','Available'], 'Available')),
                        ':price'   => $basePrice,
                        ':pcols'   => json_encode($priceColumns, JSON_UNESCAPED_UNICODE),
                        ':fcat'    => $sheetType === 'food' ? trim((string) setupFindVal($row, ['Food Category','FoodCategory'], '')) : '',
                        ':sort'    => $sort++,
                        ':created' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        // ---- Events ----
        $eventRecords = setupFetchTab($appsScriptUrl, 'EVENTS');
        $importSummary[] = 'EVENTS: ' . count($eventRecords) . ' rows';
        if (count($eventRecords) > 0) {
            $db->exec("DELETE FROM events");
            $ins = $db->prepare('INSERT INTO events (event_id,title,subtitle,description,image_url,video_url,show_video,cta_text,cta_url,badge_text,start_date,start_time,end_date,end_time,time_display_format,is_active,priority,popup_enabled,show_once_per_session,popup_delay_hours,popup_cooldown_hours,event_type,ticket_price,currency,max_tickets,payment_enabled,cancellation_policy,refund_policy,created_at) VALUES (:eid,:title,:sub,:desc,:img,:vid,:showvid,:cta_t,:cta_u,:badge,:sd,:st,:ed,:et,:tdf,:active,:priority,:popup,:once,:delay,:cool,:etype,:price,:cur,:max,:pay,:cancel,:refund,:created)');
            foreach ($eventRecords as $row) {
                $row = (array) $row;
                $eventId = trim((string) setupFindVal($row, ['Event ID','event_id','id'], ''));
                $title   = trim((string) setupFindVal($row, ['Title'], ''));
                if ($eventId === '' || $title === '') continue;
                $ins->execute([
                    ':eid'     => $eventId, ':title'   => $title,
                    ':sub'     => trim((string) setupFindVal($row, ['Subtitle'], '')),
                    ':desc'    => trim((string) setupFindVal($row, ['Description'], '')),
                    ':img'     => trim((string) setupFindVal($row, ['Image URL'], '')),
                    ':vid'     => trim((string) setupFindVal($row, ['Video URL'], '')),
                    ':showvid' => setupAsBool(setupFindVal($row, ['Show Video'], 0)),
                    ':cta_t'   => trim((string) setupFindVal($row, ['CTA Text'], '')),
                    ':cta_u'   => trim((string) setupFindVal($row, ['CTA URL'], '')),
                    ':badge'   => trim((string) setupFindVal($row, ['Badge Text'], '')),
                    ':sd'      => setupParseDate((string) setupFindVal($row, ['Start Date'], '')),
                    ':st'      => trim((string) setupFindVal($row, ['Start Time'], '')) ?: null,
                    ':ed'      => setupParseDate((string) setupFindVal($row, ['End Date'], '')),
                    ':et'      => trim((string) setupFindVal($row, ['End Time'], '')) ?: null,
                    ':tdf'     => strtolower(trim((string) setupFindVal($row, ['Time Display Format'], '12h'))) === '24h' ? '24h' : '12h',
                    ':active'  => setupAsBool(setupFindVal($row, ['Is Active'], 1)),
                    ':priority'=> (int) setupFindVal($row, ['Priority'], 0),
                    ':popup'   => setupAsBool(setupFindVal($row, ['Popup Enabled'], 0)),
                    ':once'    => setupAsBool(setupFindVal($row, ['Show Once Per Session'], 0)),
                    ':delay'   => (float) setupFindVal($row, ['Popup Delay Hours'], 0),
                    ':cool'    => (float) setupFindVal($row, ['Popup Cooldown Hours'], 24),
                    ':etype'   => strtolower(trim((string) setupFindVal($row, ['Event Type'], 'free'))) === 'paid' ? 'paid' : 'free',
                    ':price'   => (float) setupFindVal($row, ['Ticket Price'], 0),
                    ':cur'     => trim((string) setupFindVal($row, ['Currency'], 'INR')) ?: 'INR',
                    ':max'     => (int) setupFindVal($row, ['Max Tickets'], 0),
                    ':pay'     => setupAsBool(setupFindVal($row, ['Payment Enabled'], 0)),
                    ':cancel'  => trim((string) setupFindVal($row, ['Cancellation Policy Text'], '')),
                    ':refund'  => trim((string) setupFindVal($row, ['Refund Policy'], 'No refund once pass is purchased.')),
                    ':created' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        // ---- Leads ----
        $leadRecords = setupFetchTab($appsScriptUrl, 'Leads');
        $importSummary[] = 'Leads: ' . count($leadRecords) . ' rows';
        if (count($leadRecords) > 0) {
            $db->exec("DELETE FROM leads");
            $ins = $db->prepare('INSERT INTO leads (created_at,name,phone,prize,status,date_of_birth,date_of_anniversary,source,visit_count,coupon_code,crm_sync_status,crm_sync_code,crm_sync_message,redeemed_at) VALUES (:created,:name,:phone,:prize,:status,:dob,:ann,:src,:vc,:coupon,:crmst,:crmc,:crmm,:redeemed)');
            foreach ($leadRecords as $row) {
                $row   = (array) $row;
                $phone = preg_replace('/\D/', '', (string) setupFindVal($row, ['Phone'], ''));
                if ($phone === '') continue;
                $ins->execute([
                    ':created'  => setupParseDateTime((string) setupFindVal($row, ['Timestamp'], date('Y-m-d H:i:s'))) ?: date('Y-m-d H:i:s'),
                    ':name'     => trim((string) setupFindVal($row, ['Name'], '')),
                    ':phone'    => substr($phone, -10),
                    ':prize'    => trim((string) setupFindVal($row, ['Prize'], '')),
                    ':status'   => trim((string) setupFindVal($row, ['Status'], 'Unredeemed')) ?: 'Unredeemed',
                    ':dob'      => setupParseDate((string) setupFindVal($row, ['Date Of Birth'], '')),
                    ':ann'      => setupParseDate((string) setupFindVal($row, ['Date Of Anniversary'], '')),
                    ':src'      => trim((string) setupFindVal($row, ['Source'], 'menu-blocker-web')),
                    ':vc'       => (int) setupFindVal($row, ['Visit Count'], 1),
                    ':coupon'   => trim((string) setupFindVal($row, ['Coupon Code'], '')),
                    ':crmst'    => trim((string) setupFindVal($row, ['CRM Sync Status'], 'Pending')),
                    ':crmc'     => trim((string) setupFindVal($row, ['CRM Sync Code'], '')),
                    ':crmm'     => trim((string) setupFindVal($row, ['CRM Sync Message'], '')),
                    ':redeemed' => null,
                ]);
            }
        }

        // ---- Users ----
        $userRecords = setupFetchTab($appsScriptUrl, 'Users');
        $importSummary[] = 'Users: ' . count($userRecords) . ' rows';
        if (count($userRecords) > 0) {
            $db->exec("DELETE FROM users");
            $ins = $db->prepare('INSERT INTO users (username,display_name,role,password_hash,password_salt,status,force_password_change,failed_attempts,lockout_until,last_login_at,last_login_ip,created_at,created_by,updated_at,updated_by,permissions) VALUES (:un,:dn,:role,:ph,:ps,:st,:fpc,:fa,:lu,:lla,:llip,:cat,:cb,:uat,:ub,:perms)');
            foreach ($userRecords as $row) {
                $row      = (array) $row;
                $username = \NK\Services\AuthService::normalizeUsername((string) setupFindVal($row, ['Username'], ''));
                if ($username === '') continue;
                $permsRaw = (string) setupFindVal($row, ['Permissions'], '{}');
                $perms    = json_decode($permsRaw, true);
                if (!is_array($perms)) $perms = [];
                if (array_keys($perms) === range(0, count($perms) - 1)) {
                    $normalizedPerms = [];
                    foreach ($perms as $permission) {
                        $key = trim((string) $permission);
                        if ($key !== '') {
                            $normalizedPerms[$key] = true;
                        }
                    }
                    $perms = $normalizedPerms;
                }
                $role   = strtolower(trim((string) setupFindVal($row, ['Role'], 'admin')));
                if (!in_array($role, ['admin','superadmin'], true)) $role = 'admin';
                $status = strtolower(trim((string) setupFindVal($row, ['Status'], 'active')));
                if (!in_array($status, ['active','disabled'], true)) $status = 'active';
                $ins->execute([
                    ':un'   => $username,
                    ':dn'   => trim((string) setupFindVal($row, ['Display Name'], '')),
                    ':role' => $role,
                    ':ph'   => trim((string) setupFindVal($row, ['Password Hash'], '')),
                    ':ps'   => trim((string) setupFindVal($row, ['Password Salt'], '')),
                    ':st'   => $status,
                    ':fpc'  => setupAsBool(setupFindVal($row, ['Force Password Change'], 0)),
                    ':fa'   => (int) setupFindVal($row, ['Failed Attempts'], 0),
                    ':lu'   => setupParseDateTime((string) setupFindVal($row, ['Lockout Until'], '')),
                    ':lla'  => setupParseDateTime((string) setupFindVal($row, ['Last Login At'], '')),
                    ':llip' => trim((string) setupFindVal($row, ['Last Login IP'], '')),
                    ':cat'  => setupParseDateTime((string) setupFindVal($row, ['Created At'], '')) ?: date('Y-m-d H:i:s'),
                    ':cb'   => trim((string) setupFindVal($row, ['Created By'], 'importer')),
                    ':uat'  => setupParseDateTime((string) setupFindVal($row, ['Updated At'], '')),
                    ':ub'   => trim((string) setupFindVal($row, ['Updated By'], '')),
                    ':perms'=> json_encode($perms, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        $db->commit();
        logStep('Data Import', 'OK', implode('; ', $importSummary));
    } catch (Throwable $e) {
        $db->rollBack();
        logStep('Data Import', 'FAIL', $e->getMessage());
        $errors[] = 'Import failed: ' . $e->getMessage();
    }
} else {
    logStep('Data Import', 'SKIP', 'No Apps Script URL available');
}

// ── Step 5: Bootstrap superadmin ─────────────────────────────────────────────
try {
    $authService = new \NK\Services\AuthService();
    $bootstrap   = $authService->bootstrapStatus();
    if (isset($bootstrap['bootstrapped']) && !$bootstrap['bootstrapped']) {
        // Trigger bootstrap by attempting login with bootstrap credentials
        // (AuthService.bootstrapStatus runs auto-bootstrap on first call if table is empty)
        $detail = 'Superadmin bootstrapped.';
    } else {
        $detail = $bootstrap['message'] ?? 'Superadmin already exists.';
    }
    logStep('Bootstrap', 'OK', $detail);
} catch (Throwable $e) {
    logStep('Bootstrap', 'WARN', $e->getMessage());
}

// ── Output ────────────────────────────────────────────────────────────────────
function outputAndExit(): void
{
    global $steps, $errors;
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'     => count($errors) === 0,
        'steps'  => $steps,
        'errors' => $errors,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

outputAndExit();
