<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../index.php");
    exit;
}

require '../../koneksi.php';

if (isset($_POST['tambah_kelas'])) {
    $nama = $_POST['nama_kelas'];
    $tahun = $_POST['tahun_ajaran'];
    $deskripsi = $_POST['deskripsi'];

    // Upload file foto
    $foto = $_FILES['photo']['name'];
    $tmp = $_FILES['photo']['tmp_name'];
    $folder = "../../uploads/kelas/";

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $path_foto = $folder . $foto;
    move_uploaded_file($tmp, $path_foto);

    // Simpan ke database
    $stmt = $pdo->prepare("INSERT INTO class (nama_kelas, photo, tahun_ajaran, deskripsi) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nama, $foto, $tahun, $deskripsi]);

    header("Location: index.php");
    exit;
}
?>

<form action="" method="post" enctype="multipart/form-data">
    <label>Nama Kelas: <input type="text" name="nama_kelas" required></label><br>
    <label>Tahun Ajaran: <input type="text" name="tahun_ajaran" placeholder="contoh: 2025/2026"></label><br>
    <label>Deskripsi: <textarea name="deskripsi"></textarea></label><br>
    <label>Foto Kelas: <input type="file" name="photo"></label><br>
    <button type="submit" name="tambah_kelas">Simpan</button>
</form>

