<?php

declare(strict_types=1);

namespace Modules\Sample;

use App\Core\Logger;
use App\Core\Router;
use App\Core\ServiceProvider;
use App\Core\View;

/**
 * Module mẫu — minh hoạ cách thêm tính năng mà KHÔNG sửa code lõi:
 *   - đăng ký view namespace riêng      (sample::hello)
 *   - thêm route                         (/sample)
 *   - lắng nghe event nghiệp vụ          (order.created, order.paid)
 *   - dùng filter hook để can thiệp giá  (cart.discount / order.discount) — xem ví dụ comment bên dưới
 *   - migration riêng                    (modules/Sample/migrations/*.sql — chạy cùng `php cli.php migrate`)
 *
 * Tắt module: xoá 'Sample' khỏi config/modules.php
 */
final class Module extends ServiceProvider
{
    public function register(): void
    {
        $this->app->get(View::class)->addNamespace('sample', __DIR__ . '/views');
    }

    public function boot(): void
    {
        $router = $this->app->router();
        $router->get('/sample', fn () => view('sample::hello', ['title' => 'Module mẫu']))->name('sample.hello');

        $events = $this->app->events();
        $logger = $this->app->get(Logger::class);

        $events->listen('order.created', function (array $payload) use ($logger): void {
            $logger->info('Đơn mới ' . $payload['order']['code'], ['total' => $payload['order']['total']], 'orders');
            // Ví dụ: gửi email xác nhận, bắn thông báo Telegram/Slack, đẩy sang hệ thống kho...
        });

        $events->listen('order.paid', function (array $payload) use ($logger): void {
            $logger->info('Đơn đã thanh toán ' . ($payload['order']['code'] ?? ''), [], 'orders');
        });

        // Ví dụ giảm 5% cho đơn từ 2 triệu (bỏ comment để thử):
        // $discount = fn (int $value, array $ctx): int => $ctx['subtotal'] >= 2_000_000 ? $value + intdiv($ctx['subtotal'] * 5, 100) : $value; // tiền là số nguyên, không nhân float
        // $events->listen('cart.discount', $discount);
        // $events->listen('order.discount', $discount);
    }
}
