<?php

declare(strict_types=1);

$token = trim((string) ($_GET['token'] ?? ''));
if ($token !== 'nk032-whatsapp-20260421') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Forbidden';
    exit;
}

require __DIR__ . '/bootstrap/app.php';

use NK\Config\Database;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::connection();
    $dbName = trim((string) ($_ENV['DB_NAME'] ?? ''));
    if ($dbName === '') {
        throw new RuntimeException('Missing DB_NAME');
    }

    $tableExists = static function (string $table) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table');
        $stmt->execute([
            ':db' => $dbName,
            ':table' => $table,
        ]);
        return (bool) $stmt->fetchColumn();
    };

    $hasIndex = static function (string $table, string $index) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND INDEX_NAME = :idx');
        $stmt->execute([
            ':db' => $dbName,
            ':table' => $table,
            ':idx' => $index,
        ]);
        return (bool) $stmt->fetchColumn();
    };

    $mappingExists = static function (string $eventKey) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM whatsapp_event_mappings WHERE event_key = :event_key');
        $stmt->execute([':event_key' => $eventKey]);
        return (bool) $stmt->fetchColumn();
    };

    $applied = [];

    if (!$tableExists('whatsapp_scheduled_messages')) {
        $pdo->exec("CREATE TABLE whatsapp_scheduled_messages (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_key VARCHAR(80) NOT NULL,
            transaction_id VARCHAR(80) NULL DEFAULT NULL,
            lead_id BIGINT UNSIGNED NULL DEFAULT NULL,
            phone VARCHAR(20) NOT NULL DEFAULT '',
            customer_name VARCHAR(160) NOT NULL DEFAULT '',
            event_id VARCHAR(80) NOT NULL DEFAULT '',
            event_title VARCHAR(200) NOT NULL DEFAULT '',
            due_at DATETIME NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_result_code VARCHAR(40) NOT NULL DEFAULT '',
            last_result_message VARCHAR(500) NOT NULL DEFAULT '',
            payload_json LONGTEXT NULL,
            sent_at DATETIME NULL DEFAULT NULL,
            cancelled_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_whatsapp_schedule_event_tx (event_key, transaction_id),
            KEY idx_whatsapp_schedule_due_status (status, due_at),
            KEY idx_whatsapp_schedule_event (event_key),
            KEY idx_whatsapp_schedule_tx (transaction_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $applied[] = 'whatsapp_scheduled_messages';
    }

    if (!$tableExists('whatsapp_template_drafts')) {
        $pdo->exec("CREATE TABLE whatsapp_template_drafts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_name VARCHAR(160) NOT NULL DEFAULT '',
            template_name VARCHAR(120) NOT NULL,
            category VARCHAR(30) NOT NULL DEFAULT 'UTILITY',
            language_code VARCHAR(20) NOT NULL DEFAULT 'en',
            header_type VARCHAR(20) NOT NULL DEFAULT 'NONE',
            header_text VARCHAR(120) NOT NULL DEFAULT '',
            body_text TEXT NOT NULL,
            footer_text VARCHAR(120) NOT NULL DEFAULT '',
            buttons_json LONGTEXT NULL,
            sample_variables_json LONGTEXT NULL,
            example_media_handle VARCHAR(255) NOT NULL DEFAULT '',
            status VARCHAR(40) NOT NULL DEFAULT 'draft',
            meta_template_id VARCHAR(80) NOT NULL DEFAULT '',
            submitted_at DATETIME NULL DEFAULT NULL,
            last_synced_at DATETIME NULL DEFAULT NULL,
            rejection_reason VARCHAR(500) NOT NULL DEFAULT '',
            created_by VARCHAR(30) NOT NULL DEFAULT '',
            updated_by VARCHAR(30) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_whatsapp_template_drafts_status (status),
            KEY idx_whatsapp_template_drafts_name_lang (template_name, language_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $applied[] = 'whatsapp_template_drafts';
    }

    $eventKeys = [
        'event_registration_confirmed',
        'winner_coupon_issued',
        'coupon_redeemed',
        'guest_checked_in',
        'event_reminder_24h_image',
        'event_reminder_6h_image',
        'event_reminder_2h_image',
        'event_checkin_pending_30m',
        'event_checkin_thank_you_12h',
    ];

    foreach ($eventKeys as $eventKey) {
        if (!$mappingExists($eventKey)) {
            $stmt = $pdo->prepare('INSERT INTO whatsapp_event_mappings (event_key, template_name, language_code, is_enabled, updated_by) VALUES (:event_key, :template_name, :language_code, :is_enabled, :updated_by)');
            $stmt->execute([
                ':event_key' => $eventKey,
                ':template_name' => '',
                ':language_code' => '',
                ':is_enabled' => 0,
                ':updated_by' => 'migration-032',
            ]);
            $applied[] = 'mapping:' . $eventKey;
        }
    }

    echo json_encode([
        'ok' => true,
        'applied' => $applied,
        'checks' => [
            'whatsapp_scheduled_messages_exists' => $tableExists('whatsapp_scheduled_messages'),
            'whatsapp_template_drafts_exists' => $tableExists('whatsapp_template_drafts'),
            'schedule_unique_index' => $hasIndex('whatsapp_scheduled_messages', 'uq_whatsapp_schedule_event_tx'),
            'drafts_name_lang_index' => $hasIndex('whatsapp_template_drafts', 'idx_whatsapp_template_drafts_name_lang'),
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'MIGRATION_032_FAILED',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
}