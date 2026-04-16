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
}
