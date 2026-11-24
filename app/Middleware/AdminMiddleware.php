<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/**
 * Chặn người không phải admin. Có thể truyền danh sách role: ->middleware('admin:admin,staff')
 */
final class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth)
    {
    }

    public function handle(Request $request, callable $next, string ...$roles): Response
    {
        $roles = $roles === [] ? ['admin'] : $roles;
        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect(url('/login'));
        }
        if (!in_array($user['role'] ?? '', $roles, true)) {
            throw new HttpException(403);
        }
        return $next($request);
    }
}
