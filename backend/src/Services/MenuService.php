<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Middleware\AuthMiddleware;
use NK\Models\MenuItem;
use NK\Repositories\MenuRepository;

class MenuService
{
    private MenuRepository $repo;

    public function __construct()
    {
        $this->repo = new MenuRepository();
    }

    public function getTab(array $query): array
    {
        $tab = trim((string) ($query['tab'] ?? ''));
        $shape = strtolower(trim((string) ($query['shape'] ?? 'grid')));

        $sheetType = $this->mapTabToSheetType($tab);
        if ($sheetType === null) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_TAB',
                'message' => 'Unknown tab requested.',
            ];
        }

        return $this->buildTabResponse($sheetType, $shape);
    }

    public function load(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Menu editor permission required.',
            ];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Invalid sheet name.',
            ];
        }

        $rows = $this->repo->listItems($sheetType);
        $headers = $this->repo->getSchema($sheetType);

        if (empty($headers)) {
            $headers = $sheetType === Constants::MENU_SHEET_FOOD
                ? ['Category', 'Sub Category', 'Item Name', 'Description', 'Image URL', 'Jain', 'Chef Special', 'Spice Level', 'Unit (Pcs)', 'Price', 'Availability', 'Food Category', 'Primary Diet']
                : ['Category', 'Sub Category', 'Item Name', 'Description', 'Price', 'Availability'];
        }

        // Inject diet headers if not already present
        if ($sheetType === Constants::MENU_SHEET_FOOD) {
            if (!in_array('Primary Diet', $headers, true)) {
                $headers[] = 'Primary Diet';
            }
            if (!in_array('Diet Flags', $headers, true)) {
                $headers[] = 'Diet Flags';
            }
        }

        $editableMeta = $this->buildEditableMeta($headers, $sheetType);

        $items = [];
        foreach (array_values($rows) as $index => $row) {
            $item = MenuItem::fromDb($row)->toArray();
            $items[] = [
                'id'        => $item['id'],
                'rowNumber' => $index + 2,
                'cells'     => $this->itemToCells($item, $headers),
            ];
        }

        return [
            'ok'          => true,
            'action'      => 'admin_menu_editor_load',
            'sheetType'   => $sheetType,
            'sheetName'   => self::sheetTypeToName($sheetType),
            'headers'     => $headers,
            'editableMeta'=> $editableMeta,
            'items'       => $items,
            'rowCount'    => count($items),
        ];
    }

    public function saveChanges(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Menu editor permission required.',
            ];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Invalid sheet name.',
            ];
        }

        // Compatibility: old Apps Script editor payload shape used updates[] with cells.
        // Also handles new JS payload: updates[{rowNumber, cells{header:value}}] without explicit id.
        if (!isset($data['changes']) && isset($data['updates']) && is_array($data['updates'])) {
            $converted = [];
            foreach ($data['updates'] as $update) {
                if (!is_array($update)) {
                    continue;
                }
                $cells = $update['cells'] ?? [];
                if (!is_array($cells)) {
                    $cells = [];
                }

                // Map header-keyed cells to camelCase payload keys
                $mapped = $this->mapHeaderCellsToCamelCase($cells);

                $id = (int) ($update['id'] ?? ($cells['id'] ?? ($mapped['id'] ?? 0)));
                // If id still 0, look up the item by rowNumber in the sheet
                if ($id <= 0 && isset($update['rowNumber'])) {
                    $id = $this->repo->getIdByRowNumber($sheetType, (int) $update['rowNumber']);
                }

                if ($id > 0) {
                    $converted[] = array_merge(['id' => $id], $mapped);
                }
            }
            $data['changes'] = $converted;
        }

        $changes = $data['changes'] ?? [];
        if (!is_array($changes) || empty($changes)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'changes array is required.',
            ];
        }

        $updated = 0;
        $skipped = [];
        foreach ($changes as $index => $change) {
            if (!is_array($change)) {
                $skipped[] = ['index' => $index, 'reason' => 'Change is not an object'];
                continue;
            }

            $id = (int) ($change['id'] ?? 0);
            if ($id <= 0) {
                $skipped[] = ['index' => $index, 'reason' => 'Missing/invalid id'];
                continue;
            }

            $payload = $this->mapEditorRowToDbPayload($change, $sheetType);
            $payload['manually_edited'] = 1;
            $affected = $this->repo->updateItem($id, $payload);

            if ($affected > 0) {
                $updated++;
            } else {
                $skipped[] = ['index' => $index, 'id' => $id, 'reason' => 'Row not found or unchanged'];
            }
        }

        if (isset($data['headers']) && is_array($data['headers'])) {
            $this->repo->saveSchema($sheetType, $data['headers']);
        }

        if ($updated === 0) {
            return [
                'ok'      => false,
                'error'   => 'NO_ROWS_UPDATED',
                'message' => 'No menu rows were updated.',
                'skipped' => $skipped,
            ];
        }

        return [
            'ok'           => true,
            'action'       => 'admin_menu_editor_save_changes',
            'updatedCount' => $updated,
            'skipped'      => $skipped,
            'message'      => 'Updated ' . $updated . ' row(s).',
        ];
    }

    public function addRow(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Menu editor permission required.',
            ];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Invalid sheet name.',
            ];
        }

        $row = $data['row'] ?? [];
        if (!is_array($row)) {
            $row = [];
        }

        $payload = $this->mapEditorRowToDbPayload($row, $sheetType);
        if (trim((string) ($payload['item_name'] ?? '')) === '') {
            $payload['item_name'] = 'New Item';
        }

        $id = $this->repo->addItem(array_merge($payload, ['sheet_type' => $sheetType]));

        return [
            'ok'      => true,
            'action'  => 'admin_menu_editor_add_row',
            'id'      => $id,
            'message' => 'Menu row added.',
        ];
    }

    public function deleteRows(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Menu editor permission required.',
            ];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Invalid sheet name.',
            ];
        }

        $ids = $data['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        $deleted = $this->repo->deleteItems($sheetType, $ids);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_editor_delete_rows',
            'deletedCount' => $deleted,
        ];
    }

    public function setVisibility(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Menu editor permission required.',
            ];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Invalid sheet name.',
            ];
        }

        $ids = $data['ids'] ?? [];
        $isAvailable = !empty($data['isAvailable']);

        if (!is_array($ids)) {
            $ids = [];
        }

        $updated = $this->repo->setAvailability($sheetType, $ids, $isAvailable);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_editor_set_visibility',
            'updatedCount' => $updated,
        ];
    }

    public function designerLoad(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }

        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Menu editor permission required.',
            ];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Invalid sheet name.',
            ];
        }

        $rows = $this->repo->listItems($sheetType);
        $categoryOrder = $this->repo->getDesignerCategoryOrder($sheetType);
        $itemOrder = $this->repo->getDesignerItemOrder($sheetType);

        $categories = [];
        foreach ($rows as $row) {
            $item = MenuItem::fromDb($row)->toArray();
            $cat = trim((string) ($item['category'] ?? ''));
            if ($cat === '') {
                $cat = 'Other';
            }

            if (!isset($categories[$cat])) {
                $categories[$cat] = [
                    'name'  => $cat,
                    'items' => [],
                ];
            }

            $categories[$cat]['items'][] = [
                'id'          => (int) ($item['id'] ?? 0),
                'itemName'    => (string) ($item['itemName'] ?? ''),
                'isAvailable' => !empty($item['isAvailable']),
                'basePrice'   => $item['basePrice'] ?? null,
                'sortOrder'   => (int) ($item['sortOrder'] ?? 0),
            ];
        }

        $orderedCategoryNames = $this->mergeDesignerCategoryOrder(array_keys($categories), $categoryOrder);
        $orderedCategories = [];
        foreach ($orderedCategoryNames as $catName) {
            if (!isset($categories[$catName])) {
                continue;
            }

            $items = $categories[$catName]['items'];
            $items = $this->sortDesignerItems($items, $itemOrder[$catName] ?? []);
            $isCategoryAvailable = false;
            foreach ($items as $item) {
                if (!empty($item['isAvailable'])) {
                    $isCategoryAvailable = true;
                    break;
                }
            }

            $orderedCategories[] = [
                'name'        => $catName,
                'isAvailable' => $isCategoryAvailable,
                'items'       => $items,
            ];
        }

        return [
            'ok'        => true,
            'action'    => 'admin_menu_designer_load',
            'sheetType' => $sheetType,
            'sheetName' => self::sheetTypeToName($sheetType),
            'categories'=> $orderedCategories,
            'designer'  => [
                'categoryOrder' => $orderedCategoryNames,
                'itemOrder'     => $itemOrder,
            ],
        ];
    }

    public function designerSaveCategoryOrder(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid sheet name.'];
        }

        $categories = $data['categories'] ?? [];
        if (!is_array($categories) || empty($categories)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'categories array is required.'];
        }

        $clean = [];
        foreach ($categories as $category) {
            $name = trim((string) $category);
            if ($name !== '') {
                $clean[] = $name;
            }
        }

        $this->repo->saveDesignerCategoryOrder($sheetType, $clean);

        return [
            'ok'        => true,
            'action'    => 'admin_menu_designer_save_category_order',
            'sheetType' => $sheetType,
            'count'     => count($clean),
        ];
    }

    public function designerSaveItemOrder(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid sheet name.'];
        }

        $category = trim((string) ($data['category'] ?? ''));
        $itemIds = $data['itemIds'] ?? [];
        if ($category === '' || !is_array($itemIds) || empty($itemIds)) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'category and itemIds are required.'];
        }

        $this->repo->saveDesignerItemOrder($sheetType, $category, $itemIds);

        return [
            'ok'        => true,
            'action'    => 'admin_menu_designer_save_item_order',
            'sheetType' => $sheetType,
            'category'  => $category,
            'count'     => count(array_values(array_unique(array_map('intval', $itemIds)))),
        ];
    }

    public function designerToggleCategory(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid sheet name.'];
        }

        $category = trim((string) ($data['category'] ?? ''));
        if ($category === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'category is required.'];
        }

        $isAvailable = !empty($data['isAvailable']);
        $updated = $this->repo->setAvailabilityByCategory($sheetType, $category, $isAvailable);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_designer_toggle_category',
            'sheetType'    => $sheetType,
            'category'     => $category,
            'isAvailable'  => $isAvailable,
            'updatedCount' => $updated,
        ];
    }

    public function designerToggleItem(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? Constants::MENU_SHEET_FOOD));
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid sheet name.'];
        }

        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'id is required.'];
        }

        $isAvailable = !empty($data['isAvailable']);
        $updated = $this->repo->setAvailability($sheetType, [$id], $isAvailable);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_designer_toggle_item',
            'sheetType'    => $sheetType,
            'id'           => $id,
            'isAvailable'  => $isAvailable,
            'updatedCount' => $updated,
        ];
    }

    private function buildTabResponse(string $sheetType, string $shape): array
    {
        $rows = $this->repo->listItems($sheetType);
        $headers = $this->repo->getSchema($sheetType);
        $categoryOrder = $this->repo->getDesignerCategoryOrder($sheetType);
        $itemOrder = $this->repo->getDesignerItemOrder($sheetType);

        if ($shape === 'records') {
            $records = array_map(static fn(array $row) => MenuItem::fromDb($row)->toArray(), $rows);
            return [
                'ok'       => true,
                'tab'      => self::sheetTypeToName($sheetType),
                'shape'    => 'records',
                'headers'  => $headers,
                'records'  => $records,
                'designer' => [
                    'categoryOrder' => $categoryOrder,
                    'itemOrder'     => $itemOrder,
                ],
                'count'    => count($records),
            ];
        }

        // Default shape='grid' for compatibility with existing frontend consumers
        $grid = [];
        foreach ($rows as $row) {
            $item = MenuItem::fromDb($row)->toArray();
            $flat = [
                'id'          => $item['id'],
                'category'    => $item['category'],
                'subCategory' => $item['subCategory'],
                'itemName'    => $item['itemName'],
                'description' => $item['description'] ?? '',
                'imageUrl'    => $item['imageUrl'] ?? '',
                'availability'=> $item['isAvailable'] ? 'Available' : 'Unavailable',
                'isAvailable' => (bool) ($item['isAvailable'] ?? false),
                'isJain'        => (bool) ($item['isJain'] ?? false),
                'isVeg'         => (bool) ($item['isVeg'] ?? false),
                'isNonveg'      => (bool) ($item['isNonveg'] ?? false),
                'isUniversal'   => (bool) ($item['isUniversal'] ?? false),
                'isChefSpecial' => (bool) ($item['isChefSpecial'] ?? false),
                'spiceLevel'  => $item['spiceLevel'] ?? '',
                'servingUnit' => $item['servingUnit'] ?? '',
                'foodCategory'=> $item['foodCategory'],
                'basePrice'   => $item['basePrice'] ?? null,
                'priceColumns'=> $item['priceColumns'] ?? [],
                'meta'        => $item['meta'] ?? [],
                'sortOrder'   => $item['sortOrder'] ?? 0,
            ];

            foreach (($item['priceColumns'] ?? []) as $key => $value) {
                $flat[(string) $key] = $value;
            }

            if (!isset($flat['Price']) && $item['basePrice'] !== null) {
                $flat['Price'] = $item['basePrice'];
            }

            $grid[] = $flat;
        }

        return [
            'ok'      => true,
            'tab'     => self::sheetTypeToName($sheetType),
            'shape'   => 'grid',
            'headers' => $headers,
            'rows'    => $grid,
            'designer' => [
                'categoryOrder' => $categoryOrder,
                'itemOrder'     => $itemOrder,
            ],
            'count'   => count($grid),
        ];
    }

    private function mergeDesignerCategoryOrder(array $categories, array $configuredOrder): array
    {
        $seen = [];
        $ordered = [];

        foreach ($configuredOrder as $name) {
            $key = trim((string) $name);
            if ($key === '' || in_array($key, $seen, true) || !in_array($key, $categories, true)) {
                continue;
            }
            $ordered[] = $key;
            $seen[] = $key;
        }

        foreach ($categories as $name) {
            $key = trim((string) $name);
            if ($key === '' || in_array($key, $seen, true)) {
                continue;
            }
            $ordered[] = $key;
            $seen[] = $key;
        }

        return $ordered;
    }

    private function sortDesignerItems(array $items, array $orderedIds): array
    {
        if (empty($items)) {
            return [];
        }

        $rank = [];
        foreach (array_values($orderedIds) as $idx => $itemId) {
            $id = (int) $itemId;
            if ($id > 0 && !isset($rank[$id])) {
                $rank[$id] = $idx;
            }
        }

        usort($items, static function (array $a, array $b) use ($rank): int {
            $idA = (int) ($a['id'] ?? 0);
            $idB = (int) ($b['id'] ?? 0);
            $ordA = $rank[$idA] ?? (100000 + (int) ($a['sortOrder'] ?? 0));
            $ordB = $rank[$idB] ?? (100000 + (int) ($b['sortOrder'] ?? 0));

            if ($ordA === $ordB) {
                return $idA <=> $idB;
            }
            return $ordA <=> $ordB;
        });

        return $items;
    }

    private function mapEditorRowToDbPayload(array $row, string $sheetType): array
    {
        $priceColumns = [];
        if (isset($row['priceColumns']) && is_array($row['priceColumns'])) {
            $priceColumns = $row['priceColumns'];
        }

        // Capture dynamic numeric columns as price columns as fallback.
        foreach ($row as $key => $value) {
            if (in_array((string) $key, [
                'id',
                'category', 'subCategory', 'sub_category',
                'itemName', 'item_name',
                'description', 'imageUrl', 'image_url',
                'isAvailable', 'availability',
                'foodCategory', 'food_category',
                'isJain', 'jain',
                'isVeg', 'is_veg',
                'isNonveg', 'is_nonveg',
                'isUniversal', 'is_universal',
                'isChefSpecial', 'chef', 'chefSpecial',
                'spice', 'spiceLevel', 'spice_level',
                'servingUnit', 'serving_unit',
                'sortOrder', 'sort_order',
                'basePrice', 'base_price',
                'meta', 'meta_json'
            ], true)) {
                continue;
            }
            if (is_scalar($value) && is_numeric((string) $value)) {
                $priceColumns[(string) $key] = (float) $value;
            }
        }

        $foodCategory = trim((string) ($row['foodCategory'] ?? $row['food_category'] ?? ''));
        if ($sheetType === Constants::MENU_SHEET_FOOD) {
            if (!in_array($foodCategory, ['Veg', 'NonVeg', 'Jain', ''], true)) {
                $foodCategory = '';
            }
        } else {
            $foodCategory = '';
        }

        $primaryDiet = trim((string) ($row['primaryDiet'] ?? $row['primary_diet'] ?? ''));
        $allowedDiets = ['veg', 'nonveg', 'jain', 'mixed', 'universal', 'bar', ''];
        if (!in_array($primaryDiet, $allowedDiets, true)) {
            $primaryDiet = '';
        }

        $availability = $row['isAvailable'] ?? $row['availability'] ?? true;
        if (is_string($availability)) {
            $norm = strtolower(trim($availability));
            $availability = !in_array($norm, ['0', 'false', 'no', 'off', 'hidden', 'inactive', 'unavailable'], true);
        }

        $isJain = $row['isJain'] ?? $row['jain'] ?? false;
        if (is_string($isJain)) {
            $isJain = in_array(strtolower(trim($isJain)), ['1', 'true', 'yes', 'jain'], true);
        }

        $isChefSpecial = $row['isChefSpecial'] ?? $row['chef'] ?? $row['chefSpecial'] ?? false;
        if (is_string($isChefSpecial)) {
            $isChefSpecial = in_array(strtolower(trim($isChefSpecial)), ['1', 'true', 'yes'], true);
        }

        $isVeg = $row['isVeg'] ?? $row['is_veg'] ?? false;
        if (is_string($isVeg)) {
            $isVeg = in_array(strtolower(trim($isVeg)), ['1', 'true', 'yes'], true);
        }

        $isNonveg = $row['isNonveg'] ?? $row['is_nonveg'] ?? false;
        if (is_string($isNonveg)) {
            $isNonveg = in_array(strtolower(trim($isNonveg)), ['1', 'true', 'yes'], true);
        }

        $isUniversal = $row['isUniversal'] ?? $row['is_universal'] ?? false;
        if (is_string($isUniversal)) {
            $isUniversal = in_array(strtolower(trim($isUniversal)), ['1', 'true', 'yes'], true);
        }

        $basePrice = $row['basePrice'] ?? $row['base_price'] ?? null;
        if ($basePrice === null && isset($priceColumns['Price']) && is_numeric((string) $priceColumns['Price'])) {
            $basePrice = (float) $priceColumns['Price'];
        }
        if ($basePrice !== null && !is_numeric((string) $basePrice)) {
            $basePrice = null;
        }

        $meta = $row['meta'] ?? $row['meta_json'] ?? [];
        if (!is_array($meta)) {
            $meta = [];
        }

        return [
            'category'        => trim((string) ($row['category'] ?? '')),
            'sub_category'    => trim((string) ($row['subCategory'] ?? $row['sub_category'] ?? '')),
            'item_name'       => trim((string) ($row['itemName'] ?? $row['item_name'] ?? '')),
            'description'     => trim((string) ($row['description'] ?? '')),
            'image_url'       => trim((string) ($row['imageUrl'] ?? $row['image_url'] ?? '')),
            'is_available'    => (bool) $availability,
            'is_jain'         => (bool) $isJain,
            'is_veg'          => (bool) $isVeg,
            'is_nonveg'       => (bool) $isNonveg,
            'is_universal'    => (bool) $isUniversal,
            'is_chef_special' => (bool) $isChefSpecial,
            'spice_level'     => trim((string) ($row['spice'] ?? $row['spiceLevel'] ?? $row['spice_level'] ?? '')),
            'serving_unit'    => trim((string) ($row['servingUnit'] ?? $row['serving_unit'] ?? '')),
            'base_price'      => $basePrice !== null ? (float) $basePrice : null,
            'price_columns'   => $priceColumns,
            'food_category'   => $foodCategory,
            'primary_diet'    => $primaryDiet,
            'meta_json'       => $meta,
            'sort_order'      => (int) ($row['sortOrder'] ?? $row['sort_order'] ?? 0),
        ];
    }

    private function buildEditableMeta(array $headers, string $sheetType): array
    {
        $meta = [];
        foreach ($headers as $header) {
            $lower = strtolower(trim((string) $header));
            if (in_array($lower, ['availability', 'available', 'isavailable'], true)) {
                $meta[$header] = 'visibility';
            } elseif (in_array($lower, ['is veg'], true)) {
                $meta[$header] = 'vegflag';
            } elseif (in_array($lower, ['is nonveg', 'is non veg'], true)) {
                $meta[$header] = 'nonvegflag';
            } elseif (in_array($lower, ['is universal'], true)) {
                $meta[$header] = 'universalflag';
            } elseif (in_array($lower, ["chef special", "chef's special", "is chef special"], true)) {
                $meta[$header] = 'chef';
            } elseif (in_array($lower, ['spice level', 'spice'], true)) {
                $meta[$header] = 'spice';
            } elseif (in_array($lower, ['primary diet'], true)) {
                $meta[$header] = 'diet';
            } elseif (in_array($lower, ['diet flags'], true)) {
                $meta[$header] = 'dietflags';
            } elseif (in_array($lower, [
                'category', 'sub category', 'item name', 'description',
                'image url', 'food category', 'unit (pcs)', 'serving unit',
            ], true)) {
                $meta[$header] = 'text';
            } else {
                // Anything else is treated as a price column
                $meta[$header] = 'price';
            }
        }
        return $meta;
    }

    private function itemToCells(array $item, array $headers): array
    {
        $cells = [];
        $staticMap = [
            'Category'      => $item['category'],
            'Sub Category'  => $item['subCategory'],
            'Item Name'     => $item['itemName'],
            'Description'   => $item['description'],
            'Image URL'     => $item['imageUrl'],
            'Availability'  => $item['isAvailable'] ? 'Yes' : 'No',
            'Food Category' => $item['foodCategory'],
            'Jain'          => $item['isJain'] ? 'Yes' : 'No',
            'Is Veg'        => ($item['isVeg'] ?? false) ? 'Yes' : 'No',
            'Is Nonveg'     => ($item['isNonveg'] ?? false) ? 'Yes' : 'No',
            'Is Universal'  => ($item['isUniversal'] ?? false) ? 'Yes' : 'No',
            'Chef Special'  => $item['isChefSpecial'] ? 'Yes' : 'No',
            "Chef's Special"=> $item['isChefSpecial'] ? 'Yes' : 'No',
            'Spice Level'   => $item['spiceLevel'] ?? '',
            'Unit (Pcs)'    => $item['servingUnit'] ?? '',
            'Serving Unit'  => $item['servingUnit'] ?? '',
            'Price'         => $item['basePrice'] !== null ? (string) $item['basePrice'] : '',
            'Base Price'    => $item['basePrice'] !== null ? (string) $item['basePrice'] : '',
            'Primary Diet'  => $item['primaryDiet'] ?? '',
            'Diet Flags'    => implode(', ', $item['computedDiets'] ?? []),
        ];

        foreach ($headers as $header) {
            if (array_key_exists($header, $staticMap)) {
                $cells[$header] = (string) ($staticMap[$header] ?? '');
                continue;
            }
            // Check price columns
            $priceColumns = $item['priceColumns'] ?? [];
            if (array_key_exists($header, $priceColumns)) {
                $cells[$header] = (string) $priceColumns[$header];
                continue;
            }
            $cells[$header] = '';
        }
        return $cells;
    }

    private function mapHeaderCellsToCamelCase(array $cells): array
    {
        $headerMap = [
            'category'       => 'category',
            'sub category'   => 'subCategory',
            'item name'      => 'itemName',
            'description'    => 'description',
            'image url'      => 'imageUrl',
            'availability'   => 'isAvailable',
            'food category'  => 'foodCategory',
            'primary diet'   => 'primaryDiet',
            'jain'           => 'isJain',
            'is veg'         => 'isVeg',
            'is nonveg'      => 'isNonveg',
            'is non veg'     => 'isNonveg',
            'is universal'   => 'isUniversal',
            'chef special'   => 'isChefSpecial',
            "chef's special" => 'isChefSpecial',
            'spice level'    => 'spiceLevel',
            'unit (pcs)'     => 'servingUnit',
            'serving unit'   => 'servingUnit',
            'price'          => 'basePrice',
            'base price'     => 'basePrice',
        ];

        $result = [];
        $priceColumns = [];
        foreach ($cells as $header => $value) {
            $lower = strtolower(trim((string) $header));
            if (isset($headerMap[$lower])) {
                $result[$headerMap[$lower]] = $value;
            } elseif (is_numeric((string) $value) || $value === '' || $value === null) {
                // Likely a price column (e.g. Half, Full, Chicken, Glass)
                $priceColumns[$header] = $value;
            } else {
                // Pass through as-is (camelCase keys from JS)
                $result[$header] = $value;
            }
        }
        if (!empty($priceColumns)) {
            $result['priceColumns'] = $priceColumns;
        }
        return $result;
    }

    private function normalizeSheetType(string $sheetNameOrType): ?string
    {
        $value = strtolower(trim($sheetNameOrType));

        if ($value === Constants::MENU_SHEET_FOOD || $value === 'awgnk menu') {
            return Constants::MENU_SHEET_FOOD;
        }

        if ($value === Constants::MENU_SHEET_BAR || $value === 'bar menu nk') {
            return Constants::MENU_SHEET_BAR;
        }

        return null;
    }

    private function mapTabToSheetType(string $tab): ?string
    {
        return $this->normalizeSheetType($tab);
    }

    public function addColumn(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? ''));
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid sheet name.'];
        }

        $columnName = trim((string) ($data['columnName'] ?? ''));
        if ($columnName === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'columnName is required.'];
        }

        $reserved = [
            'category', 'sub category', 'item name', 'description', 'image url',
            'availability', 'food category', 'primary diet', 'price', 'base price',
            'jain', 'is jain', 'is veg', 'is nonveg', 'is universal',
            'chef special', "chef's special", 'spice level', 'unit (pcs)', 'serving unit',
        ];
        if (in_array(strtolower($columnName), $reserved, true)) {
            return ['ok' => false, 'error' => 'RESERVED_NAME', 'message' => "\"$columnName\" is a reserved column name."];
        }

        $headers = $this->repo->getSchema($sheetType);
        foreach ($headers as $h) {
            if (strtolower(trim((string) $h)) === strtolower($columnName)) {
                return ['ok' => false, 'error' => 'DUPLICATE_COLUMN', 'message' => "Column \"$columnName\" already exists."];
            }
        }

        $headers[] = $columnName;
        $this->repo->saveSchema($sheetType, $headers);

        return [
            'ok'      => true,
            'action'  => 'admin_menu_editor_add_column',
            'headers' => $headers,
            'message' => "Column \"$columnName\" added.",
        ];
    }

    public function renameColumn(array $data): array
    {
        $auth = AuthMiddleware::authorize($data, 'admin');
        if (!$auth['ok']) {
            return $auth;
        }
        if (!AuthMiddleware::requirePermission($auth['user'], 'menuEditor')) {
            return ['ok' => false, 'error' => 'FORBIDDEN', 'message' => 'Menu editor permission required.'];
        }

        $sheetType = $this->normalizeSheetType((string) ($data['sheetName'] ?? $data['sheetType'] ?? ''));
        if ($sheetType === null) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'Invalid sheet name.'];
        }

        $oldName = trim((string) ($data['oldName'] ?? ''));
        $newName = trim((string) ($data['newName'] ?? ''));
        if ($oldName === '' || $newName === '') {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'oldName and newName are required.'];
        }
        if ($oldName === $newName) {
            return ['ok' => false, 'error' => 'INVALID_INPUT', 'message' => 'New name must differ from old name.'];
        }

        $headers = $this->repo->getSchema($sheetType);
        $foundIdx = null;
        foreach ($headers as $idx => $h) {
            if (strtolower(trim((string) $h)) === strtolower($oldName)) {
                $foundIdx = $idx;
                break;
            }
        }
        if ($foundIdx === null) {
            return ['ok' => false, 'error' => 'NOT_FOUND', 'message' => "Column \"$oldName\" not found."];
        }

        foreach ($headers as $idx => $h) {
            if ($idx !== $foundIdx && strtolower(trim((string) $h)) === strtolower($newName)) {
                return ['ok' => false, 'error' => 'DUPLICATE_COLUMN', 'message' => "Column \"$newName\" already exists."];
            }
        }

        $headers[$foundIdx] = $newName;
        $this->repo->saveSchema($sheetType, $headers);
        $updated = $this->repo->renamePriceColumnKey($sheetType, $oldName, $newName);

        return [
            'ok'           => true,
            'action'       => 'admin_menu_editor_rename_column',
            'headers'      => array_values($headers),
            'updatedItems' => $updated,
            'message'      => "Column \"$oldName\" renamed to \"$newName\".",
        ];
    }

    public static function sheetTypeToName(string $sheetType): string
    {
        return $sheetType === Constants::MENU_SHEET_BAR ? 'BAR MENU NK' : 'AWGNK MENU';
    }
}
