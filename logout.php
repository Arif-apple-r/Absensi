<?php
session_start();

require_once($_SERVER['DOCUMENT_ROOT'] . '/AbsensiPKL/koneksi.php');
// Cek dan unset sesuai role yang aktif
if (isset($_SESSION['admin'])) {
    unset($_SESSION['admin']);
    $redirect = "login.php";
} elseif (isset($_SESSION['guru'])) {
    unset($_SESSION['guru']);
    $redirect = "login.php";
} elseif (isset($_SESSION['siswa'])) {
    unset($_SESSION['siswa']);
    $redirect = "login.php";
} else {
    $redirect = "login.php";
}

// Optional: hancurkan semua session
session_destroy();

header("Location: $redirect");
exit;
?>


