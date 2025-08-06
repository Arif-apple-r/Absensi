<?php
require '../../koneksi.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}

$NIS = $_GET['NIS'];

// Ambil data untuk hapus foto
$stmt = $pdo->prepare("SELECT photo FROM siswa WHERE NIS = ?");
$stmt->execute([$NIS]);
$data = $stmt->fetch();

if ($data && $data['photo']) {
    $path = "../../uploads/siswa/" . $data['photo'];
    if (file_exists($path)) {
        unlink($path);
    }
}

// Hapus kelas
$stmt = $pdo->prepare("DELETE FROM siswa WHERE NIS = ?");
$stmt->execute([$NIS]);

header("Location: index.php");
exit;
?>
