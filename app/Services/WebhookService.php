<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Repositories\EventTransactionRepository;

class WebhookService
{
    private RazorpayService $razorpay;
    private EventTransactionRepository $transactions;

    public function __construct()
    {
        $this->razorpay = new RazorpayService();
        $this->transactions = new EventTransactionRepository();
    }

    public function handleRazorpay(array $data, string $signatureHeader): array
    {
        $rawBody = (string) ($data['_rawBody'] ?? '');

        if (!$this->razorpay->verifyWebhookSignature($rawBody, $signatureHeader)) {
            return [
                'ok' => false,
                'error' => 'SIGNATURE_MISMATCH',
                'message' => 'Invalid webhook signature.',
            ];
        }

        $eventType = (string) ($data['event'] ?? '');
        $entity = $data['payload']['payment']['entity'] ?? null;
        if (!is_array($entity)) {
            return [
                'ok' => true,
                'action' => 'razorpay_webhook',
                'message' => 'Ignored webhook: no payment entity.',
            ];
        }

        $orderId = trim((string) ($entity['order_id'] ?? ''));
        $paymentId = trim((string) ($entity['id'] ?? ''));

        if ($orderId === '') {
            return [
                'ok' => true,
                'action' => 'razorpay_webhook',
                'message' => 'Ignored webhook: missing order_id.',
            ];
        }

        $txn = $this->transactions->findByOrderId($orderId);
        if (!$txn) {
            return [
                'ok' => true,
                'action' => 'razorpay_webhook',
                'message' => 'No matching transaction for order_id.',
                'orderId' => $orderId,
            ];
        }

        if ($eventType === 'payment.captured' || $eventType === 'order.paid') {
            $this->transactions->markPaid((string) $txn['transaction_id'], $paymentId, 'paid');
        } elseif ($eventType === 'payment.failed') {
            $this->transactions->setStatus((string) $txn['transaction_id'], 'payment_failed');
        }

        return [
            'ok' => true,
            'action' => 'razorpay_webhook',
            'message' => 'Webhook processed.',
            'event' => $eventType,
            'transactionId' => (string) ($txn['transaction_id'] ?? ''),
        ];
    }
}
