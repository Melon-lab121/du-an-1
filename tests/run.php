<?php

declare(strict_types=1);

/*
 * Chạy test:  C:\xampp\php\php.exe tests\run.php
 * Dùng SQLite in-memory => không đụng tới database MySQL thật. Cần extension pdo_sqlite.
 */

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Events;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Validator;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentManager;
use App\Services\Payment\SepayGateway;
use App\Services\PriceCalculator;
use App\Services\VariantService;
use Database\Migrator;
use Database\Seeder;

// Biến môi trường thật được ưu tiên hơn .env => cô lập test khỏi cấu hình máy
foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'true', 'APP_URL' => 'http://localhost/base',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
    'SEPAY_ENABLED' => 'true', 'SEPAY_ENV' => 'sandbox', 'SEPAY_MERCHANT_ID' => 'SP-TEST-TEST1234',
    'SEPAY_SECRET_KEY' => 'spsk_test_TESTSECRETKEY0123456789', 'SEPAY_IPN_SECRET' => 'ipn-secret-cua-nhom',
    'SEPAY_RECONCILE_ON_RETURN' => 'true',
] as $k => $v) {
    putenv("{$k}={$v}");
    $_ENV[$k] = $v;
}

if (!extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "Cần bật extension=pdo_sqlite trong php.ini để chạy test.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
/** @var App\Core\App $app */
$app = require BASE_PATH . '/bootstrap/app.php';
$app->registerErrorHandling();
require __DIR__ . '/TestCase.php';

$db = $app->get(Database::class);
foreach (Migrator::splitStatements((string) file_get_contents(__DIR__ . '/schema.sqlite.sql')) as $sql) {
    $db->pdo()->exec($sql);
}
(new Seeder($db))->run();
$app->boot();

/**
 * Chép nguyên SignatureGenerator::signCheckoutFields() của SDK PHP chính thức (sepay/sepay-pg, MIT)
 * — dùng làm chuẩn đối chiếu chữ ký form checkout.
 */
function sepay_reference_signature(array $fields, string $secretKey): string
{
    $signed = [];
    $signedFields = array_values(array_filter(array_keys($fields), fn ($field) => in_array($field, [
        'merchant', 'env', 'operation', 'payment_method', 'order_amount', 'currency', 'order_invoice_number',
        'order_description', 'customer_id', 'agreement_id', 'agreement_name', 'agreement_type',
        'agreement_payment_frequency', 'agreement_amount_per_payment', 'success_url', 'error_url', 'cancel_url',
    ])));
    foreach ($signedFields as $field) {
        if (!isset($fields[$field])) {
            continue;
        }
        $signed[] = $field . '=' . ($fields[$field] ?? '');
    }
    return base64_encode(hash_hmac('sha256', implode(',', $signed), $secretKey, true));
}

/** Giả lập body IPN ORDER_PAID của SePay (theo ví dụ trong tài liệu IPN) cho 1 payment. */
function sepay_ipn(array $payment, ?int $amount = null, string $type = 'ORDER_PAID'): array
{
    return [
        'timestamp' => time(),
        'notification_type' => $type,
        'order' => [
            'id' => 'e2c1' . random_int(1000, 9999),
            'order_id' => 'ORD' . random_int(10000, 99999),
            'order_status' => 'CAPTURED',
            'order_currency' => 'VND',
            'order_amount' => number_format($amount ?? (int) $payment['amount'], 2, '.', ''), // "189000.00"
            'order_invoice_number' => (string) $payment['txn_ref'],
            'custom_data' => [],
            'order_description' => 'Thanh toan don hang',
        ],
        'transaction' => [
            'id' => (string) random_int(100, 999),
            'payment_method' => 'BANK_TRANSFER',
            'transaction_id' => 'TXN' . random_int(10000000, 99999999),
            'transaction_type' => 'PAYMENT',
            'transaction_status' => 'APPROVED',
            'transaction_amount' => (string) ($amount ?? (int) $payment['amount']),
            'transaction_currency' => 'VND',
        ],
        'customer' => null,
    ];
}

/** Đăng ký lại cổng SePay với transport giả (không gọi mạng). Trả về mảng ghi lại các request. */
function fake_sepay_api(App\Core\App $app, callable $respond): ArrayObject
{
    $calls = new ArrayObject();
    $manager = $app->get(PaymentManager::class);
    /** @var SepayGateway $sepay */
    $sepay = $manager->get('sepay');
    $manager->register($sepay->withTransport(function (string $method, string $url, array $headers, ?string $body) use ($calls, $respond): array {
        $calls[] = compact('method', 'url', 'headers');
        return $respond($url);
    }));
    return $calls;
}

/** Phản hồi giả của API chi tiết đơn. */
function sepay_detail(string $status, int $amount): array
{
    return ['status' => 200, 'body' => json_encode(['data' => ['id' => 'ord_1', 'order_status' => $status, 'order_amount' => $amount . '.00']])];
}

function product(string $slug): array
{
    return Product::findBy('slug', $slug) ?? throw new RuntimeException("Không có sản phẩm {$slug}");
}

$customer = ['name' => 'Nguyễn Văn A', 'email' => 'a@example.com', 'phone' => '0912345678', 'address' => '1 Lê Lợi, Q1, TP.HCM', 'note' => null];

// ---------------------------------------------------------------- Helpers / Core
T::section('Core');

T::test('str_slug bỏ dấu tiếng Việt', function () {
    T::eq('ao-thun-nu-dep-2026', str_slug('  Áo thun NỮ đẹp — 2026! '));
    T::eq('dien-thoai-di-dong', str_slug('Điện thoại di động'));
    T::eq('Don hang', str_ascii('Đơn hàng'));
});

T::test('money() định dạng VND', function () {
    T::eq('1.250.000 ₫', money(1250000));
});

T::test('Request tách base path cho XAMPP thư mục con và php -S', function () {
    T::eq(['/base', '/products'], Request::splitBasePath('/base/products', '/base/public/index.php'));
    T::eq(['/base', '/'], Request::splitBasePath('/base/', '/base/public/index.php'));
    T::eq(['/base/public', '/cart'], Request::splitBasePath('/base/public/cart', '/base/public/index.php'));
    T::eq(['', '/cart'], Request::splitBasePath('/cart', '/index.php'));
    T::eq(['', '/basement'], Request::splitBasePath('/basement', '/base/public/index.php'));
});

T::test('Request::ip chỉ tin CF-Connecting-IP khi bật TRUST_PROXY và request từ loopback', function () {
    $origEnv = $_ENV['TRUST_PROXY'] ?? null;
    putenv('TRUST_PROXY=false');
    $_ENV['TRUST_PROXY'] = 'false';
    $behindTunnel = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => '203.0.113.7'];
    T::eq('127.0.0.1', Request::create('GET', '/', [], $behindTunnel)->ip(), 'mặc định không tin header');

    putenv('TRUST_PROXY=true');
    $_ENV['TRUST_PROXY'] = 'true';
    try {
        T::eq('203.0.113.7', Request::create('GET', '/', [], $behindTunnel)->ip());
        $spoof = ['REMOTE_ADDR' => '198.51.100.9', 'HTTP_CF_CONNECTING_IP' => '1.2.3.4'];
        T::eq('198.51.100.9', Request::create('GET', '/', [], $spoof)->ip(), 'client ngoài không giả mạo được IP');
        T::eq('127.0.0.1', Request::create('GET', '/', [], ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_CF_CONNECTING_IP' => 'abc'])->ip());
    } finally {
        if ($origEnv !== null) {
            putenv('TRUST_PROXY=' . $origEnv);
            $_ENV['TRUST_PROXY'] = $origEnv;
        } else {
            putenv('TRUST_PROXY');
            unset($_ENV['TRUST_PROXY']);
        }
    }
});

T::test('Router: tham số, ép kiểu int, tên route, group', function () use ($app) {
    $router = new Router($app->container());
    $router->get('/p/{slug}', fn (string $slug) => "slug={$slug}")->name('p');
    $router->get('/n/{id:\d+}', fn (int $id) => ['id' => $id]);
    $router->group(['prefix' => '/admin', 'as' => 'admin.'], function (Router $r) {
        $r->get('/x/{id:\d+}', fn () => 'x')->name('x');
    });

    T::eq('slug=ao-dep', $router->dispatch(Request::create('GET', '/p/ao-dep'))->content());
    T::eq('{"id":42}', $router->dispatch(Request::create('GET', '/n/42'))->content());
    T::eq('/admin/x/7?tab=1', $router->url('admin.x', ['id' => 7, 'tab' => 1]));
    T::eq('/p/a%20b', $router->url('p', ['slug' => 'a b']));
    T::throws(HttpException::class, fn () => $router->dispatch(Request::create('GET', '/n/abc')));
    try {
        $router->dispatch(Request::create('POST', '/p/x'));
        throw new RuntimeException('không ném 405');
    } catch (HttpException $e) {
        T::eq(405, $e->status);
    }
});

T::test('Router: middleware chạy đúng thứ tự và nhận tham số', function () use ($app) {
    $log = new ArrayObject();
    $app->container()->instance('mw.a', new class ($log) implements App\Core\MiddlewareInterface {
        public function __construct(private ArrayObject $log) {}
        public function handle(Request $r, callable $next, string ...$args): Response
        {
            $this->log[] = 'a:' . implode(',', $args);
            $res = $next($r);
            $this->log[] = 'a-after';
            return $res;
        }
    });
    $router = new Router($app->container());
    $router->aliasMiddleware('a', 'mw.a');
    $router->get('/m', function () use ($log) { $log[] = 'handler'; return 'ok'; })->middleware('a:x,y');
    $router->dispatch(Request::create('GET', '/m'));
    T::eq(['a:x,y', 'handler', 'a-after'], $log->getArrayCopy());
});

T::test('Validator: rule cơ bản + thông báo tiếng Việt', function () use ($app) {
    $v = $app->get(Validator::class);
    [$clean, $errors] = $v->validate(
        ['email' => 'sai', 'qty' => '3', 'pw' => 'abc', 'pw_confirmation' => 'abd', 'phone' => '0912 345 678', 'opt' => ''],
        ['email' => 'required|email', 'qty' => 'required|integer|min:1|max:5', 'pw' => 'required|min:8|confirmed', 'phone' => 'phone', 'opt' => 'nullable|integer', 'name' => 'required'],
        ['email' => 'Email', 'name' => 'Họ tên'],
    );
    T::eq('Email không đúng định dạng email.', $errors['email'][0]);
    T::eq('Họ tên là bắt buộc.', $errors['name'][0]);
    T::true(isset($errors['pw']));
    T::eq(3, $clean['qty']);
    T::eq(null, $clean['opt']);
    T::eq('0912 345 678', $clean['phone']);

    [, $errors] = $v->validate(['email' => 'admin@example.com'], ['email' => 'unique:users,email']);
    T::eq('Email đã tồn tại.', $errors['email'][0]);

    // chuỗi không phải UTF-8 (vd: client gửi Latin-1) => lỗi validate thay vì lỗi 500 từ MySQL
    [$clean, $errors] = $v->validate(['address' => "L\xEA L\xF4i"], ['address' => 'required|string']);
    T::true(isset($errors['address']));
    T::false(array_key_exists('address', $clean));
});

T::test('QueryBuilder chặn tên cột độc hại, paginate đúng', function () use ($db) {
    T::throws(InvalidArgumentException::class, fn () => $db->table('products')->orderBy('id; DROP TABLE users')->get());
    T::throws(InvalidArgumentException::class, fn () => $db->table('products')->where('name', 'OR 1=1', 'x'));
    $page = $db->table('products')->orderBy('id')->paginate(5, 3);
    T::eq(13, $page['total'], '12 sản phẩm thường + 1 sản phẩm có biến thể');
    T::eq(3, $page['last_page']);
    T::eq(3, count($page['data']));
});

T::test('Events: filter cộng dồn theo priority', function () {
    $events = new Events();
    $events->listen('price', fn ($v) => $v * 2, 20);
    $events->listen('price', fn ($v) => $v + 1, 10);
    T::eq(22, $events->filter('price', 10)); // (10 + 1) * 2
});

// ---------------------------------------------------------------- Pricing
T::section('Pricing');

T::test('PriceCalculator: giá KM chỉ áp dụng khi nhỏ hơn giá gốc', function () {
    T::eq(90, PriceCalculator::unitPrice(['price' => 100, 'sale_price' => 90]));
    T::eq(100, PriceCalculator::unitPrice(['price' => 100, 'sale_price' => 150]));
    T::eq(100, PriceCalculator::unitPrice(['price' => 100, 'sale_price' => null]));
});

T::test('PriceCalculator: phí ship, miễn phí ship, giảm giá không vượt tạm tính', function () {
    T::eq(['subtotal' => 200000, 'discount' => 0, 'shipping_fee' => 30000, 'total' => 230000], PriceCalculator::totals([['total' => 200000]], 30000, 500000));
    T::eq(0, PriceCalculator::totals([['total' => 500000]], 30000, 500000)['shipping_fee']);
    T::eq(['subtotal' => 100, 'discount' => 100, 'shipping_fee' => 5, 'total' => 5], PriceCalculator::totals([['total' => 100]], 5, 0, 999));
    T::eq(0, PriceCalculator::totals([], 30000, 0)['total']);
});

// ---------------------------------------------------------------- SePay
T::section('SePay');

T::test('Form checkout: đúng URL sandbox, thứ tự trường, số tiền KHÔNG nhân 100', function () use ($app) {
    /** @var SepayGateway $sepay */
    $sepay = $app->get(PaymentManager::class)->get('sepay');
    $checkout = $sepay->createCheckout([
        'txn_ref' => '2611041200001234', 'amount' => 1250000, 'order_info' => 'Thanh toán đơn hàng DH261104ABC',
        'method' => 'BANK_TRANSFER',
    ]);

    T::eq('https://pay-sandbox.sepay.vn/v1/checkout/init', $checkout['action']);
    $f = $checkout['fields'];
    T::eq(['merchant', 'currency', 'order_amount', 'operation', 'order_description', 'payment_method',
        'order_invoice_number', 'success_url', 'error_url', 'cancel_url', 'signature'], array_keys($f), 'thứ tự giống SDK');
    T::eq('1250000', $f['order_amount'], 'số nguyên VND, không nhân 100');
    T::eq('SP-TEST-TEST1234', $f['merchant']);
    T::eq('Thanh toan don hang DH261104ABC', $f['order_description']);
    T::eq('http://localhost/base/payment/sepay/success?ref=2611041200001234', $f['success_url']);
});

T::test('Chữ ký khớp SDK chính thức (PHP + vector sinh bằng sepay-pg-node)', function () use ($app) {
    $sepay = $app->get(PaymentManager::class)->get('sepay');
    $f = $sepay->createCheckout([
        'txn_ref' => '2611041200001234', 'amount' => 1250000, 'order_info' => 'Thanh toan don hang DH261104ABC',
        'method' => 'BANK_TRANSFER',
    ])['fields'];
    $signature = $f['signature'];
    unset($f['signature']);
    T::eq(sepay_reference_signature($f, 'spsk_test_TESTSECRETKEY0123456789'), $signature);
    // Giá trị do SDK Node chính thức (sepay-pg-node 1.0.0) tính trên cùng dữ liệu
    T::eq('U+4IjTDnY+Jp6Xz815o3Qrzw1QhyjEWgKBOSuDcocYs=', $signature);

    $f['order_amount'] = '1000';
    T::false(hash_equals($signature, $sepay->sign($f)), 'sửa số tiền => chữ ký đổi');
});

T::test('Khoá IPN: đúng thì nhận, sai/thiếu thì từ chối; parseAmount không qua float', function () use ($app) {
    $sepay = $app->get(PaymentManager::class)->get('sepay');
    T::true($sepay->verifyIpnKey('ipn-secret-cua-nhom'));
    T::false($sepay->verifyIpnKey('sai'));
    T::false($sepay->verifyIpnKey(null));
    T::eq(50000, SepayGateway::parseAmount('50000.00'));
    T::eq(50000, SepayGateway::parseAmount('50000'));
    T::eq(null, SepayGateway::parseAmount('50000.50'));
    T::eq(null, SepayGateway::parseAmount('abc'));
});

// ---------------------------------------------------------------- Đặt hàng + thanh toán (tích hợp)
T::section('Order & Payment flow');

T::test('Giỏ hàng: không cho thêm vượt tồn kho', function () use ($app) {
    $cart = $app->get(CartService::class);
    $cart->clear();
    $p = product('laptop-workstation-15-i7'); // stock 5
    $cart->add((int) $p['id'], 3);
    T::throws(DomainException::class, fn () => $cart->add((int) $p['id'], 3), 'Chỉ còn 5');
    T::eq(3, $cart->count());
    T::eq((int) $p['price'] * 3, $cart->totals()['subtotal']);
    $cart->clear();
});

T::test('Đặt hàng: tính tiền từ DB, trừ kho, tạo order_items', function () use ($app, $customer) {
    $p = product('tai-nghe-bluetooth-anc'); // 1.290.000, KM 990.000, stock 60
    $order = $app->get(OrderService::class)->place($customer, [(int) $p['id'] => 2], 'cod');

    T::eq(1980000, (int) $order['subtotal']);
    T::eq(0, (int) $order['shipping_fee'], 'đơn > 500k được miễn ship');
    T::eq(1980000, (int) $order['total']);
    T::eq('pending', $order['status']);
    T::eq(58, (int) product('tai-nghe-bluetooth-anc')['stock']);
    T::eq(1, count(Order::items((int) $order['id'])));
});

T::test('Đặt hàng: hết hàng thì rollback toàn bộ (không trừ kho sản phẩm khác)', function () use ($app, $customer) {
    $a = product('sac-nhanh-gan-65w');
    $b = product('laptop-workstation-15-i7');
    $before = (int) $a['stock'];
    $orders = (int) $app->get(Database::class)->scalar('SELECT COUNT(*) FROM orders');

    T::throws(DomainException::class, fn () => $app->get(OrderService::class)->place($customer, [(int) $a['id'] => 1, (int) $b['id'] => 999], 'cod'), 'không đủ hàng');
    T::eq($before, (int) product('sac-nhanh-gan-65w')['stock']);
    T::eq($orders, (int) $app->get(Database::class)->scalar('SELECT COUNT(*) FROM orders'));
});

T::test('SePay IPN: thành công => đơn paid; gửi lại => 200 và không xử lý lại (idempotent)', function () use ($app, $customer) {
    $p = product('ao-thun-cotton-basic'); // 159.000
    $order = $app->get(OrderService::class)->place($customer, [(int) $p['id'] => 1], 'sepay');
    T::eq(189000, (int) $order['total'], '159k + 30k ship');

    $service = $app->get(PaymentService::class);
    $checkout = $service->start($order);
    T::eq('189000', $checkout['fields']['order_amount']);

    $payment = Order::payments((int) $order['id'])[0];
    T::eq('pending', $payment['status']);
    T::eq($payment['txn_ref'], $checkout['fields']['order_invoice_number']);

    $ipn = sepay_ipn($payment);
    T::eq(['status' => 200, 'body' => ['success' => true]], $service->handleSepayIpn('ipn-secret-cua-nhom', $ipn));
    T::eq('paid', Order::find((int) $order['id'])['payment_status']);
    T::eq('confirmed', Order::find((int) $order['id'])['status']);
    $saved = Payment::find((int) $payment['id']);
    T::eq('success', $saved['status']);
    T::eq($ipn['transaction']['transaction_id'], $saved['transaction_no']);
    T::eq(200, $service->handleSepayIpn('ipn-secret-cua-nhom', $ipn)['status']);
    T::eq($saved['paid_at'], Payment::find((int) $payment['id'])['paid_at'], 'lần 2 không ghi đè');
    T::throws(DomainException::class, fn () => $service->start(Order::find((int) $order['id'])), 'đã được thanh toán');
});

T::test('SePay IPN: sai khoá 401, sai số tiền 422, không có đơn 404, VOID không đổi gì', function () use ($app, $customer) {
    $service = $app->get(PaymentService::class);
    $order = $app->get(OrderService::class)->place($customer, [(int) product('ao-thun-cotton-basic')['id'] => 1], 'sepay');
    $service->start($order);
    $payment = Order::payments((int) $order['id'])[0];

    T::eq(401, $service->handleSepayIpn('sai-khoa', sepay_ipn($payment))['status']);
    T::eq(401, $service->handleSepayIpn(null, sepay_ipn($payment))['status']);
    T::eq(422, $service->handleSepayIpn('ipn-secret-cua-nhom', sepay_ipn($payment, 1000))['status']);
    T::eq(404, $service->handleSepayIpn('ipn-secret-cua-nhom', sepay_ipn(['amount' => 1000, 'txn_ref' => 'KHONGTONTAI']))['status']);
    T::eq(200, $service->handleSepayIpn('ipn-secret-cua-nhom', sepay_ipn($payment, null, 'TRANSACTION_VOID'))['status']);
    T::eq('pending', Payment::find((int) $payment['id'])['status'], 'không được đổi trạng thái khi lỗi');
});

T::test('SePay: khách huỷ => đơn vẫn chờ, thanh toán lại bằng mã hoá đơn mới', function () use ($app, $customer) {
    $service = $app->get(PaymentService::class);
    $order = $app->get(OrderService::class)->place($customer, [(int) product('ao-thun-cotton-basic')['id'] => 1], 'sepay');
    $service->start($order);
    $first = Order::payments((int) $order['id'])[0];

    $page = $app->handle(Request::create('GET', '/payment/sepay/cancel', ['ref' => $first['txn_ref']]));
    T::true(str_contains($page->content(), 'Bạn đã huỷ thanh toán'));
    T::eq('pending', Payment::find((int) $first['id'])['status']);

    $service->start(Order::find((int) $order['id']));
    $retry = Order::payments((int) $order['id'])[0];
    T::true($retry['txn_ref'] !== $first['txn_ref']);
    T::eq(200, $service->handleSepayIpn('ipn-secret-cua-nhom', sepay_ipn($retry))['status']);
    T::eq('paid', Order::find((int) $order['id'])['payment_status']);
});

T::test('Đối soát: gọi đúng API + Basic Auth; chỉ cập nhật khi CAPTURED và đúng số tiền', function () use ($app, $customer) {
    $service = $app->get(PaymentService::class);
    $order = $app->get(OrderService::class)->place($customer, [(int) product('ao-thun-cotton-basic')['id'] => 1], 'sepay');
    $service->start($order);
    $payment = Order::payments((int) $order['id'])[0];

    $calls = fake_sepay_api($app, fn () => sepay_detail('CAPTURED', 1000));
    T::false($service->reconcileSepay($payment)['updated'], 'sai số tiền => không cập nhật');
    T::eq('https://pgapi-sandbox.sepay.vn/v1/order/detail/' . $payment['txn_ref'], $calls[0]['url']);
    T::eq('GET', $calls[0]['method']);
    T::eq('Basic ' . base64_encode('SP-TEST-TEST1234:spsk_test_TESTSECRETKEY0123456789'), $calls[0]['headers']['Authorization']);

    fake_sepay_api($app, fn () => sepay_detail('PENDING', 189000));
    T::false($service->reconcileSepay($payment)['updated']);

    fake_sepay_api($app, fn () => sepay_detail('CAPTURED', 189000));
    T::true($service->reconcileSepay($payment)['updated']);
    T::eq('paid', Order::find((int) $order['id'])['payment_status']);
    T::false($service->reconcileSepay($payment)['updated'], 'lần 2 không xử lý lại');

    fake_sepay_api($app, fn () => ['status' => 401, 'body' => '{"message":"Unauthenticated"}']);
    T::throws(RuntimeException::class, fn () => $service->reconcileSepay($payment), 'HTTP 401');
});

T::test('Huỷ đơn hoàn lại tồn kho, không đổi trạng thái sau khi huỷ', function () use ($app, $customer) {
    $orders = $app->get(OrderService::class);
    $p = product('chuot-khong-day-silent');
    $before = (int) $p['stock'];
    $order = $orders->place($customer, [(int) $p['id'] => 4], 'cod');
    T::eq($before - 4, (int) product('chuot-khong-day-silent')['stock']);
    $orders->updateStatus((int) $order['id'], 'cancelled');
    T::eq($before, (int) product('chuot-khong-day-silent')['stock']);
    T::throws(DomainException::class, fn () => $orders->updateStatus((int) $order['id'], 'shipping'));
});

// ---------------------------------------------------------------- Xoá mềm
T::section('Soft delete');

/** Tạo sản phẩm thường để thử (không đụng dữ liệu seed mà test khác dùng). */
function make_product(string $name, int $price = 100000, int $stock = 10, ?string $sku = null): array
{
    $id = Product::create([
        'category_id' => null, 'name' => $name, 'slug' => Product::uniqueSlug($name), 'sku' => $sku,
        'price' => $price, 'stock' => $stock, 'status' => 'active', 'is_featured' => 0,
    ]);
    return Product::withTrashed()->find($id);
}

T::test('Xoá mềm: biến mất khỏi query()/find(), vẫn còn trong DB; withTrashed/onlyTrashed', function () use ($db) {
    $p = make_product('Ốp lưng thử xoá', 50000, 5, 'OPTHU1');
    $before = Product::query()->count();

    T::eq(1, Product::deleteById((int) $p['id']));
    T::eq(null, Product::find((int) $p['id']), 'find() không thấy bản ghi đã xoá');
    T::eq($before - 1, Product::query()->count());
    T::eq(1, (int) $db->scalar('SELECT COUNT(*) FROM products WHERE id = ?', [(int) $p['id']]), 'dòng vẫn còn trong bảng');
    T::true(Product::onlyTrashed()->where('id', (int) $p['id'])->exists());
    T::eq(0, Product::deleteById((int) $p['id']), 'xoá lần 2 không ghi đè thời điểm xoá');
});

T::test('Bẫy UNIQUE: xoá rồi tạo lại cùng slug/SKU được; khôi phục trả slug gốc, nhường SKU nếu đã bị dùng', function () {
    $old = make_product('Cáp sạc thử', 90000, 5, 'CAPTHU');
    Product::deleteById((int) $old['id']);
    $trashed = Product::withTrashed()->find((int) $old['id']);
    T::eq('cap-sac-thu--xoa-' . $old['id'], $trashed['slug']);
    T::eq('CAPTHU#' . $old['id'], $trashed['sku']);

    $new = make_product('Cáp sạc thử', 95000, 5, 'CAPTHU'); // không vướng UNIQUE
    T::eq('cap-sac-thu', $new['slug']);

    T::eq(1, Product::restore((int) $old['id']));
    $restored = Product::find((int) $old['id']);
    T::eq('cap-sac-thu-2', $restored['slug'], 'slug gốc đã có người dùng => sinh slug mới');
    T::eq(null, $restored['sku'], 'SKU đã có người dùng => để trống cho admin đặt lại');

    Product::deleteById((int) $new['id']);
    Product::deleteById((int) $old['id']);
    Product::restore((int) $new['id']);
    T::eq('cap-sac-thu', Product::find((int) $new['id'])['slug'], 'không ai chiếm => trả đúng slug gốc');
    T::eq('CAPTHU', Product::find((int) $new['id'])['sku']);
});

T::test('Sản phẩm vừa xoá mềm: biến khỏi trang, giỏ, không đặt được; đơn cũ vẫn giữ tên + liên kết', function () use ($app, $customer) {
    $p = make_product('Giá đỡ điện thoại', 120000, 10);
    $cart = $app->get(CartService::class);
    $cart->clear();
    $cart->add((int) $p['id'], 2);
    $order = $app->get(OrderService::class)->place($customer, [(int) $p['id'] => 1], 'cod');

    Product::deleteById((int) $p['id']);

    T::eq([], $cart->lines(), 'giỏ tự bỏ dòng trỏ tới sản phẩm đã xoá');
    T::eq(404, $app->handle(Request::create('GET', '/product/' . $p['slug']))->status());
    T::throws(DomainException::class, fn () => $app->get(OrderService::class)->place($customer, [(int) $p['id'] => 1], 'cod'), 'ngừng bán');
    $item = Order::items((int) $order['id'])[0];
    T::eq((int) $p['id'], (int) $item['product_id'], 'xoá mềm không làm mất liên kết như xoá cứng (SET NULL)');
    T::eq('Giá đỡ điện thoại', $item['product_name']);
    T::true(Product::hasOrders((int) $p['id']));
    $cart->clear();
});

T::test('Admin: Xoá → thùng rác → khôi phục; xoá vĩnh viễn bị chặn nếu đã có đơn', function () use ($app, $db, $customer) {
    $admin = App\Models\User::findBy('email', 'admin@example.com');
    $app->get(App\Services\Auth::class)->login($admin);
    try {
    $token = $app->get(Csrf::class)->token();

    $sold = make_product('Bao da đã bán', 80000, 5);
    $app->get(OrderService::class)->place($customer, [(int) $sold['id'] => 1], 'cod');
    $fresh = make_product('Bao da chưa bán', 80000, 5);

    foreach ([$sold, $fresh] as $p) {
        $res = $app->handle(Request::create('POST', '/admin/products/' . $p['id'], ['_token' => $token, '_method' => 'DELETE']));
        T::eq(302, $res->status());
    }
    $trash = $app->handle(Request::create('GET', '/admin/products/trash'));
    T::true(str_contains($trash->content(), 'Bao da đã bán') && str_contains($trash->content(), 'Có trong đơn'));

    $app->handle(Request::create('POST', '/admin/products/' . $sold['id'] . '/force', ['_token' => $token, '_method' => 'DELETE']));
    T::true(Product::onlyTrashed()->where('id', (int) $sold['id'])->exists(), 'đã bán => không cho xoá vĩnh viễn');

    $app->handle(Request::create('POST', '/admin/products/' . $fresh['id'] . '/force', ['_token' => $token, '_method' => 'DELETE']));
    T::eq(0, (int) $db->scalar('SELECT COUNT(*) FROM products WHERE id = ?', [(int) $fresh['id']]), 'chưa bán => xoá thật');

    $app->handle(Request::create('POST', '/admin/products/' . $sold['id'] . '/restore', ['_token' => $token]));
    T::true(Product::find((int) $sold['id']) !== null, 'khôi phục xong lại hiện');
    } finally {
        $app->get(App\Services\Auth::class)->logout();
    }
});

T::test('Đếm sản phẩm theo danh mục (SQL viết tay) cũng phải bỏ sản phẩm đã xoá', function () {
    $count = fn (): int => (int) array_column(App\Models\Category::withProductCount(), 'product_count', 'slug')['phu-kien'];
    $before = $count();
    $p = product('chuot-khong-day-silent');
    Product::deleteById((int) $p['id']);
    T::eq($before - 1, $count());
    Product::restore((int) $p['id']);
    T::eq($before, $count());
});

// ---------------------------------------------------------------- Biến thể động
T::section('Variants');

T::test('parseSpec + tích Descartes: 2 màu × 3 size = 6 tổ hợp; báo lỗi nhập sai', function () {
    $spec = VariantService::parseSpec("Màu: Đen, Xám\n\nSize: M, L, XL, L");
    T::eq([['name' => 'Màu', 'values' => ['Đen', 'Xám']], ['name' => 'Size', 'values' => ['M', 'L', 'XL']]], $spec, 'bỏ dòng trống, giá trị trùng');
    $combos = VariantService::cartesian(array_column($spec, 'values'));
    T::eq(6, count($combos));
    T::eq(['Đen', 'M'], $combos[0]);
    T::eq(['Xám', 'XL'], $combos[5]);
    T::eq([], VariantService::cartesian([]));
    T::throws(DomainException::class, fn () => VariantService::parseSpec('Màu Đen, Xám'), 'thiếu dấu');
    T::throws(DomainException::class, fn () => VariantService::parseSpec("Màu: Đen\nmàu: Xám"), 'bị lặp');
    T::throws(DomainException::class, fn () => VariantService::parseSpec("A: 1,2,3,4,5\nB: 1,2,3,4,5\nC: 1,2,3,4,5"), 'vượt giới hạn');
});

T::test('Seed hoodie: 6 biến thể, products.price/stock là cột tổng hợp từ biến thể', function () {
    $p = product('ao-hoodie-ni-basic');
    T::eq(1, (int) $p['has_variants']);
    $variants = ProductVariant::forProduct((int) $p['id']);
    T::eq(['Đen / M', 'Đen / L', 'Đen / XL', 'Xám / M', 'Xám / L', 'Xám / XL'], array_column($variants, 'title'));
    T::eq(26, (int) $p['stock'], 'tổng tồn 10+8+0+5+0+3');
    T::eq(350000, (int) $p['price'], 'giá gốc thấp nhất');
    T::eq(300000, (int) $p['sale_price'], 'giá bán thấp nhất (Xám giảm 50k)');
    T::eq('SP0013-DEN-XL', $variants[2]['sku']);
});

T::test('findByValues (option_key) khớp với phép chia quan hệ GROUP BY … HAVING COUNT', function () use ($app, $db) {
    $p = product('ao-hoodie-ni-basic');
    $service = $app->get(VariantService::class);
    $options = $service->options((int) $p['id']);
    $xam = $options[0]['values'][1]['id'];
    $xl = $options[1]['values'][2]['id'];

    $v = $service->findByValues((int) $p['id'], [$xl, $xam]); // thứ tự không quan trọng
    T::eq('Xám / XL', $v['title']);
    T::eq(null, $service->findByValues((int) $p['id'], [$xam]), 'chưa chọn đủ thuộc tính');
    T::eq(null, $service->findByValues((int) $p['id'], [$xam, $options[0]['values'][0]['id']]), '2 giá trị cùng thuộc tính');

    $sql = 'SELECT v.id FROM product_variants v
            JOIN product_variant_values pvv ON pvv.variant_id = v.id
            WHERE v.product_id = ? AND v.deleted_at IS NULL AND pvv.option_value_id IN (?, ?)
            GROUP BY v.id HAVING COUNT(*) = 2';
    T::eq((int) $v['id'], (int) $db->scalar($sql, [(int) $p['id'], $xam, $xl]));
});

T::test('Giỏ hàng: bắt chọn phân loại; mỗi biến thể 1 dòng; tồn kho theo biến thể; form không JS gửi options[]', function () use ($app) {
    $p = product('ao-hoodie-ni-basic');
    $cart = $app->get(CartService::class);
    $cart->clear();
    $byTitle = array_column(ProductVariant::forProduct((int) $p['id']), null, 'title');

    T::throws(DomainException::class, fn () => $cart->add((int) $p['id']), 'chọn phân loại');
    T::throws(DomainException::class, fn () => $cart->add((int) $p['id'], 1, (int) $byTitle['Đen / XL']['id']), 'hết hàng');
    T::throws(DomainException::class, fn () => $cart->add((int) $p['id'], 9, (int) $byTitle['Xám / M']['id']), 'Chỉ còn 5');
    T::throws(DomainException::class, fn () => $cart->add((int) product('sac-nhanh-gan-65w')['id'], 1, (int) $byTitle['Đen / M']['id']), 'không có phân loại');

    $cart->add((int) $p['id'], 2, (int) $byTitle['Đen / M']['id']);
    $cart->add((int) $p['id'], 1, (int) $byTitle['Xám / XL']['id']);
    $lines = $cart->lines();
    T::eq(2, count($lines));
    T::eq('Đen / M', $lines[0]['variant_label']);
    T::eq(700000, $lines[0]['total'], '2 × 350k');
    T::eq(330000, $lines[1]['price'], 'XL 380k - giảm 50k');

    // Form HTTP không có JS: gửi options[option_id] = value_id, server tự tìm biến thể
    $token = $app->get(Csrf::class)->token();
    $options = $app->get(VariantService::class)->options((int) $p['id']);
    $res = $app->handle(Request::create('POST', '/cart/add', [
        '_token' => $token, 'product_id' => $p['id'], 'quantity' => 1,
        'options' => [$options[0]['id'] => $options[0]['values'][0]['id'], $options[1]['id'] => $options[1]['values'][1]['id']],
    ], ['HTTP_ACCEPT' => 'application/json']));
    T::eq(200, $res->status());
    T::eq(1, $cart->raw()[CartService::key((int) $p['id'], (int) $byTitle['Đen / L']['id'])]);

    $page = $app->handle(Request::create('GET', '/cart'));
    T::true(str_contains($page->content(), 'Phân loại: Đen / L'));
    $cart->clear();
});

T::test('Đặt hàng biến thể: trừ kho đúng biến thể, cập nhật tổng tồn; huỷ đơn hoàn kho; đơn lưu nhãn', function () use ($app, $customer) {
    $p = product('ao-hoodie-ni-basic');
    $byTitle = array_column(ProductVariant::forProduct((int) $p['id']), null, 'title');
    $denL = $byTitle['Đen / L'];
    $orders = $app->get(OrderService::class);

    $order = $orders->place($customer, [CartService::key((int) $p['id'], (int) $denL['id']) => 3], 'cod');
    T::eq(5, (int) ProductVariant::find((int) $denL['id'])['stock'], '8 - 3');
    T::eq(23, (int) product('ao-hoodie-ni-basic')['stock'], 'tổng tồn 26 - 3');
    $item = Order::items((int) $order['id'])[0];
    T::eq('Đen / L', $item['variant_label']);
    T::eq((int) $denL['id'], (int) $item['variant_id']);
    T::eq('SP0013-DEN-L', $item['sku']);

    T::throws(DomainException::class, fn () => $orders->place($customer, [(int) $p['id'] => 1], 'cod'), 'chọn lại phân loại');
    T::throws(DomainException::class, fn () => $orders->place($customer, [CartService::key((int) $p['id'], (int) $byTitle['Xám / L']['id']) => 1], 'cod'), 'không đủ hàng');

    $orders->updateStatus((int) $order['id'], 'cancelled');
    T::eq(8, (int) ProductVariant::find((int) $denL['id'])['stock']);
    T::eq(26, (int) product('ao-hoodie-ni-basic')['stock']);
});

T::test('Sửa thuộc tính: bỏ 1 size => biến thể vào thùng rác (đơn cũ giữ); thêm lại => khôi phục giá/tồn cũ', function () use ($app, $customer) {
    $p = product('ao-hoodie-ni-basic');
    $service = $app->get(VariantService::class);
    $xamXl = array_column(ProductVariant::forProduct((int) $p['id']), null, 'title')['Xám / XL'];
    $order = $app->get(OrderService::class)->place($customer, [CartService::key((int) $p['id'], (int) $xamXl['id']) => 1], 'cod');

    $stats = $service->sync((int) $p['id'], VariantService::parseSpec("Màu: Đen, Xám\nSize: M, L"));
    T::eq(['created' => 0, 'restored' => 0, 'trashed' => 2, 'kept' => 4], $stats);
    T::eq(null, ProductVariant::find((int) $xamXl['id']), 'Xám / XL đã vào thùng rác');
    T::eq((int) $xamXl['id'], (int) Order::items((int) $order['id'])[0]['variant_id'], 'đơn cũ vẫn trỏ tới biến thể');
    T::eq(23, (int) product('ao-hoodie-ni-basic')['stock'], 'tổng tồn chỉ tính biến thể đang bán: 10+8+5+0');

    $stats = $service->sync((int) $p['id'], VariantService::parseSpec("Màu: Đen, Xám, Trắng\nSize: M, L, XL"));
    T::eq(['created' => 3, 'restored' => 2, 'trashed' => 0, 'kept' => 4], $stats);
    $back = ProductVariant::find((int) $xamXl['id']);
    T::eq('Xám / XL', $back['title'], 'cùng id, không tạo trùng');
    T::eq(330000, (int) $back['sale_price']);
    T::eq(2, (int) $back['stock'], 'tồn kho cũ (3 - 1 vừa bán) được giữ');

    // Bỏ hết phân loại => sản phẩm thường; rồi trả lại cấu hình gốc cho các test khác
    $service->sync((int) $p['id'], []);
    T::eq(0, (int) product('ao-hoodie-ni-basic')['has_variants']);
    $service->sync((int) $p['id'], VariantService::parseSpec("Màu: Đen, Xám\nSize: M, L, XL"));
    T::eq(6, count(ProductVariant::forProduct((int) $p['id'])));
});

T::test('Admin lưu giá/tồn biến thể: không sửa được biến thể của sản phẩm khác; trang SP hiện bộ chọn', function () use ($app) {
    $admin = App\Models\User::findBy('email', 'admin@example.com');
    $app->get(App\Services\Auth::class)->login($admin);
    try {
    $token = $app->get(Csrf::class)->token();
    $p = product('ao-hoodie-ni-basic');
    $denM = array_column(ProductVariant::forProduct((int) $p['id']), null, 'title')['Đen / M'];
    $other = make_product('Sản phẩm khác', 1000, 1);

    // Gửi id biến thể của hoodie vào URL của sản phẩm khác => WHERE product_id chặn lại
    $app->handle(Request::create('POST', '/admin/products/' . $other['id'] . '/variants', [
        '_token' => $token, 'variants' => [$denM['id'] => ['sku' => 'HACK', 'price' => 1, 'sale_price' => '', 'stock' => 999]],
    ]));
    T::eq(10, (int) ProductVariant::find((int) $denM['id'])['stock']);

    $app->handle(Request::create('POST', '/admin/products/' . $p['id'] . '/variants', [
        '_token' => $token, 'variants' => [$denM['id'] => ['sku' => 'SP0013-DEN-M', 'price' => 360000, 'sale_price' => '', 'stock' => 12]],
    ]));
    T::eq(12, (int) ProductVariant::find((int) $denM['id'])['stock']);
    T::eq(27, (int) product('ao-hoodie-ni-basic')['stock'], '12+8+0+5+0+2');

    $edit = $app->handle(Request::create('GET', '/admin/products/' . $p['id'] . '/edit'));
    T::true(str_contains($edit->content(), 'Màu: Đen, Xám'));
    } finally {
        $app->get(App\Services\Auth::class)->logout();
    }

    $page = $app->handle(Request::create('GET', '/product/ao-hoodie-ni-basic'));
    T::true(str_contains($page->content(), 'name="options[') && str_contains($page->content(), 'data-variant-json'));
    T::true(str_contains($page->content(), 'Từ '));
});

// ---------------------------------------------------------------- HTTP (đi qua toàn bộ App)
T::section('HTTP');

T::test('GET / và /products hiển thị sản phẩm; 404 cho đường dẫn lạ', function () use ($app) {
    $home = $app->handle(Request::create('GET', '/'));
    T::eq(200, $home->status());
    T::true(str_contains($home->content(), 'Tai nghe Bluetooth ANC'));
    T::true(str_contains($home->content(), 'href="/base/products"'), 'link phải có base path');

    $list = $app->handle(Request::create('GET', '/products', ['q' => 'laptop', 'sort' => 'price_asc']));
    T::eq(200, $list->status());
    T::true(strpos($list->content(), 'StudyMate') < strpos($list->content(), 'WorkStation'), 'sắp xếp giá tăng dần');

    T::eq(404, $app->handle(Request::create('GET', '/khong-ton-tai'))->status());
    T::eq(404, $app->handle(Request::create('GET', '/product/khong-co'))->status());
    T::eq(200, $app->handle(Request::create('GET', '/sample'))->status(), 'route của module Sample');
});

T::test('POST không có CSRF token => 419', function () use ($app) {
    T::eq(419, $app->handle(Request::create('POST', '/cart/add', ['product_id' => 1]))->status());
});

T::test('AJAX thêm giỏ trả JSON; checkout SePay in form ký sẵn POST sang sandbox', function () use ($app) {
    $token = $app->get(Csrf::class)->token();
    $app->get(CartService::class)->clear();
    $p = product('balo-laptop-chong-nuoc');

    $res = $app->handle(Request::create('POST', '/cart/add', ['_token' => $token, 'product_id' => $p['id'], 'quantity' => 2], ['HTTP_ACCEPT' => 'application/json']));
    T::eq(200, $res->status());
    $json = json_decode($res->content(), true);
    T::true($json['ok']);
    T::eq(2, $json['count']);

    $res = $app->handle(Request::create('POST', '/checkout', [
        '_token' => $token, 'name' => 'Trần B', 'email' => 'b@example.com', 'phone' => '0987654321',
        'address' => 'Hà Nội', 'payment_method' => 'sepay', 'sepay_method' => 'BANK_TRANSFER',
    ]));
    T::eq(200, $res->status());
    T::true(str_contains($res->content(), 'action="https://pay-sandbox.sepay.vn/v1/checkout/init"'));
    T::true(str_contains($res->content(), 'name="payment_method" value="BANK_TRANSFER"'));
    T::true(str_contains($res->content(), 'name="signature"'));
    T::eq(0, $app->get(CartService::class)->count(), 'giỏ được xoá sau khi đặt');

    $order = Order::query()->latest()->first();
    $payment = Order::payments((int) $order['id'])[0];

    // Khách tự gõ success_url khi chưa trả tiền => API SePay báo chưa thu => KHÔNG được báo thành công
    fake_sepay_api($app, fn () => sepay_detail('PENDING', (int) $payment['amount']));
    $fake = $app->handle(Request::create('GET', '/payment/sepay/success', ['ref' => $payment['txn_ref']]));
    T::false(str_contains($fake->content(), 'Thanh toán thành công'));
    T::eq('unpaid', Order::find((int) $order['id'])['payment_status']);

    // SePay redirect về success_url sau khi thu tiền => reconcile_on_return hỏi API => cập nhật
    fake_sepay_api($app, fn () => sepay_detail('CAPTURED', (int) $payment['amount']));
    $page = $app->handle(Request::create('GET', '/payment/sepay/success', ['ref' => $payment['txn_ref']]));
    T::eq(200, $page->status());
    T::true(str_contains($page->content(), 'Thanh toán thành công'));
    T::eq('paid', Order::find((int) $order['id'])['payment_status']);

    $ghost = $app->handle(Request::create('GET', '/payment/sepay/success', ['ref' => 'KHONGCO']));
    T::true(str_contains($ghost->content(), 'Không tìm thấy giao dịch'));

    // IPN thật đi qua HTTP: POST JSON, không cần CSRF, header X-Secret-Key; đến sau => 200, không xử lý lại
    $ipn = $app->handle(Request::create('POST', '/payment/sepay/ipn', sepay_ipn($payment), ['HTTP_X_SECRET_KEY' => 'ipn-secret-cua-nhom', 'CONTENT_TYPE' => 'application/json']));
    T::eq(200, $ipn->status());
    T::eq(['success' => true], json_decode($ipn->content(), true));
    $bad = $app->handle(Request::create('POST', '/payment/sepay/ipn', sepay_ipn($payment), ['CONTENT_TYPE' => 'application/json']));
    T::eq(401, $bad->status());

    // khách vãng lai xem được đơn của mình
    T::eq(200, $app->handle(Request::create('GET', '/orders/' . $order['code']))->status());
});

T::test('Checkout thiếu thông tin => quay lại form với lỗi', function () use ($app) {
    $token = $app->get(Csrf::class)->token();
    $app->get(CartService::class)->add((int) product('sac-nhanh-gan-65w')['id']);
    $res = $app->handle(Request::create('POST', '/checkout', ['_token' => $token, 'payment_method' => 'cod', 'phone' => '123'], ['HTTP_REFERER' => 'http://localhost/base/checkout']));
    T::eq(302, $res->status());
    T::eq('http://localhost/base/checkout', $res->header('Location'));
    $errors = flash('_errors');
    T::true(isset($errors['name'], $errors['phone']));
    $app->get(CartService::class)->clear();
});

T::test('Trang admin yêu cầu đăng nhập và quyền admin', function () use ($app) {
    $res = $app->handle(Request::create('GET', '/admin'));
    T::eq(302, $res->status());
    T::eq('/base/login', $res->header('Location'));

    $auth = $app->get(App\Services\Auth::class);
    T::true($auth->attempt('customer@example.com', 'customer123'));
    T::eq(403, $app->handle(Request::create('GET', '/admin'))->status());
    $auth->logout();

    T::false($auth->attempt('admin@example.com', 'sai-mat-khau'));
    T::true($auth->attempt('admin@example.com', 'admin123'));
    foreach (['/admin', '/admin/products', '/admin/products/create', '/admin/orders', '/admin/categories', '/admin/orders/1', '/admin/products/1/edit'] as $path) {
        T::eq(200, $app->handle(Request::create('GET', $path))->status(), $path);
    }
    $auth->logout();
});

T::test('API JSON /api/products', function () use ($app) {
    $res = $app->handle(Request::create('GET', '/api/products', ['per_page' => 3]));
    T::eq(200, $res->status());
    $json = json_decode($res->content(), true);
    T::eq(3, count($json['data']));
    T::eq(Product::active()->count(), $json['meta']['total'], 'API chỉ đếm sản phẩm đang bán, chưa xoá');
    T::eq(404, $app->handle(Request::create('GET', '/api/products/khong-co'))->status());

    $hoodie = json_decode($app->handle(Request::create('GET', '/api/products/ao-hoodie-ni-basic'))->content(), true)['data'];
    T::true($hoodie['has_variants']);
    T::eq(['Màu', 'Size'], array_column($hoodie['options'], 'name'));
    T::eq(6, count($hoodie['variants']));
});

T::section('Voucher');

T::test('Voucher: kiểm tra điều kiện kích hoạt, hạn dùng, chi tiêu tối thiểu', function () use ($app) {
    $voucherService = $app->get(App\Services\VoucherService::class);

    // 1. Voucher không tồn tại
    $res = $voucherService->evaluate('KHONG_TON_TAI', [['product_id' => 1, 'total' => 200000]]);
    T::false($res['valid']);
    T::eq('Mã giảm giá không tồn tại.', $res['error']);

    // 2. Tạo voucher tạm khóa
    $vId = App\Models\Voucher::create([
        'code' => 'KHOA10',
        'voucher_type' => 1,
        'discount_type' => 1,
        'discount_value' => 10,
        'min_spending' => 0,
        'max_usage_per_user' => 1,
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2027-12-31 23:59:59',
        'is_active' => 0,
    ]);
    $res = $voucherService->evaluate('KHOA10', [['product_id' => 1, 'total' => 200000]]);
    T::false($res['valid']);
    T::eq('Mã giảm giá hiện đang tạm khóa.', $res['error']);

    // 3. Voucher hết hạn
    App\Models\Voucher::updateById($vId, ['is_active' => 1, 'end_date' => '2025-01-01 00:00:00']);
    $res = $voucherService->evaluate('KHOA10', [['product_id' => 1, 'total' => 200000]]);
    T::false($res['valid']);
    T::true(str_contains($res['error'], 'hết hạn'));

    // 4. Chi tiêu tối thiểu
    App\Models\Voucher::updateById($vId, ['end_date' => '2027-12-31 23:59:59', 'min_spending' => 500000]);
    $res = $voucherService->evaluate('KHOA10', [['product_id' => 1, 'total' => 300000]]);
    T::false($res['valid']);
    T::true(str_contains($res['error'], 'chi tiêu tối thiểu'));

    // 5. Đạt chi tiêu tối thiểu
    $res = $voucherService->evaluate('KHOA10', [['product_id' => 1, 'total' => 600000]]);
    T::true($res['valid']);
    T::eq(60000, $res['discount']); // 10% của 600.000 = 60.000
});

T::test('Voucher: giảm theo phần trăm và số tiền cố định', function () use ($app) {
    $voucherService = $app->get(App\Services\VoucherService::class);

    // Phần trăm 15%
    App\Models\Voucher::create([
        'code' => 'PERCENT15',
        'voucher_type' => 1,
        'discount_type' => 1,
        'discount_value' => 15,
        'min_spending' => 0,
        'max_usage_per_user' => 5,
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2027-12-31 23:59:59',
        'is_active' => 1,
    ]);
    $res = $voucherService->evaluate('PERCENT15', [['product_id' => 1, 'total' => 200000]]);
    T::true($res['valid']);
    T::eq(30000, $res['discount']); // 15% của 200.000 = 30.000

    // Cố định 50.000đ
    App\Models\Voucher::create([
        'code' => 'FIXED50K',
        'voucher_type' => 1,
        'discount_type' => 2,
        'discount_value' => 50000,
        'min_spending' => 100000,
        'max_usage_per_user' => 5,
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2027-12-31 23:59:59',
        'is_active' => 1,
    ]);
    $res = $voucherService->evaluate('FIXED50K', [['product_id' => 1, 'total' => 200000]]);
    T::true($res['valid']);
    T::eq(50000, $res['discount']);

    // Giảm 30% nhưng tối đa 500.000đ trên đơn 10 triệu
    App\Models\Voucher::create([
        'code' => 'CAP30PERCENT',
        'voucher_type' => 1,
        'discount_type' => 1,
        'discount_value' => 30,
        'max_discount_amount' => 500000,
        'min_spending' => 0,
        'max_usage_per_user' => 5,
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2027-12-31 23:59:59',
        'is_active' => 1,
    ]);
    // Đơn 10.000.000đ: 30% là 3.000.000đ, nhưng trần tối đa là 500.000đ => giảm đúng 500.000đ
    $resCap = $voucherService->evaluate('CAP30PERCENT', [['product_id' => 1, 'total' => 10000000]]);
    T::true($resCap['valid']);
    T::eq(500000, $resCap['discount']);

    // Đơn 1.000.000đ: 30% là 300.000đ (chưa vượt trần 500.000đ) => giảm 300.000đ
    $resNoCap = $voucherService->evaluate('CAP30PERCENT', [['product_id' => 1, 'total' => 1000000]]);
    T::true($resNoCap['valid']);
    T::eq(300000, $resNoCap['discount']);
});

T::test('Voucher: giảm theo danh mục và theo sản phẩm', function () use ($app) {
    $voucherService = $app->get(App\Services\VoucherService::class);
    $db = $app->get(App\Core\Database::class);

    // Lấy 2 sản phẩm thuộc danh mục khác nhau
    $p1 = Product::find(1);
    $cat1 = (int) $p1['category_id'];

    // Tạo voucher theo danh mục cat1
    $vCatId = App\Models\Voucher::create([
        'code' => 'CATONLY',
        'voucher_type' => App\Models\VoucherType::CATEGORY,
        'discount_type' => App\Models\DiscountType::PERCENT,
        'discount_value' => 20,
        'min_spending' => 0,
        'max_usage_per_user' => 5,
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2027-12-31 23:59:59',
        'is_active' => 1,
    ]);
    App\Models\Voucher::syncCategories($vCatId, [$cat1]);

    // Giỏ hàng có sản phẩm cat1 (giá 100k) và sản phẩm danh mục khác (999) (giá 200k)
    $lines = [
        ['product_id' => 1, 'total' => 100000],
        ['product_id' => 999999, 'total' => 200000],
    ];
    $res = $voucherService->evaluate('CATONLY', $lines);
    T::true($res['valid']);
    // 20% chỉ tính trên sản phẩm của cat1 (100.000đ) => 20.000đ
    T::eq(20000, $res['discount']);

    // Giỏ hàng chỉ có sản phẩm không thuộc cat1
    $resNoCat = $voucherService->evaluate('CATONLY', [['product_id' => 999999, 'total' => 200000]]);
    T::false($resNoCat['valid']);
    T::true(str_contains($resNoCat['error'], 'danh mục'));

    // Voucher theo sản phẩm
    $vProdId = App\Models\Voucher::create([
        'code' => 'PRODONLY',
        'voucher_type' => App\Models\VoucherType::PRODUCT,
        'discount_type' => App\Models\DiscountType::FIXED,
        'discount_value' => 40000,
        'min_spending' => 0,
        'max_usage_per_user' => 5,
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2027-12-31 23:59:59',
        'is_active' => 1,
    ]);
    App\Models\Voucher::syncProducts($vProdId, [1]);

    $resProd = $voucherService->evaluate('PRODONLY', $lines);
    T::true($resProd['valid']);
    T::eq(40000, $resProd['discount']);

    $resNoProd = $voucherService->evaluate('PRODONLY', [['product_id' => 999999, 'total' => 200000]]);
    T::false($resNoProd['valid']);
    T::true(str_contains($resNoProd['error'], 'sản phẩm'));
});

T::test('Voucher: freeship giảm phí vận chuyển', function () use ($app) {
    $voucherService = $app->get(App\Services\VoucherService::class);
    // Freeship 100%
    App\Models\Voucher::create([
        'code' => 'FREESHIP100',
        'voucher_type' => App\Models\VoucherType::FREESHIP,
        'discount_type' => App\Models\DiscountType::PERCENT,
        'discount_value' => 100,
        'min_spending' => 0,
        'max_usage_per_user' => 5,
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2027-12-31 23:59:59',
        'is_active' => 1,
    ]);
    $res = $voucherService->evaluate('FREESHIP100', [['product_id' => 1, 'total' => 100000]]);
    T::true($res['valid']);
    $shippingFee = (int) $app->config()->get('shop.shipping_fee', 30000);
    T::eq($shippingFee, $res['discount']);
});

T::test('Voucher: tích hợp giỏ hàng và đặt hàng lưu voucher_code', function () use ($app) {
    $cart = $app->get(CartService::class);
    $voucherService = $app->get(App\Services\VoucherService::class);
    $orderService = $app->get(App\Services\OrderService::class);

    $cart->clear();
    $voucherService->remove();

    // Thêm sản phẩm áo thun (id: 1, giá 250k) số lượng 2 => subtotal 500k
    $cart->add(1, 2);
    $totalsBefore = $cart->totals();
    T::eq(0, $totalsBefore['discount']);

    // Tạo và áp voucher TESTCHECKOUT: giảm 50k
    App\Models\Voucher::create([
        'code' => 'CHECKOUT50',
        'voucher_type' => 1,
        'discount_type' => 2,
        'discount_value' => 50000,
        'min_spending' => 300000,
        'max_usage_per_user' => 1,
        'start_date' => '2026-01-01 00:00:00',
        'end_date' => '2027-12-31 23:59:59',
        'is_active' => 1,
    ]);

    $applyRes = $voucherService->apply('CHECKOUT50', $cart->lines());
    T::true($applyRes['success']);
    T::eq(50000, $applyRes['discount']);

    // Totals giỏ hàng tự động trừ discount qua filter cart.discount
    $totalsAfter = $cart->totals();
    T::eq(50000, $totalsAfter['discount']);
    T::eq($totalsBefore['subtotal'] - 50000 + $totalsAfter['shipping_fee'], $totalsAfter['total']);

    // Đặt hàng
    $order = $orderService->place([
        'name' => 'Nguyen Van A',
        'email' => 'nguyenvana@test.com',
        'phone' => '0912345678',
        'address' => 'Hanoi',
    ], $cart->raw(), 'cod');

    T::eq('CHECKOUT50', $order['voucher_code']);
    T::eq(50000, (int) $order['discount']);

    // Sau khi đặt hàng thành công, session voucher được dọn sạch
    T::eq(null, $voucherService->appliedCode());

    // Khách hàng này dùng lại mã CHECKOUT50 sẽ bị chặn vì max_usage_per_user = 1
    $reapply = $voucherService->apply('CHECKOUT50', $cart->lines(), null, 'nguyenvana@test.com', '0912345678');
    T::false($reapply['success']);
    T::true(str_contains($reapply['message'], 'hết giới hạn'));

    $cart->clear();
});

T::test('Admin: Quản lý voucher (CRUD, toggle, route)', function () use ($app) {
    $auth = $app->get(App\Services\Auth::class);
    $auth->attempt('admin@example.com', 'admin123');
    $token = $app->get(App\Core\Csrf::class)->token();

    // Xem danh sách voucher và trang tạo mới
    T::eq(200, $app->handle(Request::create('GET', '/admin/vouchers'))->status());
    T::eq(200, $app->handle(Request::create('GET', '/admin/vouchers/create'))->status());

    // Toggle kích hoạt
    $v = App\Models\Voucher::query()->first();
    T::true($v !== null);
    $originalActive = (int) $v['is_active'];

    $toggleRes = $app->handle(Request::create('POST', '/admin/vouchers/' . (int) $v['id'] . '/toggle', ['_token' => $token]));
    T::eq(302, $toggleRes->status());
    $vUpdated = App\Models\Voucher::find((int) $v['id']);
    T::eq(1 - $originalActive, (int) $vUpdated['is_active']);

    $auth->logout();
});

exit(T::run());
