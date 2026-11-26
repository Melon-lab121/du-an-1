<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Comment;
use App\Models\Product;
use App\Services\Auth;

final class CommentController extends Controller
{
    public function store(): void
    {
        $user = Auth::user();
        if (!$user) {
            $this->flash('error', 'Vui lòng đăng nhập để gửi đánh giá và bình luận.');
            $this->redirect('/login');
            return;
        }

        $productId = (int) ($this->input('product_id') ?? 0);
        $rating = (int) ($this->input('rating') ?? 5);
        $content = trim((string) ($this->input('content') ?? ''));

        $productModel = new Product();
        $product = $productModel->find($productId);
        if (!$product) {
            $this->flash('error', 'Sản phẩm không tồn tại.');
            $this->redirect('/products');
            return;
        }

        if ($rating < 1 || $rating > 5) {
            $this->flash('error', 'Số sao đánh giá phải từ 1 đến 5 sao.');
            $this->redirect('/product/' . ($product['slug'] ?? $productId));
            return;
        }

        if (mb_strlen($content) < 3) {
            $this->flash('error', 'Nội dung bình luận/đánh giá phải có ít nhất 3 ký tự.');
            $this->redirect('/product/' . ($product['slug'] ?? $productId));
            return;
        }

        Comment::createComment([
            'user_id' => (int) $user['id'],
            'product_id' => $productId,
            'rating' => $rating,
            'content' => $content,
            'status' => Comment::STATUS_APPROVED,
        ]);

        $this->flash('success', 'Cảm ơn bạn đã gửi đánh giá cho sản phẩm!');
        $this->redirect('/product/' . ($product['slug'] ?? $productId));
    }
}
