<?php
session_start();

// Validasi sesi siswa
if (!isset($_SESSION['siswa_id']) || !is_numeric($_SESSION['siswa_id'])) {
    header("Location: ../login.php");
    exit;
}

// Sertakan file koneksi database Anda
require_once '../koneksi.php';

// Ambil data siswa dari sesi
$siswa_id = $_SESSION['siswa_id'];
$siswa_name = $_SESSION['siswa_name'] ?? 'Siswa';
$siswa_nis = $_SESSION['siswa_nis'] ?? 'N/A';

// --- BAGIAN KODE YANG SUDAH DIPERBAIKI SECARA KESELURUHAN ---
// Mengambil data siswa, kelas, dan tahun akademik dalam satu query
$sql_siswa = "SELECT s.id, s.name AS nama, s.nis, s.class_id, s.photo,
                     c.nama_kelas, c.id_tahun_akademik,
                     ta.nama_tahun
              FROM siswa AS s
              LEFT JOIN class AS c ON s.class_id = c.id
              LEFT JOIN tahun_akademik AS ta ON c.id_tahun_akademik = ta.id
              WHERE s.id = ?";
$stmt_siswa = $pdo->prepare($sql_siswa);
$stmt_siswa->execute([$siswa_id]);
$siswa_data = $stmt_siswa->fetch(PDO::FETCH_ASSOC);

$siswa_photo = $siswa_data['photo'] ?? '';
$siswa_class_id = $siswa_data['class_id'] ?? null;
$nama_kelas_siswa = $siswa_data['nama_kelas'] ?? 'Tidak Ditemukan';
$nama_tahun_akademik = $siswa_data['nama_tahun'] ?? 'Tidak Ditemukan';
$id_tahun_akademik_siswa = $siswa_data['id_tahun_akademik'] ?? null;

// --- Bagian PHP untuk mengambil data ringkasan dashboard siswa ---
$total_mapel = 0;
$total_pertemuan_kelas = 0;
$rekap_absensi = [
    'Hadir' => 0,
    'Sakit' => 0,
    'Izin' => 0,
    'Alpha' => 0
];

try {
    if ($siswa_class_id && $id_tahun_akademik_siswa) {
        // 1. Jumlah Mata Pelajaran yang diikuti siswa (difilter berdasarkan tahun akademik)
        // Perbaikan: Gabungkan dengan tabel `class` untuk mendapatkan `id_tahun_akademik`
        $sql_mapel = "SELECT COUNT(DISTINCT j.id_mapel) AS jumlah_mapel
                      FROM jadwal AS j
                      JOIN class AS c ON j.class_id = c.id
                      WHERE c.id = ? AND c.id_tahun_akademik = ?";
        $stmt_mapel = $pdo->prepare($sql_mapel);
        $stmt_mapel->execute([$siswa_class_id, $id_tahun_akademik_siswa]);
        $total_mapel = $stmt_mapel->fetch(PDO::FETCH_ASSOC)['jumlah_mapel'] ?? 0;

        // 2. Jumlah Total Pertemuan di kelas siswa (difilter berdasarkan tahun akademik)
        // Perbaikan: Gabungkan dengan tabel `class` untuk mendapatkan `id_tahun_akademik`
        $sql_pertemuan_kelas = "SELECT COUNT(p.id) AS jumlah_pertemuan
                                FROM pertemuan AS p
                                JOIN jadwal AS j ON p.id_jadwal = j.id
                                JOIN class AS c ON j.class_id = c.id
                                WHERE c.id = ? AND c.id_tahun_akademik = ?";
        $stmt_pertemuan_kelas = $pdo->prepare($sql_pertemuan_kelas);
        $stmt_pertemuan_kelas->execute([$siswa_class_id, $id_tahun_akademik_siswa]);
        $total_pertemuan_kelas = $stmt_pertemuan_kelas->fetch(PDO::FETCH_ASSOC)['jumlah_pertemuan'] ?? 0;
    }

    // 3. Rekap Absensi Siswa (Hadir, Sakit, Izin, Alpha) (difilter berdasarkan tahun akademik)
    // Query ini sudah benar, tidak perlu diubah.
    $sql_rekap_absensi = "SELECT a.status, COUNT(*) AS count
                          FROM absensi AS a
                          JOIN pertemuan AS p ON a.id_pertemuan = p.id
                          JOIN jadwal AS j ON p.id_jadwal = j.id
                          JOIN class AS c ON j.class_id = c.id
                          WHERE a.id_siswa = ? AND c.id_tahun_akademik = ?
                          GROUP BY a.status";
    $stmt_rekap_absensi = $pdo->prepare($sql_rekap_absensi);
    $stmt_rekap_absensi->execute([$siswa_id, $id_tahun_akademik_siswa]);
    while ($row = $stmt_rekap_absensi->fetch(PDO::FETCH_ASSOC)) {
        if (isset($rekap_absensi[$row['status']])) {
            $rekap_absensi[$row['status']] = $row['count'];
        }
    }
} catch (PDOException $e) {
    die("Error mengambil data dari database: " . $e->getMessage());
}

