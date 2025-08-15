<?php
include '../../koneksi.php';

// Ambil data untuk dropdown
$kelas = $pdo->query("SELECT * FROM class ORDER BY nama_kelas ASC")->fetchAll();
$mapel = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC")->fetchAll();
$guru = $pdo->query("SELECT * FROM guru ORDER BY name ASC")->fetchAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $class_id = $_POST['class_id'] ?? '';
    $mapel_id = $_POST['mapel_id'] ?? '';
    $hari = $_POST['hari'] ?? '';
    $jam_mulai = $_POST['jam_mulai'] ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    $teacher_id = $_POST['teacher_id'] ?? '';

    // Validasi sederhana
    if ($class_id && $mapel_id && $hari && $jam_mulai && $jam_selesai && $teacher_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO jadwal (class_id, id_mapel, mata_pelajaran, hari, jam_mulai, jam_selesai, teacher_id) VALUES (?, ?, (SELECT nama_mapel FROM mapel WHERE id = ?), ?, ?, ?, ?)");
            $stmt->execute([$class_id, $mapel_id, $mapel_id, $hari, $jam_mulai, $jam_selesai, $teacher_id]);
            $success = "Jadwal berhasil ditambahkan!";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $error = "Gagal menambah jadwal: " . $e->getMessage();
        }
    } else {
        $error = "Semua field wajib diisi!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tambah Jadwal</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; }
        .container { max-width: 500px; margin: 40px auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);}
        h2 { text-align: center; }
        label { display: block; margin-top: 12px; }
        select, input[type="text"], input[type="time"] { width: 100%; padding: 8px; margin-top: 4px; border-radius: 4px; border: 1px solid #ccc; }
        button { margin-top: 16px; width: 100%; padding: 10px; background: #007bff; color: #fff; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;}
        button:hover { background: #0056b3; }
        .alert { padding: 10px; margin-bottom: 16px; border-radius: 4px;}
        .alert-success { background: #d4edda; color: #155724;}
        .alert-error { background: #f8d7da; color: #721c24;}
    </style>
</head>
<body>
<div class="container">
    <h2>Tambah Jadwal</h2>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" autocomplete="off">
        <label for="class_id">Kelas:</label>
        <select name="class_id" id="class_id" required>
            <option value="">--Pilih--</option>
            <?php foreach ($kelas as $k): ?>
                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="mapel_id">Mapel:</label>
        <select name="mapel_id" id="mapel_id" required>
            <option value="">--Pilih--</option>
            <?php foreach ($mapel as $m): ?>
                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="hari">Hari:</label>
        <select name="hari" id="hari" required>
            <option value="">--Pilih Hari--</option>
            <?php
            $days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            foreach ($days as $d): ?>
                <option value="<?= $d ?>"><?= $d ?></option>
            <?php endforeach; ?>
        </select>

        <label for="jam_mulai">Jam Mulai:</label>
        <input type="time" name="jam_mulai" id="jam_mulai" required>

        <label for="jam_selesai">Jam Selesai:</label>
        <input type="time" name="jam_selesai" id="jam_selesai" required>

        <label for="teacher_id">Guru:</label>
        <select name="teacher_id" id="teacher_id" required>
            <option value="">--Pilih--</option>
            <?php foreach ($guru as $g): ?>
                <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Simpan</button>
    </form>
</div>
</body>
</html>
