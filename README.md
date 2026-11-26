# Base Shop — Base code TMĐT PHP thuần (không framework)

Bộ khung để bắt đầu các dự án website thương mại điện tử: kiến trúc MVC nhỏ gọn, có DI container,
router + middleware, query builder an toàn, hệ thống **Module + Event/Filter hook** để thêm tính năng
mà không sửa code lõi, và **thanh toán SePay (sandbox)** + COD.

- PHP >= 8.1 (XAMPP 8.2 trở lên), MySQL/MariaDB của XAMPP
- Không cần Composer (vẫn tự nạp `vendor/autoload.php` nếu sau này bạn cài thêm thư viện)

> **Làm việc nhóm — đọc trước khi code:**
> [CONTRIBUTING.md](CONTRIBUTING.md) (quy trình nhánh/commit/PR/review) ·
> [docs/CODING_STANDARDS.md](docs/CODING_STANDARDS.md) (chuẩn code, kiến trúc, bảo mật) ·
> [docs/AI_GUIDELINES.md](docs/AI_GUIDELINES.md) (quy định dùng AI) ·
> [AGENTS.md](AGENTS.md) (hướng dẫn cho AI coding agent) ·
> [docs/slides/bai-giang.html](docs/slides/bai-giang.html) (slide bài giảng: SePay, luật code, làm việc nhóm, deploy Cloudflare)

---

## 1. Chạy bằng XAMPP

1. Cài XAMPP (bản PHP 8.2+): https://www.apachefriends.org — mở **XAMPP Control Panel**, Start **Apache** và **MySQL**.
2. Đưa project vào `htdocs`. Chọn 1 trong 2 cách:
   - **Copy** thư mục `base` vào `C:\xampp\htdocs\base`, hoặc
   - **Giữ code ở Desktop** và tạo liên kết (mở CMD *Run as administrator*):
     ```bat
     mklink /J C:\xampp\htdocs\base C:\Users\Admin\Desktop\base
     ```
3. Kiểm tra file `.env` (đã tạo sẵn từ `.env.example`). Mặc định: `APP_URL=http://localhost/base`, DB `base_shop`, user `root`, không mật khẩu.
4. Tạo database + bảng + dữ liệu mẫu:
   ```bat
   cd C:\Users\Admin\Desktop\base
   C:\xampp\php\php.exe cli.php install
   ```
5. Mở http://localhost/base

| Tài khoản demo | Email | Mật khẩu |
|---|---|---|
| Quản trị | admin@example.com | admin123 |
| Khách hàng | customer@example.com | customer123 |

Trang quản trị: http://localhost/base/admin — **đổi mật khẩu admin trước khi đưa lên mạng.**

> Không muốn dùng Apache? Chạy `C:\xampp\php\php.exe cli.php serve 8000` và đặt `APP_URL=http://localhost:8000` (vẫn cần MySQL của XAMPP).

---

## 2. Đăng ký & cấu hình SePay Sandbox

1. Đăng ký tài khoản tại **https://my.sepay.vn**, vào **Payment Gateway → Register → Start now**, chọn bắt đầu với **Sandbox**.
2. Copy **`MERCHANT ID`** và **`SECRET KEY`**. SECRET KEY **chỉ hiện một lần**; mất thì phải tạo lại
   (Payment Gateway → Configuration → Merchant information) và key cũ hết hiệu lực ngay.
3. Vào **Payment Gateway → Configuration → IPN**, chọn kiểu xác thực **SECRET_KEY** và tự đặt một chuỗi ngẫu nhiên làm khoá IPN.
4. Điền vào `.env` (không điền vào `.env.example`):
   ```env
   SEPAY_ENV=sandbox
   SEPAY_MERCHANT_ID=SP-TEST-XXXXXXX
   SEPAY_SECRET_KEY=spsk_test_XXXXXXXXXXXXXXXX
   SEPAY_IPN_SECRET=chuoi-ngau-nhien-ban-tu-dat
   ```
   Khi chưa điền MERCHANT ID / SECRET KEY, SePay tự ẩn khỏi trang thanh toán (chỉ còn COD).
