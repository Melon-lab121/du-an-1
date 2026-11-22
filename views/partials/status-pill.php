<?php
/** @var string $status  @var string $type 'order'|'payment'|'transaction' */
use App\Models\Order;

$type ??= 'order';
$label = match ($type) {
    'payment' => Order::paymentStatusLabel($status),
    'transaction' => ['pending' => 'Đang chờ', 'success' => 'Thành công', 'failed' => 'Thất bại'][$status] ?? $status,
    default => Order::statusLabel($status),
};
$tone = match ($status) {
    'paid', 'completed', 'success' => 'success',
    'failed', 'cancelled' => 'danger',
    'confirmed', 'shipping' => 'info',
    default => 'warning',
};
?>
<span class="pill pill-<?= $tone ?>"><?= e($label) ?></span>
