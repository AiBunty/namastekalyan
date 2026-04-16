<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Config\Database;
use NK\Middleware\AuthMiddleware;
use NK\Services\EventService;
use PDO;

class EventController
{
    public static function eventsList(array $body, array $query): array
    {
        $service = new EventService();
        return $service->eventsList($query);
    }

    public static function eventPopup(array $body, array $query): array
    {
        $service = new EventService();
        return $service->eventPopup();
    }

    public static function eventDetail(array $body, array $query): array
    {
        $service = new EventService();
        return $service->eventDetail($query);
    }

    public static function adminListEvents(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminListEvents($payload);
    }

    public static function adminCreateEvent(array $body, array $query): array
    {
        $service = new EventService();
        return $service->adminCreateEvent($body);
    }

    public static function adminUpdateEvent(array $body, array $query): array
    {
        $service = new EventService();
        return $service->adminUpdateEvent($body);
    }

    public static function adminToggleEvent(array $body, array $query): array
    {
        $service = new EventService();
        return $service->adminToggleEvent($body);
    }

    // Stubs for phase-2 migration (booking/payments, QR, reports)
    public static function registerFreeEvent(array $body, array $query): array
    {
        $service = new EventService();
        return $service->registerFreeEvent($body);
    }

    public static function createEventOrder(array $body, array $query): array
    {
        $service = new EventService();
        return $service->createEventOrder($body);
    }

    public static function confirmEventPayment(array $body, array $query): array
    {
        $service = new EventService();
        return $service->confirmEventPayment($body);
    }

    public static function resendEventConfirmation(array $body, array $query): array
    {
        $service = new EventService();
        return $service->resendEventConfirmation($body);
    }

    public static function requestEventCancellation(array $body, array $query): array
    {
        $service = new EventService();
        return $service->requestEventCancellation($body);
    }

    public static function verifyEventQr(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->verifyEventQr($payload);
    }

    public static function adminPreviewEventQr(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminPreviewEventQr($payload);
    }

    public static function adminBatchCheckin(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminBatchCheckin($payload);
    }

