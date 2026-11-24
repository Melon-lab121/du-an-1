<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Template engine dùng PHP thuần, có layout + section + partial + namespace cho module.
 *
 * Trong template:
 *   <?php $this->extend('layouts/main', ['title' => 'Trang chủ']) ?>
 *   <?php $this->start('scripts') ?> ... <?php $this->stop() ?>
 *   <?= $this->insert('partials/product-card', ['product' => $p]) ?>
 * Trong layout:
 *   <?= $this->section('content') ?>
 */
final class View
{
    /** @var array<string, string> */
    private array $namespaces = [];
    /** @var array<string, mixed> */
    private array $shared = [];
    /** @var array<string, callable[]> */
    private array $composers = [];
    /** @var array<string, string> */
    private array $sections = [];
    /** @var string[] */
    private array $sectionStack = [];
    private ?string $layout = null;
    /** @var array<string, mixed> */
    private array $layoutData = [];

    public function __construct(private string $path)
    {
    }

    /** Đăng ký thư mục view cho module: 'blog::posts/index' */
    public function addNamespace(string $namespace, string $path): void
    {
        $this->namespaces[$namespace] = rtrim($path, '/\\');
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** Callback trả về mảng dữ liệu bổ sung mỗi khi render template này. */
    public function composer(string $template, callable $callback): void
    {
        $this->composers[$template][] = $callback;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = []): string
    {
        $this->sections = [];
        $this->sectionStack = [];
        $this->layout = null;

        $content = $this->renderFile($template, $data);

        // layout có thể kế thừa tiếp layout khác
        while ($this->layout !== null) {
            $layout = $this->layout;
            $layoutData = array_merge($data, $this->layoutData);
            $this->layout = null;
            $this->layoutData = [];
            $this->sections['content'] ??= $content;
            $content = $this->renderFile($layout, $layoutData);
        }

        return $content;
    }

    /** @param array<string, mixed> $data */
    public function extend(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function stop(): void
    {
        $name = array_pop($this->sectionStack) ?? throw new RuntimeException('stop() khi chưa start() section.');
        $this->sections[$name] = ($this->sections[$name] ?? '') . ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /** Render partial (không ảnh hưởng layout của template cha). @param array<string, mixed> $data */
    public function insert(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data);
    }

    public function exists(string $template): bool
    {
        return is_file($this->resolve($template));
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->resolve($template);
        if (!is_file($file)) {
            throw new RuntimeException("View [{$template}] không tồn tại ({$file}).");
        }

        foreach ($this->composers[$template] ?? [] as $composer) {
            $data = array_merge($data, (array) $composer($data));
        }
        $data = array_merge($this->shared, $data);

        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $data);
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }

    private function resolve(string $template): string
    {
        if (str_contains($template, '::')) {
            [$ns, $name] = explode('::', $template, 2);
            $base = $this->namespaces[$ns] ?? throw new RuntimeException("View namespace [{$ns}] chưa đăng ký.");
        } else {
            [$base, $name] = [$this->path, $template];
        }
        if (str_contains($name, '..')) {
            throw new RuntimeException('Tên view không hợp lệ.');
        }
        return $base . '/' . $name . '.php';
    }
}
