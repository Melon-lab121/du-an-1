<?php
/**
 * Trang trung gian: in form ẩn đã ký rồi tự POST sang trang thanh toán của cổng (SePay checkout).
 *
 * @var array{action: string, fields: array<string, string>} $checkout
 */
$this->extend('layouts/main', ['title' => 'Đang chuyển sang trang thanh toán']);
?>

<section class="container section narrow">
    <div class="card result-card">
        <h1>Đang chuyển sang trang thanh toán…</h1>
        <p class="lead">Nếu trình duyệt không tự chuyển, bấm nút bên dưới.</p>
        <form id="payment-redirect" method="post" action="<?= e($checkout['action']) ?>">
            <?php foreach ($checkout['fields'] as $name => $value): ?>
                <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
            <?php endforeach ?>
            <button class="btn" type="submit">Tiếp tục thanh toán</button>
        </form>
    </div>
</section>

<?php $this->start('scripts') ?>
<script>document.getElementById('payment-redirect').submit();</script>
<?php $this->stop() ?>
