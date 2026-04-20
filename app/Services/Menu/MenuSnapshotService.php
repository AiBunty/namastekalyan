<?php

declare(strict_types=1);

namespace NK\Services\Menu;

use NK\Config\Database;
use NK\Repositories\BarMenuRepository;
use NK\Repositories\BarMenuVariantRepository;
use NK\Repositories\FoodMenuRepository;
use NK\Repositories\FoodMenuVariantRepository;
use PDO;

class MenuSnapshotService
{
    private PDO $db;
    private FoodMenuRepository $foodRepo;
    private FoodMenuVariantRepository $foodVariantRepo;
    private BarMenuRepository $barRepo;
    private BarMenuVariantRepository $barVariantRepo;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->foodRepo = new FoodMenuRepository();
        $this->foodVariantRepo = new FoodMenuVariantRepository();
        $this->barRepo = new BarMenuRepository();
        $this->barVariantRepo = new BarMenuVariantRepository();
    }

    public function listSnapshots(?string $sheetType = null): array
    {
        if ($sheetType === null) {
            $stmt = $this->db->query(
                'SELECT id, sheet_type, label, row_count, triggered_by, created_by, created_at
                 FROM menu_snapshots
                 ORDER BY created_at DESC, id DESC'
            );
            return $stmt->fetchAll() ?: [];
        }

        $stmt = $this->db->prepare(
            'SELECT id, sheet_type, label, row_count, triggered_by, created_by, created_at
             FROM menu_snapshots
             WHERE sheet_type = :sheet_type
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([':sheet_type' => $sheetType]);
        return $stmt->fetchAll() ?: [];
    }

    public function getSnapshot(int $snapshotId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM menu_snapshots WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $snapshotId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createSnapshot(string $sheetType, string $label, string $triggeredBy, string $createdBy): int
    {
        $payload = $this->captureState($sheetType);
        $stmt = $this->db->prepare(
            'INSERT INTO menu_snapshots
             (sheet_type, label, snapshot_data, row_count, triggered_by, created_by)
             VALUES
             (:sheet_type, :label, :snapshot_data, :row_count, :triggered_by, :created_by)'
        );
        $stmt->execute([
            ':sheet_type'    => $sheetType,
            ':label'         => $label,
            ':snapshot_data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':row_count'     => (int) count($payload['items'] ?? []),
            ':triggered_by'  => $triggeredBy,
            ':created_by'    => $createdBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function restoreSnapshot(int $snapshotId): array
    {
        $snapshot = $this->getSnapshot($snapshotId);
        if (!$snapshot) {
            throw new \RuntimeException("Snapshot #{$snapshotId} not found.");
        }

        $sheetType = (string) ($snapshot['sheet_type'] ?? '');
        $payload = json_decode((string) ($snapshot['snapshot_data'] ?? ''), true);
        if (!is_array($payload) || !isset($payload['items']) || !is_array($payload['items'])) {
            throw new \RuntimeException('Snapshot payload is invalid.');
        }

        $items = $payload['items'];
        $this->db->beginTransaction();
        try {
            if ($sheetType === 'bar') {
                $this->db->exec('DELETE FROM bar_menu_items');
                foreach ($items as $row) {
                    $variants = is_array($row['variants'] ?? null) ? $row['variants'] : [];
                    unset($row['id'], $row['variants'], $row['created_at'], $row['updated_at']);
                    $newId = $this->barRepo->insertItem($row);
                    foreach ($variants as $variant) {
                        $this->barVariantRepo->insertVariant(
                            $newId,
                            (string) ($variant['variant_label'] ?? ''),
                            (float) ($variant['price'] ?? 0),
                            (int) ($variant['variant_sort_order'] ?? 0)
                        );
                    }
                }
            } else {
                $this->db->exec('DELETE FROM food_menu_items');
                foreach ($items as $row) {
                    $variants = is_array($row['variants'] ?? null) ? $row['variants'] : [];
                    unset($row['id'], $row['variants'], $row['created_at'], $row['updated_at']);
                    $newId = $this->foodRepo->insertItem($row);
                    foreach ($variants as $variant) {
                        $this->foodVariantRepo->insertVariant(
                            $newId,
                            (string) ($variant['variant_label'] ?? ''),
                            (float) ($variant['price'] ?? 0),
                            (int) ($variant['variant_sort_order'] ?? 0)
                        );
                    }
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'sheetType' => $sheetType,
            'restored'  => count($items),
        ];
    }

    private function captureState(string $sheetType): array
    {
        if ($sheetType === 'bar') {
            $items = $this->barRepo->listItems(false);
            $variants = $this->barVariantRepo->getVariantsForItems(array_column($items, 'id'));
        } else {
            $items = $this->foodRepo->listItems(false);
            $variants = $this->foodVariantRepo->getVariantsForItems(array_column($items, 'id'));
        }

        $rows = [];
        foreach ($items as $item) {
            $item['variants'] = $variants[(int) ($item['id'] ?? 0)] ?? [];
            $rows[] = $item;
        }

        return [
            'sheet_type' => $sheetType,
            'items'      => $rows,
        ];
    }
}
