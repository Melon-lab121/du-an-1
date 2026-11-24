<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session + flash data. Ở môi trường CLI (test), dữ liệu lưu trong mảng nội bộ.
 */
final class Session
{
    /** @var array<string, mixed> */
    private array $data = [];
    private bool $started = false;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function start(bool $secure = false): void
    {
        if ($this->started) {
            return;
        }

        if (PHP_SAPI === 'cli') {
            $this->data = [];
        } else {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_name((string) ($this->config['name'] ?? 'app_session'));
                session_set_cookie_params([
                    'lifetime' => (int) ($this->config['lifetime'] ?? 0) * 60,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax', // Lax để cookie vẫn gửi khi SePay redirect về (GET top-level)
                ]);
                $lifetimeSec = max(1440, (int) ($this->config['lifetime'] ?? 120) * 60);
                ini_set('session.gc_maxlifetime', (string) $lifetimeSec);
                ini_set('session.use_strict_mode', '1');
                session_start();
            }
            $this->data = &$_SESSION;
        }

        $this->started = true;
        // flash: dữ liệu của request trước được chuyển sang "old" và sẽ mất ở request kế tiếp
        $this->data['_flash_old'] = $this->data['_flash_new'] ?? [];
        $this->data['_flash_new'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    /** Lấy và xoá. */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->data['_flash_new'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $this->data['_flash_old'][$key] ?? $this->data['_flash_new'][$key] ?? $default;
    }

    public function regenerate(): void
    {
        if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        unset($this->data['_csrf_token']);
    }

    public function invalidate(): void
    {
        foreach (array_keys($this->data) as $key) {
            unset($this->data[$key]);
        }
        $this->data['_flash_old'] = [];
        $this->data['_flash_new'] = [];
        $this->regenerate();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }
}
