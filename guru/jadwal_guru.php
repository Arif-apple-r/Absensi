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
        // Log error jika ada masalah dengan database
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

// --- TENTUKAN NILAI FILTER YANG DIPILIH ---
$selected_tahun_akademik_id = filter_input(INPUT_GET, 'tahun_akademik_id', FILTER_SANITIZE_NUMBER_INT);

// Jika tidak ada filter tahun akademik yang dipilih, gunakan yang aktif
if (empty($selected_tahun_akademik_id)) {
    if (isset($pdo)) {
        $selected_tahun_akademik_id = getActiveTahunAkademikId($pdo);
    } else {
        $selected_tahun_akademik_id = null;
    }
}

$selected_kelas_id = filter_input(INPUT_GET, 'kelas_id', FILTER_SANITIZE_NUMBER_INT);

// --- AMBIL DATA UNTUK DROPDOWN FILTER ---
// Ambil daftar Tahun Akademik
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun, is_active FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar Kelas yang diajar oleh guru ini
$kelas_options = [];
// Cek jika ada tahun akademik yang dipilih sebelum mengambil data kelas
if ($selected_tahun_akademik_id) {
    $query_kelas_diajar = "SELECT DISTINCT c.id, c.nama_kelas
                           FROM jadwal AS j
                           JOIN class AS c ON j.class_id = c.id
                           WHERE j.teacher_id = ? AND c.id_tahun_akademik = ?
                           ORDER BY c.nama_kelas ASC";
    $stmt_kelas = mysqli_prepare($conn, $query_kelas_diajar);
    mysqli_stmt_bind_param($stmt_kelas, "ii", $guru_id, $selected_tahun_akademik_id);
    mysqli_stmt_execute($stmt_kelas);
    $result_kelas = mysqli_stmt_get_result($stmt_kelas);
    $kelas_options = mysqli_fetch_all($result_kelas, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_kelas);
} else {
    // Jika tidak ada tahun akademik yang dipilih, ambil semua kelas guru
    $query_kelas_diajar = "SELECT DISTINCT c.id, c.nama_kelas
                           FROM jadwal AS j
                           JOIN class AS c ON j.class_id = c.id
                           WHERE j.teacher_id = ?
                           ORDER BY c.nama_kelas ASC";
    $stmt_kelas = mysqli_prepare($conn, $query_kelas_diajar);
    mysqli_stmt_bind_param($stmt_kelas, "i", $guru_id);
    mysqli_stmt_execute($stmt_kelas);
    $result_kelas = mysqli_stmt_get_result($stmt_kelas);
    $kelas_options = mysqli_fetch_all($result_kelas, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_kelas);
}


$jadwal_mengajar = []; // Inisialisasi array untuk menampung data jadwal
$params = [$guru_id];
$types = "i";
$where_clauses = "j.teacher_id = ?";

if ($selected_tahun_akademik_id) {
    $where_clauses .= " AND c.id_tahun_akademik = ?";
    $params[] = $selected_tahun_akademik_id;
    $types .= "i";
}

if ($selected_kelas_id) {
    $where_clauses .= " AND j.class_id = ?";
    $params[] = $selected_kelas_id;
    $types .= "i";
}

// Query utama untuk mengambil jadwal mengajar dengan filter
$query = "
    SELECT
        j.id AS jadwal_id,
        j.hari,
        j.jam_mulai,
        j.jam_selesai,
        m.nama_mapel,
        c.nama_kelas,
        c.photo AS class_photo,
        g.photo AS guru_photo
    FROM jadwal AS j
    JOIN mapel AS m ON j.id_mapel = m.id
    JOIN class AS c ON j.class_id = c.id
    JOIN guru AS g ON j.teacher_id = g.id
    WHERE " . $where_clauses . "
    ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC;
";

$stmt = mysqli_prepare($conn, $query);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $jadwal_mengajar[] = $row;
        }
    } else {
        $error_message = "Gagal mengambil data jadwal: " . mysqli_error($conn);
    }
    mysqli_stmt_close($stmt);
} else {
    $error_message = "Gagal mempersiapkan statement: " . mysqli_error($conn);
}

// Perbaikan: Pastikan $error_message didefinisikan jika tidak ada error
$error_message = $error_message ?? '';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Mengajar - Dashboard Guru</title>
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
            <a href="jadwal_guru.php" class="active">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Mengajar</span>
            </a>
            <a href="rekap_absensi_guru.php">
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
        <h1>Jadwal Mengajar</h1>
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
            <div class="alert" id="successAlert">
                <p><?= htmlspecialchars($success_message) ?></p>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert" id="errorAlert">
                <p><?= htmlspecialchars($error_message) ?></p>
            </div>
        <?php endif; ?>

        <div class="card mb-6">
            <h2 class="text-xl font-bold mb-4">Filter Jadwal</h2>
            <form action="jadwal_guru.php" method="GET" class="flex flex-col md:flex-row gap-4">
                <div class="filter-group flex-1">
                    <label for="tahun_akademik_id" class="block text-gray-700 font-semibold mb-2">Tahun Akademik</label>
                    <select name="tahun_akademik_id" id="tahun_akademik_id" onchange="this.form.submit()" class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="">Semua Tahun Akademik</option>
                        <?php foreach ($tahun_akademik_options as $tahun): ?>
                            <option value="<?= htmlspecialchars($tahun['id']) ?>" <?= $selected_tahun_akademik_id == $tahun['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tahun['nama_tahun']) ?> <?= $tahun['is_active'] ? '(Aktif)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group flex-1">
                    <label for="kelas_id" class="block text-gray-700 font-semibold mb-2">Kelas</label>
                    <select name="kelas_id" id="kelas_id" class="w-full p-2 border border-gray-300 rounded-lg">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelas_options as $kelas): ?>
                            <option value="<?= htmlspecialchars($kelas['id']) ?>" <?= $selected_kelas_id == $kelas['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kelas['nama_kelas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full md:w-auto px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-300">
                        Filter
                    </button>
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
                                <th>Kelas</th>
                                <th>Hari</th>
                                <th>Waktu</th>
                                <th>Mata Pelajaran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal_mengajar as $jadwal): ?>
                                <tr>
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
                                    <td><?= htmlspecialchars($jadwal['hari']) ?></td>
                                    <td><?= htmlspecialchars(date("H:i", strtotime($jadwal['jam_mulai']))) . ' - ' . htmlspecialchars(date("H:i", strtotime($jadwal['jam_selesai']))) ?></td>
                                    <td><?= htmlspecialchars($jadwal['nama_mapel']) ?></td>
                                    <td class="action-buttons">
                                        <a href="pertemuan_guru.php?id_jadwal=<?= htmlspecialchars($jadwal['jadwal_id']) ?>" class="action-button btn-view">
                                            <i class="fas fa-eye"></i> Kelola Pertemuan
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
        const tahunAkademikSelect = document.getElementById('tahun_akademik_id');

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function() {
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            // Tutup dropdown jika user klik di luar area dropdown
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