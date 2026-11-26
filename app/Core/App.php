<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use RuntimeException;
use Throwable;

/**
 * Kernel của ứng dụng: giữ container, nạp provider/module/route và xử lý vòng đời request.
 */
final class App
{
    private static ?self $instance = null;
    private Container $container;
    /** @var ServiceProvider[] */
    private array $providers = [];
    private bool $booted = false;
    private string $urlBasePath;

    public function __construct(private string $basePath)
    {
        self::$instance = $this;
        $this->basePath = rtrim($basePath, '/\\');
        $this->container = $container = new Container();

        $config = new Config($this->basePath . '/config');
        $container->instance(self::class, $this);
        $container->instance(Container::class, $container);
        $container->instance(Config::class, $config);

        date_default_timezone_set((string) $config->get('app.timezone', 'Asia/Ho_Chi_Minh'));
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        $this->urlBasePath = rtrim((string) (parse_url((string) $config->get('app.url', ''), PHP_URL_PATH) ?? ''), '/');
        $this->registerCoreServices();
        $this->registerProviders();
    }

    public static function instance(): self
    {
        return self::$instance ?? throw new RuntimeException('Ứng dụng chưa được khởi tạo.');
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path === '' ? '' : '/' . ltrim($path, '/\\'));
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function get(string $id): mixed
    {
        return $this->container->get($id);
    }

    public function config(): Config
    {
        return $this->container->get(Config::class);
    }

    public function router(): Router
    {
        return $this->container->get(Router::class);
    }

    public function events(): Events
    {
        return $this->container->get(Events::class);
    }

    public function isDebug(): bool
    {
        return (bool) $this->config()->get('app.debug', false);
    }

    /** Base path dùng để sinh URL (vd: "/base"), tự nhận diện theo request hiện tại. */
    public function urlBasePath(): string
    {
        return $this->urlBasePath;
    }

    public function registerErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->isDebug() ? '1' : '0');
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }

    private function registerCoreServices(): void
    {
        $c = $this->container;
        $c->singleton(Database::class, fn () => new Database((array) $this->config()->get('database', [])));
        $c->singleton(Session::class, fn () => new Session((array) $this->config()->get('app.session', [])));
        $c->singleton(Events::class, fn () => new Events());
        $c->singleton(Logger::class, fn () => new Logger($this->basePath('storage/logs')));
        $c->singleton(View::class, fn () => new View($this->basePath('views')));
        $c->singleton(Csrf::class, fn (Container $c) => new Csrf($c->get(Session::class)));
        $c->singleton(Validator::class, fn (Container $c) => new Validator($c->get(Database::class)));
        $c->singleton(Router::class, function (Container $c): Router {
            $router = new Router($c);
            foreach ((array) $this->config()->get('app.middleware_aliases', []) as $alias => $class) {
                $router->aliasMiddleware($alias, $class);
            }
            $router->setGlobalMiddleware((array) $this->config()->get('app.middleware', []));
            return $router;
        });
    }

    /** Provider trong config/app.php + module bật trong config/modules.php */
    private function registerProviders(): void
    {
        $classes = (array) $this->config()->get('app.providers', []);
        foreach ((array) $this->config()->get('modules', []) as $module) {
            $classes[] = 'Modules\\' . $module . '\\Module';
        }

        foreach ($classes as $class) {
            if (!class_exists($class)) {
                throw new RuntimeException("Provider/Module [{$class}] không tồn tại.");
            }
            $provider = new $class($this);
            $provider->register();
            $this->providers[] = $provider;
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        $router = $this->router();
        foreach (glob($this->basePath('routes/*.php')) ?: [] as $file) {
            $define = require $file;
            if (is_callable($define)) {
                $define($router, $this);
            }
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }
    }

    public function handle(Request $request): Response
    {
        $this->container->instance(Request::class, $request);
        if (PHP_SAPI !== 'cli') {
            $this->urlBasePath = $request->basePath();
        }

        try {
            $this->boot();
            $this->get(Session::class)->start($request->isSecure());
            return $this->router()->dispatch($request);
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json(['message' => $e->getMessage(), 'errors' => $e->errors], 422);
            }
            $session = $this->get(Session::class);
            $session->flash('_errors', $e->errors);
            $session->flash('_old', array_diff_key($e->old, array_flip(['password', 'password_confirmation', '_token'])));
            return Response::redirect(back_url());
        } catch (HttpException $e) {
            return $this->renderError($request, $e->status, $e->getMessage());
        } catch (Throwable $e) {
            $this->get(Logger::class)->error($e->getMessage(), [
                'exception' => $e::class,
                'file' => $e->getFile() . ':' . $e->getLine(),
                'url' => $request->method() . ' ' . $request->path(),
            ]);
            return $this->renderError($request, 500, $this->isDebug() ? $e->getMessage() : 'Đã có lỗi xảy ra, vui lòng thử lại sau.', $e);
        }
    }

    public function run(): void
    {
        $this->registerErrorHandling();
        $this->handle(Request::capture())->send();
    }

    private function renderError(Request $request, int $status, string $message, ?Throwable $e = null): Response
    {
        if ($request->wantsJson()) {
            $body = ['message' => $message];
            if ($e !== null && $this->isDebug()) {
                $body['exception'] = $e::class;
                $body['file'] = $e->getFile() . ':' . $e->getLine();
            }
            return Response::json($body, $status);
        }

        try {
            $view = $this->get(View::class);
            $template = $view->exists("errors/{$status}") ? "errors/{$status}" : 'errors/error';
            $html = $view->render($template, [
                'status' => $status,
                'message' => $message,
                'exception' => $this->isDebug() ? $e : null,
            ]);
        } catch (Throwable) {
            $html = '<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return Response::html($html, $status);
    }
}
