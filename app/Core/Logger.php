<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Ghi log ra storage/logs/{channel}-YYYY-MM-DD.log
 */
final class Logger
{
    public function __construct(private string $dir)
    {
    }

    /** @param array<string, mixed> $context */
    public function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            $channel,
            strtoupper($level),
            $message,
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
        );
        @file_put_contents($this->dir . '/' . $channel . '-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public function info(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->log('info', $message, $context, $channel);
    }

    public function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->log('warning', $message, $context, $channel);
    }

    public function error(string $message, array $context = [], string $channel = 'app'): void
    {
        $this->log('error', $message, $context, $channel);
    }
}
