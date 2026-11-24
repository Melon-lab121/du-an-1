<?php

declare(strict_types=1);

use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Providers\AppServiceProvider;

return [
    'name' => env('APP_NAME', 'Base Shop'),
    'env' => env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', 'http://localhost/base'), '/'),
    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => 'vi',

    // Provider lõi của ứng dụng. Module thì khai báo trong config/modules.php
    'providers' => [
        AppServiceProvider::class,
    ],

    // Middleware chạy cho MỌI route
    'middleware' => [
        CsrfMiddleware::class,
    ],

    // Tên rút gọn dùng trong route: ->middleware('auth')
    'middleware_aliases' => [
        'auth' => AuthMiddleware::class,
        'guest' => GuestMiddleware::class,
        'admin' => AdminMiddleware::class,
        'csrf' => CsrfMiddleware::class,
    ],

    // Đường dẫn bỏ qua kiểm tra CSRF (webhook/IPN từ bên ngoài). Hỗ trợ dấu *
    'csrf_except' => [
        '/payment/sepay/ipn',
    ],

    'session' => [
        'name' => env('SESSION_NAME', 'base_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 120), // phút
    ],
];
