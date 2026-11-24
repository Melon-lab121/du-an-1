<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Provider/Module: nơi đăng ký service, route, event listener, view namespace...
 * register() chạy trước cho tất cả provider, sau đó mới tới boot().
 */
abstract class ServiceProvider
{
    public function __construct(protected App $app)
    {
    }

    public function register(): void
    {
    }

    public function boot(): void
    {
    }
}
