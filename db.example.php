<?php
// Veritabanı bağlantı bilgileri (ÖRNEK)
// Bu dosyayı kopyalayıp db.php yapın ve kendi bilgilerinizi girin

$host = "localhost";      // MySQL sunucu
$db   = "DB_NAME";        // Veritabanı adı
$user = "DB_USER";        // Kullanıcı adı
$pass = "DB_PASSWORD";    // Şifre
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Veritabanına bağlanırken hata oluştu.");
}
