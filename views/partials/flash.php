<?php foreach (['success', 'error', 'info'] as $type): ?>
    <?php if ($message = flash($type)): ?>
        <div class="container">
            <div class="alert alert-<?= $type ?>" role="alert"><?= e($message) ?></div>
        </div>
    <?php endif ?>
<?php endforeach ?>
