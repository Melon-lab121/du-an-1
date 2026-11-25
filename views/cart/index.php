<?php $this->extend('layouts/main', ['title' => 'Giỏ hàng']) ?>

<section class="container section">
    <h1>Giỏ hàng</h1>

    <?php if (!$lines): ?>
        <div class="empty">
            <p>Giỏ hàng của bạn đang trống.</p>
            <a class="btn" href="<?= url('/products') ?>">Tiếp tục mua sắm</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <form action="<?= url('/cart/update') ?>" method="post" class="card">
                <?= csrf_field() ?>
                <table class="table cart-table">
                    <thead><tr><th>Sản phẩm</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($lines as $line): ?>
                        <tr>
                            <td>
                                <a class="cart-item" href="<?= route('product.show', ['slug' => $line['slug']]) ?>">
                                    <span class="cart-thumb"><?= $this->insert('partials/product-image', ['product' => $line['product']]) ?></span>
                                    <span><?= e($line['name']) ?><?php if ($line['variant_label']): ?><br><small class="muted">Phân loại: <?= e($line['variant_label']) ?></small><?php endif ?></span>
                                </a>
                            </td>
                            <td data-label="Đơn giá"><?= money($line['price']) ?></td>
                            <td data-label="Số lượng">
                                <div class="qty" data-qty>
                                    <button type="button" data-step="-1" aria-label="Giảm">−</button>
                                    <input type="number" name="quantities[<?= e($line['key']) ?>]" value="<?= $line['quantity'] ?>" min="0" max="<?= $line['stock'] ?>" aria-label="Số lượng">
                                    <button type="button" data-step="1" aria-label="Tăng">+</button>
                                </div>
                            </td>
                            <td data-label="Thành tiền"><strong><?= money($line['total']) ?></strong></td>
                            <td>
                                <button class="btn-icon" type="submit" form="remove-<?= e(str_replace(':', '-', $line['key'])) ?>" aria-label="Xoá">✕</button>
                            </td>
                        </tr>
                    <?php endforeach ?>
                    </tbody>
                </table>
                <div class="card-footer">
                    <a href="<?= url('/products') ?>">← Tiếp tục mua sắm</a>
                    <button class="btn btn-ghost" type="submit">Cập nhật giỏ hàng</button>
                </div>
            </form>

            <?php foreach ($lines as $line): ?>
                <form id="remove-<?= e(str_replace(':', '-', $line['key'])) ?>" action="<?= url('/cart/remove') ?>" method="post" hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="key" value="<?= e($line['key']) ?>">
                </form>
            <?php endforeach ?>

            <aside class="card">
                <h3>Tóm tắt đơn hàng</h3>
                <?= $this->insert('partials/voucher-box') ?>
                <?= $this->insert('partials/order-summary', ['totals' => $totals]) ?>
                <a class="btn btn-lg btn-block" href="<?= url('/checkout') ?>">Tiến hành đặt hàng</a>
            </aside>
        </div>
    <?php endif ?>
</section>
