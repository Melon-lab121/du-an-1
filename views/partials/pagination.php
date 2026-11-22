<?php
/** @var array{page: int, last_page: int, total: int} $pager */
if ($pager['last_page'] <= 1) {
    return;
}
$query = request()->query();
$link = static function (int $page) use ($query): string {
    return url(request()->path(), array_merge($query, ['page' => $page]));
};
$start = max(1, $pager['page'] - 2);
$end = min($pager['last_page'], $pager['page'] + 2);
?>
<nav class="pagination" aria-label="Phân trang">
    <?php if ($pager['page'] > 1): ?><a href="<?= e($link($pager['page'] - 1)) ?>">‹</a><?php endif ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= e($link($i)) ?>" class="<?= $i === $pager['page'] ? 'current' : '' ?>"><?= $i ?></a>
    <?php endfor ?>
    <?php if ($pager['page'] < $pager['last_page']): ?><a href="<?= e($link($pager['page'] + 1)) ?>">›</a><?php endif ?>
</nav>
