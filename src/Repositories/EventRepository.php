<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class EventRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function listActive(int $limit): array
    {
        $sql = 'SELECT *
                FROM events
                WHERE is_active = 1
                ORDER BY start_date ASC, start_time ASC, priority DESC
                LIMIT :lim';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function listAll(): array
    {
        $sql = 'SELECT *
                FROM events
                ORDER BY start_date DESC, start_time DESC, id DESC';

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll() ?: [];
    }

    public function findByEventId(string $eventId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM events WHERE event_id = :event_id LIMIT 1');
        $stmt->execute([':event_id' => $eventId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getPopupEvent(): ?array
    {
        $sql = 'SELECT *
                FROM events
                WHERE is_active = 1
                  AND popup_enabled = 1
                ORDER BY start_date ASC, start_time ASC, priority DESC
                LIMIT 1';

        $stmt = $this->db->query($sql);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO events (
                    event_id, title, subtitle, description, image_url, video_url,
                    show_video, cta_text, cta_url, badge_text,
                    start_date, start_time, end_date, end_time, time_display_format,
                    is_active, priority, popup_enabled, show_once_per_session,
                    popup_delay_hours, popup_cooldown_hours,
                    event_type, ticket_price, currency, max_tickets,
                    payment_enabled, cancellation_policy, refund_policy,
                    created_at
                ) VALUES (
                    :event_id, :title, :subtitle, :description, :image_url, :video_url,
                    :show_video, :cta_text, :cta_url, :badge_text,
                    :start_date, :start_time, :end_date, :end_time, :time_display_format,
                    :is_active, :priority, :popup_enabled, :show_once_per_session,
                    :popup_delay_hours, :popup_cooldown_hours,
                    :event_type, :ticket_price, :currency, :max_tickets,
                    :payment_enabled, :cancellation_policy, :refund_policy,
                    :created_at
                )';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':event_id'              => $payload['event_id'],
            ':title'                 => $payload['title'],
            ':subtitle'              => $payload['subtitle'] ?? '',
            ':description'           => $payload['description'] ?? '',
            ':image_url'             => $payload['image_url'] ?? '',
            ':video_url'             => $payload['video_url'] ?? '',
            ':show_video'            => !empty($payload['show_video']) ? 1 : 0,
            ':cta_text'              => $payload['cta_text'] ?? '',
            ':cta_url'               => $payload['cta_url'] ?? '',
            ':badge_text'            => $payload['badge_text'] ?? '',
            ':start_date'            => $payload['start_date'] ?? null,
            ':start_time'            => $payload['start_time'] ?? null,
            ':end_date'              => $payload['end_date'] ?? null,
            ':end_time'              => $payload['end_time'] ?? null,
            ':time_display_format'   => $payload['time_display_format'] ?? '12h',
            ':is_active'             => !empty($payload['is_active']) ? 1 : 0,
            ':priority'              => (int) ($payload['priority'] ?? 0),
            ':popup_enabled'         => !empty($payload['popup_enabled']) ? 1 : 0,
            ':show_once_per_session' => !empty($payload['show_once_per_session']) ? 1 : 0,
            ':popup_delay_hours'     => (float) ($payload['popup_delay_hours'] ?? 0),
            ':popup_cooldown_hours'  => (float) ($payload['popup_cooldown_hours'] ?? 24),
            ':event_type'            => $payload['event_type'] ?? 'free',
            ':ticket_price'          => (float) ($payload['ticket_price'] ?? 0),
            ':currency'              => $payload['currency'] ?? 'INR',
            ':max_tickets'           => (int) ($payload['max_tickets'] ?? 0),
            ':payment_enabled'       => !empty($payload['payment_enabled']) ? 1 : 0,
            ':cancellation_policy'   => $payload['cancellation_policy'] ?? '',
            ':refund_policy'         => $payload['refund_policy'] ?? '',
            ':created_at'            => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updateByEventId(string $eventId, array $payload): void
    {
        $sql = 'UPDATE events
                SET title = :title,
                    subtitle = :subtitle,
                    description = :description,
                    image_url = :image_url,
                    video_url = :video_url,
                    show_video = :show_video,
                    cta_text = :cta_text,
                    cta_url = :cta_url,
                    badge_text = :badge_text,
                    start_date = :start_date,
                    start_time = :start_time,
                    end_date = :end_date,
                    end_time = :end_time,
                    time_display_format = :time_display_format,
                    is_active = :is_active,
                    priority = :priority,
                    popup_enabled = :popup_enabled,
                    show_once_per_session = :show_once_per_session,
                    popup_delay_hours = :popup_delay_hours,
                    popup_cooldown_hours = :popup_cooldown_hours,
                    event_type = :event_type,
                    ticket_price = :ticket_price,
                    currency = :currency,
                    max_tickets = :max_tickets,
                    payment_enabled = :payment_enabled,
                    cancellation_policy = :cancellation_policy,
                    refund_policy = :refund_policy,
                    updated_at = :updated_at
                WHERE event_id = :event_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title'                 => $payload['title'],
            ':subtitle'              => $payload['subtitle'] ?? '',
            ':description'           => $payload['description'] ?? '',
            ':image_url'             => $payload['image_url'] ?? '',
            ':video_url'             => $payload['video_url'] ?? '',
            ':show_video'            => !empty($payload['show_video']) ? 1 : 0,
            ':cta_text'              => $payload['cta_text'] ?? '',
            ':cta_url'               => $payload['cta_url'] ?? '',
            ':badge_text'            => $payload['badge_text'] ?? '',
            ':start_date'            => $payload['start_date'] ?? null,
            ':start_time'            => $payload['start_time'] ?? null,
            ':end_date'              => $payload['end_date'] ?? null,
            ':end_time'              => $payload['end_time'] ?? null,
            ':time_display_format'   => $payload['time_display_format'] ?? '12h',
            ':is_active'             => !empty($payload['is_active']) ? 1 : 0,
            ':priority'              => (int) ($payload['priority'] ?? 0),
            ':popup_enabled'         => !empty($payload['popup_enabled']) ? 1 : 0,
            ':show_once_per_session' => !empty($payload['show_once_per_session']) ? 1 : 0,
            ':popup_delay_hours'     => (float) ($payload['popup_delay_hours'] ?? 0),
            ':popup_cooldown_hours'  => (float) ($payload['popup_cooldown_hours'] ?? 24),
            ':event_type'            => $payload['event_type'] ?? 'free',
            ':ticket_price'          => (float) ($payload['ticket_price'] ?? 0),
            ':currency'              => $payload['currency'] ?? 'INR',
            ':max_tickets'           => (int) ($payload['max_tickets'] ?? 0),
            ':payment_enabled'       => !empty($payload['payment_enabled']) ? 1 : 0,
            ':cancellation_policy'   => $payload['cancellation_policy'] ?? '',
            ':refund_policy'         => $payload['refund_policy'] ?? '',
            ':updated_at'            => date('Y-m-d H:i:s'),
            ':event_id'              => $eventId,
        ]);
    }

    public function setActiveByEventId(string $eventId, bool $isActive): void
    {
        $sql = 'UPDATE events
                SET is_active = :is_active, updated_at = :updated_at
                WHERE event_id = :event_id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':is_active'  => $isActive ? 1 : 0,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':event_id'   => $eventId,
        ]);
    }
}
