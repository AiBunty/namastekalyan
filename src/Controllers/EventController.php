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
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'verify_event_qr is not implemented yet.'];
    }

    public static function adminPreviewEventQr(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'admin_preview_event_qr is not implemented yet.'];
    }

    public static function adminBatchCheckin(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'admin_batch_checkin_event_qr is not implemented yet.'];
    }

    public static function eventGuestReport(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'event_guest_report is not implemented yet.'];
    }

    public static function eventTransactionsReport(array $body, array $query): array
    {
        return ['ok' => false, 'error' => 'NOT_IMPLEMENTED', 'message' => 'event_transactions_report is not implemented yet.'];
    }
}
