<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Services\EventService;

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
        $payload = array_merge($query, $body);
        return $service->adminCreateEvent($payload);
    }

    public static function adminUpdateEvent(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminUpdateEvent($payload);
    }

    public static function adminToggleEvent(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminToggleEvent($payload);
    }

    public static function adminDeleteEvent(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminDeleteEvent($payload);
    }

    public static function adminCloneEvent(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminCloneEvent($payload);
    }

    public static function adminUploadEventImage(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        $tmpPath = '';

        if (isset($_FILES['file']) && is_array($_FILES['file'])) {
            $tmpPath = (string) ($_FILES['file']['tmp_name'] ?? '');
        }

        return $service->adminUploadEventImage($payload, $tmpPath);
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

    public static function sendEventOtp(array $body, array $query): array
    {
        $service = new EventService();
        return $service->sendEventOtp($body);
    }

    public static function verifyEventOtp(array $body, array $query): array
    {
        $service = new EventService();
        return $service->verifyEventOtp($body);
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
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->eventGuestReport($payload);
    }

    public static function eventTransactionsReport(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->eventTransactionsReport($payload);
    }

    public static function adminMailLogReport(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminMailLogReport($payload);
    }

    public static function adminSmtpHealth(array $body, array $query): array
    {
        $service = new EventService();
        $payload = array_merge($query, $body);
        return $service->adminSmtpHealth($payload);
    }
}
