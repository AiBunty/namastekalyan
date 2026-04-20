<?php

declare(strict_types=1);

namespace NK\Services\Menu;

class MenuImageFilename
{
    public const FOOD_EXPORT_HEADER = 'Image File Name';

    public static function buildFoodBaseName(string $category, string $itemName): string
    {
        $categoryPart = self::normalizeSegment($category);
        $itemPart = self::normalizeSegment($itemName);

        if ($categoryPart !== '' && $itemPart !== '') {
            return $categoryPart . '--' . $itemPart;
        }

        $value = $categoryPart !== '' ? $categoryPart : $itemPart;
        return $value !== '' ? $value : 'menu-item';
    }

    public static function normalizeUploadedStem(string $stem): string
    {
        return self::normalizeCompositeValue($stem);
    }

    public static function normalizeForLookup(string $value): string
    {
        return self::normalizeCompositeValue($value);
    }

    private static function normalizeCompositeValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                $value = $transliterated;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9-]+/', '-', $value) ?? $value;
        $value = preg_replace('/-{3,}/', '--', $value) ?? $value;
        return trim($value, '-');
    }

    private static function normalizeSegment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('iconv')) {
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($transliterated) && $transliterated !== '') {
                $value = $transliterated;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value;
    }
}