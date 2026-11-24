<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, string[]> $errors
     * @param array<string, mixed> $old
     */
    public function __construct(public readonly array $errors, public readonly array $old = [])
    {
        parent::__construct('Dữ liệu không hợp lệ');
    }

    /** @param array<string, string|string[]> $errors */
    public static function withMessages(array $errors, array $old = []): self
    {
        return new self(array_map(fn ($m) => (array) $m, $errors), $old);
    }
}
