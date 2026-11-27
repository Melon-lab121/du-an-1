<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Category;
use App\Models\Product;
use App\Services\Auth;
use App\Services\VariantService;
use DomainException;

final class ProductController extends Controller
{
    public function __construct(
        private Config $config,
        private VariantService $variants,
        private Database $db,
        private Auth $auth,
    ) {}

    public function index(Request $request, ?string $slug = null): Response
    {
        $categoryParam = trim((string) ($slug ?? $request->query('category', '')));
        $category = null;

        if ($categoryParam !== '') {
            $category = Category::findBy('slug', $categoryParam);
            if ($category === null && ctype_digit($categoryParam)) {
                $category = Category::find((int) $categoryParam);
            }
            if ($category === null) {
                throw new HttpException(404, 'Danh mục không tồn tại');
            }
        }

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => $category['slug'] ?? ($category['id'] ?? ''),
            'category_id' => isset($category['id']) ? (int) $category['id'] : null,
            'sort' => (string) $request->query('sort', 'newest'),
            'min_price' => (int) $request->query('min_price', 0) ?: null,
            'max_price' => (int) $request->query('max_price', 0) ?: null,
        ];

        $pager = Product::search($filters)->paginate(
            (int) $this->config->get('shop.per_page', 12),
            max(1, (int) $request->query('page', 1)),
        );

        return $this->view('products/index', [
            'pager' => $pager,
            'filters' => $filters,
            'category' => $category,
            'categories' => Category::withProductCount(),
        ]);
    }

    public function category(Request $request, string $slug): Response
    {
        return $this->index($request, $slug);
    }

    public function storeReview(Request $request, string $slug): Response
    {
        $user = $this->resolveCurrentUser();
        if ($user === null || empty($user['id'])) {
            $this->flash('error', 'Vui lòng đăng nhập để gửi đánh giá và bình luận.');
            return $this->redirect(url('/login'));
        }

        $product = Product::query()->where('slug', $slug)->whereNull('deleted_at')->first()
            ?? throw new HttpException(404);

        if (!$this->hasReceivedProduct((int) $user['id'], (int) $product['id'])) {
            $this->flash('error', 'Chỉ khách hàng đã nhận hàng (đơn hàng Hoàn thành) mới có thể đánh giá sản phẩm này.');
            return $this->redirect(url('/product/' . $slug . '#product-comments'));
        }

        $rating = (int) ($request->input('rating') ?? 5);
        if ($rating < 1 || $rating > 5) {
            $rating = 5;
        }

        $body = trim((string) ($request->input('body') ?? ''));
        if ($body === '' || mb_strlen($body) > 1000) {
            $this->flash('error', 'Nội dung bình luận bắt buộc và tối đa 1000 ký tự.');
            return $this->redirect('/product/' . $slug . '#product-comments');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('comments', [
            'product_id' => (int) $product['id'],
            'user_id' => (int) $user['id'],
            'rating' => $rating,
            'body' => $body,
            'status' => 'approved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->flash('success', 'Cảm ơn bạn đã gửi đánh giá và bình luận.');
        return $this->redirect('/product/' . $slug . '#product-comments');
    }

    public function show(string $slug): Response
    {
        $product = Product::findActiveBySlug($slug);
        if ($product === null && ctype_digit($slug)) {
            $candidate = Product::find((int) $slug);
            if ($candidate !== null && empty($candidate['deleted_at'])) {
                $product = $candidate;
            }
        }

        if ($product === null) {
            throw new HttpException(404, 'Sản phẩm không tồn tại');
        }

        $productId = (int) $product['id'];
        $categoryId = !empty($product['category_id']) ? (int) $product['category_id'] : null;

        $related = $categoryId === null ? [] : Product::active()
            ->where('category_id', $categoryId)
            ->where('id', '!=', $productId)
            ->latest()
            ->limit(4)
            ->get();

        $comments = $this->loadProductComments($productId);
        $avgRating = $comments === []
            ? 0.0
            : array_sum(array_map(static fn(array $r): int => (int) ($r['rating'] ?? 5), $comments)) / count($comments);

        $currentUser = $this->resolveCurrentUser();
        $isLoggedIn = $currentUser !== null && !empty($currentUser['id']);
        $canReview = $isLoggedIn && $this->hasReceivedProduct((int) $currentUser['id'], $productId);

        return $this->view('products/show', [
            'product' => $product,
            'category' => $categoryId ? Category::find($categoryId) : null,
            'related' => $related,
            'variants' => !empty($product['has_variants']) ? $this->variants->forStorefront($productId) : null,
            'comments' => $comments,
            'avgRating' => $avgRating,
            'isLoggedIn' => $isLoggedIn,
            'canReview' => $canReview,
        ]);
    }

    /**
     * Lấy danh sách đánh giá & bình luận đang hiển thị của sản phẩm từ bảng `comments`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadProductComments(int $productId): array
    {
        return $this->db->select(
            'SELECT c.id, c.product_id, c.user_id, c.rating, c.body, c.status, c.created_at, u.name AS user_name
             FROM comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.product_id = ? AND c.status = ?
             ORDER BY c.created_at DESC, c.id DESC',
            [$productId, 'approved'],
        );
    }

    private function resolveCurrentUser(): ?array
    {
        try {
            if (isset($this->auth) && method_exists($this->auth, 'user')) {
                $user = $this->auth->user();
                if (is_array($user) && !empty($user['id'])) {
                    return $user;
                }
            }
        } catch (\Throwable) {
            // Fall through to session lookup
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        foreach (['auth_user', 'user', 'customer', 'account'] as $key) {
            if (!empty($_SESSION[$key]) && is_array($_SESSION[$key]) && !empty($_SESSION[$key]['id'])) {
                return $_SESSION[$key];
            }
        }

        if (!empty($_SESSION['user_id'])) {
            $row = $this->db->selectOne('SELECT id, name, email, role FROM users WHERE id = ?', [(int) $_SESSION['user_id']]);
            if (is_array($row) && !empty($row['id'])) {
                return $row;
            }
            return ['id' => (int) $_SESSION['user_id']];
        }

        return null;
    }

    private function hasReceivedProduct(int $userId, int $productId): bool
    {
        if ($userId <= 0 || $productId <= 0) {
            return false;
        }

        $row = $this->db->selectOne(
            "SELECT o.id
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ?
               AND oi.product_id = ?
               AND o.status IN ('completed', 'delivered')
             LIMIT 1",
            [$userId, $productId],
        );

        return is_array($row) && !empty($row['id']);
    }
}
