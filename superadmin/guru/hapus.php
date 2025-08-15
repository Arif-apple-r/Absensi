<?php
require '../../koneksi.php';
session_start();
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../login.php");
    exit;
}

$nip = $_GET['nip'] ?? null;

// Ambil data untuk hapus foto
$stmt = $pdo->prepare("SELECT photo FROM guru WHERE nip = ?");
$stmt->execute([$nip]);
$data = $stmt->fetch();

if ($data && $data['photo']) {
    $path = "../../uploads/guru/" . $data['photo'];
    if (file_exists($path)) {
        unlink($path);
    }
}

// Hapus kelas
$stmt = $pdo->prepare("DELETE FROM guru WHERE nip = ?");
$stmt->execute([$nip]);

header("Location: index.php");
exit;
?>