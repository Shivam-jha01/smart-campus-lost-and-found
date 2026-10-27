<?php
/**
 * Authentication and Session Management Service
 * Provides token creation, verification, session tracking, and auth guards.
 * Uses environment secrets, constant-time comparisons, and secure cookie attributes.
 */
class Auth {
    private static function getSecret(): string {
        return getenv('JWT_SECRET')
            ?: getenv('APP_SECRET')
            ?: 'cucek_lost_found_jwt_secret_key_2026';
    }

    public static function createToken(array $user): string {
        $payload = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => strtolower($user['email']),
            'uid' => $user['uid'] ?? '',
            'dept' => $user['dept'] ?? '',
            'role' => $user['role'] ?? 'student',
            'isAdmin' => !empty($user['isAdmin']),
            'iat' => time(),
            'exp' => time() + (86400 * 30) // 30-day expiration
        ];
        $encoded = base64_encode(json_encode($payload));
        $sig = hash_hmac('sha256', $encoded, self::getSecret());
        return $encoded . '.' . $sig;
    }

    public static function verifyToken(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$encoded, $sig] = $parts;
        $expected = hash_hmac('sha256', $encoded, self::getSecret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $data = json_decode(base64_decode($encoded), true);
        if (!is_array($data) || empty($data['exp']) || $data['exp'] < time()) {
            return null;
        }
        return $data;
    }

    public static function getUser(Request $request): ?array {
        // 1. Check Bearer Token header (preferred for stateless serverless requests)
        $bearer = $request->getBearerToken();
        if ($bearer) {
            $user = self::verifyToken($bearer);
            if ($user) {
                return $user;
            }
        }

        // 2. Check secure PHP Session fallback
        self::initSession();

        if (!empty($_SESSION['admin'])) {
            return array_merge($_SESSION['admin'], ['isAdmin' => true]);
        }
        if (!empty($_SESSION['user'])) {
            return array_merge($_SESSION['user'], ['isAdmin' => false]);
        }

        return null;
    }

    public static function requireUser(Request $request): array {
        $user = self::getUser($request);
        if (!$user) {
            Response::fail('Unauthorized. Please log in.', 401);
        }
        return $user;
    }

    public static function requireAdmin(Request $request): array {
        $user = self::requireUser($request);
        if (empty($user['isAdmin'])) {
            Response::fail('Access denied. Admin privileges required.', 403);
        }
        return $user;
    }

    public static function setSession(array $user, bool $isAdmin = false): void {
        self::initSession();
        // Regenerate session ID upon privilege escalation or login to prevent session fixation
        if (session_id()) {
            @session_regenerate_id(true);
        }
        if ($isAdmin) {
            $_SESSION['admin'] = $user;
            unset($_SESSION['user']);
        } else {
            $_SESSION['user'] = $user;
            unset($_SESSION['admin']);
        }
    }

    public static function destroySession(): void {
        self::initSession();
        $_SESSION = [];
        if (session_id()) {
            session_destroy();
        }
    }

    private static function initSession(): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_set_cookie_params([
                'lifetime' => 86400 * 30,
                'path'     => '/',
                'domain'   => '',
                'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            @session_start();
        }
    }
}
