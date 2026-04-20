<?php

declare(strict_types=1);

namespace NK\Services;

class RazorpayService
{
    private string $keyId;
    private string $keySecret;
    private string $webhookSecret;

    public function __construct()
    {
        $this->keyId = trim((string) ($_ENV['RAZORPAY_KEY_ID'] ?? ''));
        $this->keySecret = trim((string) ($_ENV['RAZORPAY_KEY_SECRET'] ?? ''));
        $this->webhookSecret = trim((string) ($_ENV['RAZORPAY_WEBHOOK_SECRET'] ?? ''));
    }

    public function isConfigured(): bool
    {
        return $this->keyId !== '' && $this->keySecret !== '';
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    public function createOrder(int $amountInPaise, string $receipt, string $currency = 'INR', array $notes = []): array
    {
        if (!$this->isConfigured()) {
            return [
                'ok' => false,
                'message' => 'Razorpay is not configured.',
            ];
        }

        $payload = [
            'amount' => $amountInPaise,
            'currency' => $currency,
            'receipt' => $receipt,
            'payment_capture' => 1,
            'notes' => $notes,
        ];

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->keyId . ':' . $this->keySecret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error !== '') {
            return [
                'ok' => false,
                'message' => 'Razorpay request failed: ' . $error,
            ];
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded) || empty($decoded['id'])) {
            return [
                'ok' => false,
                'message' => is_array($decoded) && isset($decoded['error']['description'])
                    ? (string) $decoded['error']['description']
                    : 'Razorpay order creation failed.',
                'raw' => $decoded,
            ];
        }

        return [
            'ok' => true,
            'order' => $decoded,
        ];
    }

    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        if ($this->keySecret === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        $payload = $orderId . '|' . $paymentId;
        $expected = hash_hmac('sha256', $payload, $this->keySecret);
        return hash_equals($expected, $signature);
    }

    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        if ($this->webhookSecret === '' || $rawBody === '' || $signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
        return hash_equals($expected, trim($signatureHeader));
    }
}
