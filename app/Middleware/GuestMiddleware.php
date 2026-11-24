<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/** Chỉ cho khách chưa đăng nhập (trang login/register). */
final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth)
    {
    }

    public function handle(Request $request, callable $next, string ...$args): Response
    {
        if ($this->auth->check()) {
            return Response::redirect(url('/'));
        }
        return $next($request);
    }
}
