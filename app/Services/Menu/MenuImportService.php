<?php

declare(strict_types=1);

namespace NK\Services\Menu;

use NK\Config\Database;
use NK\Repositories\FoodMenuRepository;
use NK\Repositories\FoodMenuVariantRepository;
use NK\Repositories\BarMenuRepository;
use NK\Repositories\BarMenuVariantRepository;

/**
 * MenuImportService
 *
 * Coordinator for food and bar menu import / export workflows.
 *
 * Import flow (preview → execute):
 *   1. preview*($path)  — parse Excel only, return diagnostics, no DB writes.
 *   2. execute*($path)  — parse Excel + persist to DB atomically.
 *
 * Export flow:
 *   export*Xlsx($outputPath) — read DB + write .xlsx file.
 *   If $outputPath is null a temp path in storage/tmp is generated.
 *
 * The service owns the database transaction so that both items and their
 * variants are written (or rolled back) atomically.
 */
class MenuImportService
{
    private FoodMenuRepository        $foodRepo;
    private FoodMenuVariantRepository $foodVariantRepo;
    private BarMenuRepository         $barRepo;
    private BarMenuVariantRepository  $barVariantRepo;
    private MenuCategoryService       $categoryService;
    private MenuSnapshotService       $snapshotService;

    public function __construct()
    {
        $this->foodRepo        = new FoodMenuRepository();
        $this->foodVariantRepo = new FoodMenuVariantRepository();
        $this->barRepo         = new BarMenuRepository();
        $this->barVariantRepo  = new BarMenuVariantRepository();
        $this->categoryService = new MenuCategoryService();
        $this->snapshotService = new MenuSnapshotService();
    }

    // ── Food preview ──────────────────────────────────────────────────────────

    /**
     * Parse the food Excel sheet and return diagnostics without DB writes.
     */
    public function previewFood(string $xlsxPath): array
    {
        $result = FoodMenuExcelImporter::preview($xlsxPath);
        $categoryReview = $this->categoryService->validateCategoryInputs('food', $result['categories'] ?? []);
        return array_merge(['ok' => true, 'type' => 'food_import_preview'], $result, [
            'previewSummary' => [
                'rows'     => (int) ($result['data_rows'] ?? 0),
                'inserted' => (int) ($result['data_rows'] ?? 0),
                'updated'  => 0,
                'skipped'  => (int) ($result['blank_rows_skipped'] ?? 0),
            ],
            'categoryMappingActions' => $this->buildCategoryActions($categoryReview),
        ]);
    }

    // ── Food execute ──────────────────────────────────────────────────────────

    /**
     * Parse + persist food menu items and their variants atomically.
     *
     * @return array{ ok: bool, type: string, items_inserted: int, variants_inserted: int }
     */
    public function executeFood(string $xlsxPath, bool $takeSnapshot = true, string $createdBy = 'admin', array $createCategories = []): array
    {
        // May throw RuntimeException if importer finds errors
        $parsed = FoodMenuExcelImporter::execute($xlsxPath);
        $resolved = $this->applyResolvedCategories('food', $parsed, $createCategories);

        if ($takeSnapshot) {
            $this->snapshotService->createSnapshot(
                'food',
                'Pre-import snapshot ' . date('Y-m-d H:i:s'),
                'import',
                $createdBy
            );
        }

        [$itemsInserted, $variantsInserted] = $this->persistFoodItems($resolved);

        return [
            'ok'               => true,
            'type'             => 'food_import_execute',
            'items_inserted'   => $itemsInserted,
            'variants_inserted'=> $variantsInserted,
            'inserted'         => $itemsInserted,
            'updated'          => 0,
            'skipped'          => 0,
        ];
    }

    // ── Bar preview ───────────────────────────────────────────────────────────

    /**
     * Parse the bar Excel sheet and return diagnostics without DB writes.
     */
    public function previewBar(string $xlsxPath): array
    {
        $result = BarMenuExcelImporter::preview($xlsxPath);
        $categoryReview = $this->categoryService->validateCategoryInputs('bar', $result['categories'] ?? []);
        return array_merge(['ok' => true, 'type' => 'bar_import_preview'], $result, [
            'previewSummary' => [
                'rows'     => (int) ($result['data_rows'] ?? 0),
                'inserted' => (int) ($result['data_rows'] ?? 0),
                'updated'  => 0,
                'skipped'  => (int) ($result['blank_rows_skipped'] ?? 0),
            ],
            'categoryMappingActions' => $this->buildCategoryActions($categoryReview),
        ]);
    }

    // ── Bar execute ───────────────────────────────────────────────────────────

    /**
     * Parse + persist bar menu items and their variants atomically.
     */
    public function executeBar(string $xlsxPath, bool $takeSnapshot = true, string $createdBy = 'admin', array $createCategories = []): array
    {
        $parsed = BarMenuExcelImporter::execute($xlsxPath);
        $resolved = $this->applyResolvedCategories('bar', $parsed, $createCategories);

        if ($takeSnapshot) {
            $this->snapshotService->createSnapshot(
                'bar',
                'Pre-import snapshot ' . date('Y-m-d H:i:s'),
                'import',
                $createdBy
            );
        }

        [$itemsInserted, $variantsInserted] = $this->persistBarItems($resolved);

        return [
            'ok'                => true,
            'type'              => 'bar_import_execute',
            'items_inserted'    => $itemsInserted,
            'variants_inserted' => $variantsInserted,
            'inserted'          => $itemsInserted,
            'updated'           => 0,
            'skipped'           => 0,
        ];
    }

