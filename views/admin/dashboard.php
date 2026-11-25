<?php
use App\Models\Order;
use App\Services\DashboardStats;

/** @var App\Core\View $this */
/** @var array{revenue: int|float, orders: int, pending: int, products: int, customers: int, low_stock: int} $stats */
/** @var array{labels: list<string>, revenue: list<int>, orders: list<int>} $chart */
/** @var string $orderStatus */
/** @var string $staffSort */
/** @var string $productSort */
/** @var string $stock */
/** @var array<string, int> $statusCounts */
/** @var int $filteredOrderCount */
/** @var array<int, array<string, mixed>> $filteredOrders */
/** @var array<int, array{id: int, name: string, email: string, role: string, revenue: int, order_count: int}> $staffRows */
/** @var array<int, array{id: int, name: string, stock: int, sold: int}> $productRows */

$this->extend('layouts/admin', ['title' => 'Tổng quan', 'heading' => 'Tổng quan']);

$keep = array_filter([
    'order_status' => $orderStatus,
    'staff_sort' => $staffSort,
    'product_sort' => $productSort,
    'stock' => $stock,
], static fn (string $value): bool => $value !== '');

$dashUrl = static function (array $overrides) use ($keep): string {
    $query = array_merge($keep, $overrides);
    $query = array_filter($query, static fn (string $value): bool => $value !== '');
    return url('/admin', $query);
};
?>

<div class="stat-grid">
    <div class="card stat"><span class="muted">Doanh thu (đã thanh toán)</span><strong><?= money($stats['revenue']) ?></strong></div>
    <div class="card stat"><span class="muted">Tổng đơn hàng</span><strong><?= number_format($stats['orders']) ?></strong></div>
    <div class="card stat"><span class="muted">Đơn chờ xác nhận</span><strong><?= number_format($stats['pending']) ?></strong></div>
    <div class="card stat"><span class="muted">Sản phẩm</span><strong><?= number_format($stats['products']) ?></strong></div>
    <div class="card stat"><span class="muted">Khách hàng</span><strong><?= number_format($stats['customers']) ?></strong></div>
    <div class="card stat"><span class="muted">Sắp hết hàng (≤ 5)</span><strong><?= number_format($stats['low_stock']) ?></strong></div>
</div>

<div class="dashboard-charts">
    <div class="card section">
        <div class="section-head"><h3>Doanh thu 7 ngày</h3><span class="muted">Đơn đã thanh toán</span></div>
        <div class="chart-wrap"><canvas id="chart-revenue" height="160" aria-label="Biểu đồ doanh thu 7 ngày"></canvas></div>
    </div>
    <div class="card section">
        <div class="section-head"><h3>Đơn hàng 7 ngày</h3><span class="muted">Tất cả đơn</span></div>
        <div class="chart-wrap"><canvas id="chart-orders" height="160" aria-label="Biểu đồ số đơn 7 ngày"></canvas></div>
    </div>
</div>

