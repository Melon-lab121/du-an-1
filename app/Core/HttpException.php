<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

class HttpException extends RuntimeException
{
    private const MESSAGES = [
        400 => 'Yêu cầu không hợp lệ',
        401 => 'Bạn cần đăng nhập',
        403 => 'Bạn không có quyền truy cập',
        404 => 'Không tìm thấy trang',
        405 => 'Phương thức không được hỗ trợ',
        419 => 'Phiên làm việc đã hết hạn, vui lòng tải lại trang',
        422 => 'Dữ liệu không hợp lệ',
        429 => 'Quá nhiều yêu cầu',
        500 => 'Đã có lỗi xảy ra',
        503 => 'Hệ thống đang bảo trì',
    ];

    public function __construct(public readonly int $status, string $message = '', ?Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : (self::MESSAGES[$status] ?? 'Lỗi'), $status, $previous);
    }
}
