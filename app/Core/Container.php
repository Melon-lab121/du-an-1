<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Dependency Injection container nhỏ gọn, hỗ trợ autowiring theo type-hint của constructor.
 */
final class Container
{
    /** @var array<string, callable> */
    private array $factories = [];
    /** @var array<string, bool> */
    private array $shared = [];
    /** @var array<string, mixed> */
    private array $instances = [];

    /** Đăng ký service dùng chung (tạo 1 lần). */
    public function singleton(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = true;
        unset($this->instances[$id]);
    }

    /** Đăng ký service tạo mới mỗi lần get(). */
    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = false;
        unset($this->instances[$id]);
    }

    public function instance(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || isset($this->factories[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $object = ($this->factories[$id])($this);
            if ($this->shared[$id]) {
                $this->instances[$id] = $object;
            }
            return $object;
        }

        if (class_exists($id)) {
            return $this->make($id);
        }

        throw new RuntimeException("Service [{$id}] chưa được đăng ký trong container.");
    }

    /**
     * Tạo object và tự inject các dependency theo type-hint.
     *
     * @param array<string, mixed> $params tham số truyền tay (theo tên)
     */
    public function make(string $class, array $params = []): object
    {
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Không thể khởi tạo [{$class}].");
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $params)) {
                $args[] = $params[$name];
                continue;
            }

            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->get($type->getName());
                continue;
            }

            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            throw new RuntimeException("Không resolve được tham số \${$name} của [{$class}].");
        }

        return $reflection->newInstanceArgs($args);
    }
}
