<?php
$host = '127.0.0.1';
$port = 3306;
$dbName = 'noi_that_luxe';
$username = 'root';
$password = '';

$pdo = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$variants = $pdo->query("SELECT product_id, count(*) as c FROM product_variants GROUP BY product_id HAVING c > 1")->fetchAll();
echo "Products with > 1 variant in product_variants:\n";
foreach ($variants as $v) {
    $p = $pdo->query("SELECT name FROM products WHERE id = {$v['product_id']}")->fetch();
    echo "ID {$v['product_id']}: {$p['name']} ({$v['c']} variants)\n";
    $vRows = $pdo->query("SELECT * FROM product_variants WHERE product_id = {$v['product_id']}")->fetchAll();
    foreach ($vRows as $vr) {
        echo "   - Variant ID {$vr['id']}: price={$vr['price']}, image=" . substr($vr['image'], 0, 50) . "...\n";
    }
}
