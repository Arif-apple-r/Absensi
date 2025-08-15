<?php
$host = 'localhost';
$db   = 'absensi_siswa';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    // Aktifkan mode error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi MySQLi gagal: " . mysqli_connect_error());
}
?>
