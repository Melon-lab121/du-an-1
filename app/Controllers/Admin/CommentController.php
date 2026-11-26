<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class CommentController extends Controller
{
    public function __construct(private Database $db) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $sql = 'SELECT c.id, c.product_id, c.user_id, c.rating, c.body, c.status, c.created_at,
                       p.name AS product_name, p.slug AS product_slug,
                       u.name AS user_name, u.email AS user_email
                FROM comments c
                LEFT JOIN products p ON p.id = c.product_id
                LEFT JOIN users u ON u.id = c.user_id
                WHERE 1=1';
        $params = [];

        if (in_array($status, ['approved', 'hidden'], true)) {
            $sql .= ' AND c.status = ?';
            $params[] = $status;
        }

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $sql .= ' AND (c.body LIKE ? OR p.name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= ' ORDER BY c.created_at DESC, c.id DESC';

        return $this->view('admin/comments/index', [
            'comments' => $this->db->select($sql, $params),
            'statusFilter' => $status,
            'q' => $q,
        ]);
    }

    public function toggleStatus(Request $request, int $id): Response
    {
        $row = $this->db->selectOne('SELECT id, status FROM comments WHERE id = ?', [$id]);
        if (is_array($row)) {
            $next = ($row['status'] ?? 'approved') === 'approved' ? 'hidden' : 'approved';
            $this->db->update(
                'comments',
                ['status' => $next, 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$id],
            );
            $this->flash('success', $next === 'hidden' ? 'Đã ẩn bình luận.' : 'Đã duyệt hiển thị bình luận.');
        } else {
            $this->flash('error', 'Không tìm thấy bình luận.');
        }

        return $this->redirect($this->adminCommentsTarget($request));
    }

    private function adminCommentsTarget(Request $request): string
    {
        $query = [];
        $status = (string) $request->input('status', $request->query('status', ''));
        $q = trim((string) $request->input('q', $request->query('q', '')));

        if (in_array($status, ['approved', 'hidden'], true)) {
            $query['status'] = $status;
        }
        if ($q !== '') {
            $query['q'] = $q;
        }

        return '/admin/comments' . ($query !== [] ? ('?' . http_build_query($query)) : '');
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->db->delete('comments', 'id = ?', [$id]);
        $this->flash('success', 'Đã xoá bình luận.');

        return $this->redirect($this->adminCommentsTarget($request));
    }
}
