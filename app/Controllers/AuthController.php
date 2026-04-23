<?php

declare(strict_types=1);

namespace NK\Controllers;

use NK\Services\AuthService;

class AuthController
{
    public static function bootstrapStatus(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->bootstrapStatus();
    }

    public static function login(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->login($body);
    }

    public static function logout(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->logout($body);
    }

    public static function me(array $body, array $query): array
    {
        $service = new AuthService();
        $payload = array_merge($query, $body);
        return $service->me($payload);
    }

    public static function changePassword(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->changePassword($body);
    }

    public static function createUser(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->createUser($body);
    }

    public static function setUserStatus(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->setUserStatus($body);
    }

    public static function deleteUser(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->deleteUser($body);
    }

    public static function resetPassword(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->resetPassword($body);
    }

    public static function setUserPermissions(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->setUserPermissions($body);
    }

    public static function getApiSettings(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->getApiSettings($body);
    }

    public static function setApiSettings(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->setApiSettings($body);
    }

    public static function getWhatsAppWorkspace(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->getWhatsAppWorkspace($body);
    }

    public static function saveWhatsAppConfig(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->saveWhatsAppConfig($body);
    }

    public static function syncWhatsAppTemplates(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->syncWhatsAppTemplates($body);
    }

    public static function saveWhatsAppEventMapping(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->saveWhatsAppEventMapping($body);
    }

    public static function sendTestWhatsAppTemplate(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->sendTestWhatsAppTemplate($body);
    }

    public static function saveWhatsAppTemplateDraft(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->saveWhatsAppTemplateDraft($body);
    }

    public static function submitWhatsAppTemplateDraft(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->submitWhatsAppTemplateDraft($body);
    }

    public static function previewWhatsAppTemplate(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->previewWhatsAppTemplate($body);
    }

    public static function runWhatsAppScheduler(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->runWhatsAppScheduler($body);
    }

    public static function getAppSettings(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->getAppSettings($body);
    }

    public static function setAppSettings(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->setAppSettings($body);
    }

    public static function getQrRedirectSettings(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->getQrRedirectSettings($body);
    }

    public static function setQrRedirectSettings(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->setQrRedirectSettings($body);
    }

    public static function listQrRedirects(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->listQrRedirects($body);
    }

    public static function saveQrRedirect(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->saveQrRedirect($body);
    }

    public static function setQrRedirectActive(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->setQrRedirectActive($body);
    }

    public static function deleteQrRedirect(array $body, array $query): array
    {
        $service = new AuthService();
        return $service->deleteQrRedirect($body);
    }

    public static function listUsers(array $body, array $query): array
    {
        $service = new AuthService();
        $payload = array_merge($query, $body);
        return $service->listUsers($payload);
    }
}
