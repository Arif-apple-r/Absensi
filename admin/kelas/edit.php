<?php
require '../../koneksi.php';
session_start();

// Cek login admin
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

// Ambil data berdasarkan ID
$id = $_GET['id'] ?? null;

if (!$id) {
    echo "ID tidak tersedia!";
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM class WHERE id = ?");
$stmt->execute([$id]);
$data = $stmt->fetch();

if (!$data) {
    echo "Data tidak ditemukan!";
    exit;
}

// Proses update saat form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_kelas   = $_POST['nama_kelas'];
    $tahun_ajaran = $_POST['tahun_ajaran'];
    $deskripsi    = $_POST['deskripsi'];
    $photo        = $_FILES['photo']['name'];

    // Upload file jika ada foto baru
    if (!empty($photo)) {
        $target_dir  = "../../uploads/kelas/";
        $target_file = $target_dir . basename($photo);

        // Hapus foto lama jika ada
        $old_photo_path = $target_dir . $data['photo'];
        if (file_exists($old_photo_path)) {
            unlink($old_photo_path); // hapus file lama
        }

        // Upload foto baru
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            $photo_path = basename($photo);
        } else {
            echo "Gagal upload foto!";
            exit;
        }
    } else {
        $photo_path = $data['photo']; // tetap pakai foto lama
    }

    $stmt = $pdo->prepare("
        UPDATE class 
        SET nama_kelas = ?, tahun_ajaran = ?, deskripsi = ?, photo = ? 
        WHERE id = ?
    ");
    $stmt->execute([$nama_kelas, $tahun_ajaran, $deskripsi, $photo_path, $id]);

    header("Location: index.php");
    exit;
}
?>

<!-- Form Edit Kelas -->
<h2>Edit Data Kelas</h2>
<form action="" method="post" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">

    <label>Nama Kelas:
        <input type="text" name="nama_kelas" value="<?= htmlspecialchars($data['nama_kelas']) ?>" required>
    </label><br>

    <label>Tahun Ajaran:
        <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($data['tahun_ajaran']) ?>">
    </label><br>

    <label>Deskripsi:
        <textarea name="deskripsi"><?= htmlspecialchars($data['deskripsi']) ?></textarea>
    </label><br>

    <label>Foto Lama:<br>
        <img src="../../uploads/kelas/<?= htmlspecialchars($data['photo']) ?>" width="80">
    </label><br>

    <label>Ganti Foto:
        <input type="file" name="photo">
    </label><br>

    <button type="submit">Update</button>
</form>