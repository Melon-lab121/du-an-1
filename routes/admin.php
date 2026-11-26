<?php

declare(strict_types=1);

use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\CommentController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\ProductController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\VoucherController;
use App\Core\Router;

return function (Router $router): void {
    $router->group(['prefix' => '/admin', 'middleware' => ['auth', 'admin'], 'as' => 'admin.'], function (Router $r): void {
        $r->get('/', [DashboardController::class, 'index'])->name('dashboard');

        $r->get('/products', [ProductController::class, 'index'])->name('products.index');
        $r->get('/products/create', [ProductController::class, 'create'])->name('products.create');
        $r->post('/products', [ProductController::class, 'store'])->name('products.store');
        $r->get('/products/{id:\d+}/edit', [ProductController::class, 'edit'])->name('products.edit');
        $r->put('/products/{id:\d+}', [ProductController::class, 'update'])->name('products.update');
        $r->delete('/products/{id:\d+}', [ProductController::class, 'destroy'])->name('products.destroy');
        // Xoá mềm: thùng rác, khôi phục, xoá vĩnh viễn
        $r->get('/products/trash', [ProductController::class, 'trash'])->name('products.trash');
        $r->post('/products/{id:\d+}/restore', [ProductController::class, 'restore'])->name('products.restore');
        $r->delete('/products/{id:\d+}/force', [ProductController::class, 'forceDestroy'])->name('products.force');
        // Biến thể động
        $r->post('/products/{id:\d+}/options', [ProductController::class, 'saveOptions'])->name('products.options');
        $r->post('/products/{id:\d+}/variants', [ProductController::class, 'saveVariants'])->name('products.variants');
        $r->post('/products/{id:\d+}/variants/batch', [ProductController::class, 'batchStoreVariants']);
        $r->post('/products/{id:\d+}/variants/{variantId}/quick-update', [ProductController::class, 'quickUpdateVariant']);
        $r->post('/products/{id:\d+}/variants/{variantId}/force-delete', [ProductController::class, 'forceDeleteVariant']);
        $r->post('/products/{id:\d+}/variants/{variantId}/delete', [ProductController::class, 'softDeleteVariant']);
        $r->post('/products/{id:\d+}/variants/{variantId}/restore', [ProductController::class, 'restoreVariant']);
        $r->get('/products/{id}/variants/trash', [ProductController::class, 'variantsTrash']);

        $r->get('/comments', [CommentController::class, 'index'])->name('comments.index');
        $r->post('/comments/{id:\d+}/toggle', [CommentController::class, 'toggleStatus'])->name('comments.toggle');
        $r->post('/comments/{id:\d+}/delete', [CommentController::class, 'destroy'])->name('comments.destroy');

        $r->get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        $r->post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        $r->put('/categories/{id:\d+}', [CategoryController::class, 'update'])->name('categories.update');
        $r->delete('/categories/{id:\d+}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        $r->get('/categories/trash', [CategoryController::class, 'trash'])->name('categories.trash');
        $r->post('/categories/{id:\d+}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
        $r->delete('/categories/{id:\d+}/force', [CategoryController::class, 'forceDestroy'])->name('categories.force');

        $r->get('/orders', [OrderController::class, 'index'])->name('orders.index');
        $r->get('/orders/{id:\d+}', [OrderController::class, 'show'])->name('orders.show');
        $r->post('/orders/{id:\d+}/status', [OrderController::class, 'updateStatus'])->name('orders.status');
        $r->post('/orders/{id:\d+}/refund', [OrderController::class, 'confirmRefund'])->name('orders.refund');
        $r->post('/orders/{id:\d+}/payments/{paymentId:\d+}/reconcile', [OrderController::class, 'reconcile'])->name('orders.reconcile');

        // Quản lý User
        $r->get('/users', [UserController::class, 'index'])->name('users.index');
        $r->get('/users/{id:\d+}/orders', [UserController::class, 'orders'])->name('users.orders');
        $r->post('/users/{id:\d+}/role', [UserController::class, 'updateRole'])->name('users.role');
        $r->post('/users/{id:\d+}/delete', [UserController::class, 'destroy'])->name('users.delete');

        // Quản lý Voucher
        $r->get('/vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
        $r->get('/vouchers/create', [VoucherController::class, 'create'])->name('vouchers.create');
        $r->post('/vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
        $r->get('/vouchers/{id:\d+}/edit', [VoucherController::class, 'edit'])->name('vouchers.edit');
        $r->put('/vouchers/{id:\d+}', [VoucherController::class, 'update'])->name('vouchers.update');
        $r->post('/vouchers/{id:\d+}/toggle', [VoucherController::class, 'toggle'])->name('vouchers.toggle');
        $r->delete('/vouchers/{id:\d+}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
    });
};