<?php
require_once 'koneksi.php';
session_start();

$email = $_POST['email'];
$pass = $_POST['password'];

// Cek ke superadmin
$stmt = $pdo->prepare("SELECT * FROM superadmin WHERE email = ?");
$stmt->execute([$email]);
$superadmin = $stmt->fetch();

if ($superadmin && password_verify($pass, $superadmin['pass'])) {
    $_SESSION['superadmin_id'] = $superadmin['id'];
    $_SESSION['superadmin_name'] = $superadmin['username'];
    $_SESSION['superadmin'] = $superadmin;
    header("Location: dashboard.php");
    exit;
}

// Cek di tabel admin
$stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
$stmt->execute([$email]);
$admin = $stmt->fetch();

if ($admin && password_verify($pass, $admin['pass'])) {
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['username'];
    $_SESSION['admin'] = $admin;
    header("Location: admin/dashboard_admin.php");
    exit;
}

// Cek ke tabel guru 
$stmt = $pdo->prepare("SELECT * FROM guru WHERE email = ?");
$stmt->execute([$email]);
$guru = $stmt->fetch();

if ($guru && password_verify($pass, $guru['pass'])) {
    $_SESSION['guru_id'] = $guru['id'];
    $_SESSION['guru_name'] = $guru['name'];
    $_SESSION['guru'] = $guru;
    header("Location: dashboard_guru.php");
    exit;
}

// cek ke siswa
$stmt = $pdo->prepare("SELECT * FROM siswa WHERE email = ?");
$stmt->execute([$email]);
$siswa = $stmt->fetch();

if ($siswa && password_verify($pass, $siswa['pass'])) {
    $_SESSION['siswa_id'] = $siswa['id'];
    $_SESSION['siswa_name'] = $siswa['name'];
    $_SESSION['siswa'] = $siswa;
    header("Location: dashboard_siswa.php");
    exit;
}

echo "Login gagal: Email atau password salah!";
?>