    public static function eventGuestReport(array $body, array $query): array
    {
        $payload = array_merge($query, $body);
        $auth = AuthMiddleware::authorize($payload, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventGuests')) {
            return [
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Event guests permission required.',
            ];
        }

        $selectedEventId = trim((string) ($payload['eventId'] ?? $payload['event_id'] ?? ''));
        $db = self::db();

        $summarySql = 'SELECT
                e.event_id,
                e.title,
                COALESCE(SUM(t.qty), 0) AS guests,
                COALESCE(SUM(CASE WHEN t.gateway = "free" THEN 1 ELSE 0 END), 0) AS free_registrations,
                COALESCE(SUM(CASE WHEN t.gateway IN ("razorpay", "cash") THEN 1 ELSE 0 END), 0) AS paid_registrations
            FROM events e
            LEFT JOIN event_transactions t ON t.event_id = e.event_id
            GROUP BY e.event_id, e.title
            ORDER BY e.start_date DESC, e.start_time DESC, e.id DESC';
        $summaryRows = $db->query($summarySql)->fetchAll() ?: [];

        $eventSummary = [];
        foreach ($summaryRows as $row) {
            $eventSummary[] = [
                'eventId' => (string) ($row['event_id'] ?? ''),
                'eventTitle' => (string) ($row['title'] ?? ''),
                'guests' => (int) ($row['guests'] ?? 0),
                'freeRegistrations' => (int) ($row['free_registrations'] ?? 0),
                'paidRegistrations' => (int) ($row['paid_registrations'] ?? 0),
            ];
        }

        $where = '';
        $params = [];
        if ($selectedEventId !== '') {
            $where = ' WHERE t.event_id = :event_id ';
            $params[':event_id'] = $selectedEventId;
        }

        $guestSql = 'SELECT
                t.event_id,
                t.event_title,
                t.transaction_id,
                t.customer_name,
                t.customer_email,
                t.customer_phone,
                t.qty,
                t.gateway,
                t.status,
                t.email_status,
                t.created_at,
                t.checked_in_at,
                t.checked_in_count,
                t.attendee_details,
                t.amount,
                t.currency,
                t.order_id,
                t.payment_id,
                t.refund_status,
                t.paid_at
            FROM event_transactions t' . $where . 'ORDER BY t.id DESC';
        $guestStmt = $db->prepare($guestSql);
        $guestStmt->execute($params);
        $rows = $guestStmt->fetchAll() ?: [];

        $guests = [];
        $reconEntries = [];
        $totals = [
            'registrations' => 0,
            'guests' => 0,
            'freeRegistrations' => 0,
            'paidRegistrations' => 0,
            'checkedInGuests' => 0,
            'razorpayCollectedAmount' => 0.0,
            'cashCollectedAmount' => 0.0,
            'razorpayPendingAmount' => 0.0,
        ];

        foreach ($rows as $row) {
            $gateway = strtolower((string) ($row['gateway'] ?? ''));
            $status = strtolower((string) ($row['status'] ?? ''));
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $amount = (float) ($row['amount'] ?? 0);

            $totals['registrations'] += 1;
            $totals['guests'] += $qty;
            $totals['checkedInGuests'] += max(0, (int) ($row['checked_in_count'] ?? 0));

            if ($gateway === 'free') {
                $totals['freeRegistrations'] += 1;
            } elseif ($gateway === 'razorpay' || $gateway === 'cash') {
                $totals['paidRegistrations'] += 1;
            }

            if ($gateway === 'razorpay') {
                if (in_array($status, ['paid', 'checked_in'], true)) {
                    $totals['razorpayCollectedAmount'] += $amount;
                } elseif ($status !== 'cancelled') {
                    $totals['razorpayPendingAmount'] += $amount;
                }

                $reconEntries[] = [
                    'eventTitle' => (string) ($row['event_title'] ?? ''),
                    'customerName' => (string) ($row['customer_name'] ?? ''),
                    'transactionId' => (string) ($row['transaction_id'] ?? ''),
                    'orderId' => (string) ($row['order_id'] ?? ''),
                    'paymentId' => (string) ($row['payment_id'] ?? ''),
                    'amount' => $amount,
                    'currency' => (string) ($row['currency'] ?? 'INR'),
                    'status' => (string) ($row['status'] ?? ''),
                    'refundStatus' => (string) ($row['refund_status'] ?? ''),
                    'createdAt' => (string) ($row['created_at'] ?? ''),
                    'confirmedAt' => (string) ($row['paid_at'] ?? ''),
                ];
            }

            if ($gateway === 'cash' && in_array($status, ['paid', 'checked_in', 'handover_requested', 'handover_approved'], true)) {
                $totals['cashCollectedAmount'] += $amount;
            }

            $guests[] = [
                'eventId' => (string) ($row['event_id'] ?? ''),
                'eventTitle' => (string) ($row['event_title'] ?? ''),
                'transactionId' => (string) ($row['transaction_id'] ?? ''),
                'customerName' => (string) ($row['customer_name'] ?? ''),
                'customerEmail' => (string) ($row['customer_email'] ?? ''),
                'customerPhone' => (string) ($row['customer_phone'] ?? ''),
                'bookingType' => $gateway === 'free' ? 'Free' : 'Paid',
                'collectionType' => self::collectionTypeLabel($gateway),
                'qty' => $qty,
                'attendeeDetails' => self::normalizeAttendees($row['attendee_details'] ?? null),
                'status' => (string) ($row['status'] ?? ''),
                'emailStatus' => (string) ($row['email_status'] ?? ''),
                'createdAt' => (string) ($row['created_at'] ?? ''),
                'checkedInAt' => (string) ($row['checked_in_at'] ?? ''),
            ];
        }

        $report = [
            'selectedEventId' => $selectedEventId,
            'eventSummary' => $eventSummary,
            'totals' => $totals,
            'guests' => $guests,
            'razorpayReconciliation' => [
                'totals' => [
                    'collectedRegistrations' => count(array_filter($reconEntries, static fn(array $item): bool => in_array(strtolower((string) ($item['status'] ?? '')), ['paid', 'checked_in'], true))),
                    'collectedAmount' => $totals['razorpayCollectedAmount'],
                    'pendingAmount' => $totals['razorpayPendingAmount'],
                    'cancelledRegistrations' => count(array_filter($reconEntries, static fn(array $item): bool => strtolower((string) ($item['status'] ?? '')) === 'cancelled')),
                ],
                'entries' => $reconEntries,
            ],
        ];

        return [
            'ok' => true,
            'action' => 'event_guest_report',
            'report' => $report,
        ];
    }

    public static function eventTransactionsReport(array $body, array $query): array
    {
        $guestReport = self::eventGuestReport($body, $query);
        if (empty($guestReport['ok'])) {
            return $guestReport;
        }

        return [
            'ok' => true,
            'action' => 'event_transactions_report',
            'report' => $guestReport['report'],
            'items' => $guestReport['report']['razorpayReconciliation']['entries'] ?? [],
        ];
    }

    private static function db(): PDO
    {
        return Database::connection();
    }

    private static function collectionTypeLabel(string $gateway): string
    {
        $gateway = strtolower(trim($gateway));
        if ($gateway === 'razorpay') {
            return 'Razorpay';
        }
        if ($gateway === 'cash') {
            return 'Cash';
        }
        return 'Free';
    }

    private static function normalizeAttendees($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $names = [];
        foreach ($decoded as $item) {
            if (is_string($item)) {
                $name = trim($item);
                if ($name !== '') {
                    $names[] = $name;
                }
                continue;
            }

            if (is_array($item)) {
                $name = trim((string) ($item['name'] ?? $item['guestName'] ?? $item['fullName'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }
}
