<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\QueryBuilder;

final class Comment extends Model
{
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PENDING = 'pending';
    public const STATUS_HIDDEN = 'hidden';

    protected static string $table = 'comments';
    protected static array $fillable = [
        'user_id', 'product_id', 'rating', 'content', 'status', 'date',
    ];

    public static function approvedByProduct(int $productId): QueryBuilder
    {
        return self::query()
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->where('comments.product_id', $productId)
            ->where('comments.status', self::STATUS_APPROVED)
            ->selectRaw('comments.*, users.name AS user_name, users.email AS user_email')
            ->orderBy('comments.created_at', 'DESC')
            ->orderBy('comments.id', 'DESC');
    }

    public static function ratingSummary(int $productId): array
    {
        $row = self::db()->selectOne(
            'SELECT COUNT(*) AS total_reviews,
                    COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating
             FROM comments
             WHERE product_id = ? AND status = ?',
            [$productId, self::STATUS_APPROVED],
        );

        return [
            'total_reviews' => (int) ($row['total_reviews'] ?? 0),
            'avg_rating' => (float) ($row['avg_rating'] ?? 0),
        ];
    }

    public static function forAdmin(string $search = '', string $status = ''): QueryBuilder
    {
        $query = self::query()
            ->join('users', 'comments.user_id', '=', 'users.id')
            ->join('products', 'comments.product_id', '=', 'products.id')
            ->selectRaw(
                'comments.*, users.name AS user_name, users.email AS user_email,
                 products.name AS product_name, products.sku AS product_sku',
            );

        if (in_array($status, [self::STATUS_APPROVED, self::STATUS_PENDING, self::STATUS_HIDDEN], true)) {
            $query->where('comments.status', $status);
        }
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $query->whereRaw(
                'comments.content LIKE ? OR users.name LIKE ? OR users.email LIKE ? OR products.name LIKE ?',
                [$like, $like, $like, $like],
            );
        }

        return $query->orderBy('comments.created_at', 'DESC')->orderBy('comments.id', 'DESC');
    }

    public static function updateStatus(int $id, string $status): int
    {
        if (!in_array($status, [self::STATUS_APPROVED, self::STATUS_PENDING, self::STATUS_HIDDEN], true)) {
            return 0;
        }
        return self::updateById($id, ['status' => $status]);
    }

    // Giữ các tên phương thức cũ để tương thích file controller đang dùng.
    public static function getApprovedByProduct(int $productId): array
    {
        return self::approvedByProduct($productId)->get();
    }

    public static function getRatingSummary(int $productId): array
    {
        return self::ratingSummary($productId);
    }

    public static function getAllForAdmin(string $search = '', string $status = ''): array
    {
        return self::forAdmin($search, $status)->get();
    }

    public static function createComment(array $data): int
    {
        $data['date'] ??= date('Y-m-d');
        $data['status'] ??= self::STATUS_APPROVED;
        $data['rating'] = (int) ($data['rating'] ?? 5);
        $data['content'] = trim((string) ($data['content'] ?? ''));
        return self::create($data);
    }

    public static function deleteComment(int $id): bool
    {
        return self::deleteById($id) > 0;
    }
}