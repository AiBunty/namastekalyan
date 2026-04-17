<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Services\MenuService;

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

    public static function designerLoad(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->designerLoad($body);
    }

    public static function designerSaveCategoryOrder(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->designerSaveCategoryOrder($body);
    }

    public static function designerSaveItemOrder(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->designerSaveItemOrder($body);
    }

    public static function designerToggleCategory(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->designerToggleCategory($body);
    }

    public static function designerToggleItem(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->designerToggleItem($body);
    }

    public static function addColumn(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->addColumn($body);
    }

    public static function renameColumn(array $body, array $query): array
    {
        $service = new MenuService();
        return $service->renameColumn($body);
    }
}
