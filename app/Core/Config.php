<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Nạp toàn bộ config/*.php, truy cập bằng dot-notation: config('payment.gateways.sepay.env').
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function __construct(?string $dir = null)
    {
        if ($dir !== null) {
            foreach (glob(rtrim($dir, '/\\') . '/*.php') ?: [] as $file) {
                $this->items[basename($file, '.php')] = require $file;
            }
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $ref = &$this->items;
        foreach (explode('.', $key) as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->items;
    }
}