5. Sandbox là **giả lập**: không cần thẻ hay tài khoản ngân hàng thật, không mất tiền.

### Luồng SePay

1. Khách bấm **Đặt hàng** → Base tạo đơn + 1 dòng `payments` (pending) với `txn_ref` duy nhất làm `order_invoice_number`.
2. Base trả về trang có **form ẩn đã ký** (`SepayGateway::createCheckout()`), trình duyệt tự POST sang
   `https://pay-sandbox.sepay.vn/v1/checkout/init`.
3. Khách thanh toán trên trang SePay.
4. Server SePay gọi **IPN** `POST /payment/sepay/ipn` (JSON, header `X-Secret-Key`) → Base cập nhật đơn.
5. SePay đưa trình duyệt về `success_url` / `error_url` / `cancel_url` (kèm `?ref=<txn_ref>` do Base tự thêm).

Chữ ký form: HMAC-SHA256 (base64) trên chuỗi `field=value` nối bằng dấu phẩy, theo đúng thứ tự trường trong form.
Thuật toán chép từ SDK chính thức (`sepay/sepay-pg`, `sepay-pg-node`); test đối chiếu với vector sinh bằng SDK Node.
Số tiền gửi là **số nguyên VND, không nhân 100**.

### success_url và IPN — điều quan trọng cần hiểu

| | success_url `/payment/sepay/success` | IPN `/payment/sepay/ipn` |
|---|---|---|
| Ai gọi | Trình duyệt của khách (SePay redirect về) | Server SePay gọi thẳng server của bạn |
| Có xác thực? | **Không** (khách tự gõ URL cũng vào được) | Header `X-Secret-Key` = khoá IPN |
| Mục đích | Hiển thị kết quả | **Cập nhật trạng thái đơn chính thức** |
| Chạy được ở localhost? | Có | **Không** (cần URL public HTTPS) |

Vì vậy:
- **Khi dev ở localhost**: để `SEPAY_RECONCILE_ON_RETURN=true` → khi khách về success_url, server **gọi API SePay**
  (`GET https://pgapi-sandbox.sepay.vn/v1/order/detail/{txn_ref}`, Basic Auth) và chỉ cập nhật đơn khi SePay trả
  `order_status = CAPTURED` và số tiền khớp. Không bao giờ tin tham số trên URL.
- **Muốn test IPN thật**: mở tunnel (Cloudflare Tunnel / ngrok), đặt `APP_URL` = URL public, rồi khai báo
  IPN URL `https://<url-public>/payment/sepay/ipn` trên my.sepay.vn.
- **Production**: `SEPAY_ENV=production` + bộ khoá live, `SEPAY_RECONCILE_ON_RETURN=false`, `APP_DEBUG=false`, HTTPS.
- Nếu không nhận được IPN: vào **Admin → Đơn hàng → Lịch sử thanh toán → Đối soát** (gọi API chi tiết đơn của SePay).
- IPN trả `200 {"success":true}` khi đã nhận (kể cả gửi lặp). `401` sai khoá, `404` không có giao dịch,
  `422` sai số tiền là **quy ước của Base**; SePay chỉ yêu cầu 200.

Những điểm tài liệu SePay **chưa công bố** (đã xử lý an toàn trong code, nên chạy thử trước khi dạy):
schema đầy đủ của API chi tiết đơn (Base chỉ coi `CAPTURED` là đã thu tiền), chính sách gửi lại IPN,
và cách hoàn tất giao dịch trên trang sandbox. Kết quả thật của mọi lần đối soát được ghi trong `storage/logs/payment-*.log`.

---

## 3. Cấu trúc thư mục

