<?php
include '../../koneksi.php';

$id = $_GET['id'];
$id_jadwal = $_GET['id_jadwal'];

$stmt = $pdo->prepare("SELECT * FROM pertemuan WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'];
    $topik = $_POST['topik'];

    $stmt = $pdo->prepare("UPDATE pertemuan SET tanggal = ?, topik = ? WHERE id = ?");
    $stmt->execute([$tanggal, $topik, $id]);

    header("Location: pertemuan.php?id_jadwal=$id_jadwal");
    exit;
}
?>

<h2>Edit Pertemuan</h2>
<form method="POST">
  Tanggal: <input type="date" name="tanggal" value="<?= $data['tanggal'] ?>" required><br>
  Topik: <textarea name="topik" required><?= $data['topik'] ?></textarea><br>
  <button type="submit">Simpan Perubahan</button>
</form>
