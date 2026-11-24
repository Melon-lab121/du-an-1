<?php

declare(strict_types=1);

namespace App\Core;

final class Route
{
    /** @var string[] */
    public array $middleware = [];
    public ?string $name = null;
    private ?string $regex = null;

    /**
     * @param string[] $methods
     * @param mixed $handler Closure | [Controller::class, 'method'] | 'Controller@method'
     */
    public function __construct(
        public readonly array $methods,
        public readonly string $uri,
        public readonly mixed $handler,
        private string $namePrefix = '',
    ) {
    }

    public function name(string $name): self
    {
        $this->name = $this->namePrefix . $name;
        return $this;
    }

    /** @param string|string[] $middleware */
    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    public function allowsMethod(string $method): bool
    {
        return in_array($method, $this->methods, true) || ($method === 'HEAD' && in_array('GET', $this->methods, true));
    }

    /** @return array<string, string>|null tham số route nếu khớp */
    public function match(string $path): ?array
    {
        if (!preg_match($this->regex(), $path, $m)) {
            return null;
        }
        return array_map('strval', array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));
    }

    private function regex(): string
    {
        if ($this->regex !== null) {
            return $this->regex;
        }

        // {slug} hoặc {id:\d+}
        $parts = preg_split('#(\{\w+(?::[^}]+)?\})#', $this->uri, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $pattern = '';
        foreach ($parts as $part) {
            if (preg_match('#^\{(\w+)(?::([^}]+))?\}$#', $part, $m)) {
                $pattern .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')';
            } else {
                $pattern .= preg_quote($part, '#');
            }
        }

        return $this->regex = '#^' . $pattern . '$#u';
    }

    /** @param array<string, scalar> $params */
    public function url(array $params = []): string
    {
        $path = preg_replace_callback('#\{(\w+)(?::[^}]+)?\}#', function (array $m) use (&$params): string {
            if (!array_key_exists($m[1], $params)) {
                throw new \InvalidArgumentException("Thiếu tham số [{$m[1]}] cho route [{$this->name}].");
            }
            $value = rawurlencode((string) $params[$m[1]]);
            unset($params[$m[1]]);
            return $value;
        }, $this->uri);

        return $params === [] ? (string) $path : $path . '?' . http_build_query($params);
    }
}
