<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function validate(?string $token): bool
    {
        $expected = $this->session->get(self::KEY);
        return is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    public function field(): string
    {
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($this->token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