```
base/
├── public/                 Document root (chỉ thư mục này lộ ra web)
│   ├── index.php           Front controller
│   ├── assets/css, js/     Giao diện (CSS/JS thuần, responsive, dark mode)
│   └── uploads/            Ảnh upload (đã chặn thực thi PHP)
├── app/
│   ├── Core/               "Framework" mini: App, Container, Router, Request/Response,
│   │                       Database, QueryBuilder, Model, View, Session, Csrf, Validator, Events, Logger
│   ├── Controllers/        Storefront, Admin/, Api/
│   ├── Middleware/         auth, guest, admin, csrf
│   ├── Models/             User, Category, Product, ProductVariant, Order, Payment
│   ├── Services/           Auth, CartService, OrderService, VariantService, PriceCalculator, Uploader
│   │   └── Payment/        PaymentGatewayInterface, PaymentManager, PaymentService, SepayGateway, CodGateway
│   ├── Providers/          Đăng ký service
│   └── helpers.php         url(), route(), e(), money(), old(), csrf_field()...
├── config/                 app, database, payment, shop, modules
├── routes/                 web.php, admin.php, api.php
├── views/                  Template PHP (layout + section + partial)
├── modules/                Tính năng mở rộng dạng plugin (Sample là ví dụ)
├── database/               migrations/*.sql, Migrator, Seeder
├── storage/logs/           app-*.log, payment-*.log (log giao dịch SePay)
├── tests/                  Bộ test không cần PHPUnit
└── cli.php                 install | migrate | seed | fresh --force | routes | serve | make:module
```

### Luồng một request
`public/index.php` → `bootstrap/app.php` (autoload, .env, config, provider, module) → `Router`
→ middleware (CSRF toàn cục + `auth`/`admin`... theo route) → Controller (tự inject service) → `View` → `Response`.

---

## 4. Phát triển thêm tính năng

### Thêm trang/route
```php
// routes/web.php
$router->get('/about', [PageController::class, 'about'])->name('about');
$router->group(['prefix' => '/account', 'middleware' => ['auth']], function (Router $r) {
    $r->get('/profile/{id:\d+}', [ProfileController::class, 'show']);   // int $id tự ép kiểu
});
```
Controller kế thừa `App\Core\Controller`, constructor khai báo service cần dùng là container tự inject.

### Thêm bảng
Tạo file mới `database/migrations/2026_02_01_000001_create_coupons_table.sql` rồi chạy `C:\xampp\php\php.exe cli.php migrate`.

### Viết module (khuyến nghị cho tính năng lớn)
```bat
C:\xampp\php\php.exe cli.php make:module Coupon
```
Thêm `'Coupon'` vào `config/modules.php`. Trong `modules/Coupon/Module.php` bạn có thể đăng ký route, view, migration riêng và **hook vào nghiệp vụ**:

| Hook | Loại | Dùng để |
|---|---|---|
| `cart.discount`, `order.discount` | filter | Mã giảm giá, khuyến mãi theo điều kiện |
| `order.created` | event | Gửi email, thông báo Telegram, đẩy sang kho |
| `order.paid`, `order.payment_failed` | event | Xuất hoá đơn, cộng điểm thành viên |
| `order.status_changed` | event | Thông báo cho khách khi giao hàng |
| `cart.added` | event | Tracking, gợi ý sản phẩm |

Xem ví dụ trong `modules/Sample/Module.php`.

### Thêm cổng thanh toán (MoMo, ZaloPay, Stripe...)
1. Tạo class `implements App\Services\Payment\PaymentGatewayInterface`.
2. Đăng ký trong provider/module: `$this->app->get(PaymentManager::class)->register(new MomoGateway($config));`
3. Thêm route callback/IPN của cổng đó. Trang checkout tự hiện cổng mới.

### Truy vấn dữ liệu
```php
Product::active()->where('price', '>=', 100000)->whereIn('category_id', [1, 2])
    ->orderBy('id', 'DESC')->paginate(12, $page);
app(Database::class)->transaction(fn (Database $db) => ...);
```

### Xoá mềm (soft delete)
Sản phẩm và biến thể **không bị DELETE** mà được đánh dấu `deleted_at`. Model bật `protected static bool $softDeletes = true;`:

| Gọi | Kết quả |
|---|---|
| `Product::query()`, `find()`, `active()` | Tự thêm `deleted_at IS NULL` (ẩn hàng đã xoá) |
| `Product::withTrashed()` / `onlyTrashed()` | Cả hàng đã xoá / chỉ hàng đã xoá (thùng rác) |
| `Product::deleteById($id)` | Đưa vào thùng rác, đổi slug/SKU (`--xoa-<id>`, `#<id>`) để nhả UNIQUE |
| `Product::restore($id)` | Khôi phục, trả slug/SKU gốc nếu chưa ai dùng |
| `Product::forceDeleteById($id)` | Xoá thật. Admin chỉ cho làm khi sản phẩm chưa có trong đơn nào |

