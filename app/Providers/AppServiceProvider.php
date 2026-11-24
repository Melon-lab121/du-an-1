<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\ServiceProvider;
use App\Core\View;
use App\Models\Category;
use App\Services\Auth;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\CodGateway;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SepayGateway;
use App\Services\Uploader;
use Throwable;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $c = $this->app->container();

        foreach ([Auth::class, CartService::class, OrderService::class, PaymentService::class, Uploader::class] as $class) {
            $c->singleton($class, fn (Container $c) => $c->make($class));
        }

        $c->singleton(PaymentManager::class, function (): PaymentManager {
            $config = $this->app->config();
            $sepay = (array) $config->get('payment.gateways.sepay', []);
            // success/error/cancel URL sinh từ APP_URL, gửi sang SePay trong form (có ký)
            $sepay['success_url'] = absolute_url('/payment/sepay/success');
            $sepay['error_url'] = absolute_url('/payment/sepay/error');
            $sepay['cancel_url'] = absolute_url('/payment/sepay/cancel');

            $manager = new PaymentManager();
            $manager->register(new CodGateway((array) $config->get('payment.gateways.cod', [])));
            $manager->register(new SepayGateway($sepay));
            return $manager;
        });
    }

    public function boot(): void
    {
        $view = $this->app->get(View::class);
        $view->share('appName', (string) $this->app->config()->get('app.name', 'Shop'));

        // Danh mục cho menu header — chỉ query khi render layout chính
        $view->composer('layouts/main', static function (): array {
            try {
                return ['navCategories' => Category::ordered()];
            } catch (Throwable) {
                return ['navCategories' => []]; // DB chưa sẵn sàng vẫn hiển thị được trang lỗi
            }
        });
    }
}
