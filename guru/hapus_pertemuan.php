<?php
include '../koneksi.php';

$id = $_GET['id'];
$id_jadwal = $_GET['id_jadwal'];

$stmt1 = $pdo->prepare("DELETE FROM absensi WHERE id_pertemuan = ?");
$stmt1->execute([$id]);
// Hapus pertemuan
$stmt2 = $pdo->prepare("DELETE FROM pertemuan WHERE id = ?");
$stmt2->execute([$id]);

header("Location: pertemuan_guru.php?id_jadwal=$id_jadwal");
exit;
?>
