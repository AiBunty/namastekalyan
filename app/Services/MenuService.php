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
                ? ['Category', 'Sub Category', 'Item Name', 'Price', 'Availability', 'Food Category']
                : ['Category', 'Item Name', 'Price', 'Availability'];
        }

        $records = array_map(static function (array $row): array {
            return MenuItem::fromDb($row)->toArray();
        }, $rows);

        return [
            'ok'         => true,
            'action'     => 'admin_menu_editor_load',
            'sheetType'  => $sheetType,
            'sheetName'  => self::sheetTypeToName($sheetType),
            'headers'    => $headers,
            'rows'       => $records,
            'rowCount'   => count($records),
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

        $changes = $data['changes'] ?? [];
        if (!is_array($changes)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'changes array is required.',
            ];
        }

        $updated = 0;
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $id = (int) ($change['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $payload = $this->mapEditorRowToDbPayload($change, $sheetType);
            $this->repo->updateItem($id, $payload);
            $updated++;
        }

        if (isset($data['headers']) && is_array($data['headers'])) {
            $this->repo->saveSchema($sheetType, $data['headers']);
        }

        return [
            'ok'           => true,
            'action'       => 'admin_menu_editor_save_changes',
            'updatedCount' => $updated,
            'message'      => 'Menu changes saved successfully.',
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

    private function buildTabResponse(string $sheetType, string $shape): array
    {
        $rows = $this->repo->listItems($sheetType);
        $headers = $this->repo->getSchema($sheetType);

        if ($shape === 'records') {
            $records = array_map(static fn(array $row) => MenuItem::fromDb($row)->toArray(), $rows);
            return [
                'ok'       => true,
                'tab'      => self::sheetTypeToName($sheetType),
                'shape'    => 'records',
                'headers'  => $headers,
                'records'  => $records,
                'count'    => count($records),
            ];
        }

        // Default shape='grid' for compatibility with existing frontend consumers
        $grid = [];
        foreach ($rows as $row) {
            $item = MenuItem::fromDb($row)->toArray();
            $flat = [
                'category'    => $item['category'],
                'subCategory' => $item['subCategory'],
                'itemName'    => $item['itemName'],
                'availability'=> $item['isAvailable'] ? 'Available' : 'Unavailable',
                'foodCategory'=> $item['foodCategory'],
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
            'count'   => count($grid),
        ];
    }

    private function mapEditorRowToDbPayload(array $row, string $sheetType): array
    {
        $priceColumns = [];
        if (isset($row['priceColumns']) && is_array($row['priceColumns'])) {
            $priceColumns = $row['priceColumns'];
        }

        // Capture dynamic numeric columns as price columns as fallback.
        foreach ($row as $key => $value) {
            if (in_array((string) $key, ['id', 'category', 'subCategory', 'sub_category', 'itemName', 'item_name', 'isAvailable', 'availability', 'foodCategory', 'food_category', 'sortOrder', 'sort_order', 'basePrice', 'base_price'], true)) {
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

        $availability = $row['isAvailable'] ?? $row['availability'] ?? true;
        if (is_string($availability)) {
            $availability = in_array(strtolower(trim($availability)), ['1', 'true', 'yes', 'available', 'on'], true);
        }

        $basePrice = $row['basePrice'] ?? $row['base_price'] ?? null;
        if ($basePrice === null && isset($priceColumns['Price']) && is_numeric((string) $priceColumns['Price'])) {
            $basePrice = (float) $priceColumns['Price'];
        }
        if ($basePrice !== null && !is_numeric((string) $basePrice)) {
            $basePrice = null;
        }

        return [
            'category'      => trim((string) ($row['category'] ?? '')),
            'sub_category'  => trim((string) ($row['subCategory'] ?? $row['sub_category'] ?? '')),
            'item_name'     => trim((string) ($row['itemName'] ?? $row['item_name'] ?? '')),
            'is_available'  => (bool) $availability,
            'base_price'    => $basePrice !== null ? (float) $basePrice : null,
            'price_columns' => $priceColumns,
            'food_category' => $foodCategory,
            'sort_order'    => (int) ($row['sortOrder'] ?? $row['sort_order'] ?? 0),
        ];
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

    public static function sheetTypeToName(string $sheetType): string
    {
        return $sheetType === Constants::MENU_SHEET_BAR ? 'BAR MENU NK' : 'AWGNK MENU';
    }
}
