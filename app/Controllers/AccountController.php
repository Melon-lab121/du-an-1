<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Auth;

final class AccountController extends Controller
{
    public function __construct(
        private Database $db,
        private Auth $auth,
    ) {}

    public function show(): Response
    {
        $authUser = $this->auth->user();
        if (!is_array($authUser) || empty($authUser['id'])) {
            $this->flash('error', 'Vui lòng đăng nhập để xem thông tin tài khoản.');
            return $this->redirect(url('/login'));
        }

        $userId = (int) $authUser['id'];
        $user = $this->db->selectOne(
            'SELECT id, name, email, phone, address, role, created_at FROM users WHERE id = ? LIMIT 1',
            [$userId],
        ) ?? $authUser;

        $orders = $this->loadOrderHistory($userId);

        return $this->view('account/show', [
            'user' => $user,
            'orders' => $orders,
        ]);
    }

    public function update(Request $request): Response
    {
        $authUser = $this->auth->user();
        if (!is_array($authUser) || empty($authUser['id'])) {
            $this->flash('error', 'Vui lòng đăng nhập để cập nhật thông tin tài khoản.');
            return $this->redirect(url('/login'));
        }

        $userId = (int) $authUser['id'];

        $name = trim((string) ($request->input('name') ?? ''));
        $phone = trim((string) ($request->input('phone') ?? ''));
        $address = trim((string) ($request->input('address') ?? ''));

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = 'Họ tên bắt buộc và tối đa 120 ký tự.';
        }
        if ($phone !== '' && mb_strlen($phone) > 30) {
            $errors['phone'] = 'Số điện thoại tối đa 30 ký tự.';
        }
        if (mb_strlen($address) > 255) {
            $errors['address'] = 'Địa chỉ tối đa 255 ký tự.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors, $request->all());
        }

        $this->db->update(
            'users',
            [
                'name' => $name,
                'phone' => $phone === '' ? null : $phone,
                'address' => $address === '' ? null : $address,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'id = ?',
            [$userId],
        );

        $this->flash('success', 'Đã cập nhật thông tin tài khoản.');
        return $this->redirect(url('/account'));
    }

    /**
     * Load orders placed by the user together with purchased product items.
     * Adjust table/column names if your order schema uses different names.
     *
     * @return array<int, array<string, mixed>>
     */
    private function loadOrderHistory(int $userId): array
    {
        $orders = $this->db->select(
            'SELECT id, code, status, total, created_at
             FROM orders
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC',
            [$userId],
        );

        if ($orders === []) {
            return [];
        }

        $orderIds = array_map(static fn(array $o): int => (int) $o['id'], $orders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        $items = $this->db->select(
            "SELECT oi.order_id,
                    oi.product_id,
                    oi.quantity,
                    oi.price,
                    p.name AS product_name,
                    p.slug AS product_slug,
                    p.image AS product_image
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id IN ($placeholders)
             ORDER BY oi.id ASC",
            $orderIds,
        );

        $itemsByOrder = [];
        foreach ($items as $item) {
            $oid = (int) $item['order_id'];
            $itemsByOrder[$oid][] = $item;
        }

        foreach ($orders as &$order) {
            $oid = (int) $order['id'];
            $order['items'] = $itemsByOrder[$oid] ?? [];
        }
        unset($order);

        return $orders;
    }
}