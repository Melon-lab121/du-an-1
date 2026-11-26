<?php
/**
 * Ảnh sản phẩm hoặc ảnh biến thể, hoặc ảnh giữ chỗ khi chưa có ảnh.
 * @var array<string, mixed> $product
 * @var string|null $image
 * @var string|null $alt
 */
$rawSrc = trim((string) ($image ?? $product['image'] ?? ''));$label = (string) ($alt ?? $product['name'] ?? 'Sản phẩm');
$hue = crc32($label) % 360;

$imgSrc = '';
if ($rawSrc !== '') {
    $imgSrc = preg_match('#^https?://#i', $rawSrc) === 1
        ? $rawSrc
        : url($rawSrc);
}
?>
<?php if ($imgSrc !== ''): ?>
    <img src="<?= e($imgSrc) ?>" alt="<?= e($label) ?>" loading="lazy">
<?php else: ?>
    <div class="placeholder" style="--h: <?= $hue ?>" aria-label="<?= e($label) ?>">
        <span><?= e(mb_strtoupper(mb_substr($label, 0, 1))) ?></span>
    </div>
<?php endif ?>