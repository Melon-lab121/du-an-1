<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use DomainException;

/** Upload ảnh an toàn: kiểm tra lỗi, dung lượng, nội dung thật là ảnh, đặt tên ngẫu nhiên. */
final class Uploader
{
    private const MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(private App $app)
    {
    }

    /**
     * @param array<string, mixed> $file phần tử của $_FILES
     * @return string đường dẫn tương đối từ public/, vd "uploads/products/ab12.jpg"
     */
    public function image(array $file, string $folder = 'images', int $maxKb = 2048): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new DomainException('Tải ảnh lên thất bại (mã lỗi ' . ($file['error'] ?? '?') . ').');
        }
        if ((int) $file['size'] > $maxKb * 1024) {
            throw new DomainException("Ảnh vượt quá {$maxKb}KB.");
        }

        $info = @getimagesize((string) $file['tmp_name']);
        $ext = self::MIME_EXT[$info['mime'] ?? ''] ?? null;
        if ($info === false || $ext === null) {
            throw new DomainException('File không phải ảnh hợp lệ (chỉ nhận JPG, PNG, WEBP, GIF).');
        }

        $folder = trim(preg_replace('/[^a-z0-9_\/-]/i', '', $folder) ?? 'images', '/');
        $relativeDir = 'uploads/' . $folder . '/' . date('Y/m');
        $dir = $this->app->basePath('public/' . $relativeDir);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new DomainException('Không tạo được thư mục upload.');
        }

        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $target = $dir . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new DomainException('Không lưu được file ảnh.');
        }

        return $relativeDir . '/' . $name;
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || !str_starts_with($relativePath, 'uploads/') || str_contains($relativePath, '..')) {
            return;
        }
        $file = $this->app->basePath('public/' . $relativePath);
        if (is_file($file)) {
            @unlink($file);
        }
    }
}
