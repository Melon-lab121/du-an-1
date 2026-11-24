<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private const DEFAULT_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /** @param array<string, string> $headers */
    public function __construct(
        protected string $content = '',
        protected int $status = 200,
        protected array $headers = [],
    ) {
    }

    public static function html(string $content, int $status = 200): static
    {
        return new static($content, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): static
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return new static($body, $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    public static function redirect(string $url, int $status = 302): static
    {
        return new static('', $status, ['Location' => $url]);
    }

    public function withHeader(string $name, string $value): static
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withStatus(int $status): static
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach (array_merge(self::DEFAULT_HEADERS, $this->headers) as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $this->content;
    }
}
