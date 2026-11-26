<?php $this->extend('layouts/main', ['title' => 'Đăng nhập']) ?>

<section class="container section narrow">
    <form class="card auth-card" action="<?= url('/login') ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <h1>Đăng nhập</h1>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="email">
            <?php if ($err = error('email')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
        </label>
        <label class="field">
            <span>Mật khẩu</span>
            <input type="password" name="password" required autocomplete="current-password">
            <?php if ($err = error('password')): ?><small class="error"><?= e($err) ?></small><?php endif ?>
        </label>
        <button class="btn btn-lg btn-block" type="submit">Đăng nhập</button>
        <p class="muted center">Chưa có tài khoản? <a href="<?= url('/register') ?>">Đăng ký</a></p>
        <?php if (config('app.debug')): ?>
            <p class="muted small center">Demo: admin@gmail.com / 12345678</p>
        <?php endif ?>
    </form>
</section>