$total_absensi_tercatat = array_sum($rekap_absensi);
$success_message = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Siswa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* User Info Dropdown Styling */
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
        .stat-card.blue { border-color: #3498db; }
        .stat-card.orange { border-color: #f39c12; }
        .stat-card.purple { border-color: #8e44ad; }
        .stat-card.teal { border-color: #1abc9c; }
        .stat-card.gray { border-color: #95a5a6; }
        .stat-card.dark-red { border-color: #c0392b; }
        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }
        .stat-card.blue .icon { color: #3498db; }
        .stat-card.orange .icon { color: #f39c12; }
        .stat-card.purple .icon { color: #8e44ad; }
        .stat-card.teal .icon { color: #1abc9c; }
        .stat-card.gray .icon { color: #95a5a6; }
        .stat-card.dark-red .icon { color: #c0392b; }
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
        .info-header {
            background: #e9ecef;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-color);
            color: var(--text-color);
            font-size: 0.95em;
        }
        .info-header p {
            margin: 5px 0;
            line-height: 1.5;
        }
        .info-header strong {
            color: var(--secondary-color);
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.collapsed {
                transform: translateX(0);
                width: var(--sidebar-collapsed-width);
            }
            .content, .header {
                margin-left: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding-left: 20px !important;
            }
            .header .user-info {
                display: none;
            }
            .sidebar.collapsed + .header, .sidebar.collapsed ~ .content {
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

        /* Tambahan: CSS untuk Info Tahun Akademik */
        .info-card {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #e9ecef;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid var(--primary-color);
        }
        .info-card .info-icon {
            font-size: 24px;
            color: var(--primary-color);
            margin-right: 15px;
        }
        .info-card .info-details h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--secondary-color);
        }
        .info-card .info-details p {
            margin: 0;
            font-size: 14px;
            color: var(--text-color);
        }
    </style>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>SiswaCoy</span></div>
        <nav>
            <a href="#" class="active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_siswa.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Saya</span>
            </a>
            <a href="absensi_siswa.php">
                <i class="fas fa-check-circle"></i>
                <span>Absensi Saya</span>
            </a>
            <div class="logout-button-container">
                <a onclick="showLogoutConfirmation()">
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
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard Siswa</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="siswaName"><?php echo htmlspecialchars($siswa_name); ?></span>
            <?php
            // Tampilkan foto profil siswa jika ada, jika tidak pakai placeholder
            $siswa_photo_src_header = !empty($siswa_photo) ? '../uploads/siswa/' . htmlspecialchars($siswa_photo) : 'https://placehold.co/40x40/cccccc/333333?text=GR';
            ?>
            <img src="<?php echo $siswa_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';">
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_siswa.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a onclick="showLogoutConfirmation()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Selamat Datang, <?php echo htmlspecialchars($siswa_name); ?>!</h2>
            <div class="info-header">
                <p><strong>NIS:</strong> <?php echo htmlspecialchars($siswa_nis); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($nama_kelas_siswa); ?></p>
            </div>
            
            <div class="info-card">
                <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="info-details">
                    <h3>Tahun Akademik</h3>
                    <p><?php echo htmlspecialchars($nama_tahun_akademik); ?></p>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <h2>Ringkasan Data Akademik</h2>
            <div class="dashboard-stats-grid">
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-book-open"></i></div>
                    <p class="value"><?php echo htmlspecialchars($total_mapel); ?></p>
                    <p class="label">Mata Pelajaran Diikuti</p>
                </div>

                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                    <p class="value"><?php echo htmlspecialchars($total_pertemuan_kelas); ?></p>
                    <p class="label">Total Pertemuan Kelas</p>
                </div>

                <div class="stat-card purple">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <p class="value"><?php echo htmlspecialchars($rekap_absensi['Hadir']); ?></p>
                    <p class="label">Kehadiran (Hadir)</p>
                </div>

                <div class="stat-card teal">
                    <div class="icon"><i class="fas fa-heartbeat"></i></div>
                    <p class="value"><?php echo htmlspecialchars($rekap_absensi['Sakit']); ?></p>
                    <p class="label">Kehadiran (Sakit)</p>
                </div>

                <div class="stat-card gray">
                    <div class="icon"><i class="fas fa-user-times"></i></div>
                    <p class="value"><?php echo htmlspecialchars($rekap_absensi['Izin']); ?></p>
                    <p class="label">Kehadiran (Izin)</p>
                </div>

                <div class="stat-card dark-red">
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                    <p class="value"><?php echo htmlspecialchars($rekap_absensi['Alpha']); ?></p>
                    <p class="label">Kehadiran (Alpha)</p>
                </div>
            </div>

            <h2>Akses Data Saya</h2>
            <p style="color: var(--light-text-color); margin-bottom: 20px;">
                Lihat detail jadwal dan rekap absensi Anda.
            </p>
            <div>
                <button onclick="window.location.href='jadwal_siswa.php';" class="action-button">
                    <i class="fas fa-calendar-alt"></i> Jadwal Saya
                </button>
                <button onclick="window.location.href='absensi_siswa.php';" class="action-button">
                    <i class="fas fa-check-circle"></i> Absensi Saya
                </button>
            </div>
        </div>
    </div>

    <script>
        // Logika untuk toggle sidebar
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");

        function toggleSidebar() {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
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
                    window.location.href = "../logout.php";
                }
            });
        }

        // Logika Dropdown User Info
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function() {
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.onclick = function(event) {
                if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                    if (userDropdownContent.style.display === 'block') {
                        userDropdownContent.style.display = 'none';
                    }
                }
            }
        }
    </script>
</body>
</html>