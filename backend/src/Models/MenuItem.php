<?php

declare(strict_types=1);

namespace NK\Models;

class MenuItem
{
    public int $id = 0;
    public string $sheetType = 'food';
    public string $category = '';
    public string $subCategory = '';
    public string $itemName = '';
    public bool $isAvailable = true;
    public ?float $basePrice = null;
    public array $priceColumns = [];
    public string $foodCategory = '';
    public int $sortOrder = 0;

    public static function fromDb(array $row): self
    {
        $item = new self();
        $item->id = (int) ($row['id'] ?? 0);
        $item->sheetType = (string) ($row['sheet_type'] ?? 'food');
        $item->category = (string) ($row['category'] ?? '');
        $item->subCategory = (string) ($row['sub_category'] ?? '');
        $item->itemName = (string) ($row['item_name'] ?? '');
        $item->isAvailable = ((int) ($row['is_available'] ?? 1) === 1);
        $item->basePrice = isset($row['base_price']) ? (float) $row['base_price'] : null;

        $prices = $row['price_columns'] ?? [];
        if (is_string($prices)) {
            $decoded = json_decode($prices, true);
            $prices = is_array($decoded) ? $decoded : [];
        }
        $item->priceColumns = is_array($prices) ? $prices : [];

        $item->foodCategory = (string) ($row['food_category'] ?? '');
        $item->sortOrder = (int) ($row['sort_order'] ?? 0);
        return $item;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'sheetType'    => $this->sheetType,
            'category'     => $this->category,
            'subCategory'  => $this->subCategory,
            'itemName'     => $this->itemName,
            'isAvailable'  => $this->isAvailable,
            'basePrice'    => $this->basePrice,
            'priceColumns' => $this->priceColumns,
            'foodCategory' => $this->foodCategory,
            'sortOrder'    => $this->sortOrder,
        ];
    }
}