<div class="card section">
    <div class="section-head">
        <h3>Đơn hàng</h3>
        <span class="muted"><?= number_format($filteredOrderCount) ?> đơn<?= $orderStatus !== '' ? ' · ' . e(Order::statusLabel($orderStatus)) : '' ?></span>
    </div>

    <div class="status-chips">
        <a class="status-chip <?= $orderStatus === '' ? 'active' : '' ?>" href="<?= e($dashUrl(['order_status' => ''])) ?>">
            Tất cả <strong><?= number_format(array_sum($statusCounts)) ?></strong>
        </a>
        <?php foreach (Order::STATUSES as $value => $label): ?>
            <a class="status-chip <?= $orderStatus === $value ? 'active' : '' ?>" href="<?= e($dashUrl(['order_status' => $value])) ?>">
                <?= e($label) ?> <strong><?= number_format($statusCounts[$value] ?? 0) ?></strong>
            </a>
        <?php endforeach ?>
    </div>

    <form class="toolbar" method="get" action="<?= url('/admin') ?>">
        <input type="hidden" name="staff_sort" value="<?= e($staffSort) ?>">
        <input type="hidden" name="product_sort" value="<?= e($productSort) ?>">
        <input type="hidden" name="stock" value="<?= e($stock) ?>">
        <select name="order_status" aria-label="Lọc đơn hàng theo trạng thái">
            <option value="">Tất cả trạng thái</option>
            <?php foreach (Order::STATUSES as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $orderStatus === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <button class="btn btn-sm" type="submit">Lọc</button>
        <a href="<?= url('/admin/orders') ?>">Xem tất cả →</a>
    </form>

    <?= $this->insert('admin/orders/table', ['orders' => $filteredOrders]) ?>
</div>

<div class="card section">
    <div class="section-head">
        <h3>Nhân viên theo doanh số</h3>
        <span class="muted">Chỉ đơn đã thanh toán</span>
    </div>
    <form class="toolbar" method="get" action="<?= url('/admin') ?>">
        <input type="hidden" name="order_status" value="<?= e($orderStatus) ?>">
        <input type="hidden" name="product_sort" value="<?= e($productSort) ?>">
        <input type="hidden" name="stock" value="<?= e($stock) ?>">
        <select name="staff_sort" aria-label="Lọc nhân viên theo doanh số">
            <?php foreach (DashboardStats::STAFF_SORTS as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $staffSort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <button class="btn btn-sm" type="submit">Lọc</button>
    </form>
    <?php if ($staffRows === []): ?>
        <div class="dash-empty">Chưa có nhân viên.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Nhân viên</th><th>Vai trò</th><th>Số đơn</th><th>Doanh số</th></tr></thead>
            <tbody>
            <?php foreach ($staffRows as $row): ?>
                <tr>
                    <td>
                        <strong><?= e($row['name']) ?></strong><br>
                        <small class="muted"><?= e($row['email']) ?></small>
                    </td>
                    <td data-label="Vai trò"><?= e($row['role'] === 'admin' ? 'Quản trị' : 'Nhân viên') ?></td>
                    <td data-label="Số đơn"><?= number_format($row['order_count']) ?></td>
                    <td data-label="Doanh số"><?= money($row['revenue']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<div class="card section">
    <div class="section-head">
        <h3>Sản phẩm</h3>
        <a href="<?= url('/admin/products') ?>">Sản phẩm →</a>
    </div>
    <form class="toolbar" method="get" action="<?= url('/admin') ?>">
        <input type="hidden" name="order_status" value="<?= e($orderStatus) ?>">
        <input type="hidden" name="staff_sort" value="<?= e($staffSort) ?>">
        <select name="product_sort" aria-label="Lọc sản phẩm theo lượt mua hoặc tồn kho">
            <?php foreach (DashboardStats::PRODUCT_SORTS as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $productSort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <select name="stock" aria-label="Lọc theo số lượng trong kho">
            <?php foreach (DashboardStats::STOCK_FILTERS as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= $stock === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach ?>
        </select>
        <button class="btn btn-sm" type="submit">Lọc</button>
    </form>
    <?php if ($productRows === []): ?>
        <div class="dash-empty">Chưa có sản phẩm.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Sản phẩm</th><th>Lượt mua</th><th>Tồn kho</th></tr></thead>
            <tbody>
            <?php foreach ($productRows as $row): ?>
                <tr>
                    <td><a href="<?= url('/admin/products/' . $row['id'] . '/edit') ?>"><?= e($row['name']) ?></a></td>
                    <td data-label="Lượt mua"><?= number_format($row['sold']) ?></td>
                    <td data-label="Tồn kho" class="<?= $row['stock'] <= 5 ? 'text-danger' : '' ?>"><?= number_format($row['stock']) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<?php $this->start('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    var chart = <?= json_encode($chart, JSON_UNESCAPED_UNICODE) ?>;
    var brand = getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#4f46e5';
    var muted = getComputedStyle(document.documentElement).getPropertyValue('--muted').trim() || '#64748b';
    var grid = getComputedStyle(document.documentElement).getPropertyValue('--border').trim() || '#e5e7eb';

    new Chart(document.getElementById('chart-revenue'), {
        type: 'line',
        data: {
            labels: chart.labels,
            datasets: [{
                label: 'Doanh thu',
                data: chart.revenue,
                borderColor: brand,
                backgroundColor: 'transparent',
                tension: 0.35,
                fill: false,
                pointRadius: 3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: muted }, grid: { color: grid } },
                y: {
                    ticks: {
                        color: muted,
                        callback: function (v) { return new Intl.NumberFormat('vi-VN').format(v); },
                    },
                    grid: { color: grid },
                    beginAtZero: true,
                },
            },
        },
    });

    new Chart(document.getElementById('chart-orders'), {
        type: 'bar',
        data: {
            labels: chart.labels,
            datasets: [{
                label: 'Đơn hàng',
                data: chart.orders,
                backgroundColor: brand,
                borderRadius: 6,
                maxBarThickness: 36,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: muted }, grid: { display: false } },
                y: {
                    ticks: { color: muted, stepSize: 1 },
                    grid: { color: grid },
                    beginAtZero: true,
                },
            },
        },
    });
});
</script>
<?php $this->stop() ?>
