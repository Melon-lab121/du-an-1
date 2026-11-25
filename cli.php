<?php

declare(strict_types=1);

/*
 * Công cụ dòng lệnh. Với XAMPP:
 *   C:\xampp\php\php.exe cli.php install      Tạo database + bảng + dữ liệu mẫu
 *   C:\xampp\php\php.exe cli.php migrate      Chạy migration mới
 *   C:\xampp\php\php.exe cli.php seed         Thêm dữ liệu mẫu
 *   C:\xampp\php\php.exe cli.php fresh        XOÁ toàn bộ bảng rồi install lại (dev)
 *   C:\xampp\php\php.exe cli.php routes       Liệt kê route
 *   C:\xampp\php\php.exe cli.php orders:autocomplete   Tự hoàn thành đơn "Đã giao" quá 7 ngày (đặt lịch chạy mỗi ngày)
 *   C:\xampp\php\php.exe cli.php serve [port] Chạy server PHP tích hợp (không cần Apache)
 *   C:\xampp\php\php.exe cli.php make:module Blog   Tạo khung module mới
 */

use App\Core\Database;
use App\Services\OrderService;
use Database\Migrator;
use Database\Seeder;

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

define('BASE_PATH', __DIR__);
/** @var App\Core\App $app */
$app = require BASE_PATH . '/bootstrap/app.php';
$app->registerErrorHandling();

$command = $argv[1] ?? 'help';
$out = static fn (string $line) => fwrite(STDOUT, $line . PHP_EOL);

$migrator = static fn (): Migrator => new Migrator($app->get(Database::class), [
    BASE_PATH . '/database/migrations/*.sql',
    BASE_PATH . '/modules/*/migrations/*.sql',
]);

$createDatabase = static function () use ($app, $out): void {
    $cfg = (array) $app->config()->get('database');
    if (($cfg['driver'] ?? 'mysql') !== 'mysql') {
        return;
    }
    $name = (string) $cfg['database'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Tên database không hợp lệ: ' . $name);
    }
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], (int) $cfg['port']),
        (string) $cfg['username'],
        (string) $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $out("Database `{$name}` sẵn sàng.");
};

try {
    switch ($command) {
        case 'install':
            $createDatabase();
            $done = $migrator()->migrate($out);
            $out($done === [] ? 'Không có migration mới.' : 'Đã chạy ' . count($done) . ' migration.');
            (new Seeder($app->get(Database::class)))->run($out);
            $out('Hoàn tất! Mở ' . $app->config()->get('app.url') . ' để xem website.');
            break;

        case 'migrate':
            $done = $migrator()->migrate($out);
            $out($done === [] ? 'Không có migration mới.' : 'Đã chạy ' . count($done) . ' migration.');
            break;

        case 'seed':
            (new Seeder($app->get(Database::class)))->run($out);
            break;

        case 'fresh':
            if (!in_array('--force', $argv, true)) {
                $out('Lệnh này XOÁ TOÀN BỘ dữ liệu. Chạy lại với --force để xác nhận.');
                exit(1);
            }
            $createDatabase();
            $migrator()->dropAllTables();
            $migrator()->migrate($out);
            (new Seeder($app->get(Database::class)))->run($out);
            break;

        case 'routes':
            $app->boot();
            foreach ($app->router()->routes() as $route) {
                $out(sprintf('%-8s %-55s %s', implode('|', $route->methods), $route->uri, $route->name ?? ''));
            }
            break;

        case 'orders:autocomplete':
            $app->boot();
            $count = $app->get(OrderService::class)->autoCompleteDelivered();
            $out("Đã tự động hoàn thành {$count} đơn (đơn \"Đã giao\" quá 7 ngày).");
            break;

        case 'serve':
            $port = (int) ($argv[2] ?? 8000);
            $out("Server chạy tại http://localhost:{$port}  (Ctrl+C để dừng)");
            $out("Lưu ý: đặt APP_URL=http://localhost:{$port} trong .env để success/error/cancel URL gửi sang SePay đúng.");
            passthru(escapeshellarg(PHP_BINARY) . " -S localhost:{$port} -t " . escapeshellarg(BASE_PATH . '/public'));
            break;

        case 'make:module':
            $name = $argv[2] ?? '';
            if (!preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
                $out('Cách dùng: php cli.php make:module TenModule  (PascalCase)');
                exit(1);
            }
            $dir = BASE_PATH . '/modules/' . $name;
            if (is_dir($dir)) {
                $out("Module {$name} đã tồn tại.");
                exit(1);
            }
            $slug = strtolower($name);
            mkdir($dir . '/views', 0775, true);
            mkdir($dir . '/migrations', 0775, true);
            file_put_contents($dir . '/Module.php', <<<PHP
<?php

declare(strict_types=1);

namespace Modules\\{$name};

use App\\Core\\Router;
use App\\Core\\ServiceProvider;
use App\\Core\\View;

final class Module extends ServiceProvider
{
    public function register(): void
    {
        \$this->app->get(View::class)->addNamespace('{$slug}', __DIR__ . '/views');
    }

    public function boot(): void
    {
        \$this->app->router()->get('/{$slug}', fn () => view('{$slug}::index'))->name('{$slug}.index');
    }
}

PHP);
            file_put_contents($dir . '/views/index.php', "<?php \$this->extend('layouts/main', ['title' => '{$name}']) ?>\n<section class=\"container section\"><h1>Module {$name}</h1></section>\n");
            $out("Đã tạo modules/{$name}. Thêm '{$name}' vào config/modules.php để bật.");
            break;

        default:
            $out('Lệnh: install | migrate | seed | fresh --force | routes | orders:autocomplete | serve [port] | make:module Ten');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Lỗi: ' . $e->getMessage() . PHP_EOL);
    if ($e instanceof PDOException && str_contains($e->getMessage(), '2002')) {
        fwrite(STDERR, 'Gợi ý: hãy bật MySQL trong XAMPP Control Panel.' . PHP_EOL);
    }
    exit(1);
}
