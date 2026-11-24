<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Đọc file .env (KEY=VALUE). Biến môi trường thật của hệ thống luôn được ưu tiên.
 */
final class Env
{
    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key === '' || getenv($key) !== false) {
                continue;
            }

            $len = strlen($value);
            $quoted = $len >= 2
                && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"));

            if ($quoted) {
                $value = substr($value, 1, -1);
            } elseif (($pos = strpos($value, ' #')) !== false) {
                $value = rtrim(substr($value, 0, $pos)); // bỏ comment cuối dòng
            }

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
