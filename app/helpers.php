<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth;
use App\Services\CartService;
use App\Services\VoucherService;

/**
 * Hàm tiện ích toàn cục. Chỉ khai báo khi chưa tồn tại để module có thể override.
 */

if (!function_exists('app')) {
    function app(?string $id = null): mixed
    {
        $app = App::instance();
        return $id === null ? $app : $app->get($id);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app(Config::class)->get($key, $default);
    }
}

if (!function_exists('e')) {
    /** Escape HTML — luôn dùng khi in dữ liệu ra view. */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** @param array<string, scalar> $query */
    function url(string $path = '/', array $query = []): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $url = App::instance()->urlBasePath() . '/' . ltrim($path, '/');
        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}

if (!function_exists('absolute_url')) {
    /** URL tuyệt đối dựa trên request hiện tại (Cloudflare/domain) hoặc fallback APP_URL. Giữ nguyên nếu đã là URL tuyệt đối. */
    function absolute_url(string $path = '/'): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $app = App::instance();
        if (PHP_SAPI !== 'cli') {
            try {
                $req = $app->get(Request::class);
                $host = trim($req->host());
                if ($host !== '') {
                    $scheme = $req->isSecure() ? 'https' : 'http';
                    return $scheme . '://' . $host . $app->urlBasePath() . '/' . ltrim($path, '/');
                }
            } catch (Throwable) {
                // Request chưa bind trong container => dùng fallback APP_URL bên dưới
            }
        }

        $parts = parse_url((string) config('app.url', 'http://localhost'));
        $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return $origin . rtrim($parts['path'] ?? '', '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $file = App::instance()->basePath('public/' . ltrim($path, '/'));
        $version = is_file($file) ? '?v=' . filemtime($file) : '';
        return url($path) . $version;
    }
}

if (!function_exists('route')) {
    /** @param array<string, scalar> $params */
    function route(string $name, array $params = []): string
    {
        return url(app(Router::class)->url($name, $params));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): Response
    {
        return Response::redirect(url($path));
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        $container = App::instance()->container();
        return $container->has(Request::class) ? $container->get(Request::class) : Request::create('GET', '/');
    }
}

if (!function_exists('back_url')) {
    /** URL trang trước (chỉ chấp nhận cùng host để tránh open redirect). */
    function back_url(string $fallback = '/'): string
    {
        $request = request();
        $referer = $request->referer();
        if ($referer !== null && parse_url($referer, PHP_URL_HOST) === parse_url('http://' . $request->host(), PHP_URL_HOST)) {
            return $referer;
        }
        return url($fallback);
    }
}

if (!function_exists('view')) {
    /** @param array<string, mixed> $data */
    function view(string $template, array $data = []): string
    {
        return app(View::class)->render($template, $data);
    }
}

if (!function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        $session = app(Session::class);
        return $key === null ? $session : $session->get($key, $default);
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return app(Session::class)->getFlash($key, $default);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return app(Csrf::class)->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return app(Csrf::class)->field();
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $old = app(Session::class)->getFlash('_old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    /** @return array<string, string[]> */
    function errors(): array
    {
        return app(Session::class)->getFlash('_errors', []);
    }
}

if (!function_exists('error')) {
    /** Lỗi đầu tiên của 1 trường (hoặc null). */
    function error(string $field): ?string
    {
        return errors()[$field][0] ?? null;
    }
}

if (!function_exists('auth')) {
    function auth(): Auth
    {
        return app(Auth::class);
    }
}

if (!function_exists('cart')) {
    function cart(): CartService
    {
        return app(CartService::class);
    }
}

if (!function_exists('voucher')) {
    function voucher(): VoucherService
    {
        return app(VoucherService::class);
    }
}

if (!function_exists('money')) {
    /** 1250000 => "1.250.000 ₫" */
    function money(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' ₫';
    }
}

if (!function_exists('str_ascii')) {
    /** Bỏ dấu tiếng Việt, giữ nguyên hoa/thường: "Đơn hàng" => "Don hang" */
    function str_ascii(string $text): string
    {
        static $search = null, $replace = null;
        if ($search === null) {
            $map = [
                'a' => 'áàảãạăắằẳẵặâấầẩẫậ', 'd' => 'đ', 'e' => 'éèẻẽẹêếềểễệ', 'i' => 'íìỉĩị',
                'o' => 'óòỏõọôốồổỗộơớờởỡợ', 'u' => 'úùủũụưứừửữự', 'y' => 'ýỳỷỹỵ',
            ];
            $search = $replace = [];
            foreach ($map as $ascii => $chars) {
                foreach (mb_str_split($chars) as $char) {
                    array_push($search, $char, mb_strtoupper($char, 'UTF-8'));
                    array_push($replace, $ascii, strtoupper($ascii));
                }
            }
        }
        return str_replace($search, $replace, $text);
    }
}

if (!function_exists('str_slug')) {
    /** Tạo slug, hỗ trợ tiếng Việt: "Áo thun Nữ" => "ao-thun-nu" */
    function str_slug(string $text): string
    {
        $text = strtolower(str_ascii($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-');
    }
}

if (!function_exists('str_limit')) {
    function str_limit(?string $text, int $limit = 100, string $end = '…'): string
    {
        $text = (string) $text;
        return mb_strlen($text) <= $limit ? $text : rtrim(mb_substr($text, 0, $limit)) . $end;
    }
}

if (!function_exists('is_active')) {
    /** Trả về 'active' nếu path hiện tại khớp (hỗ trợ dấu * ở cuối). */
    function is_active(string $pattern, string $class = 'active'): string
    {
        $path = request()->path();
        $pattern = '/' . trim($pattern, '/');
        $match = str_ends_with($pattern, '*')
            ? str_starts_with($path, rtrim(substr($pattern, 0, -1), '/'))
            : $path === $pattern;
        return $match ? $class : '';
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        echo '<pre style="background:#111;color:#0f0;padding:12px;font-size:13px;">';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
        exit(1);
    }
}
