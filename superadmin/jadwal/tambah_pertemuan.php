<?php
include '../../koneksi.php';

$id_jadwal = $_GET['id_jadwal'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'];
    $topik = $_POST['topik'];

    $stmt = $pdo->prepare("INSERT INTO pertemuan (id_jadwal, tanggal, topik) VALUES (?, ?, ?)");
    $stmt->execute([$id_jadwal, $tanggal, $topik]);

    header("Location: pertemuan.php?id_jadwal=$id_jadwal");
    exit;
}
?>

<h2>Tambah Pertemuan</h2>
<form method="POST">
  Tanggal: <input type="date" name="tanggal" required><br>
  Topik: <textarea name="topik" required></textarea><br>
  <button type="submit">Simpan</button>
</form>
