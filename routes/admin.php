<?php

$router->get('/admin/comments', [App\Controllers\Admin\CommentController::class, 'index'])->name('admin.comments.index');
$router->post('/admin/comments/status', [App\Controllers\Admin\CommentController::class, 'updateStatus'])->name('admin.comments.status');
$router->post('/admin/comments/delete', [App\Controllers\Admin\CommentController::class, 'delete'])->name('admin.comments.delete');
