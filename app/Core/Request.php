<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string, string> */
    private array $routeParams = [];
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $server
     * @param array<string, mixed> $files
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query = [],
        private array $post = [],
        private array $server = [],
        private array $files = [],
        private array $cookies = [],
        private string $basePath = '',
    ) {
        $this->method = strtoupper($method);
        $this->path = self::normalizePath($path);
    }

    /** Tạo Request từ biến toàn cục của PHP. */
    public static function capture(): self
    {
        $server = $_SERVER;
        $uri = rawurldecode((string) (parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
        [$basePath, $path] = self::splitBasePath($uri, (string) ($server['SCRIPT_NAME'] ?? ''));

        $post = $_POST;
        $contentType = (string) ($server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? '');
        if (str_contains($contentType, 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($json)) {
                $post = array_merge($post, $json);
            }
        }

        return new self(
            (string) ($server['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $post,
            $server,
            $_FILES,
            $_COOKIE,
            $basePath,
        );
    }

    /**
     * Tách base path khi app chạy trong thư mục con (http://localhost/base/...).
     * Hỗ trợ cả 3 kiểu: rewrite từ thư mục gốc vào public/, truy cập thẳng /public, và `php -S -t public`.
     *
     * @return array{0: string, 1: string} [basePath, path]
     */
    public static function splitBasePath(string $uri, string $scriptName): array
    {
        $base = str_replace('\\', '/', dirname($scriptName));
        if ($base === '/' || $base === '.') {
            $base = '';
        }

        $inside = static fn (string $b): bool => $b === '' || $uri === $b || str_starts_with($uri, $b . '/');

        if (!$inside($base) && str_ends_with($base, '/public')) {
            $base = substr($base, 0, -strlen('/public'));
        }
        if (!$inside($base)) {
            $base = '';
        }

        $path = substr($uri, strlen($base));
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }

        return [$base, self::normalizePath($path)];
    }

    /**
     * Tạo request giả lập (dùng cho test).
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $server
     */
    public static function create(string $method, string $uri, array $params = [], array $server = []): self
    {
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);
        $isGet = strtoupper($method) === 'GET';

        return new self(
            $method,
            $path,
            $isGet ? array_merge($query, $params) : $query,
            $isGet ? [] : $params,
            array_merge(['REQUEST_METHOD' => strtoupper($method), 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'localhost'], $server),
        );
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    /** Hỗ trợ form gửi _method=PUT/PATCH/DELETE. */
    public function method(): string
    {
        if ($this->method === 'POST') {
            $override = strtoupper((string) ($this->post['_method'] ?? $this->header('X-HTTP-Method-Override') ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }
        return $this->method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->query : ($this->query[$key] ?? $default);
    }

    /** Lấy dữ liệu từ body (POST/JSON), rơi về query string nếu không có. */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->query);
    }

    /** @return array<string, mixed>|null file upload hợp lệ (đã chọn file) */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $this->server[$key] ?? null;
        if ($value === null && in_array(strtoupper($name), ['CONTENT-TYPE', 'CONTENT-LENGTH'], true)) {
            $value = $this->server[strtoupper(str_replace('-', '_', $name))] ?? null;
        }
        return $value === null ? null : (string) $value;
    }

    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
        $ip = $ip === '::1' ? '127.0.0.1' : $ip;

        // Chạy sau Cloudflare Tunnel: mọi request đến từ cloudflared trên chính máy này (127.0.0.1).
        // Chỉ khi bật TRUST_PROXY và request đến từ loopback mới tin header IP thật của Cloudflare,
        // tránh việc client tự gửi header giả mạo IP.
        if ($ip === '127.0.0.1' && Env::get('TRUST_PROXY', false) === true) {
            $forwarded = trim((string) ($this->server['HTTP_CF_CONNECTING_IP'] ?? ''));
            if (filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
                return $forwarded;
            }
        }

        return $ip;
    }

    public function isSecure(): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        return ($https !== '' && $https !== 'off')
            || strtolower((string) ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public function host(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? 'localhost');
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax()
            || str_contains((string) $this->header('Accept'), 'application/json')
            || str_starts_with($this->path, '/api/') || $this->path === '/api';
    }

    public function referer(): ?string
    {
        $ref = $this->server['HTTP_REFERER'] ?? null;
        return is_string($ref) && $ref !== '' ? $ref : null;
    }

    /** @param array<string, string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
