<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Services\MenuService;
use NK\Repositories\FoodMenuRepository;
use NK\Repositories\FoodMenuVariantRepository;
use NK\Repositories\BarMenuRepository;
use NK\Repositories\BarMenuVariantRepository;
use NK\Services\Menu\MenuCategoryService;

class MenuController
{
    public static function getTab(array $query): array
    {
        $service = new MenuService();
        return $service->getTab($query);
    }

    public static function load(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->load($body);
    }

    public static function saveChanges(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->saveChanges($body);
    }

    public static function addRow(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->addRow($body);
    }

    public static function deleteRows(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->deleteRows($body);
    }

    public static function setVisibility(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->setVisibility($body);
    }

    // ── Public endpoints (no auth required) ──────────────────────────────────

    /**
     * GET ?action=food_menu_items
     * Returns all available food items with their variants.
     * Response shape consumed by menu.html.
     */
    public static function publicFoodMenu(array $body, array $query): array
    {
        $repo        = new FoodMenuRepository();
        $variantRepo = new FoodMenuVariantRepository();

        $items = $repo->listItems(availableOnly: true);

        if (empty($items)) {
            return ['ok' => true, 'items' => []];
        }

        $ids      = array_column($items, 'id');
        $variants = $variantRepo->getVariantsForItems($ids);

        foreach ($items as &$item) {
            $item['variants'] = $variants[(int) $item['id']] ?? [];
        }
        unset($item);

        return ['ok' => true, 'items' => $items];
    }

    /**
     * GET ?action=bar_menu_items
     * Returns all available bar items with their price variants.
     * Response shape consumed by cocktail.html.
     */
    public static function publicBarMenu(array $body, array $query): array
    {
        $repo        = new BarMenuRepository();
        $variantRepo = new BarMenuVariantRepository();

        $items = $repo->listItems(availableOnly: true);

        if (empty($items)) {
            return ['ok' => true, 'items' => []];
        }

        $ids      = array_column($items, 'id');
        $variants = $variantRepo->getVariantsForItems($ids);

        foreach ($items as &$item) {
            $item['variants'] = $variants[(int) $item['id']] ?? [];
        }
        unset($item);

        return ['ok' => true, 'items' => $items];
    }

    // ── Admin bulk-editor endpoints (new food_menu_items / bar_menu_items tables) ──

    public static function adminFoodLoad(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $foodRepo    = new FoodMenuRepository();
        $variantRepo = new FoodMenuVariantRepository();
        $items       = $foodRepo->listItems(availableOnly: false);
        $ids         = array_column($items, 'id');
        $variantMap  = $ids ? $variantRepo->getVariantsForItems($ids) : [];

        foreach ($items as &$item) {
            $item['variants'] = $variantMap[(int) $item['id']] ?? [];
        }
        unset($item);

        [$headers, $meta, $rows] = self::buildFoodEditorPayload($items);
        $categoryService = new MenuCategoryService();

        return [
            'ok'              => true,
            'action'          => 'admin_food_menu_editor_load',
            'menuType'        => 'food',
            'headers'         => $headers,
            'editableColumns' => $headers,
            'editableMeta'    => $meta,
            'columnGroups'    => self::foodColumnGroups(),
            'categoryOptions' => $categoryService->listOptions('food'),
            'rows'            => $rows,
            'rowCount'        => count($rows),
        ];
    }

    public static function adminFoodSave(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $changes = $body['changes'] ?? [];
        if (!is_array($changes)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'changes array is required.'];
        }

