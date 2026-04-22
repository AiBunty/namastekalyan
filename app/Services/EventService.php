<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Middleware\AuthMiddleware;
use NK\Models\EventItem;
use NK\Repositories\ApiSettingsRepository;
use NK\Repositories\EventCheckinLogRepository;
use NK\Repositories\EventRepository;
use NK\Repositories\EventTransactionRepository;
use NK\Support\SiteUrl;
use NK\Support\Validator;
use NK\Services\WhatsAppCloudService;

class EventService
{
    private EventRepository $repo;
    private ApiSettingsRepository $apiSettings;
    private EventTransactionRepository $transactions;
    private EventCheckinLogRepository $checkinLogs;
    private RazorpayService $razorpay;
    private MailerService $mailer;
    private OtpService $otpService;
    private WhatsAppCloudService $whatsapp;

    public function __construct()
    {
        $this->repo = new EventRepository();
        $this->apiSettings = new ApiSettingsRepository();
        $this->transactions = new EventTransactionRepository();
        $this->checkinLogs = new EventCheckinLogRepository();
        $this->razorpay = new RazorpayService();
        $this->mailer = new MailerService();
        $this->otpService = new OtpService();
        $this->whatsapp = new WhatsAppCloudService();
    }

    public function sendEventOtp(array $data): array
    {
        $this->syncExpiredActiveEvents();

        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? ''));
        $email = strtolower(trim((string) ($data['customerEmail'] ?? $data['email'] ?? '')));
        $customerName = trim((string) ($data['customerName'] ?? $data['name'] ?? ''));

        if ($eventId === '' || $email === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'eventId and email are required.'];
        }
        if (!Validator::email($email)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Please enter a valid email address before requesting OTP.'];
        }

        $event = $this->repo->findByEventId($eventId);
        if (!$event || (int) ($event['is_active'] ?? 0) !== 1 || $this->isEventExpired($event)) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Event not found or inactive.'];
        }

        $result = $this->otpService->sendEventOtp($eventId, $email, $customerName, (string) ($event['title'] ?? 'Namaste Kalyan Event'));
        $result['action'] = 'send_event_otp';
        return $result;
    }

    public function verifyEventOtp(array $data): array
    {
        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? ''));
        $email = strtolower(trim((string) ($data['customerEmail'] ?? $data['email'] ?? '')));
        $otp = trim((string) ($data['otp'] ?? ''));

        if ($eventId === '' || $email === '' || $otp === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'eventId, email and otp are required.'];
        }

        $result = $this->otpService->verifyEventOtp($eventId, $email, $otp);
        $result['action'] = 'verify_event_otp';
        return $result;
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
        $createdAt = date('Y-m-d H:i:s');

        $transactionId = $this->generateTransactionId('FREE');
        $freeReferenceId = 'free_ref_' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
        $qr = $this->buildEventQrBundle($transactionId, (string) $event['event_id'], $freeReferenceId);

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
            'payment_id'        => $freeReferenceId,
            'status'            => 'free_confirmed',
            'qr_url'            => $qr['qrUrl'],
            'qr_payload'        => $qr['verificationUrl'],
            'created_at'        => $createdAt,
            'paid_at'           => $createdAt,
            'attendee_details'  => $attendees,
            'guest_passes_json' => [],
        ]);

        $emailResult = $this->sendTransactionConfirmationEmail($event, [
            'transaction_id' => $transactionId,
            'event_title' => (string) ($event['title'] ?? ''),
            'customer_name' => $customer['name'],
            'customer_email' => $customer['email'],
            'qty' => $customer['qty'],
            'amount' => 0,
            'currency' => (string) ($event['currency'] ?? 'INR'),
            'created_at' => $createdAt,
            'paid_at' => $createdAt,
            'qr_url' => $qr['qrUrl'],
            'attendee_details' => json_encode($attendees, JSON_UNESCAPED_UNICODE),
            'gateway' => 'free',
        ], true, true);

        $emailSent = !empty($emailResult['ok']);
        $whatsAppResult = $this->dispatchRegistrationWhatsApp($event, [
            'transaction_id' => $transactionId,
            'customer_name' => $customer['name'],
            'customer_phone' => $customer['phone'],
            'qty' => $customer['qty'],
            'qr_url' => $qr['qrUrl'],
            'event_id' => (string) ($event['event_id'] ?? ''),
            'event_title' => (string) ($event['title'] ?? ''),
        ], true);

        return [
            'ok'               => true,
            'action'           => 'register_free_event',
            'transactionId'    => $transactionId,
            'guestPassCount'   => count($attendees),
            'qrUrl'            => $qr['qrUrl'],
            'verificationUrl'  => $qr['verificationUrl'],
            'emailProvided'    => true,
            'emailSent'        => $emailSent,
            'emailStatus'      => $emailSent ? 'Sent' : 'Failed',
            'emailSentAt'      => $emailSent ? $createdAt : null,
            'registrationStored' => true,
            'savedAt'          => $createdAt,
            'canResendEmail'   => true,
            'whatsapp'         => $whatsAppResult,
            'message'          => $emailSent
                ? 'Registration confirmed. A confirmation email has been sent to your registered email address.'
                : 'Registration confirmed. Email delivery failed, so please keep the confirmation details shown below.',
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

        $event = $this->repo->findByEventId((string) ($txn['event_id'] ?? '')) ?: [];
        $emailResult = $this->sendTransactionConfirmationEmail($event, array_merge($txn, [
            'payment_id' => $paymentId,
            'paid_at' => date('Y-m-d H:i:s'),
            'status' => 'paid',
        ]), false, true);
        $whatsAppResult = $this->dispatchRegistrationWhatsApp($event, array_merge($txn, [
            'payment_id' => $paymentId,
            'status' => 'paid',
        ]), false);

        return [
            'ok'             => true,
            'action'         => 'confirm_event_payment',
            'transactionId'  => (string) $txn['transaction_id'],
            'guestPassCount' => (int) ($txn['qty'] ?? 1),
            'qrUrl'          => (string) ($txn['qr_url'] ?? ''),
            'verificationUrl'=> $this->buildVerificationUrl((string) $txn['transaction_id']),
            'emailProvided'  => trim((string) ($txn['customer_email'] ?? '')) !== '',
            'emailSent'      => !empty($emailResult['ok']),
            'emailStatus'    => !empty($emailResult['ok']) ? 'Sent' : 'Failed',
            'emailSentAt'    => !empty($emailResult['ok']) ? date('Y-m-d H:i:s') : null,
            'registrationStored' => true,
            'savedAt'        => (string) ($txn['created_at'] ?? date('Y-m-d H:i:s')),
            'canResendEmail' => trim((string) ($txn['customer_email'] ?? '')) !== '',
            'whatsapp'       => $whatsAppResult,
            'policy'         => $_ENV['EVENT_NO_REFUND_POLICY'] ?? 'No refund once pass is purchased.',
        ];
    }

    public function resendEventConfirmation(array $data): array
    {
        $transactionId = trim((string) ($data['transactionId'] ?? $data['transaction_id'] ?? ''));
        $email = strtolower(trim((string) ($data['customerEmail'] ?? $data['email'] ?? '')));
        $phone = Validator::digitsOnly((string) ($data['customerPhone'] ?? $data['phone'] ?? ''), 10);
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

        $registeredEmail = strtolower(trim((string) ($txn['customer_email'] ?? '')));
        $registeredPhone = Validator::digitsOnly((string) ($txn['customer_phone'] ?? ''), 10);
        $matchesEmail = $email !== '' && $email === $registeredEmail;
        $matchesPhone = $phone !== '' && $phone === $registeredPhone;
        if (($email === '' && $phone === '') || (!$matchesEmail && !$matchesPhone)) {
            return [
                'ok' => false,
                'error' => 'IDENTITY_MISMATCH',
                'message' => 'Registered email or phone does not match this transaction.',
            ];
        }

        if ($registeredEmail === '') {
            return [
                'ok' => false,
                'error' => 'EMAIL_REQUIRED',
                'message' => 'No registered email address is available for this transaction.',
            ];
        }

        $event = $this->repo->findByEventId((string) ($txn['event_id'] ?? '')) ?: [];
        $emailResult = $this->sendTransactionConfirmationEmail($event, $txn, strtolower((string) ($txn['gateway'] ?? 'free')) === 'free', true);

        if (empty($emailResult['ok'])) {
            return [
                'ok' => false,
                'error' => (string) ($emailResult['error'] ?? 'EMAIL_SEND_FAILED'),
                'message' => (string) ($emailResult['message'] ?? 'Unable to resend confirmation email.'),
            ];
        }

        return [
            'ok' => true,
            'action' => 'resend_event_confirmation',
            'message' => 'Confirmation email sent again successfully.',
            'transactionId' => $transactionId,
            'emailStatus' => 'Sent',
            'emailSentAt' => date('Y-m-d H:i:s'),
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
        $passcode = trim((string) ($data['passcode'] ?? $data['staffPasscode'] ?? ''));
        if ($passcode === '') {
            return [
                'ok' => false,
                'error' => 'PASSCODE_REQUIRED',
                'message' => 'Event entry passcode is required.',
            ];
        }

        $expectedPasscode = $this->getEventEntryPasscode();
        if ($expectedPasscode === '' || !hash_equals($expectedPasscode, strtoupper($passcode))) {
            return [
                'ok' => false,
                'error' => 'INVALID_PASSCODE',
                'message' => 'Invalid event entry passcode.',
            ];
        }

        $normalized = $this->normalizeBatchScan($data);
        $verifiedBy = $this->normalizeVerifiedBy((string) ($data['verifiedBy'] ?? ''));
        if (!empty($data['previewOnly']) || !empty($data['preview']) || (string) ($data['mode'] ?? '') === 'preview') {
            $result = $this->buildCheckinPreview($normalized, false);
            $result['action'] = 'verify_event_qr';
            return $result;
        }

        $result = $this->processSingleCheckin(
            $normalized,
            max(1, (int) ($data['admittedCount'] ?? 1)),
            $verifiedBy,
            false,
            $data['selectedGuestNames'] ?? $data['guestNames'] ?? []
        );
        $result['action'] = 'verify_event_qr';
        return $result;
    }

    public function eventGuestReport(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
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

        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? ''));
        $report = $this->buildEventGuestReport($eventId);

        return [
            'ok' => true,
            'action' => 'event_guest_report',
            'report' => $report,
        ];
    }

    public function eventTransactionsReport(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
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

        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? ''));
        $report = $this->buildEventGuestReport($eventId);

        return [
            'ok' => true,
            'action' => 'event_transactions_report',
            'report' => $report,
            'items' => $report['razorpayReconciliation']['entries'] ?? [],
        ];
    }

    public function adminMailLogReport(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
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

        $requestedFile = trim((string) ($data['file'] ?? $data['logFile'] ?? ''));
        $limit = max(10, min(250, (int) ($data['limit'] ?? 80)));

        return [
            'ok' => true,
            'action' => 'admin_mail_log_report',
            'report' => $this->buildAdminMailLogReport($requestedFile, $limit),
        ];
    }

    public function adminPreviewEventQr(array $data): array
    {
        $normalized = $this->normalizeBatchScan($data);
        $result = $this->buildCheckinPreview($normalized, true);
        $result['action'] = 'admin_preview_event_qr';
        return $result;
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
        $secret = $this->getEventQrSigningSecret();
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
            $results[] = $this->processSingleCheckin(
                $normalized,
                max(1, (int) ($requestData['admittedCount'] ?? 1)),
                $verifiedBy,
                true,
                $requestData['selectedGuestNames'] ?? $requestData['guestNames'] ?? []
            );
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
        $this->syncExpiredActiveEvents();

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
        $this->syncExpiredActiveEvents();

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
        $this->syncExpiredActiveEvents();

        $eventId = trim((string) ($query['id'] ?? $query['eventId'] ?? $query['event_id'] ?? ''));
        if ($eventId === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Event id is required.',
            ];
        }

        $row = $this->repo->findByEventId($eventId);
        if (!$row || (int) ($row['is_active'] ?? 0) !== 1 || $this->isEventExpired($row)) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'Event not found or inactive.',
            ];
        }

        return [
            'ok'    => true,
            'event' => EventItem::fromDb($row)->toPublicArray(true),
        ];
    }

    public function adminListEvents(array $data): array
    {
        $this->syncExpiredActiveEvents();

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
        if ($payload['title'] === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'title is required.',
            ];
        }
        if ($payload['event_id'] === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $payload['title']));
            $slug = trim($slug, '-');
            $payload['event_id'] = ($slug ?: 'event') . '-' . time();
        }

        if (!$this->canEventRemainActive($payload)) {
            return [
                'ok'      => false,
                'error'   => 'EVENT_EXPIRED',
                'message' => 'Expired events cannot be active. Update the event start/end date or time first.',
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

        if (!$this->canEventRemainActive($payload)) {
            return [
                'ok'      => false,
                'error'   => 'EVENT_EXPIRED',
                'message' => 'Expired events cannot be active. Update the event start/end date or time first.',
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

        if ($isActive) {
            $row = $this->repo->findByEventId($eventId);
            if (!$row) {
                return [
                    'ok'      => false,
                    'error'   => 'NOT_FOUND',
                    'message' => 'Event not found.',
                ];
            }

            if (!$this->canEventRemainActive($row, true)) {
                return [
                    'ok'      => false,
                    'error'   => 'EVENT_EXPIRED',
                    'message' => 'Expired events cannot be active. Update the event start/end date or time first.',
                ];
            }
        }

        $this->repo->setActiveByEventId($eventId, $isActive);

        return [
            'ok'      => true,
            'action'  => 'admin_toggle_event',
            'message' => 'Event status updated.',
        ];
    }

    public function adminDeleteEvent(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventManagement')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Event management permission required.'];
        }

        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? ''));
        if ($eventId === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'eventId is required.'];
        }

        $event = $this->repo->findByEventId($eventId);
        if (!$event) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Event not found.'];
        }

        $force = !empty($data['force']);
        if (!$force && $this->repo->hasRegistrations($eventId)) {
            return [
                'ok'             => false,
                'error'          => 'HAS_REGISTRATIONS',
                'message'        => 'This event has active registrations. Pass force=true to delete anyway.',
                'hasRegistrations' => true,
            ];
        }

        $this->repo->deleteByEventId($eventId);

        return [
            'ok'      => true,
            'action'  => 'admin_delete_event',
            'message' => 'Event deleted.',
        ];
    }

    public function adminCloneEvent(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventManagement')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Event management permission required.'];
        }

        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? ''));
        if ($eventId === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'eventId is required.'];
        }

        $source = $this->repo->findByEventId($eventId);
        if (!$source) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => 'Event not found.'];
        }

        $newEventId = strtolower(preg_replace('/[^a-z0-9]+/i', '-', 'copy-' . ($source['title'] ?? 'event')))
            . '-' . substr(md5(uniqid('', true)), 0, 6);
        $newEventId = trim($newEventId, '-');

        $clonePayload = $source;
        $expiredSource = $this->isEventExpired($source);
        $clonePayload['event_id']   = $newEventId;
        $clonePayload['title']      = 'Copy of ' . ($source['title'] ?? '');
        $clonePayload['is_active']  = 0;
        if ($expiredSource) {
            $today = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
            $clonePayload['start_date'] = $today->format('Y-m-d');
            $clonePayload['end_date'] = $today->format('Y-m-d');
            if (trim((string) ($clonePayload['start_time'] ?? '')) === '') {
                $clonePayload['start_time'] = $today->format('H:i:s');
            }
            if (trim((string) ($clonePayload['end_time'] ?? '')) === '') {
                $clonePayload['end_time'] = $today->modify('+4 hours')->format('H:i:s');
            }
        } else {
            $clonePayload['start_date'] = null;
            $clonePayload['start_time'] = null;
            $clonePayload['end_date']   = null;
            $clonePayload['end_time']   = null;
        }
        unset($clonePayload['id'], $clonePayload['created_at'], $clonePayload['updated_at']);

        $this->repo->create($clonePayload);

        return [
            'ok'         => true,
            'action'     => 'admin_clone_event',
            'newEventId' => $newEventId,
            'message'    => $expiredSource
                ? 'Event cloned. The source event was expired, so the clone was moved to the current date. Review the schedule before saving.'
                : 'Event cloned. Update the dates and activate when ready.',
        ];
    }

    public function adminUploadEventImage(array $data, string $tmpPath): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'eventManagement')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Event management permission required.'];
        }

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['ok' => false, 'error' => 'NO_FILE', 'message' => 'No image uploaded.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            return ['ok' => false, 'error' => 'INVALID_FILE', 'message' => 'Only JPEG, PNG, WebP or GIF images are allowed.'];
        }

        if (filesize($tmpPath) > 10 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'FILE_TOO_LARGE', 'message' => 'Image must be under 10 MB.'];
        }

        $saveDir = __DIR__ . '/../../public/event-images';
        if (!is_dir($saveDir) && !mkdir($saveDir, 0755, true)) {
            return ['ok' => false, 'error' => 'STORAGE_ERROR', 'message' => 'Could not create event-images directory.'];
        }

        $ext      = match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            default      => 'jpg',
        };
        $filename = 'evt-' . substr(md5(uniqid('', true)), 0, 12) . '.' . $ext;
        $destPath = $saveDir . '/' . $filename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            return ['ok' => false, 'error' => 'STORAGE_ERROR', 'message' => 'Failed to save image.'];
        }

        $publicUrl = '/event-images/' . $filename;

        return [
            'ok'        => true,
            'action'    => 'admin_event_image_upload',
            'imageUrl'  => $publicUrl,
            'message'   => 'Image uploaded.',
        ];
    }

    private function buildEventGuestReport(string $requestedEventId): array
    {
        $targetEventId = trim($requestedEventId);
        $eventRows = $this->repo->listAll();
        $transactionRows = $this->transactions->listReportRows($targetEventId !== '' ? $targetEventId : null);
        $transactionIds = array_values(array_filter(array_map(static fn(array $row) => trim((string) ($row['transaction_id'] ?? '')), $transactionRows)));
        $checkinHistoryMap = $this->groupCheckinLogsByTransactionId($this->checkinLogs->listByTransactionIds($transactionIds));

        $summaryByEvent = [];
        foreach ($eventRows as $event) {
            $eventId = trim((string) ($event['event_id'] ?? ''));
            if ($eventId === '') {
                continue;
            }

            $summaryByEvent[$eventId] = [
                'eventId' => $eventId,
                'eventTitle' => (string) ($event['title'] ?? $eventId),
                'eventType' => strtolower((string) ($event['event_type'] ?? 'free')) === 'paid' ? 'paid' : 'free',
                'paymentEnabled' => (int) ($event['payment_enabled'] ?? 0) === 1,
                'registrations' => 0,
                'guests' => 0,
                'freeRegistrations' => 0,
                'paidRegistrations' => 0,
                'checkedInGuests' => 0,
                'razorpayCollectedAmount' => 0.0,
                'cashCollectedAmount' => 0.0,
            ];
        }

        $guests = [];
        foreach ($transactionRows as $row) {
            $eventId = trim((string) ($row['event_id'] ?? '')) ?: 'unknown-event';
            $eventTitle = trim((string) ($row['event_title'] ?? '')) ?: 'Untitled Event';
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $gateway = strtolower(trim((string) ($row['gateway'] ?? '')));
            $status = trim((string) ($row['status'] ?? ''));
            $statusKey = strtolower($status);
            $checkInStatus = trim((string) ($row['checkin_status'] ?? ''));
            $checkInStatusKey = strtolower($checkInStatus);
            $amount = (float) ($row['amount'] ?? 0);
            $isFreeRegistration = $gateway === 'free' || in_array($statusKey, ['registeredfree', 'checkedinfree', 'free_confirmed', 'checked_in_free'], true);
            $attendeeNames = $this->getTransactionAttendeeNames($row);
            $history = $this->buildCheckinHistoryForTransaction($row, $attendeeNames, $checkinHistoryMap[(string) ($row['transaction_id'] ?? '')] ?? []);
            $checkedInCount = $history['checkedInCount'];
            $checkedIn = $checkedInCount > 0;
            $isCollectedPaid = !$isFreeRegistration && ($statusKey === 'paid' || str_starts_with($statusKey, 'checkedin') || str_starts_with($statusKey, 'checked_in'));

            if (!isset($summaryByEvent[$eventId])) {
                $summaryByEvent[$eventId] = [
                    'eventId' => $eventId,
                    'eventTitle' => $eventTitle,
                    'eventType' => $isFreeRegistration ? 'free' : 'paid',
                    'paymentEnabled' => !$isFreeRegistration,
                    'registrations' => 0,
                    'guests' => 0,
                    'freeRegistrations' => 0,
                    'paidRegistrations' => 0,
                    'checkedInGuests' => 0,
                    'razorpayCollectedAmount' => 0.0,
                    'cashCollectedAmount' => 0.0,
                ];
            }

            $summaryByEvent[$eventId]['registrations'] += 1;
            $summaryByEvent[$eventId]['guests'] += $qty;
            $summaryByEvent[$eventId]['checkedInGuests'] += $checkedInCount;
            if ($isFreeRegistration) {
                $summaryByEvent[$eventId]['freeRegistrations'] += 1;
            } else {
                $summaryByEvent[$eventId]['paidRegistrations'] += 1;
            }
            if ($isCollectedPaid && $gateway === 'razorpay') {
                $summaryByEvent[$eventId]['razorpayCollectedAmount'] += $amount;
            }
            if ($isCollectedPaid && $gateway === 'cash') {
                $summaryByEvent[$eventId]['cashCollectedAmount'] += $amount;
            }

            $guests[] = [
                'transactionId' => (string) ($row['transaction_id'] ?? ''),
                'eventId' => $eventId,
                'eventTitle' => $eventTitle,
                'guestName' => (string) ($row['customer_name'] ?? ''),
                'customerName' => (string) ($row['customer_name'] ?? ''),
                'email' => (string) ($row['customer_email'] ?? ''),
                'phone' => (string) ($row['customer_phone'] ?? ''),
                'tickets' => $qty,
                'qty' => $qty,
                'attendees' => count($attendeeNames) ? implode(', ', $attendeeNames) : $qty,
                'attendeeDetails' => $attendeeNames,
                'amount' => $amount,
                'currency' => (string) ($row['currency'] ?? 'INR'),
                'gateway' => $gateway,
                'collectionType' => $isFreeRegistration ? 'Free' : ($gateway === 'cash' ? 'Cash' : ($gateway === 'razorpay' ? 'Razorpay' : 'Other')),
                'bookingType' => $isFreeRegistration ? 'Free' : 'Paid',
                'orderId' => (string) ($row['order_id'] ?? ''),
                'paymentId' => (string) ($row['payment_id'] ?? ''),
                'status' => $status,
                'emailStatus' => (string) ($row['email_status'] ?? ''),
                'emailSentAt' => (string) ($row['email_sent_at'] ?? ''),
                'registeredAt' => (string) ($row['created_at'] ?? ''),
                'createdAt' => (string) ($row['created_at'] ?? ''),
                'confirmedAt' => (string) ($row['paid_at'] ?? ''),
                'refundStatus' => (string) ($row['refund_status'] ?? ''),
                'checkInStatus' => $checkedInCount >= $qty ? 'checked_in' : ($checkedInCount > 0 ? 'partial' : $checkInStatus),
                'checkedInAt' => (string) ($row['checked_in_at'] ?? ''),
                'checkedInCount' => $checkedInCount,
                'remainingEntries' => max(0, $qty - $checkedInCount),
                'checkinHistory' => $history['history'],
                'checkinHistorySummary' => $this->summarizeCheckinHistory($history['history']),
            ];
        }

        usort($guests, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['createdAt'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['createdAt'] ?? '')) ?: 0;
            return $rightTime <=> $leftTime;
        });

        $totals = [
            'registrations' => 0,
            'guests' => 0,
            'free' => 0,
            'paid' => 0,
            'checkedIn' => 0,
            'emailSent' => 0,
            'emailFailed' => 0,
            'emailPending' => 0,
            'razorpayCollected' => 0.0,
            'razorpayPending' => 0.0,
            'cashCollected' => 0.0,
        ];
        $reconTotals = [
            'collectedRows' => 0,
            'collectedAmount' => 0.0,
            'pendingAmount' => 0.0,
            'cancelledRows' => 0,
        ];
        $reconEntries = [];

        foreach ($guests as $item) {
            $statusKey = strtolower(trim((string) ($item['status'] ?? '')));
            $checkInStatusKey = strtolower(trim((string) ($item['checkInStatus'] ?? '')));
            $emailStatusKey = strtolower(trim((string) ($item['emailStatus'] ?? '')));
            $amount = (float) ($item['amount'] ?? 0);

            $totals['registrations'] += 1;
            $totals['guests'] += max(1, (int) ($item['qty'] ?? 1));
            if (($item['bookingType'] ?? '') === 'Free') {
                $totals['free'] += 1;
            }
            if (($item['bookingType'] ?? '') === 'Paid') {
                $totals['paid'] += 1;
            }
            if (!empty($item['checkedInCount'])) {
                $totals['checkedIn'] += max(0, (int) ($item['checkedInCount'] ?? 0));
            }

            if ($emailStatusKey === 'sent') {
                $totals['emailSent'] += 1;
            } elseif ($emailStatusKey === 'failed') {
                $totals['emailFailed'] += 1;
            } else {
                $totals['emailPending'] += 1;
            }

            if (($item['collectionType'] ?? '') === 'Razorpay') {
                $reconEntries[] = [
                    'eventTitle' => (string) ($item['eventTitle'] ?? ''),
                    'guestName' => (string) ($item['guestName'] ?? ''),
                    'transactionId' => (string) ($item['transactionId'] ?? ''),
                    'orderId' => (string) ($item['orderId'] ?? ''),
                    'paymentId' => (string) ($item['paymentId'] ?? ''),
                    'amount' => $amount,
                    'status' => (string) ($item['status'] ?? ''),
                    'refundStatus' => (string) ($item['refundStatus'] ?? ''),
                    'createdAt' => (string) ($item['createdAt'] ?? ''),
                    'confirmedAt' => (string) ($item['confirmedAt'] ?? ''),
                ];

                if ($statusKey === 'paid' || str_starts_with($statusKey, 'checkedin') || str_starts_with($statusKey, 'checked_in')) {
                    $totals['razorpayCollected'] += $amount;
                    $reconTotals['collectedRows'] += 1;
                    $reconTotals['collectedAmount'] += $amount;
                } elseif (!str_starts_with($statusKey, 'cancelled')) {
                    $totals['razorpayPending'] += $amount;
                    $reconTotals['pendingAmount'] += $amount;
                }
                if (str_starts_with($statusKey, 'cancelled')) {
                    $reconTotals['cancelledRows'] += 1;
                }
            }

            if (($item['collectionType'] ?? '') === 'Cash' && ($statusKey === 'paid' || str_starts_with($statusKey, 'checkedin') || str_starts_with($statusKey, 'checked_in'))) {
                $totals['cashCollected'] += $amount;
            }
        }

        $eventSummary = array_values($summaryByEvent);
        usort($eventSummary, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['eventTitle'] ?? ''), (string) ($right['eventTitle'] ?? ''));
        });

        $totals['freeRegistrations'] = $totals['free'];
        $totals['paidRegistrations'] = $totals['paid'];
        $totals['checkedInGuests'] = $totals['checkedIn'];
        $totals['razorpayCollectedAmount'] = $totals['razorpayCollected'];
        $totals['razorpayPendingAmount'] = $totals['razorpayPending'];
        $totals['cashCollectedAmount'] = $totals['cashCollected'];

        return [
            'selectedEventId' => $targetEventId,
            'totals' => $totals,
            'eventSummary' => $eventSummary,
            'guests' => $guests,
            'razorpayReconciliation' => [
                'totals' => $reconTotals,
                'entries' => $reconEntries,
            ],
        ];
    }

    private function buildAdminMailLogReport(string $requestedFile, int $limit): array
    {
        $availableFiles = $this->listAdminLogFiles();
        $selectedFile = '';

        if ($requestedFile !== '') {
            foreach ($availableFiles as $fileName) {
                if ($fileName === basename($requestedFile)) {
                    $selectedFile = $fileName;
                    break;
                }
            }
        }

        if ($selectedFile === '' && !empty($availableFiles)) {
            $selectedFile = $availableFiles[0];
        }

        $entries = [];
        if ($selectedFile !== '') {
            $entries = $this->readAdminMailLogEntries($this->adminLogDir() . DIRECTORY_SEPARATOR . $selectedFile, $limit);
        }

        return [
            'selectedFile' => $selectedFile,
            'availableFiles' => $availableFiles,
            'summary' => $this->summarizeAdminMailLogEntries($entries),
            'entries' => $entries,
        ];
    }

    private function listAdminLogFiles(): array
    {
        $dir = $this->adminLogDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . DIRECTORY_SEPARATOR . '*.log') ?: [];
        usort($files, static function (string $left, string $right): int {
            $leftBase = basename($left);
            $rightBase = basename($right);
            $leftIsDated = preg_match('/^\d{4}-\d{2}-\d{2}\.log$/', $leftBase) === 1;
            $rightIsDated = preg_match('/^\d{4}-\d{2}-\d{2}\.log$/', $rightBase) === 1;

            if ($leftIsDated && $rightIsDated) {
                return strcmp($rightBase, $leftBase);
            }
            if ($leftIsDated) {
                return -1;
            }
            if ($rightIsDated) {
                return 1;
            }

            return strcmp($rightBase, $leftBase);
        });

        return array_map('basename', array_slice($files, 0, 14));
    }

    private function readAdminMailLogEntries(string $filePath, int $limit): array
    {
        if (!is_file($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $entries = [];
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $decoded = json_decode((string) $lines[$index], true);
            if (!is_array($decoded) || !$this->isAdminMailLogEntry($decoded)) {
                continue;
            }

            $context = is_array($decoded['context'] ?? null) ? $decoded['context'] : [];
            $entries[] = [
                'time' => (string) ($decoded['time'] ?? ''),
                'level' => strtoupper((string) ($decoded['level'] ?? ($decoded['label'] ?? 'INFO'))),
                'message' => (string) ($decoded['message'] ?? ($decoded['label'] ?? 'Log entry')),
                'kind' => (string) ($context['kind'] ?? ''),
                'transactionId' => (string) ($context['transactionId'] ?? ''),
                'to' => (string) ($context['to'] ?? ''),
                'recipientDomain' => (string) ($context['recipientDomain'] ?? ''),
                'subject' => (string) ($context['subject'] ?? ''),
                'messageId' => (string) ($context['messageId'] ?? ''),
                'error' => (string) ($context['error'] ?? ''),
                'smtpHost' => (string) ($context['smtpHost'] ?? ''),
                'smtpPort' => isset($context['smtpPort']) ? (string) $context['smtpPort'] : '',
                'smtpSecure' => (string) ($context['smtpSecure'] ?? ''),
                'file' => basename($filePath),
            ];

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    private function summarizeAdminMailLogEntries(array $entries): array
    {
        $summary = [
            'attempted' => 0,
            'accepted' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($entries as $entry) {
            $message = strtolower(trim((string) ($entry['message'] ?? '')));
            if ($message === 'attempting event email handoff.') {
                $summary['attempted'] += 1;
            } elseif ($message === 'event email handoff accepted by smtp transport.') {
                $summary['accepted'] += 1;
            } elseif ($message === 'event email sending failed.') {
                $summary['failed'] += 1;
            } elseif ($message === 'event email skipped because smtp is not fully configured.') {
                $summary['skipped'] += 1;
            }
        }

        return $summary;
    }

    private function isAdminMailLogEntry(array $decoded): bool
    {
        $message = strtolower(trim((string) ($decoded['message'] ?? ($decoded['label'] ?? ''))));
        $context = is_array($decoded['context'] ?? null) ? $decoded['context'] : [];
        $kind = strtolower(trim((string) ($context['kind'] ?? '')));

        if (in_array($kind, ['event_otp', 'event_confirmation'], true)) {
            return true;
        }

        return str_contains($message, 'event email');
    }

    private function adminLogDir(): string
    {
        return NK_BASE_DIR . DIRECTORY_SEPARATOR . 'logs';
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

    private function processSingleCheckin(array $normalized, int $admit, string $verifiedBy, bool $requireSignedPayload, mixed $selectedGuestNamesInput = []): array
    {
        $duplicateKey = $normalized['guestId'] !== ''
            ? ($normalized['transactionId'] . '::' . $normalized['guestId'])
            : $normalized['transactionId'];

        if ($normalized['transactionId'] === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_QR',
                'message' => 'Transaction id is required.',
                'transactionId' => '',
                'duplicateKey' => $duplicateKey,
            ];
        }

        $txn = $this->transactions->findByTransactionId($normalized['transactionId']);
        if (!$txn) {
            return [
                'ok' => false,
                'error' => 'TX_NOT_FOUND',
                'message' => 'Transaction not found.',
                'transactionId' => $normalized['transactionId'],
                'duplicateKey' => $duplicateKey,
            ];
        }

        $txEventId = trim((string) ($txn['event_id'] ?? ''));
        $txPaymentId = trim((string) ($txn['payment_id'] ?? ''));
        $eventId = $normalized['eventId'] !== '' ? $normalized['eventId'] : $txEventId;
        $paymentId = $normalized['paymentId'] !== '' ? $normalized['paymentId'] : $txPaymentId;

        if ($requireSignedPayload && ($eventId === '' || $paymentId === '' || $normalized['signature'] === '')) {
            return [
                'ok' => false,
                'error' => 'INVALID_QR',
                'message' => 'Incomplete QR data.',
                'transactionId' => $normalized['transactionId'],
                'duplicateKey' => $duplicateKey,
            ];
        }

        if (($normalized['eventId'] !== '' && $txEventId !== $normalized['eventId'])
            || ($normalized['paymentId'] !== '' && $txPaymentId !== $normalized['paymentId'])) {
            return [
                'ok' => false,
                'error' => 'QR_TX_MISMATCH',
                'message' => 'QR does not match transaction data.',
                'transactionId' => $normalized['transactionId'],
                'duplicateKey' => $duplicateKey,
            ];
        }

        if ($normalized['signature'] !== '') {
            $secret = $this->getEventQrSigningSecret();
            if ($secret === '') {
                return [
                    'ok' => false,
                    'error' => 'SIGNING_SECRET_MISSING',
                    'message' => 'EVENT_QR_SIGNING_SECRET is not configured.',
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                ];
            }

            $signingRaw = $normalized['transactionId'] . '|' . $eventId . '|' . $paymentId . '|' . $normalized['guestId'];
            $expected = hash_hmac('sha256', $signingRaw, $secret);
            if (!hash_equals(strtolower($expected), strtolower($normalized['signature']))) {
                return [
                    'ok' => false,
                    'error' => 'TAMPERED_QR',
                    'message' => 'QR signature validation failed.',
                    'transactionId' => $normalized['transactionId'],
                    'duplicateKey' => $duplicateKey,
                ];
            }
        }

        $status = trim((string) ($txn['status'] ?? ''));
        $statusKey = strtolower($status);
        if (!in_array($statusKey, ['paid', 'free_confirmed', 'checked_in', 'checked_in_free'], true)) {
            return [
                'ok' => false,
                'error' => 'INVALID_STATUS',
                'message' => 'Ticket is not valid for check-in. Current status: ' . $status,
                'transactionId' => $normalized['transactionId'],
                'duplicateKey' => $duplicateKey,
            ];
        }

        $qty = max(1, (int) ($txn['qty'] ?? 1));
        $attendeeNames = $this->getTransactionAttendeeNames($txn);
        $history = $this->buildCheckinHistoryForTransaction($txn, $attendeeNames);
        $checkedInCount = $history['checkedInCount'];
        $remainingNames = $history['remainingNames'];
        $remaining = max(0, $qty - $checkedInCount);
        if ($remaining <= 0) {
            return [
                'ok' => false,
                'error' => 'ALREADY_USED',
                'message' => 'All allowed entries for this QR are already used.',
                'transactionId' => $normalized['transactionId'],
                'duplicateKey' => $duplicateKey,
                'remainingEntries' => 0,
                'remainingAttendeeNames' => [],
            ];
        }

        $selectedGuestNames = $this->normalizeSelectedGuestNames($txn, $remainingNames, $selectedGuestNamesInput);
        if (!empty($selectedGuestNames['error'])) {
            return [
                'ok' => false,
                'error' => 'INVALID_GUEST_SELECTION',
                'message' => $selectedGuestNames['error'],
                'transactionId' => $normalized['transactionId'],
                'duplicateKey' => $duplicateKey,
                'remainingEntries' => $remaining,
                'remainingAttendeeNames' => $remainingNames,
            ];
        }

        $admittedGuestNames = $selectedGuestNames['guestNames'];
        if ($admittedGuestNames !== []) {
            $admit = count($admittedGuestNames);
        }

        if ($admit > $remaining) {
            return [
                'ok' => false,
                'error' => 'ADMISSION_EXCEEDS_REMAINING',
                'message' => 'Only ' . $remaining . ' entr' . ($remaining === 1 ? 'y is' : 'ies are') . ' remaining for this QR.',
                'transactionId' => $normalized['transactionId'],
                'duplicateKey' => $duplicateKey,
                'remainingEntries' => $remaining,
                'remainingAttendeeNames' => $remainingNames,
            ];
        }

        $nextCheckedIn = min($qty, $checkedInCount + $admit);
        $isFree = strtolower((string) ($txn['gateway'] ?? '')) === 'free' || $statusKey === 'free_confirmed';
        $fullyCheckedIn = $nextCheckedIn >= $qty;
        $nextStatus = $fullyCheckedIn
            ? ($isFree ? 'checked_in_free' : 'checked_in')
            : $status;
        $nextCheckInStatus = $fullyCheckedIn ? 'checked_in' : 'pending';
        $checkinTimestamp = date('Y-m-d H:i:s');
        $checkedInAt = $checkinTimestamp;

        $this->transactions->updateCheckinState(
            $normalized['transactionId'],
            $nextStatus,
            $nextCheckInStatus,
            $nextCheckedIn,
            $verifiedBy,
            $checkedInAt === '' ? null : $checkedInAt,
            null
        );

        $this->checkinLogs->create([
            'transaction_id' => $normalized['transactionId'],
            'event_id' => $eventId,
            'admitted_count' => $admit,
            'guest_names' => $admittedGuestNames,
            'verified_by' => $verifiedBy,
            'source' => $requireSignedPayload ? 'scanner' : 'verification-page',
            'created_at' => $checkinTimestamp,
        ]);

        $emailResult = $this->sendGuestCheckinConfirmationEmail($txn, [
            'eventId' => $eventId,
            'checkedInAt' => $checkedInAt,
            'admittedCount' => $admit,
            'admittedGuestNames' => $admittedGuestNames,
            'remainingEntries' => max(0, $qty - $nextCheckedIn),
        ]);

        $whatsAppResult = $this->dispatchGuestCheckinWhatsApp($txn, [
            'eventId' => $eventId,
            'checkedInAt' => $checkedInAt,
            'admittedCount' => $admit,
            'admittedGuestNames' => $admittedGuestNames,
        ]);

        $this->whatsapp->scheduleCheckinFollowUp($txn, [
            'checkedInAt' => $checkedInAt,
        ]);

        $remainingAfter = max(0, $qty - $nextCheckedIn);
        $remainingAfterNames = $this->removeGuestNamesFromPool($remainingNames, $admittedGuestNames);
        return [
            'ok' => true,
            'transactionId' => $normalized['transactionId'],
            'eventId' => $eventId,
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
            'verifiedBy' => $verifiedBy,
            'admittedGuestNames' => $admittedGuestNames,
            'remainingAttendeeNames' => $remainingAfterNames,
            'duplicateKey' => $duplicateKey,
            'email' => $emailResult,
            'whatsapp' => $whatsAppResult,
            'message' => $remainingAfter > 0
                ? ($admit . ' entr' . ($admit === 1 ? 'y' : 'ies') . ' confirmed. ' . $remainingAfter . ' remaining on this QR.')
                : 'Ticket checked in successfully.',
        ];
    }

    private function dispatchRegistrationWhatsApp(array $event, array $transaction, bool $isFreeRegistration): array
    {
        $phone = trim((string) ($transaction['customer_phone'] ?? ''));
        if ($phone === '') {
            return [
                'ok' => false,
                'attempted' => false,
                'success' => false,
                'message' => 'Customer phone is not available for WhatsApp registration confirmation.',
            ];
        }

        $context = [
            'customerName' => (string) ($transaction['customer_name'] ?? 'Guest'),
            'eventTitle' => (string) (($event['title'] ?? '') ?: ($transaction['event_title'] ?? 'Namaste Kalyan Event')),
            'eventDate' => $this->formatEventDateLabel((string) ($event['start_date'] ?? '')),
            'eventTime' => $this->formatEventTimeLabel((string) ($event['start_time'] ?? '')),
            'transactionId' => (string) ($transaction['transaction_id'] ?? ''),
            'bookingType' => $isFreeRegistration ? 'Free Registration' : 'Paid Booking',
            'qrUrl' => (string) ($transaction['qr_url'] ?? ''),
        ];

        $result = $this->whatsapp->triggerEvent('event_registration_confirmed', $phone, $context, [
            'countryCode' => '91',
            'leadId' => null,
        ]);

        $this->whatsapp->scheduleEventReminders($event, $transaction);

        return $result;
    }

    private function dispatchGuestCheckinWhatsApp(array $transaction, array $context): array
    {
        $phone = trim((string) ($transaction['customer_phone'] ?? ''));
        if ($phone === '') {
            return [
                'ok' => false,
                'attempted' => false,
                'success' => false,
                'message' => 'Customer phone is not available for WhatsApp guest check-in confirmation.',
            ];
        }

        return $this->whatsapp->triggerEvent('guest_checked_in', $phone, [
            'customerName' => (string) ($transaction['customer_name'] ?? 'Guest'),
            'eventTitle' => (string) (($transaction['event_title'] ?? '') ?: 'Namaste Kalyan Event'),
            'admittedCount' => (string) ($context['admittedCount'] ?? '1'),
            'checkedInAt' => $this->formatDateTimeLabel((string) ($context['checkedInAt'] ?? '')),
        ], [
            'countryCode' => '91',
            'leadId' => null,
        ]);
    }

    private function sendGuestCheckinConfirmationEmail(array $transaction, array $context): array
    {
        $emailAddress = trim((string) ($transaction['customer_email'] ?? ''));
        if ($emailAddress === '') {
            return [
                'ok' => false,
                'error' => 'EMAIL_REQUIRED',
                'message' => 'No registered email address is available for this transaction.',
            ];
        }

        $eventId = trim((string) ($context['eventId'] ?? $transaction['event_id'] ?? ''));
        $event = $eventId !== '' ? ($this->events->findByEventId($eventId) ?: []) : [];

        return $this->mailer->sendGuestCheckinConfirmation([
            'customerEmail' => $emailAddress,
            'customerName' => (string) ($transaction['customer_name'] ?? 'Guest'),
            'eventTitle' => (string) (($event['title'] ?? '') ?: ($transaction['event_title'] ?? 'Namaste Kalyan Event')),
            'eventSubtitle' => (string) ($event['subtitle'] ?? ''),
            'eventImageUrl' => (string) ($event['image_url'] ?? ''),
            'transactionId' => (string) ($transaction['transaction_id'] ?? ''),
            'checkedInAt' => (string) ($context['checkedInAt'] ?? ''),
            'admittedCount' => (int) ($context['admittedCount'] ?? 1),
            'attendeeNames' => is_array($context['admittedGuestNames'] ?? null) ? $context['admittedGuestNames'] : [],
            'remainingEntries' => (int) ($context['remainingEntries'] ?? 0),
            'bookingType' => strtolower((string) ($transaction['gateway'] ?? 'free')) === 'free' ? 'Free Entry' : 'Paid Booking',
            'verificationUrl' => $this->buildVerificationUrl((string) ($transaction['transaction_id'] ?? '')),
        ]);
    }

    private function formatEventDateLabel(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }

        $ts = strtotime($date);
        return $ts ? date('d M Y', $ts) : $date;
    }

    private function formatEventTimeLabel(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '';
        }

        $ts = strtotime($time);
        return $ts ? date('g:i A', $ts) : $time;
    }

    private function formatDateTimeLabel(string $dateTime): string
    {
        $dateTime = trim($dateTime);
        if ($dateTime === '') {
            return '';
        }

        $ts = strtotime($dateTime);
        return $ts ? date('d M Y g:i A', $ts) : $dateTime;
    }

    private function buildCheckinPreview(array $normalized, bool $requireSignedPayload): array
    {
        $duplicateKey = $normalized['guestId'] !== ''
            ? ($normalized['transactionId'] . '::' . $normalized['guestId'])
            : $normalized['transactionId'];

        if ($normalized['transactionId'] === '') {
            return ['ok' => false, 'error' => 'INVALID_QR', 'message' => 'Transaction id is required.'];
        }

        $txn = $this->transactions->findByTransactionId($normalized['transactionId']);
        if (!$txn) {
            return ['ok' => false, 'error' => 'TX_NOT_FOUND', 'message' => 'Transaction not found.'];
        }

        $txEventId = trim((string) ($txn['event_id'] ?? ''));
        $txPaymentId = trim((string) ($txn['payment_id'] ?? ''));
        $eventId = $normalized['eventId'] !== '' ? $normalized['eventId'] : $txEventId;
        $paymentId = $normalized['paymentId'] !== '' ? $normalized['paymentId'] : $txPaymentId;

        if ($requireSignedPayload && ($eventId === '' || $paymentId === '' || $normalized['signature'] === '')) {
            return ['ok' => false, 'error' => 'INVALID_QR', 'message' => 'Incomplete QR data.'];
        }

        if (($normalized['eventId'] !== '' && $txEventId !== $normalized['eventId'])
            || ($normalized['paymentId'] !== '' && $txPaymentId !== $normalized['paymentId'])) {
            return ['ok' => false, 'error' => 'QR_TX_MISMATCH', 'message' => 'QR does not match transaction data.'];
        }

        if ($normalized['signature'] !== '') {
            $secret = $this->getEventQrSigningSecret();
            if ($secret === '') {
                return ['ok' => false, 'error' => 'SIGNING_SECRET_MISSING', 'message' => 'EVENT_QR_SIGNING_SECRET is not configured.'];
            }

            $raw = $normalized['transactionId'] . '|' . $eventId . '|' . $paymentId . '|' . $normalized['guestId'];
            $expected = hash_hmac('sha256', $raw, $secret);
            if (!hash_equals(strtolower($expected), strtolower($normalized['signature']))) {
                return ['ok' => false, 'error' => 'TAMPERED_QR', 'message' => 'QR signature validation failed.'];
            }
        }

        $attendeeNames = $this->getTransactionAttendeeNames($txn);
        $history = $this->buildCheckinHistoryForTransaction($txn, $attendeeNames);
        $remainingNames = $history['remainingNames'];
        $checkedInCount = $history['checkedInCount'];
        $qty = max(1, (int) ($txn['qty'] ?? 1));
        $remainingEntries = max(0, $qty - $checkedInCount);
        $checkInStatus = $remainingEntries <= 0 ? 'checked_in' : (($checkedInCount > 0) ? 'partial' : (string) ($txn['checkin_status'] ?? 'pending'));

        return [
            'ok' => true,
            'previewOnly' => true,
            'transactionId' => $normalized['transactionId'],
            'eventId' => (string) ($txn['event_id'] ?? ''),
            'eventTitle' => (string) ($txn['event_title'] ?? ''),
            'customerName' => (string) ($txn['customer_name'] ?? ''),
            'customerEmail' => (string) ($txn['customer_email'] ?? ''),
            'customerPhone' => (string) ($txn['customer_phone'] ?? ''),
            'status' => (string) ($txn['status'] ?? ''),
            'checkInStatus' => $checkInStatus,
            'checkedInCount' => $checkedInCount,
            'qty' => $qty,
            'remainingEntries' => $remainingEntries,
            'attendeeNames' => $attendeeNames,
            'remainingAttendeeNames' => $remainingNames,
            'checkinHistory' => $history['history'],
            'duplicateKey' => $duplicateKey,
            'message' => $remainingEntries > 0
                ? ('Review the guest list and confirm only the remaining guest' . ($remainingEntries === 1 ? '' : 's') . '. ' . $remainingEntries . ' entr' . ($remainingEntries === 1 ? 'y remains.' : 'ies remain.'))
                : 'Check-in already done. No more check-in allowed for this ticket.',
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

        $startDate = trim((string) ($data['startDate'] ?? $data['start_date'] ?? '')) ?: null;
        $startTime = trim((string) ($data['startTime'] ?? $data['start_time'] ?? '')) ?: null;
        $endDate = trim((string) ($data['endDate'] ?? $data['end_date'] ?? '')) ?: null;
        $endTime = trim((string) ($data['endTime'] ?? $data['end_time'] ?? '')) ?: null;

        return [
            'event_id'              => $eventId,
            'title'                 => trim((string) ($data['title'] ?? '')),
            'subtitle'              => $this->buildEventSubtitle($startDate, $startTime, $timeFormat),
            'description'           => trim((string) ($data['description'] ?? '')),
            'image_url'             => trim((string) ($data['imageUrl'] ?? $data['image_url'] ?? '')),
            'video_url'             => trim((string) ($data['videoUrl'] ?? $data['video_url'] ?? '')),
            'show_video'            => !empty($data['showVideo']) || !empty($data['show_video']),
            'cta_text'              => trim((string) ($data['ctaText'] ?? $data['cta_text'] ?? '')),
            'cta_url'               => trim((string) ($data['ctaUrl'] ?? $data['cta_url'] ?? '')),
            'badge_text'            => trim((string) ($data['badgeText'] ?? $data['badge_text'] ?? '')),
            'start_date'            => $startDate,
            'start_time'            => $startTime,
            'end_date'              => $endDate,
            'end_time'              => $endTime,
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

    private function buildEventSubtitle(?string $startDate, ?string $startTime, string $timeFormat): string
    {
        if (!$startDate) {
            return '';
        }

        try {
            $date = new \DateTimeImmutable(
                $startDate . ' ' . ($startTime ?: '00:00:00'),
                new \DateTimeZone('Asia/Kolkata')
            );
        } catch (\Throwable) {
            return '';
        }

        $day = (int) $date->format('j');
        $month = $date->format('F');
        if ($startTime) {
            $time = $date->format($timeFormat === '24h' ? 'H:i' : 'g:i A');
            return sprintf('%s %s, %s onwards', $this->ordinalDay($day), $month, $time);
        }

        return sprintf('%s %s', $this->ordinalDay($day), $month);
    }

    private function ordinalDay(int $day): string
    {
        $mod100 = $day % 100;
        if ($mod100 >= 11 && $mod100 <= 13) {
            return $day . 'th';
        }

        return match ($day % 10) {
            1 => $day . 'st',
            2 => $day . 'nd',
            3 => $day . 'rd',
            default => $day . 'th',
        };
    }

    private function prepareCustomerRegistration(array $data, bool $forPaid): array
    {
        $this->syncExpiredActiveEvents();

        $eventId = trim((string) ($data['eventId'] ?? $data['event_id'] ?? $data['id'] ?? ''));
        if ($eventId === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'eventId is required.'];
        }

        $event = $this->repo->findByEventId($eventId);
        if (!$event || (int) ($event['is_active'] ?? 0) !== 1 || $this->isEventExpired($event)) {
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

        if ($name === '' || !Validator::phone($phone)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Valid customer name and phone are required.'];
        }

        if ($email === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Email address is required. Please provide the correct email address for registration.'];
        }

        if (!Validator::email($email)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Email address is invalid. Please enter a correct email address.'];
        }

        $verificationToken = trim((string) ($data['verificationToken'] ?? $data['otpVerificationToken'] ?? ''));
        if (!$this->otpService->hasValidVerificationToken($eventId, $email, $verificationToken)) {
            return ['ok' => false, 'error' => 'OTP_VERIFICATION_REQUIRED', 'message' => 'Verify your email with OTP before continuing with registration.'];
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
                $autoEmailResult = null;
                $existingEmail = trim((string) ($existing['customer_email'] ?? ''));
                $existingEmailStatus = strtolower(trim((string) ($existing['email_status'] ?? 'pending')));
                if ($existingEmail !== '' && $existingEmailStatus !== 'sent') {
                    $autoEmailResult = $this->sendTransactionConfirmationEmail(
                        is_array($event) ? $event : [],
                        $existing,
                        strtolower((string) ($existing['gateway'] ?? 'free')) === 'free',
                        true
                    );
                }

                return [
                    'ok' => false,
                    'error' => 'ALREADY_REGISTERED',
                    'message' => !empty($autoEmailResult['ok'])
                        ? 'You have already registered for this event. Your confirmation email has been sent again automatically.'
                        : 'You have already registered for this event.',
                    'canResendEmail' => trim((string) ($existing['customer_email'] ?? '')) !== '',
                    'emailSent' => !empty($autoEmailResult['ok']),
                    'emailStatus' => !empty($autoEmailResult['ok']) ? 'Sent' : (string) (($existing['email_status'] ?? 'Pending') ?: 'Pending'),
                    'emailSentAt' => !empty($autoEmailResult['ok']) ? date('Y-m-d H:i:s') : (string) ($existing['email_sent_at'] ?? ''),
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

    private function syncExpiredActiveEvents(): void
    {
        $rows = $this->repo->listAllActive();
        foreach ($rows as $row) {
            if ($this->isEventExpired($row)) {
                $eventId = trim((string) ($row['event_id'] ?? ''));
                if ($eventId !== '') {
                    $this->repo->setActiveByEventId($eventId, false);
                }
            }
        }
    }

    private function canEventRemainActive(array $event, ?bool $targetActive = null): bool
    {
        $wantsActive = $targetActive;
        if ($wantsActive === null) {
            $wantsActive = (int) ($event['is_active'] ?? 0) === 1 || !empty($event['isActive']);
        }

        if (!$wantsActive) {
            return true;
        }

        return !$this->isEventExpired($event);
    }

    private function isEventExpired(array $event): bool
    {
        $expiry = $this->resolveEventExpiryAt($event);
        if ($expiry === null) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        return $expiry <= $now;
    }

    private function resolveEventExpiryAt(array $event): ?\DateTimeImmutable
    {
        $primaryDate = trim((string) ($event['end_date'] ?? $event['endDate'] ?? ''));
        $primaryTime = trim((string) ($event['end_time'] ?? $event['endTime'] ?? ''));

        if ($primaryDate === '') {
            $primaryDate = trim((string) ($event['start_date'] ?? $event['startDate'] ?? ''));
            $primaryTime = trim((string) ($event['start_time'] ?? $event['startTime'] ?? ''));
        }

        if ($primaryDate === '') {
            return null;
        }

        if ($primaryTime === '') {
            $primaryTime = '23:59:59';
        }

        try {
            return new \DateTimeImmutable(
                $primaryDate . ' ' . $primaryTime,
                new \DateTimeZone('Asia/Kolkata')
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function generateTransactionId(string $prefix): string
    {
        return strtoupper($prefix) . '-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    private function buildEventQrBundle(string $transactionId, string $eventId, string $paymentId, string $guestId = ''): array
    {
        $safeGuestId = trim($guestId);
        $signingSecret = $this->getEventQrSigningSecret();
        $raw = $transactionId . '|' . $eventId . '|' . $paymentId . '|' . $safeGuestId;
        $signature = hash_hmac('sha256', $raw, $signingSecret);

        $params = [
            'tx' => $transactionId,
            'eventId' => $eventId,
            'paymentId' => $paymentId,
            'sig' => $signature,
        ];

        if ($safeGuestId !== '') {
            $params['guestId'] = $safeGuestId;
        }

        $verificationUrl = 'https://namastekalyan.asianwokandgrill.in/events/verification.html?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=600x600&data=' . rawurlencode($verificationUrl);

        return [
            'qrUrl' => $qrUrl,
            'verificationUrl' => $verificationUrl,
            'signature' => $signature,
        ];
    }

    private function buildVerificationUrl(string $transactionId): string
    {
           $base = rtrim(SiteUrl::resolve('home'), '/');

           return $base . '/events/verification.html?transactionId=' . rawurlencode($transactionId);
    }

    private function getEventQrSigningSecret(): string
    {
        $candidates = [
            trim($this->apiSettings->getValue('EVENT_QR_SIGNING_SECRET')),
            trim((string) ($_ENV['EVENT_QR_SIGNING_SECRET'] ?? '')),
            trim((string) ($_ENV['ADMIN_PANEL_PASSCODE'] ?? '')),
            trim((string) ($_ENV['BOOTSTRAP_SUPERADMIN_PASSWORD'] ?? '')),
            trim((string) ($_ENV['JWT_SECRET'] ?? '')),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function getEventEntryPasscode(): string
    {
        $settingsFile = dirname(__DIR__, 2) . '/config/app-settings.json';
        if (is_file($settingsFile)) {
            $raw = @file_get_contents($settingsFile);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $configured = strtoupper(trim((string) ($decoded['eventEntryPasscode'] ?? $decoded['menuBlockerStaffCode'] ?? '')));
                if ($configured !== '') {
                    return $configured;
                }
            }
        }

        $fallbacks = [
            trim((string) ($_ENV['EVENT_ENTRY_PASSCODE'] ?? '')),
            trim((string) ($_ENV['ADMIN_PANEL_PASSCODE'] ?? '')),
        ];

        foreach ($fallbacks as $fallback) {
            $value = strtoupper($fallback);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function normalizeVerifiedBy(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', trim($value));
        $normalized = is_string($normalized) ? $normalized : '';
        return $normalized !== '' ? substr($normalized, 0, 60) : 'staff';
    }

    private function buildTransactionEmailPayload(array $event, array $transaction, bool $isFreeRegistration): array
    {
        $attendeeNames = $this->decodeAttendeeNames((string) ($transaction['attendee_details'] ?? ''));

        return [
            'customerName' => (string) ($transaction['customer_name'] ?? ''),
            'customerEmail' => (string) ($transaction['customer_email'] ?? ''),
            'eventTitle' => (string) (($event['title'] ?? '') ?: ($transaction['event_title'] ?? '')),
            'eventSubtitle' => (string) ($event['subtitle'] ?? ''),
            'eventImageUrl' => (string) ($event['image_url'] ?? ''),
            'transactionId' => (string) ($transaction['transaction_id'] ?? ''),
            'qty' => max(1, (int) ($transaction['qty'] ?? 1)),
            'amount' => (float) ($transaction['amount'] ?? 0),
            'currency' => (string) ($transaction['currency'] ?? 'INR'),
            'eventStartAt' => $this->combineEventDateTime((string) ($event['start_date'] ?? ''), (string) ($event['start_time'] ?? '')),
            'eventEndAt' => $this->combineEventDateTime((string) ($event['end_date'] ?? ''), (string) ($event['end_time'] ?? '')),
            'createdAt' => (string) ($transaction['created_at'] ?? ''),
            'paidAt' => (string) (($transaction['paid_at'] ?? '') ?: ($transaction['created_at'] ?? '')),
            'qrUrl' => (string) ($transaction['qr_url'] ?? ''),
            'verificationUrl' => $this->buildVerificationUrl((string) ($transaction['transaction_id'] ?? '')),
            'attendeeNames' => $attendeeNames,
            'isFreeRegistration' => $isFreeRegistration,
            'policyText' => $isFreeRegistration
                ? 'Free entry registration confirmed.'
                : (string) (($event['refund_policy'] ?? '') ?: ($_ENV['EVENT_NO_REFUND_POLICY'] ?? 'No refund once pass is purchased.')),
        ];
    }

    private function sendTransactionConfirmationEmail(array $event, array $transaction, bool $isFreeRegistration, bool $updateEmailStatus = false): array
    {
        $emailAddress = trim((string) ($transaction['customer_email'] ?? ''));
        if ($emailAddress === '') {
            $result = [
                'ok' => false,
                'error' => 'EMAIL_REQUIRED',
                'message' => 'No registered email address is available for this transaction.',
            ];
            if ($updateEmailStatus && trim((string) ($transaction['transaction_id'] ?? '')) !== '') {
                $this->transactions->setEmailSent((string) $transaction['transaction_id'], false);
            }
            return $result;
        }

        $payload = $this->buildTransactionEmailPayload($event, $transaction, $isFreeRegistration);
        $result = $this->mailer->sendEventConfirmation($payload);
        if (empty($result['ok'])) {
            $result = $this->mailer->sendEventConfirmation($payload);
        }

        if ($updateEmailStatus && trim((string) ($transaction['transaction_id'] ?? '')) !== '') {
            $this->transactions->setEmailSent((string) $transaction['transaction_id'], !empty($result['ok']));
        }

        return $result;
    }

    private function combineEventDateTime(string $date, string $time): string
    {
        $dateValue = trim($date);
        if ($dateValue === '') {
            return '';
        }

        $timeValue = trim($time);
        if ($timeValue === '') {
            $timeValue = '00:00:00';
        }

        return trim($dateValue . ' ' . $timeValue);
    }

    private function extractAttendeeNames(array $attendees): array
    {
        $names = [];
        foreach ($attendees as $attendee) {
            if (!is_array($attendee)) {
                continue;
            }
            $value = trim((string) ($attendee['name'] ?? ''));
            if ($value !== '') {
                $names[] = $value;
            }
        }

        return $names;
    }

    private function getTransactionAttendeeNames(array $transaction): array
    {
        $names = $this->decodeAttendeeNames((string) ($transaction['attendee_details'] ?? ''));
        if ($names !== []) {
            return $names;
        }

        $qty = max(1, (int) ($transaction['qty'] ?? 1));
        $customerName = trim((string) ($transaction['customer_name'] ?? 'Guest')) ?: 'Guest';
        $generated = [];
        for ($index = 0; $index < $qty; $index++) {
            $generated[] = $qty === 1 ? $customerName : ($customerName . ' #' . ($index + 1));
        }

        return $generated;
    }

    private function groupCheckinLogsByTransactionId(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $transactionId = trim((string) ($row['transaction_id'] ?? ''));
            if ($transactionId === '') {
                continue;
            }
            if (!isset($grouped[$transactionId])) {
                $grouped[$transactionId] = [];
            }
            $grouped[$transactionId][] = $row;
        }

        return $grouped;
    }

    private function buildCheckinHistoryForTransaction(array $transaction, array $attendeeNames, array $logRows = []): array
    {
        $storedCheckedInCount = max(0, (int) ($transaction['checked_in_count'] ?? 0));
        if ($logRows === []) {
            $transactionId = trim((string) ($transaction['transaction_id'] ?? ''));
            if ($transactionId !== '') {
                $logRows = $this->checkinLogs->listByTransactionIds([$transactionId]);
            }
        }

        $remainingNames = array_values($attendeeNames);
        $history = [];
        $checkedInCount = 0;

        foreach ($logRows as $index => $row) {
            $guestNames = json_decode((string) ($row['guest_names_json'] ?? '[]'), true);
            $guestNames = is_array($guestNames)
                ? array_values(array_filter(array_map(static fn($value) => trim((string) $value), $guestNames), static fn($value) => $value !== ''))
                : [];
            $admittedCount = max(0, (int) ($row['admitted_count'] ?? count($guestNames)));
            $checkedInCount += $admittedCount;
            $remainingNames = $this->removeGuestNamesFromPool($remainingNames, $guestNames);
            $history[] = [
                'scanNumber' => $index + 1,
                'admittedCount' => $admittedCount,
                'guestNames' => $guestNames,
                'verifiedBy' => (string) ($row['verified_by'] ?? ''),
                'createdAt' => (string) ($row['created_at'] ?? ''),
                'source' => (string) ($row['source'] ?? 'scanner'),
            ];
        }

                if ($history === [] && $storedCheckedInCount > 0) {
                    $remainingNames = array_slice($remainingNames, min(count($remainingNames), $storedCheckedInCount));
                    $checkedInCount = $storedCheckedInCount;
                    $history[] = [
                            'scanNumber' => 1,
                            'admittedCount' => $storedCheckedInCount,
                            'guestNames' => [],
                            'verifiedBy' => (string) ($transaction['verified_by'] ?? ''),
                            'createdAt' => (string) ($transaction['checked_in_at'] ?? ''),
                            'source' => 'legacy',
                    ];
                }

        return [
            'checkedInCount' => $checkedInCount,
            'remainingNames' => array_values($remainingNames),
            'history' => $history,
        ];
    }

    private function removeGuestNamesFromPool(array $pool, array $usedNames): array
    {
        $remaining = array_values($pool);
        foreach ($usedNames as $usedName) {
            $needle = trim((string) $usedName);
            if ($needle === '') {
                continue;
            }
            foreach ($remaining as $index => $candidate) {
                if (strcasecmp((string) $candidate, $needle) === 0) {
                    unset($remaining[$index]);
                    break;
                }
            }
        }

        return array_values($remaining);
    }

    private function normalizeSelectedGuestNames(array $transaction, array $remainingNames, mixed $input): array
    {
        if (!is_array($input)) {
            return ['guestNames' => []];
        }

        $selected = array_values(array_filter(array_map(static fn($value) => trim((string) $value), $input), static fn($value) => $value !== ''));
        if ($selected === []) {
            return ['guestNames' => []];
        }

        $pool = array_values($remainingNames);
        foreach ($selected as $name) {
            $matched = false;
            foreach ($pool as $index => $candidate) {
                if (strcasecmp((string) $candidate, $name) === 0) {
                    unset($pool[$index]);
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return ['error' => 'Selected guest names must belong to the remaining names on this QR.'];
            }
            $pool = array_values($pool);
        }

        return ['guestNames' => $selected];
    }

    private function summarizeCheckinHistory(array $history): string
    {
        if ($history === []) {
            return '-';
        }

        $parts = [];
        foreach ($history as $entry) {
            $admittedCount = max(0, (int) ($entry['admittedCount'] ?? 0));
            $createdAt = trim((string) ($entry['createdAt'] ?? ''));
            $timeLabel = $createdAt !== '' ? date('d M, h:i A', strtotime($createdAt) ?: time()) : '-';
            $parts[] = 'Scan ' . (int) ($entry['scanNumber'] ?? 0) . ': ' . $admittedCount . ' guest(s) at ' . $timeLabel;
        }

        return implode(' | ', $parts);
    }

    private function decodeAttendeeNames(string $attendeeDetails): array
    {
        $decoded = json_decode($attendeeDetails, true);
        if (is_array($decoded)) {
            return $this->extractAttendeeNames($decoded);
        }

        return [];
    }
}
