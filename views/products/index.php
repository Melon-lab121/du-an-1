<?php
$heading = $category['name'] ?? ($filters['q'] !== '' ? 'Kết quả cho “' . $filters['q'] . '”' : 'Tất cả sản phẩm');
$this->extend('layouts/main', ['title' => $heading]);
$sorts = ['newest' => 'Mới nhất', 'price_asc' => 'Giá tăng dần', 'price_desc' => 'Giá giảm dần', 'name' => 'Tên A–Z'];
?>
<section class="container section">
    <div class="page-nav-bar">
        <a href="<?= url('/') ?>" class="btn-back-home" title="Trở về Trang chủ">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            <span>Về trang chủ</span>
        </a>
        <nav class="breadcrumb">
            <a href="<?= url('/') ?>" class="breadcrumb-link">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Trang chủ</span>
            </a>
            <span class="breadcrumb-sep">/</span>
            <span class="breadcrumb-current"><?= e($heading) ?></span>
        </nav>
    </div>

    <div class="catalog">
        <aside class="filters card">
            <h3>Danh mục</h3>
            <ul class="filter-list">
                <li><a class="<?= $category === null ? 'active' : '' ?>" href="<?= url('/products') ?>">Tất cả</a></li>
                <?php foreach ($categories as $cat): ?>
                    <li>
                        <a class="<?= ($category['id'] ?? null) === $cat['id'] ? 'active' : '' ?>" href="<?= route('category.show', ['slug' => $cat['slug']]) ?>">
                            <?= e($cat['name']) ?> <span class="muted">(<?= (int) $cat['product_count'] ?>)</span>
                        </a>
                    </li>
                <?php endforeach ?>
            </ul>

            <h3>Khoảng giá</h3>
            <form method="get" class="price-filter">
                <?php if ($filters['q'] !== ''): ?><input type="hidden" name="q" value="<?= e($filters['q']) ?>"><?php endif ?>
                <input type="number" name="min_price" min="0" step="10000" placeholder="Từ" value="<?= e($filters['min_price'] ?? '') ?>">
                <input type="number" name="max_price" min="0" step="10000" placeholder="Đến" value="<?= e($filters['max_price'] ?? '') ?>">
                <button class="btn btn-sm" type="submit">Lọc</button>
            </form>
        </aside>

        <div>
            <div class="toolbar">
                <h1 class="h2"><?= e($heading) ?> <span class="muted">(<?= $pager['total'] ?>)</span></h1>
                <form method="get">
                    <?php foreach (['q', 'min_price', 'max_price'] as $k): ?>
                        <?php if (!empty($filters[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= e($filters[$k]) ?>"><?php endif ?>
                    <?php endforeach ?>
                    <select name="sort" onchange="this.form.submit()" aria-label="Sắp xếp">
                        <?php foreach ($sorts as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach ?>
                    </select>
                </form>
            </div>

            <?php if ($pager['data']): ?>
                <div class="product-grid">
                    <?php foreach ($pager['data'] as $product): ?>
                        <?= $this->insert('partials/product-card', ['product' => $product]) ?>
                    <?php endforeach ?>
                </div>
                <?= $this->insert('partials/pagination', ['pager' => $pager]) ?>
            <?php else: ?>
                <div class="empty">Không tìm thấy sản phẩm phù hợp.</div>
            <?php endif ?>
        </div>
    </div>
</section>
