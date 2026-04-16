<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Services\WebhookService;

class WebhookController
{
    public static function razorpayWebhook(array $body, array $query): array
    {
        $service = new WebhookService();
        $signature = (string) ($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '');

        return $service->handleRazorpay($body, $signature);
    }
}
