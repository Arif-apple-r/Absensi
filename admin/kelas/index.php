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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1abc9c;
            --secondary-color: #34495e;
            --background-color: #f0f2f5;
            --card-background: #ffffff;
            --text-color: #2c3e50;
            --light-text-color: #7f8c8d;
            --border-color: #e0e0e0;
            --shadow-color: rgba(0, 0, 0, 0.08);
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            display: flex;
            min-height: 100vh;
            color: var(--text-color);
            overflow-x: hidden;
        }

        /* Sidebar dan Header */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--secondary-color);
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            transition: width 0.3s ease;
            z-index: 1000;
            padding-top: 70px;
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar .logo {
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            padding: 15px 0;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            background: var(--primary-color);
        }

        .logo span {
            transition: font-size 0.3s ease;
        }

        .sidebar.collapsed .logo span {
            font-size: 0.5em;
            transition: font-size 0.3s ease;
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #fff;
            text-decoration: none;
            transition: background-color 0.2s ease, padding-left 0.2s ease;
        }

        .sidebar nav a i {
            width: 25px;
            text-align: center;
            margin-right: 20px;
            font-size: 18px;
        }

        .sidebar.collapsed nav a i {
            margin-right: 0;
        }

        .sidebar.collapsed nav a span {
            display: none;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background-color: #3e566d;
            padding-left: 25px;
        }

        .sidebar nav a.active i {
            color: var(--primary-color);
        }        

        .header {
            height: 65.5px;
            background-color: var(--card-background);
            box-shadow: 0 2px 10px var(--shadow-color);
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            z-index: 999;
            transition: left 0.3s ease, width 0.3s ease;
        }

        .header.shifted {
            left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
        }

        .content {
            flex-grow: 1;
            padding: 90px 30px 30px 30px;
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            max-width: 100%;
        }

        .content.shifted {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Tombol Toggle Sidebar */
        .toggle-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            margin-right: 20px;
            transition: background-color 0.3s;
        }

        .toggle-btn:hover {
            background-color: #16a085;
        }

        /* Konten Utama */
        .card {
            background: var(--card-background);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px var(--shadow-color);
            margin-bottom: 25px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .card h2 {
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
        }

        .add-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
        }

        .add-link:hover {
            background-color: #16a085;
            transform: translateY(-2px);
        }

        /* Modal */
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
            padding-top: 60px;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .close-btn {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 25px;
            font-size: 28px;
            font-weight: bold;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }

        .modal form {
            display: flex;
            flex-direction: column;
        }

        .modal label {
            margin-bottom: 5px;
            font-weight: 600;
        }

        .modal input[type="text"],
        .modal input[type="file"],
        .modal textarea {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
            width: 100%;
        }

        .modal textarea {
            height: 100px;
            resize: vertical;
        }

        .modal-buttons {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 15px;
        }

        .modal-buttons button {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            flex: 1;
        }

        .modal-buttons button:hover {
            background-color: #16a085;
        }

        .modal-buttons .btn-cancel {
            background-color: #e74c3c;
        }

        .modal-buttons .btn-cancel:hover {
            background-color: #c0392b;
        }

        .image-preview {
            margin-bottom: 15px;
            text-align: center;
        }

        .image-preview img {
            max-width: 150px;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .sidebar:not(.collapsed) {
                transform: translateX(-100%);
            }

            .sidebar.collapsed {
                transform: translateX(0);
            }

            .content,
            .header {
                margin-left: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding-left: 20px !important;
            }

            .sidebar.collapsed + .header,
            .sidebar.collapsed ~ .content {
                margin-left: var(--sidebar-collapsed-width) !important;
                left: var(--sidebar-collapsed-width) !important;
                width: calc(100% - var(--sidebar-collapsed-width)) !important;
            }
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
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .action-buttons a {
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: white;
            font-size: 0.9em;
            transition: background-color 0.3s;
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

        /* --- Penambahan CSS untuk Tombol Logout --- */
        .sidebar .logout-button-container {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            padding: 0 20px;
        }

        .sidebar .logout-button-container a {
            background-color: #e74c3c; /* Warna merah untuk Logout */
            color: white;
            font-weight: 600;
            text-align: center;
            border-radius: 8px;
            display: block;
            padding: 12px 20px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar .logout-button-container a:hover {
            background-color: #c0392b;
        }

        .sidebar.collapsed .logout-button-container {
            padding: 0;
        }

        .sidebar.collapsed .logout-button-container a span {
            display: none;
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
        <div class="logo"><span>AdminCoy</span></div>
        <nav>
            <a href="../dashboard_admin.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../guru/index.php">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Guru</span>
            </a>
            <a href="../siswa/index.php">
                <i class="fas fa-user-graduate"></i>
                <span>Siswa</span>
            </a>
            <a href="../jadwal/index.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal</span>
            </a>
            <a href="index.php" class="active">
                <i class="fas fa-school"></i>
                <span>Kelas</span>
            </a>
            <a href="../mapel/index.php">
                <i class="fas fa-book"></i>
                <span>Mata Pelajaran</span>
            </a>
            <div class="logout-button-container">
                <a href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Daftar Kelas</h1>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Data Kelas</h2>
            <a href="#" onclick="openModal('tambah')" class="add-link">
                <i class="fas fa-plus"></i> Tambah Kelas
            </a>
            
            <div class="element-grid">
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
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle"></h2>
            <form id="kelasForm" action="index.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="id" id="kelasId">
                <label>Nama Kelas: <input type="text" name="nama_kelas" id="nama_kelas" required></label>
                <label>Tahun Ajaran: <input type="text" name="tahun_ajaran" id="tahun_ajaran" placeholder="contoh: 2025/2026"></label>
                <label>Deskripsi: <textarea name="deskripsi" id="deskripsi"></textarea></label>
                <div id="fotoLamaContainer" class="image-preview" style="display:none;">
                    <label>Foto Lama:<br>
                        <img id="fotoLama" src="" alt="Foto Lama">
                    </label>
                </div>
                <label>Foto Kelas: <input type="file" name="photo" id="photo"></label>
                <div class="modal-buttons">
                    <button type="submit" id="submitButton"></button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
                </div>
            </form>
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

        const modal = document.getElementById('kelasModal');
        const modalTitle = document.getElementById('modalTitle');
        const form = document.getElementById('kelasForm');
        const submitButton = document.getElementById('submitButton');
        const fotoLamaContainer = document.getElementById('fotoLamaContainer');
        const fotoLama = document.getElementById('fotoLama');
        const photoInput = document.getElementById('photo');

        function openModal(action, id = '', nama = '', tahun = '', deskripsi = '', foto = '') {
            modal.style.display = 'block';

            if (action === 'tambah') {
                modalTitle.textContent = 'Tambah Kelas';
                form.action = 'index.php';
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
            modal.style.display = 'none';
        }

        function showLogoutConfirm() {
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah kamu yakin ingin logout?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Logout!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../../logout.php"; // redirect logout
                }
            });
        }

        // Tutup modal jika user klik di luar area modal
        window.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>