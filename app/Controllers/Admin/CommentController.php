<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Comment;

final class CommentController extends Controller
{
    public function index(): void
    {
        $search = trim((string) ($this->query('q') ?? ''));
        $status = trim((string) ($this->query('status') ?? ''));

        $this->render('admin/comments/index', [
            'title' => 'Quản lý bình luận & đánh giá',
            'comments' => Comment::getAllForAdmin($search, $status),
            'search' => $search,
            'status' => $status,
        ], 'layouts/admin');
    }

    public function updateStatus(): void
    {
        $id = (int) ($this->input('id') ?? 0);
        $status = (string) ($this->input('status') ?? '');

        if ($id > 0 && Comment::updateStatus($id, $status) > 0) {
            $this->flash('success', 'Đã cập nhật trạng thái bình luận/đánh giá.');
        } else {
            $this->flash('error', 'Không thể cập nhật trạng thái.');
        }

        $this->redirect('/admin/comments');
    }

    public function delete(): void
    {
        $id = (int) ($this->input('id') ?? 0);

        if ($id > 0 && Comment::deleteComment($id)) {
            $this->flash('success', 'Đã xóa bình luận/đánh giá.');
        } else {
            $this->flash('error', 'Không thể xóa bình luận/đánh giá.');
        }

        $this->redirect('/admin/comments');
    }
}
