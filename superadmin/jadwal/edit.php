<?php
session_start();
if (!isset($_SESSION['superadmin'])) {
    header("Location: ../../login.php");
    exit;
}
require '../../koneksi.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM jadwal WHERE id = ?");
$stmt->execute([$id]);
$jadwal = $stmt->fetch(PDO::FETCH_ASSOC);

// Ambil data dari database untuk dropdown
$mapels = $pdo->query("SELECT * FROM mapel");
$gurus = $pdo->query("SELECT * FROM guru");
$kelas = $pdo->query("SELECT * FROM class");

// Proses update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $id_mapel = $_POST['id_mapel']; 
    $teacher_id = $_POST['teacher_id'];
    $class_id = $_POST['class_id'];

    // Ambil nama mata pelajaran berdasarkan ID yang dipilih
    $stmt_mapel = $pdo->prepare("SELECT nama_mapel FROM mapel WHERE id = ?");
    $stmt_mapel->execute([$id_mapel]);
    $mapel_name = $stmt_mapel->fetchColumn();

    // -- PERBAIKAN DI SINI --
    // Tambahkan kolom `id_mapel` ke dalam query UPDATE
    $update = $pdo->prepare("UPDATE jadwal SET hari=?, jam_mulai=?, jam_selesai=?, mata_pelajaran=?, id_mapel=?, teacher_id=?, class_id=? WHERE id=?");
    $update->execute([$hari, $jam_mulai, $jam_selesai, $mapel_name, $id_mapel, $teacher_id, $class_id, $id]);

    header("Location: index.php");
    exit;
}

?>

<h2>Edit Jadwal</h2>
<form method="post">
    <label>Hari:
        <select name="hari" required>
            <?php foreach (['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'] as $h): ?>
                <option value="<?= $h ?>" <?= $jadwal['hari'] == $h ? 'selected' : '' ?>><?= $h ?></option>
            <?php endforeach; ?>
        </select>
    </label><br>

    <label>Jam Mulai: <input type="time" name="jam_mulai" value="<?= $jadwal['jam_mulai'] ?>" required></label><br>
    <label>Jam Selesai: <input type="time" name="jam_selesai" value="<?= $jadwal['jam_selesai'] ?>" required></label><br>
    
    <label>Mata Pelajaran:
        <select name="id_mapel" required>
            <option value="">-- Pilih Mata Pelajaran --</option>
            <?php
            foreach ($mapels as $m) {
                // Perbandingan nama mata pelajaran untuk 'selected'
                $sel = $jadwal['mata_pelajaran'] == $m['nama_mapel'] ? 'selected' : '';
                // Nilai option menggunakan ID mata pelajaran
                echo "<option value='{$m['id']}' $sel>{$m['nama_mapel']}</option>";
            }
            ?>
        </select>
    </label><br>

    <label>Guru:
        <select name="teacher_id">
            <?php
            foreach ($gurus as $g) {
                $sel = $jadwal['teacher_id'] == $g['id'] ? 'selected' : '';
                echo "<option value='{$g['id']}' $sel>{$g['name']}</option>";
            }
            ?>
        </select>
    </label><br>

    <label>Kelas:
        <select name="class_id">
            <?php
            foreach ($kelas as $k) {
                $sel = $jadwal['class_id'] == $k['id'] ? 'selected' : '';
                echo "<option value='{$k['id']}' $sel>{$k['nama_kelas']}</option>";
            }
            ?>
        </select>
    </label><br>

    <button type="submit">Update Jadwal</button>
</form>