        $foodRepo    = new FoodMenuRepository();
        $variantRepo = new FoodMenuVariantRepository();
        $categoryService = new MenuCategoryService();
        $updated     = 0;
        $createCategories = array_values(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            (array) ($body['createCategories'] ?? [])
        ), static fn(string $value): bool => $value !== ''));

        $categoryReview = $categoryService->validateCategoryInputs(
            'food',
            array_values(array_unique(array_filter(array_map(
                static fn($change): string => is_array($change) ? trim((string) ($change['category'] ?? '')) : '',
                $changes
            )))),
            $createCategories
        );
        if (!empty($categoryReview['unknown'])) {
            $messages = array_map(static function (array $entry): string {
                $suggestions = array_map(static fn(array $s): string => (string) ($s['name'] ?? ''), $entry['suggestions'] ?? []);
                return $entry['input'] . ($suggestions ? ' (suggestions: ' . implode(', ', $suggestions) . ')' : '');
            }, $categoryReview['unknown']);
            return ['ok' => false, 'error' => 'UNKNOWN_CATEGORY', 'message' => 'Unknown categories require confirmation: ' . implode('; ', $messages)];
        }

        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $id = (int) ($change['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (isset($change['category'], $categoryReview['resolved'][(string) $change['category']])) {
                $change['category'] = $categoryReview['resolved'][(string) $change['category']];
            }
            $payload = self::normalizeFoodPayload($change);
            if (array_key_exists('variants', $change) && is_array($change['variants'])) {
                $payload['pricing_mode'] = !empty(self::normalizeVariantPayload($change['variants'])) ? 'custom_variants' : 'standard';
            }
            if (!empty($payload)) {
                $foodRepo->updateItem($id, $payload);
            }
            if (array_key_exists('variants', $change) && is_array($change['variants'])) {
                $variantRepo->replaceVariantsForItem($id, self::normalizeVariantPayload($change['variants']));
            }
            $updated++;
        }

        return [
            'ok'           => true,
            'action'       => 'admin_food_menu_editor_save',
            'updatedCount' => $updated,
            'message'      => "Saved {$updated} food item(s).",
        ];
    }

    public static function adminFoodAddRow(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $repo    = new FoodMenuRepository();
        $categoryService = new MenuCategoryService();
        $options = $categoryService->listOptions('food');
        $defaultCategory = $options[0]['name'] ?? $categoryService->ensureCategory('food', 'Uncategorized');
        $items = $repo->listItems(false);
        $categorySortOrder = 1;
        $itemSortOrder = 1;
        foreach ($items as $item) {
            if ((string) ($item['category'] ?? '') === $defaultCategory) {
                $categorySortOrder = max($categorySortOrder, (int) ($item['category_sort_order'] ?? 0));
                $itemSortOrder = max($itemSortOrder, (int) ($item['item_sort_order'] ?? 0) + 1);
            }
        }
        $newId = $repo->insertItem([
            'category'            => $defaultCategory,
            'item_name'           => 'New Item',
            'is_available'        => 1,
            'is_veg'              => 1,
            'pricing_mode'        => 'standard',
            'category_sort_order' => $categorySortOrder,
            'item_sort_order'     => $itemSortOrder,
        ]);

        return ['ok' => true, 'action' => 'admin_food_menu_editor_add_row', 'id' => $newId, 'message' => 'Food item row added.'];
    }

    public static function adminFoodDeleteRows(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $ids     = array_filter(array_map('intval', (array) ($body['ids'] ?? [])), fn($i) => $i > 0);
        $deleted = (new FoodMenuRepository())->deleteItems(array_values($ids));

        return ['ok' => true, 'action' => 'admin_food_menu_editor_delete', 'deletedCount' => $deleted];
    }

    public static function adminFoodSetVisibility(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $ids       = array_filter(array_map('intval', (array) ($body['ids'] ?? [])), fn($i) => $i > 0);
        $available = !empty($body['isAvailable']);
        $updated   = (new FoodMenuRepository())->setAvailability(array_values($ids), $available);

        return ['ok' => true, 'action' => 'admin_food_menu_editor_visible', 'updatedCount' => $updated];
    }

    // ── Bar menu admin methods ────────────────────────────────────────────────

    public static function adminBarLoad(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $barRepo     = new BarMenuRepository();
        $variantRepo = new BarMenuVariantRepository();
        $categoryService = new MenuCategoryService();
        $items       = $barRepo->listItems(availableOnly: false);
        $ids         = array_column($items, 'id');
        $variantMap  = $ids ? $variantRepo->getVariantsForItems($ids) : [];

        foreach ($items as &$item) {
            $item['variants'] = $variantMap[(int) $item['id']] ?? [];
        }
        unset($item);

        [$headers, $meta, $rows] = self::buildBarEditorPayload($items);

        return [
            'ok'              => true,
            'action'          => 'admin_bar_menu_editor_load',
            'menuType'        => 'bar',
            'headers'         => $headers,
            'editableColumns' => $headers,
            'editableMeta'    => $meta,
            'columnGroups'    => self::barColumnGroups(),
            'categoryOptions' => $categoryService->listOptions('bar'),
            'rows'            => $rows,
            'rowCount'        => count($rows),
        ];
    }

    public static function adminBarSave(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $changes = $body['changes'] ?? [];
        if (!is_array($changes)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'changes array is required.'];
        }

        $barRepo     = new BarMenuRepository();
        $variantRepo = new BarMenuVariantRepository();
        $categoryService = new MenuCategoryService();
        $updated     = 0;
        $createCategories = array_values(array_filter(array_map(
            static fn($value): string => trim((string) $value),
            (array) ($body['createCategories'] ?? [])
        ), static fn(string $value): bool => $value !== ''));

        $categoryReview = $categoryService->validateCategoryInputs(
            'bar',
            array_values(array_unique(array_filter(array_map(
                static fn($change): string => is_array($change) ? trim((string) ($change['category'] ?? '')) : '',
                $changes
            )))),
            $createCategories
        );
        if (!empty($categoryReview['unknown'])) {
            $messages = array_map(static function (array $entry): string {
                $suggestions = array_map(static fn(array $s): string => (string) ($s['name'] ?? ''), $entry['suggestions'] ?? []);
                return $entry['input'] . ($suggestions ? ' (suggestions: ' . implode(', ', $suggestions) . ')' : '');
            }, $categoryReview['unknown']);
            return ['ok' => false, 'error' => 'UNKNOWN_CATEGORY', 'message' => 'Unknown categories require confirmation: ' . implode('; ', $messages)];
        }

        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $id = (int) ($change['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (isset($change['category'], $categoryReview['resolved'][(string) $change['category']])) {
                $change['category'] = $categoryReview['resolved'][(string) $change['category']];
            }
            $payload = self::normalizeBarPayload($change);
            if (!empty($payload)) {
                $barRepo->updateItem($id, $payload);
            }
            if (array_key_exists('variants', $change) && is_array($change['variants'])) {
                $variantRepo->replaceVariantsForItem($id, self::normalizeVariantPayload($change['variants']));
            }
            $updated++;
        }

        return [
            'ok'           => true,
            'action'       => 'admin_bar_menu_editor_save',
            'updatedCount' => $updated,
            'message'      => "Saved {$updated} bar item(s).",
        ];
    }

    public static function adminBarAddRow(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $repo  = new BarMenuRepository();
        $categoryService = new MenuCategoryService();
        $options = $categoryService->listOptions('bar');
        $defaultCategory = $options[0]['name'] ?? $categoryService->ensureCategory('bar', 'Uncategorized');
        $items = $repo->listItems(false);
        $categorySortOrder = 1;
        $itemSortOrder = 1;
        foreach ($items as $item) {
            if ((string) ($item['category'] ?? '') === $defaultCategory) {
                $categorySortOrder = max($categorySortOrder, (int) ($item['category_sort_order'] ?? 0));
                $itemSortOrder = max($itemSortOrder, (int) ($item['item_sort_order'] ?? 0) + 1);
            }
        }
        $newId = $repo->insertItem([
            'category'            => $defaultCategory,
            'item_name'           => 'New Item',
            'is_available'        => 1,
            'category_sort_order' => $categorySortOrder,
            'item_sort_order'     => $itemSortOrder,
        ]);

        return ['ok' => true, 'action' => 'admin_bar_menu_editor_add_row', 'id' => $newId, 'message' => 'Bar item row added.'];
    }

    public static function adminBarDeleteRows(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $ids     = array_filter(array_map('intval', (array) ($body['ids'] ?? [])), fn($i) => $i > 0);
        $deleted = (new BarMenuRepository())->deleteItems(array_values($ids));

        return ['ok' => true, 'action' => 'admin_bar_menu_editor_delete', 'deletedCount' => $deleted];
    }

    public static function adminBarSetVisibility(array $body, array $query): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $ids       = array_filter(array_map('intval', (array) ($body['ids'] ?? [])), fn($i) => $i > 0);
        $available = !empty($body['isAvailable']);
        $updated   = (new BarMenuRepository())->setAvailability(array_values($ids), $available);

        return ['ok' => true, 'action' => 'admin_bar_menu_editor_visible', 'updatedCount' => $updated];
    }

    // ── Menu designer endpoints ──────────────────────────────────────────────

    public static function designerLoad(array $body, array $query): array
    {
        $auth = self::authorizeMenuEditor($body);
        if (!$auth['ok']) {
            return $auth;
        }

        $menuType = self::normalizeDesignerSheetType((string) ($body['sheetName'] ?? $body['sheetType'] ?? ''));
        if ($menuType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid sheet name.'];
        }

        $items = $menuType === 'bar'
            ? (new BarMenuRepository())->listItems(false)
            : (new FoodMenuRepository())->listItems(false);
        $categories = (new MenuCategoryService())->listOptions($menuType);

        return [
            'ok'         => true,
            'action'     => 'admin_menu_designer_load',
            'sheetName'  => self::designerSheetName($menuType),
            'menuType'   => $menuType,
            'categories' => self::buildDesignerCategories($items, $categories, $menuType),
        ];
    }

    public static function designerSaveCategoryOrder(array $body, array $query): array
    {
        $auth = self::authorizeMenuEditor($body);
        if (!$auth['ok']) {
            return $auth;
        }

        $menuType = self::normalizeDesignerSheetType((string) ($body['sheetName'] ?? $body['sheetType'] ?? ''));
        $categories = array_values(array_filter(array_map(
            static function ($value): string {
                return trim((string) $value);
            },
            (array) ($body['categories'] ?? [])
        ), static function (string $value): bool {
            return $value !== '';
        }));

        if ($menuType === null || empty($categories)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'sheetName and categories are required.'];
        }

        $updated = $menuType === 'bar'
            ? (new BarMenuRepository())->updateCategorySortOrder($categories)
            : (new FoodMenuRepository())->updateCategorySortOrder($categories);
        (new MenuCategoryService())->updateSortOrder($menuType, $categories);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_designer_save_category_order',
            'updatedCount' => $updated,
            'message'      => 'Category order saved.',
        ];
    }

    public static function designerSaveItemOrder(array $body, array $query): array
    {
        $auth = self::authorizeMenuEditor($body);
        if (!$auth['ok']) {
            return $auth;
        }

        $menuType = self::normalizeDesignerSheetType((string) ($body['sheetName'] ?? $body['sheetType'] ?? ''));
        $category = trim((string) ($body['category'] ?? ''));
        $itemIds = array_values(array_filter(array_map('intval', (array) ($body['itemIds'] ?? [])), static function (int $id): bool {
            return $id > 0;
        }));

        if ($menuType === null || $category === '' || empty($itemIds)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'sheetName, category and itemIds are required.'];
        }

        $updated = $menuType === 'bar'
            ? (new BarMenuRepository())->updateItemSortOrder($category, $itemIds)
            : (new FoodMenuRepository())->updateItemSortOrder($category, $itemIds);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_designer_save_item_order',
            'updatedCount' => $updated,
            'message'      => 'Item order saved.',
        ];
    }

    public static function designerToggleCategory(array $body, array $query): array
    {
        $auth = self::authorizeMenuEditor($body);
        if (!$auth['ok']) {
            return $auth;
        }

        $menuType = self::normalizeDesignerSheetType((string) ($body['sheetName'] ?? $body['sheetType'] ?? ''));
        $category = trim((string) ($body['category'] ?? ''));
        $available = !empty($body['isAvailable']);

        if ($menuType === null || $category === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'sheetName and category are required.'];
        }

        $updated = $menuType === 'bar'
            ? (new BarMenuRepository())->setCategoryAvailability($category, $available)
            : (new FoodMenuRepository())->setCategoryAvailability($category, $available);
        (new MenuCategoryService())->setActiveByName($menuType, $category, $available);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_designer_toggle_category',
            'updatedCount' => $updated,
            'message'      => 'Category visibility updated.',
        ];
    }

    public static function designerToggleItem(array $body, array $query): array
    {
        $auth = self::authorizeMenuEditor($body);
        if (!$auth['ok']) {
            return $auth;
        }

        $menuType = self::normalizeDesignerSheetType((string) ($body['sheetName'] ?? $body['sheetType'] ?? ''));
        $itemId = (int) ($body['id'] ?? 0);
        $available = !empty($body['isAvailable']);

        if ($menuType === null || $itemId <= 0) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'sheetName and id are required.'];
        }

        $updated = $menuType === 'bar'
            ? (new BarMenuRepository())->setItemAvailability($itemId, $available)
            : (new FoodMenuRepository())->setItemAvailability($itemId, $available);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_designer_toggle_item',
            'updatedCount' => $updated,
            'message'      => 'Item visibility updated.',
        ];
    }

    public static function designerCloneCategory(array $body, array $query): array
    {
        $auth = self::authorizeMenuEditor($body);
        if (!$auth['ok']) {
            return $auth;
        }

        $menuType = self::normalizeDesignerSheetType((string) ($body['sheetName'] ?? $body['sheetType'] ?? ''));
        $sourceCategory = trim((string) ($body['sourceCategory'] ?? ''));
        $newCategoryName = trim((string) ($body['newCategoryName'] ?? ''));
        $cloneItems = !isset($body['cloneItems']) || !in_array((string) $body['cloneItems'], ['0', 'false', 'no'], true);

        if ($menuType === null || $sourceCategory === '' || $newCategoryName === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'sheetName, sourceCategory and newCategoryName are required.'];
        }

        $categoryService = new MenuCategoryService();
        $sourceResolved = $categoryService->resolveName($menuType, $sourceCategory) ?? $sourceCategory;
        $targetCategory = $categoryService->ensureCategory($menuType, $newCategoryName);
        $targetSortOrder = 1;
        foreach ($categoryService->listOptions($menuType) as $option) {
            if ((string) ($option['name'] ?? '') === $targetCategory) {
                $targetSortOrder = (int) ($option['sortOrder'] ?? 1);
                break;
            }
        }

        if ($cloneItems) {
            if ($menuType === 'bar') {
                $repo = new BarMenuRepository();
                $variantRepo = new BarMenuVariantRepository();
                $items = array_values(array_filter($repo->listItems(false), static fn(array $item): bool => (string) ($item['category'] ?? '') === $sourceResolved));
                $variantMap = $variantRepo->getVariantsForItems(array_column($items, 'id'));
                foreach ($items as $item) {
                    $variants = $variantMap[(int) ($item['id'] ?? 0)] ?? [];
                    unset($item['id'], $item['created_at'], $item['updated_at']);
                    $item['category'] = $targetCategory;
                    $item['category_sort_order'] = $targetSortOrder;
                    $newId = $repo->insertItem($item);
                    $variantRepo->replaceVariantsForItem($newId, array_map(static function (array $variant): array {
                        return [
                            'label'      => (string) ($variant['variant_label'] ?? ''),
                            'price'      => (float) ($variant['price'] ?? 0),
                            'sort_order' => (int) ($variant['variant_sort_order'] ?? 0),
                        ];
                    }, $variants));
                }
            } else {
                $repo = new FoodMenuRepository();
                $variantRepo = new FoodMenuVariantRepository();
                $items = array_values(array_filter($repo->listItems(false), static fn(array $item): bool => (string) ($item['category'] ?? '') === $sourceResolved));
                $variantMap = $variantRepo->getVariantsForItems(array_column($items, 'id'));
                foreach ($items as $item) {
                    $variants = $variantMap[(int) ($item['id'] ?? 0)] ?? [];
                    unset($item['id'], $item['created_at'], $item['updated_at']);
                    $item['category'] = $targetCategory;
                    $item['category_sort_order'] = $targetSortOrder;
                    $newId = $repo->insertItem($item);
                    $variantRepo->replaceVariantsForItem($newId, array_map(static function (array $variant): array {
                        return [
                            'label'      => (string) ($variant['variant_label'] ?? ''),
                            'price'      => (float) ($variant['price'] ?? 0),
                            'sort_order' => (int) ($variant['variant_sort_order'] ?? 0),
                        ];
                    }, $variants));
                }
            }
        }

        return [
            'ok'          => true,
            'action'      => 'admin_menu_designer_clone_category',
            'menuType'    => $menuType,
            'category'    => $targetCategory,
            'cloneItems'  => $cloneItems,
            'message'     => $cloneItems ? 'Category and items cloned.' : 'Category cloned.',
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private static function authorizeMenuEditor(array $body): array
    {
        $auth = \NK\Middleware\AuthMiddleware::authorize($body, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!\NK\Middleware\AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }
        return $auth;
    }

    private static function normalizeDesignerSheetType(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '' || $normalized === 'food' || $normalized === 'awgnk menu') {
            return 'food';
        }
        if ($normalized === 'bar' || $normalized === 'bar menu nk' || $normalized === 'cocktail') {
            return 'bar';
        }
        return null;
    }

    private static function designerSheetName(string $menuType): string
    {
        return $menuType === 'bar' ? 'BAR MENU NK' : 'AWGNK MENU';
    }

    private static function buildDesignerCategories(array $items, array $categoryOptions, string $menuType): array
    {
        $categories = [];

        foreach ($categoryOptions as $option) {
            $categoryName = trim((string) ($option['name'] ?? ''));
            if ($categoryName === '') {
                continue;
            }

            $categories[$categoryName] = [
                'name' => $categoryName,
                'isAvailable' => !empty($option['isActive']),
                'categorySortOrder' => (int) ($option['sortOrder'] ?? 0),
                'items' => [],
            ];
        }

        foreach ($items as $item) {
            $categoryName = trim((string) ($item['category'] ?? ''));
            if ($categoryName === '') {
                $categoryName = 'Uncategorized';
            }

            if (!isset($categories[$categoryName])) {
                $categories[$categoryName] = [
                    'name' => $categoryName,
                    'isAvailable' => !empty($item['is_available']),
                    'categorySortOrder' => (int) ($item['category_sort_order'] ?? 0),
                    'items' => [],
                ];
            } elseif (!empty($item['is_available'])) {
                $categories[$categoryName]['isAvailable'] = true;
            }

            $categories[$categoryName]['items'][] = [
                'id' => (int) ($item['id'] ?? 0),
                'itemName' => trim((string) ($item['item_name'] ?? '')),
                'isAvailable' => !empty($item['is_available']),
                'basePrice' => self::designerBasePrice($item, $menuType),
                'itemSortOrder' => (int) ($item['item_sort_order'] ?? 0),
            ];
        }

        $result = array_values($categories);
        usort($result, static function (array $left, array $right): int {
            return [$left['categorySortOrder'], $left['name']] <=> [$right['categorySortOrder'], $right['name']];
        });

        foreach ($result as &$category) {
            usort($category['items'], static function (array $left, array $right): int {
                return [$left['itemSortOrder'], $left['id']] <=> [$right['itemSortOrder'], $right['id']];
            });

            unset($category['categorySortOrder']);
            foreach ($category['items'] as &$item) {
                unset($item['itemSortOrder']);
            }
            unset($item);
        }
        unset($category);

        return $result;
    }

    private static function designerBasePrice(array $item, string $menuType): ?float
    {
        if ($menuType === 'bar') {
            return null;
        }

        $priceColumns = [
            'price_veg', 'price_jain', 'price_chicken', 'price_mutton', 'price_basa',
            'price_prawns', 'price_surmai', 'price_pomfret', 'price_crab', 'price_egg',
            'price_half', 'price_full', 'price_plain', 'price_butter',
            'price_medium', 'price_large', 'price_direct',
        ];

        foreach ($priceColumns as $column) {
            if (!array_key_exists($column, $item)) {
                continue;
            }

            $value = $item[$column];
            if ($value === null || $value === '') {
                continue;
            }

            return (float) $value;
        }

        return null;
    }

    private static function foodEditorHeaders(): array
    {
        return [
            'category', 'item_name', 'description',
            'is_veg', 'is_nonveg', 'is_jain', 'is_universal',
            'is_chef_special', 'is_available',
            'price_veg', 'price_chicken', 'price_mutton',
            'price_basa', 'price_prawns', 'price_surmai',
            'price_pomfret', 'price_crab', 'price_egg',
            'price_half', 'price_full', 'price_plain',
            'price_butter', 'price_medium', 'price_large', 'price_direct',
            'price_jain',
            'spice_level', 'serving_unit', 'image_url',
        ];
    }

    private static function foodEditorMeta(): array
    {
        return [
            'category'            => 'categoryPicker',
            'item_name'           => 'text',
            'description'         => 'text',
            'is_veg'              => 'bool',
            'is_nonveg'           => 'bool',
            'is_jain'             => 'bool',
            'is_universal'        => 'bool',
            'is_chef_special'     => 'bool',
            'is_available'        => 'visibility',
            'price_veg'           => 'price',
            'price_chicken'       => 'price',
            'price_mutton'        => 'price',
            'price_basa'          => 'price',
            'price_prawns'        => 'price',
            'price_surmai'        => 'price',
            'price_pomfret'       => 'price',
            'price_crab'          => 'price',
            'price_egg'           => 'price',
            'price_half'          => 'price',
            'price_full'          => 'price',
            'price_plain'         => 'price',
            'price_butter'        => 'price',
            'price_medium'        => 'price',
            'price_large'         => 'price',
            'price_direct'        => 'price',
            'price_jain'          => 'price',
            'spice_level'         => 'spice',
            'serving_unit'        => 'text',
            'image_url'           => 'imageUrl',
        ];
    }

    private static function foodColumnGroups(): array
    {
        return [
            ['label' => 'Identity',        'cols' => ['category', 'item_name', 'description']],
            ['label' => 'Diet Flags',      'cols' => ['is_veg', 'is_nonveg', 'is_jain', 'is_universal']],
            ['label' => 'Config',          'cols' => ['is_chef_special', 'is_available']],
            ['label' => 'Protein Prices',  'cols' => ['price_veg', 'price_chicken', 'price_mutton', 'price_basa', 'price_prawns', 'price_surmai', 'price_pomfret', 'price_crab', 'price_egg']],
            ['label' => 'Serving Prices',  'cols' => ['price_half', 'price_full', 'price_plain', 'price_butter', 'price_medium', 'price_large', 'price_direct']],
            ['label' => 'Jain Price',      'cols' => ['price_jain']],
            ['label' => 'Details',         'cols' => ['spice_level', 'serving_unit', 'image_url']],
        ];
    }

    private static function buildFoodEditorPayload(array $items): array
    {
        $headers  = self::foodEditorHeaders();
        $meta     = self::foodEditorMeta();
        $boolCols = ['is_veg', 'is_nonveg', 'is_jain', 'is_universal', 'is_chef_special', 'is_available'];
        $rows     = [];

        foreach ($items as $item) {
            $row = ['id' => (int) $item['id']];
            foreach ($headers as $col) {
                $val = $item[$col] ?? null;
                if ($val === null) {
                    $row[$col] = '';
                } elseif (in_array($col, $boolCols, true)) {
                    $row[$col] = ((int) $val === 1) ? '1' : '0';
                } else {
                    $row[$col] = (string) $val;
                }
            }
            $row['variants'] = $item['variants'] ?? [];
            $rows[]          = $row;
        }

        return [$headers, $meta, $rows];
    }

    private static function normalizeFoodPayload(array $change): array
    {
        $boolCols  = ['is_veg', 'is_nonveg', 'is_jain', 'is_universal', 'is_chef_special', 'is_available'];
        $priceCols = [
            'price_veg', 'price_chicken', 'price_mutton', 'price_basa', 'price_prawns',
            'price_surmai', 'price_pomfret', 'price_crab', 'price_egg',
            'price_half', 'price_full', 'price_plain', 'price_butter',
            'price_medium', 'price_large', 'price_direct', 'price_jain',
        ];
        $strCols   = ['category', 'item_name', 'description', 'spice_level', 'serving_unit', 'image_url'];

        $out = [];
        foreach ($strCols as $col) {
            if (array_key_exists($col, $change)) {
                $out[$col] = trim((string) $change[$col]);
            }
        }
        foreach ($boolCols as $col) {
            if (array_key_exists($col, $change)) {
                $v        = $change[$col];
                $out[$col] = (is_string($v)
                    ? in_array(strtolower(trim($v)), ['1', 'yes', 'true', 'on'], true)
                    : !empty($v)) ? 1 : 0;
            }
        }
        foreach ($priceCols as $col) {
            if (array_key_exists($col, $change)) {
                $v        = trim((string) $change[$col]);
                $out[$col] = ($v === '') ? null : (float) $v;
            }
        }
        unset($out['id']);
        return $out;
    }

    private static function barEditorHeaders(): array
    {
        return ['category', 'item_name', 'description', 'barman_special', 'is_available'];
    }

    private static function barEditorMeta(): array
    {
        return [
            'category'            => 'categoryPicker',
            'item_name'           => 'text',
            'description'         => 'text',
            'barman_special'      => 'bool',
            'is_available'        => 'visibility',
        ];
    }

    private static function barColumnGroups(): array
    {
        return [
            ['label' => 'Identity', 'cols' => ['category', 'item_name', 'description']],
            ['label' => 'Config',   'cols' => ['barman_special', 'is_available']],
        ];
    }

    private static function buildBarEditorPayload(array $items): array
    {
        $headers  = self::barEditorHeaders();
        $meta     = self::barEditorMeta();
        $boolCols = ['barman_special', 'is_available'];
        $rows     = [];

        foreach ($items as $item) {
            $row = ['id' => (int) $item['id']];
            foreach ($headers as $col) {
                $val = $item[$col] ?? null;
                if ($val === null) {
                    $row[$col] = '';
                } elseif (in_array($col, $boolCols, true)) {
                    $row[$col] = ((int) $val === 1) ? '1' : '0';
                } else {
                    $row[$col] = (string) $val;
                }
            }
            $row['variants'] = $item['variants'] ?? [];
            $rows[]          = $row;
        }

        return [$headers, $meta, $rows];
    }

    private static function normalizeBarPayload(array $change): array
    {
        $boolCols = ['barman_special', 'is_available'];
        $strCols  = ['category', 'item_name', 'description'];

        $out = [];
        foreach ($strCols as $col) {
            if (array_key_exists($col, $change)) {
                $out[$col] = trim((string) $change[$col]);
            }
        }
        foreach ($boolCols as $col) {
            if (array_key_exists($col, $change)) {
                $v        = $change[$col];
                $out[$col] = (is_string($v)
                    ? in_array(strtolower(trim($v)), ['1', 'yes', 'true', 'on'], true)
                    : !empty($v)) ? 1 : 0;
            }
        }
        unset($out['id']);
        return $out;
    }

    private static function normalizeVariantPayload(array $variants): array
    {
        $normalized = [];
        foreach ($variants as $index => $variant) {
            if (!is_array($variant)) {
                continue;
            }
            $label = trim((string) ($variant['label'] ?? $variant['variant_label'] ?? ''));
            $priceRaw = trim((string) ($variant['price'] ?? ''));
            if ($label === '' || $priceRaw === '') {
                continue;
            }
            $normalized[] = [
                'label'      => $label,
                'price'      => (float) $priceRaw,
                'sort_order' => isset($variant['sort_order']) ? (int) $variant['sort_order'] : (isset($variant['variant_sort_order']) ? (int) $variant['variant_sort_order'] : $index),
            ];
        }
        return $normalized;
    }
}
