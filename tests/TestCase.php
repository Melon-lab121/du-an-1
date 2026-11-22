<?php

declare(strict_types=1);

/**
 * Bộ chạy test siêu nhẹ (không cần PHPUnit/Composer).
 */
final class T
{
    /** @var array<int, array{string, callable}> */
    private static array $tests = [];
    private static int $assertions = 0;

    public static function test(string $name, callable $fn): void
    {
        self::$tests[] = [$name, $fn];
    }

    public static function section(string $title): void
    {
        self::$tests[] = [$title, null];
    }

    public static function run(): int
    {
        $failed = 0;
        foreach (self::$tests as [$name, $fn]) {
            if ($fn === null) {
                echo "\n{$name}\n";
                continue;
            }
            try {
                $fn();
                echo "  \033[32m✓\033[0m {$name}\n";
            } catch (Throwable $e) {
                $failed++;
                echo "  \033[31m✗ {$name}\033[0m\n      " . $e->getMessage() . "\n      at " . basename($e->getFile()) . ':' . $e->getLine() . "\n";
            }
        }
        $total = count(array_filter(self::$tests, fn (array $t): bool => $t[1] !== null));
        echo "\n" . ($failed === 0 ? "\033[32mOK\033[0m" : "\033[31mFAILED\033[0m")
            . " — {$total} test, " . ($total - $failed) . ' passed, ' . $failed . ' failed, ' . self::$assertions . " assertions\n";
        return $failed === 0 ? 0 : 1;
    }

    public static function eq(mixed $expected, mixed $actual, string $message = ''): void
    {
        self::$assertions++;
        if ($expected !== $actual) {
            throw new RuntimeException(($message ? $message . ': ' : '') . 'mong đợi ' . var_export($expected, true) . ', nhận ' . var_export($actual, true));
        }
    }

    public static function true(mixed $value, string $message = 'giá trị không phải true'): void
    {
        self::eq(true, $value, $message);
    }

    public static function false(mixed $value, string $message = 'giá trị không phải false'): void
    {
        self::eq(false, $value, $message);
    }

    /** @param class-string<Throwable> $class */
    public static function throws(string $class, callable $fn, ?string $contains = null): void
    {
        self::$assertions++;
        try {
            $fn();
        } catch (Throwable $e) {
            if (!$e instanceof $class) {
                throw new RuntimeException("mong đợi {$class}, nhận " . $e::class . ': ' . $e->getMessage());
            }
            if ($contains !== null && !str_contains($e->getMessage(), $contains)) {
                throw new RuntimeException("thông báo lỗi không chứa '{$contains}': " . $e->getMessage());
            }
            return;
        }
        throw new RuntimeException("mong đợi exception {$class} nhưng không có");
    }
}
