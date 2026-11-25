<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\DashboardStats;

final class DashboardController extends Controller
{
    public function __construct(private DashboardStats $stats)
    {
    }

    public function index(Request $request): Response
    {
        $orderStatus = (string) $request->query('order_status', '');
        if (!isset(Order::STATUSES[$orderStatus])) {
            $orderStatus = '';
        }

        $staffSort = (string) $request->query('staff_sort', 'revenue_desc');
        if (!isset(DashboardStats::STAFF_SORTS[$staffSort])) {
            $staffSort = 'revenue_desc';
        }

        $productSort = (string) $request->query('product_sort', 'sold_desc');
        if (!isset(DashboardStats::PRODUCT_SORTS[$productSort])) {
            $productSort = 'sold_desc';
        }

        $stock = (string) $request->query('stock', '');
        if (!isset(DashboardStats::STOCK_FILTERS[$stock])) {
            $stock = '';
        }

        return $this->view('admin/dashboard', [
            'stats' => $this->stats->summary(),
            'chart' => $this->stats->lastDaysSeries(7),
            'orderStatus' => $orderStatus,
            'staffSort' => $staffSort,
            'productSort' => $productSort,
            'stock' => $stock,
            'statusCounts' => $this->stats->orderStatusCounts(),
            'filteredOrderCount' => $this->stats->orderCount($orderStatus),
            'filteredOrders' => $this->stats->ordersByStatus($orderStatus),
            'staffRows' => $this->stats->staffByRevenue($staffSort),
            'productRows' => $this->stats->productsFiltered($productSort, $stock),
        ]);
    }
}
