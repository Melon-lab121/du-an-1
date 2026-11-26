<?php $this->extend('layouts/main', ['title' => $success ? 'Thanh toán thành công' : 'Kết quả thanh toán']) ?>

<section class="container section narrow">
    <div class="card result-card">
        <div class="result-icon <?= $success ? 'ok' : 'fail' ?>"><?= $success ? '✓' : '!' ?></div>
        <h1><?= $success ? 'Thanh toán thành công' : 'Thanh toán chưa hoàn tất' ?></h1>
        <p class="lead"><?= e($message) ?></p>
        <?php if (!empty($note)): ?><p class="muted small"><?= e($note) ?></p><?php endif ?>

        <?php if ($payment): ?>
            <?php // Mọi thông tin dưới đây lấy từ DB, không lấy từ URL (success_url không có chữ ký) ?>
            <dl class="summary">
                <?php if ($order): ?><div><dt>Mã đơn hàng</dt><dd><?= e($order['code']) ?></dd></div><?php endif ?>
                <div><dt>Số tiền</dt><dd><?= money((int) $payment['amount']) ?></dd></div>
                <div><dt>Mã hoá đơn SePay</dt><dd><code><?= e($payment['txn_ref']) ?></code></dd></div>
                <div><dt>Mã giao dịch</dt><dd><?= e($payment['transaction_no'] ?? '—') ?></dd></div>
                <div><dt>Hình thức</dt><dd><?= e($methods[(string) ($payment['bank_code'] ?? '')] ?? ($payment['bank_code'] ?? '—')) ?></dd></div>
                <div><dt>Trạng thái ghi nhận</dt><dd><?= $this->insert('partials/status-pill', ['status' => $payment['status'], 'type' => 'transaction']) ?></dd></div>
            </dl>
        <?php endif ?>

        <div class="hero-actions center">
            <?php if ($order): ?><a class="btn" href="<?= route('order.show', ['code' => $order['code']]) ?>">Xem đơn hàng</a><?php endif ?>
            <a class="btn btn-ghost" href="<?= url('/') ?>">Về trang chủ</a>
        </div>
    </div>
</section>
