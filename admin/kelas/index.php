<?php
require '../../koneksi.php';
session_start();

// Redirect jika bukan admin
if (!isset($_SESSION['admin'])) {
    header("Location: ../../index.php");
    exit;
}

// Logika Tambah Kelas
if (isset($_POST['tambah_kelas'])) {
    $nama = $_POST['nama_kelas'];
    $tahun = $_POST['tahun_ajaran'];
    $deskripsi = $_POST['deskripsi'];

    $foto = $_FILES['photo']['name'];
    $tmp = $_FILES['photo']['tmp_name'];
    $folder = "../../uploads/kelas/";

    if (!is_dir($folder)) {
        mkdir($folder, 0777, true);
    }

    $path_foto = $folder . $foto;
    move_uploaded_file($tmp, $path_foto);

    $stmt = $pdo->prepare("INSERT INTO class (nama_kelas, photo, tahun_ajaran, deskripsi) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nama, $foto, $tahun, $deskripsi]);

    header("Location: index.php");
    exit;
}

// Logika Edit Kelas
if (isset($_POST['edit_kelas'])) {
    $id = $_POST['id'];
    $nama_kelas = $_POST['nama_kelas'];
    $tahun_ajaran = $_POST['tahun_ajaran'];
    $deskripsi = $_POST['deskripsi'];
    $photo = $_FILES['photo']['name'];

    $stmt = $pdo->prepare("SELECT photo FROM class WHERE id = ?");
    $stmt->execute([$id]);
    $old_photo = $stmt->fetchColumn();

    if (!empty($photo)) {
        $target_dir = "../../uploads/kelas/";
        $target_file = $target_dir . basename($photo);

        if ($old_photo && file_exists($target_dir . $old_photo)) {
            unlink($target_dir . $old_photo);
        }

        move_uploaded_file($_FILES['photo']['tmp_name'], $target_file);
        $photo_path = basename($photo);
    } else {
        $photo_path = $old_photo;
    }

    $stmt = $pdo->prepare("UPDATE class SET nama_kelas = ?, tahun_ajaran = ?, deskripsi = ?, photo = ? WHERE id = ?");
    $stmt->execute([$nama_kelas, $tahun_ajaran, $deskripsi, $photo_path, $id]);

    header("Location: index.php");
    exit;
}

// Ambil data kelas untuk ditampilkan
try {
    $kelas = $pdo->query("SELECT * FROM class ORDER BY nama_kelas ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Daftar Kelas</title>
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
            transition: background-color 0.3s ease;
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
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 25px; /* supaya ada jarak sebelum isi card */
        }

        .card-header h2 {
            margin: 0; /* Hilangkan margin default supaya posisinya rapi */
        }

        .card-header .btn-tambah {
            margin-bottom: 0; /* Override margin bawah dari default */
        }

        textarea {
            width: 100%; /* Set the width */
            height: 150px; /* Set the height */
            resize: none; /* Optional: Prevent resizing by the user */
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close {
            color: #aaa;
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .modal-content input,
        .modal-content label {
            width: 100%;
        }
        
        .modal-content input {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .modal-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
        }

        .modal-buttons button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background-color: #3498db;
            color: white;
            font-size: 16px;
            flex: 1;
            margin: 0 5px;
            cursor: pointer;
        }

        .modal-buttons button:hover {
            background-color: #2980b9;
        }

        .image-container {
            text-align: center;
            margin-bottom: 15px;
        }

        .image-container img {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            cursor: pointer;
            object-fit: cover;
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        .image-container img:hover {
            transform: scale(1.05);
        }

        .btn-edit {
            background-color: #1abc9c;
        }

        .btn-delete {
            background-color: #e74c3c;
        }

        .btn-tambah {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            margin-bottom: 15px;
            text-decoration: none; /* Tambahkan ini untuk link */
            display: inline-block;
        }

        .btn-tambah:hover {
            background-color: #2980b9;
        }

        .element-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2.5rem;
            text-align: center;
        }

        .element-item {
            background: #fff;
            border-radius: 16px;
            padding: 5px 5px 20px 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            cursor: pointer;
        }

        .element-item:hover {
            transform: translateY(-5px);
        }
        
        .element-item img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            max-width: 100%;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            transition: transform 0.4s ease, box-shadow 0.4s ease;
        }

        .element-item h3 {
            margin-bottom: 5px;
            font-size: 1.2em;
        }

        .element-item p {
            font-size: 0.9em;
            color: #777;
        }
        
        .action-buttons {
            margin-top: 15px;
        }

        .action-buttons a {
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            font-size: 0.9em;
        }

        .action-buttons .btn-edit {
            background-color: #3498db;
        }

        .action-buttons .btn-edit:hover {
            background-color: #2980b9;
        }

        .action-buttons .btn-delete {
            background-color: #e74c3c;
        }

        .action-buttons .btn-delete:hover {
            background-color: #c0392b;
        }
    </style>
</head>

<body>
    <?php
    require '../../koneksi.php';
    try {
        $kelas = $pdo->query("SELECT * FROM class ORDER BY nama_kelas ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    ?>

    <div class="sidebar" id="sidebar">
        <div class="logo">Admin</div>
        <a href="#" id="toggle-btn"><i>☰</i><span>ㅤToggle</span></a>
        <a href="../dashboard_admin.php">📊<span>ㅤDashboard</span></a>
        <a href="../guru/index.php">👨‍🏫<span>ㅤGuru</span></a>
        <a href="../siswa/index.php">👨‍🎓<span>ㅤSiswa</span></a>
        <a href="../jadwal/index.php">📅<span>ㅤJadwal</span></a>
        <a href="index.php">🏫<span>ㅤKelas</span></a>
        <a href="../mapel/index.php">📚<span>ㅤMata Pelajaran</span></a>
    </div>

    <div class="header" id="header">
        <h1>Daftar Kelas</h1>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <div class="card-header">
                <h2>Daftar Kelas</h2>
                <button class="btn-tambah" onclick="openModal('tambah')"><b>+</b> Tambah Kelas</button>
            </div>
            <div style="clear: both;"></div> <div class="element-grid">
                <?php foreach ($kelas as $row): ?>
                    <div class="element-item">
                        <img src="../../uploads/kelas/<?= htmlspecialchars($row['photo']) ?>" alt="<?= htmlspecialchars($row['nama_kelas']) ?>">
                        <h3><?= htmlspecialchars($row['nama_kelas']) ?></h3>
                        <p>Tahun Ajaran: <?= htmlspecialchars($row['tahun_ajaran']) ?></p>
                        <div class="action-buttons">
                            <a href="#" class="btn-edit" onclick="openModal('edit', '<?= htmlspecialchars($row['id']) ?>', '<?= htmlspecialchars($row['nama_kelas']) ?>', '<?= htmlspecialchars($row['tahun_ajaran']) ?>', '<?= htmlspecialchars($row['deskripsi']) ?>', '<?= htmlspecialchars($row['photo']) ?>')">Edit</a>
                            <a href="hapus.php?id=<?= htmlspecialchars($row['id']) ?>" class="btn-delete" onclick="return confirm('Yakin ingin menghapus kelas ini?')">Hapus</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="kelasModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle"></h2>
            <form id="kelasForm" action="index.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" id="kelasId">
                <label>Nama Kelas: <input type="text" name="nama_kelas" id="nama_kelas" required></label><br>
                <label>Tahun Ajaran: <input type="text" name="tahun_ajaran" id="tahun_ajaran" placeholder="contoh: 2025/2026"></label><br>
                <label>Deskripsi: <textarea name="deskripsi" id="deskripsi"></textarea></label><br>
                <div id="fotoLamaContainer" style="display:none;">
                    <label>Foto Lama:<br>
                        <img id="fotoLama" src="" width="80">
                    </label><br>
                </div>
                <label>Foto Kelas: <input type="file" name="photo" id="photo"></label><br>
                <div class="modal-buttons">
                    <button type="submit" id="submitButton"></button>
                    <button type="button" onclick="closeModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>
        
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById("sidebar");
            const content = document.getElementById("mainContent");
            const header = document.getElementById("header");
            sidebar.classList.toggle("collapsed");
            content.classList.toggle("shifted");
            header.classList.toggle("shifted");
        }

        function openModal(action, id = '', nama = '', tahun = '', deskripsi = '', foto = '') {
            const modal = document.getElementById('kelasModal');
            const modalTitle = document.getElementById('modalTitle');
            const form = document.getElementById('kelasForm');
            const submitButton = document.getElementById('submitButton');
            const fotoLamaContainer = document.getElementById('fotoLamaContainer');
            const fotoLama = document.getElementById('fotoLama');
            const photoInput = document.getElementById('photo');

            modal.style.display = 'block';

            if (action === 'tambah') {
                modalTitle.textContent = 'Tambah Kelas';
                form.action = 'index.php';
                form.name = 'tambah_kelas';
                document.getElementById('kelasId').value = '';
                document.getElementById('nama_kelas').value = '';
                document.getElementById('tahun_ajaran').value = '';
                document.getElementById('deskripsi').value = '';
                submitButton.name = 'tambah_kelas';
                submitButton.textContent = 'Simpan';
                fotoLamaContainer.style.display = 'none';
                photoInput.required = true;

            } else if (action === 'edit') {
                modalTitle.textContent = 'Edit Kelas';
                form.action = 'index.php';
                form.name = 'edit_kelas';
                document.getElementById('kelasId').value = id;
                document.getElementById('nama_kelas').value = nama;
                document.getElementById('tahun_ajaran').value = tahun;
                document.getElementById('deskripsi').value = deskripsi;
                submitButton.name = 'edit_kelas';
                submitButton.textContent = 'Update';
                fotoLamaContainer.style.display = 'block';
                fotoLama.src = `../../uploads/kelas/${foto}`;
                photoInput.required = false;
            }
        }

        function closeModal() {
            const modal = document.getElementById('kelasModal');
            modal.style.display = 'none';
        }

        // Tutup modal jika user klik di luar area modal
        window.onclick = function(event) {
            const modal = document.getElementById('kelasModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>