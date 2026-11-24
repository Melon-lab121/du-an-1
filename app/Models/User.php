<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['name', 'email', 'password', 'phone', 'address', 'role'];

    /** @param array<string, mixed> $data */
    public static function register(array $data): int
    {
        return self::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'] ?? 'customer',
        ]);
    }
}
