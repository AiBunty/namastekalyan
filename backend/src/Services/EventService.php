<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Middleware\AuthMiddleware;
use NK\Models\EventItem;
use NK\Repositories\EventRepository;
use NK\Repositories\EventTransactionRepository;
use NK\Support\Validator;

class EventService
{
    private EventRepository $repo;
    private EventTransactionRepository $transactions;
    private RazorpayService $razorpay;

    public function __construct()
    {
        $this->repo = new EventRepository();
        $this->transactions = new EventTransactionRepository();
        $this->razorpay = new RazorpayService();
    }

    public function registerFreeEvent(array $data): array
    {
        $prepared = $this->prepareCustomerRegistration($data, false);
        if (!$prepared['ok']) {
            return $prepared;
        }

        /** @var array $event */
        $event = $prepared['event'];
        $customer = $prepared['customer'];
        $attendees = $prepared['attendees'];

        $transactionId = $this->generateTransactionId('REG');
        $qrPayload = $this->buildQrPayload($transactionId);
        $qrUrl = $this->buildQrUrl($qrPayload);

        $this->transactions->create([
            'transaction_id'    => $transactionId,
            'event_id'          => (string) $event['event_id'],
            'event_title'       => (string) ($event['title'] ?? ''),
            'customer_name'     => $customer['name'],
            'customer_email'    => $customer['email'],
            'customer_phone'    => $customer['phone'],
            'qty'               => $customer['qty'],
            'amount'            => 0,
            'currency'          => (string) ($event['currency'] ?? 'INR'),
            'gateway'           => 'free',
            'status'            => 'free_confirmed',
            'qr_url'            => $qrUrl,
            'qr_payload'        => $qrPayload,
            'paid_at'           => date('Y-m-d H:i:s'),
            'attendee_details'  => $attendees,
            'guest_passes_json' => $attendees,
        ]);

        $this->transactions->setEmailSent($transactionId, false);

        return [
            'ok'               => true,
            'action'           => 'register_free_event',
            'transactionId'    => $transactionId,
            'guestPassCount'   => count($attendees),
            'qrUrl'            => $qrUrl,
            'verificationUrl'  => $this->buildVerificationUrl($transactionId),
            'emailSent'        => false,
            'message'          => 'Registration confirmed.',
        ];
    }

    public function createEventOrder(array $data): array
    {
        $prepared = $this->prepareCustomerRegistration($data, true);
        if (!$prepared['ok']) {
            return $prepared;
        }

        /** @var array $event */
        $event = $prepared['event'];
        $customer = $prepared['customer'];
        $attendees = $prepared['attendees'];

        if (!$this->razorpay->isConfigured()) {
            return [
                'ok' => false,
                'error' => 'PAYMENT_NOT_CONFIGURED',
                'message' => 'Payment gateway is not configured.',
            ];
        }

        $amount = (float) ($event['ticket_price'] ?? 0);
        if ($amount <= 0) {
            return [
                'ok' => false,
                'error' => 'INVALID_EVENT_PRICING',
                'message' => 'This event is not configured for paid booking.',
            ];
        }

        $amountTotal = round($amount * $customer['qty'], 2);
        $amountInPaise = (int) round($amountTotal * 100);

        $transactionId = $this->generateTransactionId('TXN');
        $qrPayload = $this->buildQrPayload($transactionId);
        $qrUrl = $this->buildQrUrl($qrPayload);

        $this->transactions->create([
            'transaction_id'    => $transactionId,
            'event_id'          => (string) $event['event_id'],
            'event_title'       => (string) ($event['title'] ?? ''),
            'customer_name'     => $customer['name'],
            'customer_email'    => $customer['email'],
            'customer_phone'    => $customer['phone'],
            'qty'               => $customer['qty'],
            'amount'            => $amountTotal,
            'currency'          => (string) ($event['currency'] ?? 'INR'),
            'gateway'           => 'razorpay',
            'status'            => 'pending',
            'qr_url'            => $qrUrl,
            'qr_payload'        => $qrPayload,
            'attendee_details'  => $attendees,
            'guest_passes_json' => $attendees,
        ]);

        $receipt = substr($transactionId, 0, 40);
        $orderResp = $this->razorpay->createOrder(
            $amountInPaise,
            $receipt,
            (string) ($event['currency'] ?? 'INR'),
            [
                'transactionId' => $transactionId,
                'eventId' => (string) $event['event_id'],
            ]
        );

        if (!$orderResp['ok']) {
            $this->transactions->setStatus($transactionId, 'order_failed');
            return [
                'ok' => false,
                'error' => 'ORDER_CREATE_FAILED',
                'message' => (string) ($orderResp['message'] ?? 'Unable to create payment order.'),
            ];
        }

        $order = $orderResp['order'];
        $this->transactions->setOrderId($transactionId, (string) ($order['id'] ?? ''));

        return [
            'ok'             => true,
            'action'         => 'create_event_order',
            'transactionId'  => $transactionId,
            'order'          => $order,
            'amountInPaise'  => $amountInPaise,
            'currency'       => (string) ($event['currency'] ?? 'INR'),
            'keyId'          => $this->razorpay->getKeyId(),
            'guestPassCount' => count($attendees),
            'policy'         => (string) ($event['refund_policy'] ?? ($_ENV['EVENT_NO_REFUND_POLICY'] ?? 'No refund once pass is purchased.')),
        ];
    }

