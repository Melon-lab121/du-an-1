<?php

$router->post('/comments', [App\Controllers\CommentController::class, 'store'])->name('comments.store');
