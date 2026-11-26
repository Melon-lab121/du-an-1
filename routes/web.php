<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CartController;
use App\Controllers\CheckoutController;
use App\Controllers\HomeController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Controllers\ProductController;
use App\Controllers\VoucherController;
use App\Core\Router;

return function (Router $router): void {
    $router->get('/', [HomeController::class, 'index'])->name('home');

    // Sản phẩm
    $router->get('/products', [ProductController::class, 'index'])->name('products.index');
    $router->get('/category/{slug}', [ProductController::class, 'category'])->name('category.show');
    $router->get('/product/{slug}', [ProductController::class, 'show'])->name('product.show');
    
    // Bình luận (chỉ cho phép khách đã đăng nhập)
    $router->post('/product/{slug}/reviews', [ProductController::class, 'storeReview'])->name('product.reviews.store');
    $router->post('/product/{slug}/comments', [ProductController::class, 'storeReview'])->name('product.comments.store');


    // Giỏ hàng
    $router->get('/cart', [CartController::class, 'index'])->name('cart.index');
    $router->post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    $router->post('/cart/update', [CartController::class, 'update'])->name('cart.update');
    $router->post('/cart/remove', [CartController::class, 'remove'])->name('cart.remove');

    // Voucher giảm giá
    $router->post('/voucher/apply', [VoucherController::class, 'apply'])->name('voucher.apply');
    $router->post('/voucher/remove', [VoucherController::class, 'remove'])->name('voucher.remove');

    // Đặt hàng (cho phép khách vãng lai)
    $router->get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    $router->post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    $router->get('/orders/{code}', [OrderController::class, 'show'])->name('order.show');
    $router->post('/orders/{code}/pay', [OrderController::class, 'pay'])->name('order.pay');

    // SePay: IPN là POST JSON server-to-server; 3 URL còn lại là trình duyệt khách quay về
    $router->post('/payment/sepay/ipn', [PaymentController::class, 'sepayIpn'])->name('sepay.ipn');
    $router->get('/payment/sepay/success', [PaymentController::class, 'sepaySuccess'])->name('sepay.success');
    $router->get('/payment/sepay/error', [PaymentController::class, 'sepayError'])->name('sepay.error');
    $router->get('/payment/sepay/cancel', [PaymentController::class, 'sepayCancel'])->name('sepay.cancel');

    // Tài khoản
    $router->group(['middleware' => ['guest']], function (Router $r): void {
        $r->get('/login', [AuthController::class, 'showLogin'])->name('login');
        $r->post('/login', [AuthController::class, 'login']);
        $r->get('/register', [AuthController::class, 'showRegister'])->name('register');
        $r->post('/register', [AuthController::class, 'register']);
    });

    $router->group(['middleware' => ['auth']], function (Router $r): void {
        $r->post('/logout', [AuthController::class, 'logout'])->name('logout');
        $r->get('/orders', [OrderController::class, 'index'])->name('orders.index');
    });
};
