<?php
require '../../koneksi.php';
session_start();

// Cek sesi login admin
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

// Logika Tambah Mata Pelajaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_mapel'])) {
    $nama = trim($_POST['nama_mapel']);
    $kurikulum = $_POST['kurikulum'];

    // Validasi & Upload Foto
    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        // Handle error...
        exit;
    }
    if (!in_array($file['type'], $allowedTypes)) {
        // Handle error...
        exit;
    }
    if ($file['size'] > $maxSize) {
        // Handle error...
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('mapel_', true) . '.' . $ext;
    $uploadDir = realpath(__DIR__ . '/../../uploads/mapel');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("INSERT INTO mapel (nama_mapel, photo, kurikulum) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $filename, $kurikulum]);
    }

    header("Location: index.php");
    exit;
}

// Logika Edit Mata Pelajaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_mapel'])) {
    $id = $_POST['id'];
    $nama = trim($_POST['nama_mapel']);
    $kurikulum = $_POST['kurikulum'];
    $file = $_FILES['photo'];

    $photo_filename = $_POST['old_photo']; // Simpan foto lama

    // Cek jika ada foto baru diupload
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
            
            // Hapus foto lama jika ada
            if (!empty($photo_filename)) {
                $old_photo_path = '../../uploads/mapel/' . $photo_filename;
                if (file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }
            }

            // Upload foto baru
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('mapel_', true) . '.' . $ext;
            $uploadDir = realpath(__DIR__ . '/../../uploads/mapel');
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $photo_filename = $filename; // Update nama file foto
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE mapel SET nama_mapel = ?, photo = ?, kurikulum = ? WHERE id = ?");
    $stmt->execute([$nama, $photo_filename, $kurikulum, $id]);

    header("Location: index.php");
    exit;
}

