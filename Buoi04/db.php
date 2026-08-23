<?php
$serverName = "localhost"; // IP máy chủ
$database   = "quanlylichhen"; // Tên cơ sở dữ liệu của bạn
$username   = "sa";        // Tài khoản SQL Server của bạn
$password   = "123456";    // Mật khẩu SQL Server của bạn

try {
    // Kết nối thông qua PDO SQLSRV
    $pdo = new PDO("sqlsrv:Server=$serverName;Database=$database", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Lỗi kết nối SQL Server: " . $e->getMessage());
}
?>