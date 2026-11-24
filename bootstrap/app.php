<?php

declare(strict_types=1);

/*
 * Khởi tạo ứng dụng: autoload, .env, config, provider, module.
 * Dùng chung cho public/index.php, cli.php và tests.
 */

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Env;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Yêu cầu PHP >= 8.1 (hiện tại: ' . PHP_VERSION . ')');
}

require_once BASE_PATH . '/app/Core/Autoloader.php';
Autoloader::register([
    'App' => BASE_PATH . '/app',
    'Modules' => BASE_PATH . '/modules',
    'Database' => BASE_PATH . '/database',
]);

// Nếu sau này dùng Composer để cài thêm thư viện
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

require_once BASE_PATH . '/app/helpers.php';

Env::load(BASE_PATH . '/.env');

return new App(BASE_PATH);
