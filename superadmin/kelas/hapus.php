<?php
require '../../koneksi.php';
session_start();
if (!isset($_SESSION['superadmin'])) {
    header("Location: ../../login.php");
    exit;
}

$id = $_GET['id'];

// Ambil data untuk hapus foto
$stmt = $pdo->prepare("SELECT photo FROM class WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if ($data && $data['photo']) {
    $path = "../../uploads/kelas/" . $data['photo'];
    if (file_exists($path)) {
        unlink($path);
    }
}

// Hapus data

// Hapus semua jadwal yang terkait
$stmt = $pdo->prepare("DELETE FROM jadwal WHERE class_id = ?");
$stmt->execute([$id]);
// Hapus kelas
$stmt = $pdo->prepare("DELETE FROM class WHERE id = ?");
$stmt->execute([$id]);

header("Location: index.php");
exit;
?>
