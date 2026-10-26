<?php
/**
 * Application Autoloader
 * Automatically loads classes from core, models, services, and controllers.
 */
class Autoloader {
    private static array $directories = [
        __DIR__,
        __DIR__ . '/../models',
        __DIR__ . '/../services',
        __DIR__ . '/../controllers'
    ];

    public static function register(): void {
        spl_autoload_register([self::class, 'load']);
    }

    public static function load(string $class): bool {
        // Strip namespace if present (e.g., App\Models\User -> User)
        $parts = explode('\\', $class);
        $className = end($parts);

        foreach (self::$directories as $dir) {
            $file = $dir . '/' . $className . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
        return false;
    }
}
