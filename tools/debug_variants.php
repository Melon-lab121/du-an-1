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

$variants41 = $pdo->query("SELECT * FROM product_variants WHERE product_id = 41")->fetchAll();
echo "Variants for product 41 count: " . count($variants41) . "\n";
print_r($variants41);

$options41 = $pdo->query("SELECT * FROM product_options")->fetchAll();
echo "Options count in product_options: " . count($options41) . "\n";
print_r($options41);
