<?php
/**
 * Authentication and Session Management Service
 * Provides token creation, verification, session tracking, and auth guards.
 */
class Auth {
    private const SECRET = 'cucek_lost_found_jwt_secret_key_2026';

    public static function createToken(array $user): string {
        $payload = [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'uid' => $user['uid'] ?? '',
            'dept' => $user['dept'] ?? '',
            'role' => $user['role'] ?? 'student',
            'isAdmin' => !empty($user['isAdmin']),
            'exp' => time() + (86400 * 30) // 30-day expiration
        ];
        $encoded = base64_encode(json_encode($payload));
        $sig = hash_hmac('sha256', $encoded, self::SECRET);
        return $encoded . '.' . $sig;
    }

    public static function verifyToken(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$encoded, $sig] = $parts;
        $expected = hash_hmac('sha256', $encoded, self::SECRET);
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
        // 1. Check Bearer Token header
        $bearer = $request->getBearerToken();
        if ($bearer) {
            $user = self::verifyToken($bearer);
            if ($user) {
                return $user;
            }
        }

        // 2. Check PHP Session fallback
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
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
            Response::fail('Unauthorized. Please login.', 401);
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
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if ($isAdmin) {
            $_SESSION['admin'] = $user;
        } else {
            $_SESSION['user'] = $user;
        }
    }

    public static function destroySession(): void {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $_SESSION = [];
        if (session_id()) {
            session_destroy();
        }
    }
}
