<?php

declare(strict_types=1);

/*
 * Front controller — mọi request đều đi qua đây.
 */

define('BASE_PATH', dirname(__DIR__));

/** @var App\Core\App $app */
$app = require BASE_PATH . '/bootstrap/app.php';
$app->run();
