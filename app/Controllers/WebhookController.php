<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Services\WebhookService;
use NK\Services\WhatsAppCloudService;

class WebhookController
{
    public static function razorpayWebhook(array $body, array $query): array
    {
        $service = new WebhookService();
        $signature = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

        return $service->handleRazorpay($body, $signature);
    }

    public static function whatsAppWebhook(array $body, array $query): array
    {
        $service = new WhatsAppCloudService();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
            $result = $service->verifyWebhookChallenge($query);
            if (!($result['ok'] ?? false)) {
                http_response_code(403);
                header('Content-Type: text/plain; charset=utf-8');
                echo (string) ($result['message'] ?? 'Forbidden');
                exit;
            }

            http_response_code(200);
            header('Content-Type: text/plain; charset=utf-8');
            echo (string) ($result['challenge'] ?? '');
            exit;
        }

        return $service->processWebhookPayload($body);
    }
}
