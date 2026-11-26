<?php $this->extend('layouts/main', ['title' => 'Đăng ký']) ?>

<section class="container section narrow">
    <form class="card auth-card" action="<?= url('/register') ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <h1>Tạo tài khoản</h1>
        <?php foreach ([
            ['name', 'Họ tên', 'text', 'name'],
            ['email', 'Email', 'email', 'email'],
            ['phone', 'Số điện thoại (tuỳ chọn)', 'tel', 'tel'],
        ] as [$name, $label, $type, $auto]): ?>
            <label class="field">
                <span><?= $label ?></span>
                <input type="<?= $type ?>" name="<?= $name ?>" value="<?= e(old($name)) ?>" autocomplete="<?= $auto ?>">
                <?php if ($err = error($name)): ?><small class="error"><?= e($err) ?></small><?php endif ?>
            </label>
        <?php endforeach ?>
        <label class="field">
            <span>Mật khẩu (tối thiểu 8 ký tự)</span>
            <input type="password" name="password" autocomplete="new-password">
            <?php if ($err = error('password')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
        </label>
        <label class="field">
            <span>Nhập lại mật khẩu</span>
            <input type="password" name="password_confirmation" autocomplete="new-password">
        </label>
        <button class="btn btn-lg btn-block" type="submit">Đăng ký</button>
        <p class="muted center">Đã có tài khoản? <a href="<?= url('/login') ?>">Đăng nhập</a></p>
    </form>
</section>
