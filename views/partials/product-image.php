<?php
/** Ảnh sản phẩm, hoặc ảnh giữ chỗ dạng gradient khi chưa upload. @var array<string, mixed> $product */
$hue = crc32((string) $product['name']) % 360;
?>
<?php if (!empty($product['image'])): ?>
    <img src="<?= e(url($product['image'])) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
<?php else: ?>
    <div class="placeholder" style="--h: <?= $hue ?>" aria-label="<?= e($product['name']) ?>">
        <span><?= e(mb_strtoupper(mb_substr((string) $product['name'], 0, 1))) ?></span>
    </div>
<?php endif ?>
