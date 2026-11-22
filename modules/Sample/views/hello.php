<?php $this->extend('layouts/main', ['title' => $title]) ?>

<section class="container section">
    <div class="card prose">
        <span class="badge">Module</span>
        <h1>Xin chào từ module <code>Sample</code></h1>
        <p>Trang này được thêm bởi <code>modules/Sample/Module.php</code> mà không cần sửa code lõi.</p>
        <p>Tạo module mới nhanh bằng lệnh:</p>
        <pre><code>C:\xampp\php\php.exe cli.php make:module Blog</code></pre>
        <p>Sau đó thêm <code>'Blog'</code> vào <code>config/modules.php</code>.</p>
        <a class="btn" href="<?= url('/') ?>">← Về trang chủ</a>
    </div>
</section>
