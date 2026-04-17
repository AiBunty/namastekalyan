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
    public string $description = '';
    public string $imageUrl = '';
    public bool $isAvailable = true;
    public bool $isJain = false;
    public bool $isVeg = false;
    public bool $isNonveg = false;
    public bool $isUniversal = false;
    public bool $isChefSpecial = false;
    public string $spiceLevel = '';
    public string $servingUnit = '';
    public ?float $basePrice = null;
    public array $priceColumns = [];
    public array $meta = [];
    public string $foodCategory = '';
    public array $computedDiets = [];
    public string $primaryDiet = '';
    public array $dietColumnMap = [];
    public int $sortOrder = 0;
    public bool $manuallyEdited = false;

    public static function fromDb(array $row): self
    {
        $item = new self();
        $item->id = (int) ($row['id'] ?? 0);
        $item->sheetType = (string) ($row['sheet_type'] ?? 'food');
        $item->category = (string) ($row['category'] ?? '');
        $item->subCategory = (string) ($row['sub_category'] ?? '');
        $item->itemName = (string) ($row['item_name'] ?? '');
        $item->description = (string) ($row['description'] ?? '');
        $item->imageUrl = (string) ($row['image_url'] ?? '');
        $item->isAvailable = ((int) ($row['is_available'] ?? 1) === 1);
        $item->isJain = ((int) ($row['is_jain'] ?? 0) === 1);
        $item->isVeg = ((int) ($row['is_veg'] ?? 0) === 1);
        $item->isNonveg = ((int) ($row['is_nonveg'] ?? 0) === 1);
        $item->isUniversal = ((int) ($row['is_universal'] ?? 0) === 1);
        $item->isChefSpecial = ((int) ($row['is_chef_special'] ?? 0) === 1);
        $item->spiceLevel = (string) ($row['spice_level'] ?? '');
        $item->servingUnit = (string) ($row['serving_unit'] ?? '');
        $item->basePrice = isset($row['base_price']) ? (float) $row['base_price'] : null;

        $prices = $row['price_columns'] ?? [];
        if (is_string($prices)) {
            $decoded = json_decode($prices, true);
            $prices = is_array($decoded) ? $decoded : [];
        }
        $item->priceColumns = is_array($prices) ? $prices : [];

        $meta = $row['meta_json'] ?? [];
        if (is_string($meta)) {
            $decodedMeta = json_decode($meta, true);
            $meta = is_array($decodedMeta) ? $decodedMeta : [];
        }
        $item->meta = is_array($meta) ? $meta : [];

        $item->foodCategory = (string) ($row['food_category'] ?? '');

        $computedDiets = $row['computed_diets'] ?? [];
        if (is_string($computedDiets)) {
            $decoded = json_decode($computedDiets, true);
            $computedDiets = is_array($decoded) ? $decoded : [];
        }
        $item->computedDiets = is_array($computedDiets) ? $computedDiets : [];

        $item->primaryDiet = (string) ($row['primary_diet'] ?? '');

        $dietColumnMap = $row['diet_column_map'] ?? [];
        if (is_string($dietColumnMap)) {
            $decoded = json_decode($dietColumnMap, true);
            $dietColumnMap = is_array($decoded) ? $decoded : [];
        }
        $item->dietColumnMap = is_array($dietColumnMap) ? $dietColumnMap : [];

        $item->sortOrder = (int) ($row['sort_order'] ?? 0);
        $item->manuallyEdited = ((int) ($row['manually_edited'] ?? 0) === 1);
        return $item;
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'sheetType'     => $this->sheetType,
            'category'      => $this->category,
            'subCategory'   => $this->subCategory,
            'itemName'      => $this->itemName,
            'description'   => $this->description,
            'imageUrl'      => $this->imageUrl,
            'isAvailable'   => $this->isAvailable,
            'isJain'        => $this->isJain,
            'isVeg'         => $this->isVeg,
            'isNonveg'      => $this->isNonveg,
            'isUniversal'   => $this->isUniversal,
            'isChefSpecial' => $this->isChefSpecial,
            'spiceLevel'    => $this->spiceLevel,
            'servingUnit'   => $this->servingUnit,
            'basePrice'     => $this->basePrice,
            'priceColumns'  => $this->priceColumns,
            'meta'          => $this->meta,
            'foodCategory'  => $this->foodCategory,
            'computedDiets' => $this->computedDiets,
            'primaryDiet'   => $this->primaryDiet,
            'dietColumnMap' => $this->dietColumnMap,
            'sortOrder'     => $this->sortOrder,
            'manuallyEdited'=> $this->manuallyEdited,
        ];
    }
}
