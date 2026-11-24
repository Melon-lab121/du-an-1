<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private Csrf $csrf, private Config $config)
    {
    }

    public function handle(Request $request, callable $next, string ...$args): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || $this->isExcepted($request->path())) {
            return $next($request);
        }

        $token = $request->input('_token') ?? $request->header('X-CSRF-TOKEN');
        if (!$this->csrf->validate(is_string($token) ? $token : null)) {
            throw new HttpException(419);
        }

        return $next($request);
    }

    private function isExcepted(string $path): bool
    {
        foreach ((array) $this->config->get('app.csrf_except', []) as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }
        return false;
    }
}
