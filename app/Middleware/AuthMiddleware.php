<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private Auth $auth, private Session $session)
    {
    }

    public function handle(Request $request, callable $next, string ...$args): Response
    {
        if ($this->auth->check()) {
            $request->setAttribute('user', $this->auth->user());
            return $next($request);
        }

        if ($request->wantsJson()) {
            throw new HttpException(401);
        }

        if ($request->isMethod('GET')) {
            $this->session->set('url.intended', $request->path());
        }
        $this->session->flash('error', 'Vui lòng đăng nhập để tiếp tục.');
        return Response::redirect(url('/login'));
    }
}
