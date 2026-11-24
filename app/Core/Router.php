<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use JsonSerializable;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use RuntimeException;

final class Router
{
    /** @var Route[] */
    private array $routes = [];
    /** @var array<int, array{prefix: string, middleware: string[], as: string}> */
    private array $groupStack = [];
    /** @var array<string, class-string<MiddlewareInterface>> */
    private array $aliases = [];
    /** @var string[] */
    private array $globalMiddleware = [];

    public function __construct(private Container $container)
    {
    }

    public function get(string $uri, mixed $handler): Route
    {
        return $this->addRoute(['GET'], $uri, $handler);
    }

    public function post(string $uri, mixed $handler): Route
    {
        return $this->addRoute(['POST'], $uri, $handler);
    }

    public function put(string $uri, mixed $handler): Route
    {
        return $this->addRoute(['PUT'], $uri, $handler);
    }

    public function patch(string $uri, mixed $handler): Route
    {
        return $this->addRoute(['PATCH'], $uri, $handler);
    }

    public function delete(string $uri, mixed $handler): Route
    {
        return $this->addRoute(['DELETE'], $uri, $handler);
    }

    /** @param string[] $methods */
    public function match(array $methods, string $uri, mixed $handler): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $handler);
    }

    public function any(string $uri, mixed $handler): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $uri, $handler);
    }

    /**
     * Nhóm route: ['prefix' => '/admin', 'middleware' => ['auth', 'admin'], 'as' => 'admin.']
     *
     * @param array{prefix?: string, middleware?: string|string[], as?: string} $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => trim($attributes['prefix'] ?? '', '/'),
            'middleware' => (array) ($attributes['middleware'] ?? []),
            'as' => $attributes['as'] ?? '',
        ];
        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    /** @param string[] $methods */
    private function addRoute(array $methods, string $uri, mixed $handler): Route
    {
        $prefix = implode('/', array_filter(array_column($this->groupStack, 'prefix')));
        $full = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        $namePrefix = implode('', array_column($this->groupStack, 'as'));

        $route = new Route($methods, $full, $handler, $namePrefix);
        foreach ($this->groupStack as $group) {
            $route->middleware($group['middleware']);
        }

        return $this->routes[] = $route;
    }

    public function aliasMiddleware(string $alias, string $class): void
    {
        $this->aliases[$alias] = $class;
    }

    /** @param string[] $middleware */
    public function setGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    public function pushGlobalMiddleware(string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    /** @return Route[] */
    public function routes(): array
    {
        return $this->routes;
    }

    public function findByName(string $name): ?Route
    {
        foreach ($this->routes as $route) {
            if ($route->name === $name) {
                return $route;
            }
        }
        return null;
    }

    /** Sinh path (chưa gồm base path) từ tên route. */
    public function url(string $name, array $params = []): string
    {
        $route = $this->findByName($name) ?? throw new RuntimeException("Route [{$name}] không tồn tại.");
        return $route->url($params);
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }
            if (!$route->allowsMethod($method)) {
                $methodMismatch = true;
                continue;
            }

            $request->setRouteParams($params);
            $core = fn (Request $req): Response => $this->toResponse($this->callHandler($route->handler, $req, $params));

            return $this->pipeline(array_merge($this->globalMiddleware, $route->middleware), $core)($request);
        }

        throw new HttpException($methodMismatch ? 405 : 404);
    }

    /**
     * @param string[] $middleware
     * @param Closure(Request): Response $core
     * @return Closure(Request): Response
     */
    private function pipeline(array $middleware, Closure $core): Closure
    {
        $next = $core;
        foreach (array_reverse($middleware) as $definition) {
            [$alias, $args] = array_pad(explode(':', $definition, 2), 2, '');
            $class = $this->aliases[$alias] ?? $alias;
            $arguments = $args === '' ? [] : explode(',', $args);
            $next = function (Request $request) use ($class, $arguments, $next): Response {
                /** @var MiddlewareInterface $instance */
                $instance = $this->container->get($class);
                return $instance->handle($request, $next, ...$arguments);
            };
        }
        return $next;
    }

    /** @param array<string, string> $params */
    private function callHandler(mixed $handler, Request $request, array $params): mixed
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            $handler = explode('@', $handler, 2);
        }

        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            [$class, $method] = $handler;
            $controller = $this->container->make($class);
            if (!method_exists($controller, $method)) {
                throw new RuntimeException("Method {$class}::{$method}() không tồn tại.");
            }
            $args = $this->resolveArguments(new ReflectionMethod($controller, $method), $request, $params);
            return $controller->{$method}(...$args);
        }

        if ($handler instanceof Closure) {
            return $handler(...$this->resolveArguments(new ReflectionFunction($handler), $request, $params));
        }

        if (is_callable($handler)) {
            return $handler($request);
        }

        throw new RuntimeException('Route handler không hợp lệ.');
    }

    /**
     * Inject Request, tham số route (theo tên) và service (theo type-hint).
     *
     * @param array<string, string> $params
     * @return array<int, mixed>
     */
    private function resolveArguments(ReflectionFunctionAbstract $fn, Request $request, array $params): array
    {
        $args = [];
        foreach ($fn->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($typeName === Request::class) {
                $args[] = $request;
            } elseif (array_key_exists($param->getName(), $params)) {
                $value = $params[$param->getName()];
                $args[] = match ($typeName) {
                    'int' => ctype_digit($value) ? (int) $value : throw new HttpException(404),
                    'float' => (float) $value,
                    default => $value,
                };
            } elseif ($typeName !== null && !($type instanceof ReflectionNamedType && $type->isBuiltin())) {
                $args[] = $this->container->get($typeName);
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new RuntimeException('Không resolve được tham số $' . $param->getName());
            }
        }
        return $args;
    }

    private function toResponse(mixed $result): Response
    {
        return match (true) {
            $result instanceof Response => $result,
            $result === null => new Response('', 204),
            is_array($result), $result instanceof JsonSerializable => Response::json($result),
            default => Response::html((string) $result),
        };
    }
}
