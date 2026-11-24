<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Response;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

final class DashboardController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/dashboard', [
            'stats' => [
                'revenue' => Order::query()->where('payment_status', 'paid')->sum('total'),
                'orders' => Order::query()->count(),
                'pending' => Order::query()->where('status', 'pending')->count(),
                'products' => Product::query()->count(),
                'customers' => User::query()->where('role', 'customer')->count(),
                'low_stock' => Product::query()->where('stock', '<=', 5)->count(),
            ],
            'recentOrders' => Order::query()->latest()->limit(8)->get(),
        ]);
    }
}
