<?php

declare(strict_types=1);

namespace NK\Repositories;

use NK\Config\Database;
use PDO;

class QrRedirectRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function listAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM qr_redirects ORDER BY is_system DESC, created_at DESC, id DESC');
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM qr_redirects WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM qr_redirects WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function findByLegacyChannel(string $legacyChannel): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM qr_redirects WHERE legacy_channel = :legacy_channel LIMIT 1');
        $stmt->execute([':legacy_channel' => $legacyChannel]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null && $excludeId > 0) {
            $stmt = $this->db->prepare('SELECT 1 FROM qr_redirects WHERE slug = :slug AND id <> :id LIMIT 1');
            $stmt->execute([':slug' => $slug, ':id' => $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT 1 FROM qr_redirects WHERE slug = :slug LIMIT 1');
            $stmt->execute([':slug' => $slug]);
        }

        return (bool) $stmt->fetchColumn();
    }

    public function create(array $payload): int
    {
        $sql = 'INSERT INTO qr_redirects (
                    name, slug, redirect_mode, preset_key, manual_url, legacy_channel, notes,
                    is_active, is_system, created_at, created_by, updated_at, updated_by
                ) VALUES (
                    :name, :slug, :redirect_mode, :preset_key, :manual_url, :legacy_channel, :notes,
                    :is_active, :is_system, :created_at, :created_by, :updated_at, :updated_by
                )';

        $stmt = $this->db->prepare($sql);
        $now = $payload['created_at'] ?? date('Y-m-d H:i:s');
        $stmt->execute([
            ':name' => $payload['name'],
            ':slug' => $payload['slug'],
            ':redirect_mode' => $payload['redirect_mode'],
            ':preset_key' => $payload['preset_key'] ?? null,
            ':manual_url' => $payload['manual_url'] ?? null,
            ':legacy_channel' => $payload['legacy_channel'] ?? null,
            ':notes' => $payload['notes'] ?? null,
            ':is_active' => (int) ($payload['is_active'] ?? 1),
            ':is_system' => (int) ($payload['is_system'] ?? 0),
            ':created_at' => $now,
            ':created_by' => $payload['created_by'] ?? null,
            ':updated_at' => $payload['updated_at'] ?? $now,
            ':updated_by' => $payload['updated_by'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $payload): void
    {
        $sql = 'UPDATE qr_redirects SET
                    name = :name,
                    slug = :slug,
                    redirect_mode = :redirect_mode,
                    preset_key = :preset_key,
                    manual_url = :manual_url,
                    notes = :notes,
                    is_active = :is_active,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':name' => $payload['name'],
            ':slug' => $payload['slug'],
            ':redirect_mode' => $payload['redirect_mode'],
            ':preset_key' => $payload['preset_key'] ?? null,
            ':manual_url' => $payload['manual_url'] ?? null,
            ':notes' => $payload['notes'] ?? null,
            ':is_active' => (int) ($payload['is_active'] ?? 1),
            ':updated_at' => $payload['updated_at'] ?? date('Y-m-d H:i:s'),
            ':updated_by' => $payload['updated_by'] ?? null,
        ]);
    }

    public function setActive(int $id, bool $isActive, ?string $updatedBy = null): void
    {
        $stmt = $this->db->prepare('UPDATE qr_redirects SET is_active = :is_active, updated_at = :updated_at, updated_by = :updated_by WHERE id = :id');
        $stmt->execute([
            ':id' => $id,
            ':is_active' => $isActive ? 1 : 0,
            ':updated_at' => date('Y-m-d H:i:s'),
            ':updated_by' => $updatedBy,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM qr_redirects WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}