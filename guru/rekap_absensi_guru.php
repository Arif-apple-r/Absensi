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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/userpage.css">
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