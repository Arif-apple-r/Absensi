<?php
$host = 'localhost';
$db   = 'absensi_siswa';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal total: " . $e->getMessage());
}

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Koneksi MySQLi gagal total: " . mysqli_connect_error());
}
?>