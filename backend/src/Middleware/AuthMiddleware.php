<?php

declare(strict_types=1);

namespace NK\Middleware;

use NK\Config\Constants;
use NK\Services\AuthService;

class AuthMiddleware
{
    /**
     * Returns authenticated user context or an error payload.
     *
     * @param array $data request body/query merged payload
     * @param string $requiredRole admin|superadmin
     * @return array{ok:bool,user?:array,error?:string,message?:string}
     */
    public static function authorize(array $data, string $requiredRole = 'admin'): array
    {
        $token = AuthService::extractToken($data);
        if ($token === '') {
            return [
                'ok'      => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'Missing auth token.',
            ];
        }

        $verified = AuthService::verifyToken($token);
        if (!$verified['ok']) {
            return $verified;
        }

        $payload = $verified['payload'];
        $user = AuthService::getPublicUserByUsername((string) ($payload['sub'] ?? ''));
        if (!$user) {
            return [
                'ok'      => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'User not found.',
            ];
        }

        if (($user['status'] ?? '') !== 'active') {
            return [
                'ok'      => false,
                'error'   => 'USER_DISABLED',
                'message' => 'This user account is disabled.',
            ];
        }

        if ($requiredRole === 'superadmin' && ($user['role'] ?? '') !== 'superadmin') {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Superadmin access required.',
            ];
        }

        return [
            'ok'      => true,
            'token'   => $token,
            'payload' => $payload,
            'user'    => $user,
        ];
    }

    /**
     * Permission gate helper for admin modules.
     */
    public static function requirePermission(array $user, string $permission): bool
    {
        if (($user['role'] ?? '') === 'superadmin') {
            return true;
        }

        if (!in_array($permission, Constants::ADMIN_PERMISSION_KEYS, true)) {
            return false;
        }

        $permissions = $user['permissions'] ?? [];
        if (!is_array($permissions)) {
            return false;
        }

        if (array_keys($permissions) === range(0, count($permissions) - 1)) {
            return in_array($permission, $permissions, true);
        }

        return !empty($permissions[$permission]);
    }
}
