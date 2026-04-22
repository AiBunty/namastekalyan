<?php

declare(strict_types=1);

$token = trim((string) ($_GET['token'] ?? ''));
if ($token !== 'nk031-whatsapp-20260421') {
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

    $hasColumn = static function (string $column) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute([
            ':db' => $dbName,
            ':table' => 'whatsapp_message_logs',
            ':column' => $column,
        ]);
        return (bool) $stmt->fetchColumn();
    };

    $hasIndex = static function (string $index) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND INDEX_NAME = :idx');
        $stmt->execute([
            ':db' => $dbName,
            ':table' => 'whatsapp_message_logs',
            ':idx' => $index,
        ]);
        return (bool) $stmt->fetchColumn();
    };

    $hasLeadColumn = static function (string $column) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $stmt->execute([
            ':db' => $dbName,
            ':table' => 'leads',
            ':column' => $column,
        ]);
        return (bool) $stmt->fetchColumn();
    };

    $tableExists = static function (string $table) use ($pdo, $dbName): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table');
        $stmt->execute([
            ':db' => $dbName,
            ':table' => $table,
        ]);
        return (bool) $stmt->fetchColumn();
    };

    $applied = [];

    if (!$hasLeadColumn('spin_completed_at')) {
        $pdo->exec("ALTER TABLE leads ADD COLUMN spin_completed_at DATETIME NULL DEFAULT NULL AFTER created_at, ADD INDEX idx_spin_completed_at (spin_completed_at)");
        $applied[] = 'leads.spin_completed_at';
    }

    foreach ([
        'surprise_reward_label' => "ALTER TABLE leads ADD COLUMN surprise_reward_label VARCHAR(200) NULL DEFAULT NULL AFTER prize",
        'surprise_coupon_code' => "ALTER TABLE leads ADD COLUMN surprise_coupon_code VARCHAR(30) NULL DEFAULT NULL AFTER coupon_code",
        'surprise_issued_at' => "ALTER TABLE leads ADD COLUMN surprise_issued_at DATETIME NULL DEFAULT NULL AFTER surprise_coupon_code",
        'surprise_issued_by' => "ALTER TABLE leads ADD COLUMN surprise_issued_by VARCHAR(30) NULL DEFAULT NULL AFTER surprise_issued_at",
        'surprise_redeemed_at' => "ALTER TABLE leads ADD COLUMN surprise_redeemed_at DATETIME NULL DEFAULT NULL AFTER surprise_issued_by",
    ] as $column => $sql) {
        if (!$hasLeadColumn($column)) {
            $pdo->exec($sql);
            $applied[] = 'leads.' . $column;
        }
    }

    if (!$tableExists('whatsapp_message_templates')) {
        $pdo->exec("CREATE TABLE whatsapp_message_templates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            template_uid VARCHAR(80) NOT NULL,
            template_name VARCHAR(120) NOT NULL,
            language_code VARCHAR(20) NOT NULL,
            category VARCHAR(40) NOT NULL DEFAULT '',
            status VARCHAR(40) NOT NULL DEFAULT '',
            quality_score VARCHAR(40) NOT NULL DEFAULT '',
            components_json LONGTEXT NULL,
            last_synced_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_whatsapp_template_uid (template_uid),
            UNIQUE KEY uq_whatsapp_template_name_lang (template_name, language_code),
            KEY idx_whatsapp_template_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $applied[] = 'whatsapp_message_templates';
    }

    if (!$tableExists('whatsapp_event_mappings')) {
        $pdo->exec("CREATE TABLE whatsapp_event_mappings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_key VARCHAR(80) NOT NULL,
            template_name VARCHAR(120) NOT NULL DEFAULT '',
            language_code VARCHAR(20) NOT NULL DEFAULT '',
            is_enabled TINYINT(1) NOT NULL DEFAULT 0,
            updated_by VARCHAR(30) NOT NULL DEFAULT '',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_whatsapp_event_key (event_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO whatsapp_event_mappings (event_key, template_name, language_code, is_enabled, updated_by) VALUES ('try_again_surprise_issued', '', '', 0, 'migration-030')");
        $applied[] = 'whatsapp_event_mappings';
    }

    if (!$tableExists('whatsapp_message_logs')) {
        $pdo->exec("CREATE TABLE whatsapp_message_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            lead_id BIGINT UNSIGNED NULL DEFAULT NULL,
            event_key VARCHAR(80) NOT NULL DEFAULT '',
            phone VARCHAR(20) NOT NULL DEFAULT '',
            template_name VARCHAR(120) NOT NULL DEFAULT '',
            language_code VARCHAR(20) NOT NULL DEFAULT '',
            attempted TINYINT(1) NOT NULL DEFAULT 0,
            success TINYINT(1) NOT NULL DEFAULT 0,
            http_code VARCHAR(20) NOT NULL DEFAULT '',
            response_message VARCHAR(500) NOT NULL DEFAULT '',
            request_payload_json LONGTEXT NULL,
            response_payload_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_whatsapp_logs_event (event_key),
            KEY idx_whatsapp_logs_lead (lead_id),
            KEY idx_whatsapp_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $applied[] = 'whatsapp_message_logs';
    }

    if (!$hasColumn('provider_message_id')) {
        $pdo->exec("ALTER TABLE whatsapp_message_logs ADD COLUMN provider_message_id VARCHAR(120) NULL DEFAULT NULL AFTER language_code");
        $applied[] = 'provider_message_id';
    }
    if (!$hasColumn('delivery_status')) {
        $pdo->exec("ALTER TABLE whatsapp_message_logs ADD COLUMN delivery_status VARCHAR(40) NOT NULL DEFAULT '' AFTER provider_message_id");
        $applied[] = 'delivery_status';
    }
    if (!$hasColumn('status_updated_at')) {
        $pdo->exec("ALTER TABLE whatsapp_message_logs ADD COLUMN status_updated_at DATETIME NULL DEFAULT NULL AFTER delivery_status");
        $applied[] = 'status_updated_at';
    }
    if (!$hasIndex('idx_whatsapp_logs_provider_message_id')) {
        $pdo->exec("ALTER TABLE whatsapp_message_logs ADD KEY idx_whatsapp_logs_provider_message_id (provider_message_id)");
        $applied[] = 'idx_whatsapp_logs_provider_message_id';
    }
    if (!$hasIndex('idx_whatsapp_logs_delivery_status')) {
        $pdo->exec("ALTER TABLE whatsapp_message_logs ADD KEY idx_whatsapp_logs_delivery_status (delivery_status)");
        $applied[] = 'idx_whatsapp_logs_delivery_status';
    }

    echo json_encode([
        'ok' => true,
        'applied' => $applied,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'MIGRATION_031_FAILED',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
}