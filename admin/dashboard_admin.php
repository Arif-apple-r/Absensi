<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require '../koneksi.php';

// Ambil nama dan foto admin jika ada di database
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$admin_photo = 'default_admin.jpg'; // opsional, bisa dari DB juga

// Fungsi getCount universal
function getCount($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $count = $result ? mysqli_fetch_assoc($result)['count'] : 0;
    return $count;
}

// Hitung total
$total_siswa = getCount($conn, "SELECT COUNT(*) AS count FROM siswa");
$total_guru = getCount($conn, "SELECT COUNT(*) AS count FROM guru");
$total_kelas = getCount($conn, "SELECT COUNT(*) AS count FROM class");
$total_mapel = getCount($conn, "SELECT COUNT(*) AS count FROM mapel");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Anda tidak saya ubah, sudah cukup baik dan responsif */
        /* ... (lanjutkan dengan semua kode CSS Anda) ... */
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
            justify-content: space-between;
        }
        .header.shifted {
            left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }
        .header h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }
        .header h1 i {
            margin-right: 10px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-color);
        }
        .user-info img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }
        .user-info span {
            font-weight: 600;
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
        .dashboard-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f8f8f8;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease-in-out;
            border-left: 5px solid;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-card.green { border-color: #27ae60; }
        .stat-card.blue { border-color: #3498db; }
        .stat-card.orange { border-color: #f39c12; }
        .stat-card.red { border-color: #e74c3c; }
        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        .stat-card.green .icon { color: #27ae60; }
        .stat-card.blue .icon { color: #3498db; }
        .stat-card.orange .icon { color: #f39c12; }
        .stat-card.red .icon { color: #e74c3c; }
        .stat-card .value {
            font-size: 2.2em;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        .stat-card .label {
            font-size: 0.9em;
            color: var(--light-text-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .action-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-right: 15px;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
            border: none;
            cursor: pointer;
        }
        .action-button:hover {
            background-color: #16a085;
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.collapsed {
                transform: translateX(0);
                width: var(--sidebar-collapsed-width);
            }
            .content,
            .header {
                margin-left: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding-left: 20px !important;
            }
            .header .user-info {
                display: none;
            }
            .sidebar.collapsed + .header,
            .sidebar.collapsed ~ .content {
                margin-left: var(--sidebar-collapsed-width) !important;
                left: var(--sidebar-collapsed-width) !important;
                width: calc(100% - var(--sidebar-collapsed-width)) !important;
            }
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
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>AdminCoy</span></div>
        <nav>
            <a href="#" class="active"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>            
            <a href="guru/index.php"><i class="fas fa-chalkboard-teacher"></i><span>Guru</span></a>
            <a href="siswa/index.php"><i class="fas fa-user-graduate"></i><span>Siswa</span></a>
            <a href="jadwal/index.php"><i class="fas fa-calendar-alt"></i><span>Jadwal</span></a>
            <a href="Tahun_Akademik/index.php"><i class="fas fa-calendar"></i><span>Tahun Akademik</span></a>
            <a href="kelas/index.php"><i class="fas fa-school"></i><span>Kelas</span></a>
            <a href="mapel/index.php"><i class="fas fa-book"></i><span>Mata Pelajaran</span></a>
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
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h1>
        <div class="user-info">
            <span><?= $admin_name ?></span>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Ringkasan Data</h2>
            <div class="dashboard-stats-grid">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-user-graduate"></i></div>
                    <p class="value"><?= $total_siswa ?></p>
                    <p class="label">Jumlah Siswa</p>
                </div>
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <p class="value"><?= $total_guru ?></p>
                    <p class="label">Jumlah Guru</p>
                </div>
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-school"></i></div>
                    <p class="value"><?= $total_kelas ?></p>
                    <p class="label">Jumlah Kelas</p>
                </div>
                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-book"></i></div>
                    <p class="value"><?= $total_mapel ?></p>
                    <p class="label">Jumlah Mapel</p>
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

        function showLogoutConfirmation() {
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah kamu yakin ingin logout?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Logout!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../logout.php"; // redirect logout
                }
            });
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