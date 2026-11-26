<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 autoloader tối giản (không cần Composer).
 * Nếu sau này cài Composer, vendor/autoload.php sẽ được nạp thêm trong bootstrap.
 */
final class Autoloader
{
    /** @var array<string, string> */
    private static array $prefixes = [];
    private static bool $registered = false;

    /** @param array<string, string> $prefixes namespace prefix => thư mục */
    public static function register(array $prefixes): void
    {
        foreach ($prefixes as $prefix => $dir) {
            self::addNamespace($prefix, $dir);
        }
        if (!self::$registered) {
            spl_autoload_register([self::class, 'load']);
            self::$registered = true;
        }
    }

    public static function addNamespace(string $prefix, string $dir): void
    {
        self::$prefixes[trim($prefix, '\\') . '\\'] = rtrim($dir, '/\\') . '/';
    }

    public static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $file = $dir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($file)) {
                    require $file;
                    return;
                }
            }
        }
    }
}