    public function confirmEventPayment(array $data): array
    {
        $orderId = trim((string) ($data['orderId'] ?? $data['order_id'] ?? ''));
        $paymentId = trim((string) ($data['paymentId'] ?? $data['payment_id'] ?? $data['razorpay_payment_id'] ?? ''));
        $signature = trim((string) ($data['signature'] ?? $data['razorpay_signature'] ?? ''));

        if ($orderId === '' || $paymentId === '' || $signature === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'orderId, paymentId and signature are required.',
            ];
        }

        $valid = $this->razorpay->verifyPaymentSignature($orderId, $paymentId, $signature);
        if (!$valid) {
            return [
                'ok' => false,
                'error' => 'SIGNATURE_MISMATCH',
                'message' => 'Payment signature verification failed.',
            ];
        }

        $txn = $this->transactions->findByOrderId($orderId);
        if (!$txn) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Transaction not found for order.',
            ];
        }

        $status = (string) ($txn['status'] ?? '');
        if ($status !== 'paid') {
            $this->transactions->markPaid((string) $txn['transaction_id'], $paymentId, 'paid');
        }

        return [
            'ok'             => true,
            'action'         => 'confirm_event_payment',
            'transactionId'  => (string) $txn['transaction_id'],
            'guestPassCount' => (int) ($txn['qty'] ?? 1),
            'qrUrl'          => (string) ($txn['qr_url'] ?? ''),
            'verificationUrl'=> $this->buildVerificationUrl((string) $txn['transaction_id']),
            'policy'         => $_ENV['EVENT_NO_REFUND_POLICY'] ?? 'No refund once pass is purchased.',
        ];
    }

    public function resendEventConfirmation(array $data): array
    {
        $transactionId = trim((string) ($data['transactionId'] ?? $data['transaction_id'] ?? ''));
        if ($transactionId === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'transactionId is required.',
            ];
        }

        $txn = $this->transactions->findByTransactionId($transactionId);
        if (!$txn) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Transaction not found.',
            ];
        }

        // Hook point for SMTP mailer integration. For now mark as sent-requested.
        $this->transactions->setEmailSent($transactionId, false);

        return [
            'ok' => true,
            'action' => 'resend_event_confirmation',
            'message' => 'Resend request accepted.',
            'transactionId' => $transactionId,
        ];
    }

    public function requestEventCancellation(array $data): array
    {
        $transactionId = trim((string) ($data['transactionId'] ?? $data['transaction_id'] ?? ''));
        if ($transactionId === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'transactionId is required.',
            ];
        }

        $txn = $this->transactions->findByTransactionId($transactionId);
        if (!$txn) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'Transaction not found.',
            ];
        }

        $this->transactions->setStatus($transactionId, 'cancel_requested');

        return [
            'ok' => true,
            'action' => 'request_event_cancellation',
            'message' => 'Cancellation request captured.',
            'policy' => $_ENV['EVENT_NO_REFUND_POLICY'] ?? 'No refund once pass is purchased.',
        ];
    }

    public function verifyEventQr(array $data): array
    {
        $singleScan = [
            'scanText' => (string) ($data['scanText'] ?? $data['rawScan'] ?? $data['qrText'] ?? ''),
            'tx' => (string) ($data['tx'] ?? $data['transactionId'] ?? ''),
            'eventId' => (string) ($data['eventId'] ?? ''),
            'paymentId' => (string) ($data['paymentId'] ?? $data['pid'] ?? ''),
            'guestId' => (string) ($data['guestId'] ?? $data['gid'] ?? ''),
            'sig' => (string) ($data['sig'] ?? $data['signature'] ?? ''),
            'admittedCount' => (int) ($data['admittedCount'] ?? 1),
        ];

        $batch = $this->adminBatchCheckin(array_merge($data, ['scans' => [$singleScan]]));
        if (empty($batch['ok'])) {
            return $batch;
        }

        $first = (is_array($batch['results'] ?? null) && isset($batch['results'][0]))
            ? $batch['results'][0]
            : ['ok' => false, 'error' => 'EMPTY_RESULT', 'message' => 'No verification result available.'];

        $first['action'] = 'verify_event_qr';
        return $first;
    }

    public function adminPreviewEventQr(array $data): array
    {
        $normalized = $this->normalizeBatchScan($data);
        if ($normalized['transactionId'] === '' || $normalized['eventId'] === '' || $normalized['paymentId'] === '' || $normalized['signature'] === '') {
            return ['ok' => false, 'error' => 'INVALID_QR', 'message' => 'Incomplete QR data.'];
        }

        $secret = trim((string) ($_ENV['EVENT_QR_SIGNING_SECRET'] ?? $_ENV['ADMIN_PANEL_PASSCODE'] ?? ''));
        if ($secret === '') {
            return ['ok' => false, 'error' => 'SIGNING_SECRET_MISSING', 'message' => 'EVENT_QR_SIGNING_SECRET is not configured.'];
        }

        $raw = $normalized['transactionId'] . '|' . $normalized['eventId'] . '|' . $normalized['paymentId'] . '|' . $normalized['guestId'];
        $expected = hash_hmac('sha256', $raw, $secret);
        if (!hash_equals(strtolower($expected), strtolower($normalized['signature']))) {
            return ['ok' => false, 'error' => 'TAMPERED_QR', 'message' => 'QR signature validation failed.'];
        }

        $txn = $this->transactions->findByTransactionId($normalized['transactionId']);
        if (!$txn) {
            return ['ok' => false, 'error' => 'TX_NOT_FOUND', 'message' => 'Transaction not found.'];
        }

        return [
            'ok' => true,
            'action' => 'admin_preview_event_qr',
            'previewOnly' => true,
            'transactionId' => $normalized['transactionId'],
            'eventId' => (string) ($txn['event_id'] ?? ''),
            'eventTitle' => (string) ($txn['event_title'] ?? ''),
            'customerName' => (string) ($txn['customer_name'] ?? ''),
            'status' => (string) ($txn['status'] ?? ''),
            'checkInStatus' => (string) ($txn['checkin_status'] ?? 'pending'),
            'checkedInCount' => (int) ($txn['checked_in_count'] ?? 0),
            'qty' => max(1, (int) ($txn['qty'] ?? 1)),
            'message' => 'Ticket is ready for entry confirmation.',
            'duplicateKey' => $normalized['guestId'] !== ''
                ? ($normalized['transactionId'] . '::' . $normalized['guestId'])
                : $normalized['transactionId'],
        ];
    }

    public function adminBatchCheckin(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventScanner')) {
            return [
                'ok' => false,
                'error' => 'FORBIDDEN',
                'message' => 'Event scanner permission required.',
            ];
        }

        $scans = $data['scans'] ?? [];
        if (!is_array($scans) || count($scans) === 0) {
            return [
                'ok' => false,
                'error' => 'SCANS_REQUIRED',
                'message' => 'At least one QR scan is required.',
            ];
        }

        $verifiedBy = trim((string) (($auth['user']['username'] ?? '') ?: 'staff'));
        $secret = trim((string) ($_ENV['EVENT_QR_SIGNING_SECRET'] ?? $_ENV['ADMIN_PANEL_PASSCODE'] ?? ''));
        if ($secret === '') {
            return [
                'ok' => false,
                'error' => 'SIGNING_SECRET_MISSING',
                'message' => 'EVENT_QR_SIGNING_SECRET is not configured.',
            ];
        }

        $seen = [];
        $results = [];

        foreach ($scans as $scan) {
            $requestData = is_array($scan) ? $scan : ['scanText' => (string) $scan];
            $normalized = $this->normalizeBatchScan($requestData);

            if ($normalized['transactionId'] === '' || $normalized['eventId'] === '' || $normalized['paymentId'] === '' || $normalized['signature'] === '') {
                $results[] = [
                    'ok' => false,
                    'error' => 'INVALID_QR',
                    'message' => 'Incomplete QR data.',
                ];
                continue;
            }

            $duplicateKey = $normalized['guestId'] !== ''
                ? ($normalized['transactionId'] . '::' . $normalized['guestId'])
                : $normalized['transactionId'];
            if (isset($seen[$duplicateKey])) {
                $results[] = [
                    'ok' => false,
                    'error' => 'DUPLICATE_SCAN_IN_BATCH',
                    'message' => 'Same guest/ticket was scanned multiple times in this batch.',
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                ];
                continue;
            }
            $seen[$duplicateKey] = true;

            $signingRaw = $normalized['transactionId'] . '|' . $normalized['eventId'] . '|' . $normalized['paymentId'] . '|' . $normalized['guestId'];
            $expected = hash_hmac('sha256', $signingRaw, $secret);
            if (!hash_equals(strtolower($expected), strtolower($normalized['signature']))) {
                $results[] = [
                    'ok' => false,
                    'error' => 'TAMPERED_QR',
                    'message' => 'QR signature validation failed.',
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                ];
                continue;
            }

            $txn = $this->transactions->findByTransactionId($normalized['transactionId']);
            if (!$txn) {
                $results[] = [
                    'ok' => false,
                    'error' => 'TX_NOT_FOUND',
                    'message' => 'Transaction not found.',
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                ];
                continue;
            }

            $txEventId = trim((string) ($txn['event_id'] ?? ''));
            $txPaymentId = trim((string) ($txn['payment_id'] ?? ''));
            if ($txEventId !== $normalized['eventId'] || $txPaymentId !== $normalized['paymentId']) {
                $results[] = [
                    'ok' => false,
                    'error' => 'QR_TX_MISMATCH',
                    'message' => 'QR does not match transaction data.',
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                ];
                continue;
            }

            $status = trim((string) ($txn['status'] ?? ''));
            $statusKey = strtolower($status);
            if (!in_array($statusKey, ['paid', 'free_confirmed', 'checked_in', 'checked_in_free'], true)) {
                $results[] = [
                    'ok' => false,
                    'error' => 'INVALID_STATUS',
                    'message' => 'Ticket is not valid for check-in. Current status: ' . $status,
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                ];
                continue;
            }

            $qty = max(1, (int) ($txn['qty'] ?? 1));
            $checkedInCount = min($qty, max(0, (int) ($txn['checked_in_count'] ?? 0)));
            $remaining = max(0, $qty - $checkedInCount);
            $admit = max(1, (int) ($requestData['admittedCount'] ?? 1));

            if ($remaining <= 0) {
                $results[] = [
                    'ok' => false,
                    'error' => 'ALREADY_USED',
                    'message' => 'All allowed entries for this QR are already used.',
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                    'remainingEntries' => 0,
                ];
                continue;
            }

            if ($admit > $remaining) {
                $results[] = [
                    'ok' => false,
                    'error' => 'ADMISSION_EXCEEDS_REMAINING',
                    'message' => 'Only ' . $remaining . ' entr' . ($remaining === 1 ? 'y is' : 'ies are') . ' remaining for this QR.',
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                    'remainingEntries' => $remaining,
                ];
                continue;
            }

            $nextCheckedIn = min($qty, $checkedInCount + $admit);
            $isFree = strtolower((string) ($txn['gateway'] ?? '')) === 'free' || $statusKey === 'free_confirmed';
            $fullyCheckedIn = $nextCheckedIn >= $qty;
            $nextStatus = $fullyCheckedIn
                ? ($isFree ? 'checked_in_free' : 'checked_in')
                : $status;
            $nextCheckInStatus = $fullyCheckedIn ? 'checked_in' : 'pending';
            $checkedInAt = $fullyCheckedIn
                ? date('Y-m-d H:i:s')
                : (string) ($txn['checked_in_at'] ?? '');

            $this->transactions->updateCheckinState(
                $normalized['transactionId'],
                $nextStatus,
                $nextCheckInStatus,
                $nextCheckedIn,
                $verifiedBy,
                $checkedInAt === '' ? null : $checkedInAt,
                null
            );

            $remainingAfter = max(0, $qty - $nextCheckedIn);
            $results[] = [
                'ok' => true,
                'transactionId' => $normalized['transactionId'],
                'eventId' => $txEventId,
                'eventTitle' => (string) ($txn['event_title'] ?? ''),
                'customerName' => (string) ($txn['customer_name'] ?? ''),
                'bookingType' => $isFree ? 'Free' : 'Paid',
                'gateway' => (string) ($txn['gateway'] ?? ''),
                'status' => $nextStatus,
                'checkInStatus' => $nextCheckInStatus,
                'checkedInCount' => $nextCheckedIn,
                'remainingEntries' => $remainingAfter,
                'admittedCount' => $admit,
                'checkedInAt' => $checkedInAt,
                'duplicateKey' => $duplicateKey,
                'message' => $remainingAfter > 0
                    ? ($admit . ' entr' . ($admit === 1 ? 'y' : 'ies') . ' confirmed. ' . $remainingAfter . ' remaining on this QR.')
                    : 'Ticket checked in successfully.',
            ];
        }

        $totals = ['success' => 0, 'failed' => 0];
        foreach ($results as $item) {
            if (!empty($item['ok'])) {
                $totals['success'] += 1;
            } else {
                $totals['failed'] += 1;
            }
        }

        return [
            'ok' => true,
            'action' => 'admin_batch_checkin_event_qr',
            'verifiedBy' => $verifiedBy,
            'totals' => $totals,
            'results' => $results,
        ];
    }

    public function eventsList(array $query): array
    {
        $limit = (int) ($query['limit'] ?? Constants::EVENTS_LIST_DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = Constants::EVENTS_LIST_DEFAULT_LIMIT;
        }
        $limit = min($limit, Constants::EVENTS_LIST_MAX_LIMIT);

        $rows = $this->repo->listActive($limit);
        $events = array_map(static fn(array $row) => EventItem::fromDb($row)->toPublicArray(false), $rows);

        return [
            'ok'     => true,
            'action' => 'events_list',
            'items'  => $events,
            'count'  => count($events),
        ];
    }

    public function eventPopup(): array
    {
        $row = $this->repo->getPopupEvent();
        if (!$row) {
            return [
                'ok'    => true,
                'event' => null,
            ];
        }

        return [
            'ok'    => true,
            'event' => EventItem::fromDb($row)->toPublicArray(false),
        ];
    }

    public function eventDetail(array $query): array
    {
        $eventId = trim((string) ($query['id'] ?? $query['eventId'] ?? $query['event_id'] ?? ''));
        if ($eventId === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Event id is required.',
            ];
        }

        $row = $this->repo->findByEventId($eventId);
        if (!$row) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'Event not found.',
            ];
        }

        return [
            'ok'    => true,
            'event' => EventItem::fromDb($row)->toPublicArray(true),
        ];
    }

    public function adminListEvents(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventManagement')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Event management permission required.',
            ];
        }

        $rows = $this->repo->listAll();
        $events = array_map(static fn(array $row) => EventItem::fromDb($row)->toPublicArray(true), $rows);

        return [
            'ok'     => true,
            'action' => 'admin_list_events',
            'items'  => $events,
            'count'  => count($events),
        ];
    }

    public function adminCreateEvent(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventManagement')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Event management permission required.',
            ];
        }

        $payload = $this->mapInputToDbPayload($data);
        if ($payload['event_id'] === '' || $payload['title'] === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'event_id and title are required.',
            ];
        }

        $this->repo->create($payload);

        return [
            'ok'      => true,
            'action'  => 'admin_create_event',
            'message' => 'Event created.',
        ];
    }

    public function adminUpdateEvent(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventManagement')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Event management permission required.',
            ];
        }

        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? $data['id'] ?? ''));
        if ($eventId === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'eventId is required.',
            ];
        }

        $payload = $this->mapInputToDbPayload($data);
        if ($payload['title'] === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'title is required.',
            ];
        }

        $this->repo->updateByEventId($eventId, $payload);

        return [
            'ok'      => true,
            'action'  => 'admin_update_event',
            'message' => 'Event updated.',
        ];
    }

    public function adminToggleEvent(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventManagement')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Event management permission required.',
            ];
        }

        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? $data['id'] ?? ''));
        if ($eventId === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'eventId is required.',
            ];
        }

        $isActive = !empty($data['isActive']);
        $this->repo->setActiveByEventId($eventId, $isActive);

        return [
            'ok'      => true,
            'action'  => 'admin_toggle_event',
            'message' => 'Event status updated.',
        ];
    }

    private function normalizeBatchScan(array $requestData): array
    {
        $scanText = trim((string) ($requestData['scanText'] ?? $requestData['rawScan'] ?? $requestData['qrText'] ?? ''));
        $parsed = $this->parseEventQrScanText($scanText);

        return [
            'transactionId' => trim((string) ($parsed['transactionId'] ?? $requestData['tx'] ?? $requestData['transactionId'] ?? '')),
            'eventId' => trim((string) ($parsed['eventId'] ?? $requestData['eventId'] ?? '')),
            'paymentId' => trim((string) ($parsed['paymentId'] ?? $requestData['paymentId'] ?? $requestData['pid'] ?? '')),
            'guestId' => trim((string) ($parsed['guestId'] ?? $requestData['guestId'] ?? $requestData['gid'] ?? '')),
            'signature' => strtolower(trim((string) ($parsed['signature'] ?? $requestData['sig'] ?? $requestData['signature'] ?? ''))),
            'rawScan' => (string) ($parsed['rawScan'] ?? $scanText),
        ];
    }

    private function parseEventQrScanText(string $scanText): ?array
    {
        if ($scanText === '') {
            return null;
        }

        $candidates = [$scanText];
        $qPos = strpos($scanText, '?');
        if ($qPos !== false && $qPos < strlen($scanText) - 1) {
            $candidates[] = substr($scanText, $qPos + 1);
        }

        foreach ($candidates as $candidateRaw) {
            $candidate = trim((string) $candidateRaw);
            if ($candidate === '') {
                continue;
            }

            $query = $candidate;
            if (preg_match('/^https?:\/\//i', $candidate) === 1) {
                $parts = parse_url($candidate);
                $query = (string) ($parts['query'] ?? '');
            }

            parse_str(ltrim($query, '?#'), $params);
            if (!is_array($params) || empty($params)) {
                continue;
            }

            $transactionId = trim((string) ($params['tx'] ?? $params['transactionId'] ?? ''));
            $eventId = trim((string) ($params['eventId'] ?? ''));
            $paymentId = trim((string) ($params['paymentId'] ?? $params['pid'] ?? ''));
            $guestId = trim((string) ($params['guestId'] ?? $params['gid'] ?? ''));
            $signature = trim((string) ($params['sig'] ?? $params['signature'] ?? ''));

            if ($transactionId !== '' && $eventId !== '' && $paymentId !== '' && $signature !== '') {
                return [
                    'transactionId' => $transactionId,
                    'eventId' => $eventId,
                    'paymentId' => $paymentId,
                    'guestId' => $guestId,
                    'signature' => $signature,
                    'rawScan' => $scanText,
                ];
            }
        }

        return null;
    }

    private function mapInputToDbPayload(array $data): array
    {
        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? $data['id'] ?? ''));
        $eventType = strtolower(trim((string) ($data['eventType'] ?? $data['event_type'] ?? 'free')));
        if (!in_array($eventType, ['free', 'paid'], true)) {
            $eventType = 'free';
        }

        $timeFormat = strtolower(trim((string) ($data['timeDisplayFormat'] ?? $data['time_display_format'] ?? '12h')));
        if (!in_array($timeFormat, ['12h', '24h'], true)) {
            $timeFormat = '12h';
        }

        return [
            'event_id'              => $eventId,
            'title'                 => trim((string) ($data['title'] ?? '')),
            'subtitle'              => trim((string) ($data['subtitle'] ?? '')),
            'description'           => trim((string) ($data['description'] ?? '')),
            'image_url'             => trim((string) ($data['imageUrl'] ?? $data['image_url'] ?? '')),
            'video_url'             => trim((string) ($data['videoUrl'] ?? $data['video_url'] ?? '')),
            'show_video'            => !empty($data['showVideo']) || !empty($data['show_video']),
            'cta_text'              => trim((string) ($data['ctaText'] ?? $data['cta_text'] ?? '')),
            'cta_url'               => trim((string) ($data['ctaUrl'] ?? $data['cta_url'] ?? '')),
            'badge_text'            => trim((string) ($data['badgeText'] ?? $data['badge_text'] ?? '')),
            'start_date'            => trim((string) ($data['startDate'] ?? $data['start_date'] ?? '')) ?: null,
            'start_time'            => trim((string) ($data['startTime'] ?? $data['start_time'] ?? '')) ?: null,
            'end_date'              => trim((string) ($data['endDate'] ?? $data['end_date'] ?? '')) ?: null,
            'end_time'              => trim((string) ($data['endTime'] ?? $data['end_time'] ?? '')) ?: null,
            'time_display_format'   => $timeFormat,
            'is_active'             => !empty($data['isActive']) || !empty($data['is_active']) || !isset($data['isActive']),
            'priority'              => (int) ($data['priority'] ?? 0),
            'popup_enabled'         => !empty($data['popupEnabled']) || !empty($data['popup_enabled']),
            'show_once_per_session' => !empty($data['showOncePerSession']) || !empty($data['show_once_per_session']),
            'popup_delay_hours'     => (float) ($data['popupDelayHours'] ?? $data['popup_delay_hours'] ?? 0),
            'popup_cooldown_hours'  => (float) ($data['popupCooldownHours'] ?? $data['popup_cooldown_hours'] ?? 24),
            'event_type'            => $eventType,
            'ticket_price'          => (float) ($data['ticketPrice'] ?? $data['ticket_price'] ?? 0),
            'currency'              => trim((string) ($data['currency'] ?? 'INR')) ?: 'INR',
            'max_tickets'           => (int) ($data['maxTickets'] ?? $data['max_tickets'] ?? 0),
            'payment_enabled'       => !empty($data['paymentEnabled']) || !empty($data['payment_enabled']),
            'cancellation_policy'   => trim((string) ($data['cancellationPolicyText'] ?? $data['cancellation_policy'] ?? '')),
            'refund_policy'         => trim((string) ($data['refundPolicy'] ?? $data['refund_policy'] ?? ($_ENV['EVENT_NO_REFUND_POLICY'] ?? ''))),
        ];
    }

    private function prepareCustomerRegistration(array $data, bool $forPaid): array
    {
        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? $data['id'] ?? ''));
        if ($eventId === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'eventId is required.'];
        }

        $event = $this->repo->findByEventId($eventId);
        if (!$event || (int) ($event['is_active'] ?? 0) !== 1) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Event not found or inactive.'];
        }

        $eventType = strtolower((string) ($event['event_type'] ?? 'free'));
        $paymentEnabled = (int) ($event['payment_enabled'] ?? 0) === 1;
        if ($forPaid && (!$paymentEnabled || $eventType !== 'paid')) {
            return ['ok' => false, 'error' => 'INVALID_EVENT_TYPE', 'message' => 'Selected event is not payable.'];
        }

        $name = trim((string) ($data['customerName'] ?? $data['name'] ?? ''));
        $email = trim((string) ($data['customerEmail'] ?? $data['email'] ?? ''));
        $phone = Validator::digitsOnly((string) ($data['customerPhone'] ?? $data['phone'] ?? ''), 10);
        $qty = (int) ($data['qty'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }

        if ($name === '' || $email === '' || !Validator::email($email) || !Validator::phone($phone)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Valid customer details are required.'];
        }

        $attendeeNames = $data['attendeeNames'] ?? [];
        if (!is_array($attendeeNames)) {
            $attendeeNames = [];
        }

        $attendees = [];
        foreach ($attendeeNames as $item) {
            $value = trim((string) $item);
            if ($value !== '') {
                $attendees[] = ['name' => $value];
            }
        }
        if (count($attendees) === 0) {
            $attendees[] = ['name' => $name];
        }

        if (count($attendees) !== $qty) {
            return ['ok' => false, 'error' => 'INVALID_ATTENDEES', 'message' => 'Attendee count must match qty.'];
        }

        $allowDuplicate = !empty($data['allowDuplicate']);
        if (!$allowDuplicate) {
            $existing = $this->transactions->findLatestForEventAndCustomer($eventId, $email, $phone);
            if ($existing) {
                return [
                    'ok' => false,
                    'error' => 'ALREADY_REGISTERED',
                    'message' => 'You have already registered for this event.',
                    'canResendEmail' => true,
                    'alreadyRegistered' => [
                        'transactionId' => (string) ($existing['transaction_id'] ?? ''),
                        'customerName' => (string) ($existing['customer_name'] ?? ''),
                        'customerEmail' => (string) ($existing['customer_email'] ?? ''),
                        'customerPhone' => (string) ($existing['customer_phone'] ?? ''),
                        'qty' => (int) ($existing['qty'] ?? 1),
                        'status' => (string) ($existing['status'] ?? ''),
                        'checkInStatus' => (string) ($existing['checkin_status'] ?? ''),
                        'createdAt' => (string) ($existing['created_at'] ?? ''),
                        'paidAt' => (string) ($existing['paid_at'] ?? ''),
                    ],
                ];
            }
        }

        return [
            'ok' => true,
            'event' => $event,
            'customer' => [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'qty' => $qty,
            ],
            'attendees' => $attendees,
        ];
    }

    private function generateTransactionId(string $prefix): string
    {
        return strtoupper($prefix) . '-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function buildQrPayload(string $transactionId): string
    {
        return json_encode([
            'transactionId' => $transactionId,
            'issuedAt' => gmdate('c'),
        ], JSON_UNESCAPED_SLASHES);
    }

    private function buildQrUrl(string $payload): string
    {
        $encoded = rawurlencode($payload);
        return 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=' . $encoded;
    }

    private function buildVerificationUrl(string $transactionId): string
    {
        return 'https://namastekalyan.asianwokandgrill.in/event-verification.html?transactionId=' . rawurlencode($transactionId);
    }
}
