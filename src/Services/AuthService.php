<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Repositories\AuthAuditRepository;
use NK\Repositories\RevokedTokenRepository;
use NK\Repositories\UserRepository;
use NK\Support\Validator;

class AuthService
{
    private UserRepository $users;
    private AuthAuditRepository $audit;
    private RevokedTokenRepository $revokedTokens;

    public function __construct()
    {
        $this->users = new UserRepository();
        $this->audit = new AuthAuditRepository();
        $this->revokedTokens = new RevokedTokenRepository();
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

        if ($username === '' || $password === '' || $displayName === '') {
            return [
                'ok'      => false,
                'error'   => 'INVALID_INPUT',
                'message' => 'username, displayName and password are required.',
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
        foreach (Constants::MANAGED_SETTING_KEYS as $key) {
            $raw = (string) ($_ENV[$key] ?? '');
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

        // In shared hosting we cannot reliably write .env from web process.
        // For now we only acknowledge allowed keys and log requested updates.
        $accepted = [];
        foreach ($updates as $key => $value) {
            if (in_array((string) $key, Constants::MANAGED_SETTING_KEYS, true)) {
                $accepted[(string) $key] = (string) $value;
            }
        }

        $this->audit->log(
            'auth_set_api_settings',
            $auth['user']['username'],
            'success',
            'accepted_keys=' . implode(',', array_keys($accepted))
        );

        return [
            'ok'      => true,
            'action'  => 'auth_set_api_settings',
            'message' => 'Settings request accepted. Update environment values on server.',
            'acceptedKeys' => array_keys($accepted),
        ];
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
        foreach (Constants::ADMIN_PERMISSION_KEYS as $key) {
            $clean[$key] = !empty($permissions[$key]);
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
