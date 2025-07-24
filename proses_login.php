<?php
require_once 'koneksi.php';
session_start();

$email = $_POST['email'];
$pass = $_POST['password'];

// Cek di tabel admin dulu
$stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if ($admin && password_verify($pass, $admin['pass'])) {
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['username'];
    header("Location: regis.php");
    exit;
}

// Cek ke tabel guru juga
$stmt = $pdo->prepare("SELECT * FROM guru WHERE email = ?");
$stmt->execute([$email]);
$guru = $stmt->fetch();

if ($guru && password_verify($pass, $guru['pass'])) {
    $_SESSION['guru_id'] = $guru['id'];
    $_SESSION['guru_name'] = $guru['name'];
    header("Location: regis.php");
    exit;
}

// Kalau tidak ditemukan di admin, cek ke siswa
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE email = ?");
$stmt->execute([$email]);
$siswa = $stmt->fetch();

if ($siswa && password_verify($pass, $siswa['pass'])) {
    $_SESSION['siswa_id'] = $siswa['id'];
    $_SESSION['siswa_name'] = $siswa['name'];
    header("Location: dashboard.php");
    exit;
}

echo "Login gagal: Email atau password salah!";
?>
