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
        Order::autoCompleteExpired();

        $orderStatus = (string) $request->query('order_status', '');
        if (!isset(Order::STATUSES[$orderStatus])) {
            $orderStatus = '';
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
            'productSort' => $productSort,
            'stock' => $stock,
            'statusCounts' => $this->stats->orderStatusCounts(),
            'filteredOrderCount' => $this->stats->orderCount($orderStatus),
            'filteredOrders' => $this->stats->ordersByStatus($orderStatus),
            'recentReturns' => $this->stats->recentReturns(10),
            'mergeGroups' => $this->stats->mergeGroups(20),
            'productRows' => $this->stats->productsFiltered($productSort, $stock),
        ]);
    }
}