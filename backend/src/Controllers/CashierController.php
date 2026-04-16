<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Config\Database;
use NK\Middleware\AuthMiddleware;
use NK\Repositories\EventRepository;
use PDO;

class CashierController
{
    public static function issueCashPaidPass(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'cashier')) {
            return [
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Cashier permission required.',
            ];
        }

        $eventId = trim((string) ($payload['eventId'] ?? $payload['event_id'] ?? ''));
        $customerName = trim((string) ($payload['customerName'] ?? ''));
        $customerPhone = trim((string) ($payload['customerPhone'] ?? ''));
        $customerEmail = trim((string) ($payload['customerEmail'] ?? ''));
        $qty = max(1, (int) ($payload['qty'] ?? 1));
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($eventId === '' || $customerName === '' || $customerPhone === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'eventId, customerName and customerPhone are required.',
            ];
        }

        $event = (new EventRepository())->findByEventId($eventId);
        if (!$event) {
            return [
                'ok' => false,
                'error' => 'EVENT_NOT_FOUND',
                'message' => 'Event not found.',
            ];
        }

        $ticketPrice = (float) ($event['ticket_price'] ?? 0);
        if ($ticketPrice <= 0) {
            return [
                'ok' => false,
                'error' => 'INVALID_EVENT_PRICING',
                'message' => 'Cash pass can only be issued for paid events.',
            ];
        }

        $db = self::db();
        $now = date('Y-m-d H:i:s');
        $ledgerDate = date('Y-m-d');
        $adminUsername = (string) ($auth['user']['username'] ?? '');
        $adminDisplayName = (string) ($auth['user']['displayName'] ?? $adminUsername);

        $transactionId = self::generateId('CASH-TXN');
        $paymentId = self::generateId('CASHPAY');
        $entryId = self::generateId('CASH-LEDGER');
        $amount = round($ticketPrice * $qty, 2);
        $qrPayload = self::buildQrPayload($transactionId, $eventId, $paymentId);
        $qrUrl = self::buildQrUrl($qrPayload);
        $attendees = self::parseAttendeeNames((string) ($payload['attendeeNames'] ?? ''));

        $db->beginTransaction();
        try {
            $txStmt = $db->prepare('INSERT INTO event_transactions (
                transaction_id, event_id, event_title,
                customer_name, customer_email, customer_phone,
                qty, amount, currency, gateway,
                payment_id, status,
                qr_url, qr_payload,
                created_at, paid_at,
                attendee_details, guest_passes_json,
                issued_by, cash_ledger_entry_id
            ) VALUES (
                :transaction_id, :event_id, :event_title,
                :customer_name, :customer_email, :customer_phone,
                :qty, :amount, :currency, :gateway,
                :payment_id, :status,
                :qr_url, :qr_payload,
                :created_at, :paid_at,
                :attendee_details, :guest_passes_json,
                :issued_by, :cash_ledger_entry_id
            )');

            $txStmt->execute([
                ':transaction_id' => $transactionId,
                ':event_id' => $eventId,
                ':event_title' => (string) ($event['title'] ?? ''),
                ':customer_name' => $customerName,
                ':customer_email' => $customerEmail,
                ':customer_phone' => $customerPhone,
                ':qty' => $qty,
                ':amount' => $amount,
                ':currency' => (string) ($event['currency'] ?? 'INR'),
                ':gateway' => 'cash',
                ':payment_id' => $paymentId,
                ':status' => 'paid',
                ':qr_url' => $qrUrl,
                ':qr_payload' => $qrPayload,
                ':created_at' => $now,
                ':paid_at' => $now,
                ':attendee_details' => json_encode($attendees, JSON_UNESCAPED_UNICODE),
                ':guest_passes_json' => json_encode($attendees, JSON_UNESCAPED_UNICODE),
                ':issued_by' => $adminUsername,
                ':cash_ledger_entry_id' => $entryId,
            ]);

            $ledgerStmt = $db->prepare('INSERT INTO admin_cash_ledger (
                entry_id, ledger_date, admin_username, admin_display_name,
                transaction_id, event_id, event_title,
                customer_name, customer_phone,
                qty, amount, currency, status,
                issued_at, notes
            ) VALUES (
                :entry_id, :ledger_date, :admin_username, :admin_display_name,
                :transaction_id, :event_id, :event_title,
                :customer_name, :customer_phone,
                :qty, :amount, :currency, :status,
                :issued_at, :notes
            )');

            $ledgerStmt->execute([
                ':entry_id' => $entryId,
                ':ledger_date' => $ledgerDate,
                ':admin_username' => $adminUsername,
                ':admin_display_name' => $adminDisplayName,
                ':transaction_id' => $transactionId,
                ':event_id' => $eventId,
                ':event_title' => (string) ($event['title'] ?? ''),
                ':customer_name' => $customerName,
                ':customer_phone' => $customerPhone,
                ':qty' => $qty,
                ':amount' => $amount,
                ':currency' => (string) ($event['currency'] ?? 'INR'),
                ':status' => 'issued',
                ':issued_at' => $now,
                ':notes' => $notes,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'ok' => false,
                'error' => 'DB_WRITE_FAILED',
                'message' => 'Unable to issue cash paid pass.',
            ];
        }

        return [
            'ok' => true,
            'action' => 'admin_issue_cash_paid_pass',
            'transactionId' => $transactionId,
            'amount' => $amount,
            'currency' => (string) ($event['currency'] ?? 'INR'),
            'qrUrl' => $qrUrl,
            'message' => 'Cash paid pass issued.',
        ];
    }

    public static function requestCashHandover(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'cashier')) {
            return [
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Cashier permission required.',
            ];
        }

        $ledgerDate = self::normalizeLedgerDate((string) ($payload['ledgerDate'] ?? ''));
        $adminUsername = (string) ($auth['user']['username'] ?? '');
        $adminDisplayName = (string) ($auth['user']['displayName'] ?? $adminUsername);
        $db = self::db();

        $existingStmt = $db->prepare('SELECT batch_key, total_transactions, total_amount
            FROM superadmin_cash_ledger
            WHERE ledger_date = :ledger_date
              AND admin_username = :admin_username
              AND status = "pending"
            ORDER BY id DESC
            LIMIT 1');
        $existingStmt->execute([
            ':ledger_date' => $ledgerDate,
            ':admin_username' => $adminUsername,
        ]);
        $existing = $existingStmt->fetch();
        if ($existing) {
            return [
                'ok' => true,
                'action' => 'admin_request_cash_handover',
                'batchKey' => (string) $existing['batch_key'],
                'totalTransactions' => (int) ($existing['total_transactions'] ?? 0),
                'totalAmount' => (float) ($existing['total_amount'] ?? 0),
                'message' => 'Handover request already pending.',
            ];
        }

        $sumStmt = $db->prepare('SELECT COUNT(*) AS total_transactions, COALESCE(SUM(amount), 0) AS total_amount
            FROM admin_cash_ledger
            WHERE ledger_date = :ledger_date
              AND admin_username = :admin_username
              AND status = "issued"');
        $sumStmt->execute([
            ':ledger_date' => $ledgerDate,
            ':admin_username' => $adminUsername,
        ]);
        $totals = $sumStmt->fetch() ?: ['total_transactions' => 0, 'total_amount' => 0];
        $totalTransactions = (int) ($totals['total_transactions'] ?? 0);
        $totalAmount = (float) ($totals['total_amount'] ?? 0);

        if ($totalTransactions <= 0) {
            return [
                'ok' => false,
                'error' => 'NO_ELIGIBLE_TRANSACTIONS',
                'message' => 'No issued cash transactions available for handover.',
            ];
        }

        $batchKey = self::generateId('HANDOVER');
        $now = date('Y-m-d H:i:s');
        $db->beginTransaction();
        try {
            $insertStmt = $db->prepare('INSERT INTO superadmin_cash_ledger (
                batch_key, ledger_date, admin_username, admin_display_name,
                total_transactions, total_amount,
                requested_at, requested_by, status
            ) VALUES (
                :batch_key, :ledger_date, :admin_username, :admin_display_name,
                :total_transactions, :total_amount,
                :requested_at, :requested_by, :status
            )');
            $insertStmt->execute([
                ':batch_key' => $batchKey,
                ':ledger_date' => $ledgerDate,
                ':admin_username' => $adminUsername,
                ':admin_display_name' => $adminDisplayName,
                ':total_transactions' => $totalTransactions,
                ':total_amount' => $totalAmount,
                ':requested_at' => $now,
                ':requested_by' => $adminUsername,
                ':status' => 'pending',
            ]);

            $updateLedgerStmt = $db->prepare('UPDATE admin_cash_ledger
                SET status = "handover_requested",
                    handover_requested_at = :requested_at,
                    handover_batch_key = :batch_key
                WHERE ledger_date = :ledger_date
                  AND admin_username = :admin_username
                  AND status = "issued"');
            $updateLedgerStmt->execute([
                ':requested_at' => $now,
                ':batch_key' => $batchKey,
                ':ledger_date' => $ledgerDate,
                ':admin_username' => $adminUsername,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'ok' => false,
                'error' => 'DB_WRITE_FAILED',
                'message' => 'Unable to request cash handover.',
            ];
        }

        return [
            'ok' => true,
            'action' => 'admin_request_cash_handover',
            'batchKey' => $batchKey,
            'totalTransactions' => $totalTransactions,
            'totalAmount' => $totalAmount,
            'message' => 'Cash handover request submitted.',
        ];
    }

    public static function requestCashCancel(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'cashier')) {
            return [
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Cashier permission required.',
            ];
        }

        $transactionId = trim((string) ($payload['transactionId'] ?? $payload['transaction_id'] ?? ''));
        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($transactionId === '' || $reason === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'transactionId and reason are required.',
            ];
        }

        $db = self::db();
        $lookupStmt = $db->prepare('SELECT transaction_id, gateway, status, issued_by
            FROM event_transactions
            WHERE transaction_id = :transaction_id
            LIMIT 1');
        $lookupStmt->execute([':transaction_id' => $transactionId]);
        $tx = $lookupStmt->fetch();
        if (!$tx || strtolower((string) ($tx['gateway'] ?? '')) !== 'cash') {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Cash transaction not found.',
            ];
        }

        $adminUsername = (string) ($auth['user']['username'] ?? '');
        $isSuperadmin = strtolower((string) ($auth['user']['role'] ?? '')) === 'superadmin';
        if (!$isSuperadmin && strtolower((string) ($tx['issued_by'] ?? '')) !== strtolower($adminUsername)) {
            return [
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'You can request cancel only for your own cash transactions.',
            ];
        }

        $status = strtolower((string) ($tx['status'] ?? ''));
        if ($status === 'cancel_requested') {
            return [
                'ok' => true,
                'action' => 'admin_request_cash_cancel',
                'transactionId' => $transactionId,
                'message' => 'Cancel request is already pending.',
            ];
        }
        if ($status === 'cancelled') {
            return [
                'ok' => false,
                'error' => 'ALREADY_CANCELLED',
                'message' => 'Transaction is already cancelled.',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $db->beginTransaction();
        try {
            $updateTxStmt = $db->prepare('UPDATE event_transactions
                SET status = "cancel_requested",
                    cancel_requested_at = :cancel_requested_at,
                    cancel_request_by = :cancel_request_by,
                    cancel_request_reason = :cancel_request_reason
                WHERE transaction_id = :transaction_id');
            $updateTxStmt->execute([
                ':cancel_requested_at' => $now,
                ':cancel_request_by' => $adminUsername,
                ':cancel_request_reason' => $reason,
                ':transaction_id' => $transactionId,
            ]);

            $updateLedgerStmt = $db->prepare('UPDATE admin_cash_ledger
                SET status = "cancel_requested",
                    notes = CASE
                        WHEN notes = "" THEN :notes
                        ELSE CONCAT(notes, " | ", :notes)
                    END
                WHERE transaction_id = :transaction_id');
            $updateLedgerStmt->execute([
                ':notes' => 'Cancel requested: ' . $reason,
                ':transaction_id' => $transactionId,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'ok' => false,
                'error' => 'DB_WRITE_FAILED',
                'message' => 'Unable to request cancel.',
            ];
        }

        return [
            'ok' => true,
            'action' => 'admin_request_cash_cancel',
            'transactionId' => $transactionId,
            'message' => 'Cancel request submitted.',
        ];
    }

    public static function approveCashHandover(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'superadmin');
        if (!$auth['ok']) {
            return $auth;
        }

        $adminUsername = trim((string) ($payload['adminUsername'] ?? $payload['admin_username'] ?? ''));
        $ledgerDate = self::normalizeLedgerDate((string) ($payload['ledgerDate'] ?? ''));
        if ($adminUsername === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'adminUsername is required.',
            ];
        }

        $db = self::db();
        $batchStmt = $db->prepare('SELECT batch_key, total_amount, total_transactions
            FROM superadmin_cash_ledger
            WHERE admin_username = :admin_username
              AND ledger_date = :ledger_date
              AND status = "pending"
            ORDER BY id DESC
            LIMIT 1');
        $batchStmt->execute([
            ':admin_username' => $adminUsername,
            ':ledger_date' => $ledgerDate,
        ]);
        $batch = $batchStmt->fetch();
        if (!$batch) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'No pending handover found for this admin/date.',
            ];
        }

        $now = date('Y-m-d H:i:s');
        $approvedBy = (string) ($auth['user']['username'] ?? '');
        $db->beginTransaction();
        try {
            $approveBatchStmt = $db->prepare('UPDATE superadmin_cash_ledger
                SET status = "approved",
                    approved_at = :approved_at,
                    approved_by = :approved_by
                WHERE batch_key = :batch_key');
            $approveBatchStmt->execute([
                ':approved_at' => $now,
                ':approved_by' => $approvedBy,
                ':batch_key' => (string) $batch['batch_key'],
            ]);

            $approveLedgerStmt = $db->prepare('UPDATE admin_cash_ledger
                SET status = "handover_approved",
                    handover_approved_at = :approved_at,
                    handover_approved_by = :approved_by
                WHERE handover_batch_key = :batch_key
                  AND status = "handover_requested"');
            $approveLedgerStmt->execute([
                ':approved_at' => $now,
                ':approved_by' => $approvedBy,
                ':batch_key' => (string) $batch['batch_key'],
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'ok' => false,
                'error' => 'DB_WRITE_FAILED',
                'message' => 'Unable to approve handover.',
            ];
        }

        return [
            'ok' => true,
            'action' => 'superadmin_approve_cash_handover',
            'batchKey' => (string) $batch['batch_key'],
            'totalTransactions' => (int) ($batch['total_transactions'] ?? 0),
            'totalAmount' => (float) ($batch['total_amount'] ?? 0),
            'message' => 'Cash handover approved.',
        ];
    }

    public static function resolveCashCancel(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'superadmin');
        if (!$auth['ok']) {
            return $auth;
        }

        $transactionId = trim((string) ($payload['transactionId'] ?? $payload['transaction_id'] ?? ''));
        $decision = strtolower(trim((string) ($payload['decision'] ?? '')));
        $note = trim((string) ($payload['note'] ?? ''));
        if ($transactionId === '' || !in_array($decision, ['approve', 'reject'], true)) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'transactionId and decision (approve/reject) are required.',
            ];
        }

        $db = self::db();
        $lookupStmt = $db->prepare('SELECT transaction_id, status, gateway
            FROM event_transactions
            WHERE transaction_id = :transaction_id
            LIMIT 1');
        $lookupStmt->execute([':transaction_id' => $transactionId]);
        $tx = $lookupStmt->fetch();
        if (!$tx || strtolower((string) ($tx['gateway'] ?? '')) !== 'cash') {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Cash transaction not found.',
            ];
        }

        if (strtolower((string) ($tx['status'] ?? '')) !== 'cancel_requested') {
            return [
                'ok' => false,
                'error' => 'INVALID_STATUS',
                'message' => 'Transaction is not pending cancel review.',
            ];
        }

        $reviewer = (string) ($auth['user']['username'] ?? '');
        $now = date('Y-m-d H:i:s');
        $nextStatus = $decision === 'approve' ? 'cancelled' : 'paid';
        $ledgerStatus = $decision === 'approve' ? 'cancelled' : 'issued';

        $db->beginTransaction();
        try {
            $updateTxStmt = $db->prepare('UPDATE event_transactions
                SET status = :status,
                    cancelled_at = :cancelled_at,
                    cancel_reviewed_by = :cancel_reviewed_by,
                    cancel_reviewed_at = :cancel_reviewed_at,
                    cancel_decision = :cancel_decision,
                    cancel_request_reason = CASE
                        WHEN :note = "" THEN cancel_request_reason
                        WHEN cancel_request_reason = "" THEN :note
                        ELSE CONCAT(cancel_request_reason, " | ", :note)
                    END
                WHERE transaction_id = :transaction_id');
            $updateTxStmt->execute([
                ':status' => $nextStatus,
                ':cancelled_at' => $decision === 'approve' ? $now : null,
                ':cancel_reviewed_by' => $reviewer,
                ':cancel_reviewed_at' => $now,
                ':cancel_decision' => $decision,
                ':note' => $note,
                ':transaction_id' => $transactionId,
            ]);

            $updateLedgerStmt = $db->prepare('UPDATE admin_cash_ledger
                SET status = :status,
                    notes = CASE
                        WHEN :note = "" THEN notes
                        WHEN notes = "" THEN :note
                        ELSE CONCAT(notes, " | ", :note)
                    END
                WHERE transaction_id = :transaction_id');
            $updateLedgerStmt->execute([
                ':status' => $ledgerStatus,
                ':note' => ($decision === 'approve' ? 'Cancel approved' : 'Cancel rejected') . ($note !== '' ? ': ' . $note : ''),
                ':transaction_id' => $transactionId,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return [
                'ok' => false,
                'error' => 'DB_WRITE_FAILED',
                'message' => 'Unable to review cancel request.',
            ];
        }

        return [
            'ok' => true,
            'action' => 'superadmin_resolve_cash_cancel',
            'transactionId' => $transactionId,
            'decision' => $decision,
            'message' => $decision === 'approve' ? 'Cancel approved.' : 'Cancel rejected.',
        ];
    }

    public static function adminCashSummary(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'cashier')) {
            return [
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Cashier permission required.',
            ];
        }

        $ledgerDate = self::normalizeLedgerDate((string) ($payload['ledgerDate'] ?? ''));
        $adminUsername = (string) ($auth['user']['username'] ?? '');
        $db = self::db();

        $totalsStmt = $db->prepare('SELECT
            COALESCE(SUM(amount), 0) AS total_amount,
            COALESCE(SUM(CASE WHEN status = "issued" THEN amount ELSE 0 END), 0) AS pending_amount,
            COALESCE(SUM(CASE WHEN status = "handover_requested" THEN amount ELSE 0 END), 0) AS requested_amount,
            COALESCE(SUM(CASE WHEN status = "handover_approved" THEN amount ELSE 0 END), 0) AS approved_amount
            FROM admin_cash_ledger
            WHERE ledger_date = :ledger_date
              AND admin_username = :admin_username');
        $totalsStmt->execute([
            ':ledger_date' => $ledgerDate,
            ':admin_username' => $adminUsername,
        ]);
        $totals = $totalsStmt->fetch() ?: [];

        $txStmt = $db->prepare('SELECT
            transaction_id,
            event_title,
            customer_name,
            customer_phone,
            amount,
            status,
            created_at
            FROM event_transactions
            WHERE issued_by = :issued_by
              AND DATE(created_at) = :ledger_date
              AND gateway = "cash"
            ORDER BY id DESC
            LIMIT 100');
        $txStmt->execute([
            ':issued_by' => $adminUsername,
            ':ledger_date' => $ledgerDate,
        ]);
        $recentTransactions = [];
        foreach ($txStmt->fetchAll() ?: [] as $row) {
            $recentTransactions[] = [
                'transactionId' => (string) ($row['transaction_id'] ?? ''),
                'eventTitle' => (string) ($row['event_title'] ?? ''),
                'customerName' => (string) ($row['customer_name'] ?? ''),
                'customerPhone' => (string) ($row['customer_phone'] ?? ''),
                'amount' => (float) ($row['amount'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'createdAt' => (string) ($row['created_at'] ?? ''),
            ];
        }

        $handoverStmt = $db->prepare('SELECT
            ledger_date,
            batch_key,
            total_transactions,
            total_amount,
            status,
            approved_by
            FROM superadmin_cash_ledger
            WHERE admin_username = :admin_username
            ORDER BY id DESC
            LIMIT 30');
        $handoverStmt->execute([':admin_username' => $adminUsername]);
        $handoverHistory = [];
        foreach ($handoverStmt->fetchAll() ?: [] as $row) {
            $handoverHistory[] = [
                'ledgerDate' => (string) ($row['ledger_date'] ?? ''),
                'batchKey' => (string) ($row['batch_key'] ?? ''),
                'totalTransactions' => (int) ($row['total_transactions'] ?? 0),
                'totalAmount' => (float) ($row['total_amount'] ?? 0),
                'status' => (string) ($row['status'] ?? ''),
                'approvedBy' => (string) ($row['approved_by'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'action' => 'admin_cash_summary',
            'summary' => [
                'ledgerDate' => $ledgerDate,
                'totals' => [
                    'totalAmount' => (float) ($totals['total_amount'] ?? 0),
                    'pendingAmount' => (float) ($totals['pending_amount'] ?? 0),
                    'requestedAmount' => (float) ($totals['requested_amount'] ?? 0),
                    'approvedAmount' => (float) ($totals['approved_amount'] ?? 0),
                ],
                'recentTransactions' => $recentTransactions,
                'handoverHistory' => $handoverHistory,
            ],
        ];
    }

    public static function superadminCashDashboard(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'superadmin');
        if (!$auth['ok']) {
            return $auth;
        }

        $ledgerDate = self::normalizeLedgerDate((string) ($payload['ledgerDate'] ?? ''));
        $db = self::db();

        $handoverStmt = $db->prepare('SELECT
            ledger_date,
            admin_username,
            admin_display_name,
            total_transactions,
            total_amount,
            requested_at
            FROM superadmin_cash_ledger
            WHERE status = "pending"
              AND ledger_date = :ledger_date
            ORDER BY requested_at ASC');
        $handoverStmt->execute([':ledger_date' => $ledgerDate]);
        $pendingHandovers = [];
        foreach ($handoverStmt->fetchAll() ?: [] as $row) {
            $pendingHandovers[] = [
                'ledgerDate' => (string) ($row['ledger_date'] ?? ''),
                'adminUsername' => (string) ($row['admin_username'] ?? ''),
                'adminDisplayName' => (string) ($row['admin_display_name'] ?? ''),
                'totalTransactions' => (int) ($row['total_transactions'] ?? 0),
                'totalAmount' => (float) ($row['total_amount'] ?? 0),
                'requestedAt' => (string) ($row['requested_at'] ?? ''),
            ];
        }

        $cancelStmt = $db->prepare('SELECT
            transaction_id,
            cancel_request_by,
            issued_by,
            event_title,
            amount,
            cancel_request_reason
            FROM event_transactions
            WHERE gateway = "cash"
              AND status = "cancel_requested"
            ORDER BY cancel_requested_at ASC
            LIMIT 100');
        $cancelStmt->execute();
        $cancelRequests = [];
        foreach ($cancelStmt->fetchAll() ?: [] as $row) {
            $cancelRequests[] = [
                'transactionId' => (string) ($row['transaction_id'] ?? ''),
                'cancelRequestBy' => (string) ($row['cancel_request_by'] ?? ''),
                'issuedBy' => (string) ($row['issued_by'] ?? ''),
                'eventTitle' => (string) ($row['event_title'] ?? ''),
                'amount' => (float) ($row['amount'] ?? 0),
                'cancelRequestReason' => (string) ($row['cancel_request_reason'] ?? ''),
            ];
        }

        $approvedStmt = $db->prepare('SELECT
            ledger_date,
            admin_username,
            admin_display_name,
            total_transactions,
            total_amount,
            approved_at
            FROM superadmin_cash_ledger
            WHERE status = "approved"
            ORDER BY approved_at DESC
            LIMIT 30');
        $approvedStmt->execute();
        $recentApprovals = [];
        foreach ($approvedStmt->fetchAll() ?: [] as $row) {
            $recentApprovals[] = [
                'ledgerDate' => (string) ($row['ledger_date'] ?? ''),
                'adminUsername' => (string) ($row['admin_username'] ?? ''),
                'adminDisplayName' => (string) ($row['admin_display_name'] ?? ''),
                'totalTransactions' => (int) ($row['total_transactions'] ?? 0),
                'totalAmount' => (float) ($row['total_amount'] ?? 0),
                'approvedAt' => (string) ($row['approved_at'] ?? ''),
            ];
        }

        return [
            'ok' => true,
            'action' => 'superadmin_cash_dashboard',
            'dashboard' => [
                'ledgerDate' => $ledgerDate,
                'pendingHandovers' => $pendingHandovers,
                'cancelRequests' => $cancelRequests,
                'recentApprovals' => $recentApprovals,
            ],
        ];
    }

    private static function db(): PDO
    {
        return Database::connection();
    }

    private static function normalizeLedgerDate(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m-d');
        }

        $ts = strtotime($value);
        if ($ts === false) {
            return date('Y-m-d');
        }

        return date('Y-m-d', $ts);
    }

    private static function generateId(string $prefix): string
    {
        return $prefix . '-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    private static function parseAttendeeNames(string $input): array
    {
        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $name = trim($part);
            if ($name !== '') {
                $out[] = $name;
            }
        }

        return $out;
    }

    private static function buildQrPayload(string $transactionId, string $eventId, string $paymentId): string
    {
        $secret = trim((string) ($_ENV['EVENT_QR_SIGNING_SECRET'] ?? $_ENV['ADMIN_PANEL_PASSCODE'] ?? ''));
        if ($secret === '') {
            return '';
        }

        $raw = $transactionId . '|' . $eventId . '|' . $paymentId . '|';
        $sig = hash_hmac('sha256', $raw, $secret);
        $params = [
            'tx' => $transactionId,
            'eventId' => $eventId,
            'pid' => $paymentId,
            'sig' => $sig,
        ];

        return http_build_query($params);
    }

    private static function buildQrUrl(string $payload): string
    {
        $base = trim((string) ($_ENV['EVENT_QR_VERIFY_BASE_URL'] ?? $_ENV['EVENT_QR_REDIRECT_BASE_URL'] ?? ''));
        if ($base === '' || $payload === '') {
            return '';
        }

        return $base . (strpos($base, '?') === false ? '?' : '&') . $payload;
    }
}
