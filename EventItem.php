<?php

declare(strict_types=1);

namespace NK\Models;

class EventItem
{
    public int $id = 0;
    public string $eventId = '';
    public string $title = '';
    public string $subtitle = '';
    public string $description = '';
    public string $imageUrl = '';
    public string $videoUrl = '';
    public bool $showVideo = false;
    public string $ctaText = '';
    public string $ctaUrl = '';
    public string $badgeText = '';
    public ?string $startDate = null;
    public ?string $startTime = null;
    public ?string $endDate = null;
    public ?string $endTime = null;
    public string $timeDisplayFormat = '12h';
    public bool $isActive = true;
    public int $priority = 0;
    public bool $popupEnabled = false;
    public bool $showOncePerSession = false;
    public float $popupDelayHours = 0.0;
    public float $popupCooldownHours = 24.0;
    public string $eventType = 'free';
    public float $ticketPrice = 0.0;
    public string $currency = 'INR';
    public int $maxTickets = 0;
    public bool $paymentEnabled = false;
    public string $cancellationPolicy = '';
    public string $refundPolicy = '';

    public static function fromDb(array $row): self
    {
        $event = new self();
        $event->id = (int) ($row['id'] ?? 0);
        $event->eventId = (string) ($row['event_id'] ?? '');
        $event->title = (string) ($row['title'] ?? '');
        $event->subtitle = (string) ($row['subtitle'] ?? '');
        $event->description = (string) ($row['description'] ?? '');
        $event->imageUrl = (string) ($row['image_url'] ?? '');
        $event->videoUrl = (string) ($row['video_url'] ?? '');
        $event->showVideo = ((int) ($row['show_video'] ?? 0) === 1);
        $event->ctaText = (string) ($row['cta_text'] ?? '');
        $event->ctaUrl = (string) ($row['cta_url'] ?? '');
        $event->badgeText = (string) ($row['badge_text'] ?? '');
        $event->startDate = $row['start_date'] ?? null;
        $event->startTime = $row['start_time'] ?? null;
        $event->endDate = $row['end_date'] ?? null;
        $event->endTime = $row['end_time'] ?? null;
        $event->timeDisplayFormat = (string) ($row['time_display_format'] ?? '12h');
        $event->isActive = ((int) ($row['is_active'] ?? 0) === 1);
        $event->priority = (int) ($row['priority'] ?? 0);
        $event->popupEnabled = ((int) ($row['popup_enabled'] ?? 0) === 1);
        $event->showOncePerSession = ((int) ($row['show_once_per_session'] ?? 0) === 1);
        $event->popupDelayHours = (float) ($row['popup_delay_hours'] ?? 0);
        $event->popupCooldownHours = (float) ($row['popup_cooldown_hours'] ?? 24);
        $event->eventType = (string) ($row['event_type'] ?? 'free');
        $event->ticketPrice = (float) ($row['ticket_price'] ?? 0);
        $event->currency = (string) ($row['currency'] ?? 'INR');
        $event->maxTickets = (int) ($row['max_tickets'] ?? 0);
        $event->paymentEnabled = ((int) ($row['payment_enabled'] ?? 0) === 1);
        $event->cancellationPolicy = (string) ($row['cancellation_policy'] ?? '');
        $event->refundPolicy = (string) ($row['refund_policy'] ?? '');
        return $event;
    }

    public function toPublicArray(bool $detail = false): array
    {
        $startIso = $this->buildIso($this->startDate, $this->startTime);
        $endIso = $this->buildIso($this->endDate, $this->endTime);

        $base = [
            'id'               => $this->eventId,
            'title'            => $this->title,
            'subtitle'         => $this->subtitle,
            'imageUrl'         => $this->imageUrl,
            'videoUrl'         => $this->videoUrl,
            'showVideo'        => $this->showVideo,
            'ctaText'          => $this->ctaText,
            'ctaUrl'           => $this->ctaUrl,
            'badgeText'        => $this->badgeText,
            'startAtIso'       => $startIso,
            'endAtIso'         => $endIso,
            'isActive'         => $this->isActive,
            'priority'         => $this->priority,
            'popupEnabled'     => $this->popupEnabled,
            'showOncePerSession' => $this->showOncePerSession,
            'popupDelayHours'  => $this->popupDelayHours,
            'popupCooldownHours' => $this->popupCooldownHours,
            'eventType'        => $this->eventType,
            'ticketPrice'      => $this->ticketPrice,
            'currency'         => $this->currency,
            'maxTickets'       => $this->maxTickets,
            'paymentEnabled'   => $this->paymentEnabled,
            'cancellationPolicyText' => $this->cancellationPolicy,
            'refundPolicy'     => $this->refundPolicy,
            'timeDisplayFormat' => $this->timeDisplayFormat,
        ];

        if ($detail) {
            $base['description'] = $this->description;
        }

        return $base;
    }

    private function buildIso(?string $date, ?string $time): ?string
    {
        if (!$date) {
            return null;
        }
        $time = $time ?: '00:00:00';
        try {
            $dt = new \DateTimeImmutable(
                $date . ' ' . $time,
                new \DateTimeZone('Asia/Kolkata')
            );
            return $dt->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
    }
}