// Ambil data mata pelajaran untuk ditampilkan
$mapel = $pdo->query("SELECT * FROM mapel")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Mata Pelajaran</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f4f4f4;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            transition: width 0.3s ease;
            z-index: 1000;
            padding-top: 60px;
            overflow-x: hidden;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
        }

        .sidebar a i {
            margin-right: 15px;
            min-width: 20px;
            text-align: center;
        }

        .sidebar.collapsed a span {
            display: none;
        }

        .sidebar a:hover {
            background-color: #34495e;
        }

        .sidebar .logo {
            color: white;
            font-size: 24px;
            text-align: center;
            position: absolute;
            top: 10px;
            left: 0;
            width: 100%;
        }

        .header {
            height: 60px;
            background-color: #1abc9c;
            color: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 250px;
            width: calc(100% - 250px);
            z-index: 999;
            transition: left 0.3s ease, width 0.3s ease;
        }

        .header.shifted {
            left: 70px;
            width: calc(100% - 70px);
        }

        .content {
            padding: 80px 20px 20px 20px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            width: 100%;
        }

        .content.shifted {
            margin-left: 70px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            max-width: 1000px;
            margin-inline: auto;
        }

        .card h2 {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .add-btn {
            background-color: #2ecc71;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
        }

        .add-btn:hover {
            background-color: #27ae60;
        }

        .element-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr); /* Membuat 2 kolom dengan lebar yang sama */
            gap: 20px;
        }

        .mapel-card {
            display: flex;
            width: 100%;
            background-color: #fff;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 16px;
            gap: 20px;
            align-items: center; /* Perbaikan agar elemen sejajar vertikal */
            position: relative;
            flex-wrap: nowrap;
        }

        .mapel-image-section {
            width: 150px;
            text-align: center;
        }

        .mapel-image-section img {
            width: 100%;
            border-radius: 12px;
            height: 100px; /* Menentukan tinggi gambar agar seragam */
            object-fit: cover;
        }

        .mapel-info-section {
            flex: 1;
        }

        .mapel-title {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .mapel-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            justify-content: center;
            align-self: center;
        }

        .mapel-actions a {
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .mapel-actions .edit-btn {
            background-color: #1abc9c;
        }

        .mapel-actions .delete-btn {
            background-color: #e74c3c;
        }

        .mapel-actions .edit-btn:hover {
            background-color: #16a085;
        }

        .mapel-actions .delete-btn:hover {
            background-color: #c0392b;
        }

        /* Modal styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1001; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            padding-top: 60px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 10px;
            position: relative;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-content form label {
            display: block;
            margin-bottom: 10px;
        }

        .modal-content form input,
        .modal-content form select,
        .modal-content form button {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 10px;
            box-sizing: border-box;
        }

        .modal-content form img {
            margin-top: 10px;
            margin-bottom: 10px;
        }

    </style>
</head>

<body>
    <?php
    require '../../koneksi.php';
    $mapel = $pdo->query("SELECT * FROM mapel")->fetchAll();
    ?>

    <div class="sidebar" id="sidebar">
        <div class="logo">Admin</div>
        <a href="#" id="toggle-btn"><i>☰</i><span>ㅤToggle</span></a>
        <a href="../dashboard_admin.php">📊<span>ㅤDashboard</span></a>
        <a href="../guru/index.php">👨‍🏫<span>ㅤGuru</span></a>
        <a href="../siswa/index.php">👨‍🎓<span>ㅤSiswa</span></a>
        <a href="../jadwal/index.php">📅<span>ㅤJadwal</span></a>
        <a href="../kelas/index.php">🏫<span>ㅤKelas</span></a>
        <a href="index.php">📚<span>ㅤMata Pelajaran</span></a>
    </div>

    <div class="header" id="header">
        <h1>Mata Pelajaran</h1>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Mata Pelajaran
                <button class="add-btn" onclick="openModal('tambah')">➕ Tambah</button>
            </h2>
            <div class="element-grid">
                <?php if (count($mapel) > 0): ?>
                    <?php foreach ($mapel as $m): ?>
                        <div class="mapel-card">
                            <div class="mapel-image-section">
                                <img src="../../uploads/mapel/<?= htmlspecialchars($m['photo']) ?>" alt="<?= htmlspecialchars($m['nama_mapel']) ?>">
                            </div>
                            <div class="mapel-info-section">
                                <h3 class="mapel-title"><?= htmlspecialchars($m['nama_mapel']) ?></h3>
                                <p>Kurikulum:</p>
                                <p><?= htmlspecialchars($m['kurikulum']) ?></p>
                                </div>
                                <div class="mapel-actions">
                                    <a href="#" class="edit-btn" onclick="openModal('edit', '<?= htmlspecialchars($m['id']) ?>', '<?= htmlspecialchars($m['nama_mapel']) ?>', '<?= htmlspecialchars($m['kurikulum']) ?>', '<?= htmlspecialchars($m['photo']) ?>')">✏️ Edit</a>
                                    <a href="hapus.php?id=<?= $m['id'] ?>" class="delete-btn" onclick="return confirm('Yakin ingin menghapus mata pelajaran ini?')">🗑️ Hapus</a>
                                </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Belum ada data mata pelajaran.</p>
                <?php endif; ?>
            </div>
            <div id="mapelModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal()">&times;</span>
                    <h2 id="modalTitle"></h2>
                    <form id="mapelForm" action="index.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="mapelId">
                        <input type="hidden" name="old_photo" id="oldPhoto">

                        <label>Nama Mapel: <input type="text" name="nama_mapel" id="nama_mapel" required></label><br>

                        <div id="fotoLamaContainer" style="display:none;">
                            <label>Foto Lama:<br>
                                <img id="fotoLama" src="" width="80" alt="Foto Lama">
                            </label>
                        </div>

                        <label>Foto: <input type="file" name="photo" id="photoInput" accept="image/*" required></label><br>

                        <label>Kurikulum: 
                            <select name="kurikulum" id="kurikulum" required>
                                <option value="K13">K13</option>
                                <option value="KTSP">KTSP</option>
                                <option value="Merdeka">Merdeka</option>
                            </select>
                        </label><br>
                        <button type="submit" id="submitButton"></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");

        function toggleSidebar() {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
        }

        const mapelModal = document.getElementById('mapelModal');
        const modalTitle = document.getElementById('modalTitle');
        const mapelForm = document.getElementById('mapelForm');
        const mapelId = document.getElementById('mapelId');
        const namaMapelInput = document.getElementById('nama_mapel');
        const kurikulumSelect = document.getElementById('kurikulum');
        const photoInput = document.getElementById('photoInput');
        const submitButton = document.getElementById('submitButton');
        const fotoLamaContainer = document.getElementById('fotoLamaContainer');
        const fotoLama = document.getElementById('fotoLama');
        const oldPhotoInput = document.getElementById('oldPhoto');

        function openModal(action, id = '', nama = '', kurikulum = '', photo = '') {
            mapelModal.style.display = 'block';

            if (action === 'tambah') {
                modalTitle.textContent = 'Tambah Mata Pelajaran';
                mapelForm.action = 'index.php';
                submitButton.name = 'tambah_mapel';
                submitButton.textContent = 'Simpan';
                mapelId.value = '';
                namaMapelInput.value = '';
                kurikulumSelect.value = 'K13';
                photoInput.required = true;
                fotoLamaContainer.style.display = 'none';
                oldPhotoInput.value = '';
            } else if (action === 'edit') {
                modalTitle.textContent = 'Edit Mata Pelajaran';
                mapelForm.action = 'index.php';
                submitButton.name = 'edit_mapel';
                submitButton.textContent = 'Update';
                mapelId.value = id;
                namaMapelInput.value = nama;
                kurikulumSelect.value = kurikulum;
                photoInput.required = false;
                fotoLamaContainer.style.display = 'block';
                fotoLama.src = `../../uploads/mapel/${photo}`;
                oldPhotoInput.value = photo;
            }
        }

        function closeModal() {
            mapelModal.style.display = 'none';
            mapelForm.reset(); // Reset form saat modal ditutup
        }

        // Tutup modal jika user klik di luar area modal
        window.onclick = function(event) {
            if (event.target === mapelModal) {
                closeModal();
            }
        }
    </script>
</body>
</html>