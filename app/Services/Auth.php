<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

final class Auth
{
    private const SESSION_KEY = 'auth_user_id';

    /** @var array<string, mixed>|null */
    private ?array $user = null;
    private bool $resolved = false;

    public function __construct(private Session $session, private Database $db)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->db->table('users')->where('email', mb_strtolower(trim($email)))->first();

        // Luôn chạy password_verify để thời gian phản hồi không lộ email có tồn tại hay không
        $hash = $user['password'] ?? '$2y$10$Cz3UDPN3r8tmxxrx.DT9D.hzZp1CkwR/PvTr2OAl/p/prDUsFXEJG';
        if (!password_verify($password, $hash) || $user === null) {
            return false;
        }

        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$user['id']]);
        }

        $this->login($user);
        return true;
    }

    /** @param array<string, mixed> $user */
    public function login(array $user): void
    {
        $this->session->regenerate(); // chống session fixation
        $this->session->set(self::SESSION_KEY, (int) $user['id']);
        $this->user = $this->sanitize($user);
        $this->resolved = true;
    }

    public function logout(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->session->regenerate();
        $this->user = null;
        $this->resolved = true;
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $id = $this->session->get(self::SESSION_KEY);
            if (is_int($id)) {
                $row = $this->db->table('users')->find($id);
                $this->user = $row === null ? null : $this->sanitize($row);
            }
        }
        return $this->user;
    }

    public function id(): ?int
    {
        return isset($this->user()['id']) ? (int) $this->user()['id'] : null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? null) === 'admin';
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    private function sanitize(array $user): array
    {
        unset($user['password']);
        return $user;
    }
}
