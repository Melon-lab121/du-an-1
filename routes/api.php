<?php

declare(strict_types=1);

use App\Controllers\Api\ProductApiController;
use App\Core\Router;

return function (Router $router): void {
    $router->group(['prefix' => '/api', 'as' => 'api.'], function (Router $r): void {
        $r->get('/products', [ProductApiController::class, 'index'])->name('products.index');
        $r->get('/products/{slug}', [ProductApiController::class, 'show'])->name('products.show');
    });
};
