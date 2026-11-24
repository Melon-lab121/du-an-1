<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Hệ thống Event + Filter (hook) để mở rộng tính năng mà không sửa code lõi.
 *
 *   Event:  $events->listen('order.paid', fn (array $p) => ...);   $events->dispatch('order.paid', ['order' => $order]);
 *   Filter: $events->listen('cart.discount', fn (int $v, array $ctx) => $v + 10000);
 *           $discount = $events->filter('cart.discount', 0, ['subtotal' => ...]);
 */
final class Events
{
    /** @var array<string, array<int, array{priority: int, listener: callable}>> */
    private array $listeners = [];

    public function listen(string $event, callable $listener, int $priority = 10): void
    {
        $this->listeners[$event][] = ['priority' => $priority, 'listener' => $listener];
        usort($this->listeners[$event], fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(string $event, array $payload = []): void
    {
        foreach ($this->listeners[$event] ?? [] as $item) {
            ($item['listener'])($payload);
        }
    }

    /** @param array<string, mixed> $context */
    public function filter(string $name, mixed $value, array $context = []): mixed
    {
        foreach ($this->listeners[$name] ?? [] as $item) {
            $value = ($item['listener'])($value, $context);
        }
        return $value;
    }

    public function hasListeners(string $event): bool
    {
        return !empty($this->listeners[$event]);
    }
}