    // ── Food export ──────────────────────────────────────────────────────────

    /**
     * Export current food_menu_items to an .xlsx file.
     * Returns the absolute path of the generated file.
     */
    public function exportFoodXlsx(?string $outputPath = null): string
    {
        $this->categoryService->syncFromLiveItems('food');
        $items    = $this->foodRepo->listItems();
        $ids      = array_column($items, 'id');
        $variants = $ids ? $this->foodVariantRepo->getVariantsForItems($ids) : [];

        $path = $outputPath ?? $this->tempXlsxPath('food_menu');
        FoodMenuExcelExporter::toFile($items, $variants, $path);
        return $path;
    }

    // ── Bar export ───────────────────────────────────────────────────────────

    /**
     * Export current bar_menu_items to an .xlsx file.
     * Returns the absolute path of the generated file.
     */
    public function exportBarXlsx(?string $outputPath = null): string
    {
        $this->categoryService->syncFromLiveItems('bar');
        $items    = $this->barRepo->listItems();
        $ids      = array_column($items, 'id');
        $variants = $ids ? $this->barVariantRepo->getVariantsForItems($ids) : [];

        $path = $outputPath ?? $this->tempXlsxPath('bar_menu');
        BarMenuExcelExporter::toFile($items, $variants, $path);
        return $path;
    }

    public function buildFoodTemplateXlsx(?string $outputPath = null): string
    {
        $path = $outputPath ?? $this->tempXlsxPath('food_menu_template');
        FoodMenuExcelExporter::toFile([], [], $path);
        return $path;
    }

    public function buildBarTemplateXlsx(?string $outputPath = null): string
    {
        $path = $outputPath ?? $this->tempXlsxPath('bar_menu_template');
        BarMenuExcelExporter::toFile([], [], $path);
        return $path;
    }

    // ── Internal: persist food (atomic) ──────────────────────────────────────

    /**
     * @return array{ 0: int, 1: int }  [itemsInserted, variantsInserted]
     */
    private function persistFoodItems(array $parsed): array
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            // Delete all items (FK CASCADE deletes variants automatically)
            $db->exec('DELETE FROM food_menu_items');

            $itemsInserted    = 0;
            $variantsInserted = 0;

            foreach ($parsed as $row) {
                $variants = $row['_variants'] ?? [];

                // Strip internal keys before inserting
                unset($row['_variants']);

                $newId = $this->foodRepo->insertItem($row);

                foreach ($variants as $v) {
                    $this->foodVariantRepo->insertVariant(
                        $newId,
                        (string) ($v['variant_label'] ?? ''),
                        (float)  ($v['price'] ?? 0),
                        (int)    ($v['variant_sort_order'] ?? 0)
                    );
                    $variantsInserted++;
                }

                $itemsInserted++;
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return [$itemsInserted, $variantsInserted];
    }

    // ── Internal: persist bar (atomic) ───────────────────────────────────────

    /**
     * @return array{ 0: int, 1: int }  [itemsInserted, variantsInserted]
     */
    private function persistBarItems(array $parsed): array
    {
        $db = Database::connection();
        $db->beginTransaction();

        try {
            $db->exec('DELETE FROM bar_menu_items');

            $itemsInserted    = 0;
            $variantsInserted = 0;

            foreach ($parsed as $row) {
                $variants = $row['_variants'] ?? [];
                unset($row['_variants']);

                $newId = $this->barRepo->insertItem($row);

                foreach ($variants as $v) {
                    $this->barVariantRepo->insertVariant(
                        $newId,
                        (string) ($v['variant_label'] ?? ''),
                        (float)  ($v['price'] ?? 0),
                        (int)    ($v['variant_sort_order'] ?? 0)
                    );
                    $variantsInserted++;
                }

                $itemsInserted++;
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return [$itemsInserted, $variantsInserted];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function tempXlsxPath(string $prefix): string
    {
        $dir = rtrim(realpath(__DIR__ . '/../../../storage/tmp') ?: sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        return $dir . DIRECTORY_SEPARATOR . $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
    }

    private function applyResolvedCategories(string $menuType, array $rows, array $createCategories): array
    {
        $rawNames = array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => trim((string) ($row['category'] ?? '')),
            $rows
        ))));

        $review = $this->categoryService->validateCategoryInputs($menuType, $rawNames, $createCategories);
        if (!empty($review['unknown'])) {
            $messages = array_map(static function (array $entry): string {
                $suggestions = array_map(static fn(array $s): string => (string) ($s['name'] ?? ''), $entry['suggestions'] ?? []);
                $suffix = $suggestions ? ' Did you mean: ' . implode(', ', $suggestions) . '?' : '';
                return $entry['input'] . $suffix;
            }, $review['unknown']);
            throw new \RuntimeException('Unknown categories require confirmation: ' . implode('; ', $messages));
        }

        foreach ($rows as &$row) {
            $raw = trim((string) ($row['category'] ?? ''));
            if ($raw !== '' && isset($review['resolved'][$raw])) {
                $row['category'] = $review['resolved'][$raw];
            }
        }
        unset($row);

        return $rows;
    }

    private function buildCategoryActions(array $review): array
    {
        return [
            'resolved' => array_map(static function (string $resolved, string $input): array {
                return [
                    'input'    => $input,
                    'resolved' => $resolved,
                    'action'   => $input === $resolved ? 'exact_match' : 'normalized_match',
                ];
            }, $review['resolved'] ?? [], array_keys($review['resolved'] ?? [])),
            'unresolved' => $review['unknown'] ?? [],
        ];
    }
}
