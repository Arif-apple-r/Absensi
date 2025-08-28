<?php
// Pastikan semua error ditampilkan saat pengembangan
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Validasi otentikasi guru
if (!isset($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

// Sertakan file koneksi database Anda
require_once '../koneksi.php';

// Inisialisasi variabel dari sesi
$guru_id = $_SESSION['guru_id'];
$guru_name = htmlspecialchars($_SESSION['guru_name'] ?? 'Guru');
$last_login = htmlspecialchars($_SESSION['last_login'] ?? 'Belum ada data login');

// Cek jika ada pesan sukses dari operasi sebelumnya
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

// --- PENGAMBILAN FOTO GURU DARI DATABASE MENGGUNAKAN PDO ---
$guru_photo_db = 'https://placehold.co/40x40/cccccc/333333?text=GR'; // Placeholder default
if (!empty($guru_id)) {
    try {
        $stmt_guru_photo = $pdo->prepare("SELECT photo FROM guru WHERE id = ?");
        $stmt_guru_photo->execute([$guru_id]);
        $result = $stmt_guru_photo->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['photo'])) {
            $photo_file = htmlspecialchars($result['photo']);
            $photo_path = '../uploads/guru/' . $photo_file;

            // Cek apakah file foto ada di direktori
            if (file_exists($photo_path)) {
                $guru_photo_db = $photo_path;
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching guru photo: " . $e->getMessage());
    }
}

// --- FUNGSI UNTUK MENDAPATKAN TAHUN AKADEMIK AKTIF ---
function getActiveTahunAkademikId($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'] ?? null;
    } catch (PDOException $e) {
        error_log("Error getting active academic year ID: " . $e->getMessage());
        return null;
    }
}

// Ambil semua daftar Tahun Akademik untuk filter dropdown
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun, is_active FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

// Tentukan tahun akademik yang sedang dipilih (dari GET atau default ke yang aktif)
$selected_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? null;

if ($selected_tahun_akademik_id === null) {
    $selected_tahun_akademik_id = getActiveTahunAkademikId($pdo);
}

// Inisialisasi variabel error
$error_message = '';
$jadwal_mengajar = [];

// Query untuk mengambil jadwal mengajar guru yang sedang login menggunakan PDO
if ($selected_tahun_akademik_id) {
    try {
        $query = "
            SELECT
                j.id AS jadwal_id,
                j.hari,
                j.jam_mulai,
                j.jam_selesai,
                m.nama_mapel,
                c.nama_kelas,
                c.photo AS class_photo,
                c.id_tahun_akademik
            FROM jadwal AS j
            JOIN mapel AS m ON j.id_mapel = m.id
            JOIN class AS c ON j.class_id = c.id
            WHERE j.teacher_id = ? AND c.id_tahun_akademik = ?
            ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai;
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$guru_id, $selected_tahun_akademik_id]);
        $jadwal_mengajar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error_message = "Gagal mengambil data jadwal: " . $e->getMessage();
        error_log($error_message);
    }
} else {
    $error_message = "Tidak ada tahun akademik yang dipilih.";
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Absensi - Dashboard Guru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS yang disalin dari jadwal_guru.php */
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
            transition: width 0.3s ease, transform 0.3s ease;
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

        .sidebar.collapsed .logo span {
            font-size: 0.5em;
            transition: font-size 0.3s ease;
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

        .sidebar nav a.deactive {
            background-color: #253340ff;
            pointer-events: none;
        }

        .sidebar nav a.deactive:hover {
            background-color: #253340ff;
            padding-left: 20px;
            transition: none;
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
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--text-color);
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }

        .user-info:hover {
            background-color: #f0f0f0;
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

        .user-info .last-login {
            color: var(--light-text-color);
            font-size: 12px;
            margin-left: 10px;
        }

        .user-info i.fa-caret-down {
            margin-left: 5px;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background-color: var(--card-background);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1002;
            min-width: 160px;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 10px;
        }

        .dropdown-menu a {
            color: var(--text-color);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }

        .dropdown-menu a:hover {
            background-color: var(--background-color);
        }

        .dropdown-menu a i {
            margin-right: 10px;
            width: 20px;
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

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background-color: #f8f8f8;
            font-weight: 600;
            color: var(--text-color);
            text-transform: uppercase;
        }

        .data-table tr:hover {
            background-color: #fafafa;
        }

        .action-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
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

        .btn-view {
            background-color: #3498db;
        }

        .btn-view:hover {
            background-color: #2980b9;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
        }

        .alert-success {
            color: #fff;
            background-color: #27ae60;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6fb;
        }

        .table-cell-with-image {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .class-photo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid #e0e0e0;
        }

        @media (max-width: 768px) {
            .sidebar:not(.collapsed) {
                transform: translateX(0);
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.collapsed {
                width: var(--sidebar-collapsed-width);
                transform: translateX(0);
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

            .sidebar.collapsed+.header,
            .sidebar.collapsed~.content {
                margin-left: var(--sidebar-collapsed-width) !important;
                left: var(--sidebar-collapsed-width) !important;
                width: calc(100% - var(--sidebar-collapsed-width)) !important;
            }
        }

        .sidebar .logout-button-container {
            position: absolute;
            bottom: 20px;
            left: 0;
            width: 100%;
            padding: 0 20px;
        }

        .sidebar .logout-button-container a {
            background-color: #e74c3c;
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
    <aside class="sidebar">
        <div class="logo">
            <span>GuruCoy</span>
        </div>
        <nav>
            <a href="dashboard_guru.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_guru.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Mengajar</span>
            </a>
            <a href="rekap_absensi_guru.php" class="active">
                <i class="fas fa-chart-bar"></i>
                <span>Rekap Absensi</span>
            </a>
            <div class="logout-button-container">
                <a href="#" id="logoutButton">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <header class="header" id="mainHeader">
        <button id="toggleSidebar" class="toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Rekap Absensi</h1>
        <div class="user-info">
            <span class="user-name"><?= htmlspecialchars($guru_name) ?></span>
            <img src="<?= $guru_photo_db ?>" alt="Foto Profil Guru" class="user-photo" id="userInfoDropdown">
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_guru.php"><i class="fas fa-user-circle"></i>Profil Saya</a>
                <a href="#" id="logoutDropdownLink"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>
    </header>

    <main class="content" id="mainContent">

        <?php if ($success_message): ?>
            <div class="alert alert-success" id="successAlert">
                <p><?= htmlspecialchars($success_message) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-error" id="errorAlert">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>

        <div class="card mb-6">
            <h2 class="text-xl font-bold mb-4">Filter Rekap Absensi</h2>
            <form action="rekap_absensi_guru.php" method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="filter-group flex-1">
                    <label for="tahunAkademikFilter" class="block text-gray-700 font-semibold mb-2">Tahun Akademik</label>
                    <select name="tahun_akademik_id" id="tahunAkademikFilter" onchange="this.form.submit()" class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="">Semua Tahun Akademik</option>
                        <?php foreach ($tahun_akademik_options as $tahun): ?>
                            <option value="<?= htmlspecialchars($tahun['id']) ?>" <?= $selected_tahun_akademik_id == $tahun['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tahun['nama_tahun']) ?> <?= $tahun['is_active'] ? '(Aktif)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <div class="card">
            <h2 class="text-xl font-bold mb-4">Daftar Jadwal Mengajar</h2>
            <div class="table-responsive">
                <?php if (empty($jadwal_mengajar)): ?>
                    <p class="text-center text-gray-500">Tidak ada jadwal mengajar yang ditemukan untuk filter yang dipilih.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hari</th>
                                <th>Waktu</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal_mengajar as $jadwal): ?>
                                <tr>
                                    <td><?= htmlspecialchars($jadwal['hari']) ?></td>
                                    <td><?= htmlspecialchars(date("H:i", strtotime($jadwal['jam_mulai']))) . ' - ' . htmlspecialchars(date("H:i", strtotime($jadwal['jam_selesai']))) ?></td>
                                    <td><?= htmlspecialchars($jadwal['nama_mapel']) ?></td>
                                    <td>
                                        <div class="table-cell-with-image">
                                            <?php
                                                $class_photo_path = '../uploads/kelas/' . htmlspecialchars($jadwal['class_photo']);
                                                $class_photo_src = file_exists($class_photo_path) && !empty($jadwal['class_photo']) ? $class_photo_path : 'https://placehold.co/50x50/e0e0e0/333333?text=' . substr(htmlspecialchars($jadwal['nama_kelas']), 0, 2);
                                            ?>
                                            <img src="<?= $class_photo_src ?>" alt="Foto Kelas" class="class-photo">
                                            <span><?= htmlspecialchars($jadwal['nama_kelas']) ?></span>
                                        </div>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="rekap_absensi_kelas.php?jadwal_id=<?= htmlspecialchars($jadwal['jadwal_id']) ?>" class="action-button btn-view">
                                            <i class="fas fa-eye"></i> Lihat Rekap
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Logika Sidebar
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.content');
        const mainHeader = document.querySelector('.header');
        const toggleSidebarBtn = document.getElementById('toggleSidebar');

        toggleSidebarBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('shifted');
            mainHeader.classList.toggle('shifted');
        });

        // Logika Dropdown User Info
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");
        const logoutButtonSidebar = document.getElementById('logoutButton');
        const logoutDropdownLink = document.getElementById('logoutDropdownLink');
        const tahunAkademikSelect = document.getElementById('tahunAkademikFilter');

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function(event) {
                event.stopPropagation();
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.onclick = function(event) {
                if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                    if (userDropdownContent.style.display === 'block') {
                        userDropdownContent.style.display = 'none';
                    }
                }
            };
        }

        // SweetAlert for Logout Confirmation
        function showLogoutConfirmation(event) {
            if (event) {
                event.preventDefault();
            }
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah kamu yakin ingin logout?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Logout!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../logout.php";
                }
            });
        }

        // Bind logout button to SweetAlert
        if (logoutButtonSidebar) {
            logoutButtonSidebar.addEventListener('click', showLogoutConfirmation);
        }
        if (logoutDropdownLink) {
            logoutDropdownLink.addEventListener('click', showLogoutConfirmation);
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener("DOMContentLoaded", () => {
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.display = 'none';
                }, 5000);
            }
            if (errorAlert) {
                setTimeout(() => {
                    errorAlert.style.display = 'none';
                }, 5000);
            }
        });
    </script>
</body>

</html>