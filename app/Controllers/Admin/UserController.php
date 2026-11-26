<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Order;
use App\Services\Auth;

final class UserController extends Controller
{
    private const ALLOWED_ROLES = ['customer', 'staff', 'admin'];

    public function __construct(
        private Database $db,
        private Auth $auth,
    ) {}

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $role = trim((string) $request->query('role', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];

        if ($role !== '' && in_array($role, self::ALLOWED_ROLES, true)) {
            $where[] = 'u.role = ?';
            $params[] = $role;
        }

        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereSql = implode(' AND ', $where);
        $countRow = $this->db->selectOne("SELECT COUNT(*) AS cnt FROM users u WHERE {$whereSql}", $params);
        $total = (int) ($countRow['cnt'] ?? 0);

        $sql = "SELECT u.*,
                       COUNT(o.id) AS order_count,
                       COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total ELSE 0 END), 0) AS total_spent
                FROM users u
                LEFT JOIN orders o ON o.user_id = u.id
                WHERE {$whereSql}
                GROUP BY u.id
                ORDER BY u.id DESC
                LIMIT {$perPage} OFFSET {$offset}";

        $users = $this->db->select($sql, $params);

        return $this->view('admin/users/index', [
            'users' => $users,
            'q' => $q,
            'role' => $role,
            'allowedRoles' => self::ALLOWED_ROLES,
            'currentAdminId' => (int) ($this->auth->id() ?? 0),
            'pager' => [
                'data' => $users,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    public function orders(int $id): Response
    {
        $user = $this->db->selectOne('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!is_array($user)) {
            throw new HttpException(404, 'Không tìm thấy người dùng');
        }

        $orders = $this->db->select(
            'SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC',
            [$id],
        );

        foreach ($orders as &$ord) {
            $ord['items'] = Order::items((int) $ord['id']);
        }
        unset($ord);

        return $this->view('admin/users/orders', [
            'user' => $user,
            'orders' => $orders,
        ]);
    }

    public function updateRole(Request $request, int $id): Response
    {
        $user = $this->db->selectOne('SELECT id, role FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!is_array($user)) {
            throw new HttpException(404, 'Không tìm thấy người dùng');
        }

        $newRole = trim((string) $request->input('role', ''));
        if (!in_array($newRole, self::ALLOWED_ROLES, true)) {
            $this->flash('error', 'Vai trò không hợp lệ.');
            return $this->redirect('/admin/users');
        }

        if ($id === (int) ($this->auth->id() ?? 0) && $newRole !== 'admin') {
            $this->flash('error', 'Không thể tự hạ quyền của tài khoản đang đăng nhập.');
            return $this->redirect('/admin/users');
        }

        $this->db->update('users', [
            'role' => $newRole,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        $this->flash('success', 'Đã cập nhật vai trò người dùng.');
        return $this->redirect('/admin/users');
    }

    public function destroy(int $id): Response
    {
        if ($id === (int) ($this->auth->id() ?? 0)) {
            $this->flash('error', 'Không thể xóa tài khoản quản trị đang đăng nhập.');
            return $this->redirect('/admin/users');
        }

        $user = $this->db->selectOne('SELECT id FROM users WHERE id = ? LIMIT 1', [$id]);
        if (!is_array($user)) {
            throw new HttpException(404, 'Không tìm thấy người dùng');
        }

        $this->db->delete('users', 'id = ?', [$id]);
        $this->flash('success', 'Đã xóa người dùng.');
        return $this->redirect('/admin/users');
    }
}