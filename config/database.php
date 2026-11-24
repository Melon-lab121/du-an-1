<?php

declare(strict_types=1);

return [
    // mysql (XAMPP MariaDB/MySQL) | sqlite (chỉ dùng cho test)
    'driver' => env('DB_CONNECTION', 'mysql'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => env('DB_DATABASE', 'base_shop'),
    'username' => env('DB_USERNAME', 'root'),
    'password' => (string) env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
];
