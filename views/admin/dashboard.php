<?php
use App\Models\Order;
use App\Services\DashboardStats;

/** @var App\Core\View $this */
/** @var array{revenue: int|float, orders: int, pending: int, products: int, customers: int, low_stock: int} $stats */
/** @var array{labels: list<string>, revenue: list<int>, orders: list<int>} $chart */
/** @var string $orderStatus */
/** @var string $productSort */
/** @var string $stock */
/** @var array<string, int> $statusCounts */
/** @var int $filteredOrderCount */
/** @var array<int, array<string, mixed>> $filteredOrders */
/** @var array<int, array<string, mixed>> $recentReturns */
/** @var array<int, array<string, mixed>> $mergeGroups */
/** @var array<int, array{id: int, name: string, stock: int, sold: int}> $productRows */

$this->extend('layouts/admin', ['title' => 'Tổng quan', 'heading' => 'Tổng quan']);

$recentReturns = $recentReturns ?? [];
$mergeGroups = $mergeGroups ?? [];

$keep = array_filter([
    'order_status' => $orderStatus,
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
        <h3>Gợi ý gom đơn (mua cùng sản phẩm qua nhiều đơn)</h3>
        <span class="muted"><?= count($mergeGroups) ?> nhóm cần CSKH liên hệ</span>
    </div>
    <?php if ($mergeGroups === []): ?>
        <div class="dash-empty">Chưa có khách nào đặt trùng sản phẩm trên nhiều đơn mở.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Khách hàng / SĐT</th>
                    <th>Sản phẩm</th>
                    <th>Số đơn</th>
                    <th>Tổng SL</th>
                    <th>Mã đơn liên quan</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($mergeGroups as $grp): ?>
                <tr>
                    <td>
                        <strong><?= e((string) $grp['customer_name']) ?></strong><br>
                        <small class="muted"><?= e((string) $grp['customer_phone']) ?></small>
                    </td>
                    <td>
                        <?= e((string) $grp['product_name']) ?>
                        <?php if (!empty($grp['variant_label'])): ?>
                            <br><small class="muted"><?= e((string) $grp['variant_label']) ?></small>
                        <?php endif ?>
                    </td>
                    <td data-label="Số đơn"><strong><?= (int) $grp['order_count'] ?></strong></td>
                    <td data-label="Tổng SL"><?= (int) $grp['total_qty'] ?></td>
                    <td data-label="Mã đơn liên quan"><?= e((string) ($grp['order_codes'] ?? '—')) ?></td>
                </tr>
            <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
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
        <h3>Lượt trả hàng gần đây</h3>
        <a href="<?= url('/admin/orders?status=returned') ?>">Xem đơn trả hàng →</a>
    </div>
    <?php if ($recentReturns === []): ?>
        <div class="dash-empty">Chưa có yêu cầu trả hàng nào.</div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Lý do &amp; mô tả / STK</th>
                    <th>Trạng thái</th>
                    <th>Thời gian</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentReturns as $ret): ?>
                <tr>
                    <td>
                        <?php if (!empty($ret['order_id'])): ?>
                            <a href="<?= url('/admin/orders/' . (int) $ret['order_id']) ?>"><strong><?= e((string) ($ret['order_code'] ?? ('#' . $ret['order_id']))) ?></strong></a>
                        <?php else: ?>
                            <strong><?= e((string) ($ret['order_code'] ?? '—')) ?></strong>
                        <?php endif ?>
                    </td>
                    <td data-label="Khách hàng">
                        <?= e((string) ($ret['customer_name'] ?? 'Khách')) ?><br>
                        <small class="muted"><?= e((string) ($ret['customer_phone'] ?? '')) ?></small>
                    </td>
                    <td data-label="Lý do & mô tả / STK">
                        <strong><?= e(Order::RETURN_REASONS[$ret['reason']] ?? (string) ($ret['reason'] ?? '')) ?></strong><br>
                        <small><?= e((string) ($ret['description'] ?? '')) ?></small>
                    </td>
                    <td data-label="Trạng thái"><span class="tag"><?= e((string) ($ret['status'] ?? 'pending')) ?></span></td>
                    <td data-label="Thời gian"><?= e(date('d/m/Y H:i', strtotime((string) ($ret['created_at'] ?? 'now')))) ?></td>
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