**SQL viết tay (`$db->select`, `$db->table`) không đi qua Model nên phải tự thêm `deleted_at IS NULL`** — xem `Category::withProductCount()`.
Admin: **Sản phẩm → Thùng rác**.

### Biến thể sản phẩm động (Màu × Size...)
Trong trang sửa sản phẩm, mục **Phân loại hàng**, nhập mỗi dòng một thuộc tính: `Màu: Đen, Xám` / `Size: M, L, XL`.
Hệ thống sinh mọi tổ hợp (`VariantService::sync()`), mỗi tổ hợp có SKU, giá, tồn kho riêng.

- Bảng: `product_options` → `product_option_values`, `product_variants` ↔ `product_variant_values`.
- `products.price / sale_price / stock` của sản phẩm có biến thể là **cột tổng hợp** (giá thấp nhất, tổng tồn),
  cập nhật bằng `VariantService::refreshSummary()` trong cùng transaction.
- Bỏ một tổ hợp => biến thể bị **xoá mềm** (đơn cũ vẫn trỏ tới được); thêm lại => **khôi phục** cả giá, tồn kho cũ.
- Giỏ hàng: khoá dòng `"product_id:variant_id"`. Đơn hàng lưu `variant_id` + `variant_label` (ảnh chụp tại lúc mua).
- Form "Thêm vào giỏ" gửi `options[option_id] = value_id`, server tự tìm biến thể nên chạy được cả khi tắt JavaScript.
- Dữ liệu mẫu: **Áo hoodie nỉ Basic** (2 màu × 3 size).

Database cũ: chạy `C:\xampp\php\php.exe cli.php migrate` rồi `cli.php seed` để thêm bảng mới và sản phẩm mẫu.

---

## 5. Bảo mật đã có sẵn
- PDO prepared statement 100%; tên cột động được kiểm tra (chặn SQL injection qua sort/filter)
- Escape output bằng `e()`; CSRF token cho mọi form POST/PUT/DELETE (và header `X-CSRF-TOKEN` cho AJAX)
- `password_hash` + tự rehash, chống session fixation khi đăng nhập, cookie `HttpOnly` + `SameSite=Lax`
- Giá luôn tính lại từ DB phía server; trừ kho nguyên tử trong transaction (không bán quá tồn kho)
- SePay: form checkout ký HMAC-SHA256; IPN kiểm tra khoá `X-Secret-Key` + số tiền; xử lý idempotent (IPN gửi lặp không cộng 2 lần); đối soát qua API
- Upload: kiểm tra nội dung ảnh thật, tên file ngẫu nhiên, chặn chạy PHP trong `uploads/`
- Code, `.env`, `storage/` nằm ngoài document root

Nên bổ sung khi lên production: HTTPS, giới hạn số lần đăng nhập sai, gửi email, backup DB.

---

## 6. Kiểm thử
```bat
C:\xampp\php\php.exe tests\run.php
```
Chạy trên SQLite in-memory (cần `extension=pdo_sqlite` trong `C:\xampp\php\php.ini` — XAMPP thường đã bật),
không đụng database thật. Bao phủ: router, validator, tính giá, chữ ký SePay (đối chiếu với SDK chính thức),
đặt hàng/trừ kho/rollback, xoá mềm (thùng rác, bẫy UNIQUE, SQL viết tay), biến thể (tổ hợp, trừ kho theo biến thể,
khôi phục khi thêm lại), IPN (200/401/404/422, idempotent), đối soát, luồng HTTP checkout → SePay → success_url, phân quyền admin, API.

'cf_user'@'localhost' IDENTIFIED BY 'StrongPassword123!'

      "binding": "web_db",
      "database_name": "web-db",
      "database_id": "35ce4022-9711-4485-8702-e31dca403737"