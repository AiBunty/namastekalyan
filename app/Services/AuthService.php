<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Repositories\ApiSettingsRepository;
use NK\Repositories\AuthAuditRepository;
use NK\Repositories\EventRepository;
use NK\Repositories\QrRedirectRepository;
use NK\Repositories\QrRedirectSettingsRepository;
use NK\Repositories\RevokedTokenRepository;
use NK\Repositories\UserRepository;
use NK\Support\SiteUrl;
use NK\Support\Validator;

class AuthService
{
    private const APP_SETTINGS_FILE = __DIR__ . '/../../config/app-settings.json';
    private const APP_SETTINGS_PAGE_KEYS = ['home', 'menu', 'cocktail'];
    private const APP_SETTINGS_DESTINATIONS = ['home', 'menu', 'cocktail'];

    private static function getProtectedBootstrapSuperadminUsername(): string
    {
        return self::normalizeUsername((string) ($_ENV['BOOTSTRAP_SUPERADMIN_MOBILE'] ?? ''));
    }

    private UserRepository $users;
    private ApiSettingsRepository $apiSettings;
    private AuthAuditRepository $audit;
    private RevokedTokenRepository $revokedTokens;
    private QrRedirectSettingsRepository $qrRedirectSettings;
    private QrRedirectRepository $qrRedirects;
    private EventRepository $events;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->apiSettings = new ApiSettingsRepository();
        $this->audit = new AuthAuditRepository();
        $this->revokedTokens = new RevokedTokenRepository();
        $this->qrRedirectSettings = new QrRedirectSettingsRepository();
        $this->qrRedirects = new QrRedirectRepository();
        $this->events = new EventRepository();
    }

    public static function normalizeUsername(string $value): string
    {
        return strtolower(trim(preg_replace('/\s+/', '', $value)));
    }

    public static function extractToken(array $data): string
    {
        $authorization = trim((string) ($data['authorization'] ?? $data['Authorization'] ?? ''));
        if (preg_match('/^bearer\s+/i', $authorization)) {
            return trim(preg_replace('/^bearer\s+/i', '', $authorization));
        }

        return trim((string) ($data['token'] ?? $data['authToken'] ?? $data['jwt'] ?? ''));
    }

    public static function getPublicUserByUsername(string $username): ?array
    {
        $repo = new UserRepository();
        $user = $repo->findByUsername(self::normalizeUsername($username));
        if (!$user) {
            return null;
        }
        return self::toPublicUser($user);
    }

    public function bootstrapStatus(): array
    {
        $users = $this->users->listAll();
        $hasSuperadmin = false;
        foreach ($users as $user) {
            if (($user['role'] ?? '') === 'superadmin') {
                $hasSuperadmin = true;
                break;
            }
        }

        return [
            'ok'           => true,
            'initialized'  => $hasSuperadmin,
            'hasUsers'     => count($users) > 0,
            'sessionHours' => Constants::AUTH_TOKEN_HOURS,
            'lockout'      => [
                'maxAttempts'    => Constants::AUTH_LOCKOUT_MAX_ATTEMPTS,
                'lockoutMinutes' => Constants::AUTH_LOCKOUT_MINUTES,
            ],
        ];
    }

    public function ensureBootstrapSuperadmin(): void
    {
        $bootstrapUsername = self::normalizeUsername((string) ($_ENV['BOOTSTRAP_SUPERADMIN_MOBILE'] ?? ''));
        $bootstrapPassword = trim((string) ($_ENV['BOOTSTRAP_SUPERADMIN_PASSWORD'] ?? ''));

        if ($bootstrapUsername === '' || $bootstrapPassword === '') {
            return;
        }

        if ($this->users->findByUsername($bootstrapUsername)) {
            return;
        }

        [$hash, $salt] = self::buildLegacyPasswordHash($bootstrapPassword);

        $permissions = [];
        foreach (Constants::ADMIN_PERMISSION_KEYS as $key) {
            $permissions[$key] = true;
        }

        $this->users->create([
            'username'              => $bootstrapUsername,
            'display_name'          => 'Super Admin',
            'role'                  => 'superadmin',
            'password_hash'         => $hash,
            'password_salt'         => $salt,
            'status'                => 'active',
            'force_password_change' => 1,
            'permissions'           => $permissions,
            'created_by'            => 'bootstrap',
        ]);

        $this->audit->log('auth_bootstrap_create', $bootstrapUsername, 'success', 'bootstrap_superadmin_created');
    }

    public function login(array $data): array
    {
        $this->ensureBootstrapSuperadmin();

        $username = self::normalizeUsername((string) ($data['username'] ?? $data['mobile'] ?? $data['phone'] ?? ''));
        $password = trim((string) ($data['password'] ?? ''));

        if ($username === '' || $password === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'username and password are required.',
            ];
        }

        $user = $this->users->findByUsername($username);
        if (!$user) {
            $this->audit->log('auth_login', $username, 'failed', 'user_not_found');
            return [
                'ok'      => false,
                'error'   => 'INVALID_CREDENTIALS',
                'message' => 'Invalid username or password.',
            ];
        }

        if (($user['status'] ?? '') !== 'active') {
            $this->audit->log('auth_login', $username, 'failed', 'user_disabled');
            return [
                'ok'      => false,
                'error'   => 'USER_DISABLED',
                'message' => 'This user account is disabled.',
            ];
        }

        if ($this->isAccountLocked($user)) {
            $this->audit->log('auth_login', $username, 'failed', 'account_locked');
            return [
                'ok'      => false,
                'error'   => 'ACCOUNT_LOCKED',
                'message' => 'Too many failed attempts. Please try again later.',
            ];
        }

        $isValidPassword = $this->verifyPassword($password, $user);

        if (!$isValidPassword) {
            $this->registerFailedLoginAttempt($user);
            $this->audit->log('auth_login', $username, 'failed', 'invalid_password');
            return [
                'ok'      => false,
                'error'   => 'INVALID_CREDENTIALS',
                'message' => 'Invalid username or password.',
            ];
        }

        // On successful login, reset lockout counters and update login timestamp.
        $this->users->updateLoginState(
            (int) $user['id'],
            0,
            null,
            date('Y-m-d H:i:s'),
            $_SERVER['REMOTE_ADDR'] ?? ''
        );

        // If row was legacy-hashed, transparently upgrade to password_hash() now.
        if ($this->isLegacyHash($user['password_hash'] ?? '')) {
            $this->upgradePasswordHash((int) $user['id'], $password, (bool) ($user['force_password_change'] ?? 0));
        }

        $fresh = $this->users->findByUsername($username);
        $tokenBundle = $this->issueJwt($fresh);

        $this->audit->log('auth_login', $username, 'success', ((int) ($fresh['force_password_change'] ?? 0) === 1) ? 'force_password_change' : 'ok');

        return [
            'ok'                  => true,
            'action'              => 'auth_login',
            'token'               => $tokenBundle['token'],
            'expiresAt'           => $tokenBundle['expiresAt'],
            'sessionHours'        => Constants::AUTH_TOKEN_HOURS,
            'forcePasswordChange' => ((int) ($fresh['force_password_change'] ?? 0) === 1),
            'user'                => self::toPublicUser($fresh),
        ];
    }

    public function logout(array $data): array
    {
        $token = self::extractToken($data);
        if ($token === '') {
            return [
                'ok'      => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'Missing auth token.',
            ];
        }

        $verified = self::verifyToken($token);
        if (!$verified['ok']) {
            return $verified;
        }

        $payload = $verified['payload'];
        $exp = (int) ($payload['exp'] ?? (time() + (Constants::AUTH_TOKEN_HOURS * 3600)));
        $this->revokedTokens->revoke(
            hash('sha256', $token),
            date('Y-m-d H:i:s', $exp),
            (string) ($payload['sub'] ?? '')
        );

        $this->audit->log('auth_logout', (string) ($payload['sub'] ?? ''), 'success', 'token');

        return [
            'ok'      => true,
            'action'  => 'auth_logout',
            'message' => 'Logged out.',
        ];
    }

    public function me(array $data): array
    {
        $token = self::extractToken($data);
        $verified = self::verifyToken($token);
        if (!$verified['ok']) {
            return $verified;
        }

        $payload = $verified['payload'];
        $user = $this->users->findByUsername((string) ($payload['sub'] ?? ''));
        if (!$user) {
            return [
                'ok'      => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'User not found.',
            ];
        }

        return [
            'ok'           => true,
            'action'       => 'auth_me',
            'sessionHours' => Constants::AUTH_TOKEN_HOURS,
            'user'         => self::toPublicUser($user),
        ];
    }

    public function changePassword(array $data): array
    {
        $token = self::extractToken($data);
        $verified = self::verifyToken($token);
        if (!$verified['ok']) {
            return $verified;
        }

        $username = (string) ($verified['payload']['sub'] ?? '');
        $currentPassword = trim((string) ($data['currentPassword'] ?? $data['current_password'] ?? ''));
        $newPassword = trim((string) ($data['newPassword'] ?? $data['new_password'] ?? ''));

        if ($currentPassword === '' || $newPassword === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'currentPassword and newPassword are required.',
            ];
        }

        if (!Validator::minLength($newPassword, Constants::AUTH_PASSWORD_MIN_LENGTH)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'New password is too short.',
            ];
        }

        $user = $this->users->findByUsername($username);
        if (!$user) {
            return [
                'ok'      => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'User not found.',
            ];
        }

        if (!$this->verifyPassword($currentPassword, $user)) {
            $this->audit->log('auth_change_password', $username, 'failed', 'current_password_mismatch');
            return [
                'ok'      => false,
                'error'   => 'INVALID_CREDENTIALS',
                'message' => 'Current password is incorrect.',
            ];
        }

        $this->upgradePasswordHash((int) $user['id'], $newPassword, false, $username);
        $this->audit->log('auth_change_password', $username, 'success', 'password_changed');

        return [
            'ok'      => true,
            'action'  => 'auth_change_password',
            'message' => 'Password updated successfully.',
        ];
    }

    public function listUsers(array $data): array
    {
        $token = self::extractToken($data);
        $verified = self::verifyToken($token);
        if (!$verified['ok']) {
            return $verified;
        }

        $requester = $this->users->findByUsername((string) ($verified['payload']['sub'] ?? ''));
        if (!$requester || ($requester['role'] ?? '') !== 'superadmin') {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Superadmin access required.',
            ];
        }

        $rows = $this->users->listAll();
        $users = array_map(static fn(array $row) => self::toPublicUser($row), $rows);

        return [
            'ok'    => true,
            'users' => $users,
        ];
    }

    public function createUser(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $username = self::normalizeUsername((string) ($data['username'] ?? $data['mobile'] ?? ''));
        $displayName = trim((string) ($data['displayName'] ?? $data['display_name'] ?? ''));
        $role = strtolower(trim((string) ($data['role'] ?? Constants::DEFAULT_ROLE)));
        $password = trim((string) ($data['password'] ?? ''));

        if ($displayName === '') {
            $displayName = $username;
        }

        if ($username === '' || $password === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'username and password are required.',
            ];
        }

        if (!Validator::inArray($role, Constants::ROLES)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'Invalid role.',
            ];
        }

        if ($this->users->findByUsername($username)) {
            return [
                'ok'      => false,
                'error'   => 'ALREADY_EXISTS',
                'message' => 'User already exists.',
            ];
        }

        [$legacyHash, $salt] = self::buildLegacyPasswordHash($password);

        $permissions = $data['permissions'] ?? [];
        if (!is_array($permissions) || empty($permissions)) {
            $permissions = self::defaultPermissionsForRole($role);
        } else {
            $permissions = self::sanitizePermissions($permissions);
        }

        $this->users->create([
            'username'              => $username,
            'display_name'          => $displayName,
            'role'                  => $role,
            'password_hash'         => $legacyHash,
            'password_salt'         => $salt,
            'status'                => 'active',
            'force_password_change' => 1,
            'permissions'           => $permissions,
            'created_by'            => $auth['user']['username'],
        ]);

        $this->audit->log('auth_create_user', $auth['user']['username'], 'success', 'target=' . $username);

        return [
            'ok'      => true,
            'action'  => 'auth_create_user',
            'message' => 'User created.',
        ];
    }

    public function setUserStatus(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $username = self::normalizeUsername((string) ($data['username'] ?? $data['mobile'] ?? ''));
        $status = strtolower(trim((string) ($data['status'] ?? '')));

        if ($username === '' || !in_array($status, ['active', 'disabled'], true)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'username and valid status are required.',
            ];
        }

        $target = $this->users->findByUsername($username);
        if (!$target) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'User not found.',
            ];
        }

        $this->users->setStatus((int) $target['id'], $status, $auth['user']['username']);
        $this->audit->log('auth_set_user_status', $auth['user']['username'], 'success', "target={$username},status={$status}");

        return [
            'ok'      => true,
            'action'  => 'auth_set_user_status',
            'message' => 'User status updated.',
        ];
    }

    public function deleteUser(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $username = self::normalizeUsername((string) ($data['username'] ?? $data['mobile'] ?? ''));
        if ($username === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'username is required.',
            ];
        }

        $target = $this->users->findByUsername($username);
        if (!$target) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'User not found.',
            ];
        }

        if ($username === self::normalizeUsername((string) ($auth['user']['username'] ?? ''))) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'You cannot delete your own account.',
            ];
        }

        if ($username !== '' && $username === self::getProtectedBootstrapSuperadminUsername()) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'The bootstrap Super Admin account cannot be deleted.',
            ];
        }

        if (($target['role'] ?? '') === 'superadmin' && $this->countSuperadmins() <= 1) {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'The last superadmin account cannot be deleted.',
            ];
        }

        $this->users->deleteById((int) $target['id']);
        $this->audit->log('auth_delete_user', $auth['user']['username'], 'success', 'target=' . $username);

        return [
            'ok'      => true,
            'action'  => 'auth_delete_user',
            'message' => 'User deleted.',
        ];
    }

    public function resetPassword(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $username = self::normalizeUsername((string) ($data['username'] ?? $data['mobile'] ?? ''));
        $newPassword = trim((string) ($data['newPassword'] ?? $data['new_password'] ?? ''));

        if ($username === '' || $newPassword === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'username and newPassword are required.',
            ];
        }

        if (!Validator::minLength($newPassword, Constants::AUTH_PASSWORD_MIN_LENGTH)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'New password is too short.',
            ];
        }

        $target = $this->users->findByUsername($username);
        if (!$target) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'User not found.',
            ];
        }

        $this->upgradePasswordHash((int) $target['id'], $newPassword, true, $auth['user']['username']);
        $this->audit->log('auth_reset_password', $auth['user']['username'], 'success', 'target=' . $username);

        return [
            'ok'      => true,
            'action'  => 'auth_reset_password',
            'message' => 'Password reset successfully.',
        ];
    }

    public function setUserPermissions(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $username = self::normalizeUsername((string) ($data['username'] ?? $data['mobile'] ?? ''));
        $permissions = $data['permissions'] ?? null;

        if ($username === '' || !is_array($permissions)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'username and permissions are required.',
            ];
        }

        $target = $this->users->findByUsername($username);
        if (!$target) {
            return [
                'ok'      => false,
                'error'   => 'NOT_FOUND',
                'message' => 'User not found.',
            ];
        }

        $clean = self::sanitizePermissions($permissions);
        $this->users->setPermissions((int) $target['id'], $clean, $auth['user']['username']);

        $this->audit->log('auth_set_user_permissions', $auth['user']['username'], 'success', 'target=' . $username);

        return [
            'ok'      => true,
            'action'  => 'auth_set_user_permissions',
            'message' => 'Permissions updated.',
        ];
    }

    public function getApiSettings(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $settings = [];
        $storedSettings = $this->apiSettings->getValues(Constants::MANAGED_SETTING_KEYS);
        foreach (Constants::MANAGED_SETTING_KEYS as $key) {
            $raw = trim((string) ($storedSettings[$key] ?? ''));
            if ($raw === '') {
                $raw = (string) ($_ENV[$key] ?? '');
            }
            $settings[$key] = $this->maskSecret($raw);
        }

        return [
            'ok'       => true,
            'action'   => 'auth_get_api_settings',
            'settings' => $settings,
        ];
    }

    public function setApiSettings(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $updates = $data['settings'] ?? [];
        if (!is_array($updates) || empty($updates)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'settings object is required.',
            ];
        }

        $accepted = [];
        foreach ($updates as $key => $value) {
            if (in_array((string) $key, Constants::MANAGED_SETTING_KEYS, true)) {
                $accepted[(string) $key] = (string) $value;
            }
        }

        $this->apiSettings->upsertMany($accepted);

        $this->audit->log(
            'auth_set_api_settings',
            $auth['user']['username'],
            'success',
            'accepted_keys=' . implode(',', array_keys($accepted))
        );

        return [
            'ok'      => true,
            'action'  => 'auth_set_api_settings',
            'message' => 'API settings saved.',
            'acceptedKeys' => array_keys($accepted),
        ];
    }

    public function getAppSettings(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $settings = $this->readAppSettings();

        return [
            'ok'        => true,
            'action'    => 'auth_get_app_settings',
            'settings'  => $settings,
            'updatedAt' => $settings['updatedAt'] ?? '',
            'updatedBy' => $settings['updatedBy'] ?? '',
        ];
    }

    public function setAppSettings(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $updates = $data['settings'] ?? [];
        if (!is_array($updates) || empty($updates)) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'settings object is required.',
            ];
        }

        $settings = $this->readAppSettings();

        if (array_key_exists('hotelWhatsappNo', $updates)) {
            $settings['hotelWhatsappNo'] = preg_replace('/\D+/', '', (string) $updates['hotelWhatsappNo']);
        }

        if (array_key_exists('menuBlockerStaffCode', $updates)) {
            $settings['menuBlockerStaffCode'] = $this->normalizeMenuBlockerStaffCode($updates['menuBlockerStaffCode']);
        }

        if (array_key_exists('eventEntryPasscode', $updates)) {
            $settings['eventEntryPasscode'] = $this->normalizeEventEntryPasscode($updates['eventEntryPasscode']);
        }

        if (array_key_exists('menuBlockerPages', $updates)) {
            $settings['menuBlockerPages'] = $this->normalizeMenuBlockerPages($updates['menuBlockerPages']);
        }

        if (array_key_exists('scannerDestination', $updates)) {
            $destination = strtolower(trim((string) $updates['scannerDestination']));
            if (!in_array($destination, self::APP_SETTINGS_DESTINATIONS, true)) {
                return [
                    'ok'      => false,
                    'error'   => 'INVALID_INPUT',
                    'message' => 'scannerDestination must be one of: home, menu, cocktail.',
                ];
            }
            $settings['scannerDestination'] = $destination;
        }

        $settings['updatedAt'] = date('Y-m-d H:i:s');
        $settings['updatedBy'] = (string) ($auth['user']['username'] ?? 'system');

        if (!$this->writeAppSettings($settings)) {
            return [
                'ok'      => false,
                'error'   => 'WRITE_FAILED',
                'message' => 'Unable to persist app settings.',
            ];
        }

        $this->audit->log(
            'auth_set_app_settings',
            $auth['user']['username'],
            'success',
            'updated_keys=' . implode(',', array_keys($updates))
        );

        return [
            'ok'        => true,
            'action'    => 'auth_set_app_settings',
            'message'   => 'App settings saved.',
            'settings'  => $settings,
            'updatedAt' => $settings['updatedAt'],
            'updatedBy' => $settings['updatedBy'],
        ];
    }

    public function getQrRedirectSettings(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $this->ensureLegacyQrRedirectRecords((string) ($auth['user']['username'] ?? 'system'));

        return [
            'ok' => true,
            'action' => 'auth_get_qr_redirect_settings',
            'settings' => $this->buildQrRedirectSettingsResponse(),
        ];
    }

    public function setQrRedirectSettings(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $updates = $data['settings'] ?? [];
        if (!is_array($updates) || empty($updates)) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'settings object is required.',
            ];
        }

        $acceptedChannels = [];
        foreach (['customer', 'admin'] as $channel) {
            if (!array_key_exists($channel, $updates) || !is_array($updates[$channel])) {
                continue;
            }

            $sanitized = $this->sanitizeQrRedirectSetting($channel, $updates[$channel]);
            if (!$sanitized['ok']) {
                return $sanitized;
            }

            $payload = $sanitized['setting'];
            $payload['channel'] = $channel;
            $payload['updated_at'] = date('Y-m-d H:i:s');
            $payload['updated_by'] = (string) ($auth['user']['username'] ?? 'system');
            $this->qrRedirectSettings->upsert($payload);
            $this->syncLegacyQrRedirectRecord($channel, $payload, (string) ($auth['user']['username'] ?? 'system'));
            $acceptedChannels[] = $channel;
        }

        if (empty($acceptedChannels)) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'At least one channel settings object is required.',
            ];
        }

        $this->audit->log(
            'auth_set_qr_redirect_settings',
            $auth['user']['username'],
            'success',
            'updated_channels=' . implode(',', $acceptedChannels)
        );

        return [
            'ok' => true,
            'action' => 'auth_set_qr_redirect_settings',
            'message' => 'QR redirect settings saved.',
            'settings' => $this->buildQrRedirectSettingsResponse(),
        ];
    }

    public function listQrRedirects(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $this->ensureLegacyQrRedirectRecords((string) ($auth['user']['username'] ?? 'system'));

        return [
            'ok' => true,
            'action' => 'auth_list_qr_redirects',
            'items' => $this->buildQrRedirectListResponse(),
            'presetOptions' => $this->buildQrPresetOptions(),
        ];
    }

    public function saveQrRedirect(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $recordInput = $data['record'] ?? $data;
        if (!is_array($recordInput)) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'record object is required.',
            ];
        }

        $recordId = isset($recordInput['id']) ? (int) $recordInput['id'] : 0;
        $existing = $recordId > 0 ? $this->qrRedirects->findById($recordId) : null;
        if ($recordId > 0 && !$existing) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'QR redirect record not found.',
            ];
        }

        $sanitized = $this->sanitizeQrRedirectRecord($recordInput, $existing);
        if (!$sanitized['ok']) {
            return $sanitized;
        }

        $payload = $sanitized['record'];
        $username = (string) ($auth['user']['username'] ?? 'system');
        $payload['updated_by'] = $username;
        $payload['updated_at'] = date('Y-m-d H:i:s');

        if ($existing) {
            $this->qrRedirects->update($recordId, $payload);
            $savedId = $recordId;
        } else {
            $payload['created_by'] = $username;
            $payload['created_at'] = $payload['updated_at'];
            $payload['is_system'] = 0;
            $payload['legacy_channel'] = null;
            $savedId = $this->qrRedirects->create($payload);
        }

        $saved = $this->qrRedirects->findById($savedId);
        $this->audit->log(
            'auth_save_qr_redirect',
            $username,
            'success',
            'qr_id=' . $savedId . ',slug=' . (string) ($saved['slug'] ?? $payload['slug'])
        );

        return [
            'ok' => true,
            'action' => 'auth_save_qr_redirect',
            'message' => 'QR redirect saved.',
            'item' => $saved ? $this->formatQrRedirectRecord($saved) : null,
            'items' => $this->buildQrRedirectListResponse(),
            'presetOptions' => $this->buildQrPresetOptions(),
        ];
    }

    public function setQrRedirectActive(array $data): array
    {
        $auth = $this->requireSuperadmin($data);
        if (!$auth['ok']) {
            return $auth;
        }

        $recordId = (int) ($data['id'] ?? 0);
        if ($recordId <= 0) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'Valid QR redirect id is required.',
            ];
        }

        $existing = $this->qrRedirects->findById($recordId);
        if (!$existing) {
            return [
                'ok' => false,
                'error' => 'NOT_FOUND',
                'message' => 'QR redirect record not found.',
            ];
        }

        $isActive = filter_var($data['isActive'] ?? $data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $this->qrRedirects->setActive($recordId, $isActive !== false, (string) ($auth['user']['username'] ?? 'system'));

        $this->audit->log(
            'auth_set_qr_redirect_active',
            (string) ($auth['user']['username'] ?? 'system'),
            'success',
            'qr_id=' . $recordId . ',active=' . (($isActive !== false) ? '1' : '0')
        );

        return [
            'ok' => true,
            'action' => 'auth_set_qr_redirect_active',
            'message' => 'QR redirect status updated.',
            'items' => $this->buildQrRedirectListResponse(),
            'presetOptions' => $this->buildQrPresetOptions(),
        ];
    }

    private function defaultAppSettings(): array
    {
        return [
            'hotelWhatsappNo' => '918828004722',
            'menuBlockerStaffCode' => '2026',
            'eventEntryPasscode' => '2026',
            'menuBlockerPages' => [
                'home' => true,
                'menu' => false,
                'cocktail' => false,
            ],
            'scannerDestination' => 'menu',
            'updatedAt' => '',
            'updatedBy' => '',
        ];
    }

    private function normalizeMenuBlockerPages($value): array
    {
        $defaults = $this->defaultAppSettings()['menuBlockerPages'];
        $raw = is_array($value) ? $value : [];

        foreach (self::APP_SETTINGS_PAGE_KEYS as $key) {
            if (array_key_exists($key, $raw)) {
                $defaults[$key] = filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $defaults[$key] = $defaults[$key] === null ? false : $defaults[$key];
            }
        }

        return $defaults;
    }

    private function readAppSettings(): array
    {
        $defaults = $this->defaultAppSettings();
        $file = self::APP_SETTINGS_FILE;

        if (!is_file($file)) {
            return $defaults;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $settings = array_merge($defaults, $decoded);
        $settings['menuBlockerPages'] = $this->normalizeMenuBlockerPages($decoded['menuBlockerPages'] ?? []);

        $destination = strtolower(trim((string) ($settings['scannerDestination'] ?? 'menu')));
        $settings['scannerDestination'] = in_array($destination, self::APP_SETTINGS_DESTINATIONS, true)
            ? $destination
            : 'menu';

        $settings['hotelWhatsappNo'] = preg_replace('/\D+/', '', (string) ($settings['hotelWhatsappNo'] ?? ''));
        $settings['menuBlockerStaffCode'] = $this->normalizeMenuBlockerStaffCode($settings['menuBlockerStaffCode'] ?? '');
        $settings['eventEntryPasscode'] = $this->normalizeEventEntryPasscode($settings['eventEntryPasscode'] ?? ($settings['menuBlockerStaffCode'] ?? ''));

        return $settings;
    }

    private function normalizeMenuBlockerStaffCode(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function normalizeEventEntryPasscode(mixed $value): string
    {
        return strtoupper(trim((string) $value));
    }

    private function writeAppSettings(array $settings): bool
    {
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return false;
        }

        return @file_put_contents(self::APP_SETTINGS_FILE, $json . PHP_EOL, LOCK_EX) !== false;
    }

    private function buildQrRedirectSettingsResponse(): array
    {
        $this->ensureLegacyQrRedirectRecords();
        $dbRows = $this->qrRedirectSettings->findAllIndexed();
        $response = [];

        foreach (['customer', 'admin'] as $channel) {
            $registryRow = $this->qrRedirects->findByLegacyChannel($channel);
            $resolved = $registryRow
                ? $this->resolveQrRedirectRecord($registryRow)
                : $this->resolveQrRedirectSetting($channel, $dbRows[$channel] ?? null);
            $response[$channel] = [
                'channel' => $channel,
                'destinationMode' => $resolved['destinationMode'],
                'destinationKey' => $resolved['destinationKey'],
                'manualUrl' => $resolved['manualUrl'],
                'destinationLabel' => $resolved['destinationLabel'],
                'resolvedUrl' => $resolved['resolvedUrl'],
                'isActive' => $resolved['isActive'],
                'fallbackUsed' => $resolved['fallbackUsed'],
                'updatedAt' => $resolved['updatedAt'],
                'updatedBy' => $resolved['updatedBy'],
            ];
        }

        return $response;
    }

    private function buildQrRedirectListResponse(): array
    {
        return array_map(function (array $row): array {
            return $this->formatQrRedirectRecord($row);
        }, $this->qrRedirects->listAll());
    }

    private function sanitizeQrRedirectSetting(string $channel, array $input): array
    {
        $mode = strtolower(trim((string) ($input['destinationMode'] ?? $input['destination_mode'] ?? 'preset')));
        if (!in_array($mode, ['preset', 'manual'], true)) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'destinationMode must be preset or manual.',
            ];
        }

        $allowedPresets = $channel === 'admin'
            ? ['admin']
            : ['home', 'menu', 'cocktail'];

        if ($mode === 'manual') {
            $manualUrl = trim((string) ($input['manualUrl'] ?? $input['manual_url'] ?? ''));
            if (!$this->isValidHttpsUrl($manualUrl)) {
                return [
                    'ok' => false,
                    'error' => 'INVALID_INPUT',
                    'message' => 'manualUrl must be a valid absolute https:// URL.',
                ];
            }

            return [
                'ok' => true,
                'setting' => [
                    'destination_mode' => 'manual',
                    'destination_key' => 'manual',
                    'manual_url' => $manualUrl,
                    'is_active' => 1,
                ],
            ];
        }

        $key = strtolower(trim((string) ($input['destinationKey'] ?? $input['destination_key'] ?? '')));
        if (!in_array($key, $allowedPresets, true)) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'destinationKey is invalid for channel ' . $channel . '.',
            ];
        }

        return [
            'ok' => true,
            'setting' => [
                'destination_mode' => 'preset',
                'destination_key' => $key,
                'manual_url' => null,
                'is_active' => 1,
            ],
        ];
    }

    private function resolveQrRedirectSetting(string $channel, ?array $row): array
    {
        $fallbackKey = $channel === 'admin' ? 'admin' : 'menu';
        $mode = strtolower(trim((string) ($row['destination_mode'] ?? 'preset')));
        $key = strtolower(trim((string) ($row['destination_key'] ?? $fallbackKey)));
        $manualUrl = trim((string) ($row['manual_url'] ?? ''));
        $isActive = isset($row['is_active']) ? (bool) $row['is_active'] : true;
        $fallbackUsed = false;

        if (!$isActive) {
            $mode = 'preset';
            $key = $fallbackKey;
            $manualUrl = '';
            $fallbackUsed = true;
        }

        if ($mode === 'manual' && !$this->isValidHttpsUrl($manualUrl)) {
            $mode = 'preset';
            $key = $fallbackKey;
            $manualUrl = '';
            $fallbackUsed = true;
        }

        if ($mode !== 'manual') {
            $allowedPresets = $channel === 'admin' ? ['admin'] : ['home', 'menu', 'cocktail'];
            if (!in_array($key, $allowedPresets, true)) {
                $key = $fallbackKey;
                $fallbackUsed = true;
            }
        }

        $presetMap = [
            'home' => ['label' => 'Home Page', 'url' => SiteUrl::resolve('home')],
            'menu' => ['label' => 'Food Menu', 'url' => SiteUrl::resolve('menu')],
            'cocktail' => ['label' => 'Cocktail Menu', 'url' => SiteUrl::resolve('cocktail')],
            'admin' => ['label' => 'Admin Portal', 'url' => SiteUrl::resolve('admin')],
        ];

        if ($mode === 'manual') {
            return [
                'channel' => $channel,
                'destinationMode' => 'manual',
                'destinationKey' => 'manual',
                'manualUrl' => $manualUrl,
                'destinationLabel' => 'Manual URL',
                'resolvedUrl' => $manualUrl,
                'isActive' => $isActive,
                'fallbackUsed' => $fallbackUsed,
                'updatedAt' => (string) ($row['updated_at'] ?? ''),
                'updatedBy' => (string) ($row['updated_by'] ?? ''),
            ];
        }

        $preset = $presetMap[$key] ?? $presetMap[$fallbackKey];

        return [
            'channel' => $channel,
            'destinationMode' => 'preset',
            'destinationKey' => $key,
            'manualUrl' => '',
            'destinationLabel' => $preset['label'],
            'resolvedUrl' => $preset['url'],
            'isActive' => $isActive,
            'fallbackUsed' => $fallbackUsed,
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
            'updatedBy' => (string) ($row['updated_by'] ?? ''),
        ];
    }

    private function formatQrRedirectRecord(array $row): array
    {
        $resolved = $this->resolveQrRedirectRecord($row);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'publicUrl' => $this->buildQrPublicUrl((string) ($row['slug'] ?? '')),
            'redirectMode' => $resolved['destinationMode'],
            'presetKey' => $resolved['destinationKey'] !== 'manual' ? $resolved['destinationKey'] : '',
            'manualUrl' => $resolved['manualUrl'],
            'destinationLabel' => $resolved['destinationLabel'],
            'resolvedUrl' => $resolved['resolvedUrl'],
            'notes' => (string) ($row['notes'] ?? ''),
            'isActive' => (bool) ($row['is_active'] ?? false),
            'isSystem' => (bool) ($row['is_system'] ?? false),
            'legacyChannel' => (string) ($row['legacy_channel'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
            'updatedBy' => (string) ($row['updated_by'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'createdBy' => (string) ($row['created_by'] ?? ''),
            'fallbackUsed' => (bool) ($resolved['fallbackUsed'] ?? false),
        ];
    }

    private function resolveQrRedirectRecord(array $row): array
    {
        $presetMap = $this->getQrPresetMap();
        $fallbackKey = (($row['legacy_channel'] ?? '') === 'admin') ? 'admin' : 'menu';
        $mode = strtolower(trim((string) ($row['redirect_mode'] ?? 'preset')));
        $key = strtolower(trim((string) ($row['preset_key'] ?? $fallbackKey)));
        $manualUrl = trim((string) ($row['manual_url'] ?? ''));
        $isActive = isset($row['is_active']) ? (bool) $row['is_active'] : true;
        $fallbackUsed = false;

        if (!$isActive) {
            $mode = 'preset';
            $key = $fallbackKey;
            $manualUrl = '';
            $fallbackUsed = true;
        }

        if ($mode === 'manual' && $this->isValidHttpsUrl($manualUrl)) {
            return [
                'destinationMode' => 'manual',
                'destinationKey' => 'manual',
                'manualUrl' => $manualUrl,
                'destinationLabel' => 'Manual URL',
                'resolvedUrl' => $manualUrl,
                'fallbackUsed' => $fallbackUsed,
                'isActive' => $isActive,
                'updatedAt' => (string) ($row['updated_at'] ?? ''),
                'updatedBy' => (string) ($row['updated_by'] ?? ''),
            ];
        }

        if (!array_key_exists($key, $presetMap)) {
            $key = $fallbackKey;
            $fallbackUsed = true;
        }

        $preset = $presetMap[$key] ?? $presetMap[$fallbackKey];

        return [
            'destinationMode' => 'preset',
            'destinationKey' => $key,
            'manualUrl' => '',
            'destinationLabel' => $preset['label'],
            'resolvedUrl' => $preset['url'],
            'fallbackUsed' => $fallbackUsed || ($mode === 'manual' && $manualUrl !== ''),
            'isActive' => $isActive,
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
            'updatedBy' => (string) ($row['updated_by'] ?? ''),
        ];
    }

    private function ensureLegacyQrRedirectRecords(?string $updatedBy = null): void
    {
        $username = $updatedBy ?? 'system';
        foreach (['customer', 'admin'] as $channel) {
            if ($this->qrRedirects->findByLegacyChannel($channel)) {
                continue;
            }

            $legacyRow = $this->qrRedirectSettings->findByChannel($channel);
            $resolved = $this->resolveQrRedirectSetting($channel, $legacyRow);
            $this->qrRedirects->create([
                'name' => $channel === 'admin' ? 'Admin QR' : 'Guest QR',
                'slug' => $channel === 'admin' ? 'admin-portal' : 'guest-menu',
                'redirect_mode' => $resolved['destinationMode'] === 'manual' ? 'manual' : 'preset',
                'preset_key' => $resolved['destinationMode'] === 'manual' ? null : $resolved['destinationKey'],
                'manual_url' => $resolved['destinationMode'] === 'manual' ? $resolved['manualUrl'] : null,
                'legacy_channel' => $channel,
                'notes' => 'System QR record for legacy ' . $channel . ' page.',
                'is_active' => 1,
                'is_system' => 1,
                'created_by' => $username,
                'updated_by' => $username,
            ]);
        }
    }

    private function syncLegacyQrRedirectRecord(string $channel, array $payload, string $updatedBy): void
    {
        $existing = $this->qrRedirects->findByLegacyChannel($channel);
        $recordPayload = [
            'name' => $channel === 'admin' ? 'Admin QR' : 'Guest QR',
            'slug' => $existing['slug'] ?? ($channel === 'admin' ? 'admin-portal' : 'guest-menu'),
            'redirect_mode' => (string) ($payload['destination_mode'] ?? 'preset'),
            'preset_key' => (($payload['destination_mode'] ?? 'preset') === 'manual') ? null : ($payload['destination_key'] ?? 'menu'),
            'manual_url' => (($payload['destination_mode'] ?? 'preset') === 'manual') ? ($payload['manual_url'] ?? null) : null,
            'notes' => (string) ($existing['notes'] ?? ('System QR record for legacy ' . $channel . ' page.')),
            'is_active' => (int) ($payload['is_active'] ?? 1),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $updatedBy,
        ];

        if ($existing) {
            $this->qrRedirects->update((int) $existing['id'], $recordPayload);
            return;
        }

        $recordPayload['legacy_channel'] = $channel;
        $recordPayload['is_system'] = 1;
        $recordPayload['created_at'] = $recordPayload['updated_at'];
        $recordPayload['created_by'] = $updatedBy;
        $this->qrRedirects->create($recordPayload);
    }

    private function sanitizeQrRedirectRecord(array $input, ?array $existing = null): array
    {
        $name = trim((string) ($input['name'] ?? $existing['name'] ?? ''));
        if ($name === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'QR name is required.',
            ];
        }

        $slugInput = trim((string) ($input['slug'] ?? $existing['slug'] ?? ''));
        $slug = $this->slugifyQrRedirect($slugInput !== '' ? $slugInput : $name);
        if ($slug === '') {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'QR slug is required.',
            ];
        }

        $existingId = $existing ? (int) ($existing['id'] ?? 0) : null;
        if ($this->qrRedirects->slugExists($slug, $existingId)) {
            return [
                'ok' => false,
                'error' => 'DUPLICATE_SLUG',
                'message' => 'That QR slug already exists. Choose a different slug.',
            ];
        }

        $mode = strtolower(trim((string) ($input['redirectMode'] ?? $input['destinationMode'] ?? $input['redirect_mode'] ?? 'preset')));
        if (!in_array($mode, ['preset', 'manual'], true)) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'redirectMode must be preset or manual.',
            ];
        }

        $notes = trim((string) ($input['notes'] ?? $existing['notes'] ?? ''));
        $isActive = filter_var($input['isActive'] ?? $input['is_active'] ?? ($existing['is_active'] ?? 1), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($mode === 'manual') {
            $manualUrl = trim((string) ($input['manualUrl'] ?? $input['manual_url'] ?? ''));
            if (!$this->isValidHttpsUrl($manualUrl)) {
                return [
                    'ok' => false,
                    'error' => 'INVALID_INPUT',
                    'message' => 'manualUrl must be a valid absolute URL. Use https in production; localhost http is allowed in development.',
                ];
            }

            return [
                'ok' => true,
                'record' => [
                    'name' => $name,
                    'slug' => $slug,
                    'redirect_mode' => 'manual',
                    'preset_key' => null,
                    'manual_url' => $manualUrl,
                    'notes' => $notes,
                    'is_active' => $isActive === false ? 0 : 1,
                ],
            ];
        }

        $key = strtolower(trim((string) ($input['presetKey'] ?? $input['destinationKey'] ?? $input['preset_key'] ?? '')));
        if (!array_key_exists($key, $this->getQrPresetMap())) {
            return [
                'ok' => false,
                'error' => 'INVALID_INPUT',
                'message' => 'presetKey must be one of: ' . implode(', ', array_keys($this->getQrPresetMap())) . '.',
            ];
        }

        return [
            'ok' => true,
            'record' => [
                'name' => $name,
                'slug' => $slug,
                'redirect_mode' => 'preset',
                'preset_key' => $key,
                'manual_url' => null,
                'notes' => $notes,
                'is_active' => $isActive === false ? 0 : 1,
            ],
        ];
    }

    private function slugifyQrRedirect(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return substr($slug, 0, 160);
    }

    private function buildQrPublicUrl(string $slug): string
    {
        $homeUrl = rtrim(SiteUrl::resolve('home'), '/');
        if ($homeUrl === '') {
            return '/qr/' . rawurlencode($slug);
        }

        return $homeUrl . '/qr/' . rawurlencode($slug);
    }

    private function getQrPresetMap(): array
    {
        return array_merge([
            'home' => ['label' => 'Home Page', 'url' => SiteUrl::resolve('home')],
            'menu' => ['label' => 'Food Menu', 'url' => SiteUrl::resolve('menu')],
            'cocktail' => ['label' => 'Cocktail Menu', 'url' => SiteUrl::resolve('cocktail')],
            'admin' => ['label' => 'Admin Portal', 'url' => SiteUrl::resolve('admin')],
            'events' => ['label' => 'All Active Events', 'url' => $this->buildEventsListingUrl()],
        ], $this->buildActiveEventPresetMap());
    }

    private function buildQrPresetOptions(): array
    {
        $options = [];
        foreach ($this->getQrPresetMap() as $key => $preset) {
            $group = str_starts_with($key, 'event:') ? 'Active Event Pages' : 'Core Pages';
            $options[] = [
                'key' => $key,
                'label' => (string) ($preset['label'] ?? $key),
                'url' => (string) ($preset['url'] ?? ''),
                'group' => $group,
            ];
        }

        return $options;
    }

    private function buildActiveEventPresetMap(): array
    {
        $presets = [];
        foreach ($this->events->listAllActive() as $row) {
            if (!$this->isEventAvailableForQrPreset($row)) {
                continue;
            }

            $eventId = trim((string) ($row['event_id'] ?? ''));
            if ($eventId === '') {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $presets['event:' . $eventId] = [
                'label' => $title !== '' ? ('Event: ' . $title) : ('Event: ' . $eventId),
                'url' => $this->buildEventDetailUrl($eventId),
            ];
        }

        return $presets;
    }

    private function isEventAvailableForQrPreset(array $row): bool
    {
        if ((int) ($row['is_active'] ?? 0) !== 1) {
            return false;
        }

        $endDate = trim((string) ($row['end_date'] ?? ''));
        $endTime = trim((string) ($row['end_time'] ?? ''));
        $startDate = trim((string) ($row['start_date'] ?? ''));
        $startTime = trim((string) ($row['start_time'] ?? ''));
        $cutoffDate = $endDate !== '' ? $endDate : $startDate;
        $cutoffTime = $endDate !== '' ? ($endTime !== '' ? $endTime : '23:59:59') : ($startTime !== '' ? $startTime : '23:59:59');

        if ($cutoffDate === '') {
            return true;
        }

        try {
            $cutoff = new \DateTimeImmutable($cutoffDate . ' ' . $cutoffTime, new \DateTimeZone('Asia/Kolkata'));
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
            return $cutoff >= $now;
        } catch (\Throwable) {
            return true;
        }
    }

    private function buildEventsListingUrl(): string
    {
        return rtrim(SiteUrl::resolve('home'), '/') . '/events/';
    }

    private function buildEventDetailUrl(string $eventId): string
    {
        return rtrim(SiteUrl::resolve('home'), '/') . '/events/event.html?id=' . rawurlencode($eventId);
    }

    private function isValidHttpsUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

                if (!is_array($parts)) {
                    return false;
                }

                $scheme = strtolower((string) ($parts['scheme'] ?? ''));
                $host = strtolower(trim((string) ($parts['host'] ?? '')));
                if ($host === '') {
                    return false;
                }

                if ($scheme === 'https') {
                    return true;
                }

                return $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($validated === false) {
            return false;
        }

        $parts = parse_url($url);
        return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https' && trim((string) ($parts['host'] ?? '')) !== '';
    }

    public static function verifyToken(string $token): array
    {
        $raw = trim($token);
        if ($raw === '') {
            return [
                'ok'      => false,
                'error'   => 'UNAUTHORIZED',
                'message' => 'Missing auth token.',
            ];
        }

        $jwtSecret = (string) ($_ENV['JWT_SECRET'] ?? '');
        if ($jwtSecret === '') {
            return [
                'ok'      => false,
                'error'   => 'SERVER_MISCONFIG',
                'message' => 'JWT secret is not configured.',
            ];
        }

        try {
            $payload = self::decodeJwtHs256($raw, $jwtSecret);

            $username = self::normalizeUsername((string) ($payload['sub'] ?? ''));
            if ($username === '') {
                return [
                    'ok'      => false,
                    'error'   => 'INVALID_TOKEN',
                    'message' => 'Token subject is missing.',
                ];
            }

            $tokenHash = hash('sha256', $raw);
            $revoked = (new RevokedTokenRepository())->isRevokedAndActive($tokenHash);
            if ($revoked) {
                return [
                    'ok'      => false,
                    'error'   => 'TOKEN_REVOKED',
                    'message' => 'Session expired. Please login again.',
                ];
            }

            return [
                'ok'      => true,
                'payload' => $payload,
            ];
        } catch (\Throwable $e) {
            return [
                'ok'      => false,
                'error'   => 'INVALID_TOKEN',
                'message' => 'Invalid or expired token.',
            ];
        }
    }

    /**
     * Legacy hash compatible with Apps Script:
     * value = "salt:password" then SHA-256 hex repeated 1200 times.
     */
    public static function hashPasswordLegacy(string $password, string $salt): string
    {
        $value = trim($salt) . ':' . $password;
        for ($i = 0; $i < Constants::AUTH_LEGACY_HASH_ITERATIONS; $i++) {
            $value = hash('sha256', $value);
        }
        return $value;
    }

    public static function buildLegacyPasswordHash(string $password): array
    {
        $salt = bin2hex(random_bytes(16));
        return [self::hashPasswordLegacy($password, $salt), $salt];
    }

    private function verifyPassword(string $rawPassword, array $user): bool
    {
        $storedHash = (string) ($user['password_hash'] ?? '');
        $salt = (string) ($user['password_salt'] ?? '');

        if ($storedHash === '') {
            return false;
        }

        if ($this->isLegacyHash($storedHash)) {
            $candidate = self::hashPasswordLegacy($rawPassword, $salt);
            return hash_equals($storedHash, $candidate);
        }

        return password_verify($rawPassword, $storedHash);
    }

    private function isLegacyHash(string $hash): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', strtolower(trim($hash)));
    }

    private function upgradePasswordHash(int $userId, string $rawPassword, bool $forcePasswordChange, ?string $updatedBy = null): void
    {
        $bcryptHash = password_hash($rawPassword, PASSWORD_BCRYPT);
        $this->users->updatePassword($userId, $bcryptHash, '', $forcePasswordChange, $updatedBy);
    }

    private function isAccountLocked(array $user): bool
    {
        $lockoutUntil = (string) ($user['lockout_until'] ?? '');
        if ($lockoutUntil === '') {
            return false;
        }
        $lockoutTs = strtotime($lockoutUntil);
        return $lockoutTs !== false && $lockoutTs > time();
    }

    private function registerFailedLoginAttempt(array $user): void
    {
        $attempts = (int) ($user['failed_attempts'] ?? 0) + 1;
        $lockoutUntil = null;

        if ($attempts >= Constants::AUTH_LOCKOUT_MAX_ATTEMPTS) {
            $attempts = Constants::AUTH_LOCKOUT_MAX_ATTEMPTS;
            $lockoutUntil = date('Y-m-d H:i:s', time() + (Constants::AUTH_LOCKOUT_MINUTES * 60));
        }

        $this->users->updateLoginState(
            (int) $user['id'],
            $attempts,
            $lockoutUntil,
            $user['last_login_at'] ?? null,
            $user['last_login_ip'] ?? null
        );
    }

    private function issueJwt(array $user): array
    {
        $now = time();
        $exp = $now + (Constants::AUTH_TOKEN_HOURS * 3600);

        $payload = [
            'sub'  => self::normalizeUsername((string) ($user['username'] ?? '')),
            'role' => strtolower((string) ($user['role'] ?? Constants::DEFAULT_ROLE)),
            'name' => trim((string) ($user['display_name'] ?? '')),
            'iat'  => $now,
            'exp'  => $exp,
        ];

        $jwtSecret = (string) ($_ENV['JWT_SECRET'] ?? '');
        $token = self::encodeJwtHs256($payload, $jwtSecret);

        return [
            'token'     => $token,
            'expiresAt' => gmdate('c', $exp),
        ];
    }

    public static function toPublicUser(array $row): array
    {
        $permissions = $row['permissions'] ?? [];
        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        return [
            'id'                  => (int) ($row['id'] ?? 0),
            'username'            => self::normalizeUsername((string) ($row['username'] ?? '')),
            'displayName'         => (string) ($row['display_name'] ?? ''),
            'role'                => strtolower((string) ($row['role'] ?? Constants::DEFAULT_ROLE)),
            'status'              => strtolower((string) ($row['status'] ?? 'active')),
            'forcePasswordChange' => ((int) ($row['force_password_change'] ?? 0) === 1),
            'permissions'         => self::sanitizePermissions(is_array($permissions) ? $permissions : []),
            'lastLoginAt'         => $row['last_login_at'] ?? null,
            'updatedAt'           => $row['updated_at'] ?? null,
        ];
    }

    private static function sanitizePermissions(array $permissions): array
    {
        $clean = [];
        $selected = [];

        foreach ($permissions as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $selected[$value] = true;
                continue;
            }

            if (is_string($key) && !empty($value)) {
                $selected[$key] = true;
            }
        }

        foreach (Constants::ADMIN_PERMISSION_KEYS as $key) {
            $clean[$key] = !empty($selected[$key]);
        }
        return $clean;
    }

    private static function defaultPermissionsForRole(string $role): array
    {
        if ($role === 'superadmin') {
            $all = [];
            foreach (Constants::ADMIN_PERMISSION_KEYS as $key) {
                $all[$key] = true;
            }
            return $all;
        }

        // Conservative admin default; can be edited later in user management.
        return [
            'dashboard'       => true,
            'cashier'         => true,
            'verification'    => true,
            'eventGuests'     => true,
            'eventScanner'    => true,
            'eventManagement' => true,
            'menuEditor'      => true,
            'cashApprovals'   => false,
            'userManagement'  => false,
        ];
    }

    private function requireSuperadmin(array $data): array
    {
        $token = self::extractToken($data);
        $verified = self::verifyToken($token);
        if (!$verified['ok']) {
            return $verified;
        }

        $user = $this->users->findByUsername((string) ($verified['payload']['sub'] ?? ''));
        if (!$user || ($user['role'] ?? '') !== 'superadmin') {
            return [
                'ok'      => false,
                'error'   => 'FORBIDDEN',
                'message' => 'Superadmin access required.',
            ];
        }

        return ['ok' => true, 'user' => self::toPublicUser($user)];
    }

    private function countSuperadmins(): int
    {
        $count = 0;
        foreach ($this->users->listAll() as $user) {
            if (($user['role'] ?? '') === 'superadmin') {
                $count += 1;
            }
        }

        return $count;
    }

    private function maskSecret(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($value, 0, 2) . str_repeat('*', $len - 4) . substr($value, -2);
    }

    private static function encodeJwtHs256(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerB64 = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadB64 = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signingInput = $headerB64 . '.' . $payloadB64;
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $signatureB64 = self::base64UrlEncode($signature);
        return $signingInput . '.' . $signatureB64;
    }

    private static function decodeJwtHs256(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \RuntimeException('Malformed token');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $header = json_decode(self::base64UrlDecode($headerB64), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            throw new \RuntimeException('Invalid token header');
        }

        $signingInput = $headerB64 . '.' . $payloadB64;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));
        if (!hash_equals($expected, $signatureB64)) {
            throw new \RuntimeException('Invalid token signature');
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Invalid token payload');
        }

        $exp = (int) ($payload['exp'] ?? 0);
        if ($exp > 0 && $exp <= time()) {
            throw new \RuntimeException('Token expired');
        }

        return $payload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $data = strtr($value, '-_', '+/');
        $pad = strlen($data) % 4;
        if ($pad > 0) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return (string) base64_decode($data);
    }
}
