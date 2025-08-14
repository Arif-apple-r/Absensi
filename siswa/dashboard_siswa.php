<?php
session_start();

// Validasi sesi siswa
// Pastikan hanya siswa yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['siswa_id']) || !is_numeric($_SESSION['siswa_id'])) {
    header("Location: ../login.php");
    exit;
}

// Sertakan file koneksi database Anda
require_once '../koneksi.php'; // Menggunakan require_once untuk mencegah multiple inclusion

// Ambil data siswa dari sesi
// Gunakan operator null coalescing (??) untuk menangani kasus jika kunci sesi tidak ada
$siswa_id = $_SESSION['siswa_id'];
$siswa_name = $_SESSION['siswa_name'] ?? 'Siswa';
$siswa_nis = $_SESSION['siswa_nis'] ?? 'N/A';
$siswa_class_id = $_SESSION['siswa_class_id'] ?? null;
$last_login = $_SESSION['last_login'] ?? 'Belum ada data login';
$siswa_photo_session = $_SESSION['siswa_photo'] ?? '';

// --- Bagian PHP untuk mengambil data ringkasan dashboard siswa ---
$total_mapel = 0;
$total_pertemuan_kelas = 0;
$rekap_absensi = [
    'Hadir' => 0,
    'Sakit' => 0,
    'Izin' => 0,
    'Alpha' => 0
];
$nama_kelas_siswa = 'Memuat...';

try {
    // Ambil Nama Kelas Siswa jika class ID tersedia
    if ($siswa_class_id) {
        $sql_nama_kelas = "SELECT nama_kelas FROM class WHERE id = ?";
        $stmt_nama_kelas = $pdo->prepare($sql_nama_kelas);
        $stmt_nama_kelas->execute([$siswa_class_id]);
        $kelas_data = $stmt_nama_kelas->fetch(PDO::FETCH_ASSOC);
        $nama_kelas_siswa = $kelas_data['nama_kelas'] ?? 'Tidak Ditemukan';

        // 1. Jumlah Mata Pelajaran yang diikuti siswa
        $sql_mapel = "SELECT COUNT(DISTINCT id_mapel) AS jumlah_mapel FROM jadwal WHERE class_id = ?";
        $stmt_mapel = $pdo->prepare($sql_mapel);
        $stmt_mapel->execute([$siswa_class_id]);
        $total_mapel = $stmt_mapel->fetch(PDO::FETCH_ASSOC)['jumlah_mapel'] ?? 0;

        // 2. Jumlah Total Pertemuan di kelas siswa
        $sql_pertemuan_kelas = "SELECT COUNT(p.id) AS jumlah_pertemuan FROM pertemuan AS p JOIN jadwal AS j ON p.id_jadwal = j.id WHERE j.class_id = ?";
        $stmt_pertemuan_kelas = $pdo->prepare($sql_pertemuan_kelas);
        $stmt_pertemuan_kelas->execute([$siswa_class_id]);
        $total_pertemuan_kelas = $stmt_pertemuan_kelas->fetch(PDO::FETCH_ASSOC)['jumlah_pertemuan'] ?? 0;
    }

    // 3. Rekap Absensi Siswa (Hadir, Sakit, Izin, Alpha)
    $sql_rekap_absensi = "SELECT status, COUNT(*) AS count FROM absensi WHERE id_siswa = ? GROUP BY status";
    $stmt_rekap_absensi = $pdo->prepare($sql_rekap_absensi);
    $stmt_rekap_absensi->execute([$siswa_id]);
    while ($row = $stmt_rekap_absensi->fetch(PDO::FETCH_ASSOC)) {
        // Mengisi array rekap_absensi dengan data dari database
        if (isset($rekap_absensi[$row['status']])) {
            $rekap_absensi[$row['status']] = $row['count'];
        }
    }
} catch (PDOException $e) {
    // Tangani error database dengan lebih elegan
    // Dalam produksi, log error ini, jangan tampilkan ke user
    die("Error mengambil data dari database: " . $e->getMessage());
}

$siswa_photo = '';
if (!empty($siswa_id)) {
    $stmt_siswa_photo = $pdo->prepare("SELECT photo FROM siswa WHERE id = ?");
    $stmt_siswa_photo->execute([$siswa_id]);
    $result = $stmt_siswa_photo->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $siswa_photo = htmlspecialchars($result['photo']);
    }
}


// Hitung total absensi yang tercatat
$total_absensi_tercatat = array_sum($rekap_absensi);

// Periksa apakah ada pesan sukses dari operasi sebelumnya
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
        .user-info .last-login {
            color: var(--light-text-color);
            font-size: 12px;
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
    </style>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">SiswaCoy</div>
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
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard Siswa</h1>
        <div class="user-info">
            <span id="teacherName"><?php echo htmlspecialchars($siswa_name); ?></span>
            <img
                onclick="toprofile()"
                src="../uploads/siswa/<?= $siswa_photo ?>"
                alt="Foto <?= htmlspecialchars($siswa_name) ?>"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/000000?text=SW';"
            >
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Selamat Datang, <?php echo htmlspecialchars($siswa_name); ?>!</h2>
            <div class="info-header">
                <p><strong>NIS:</strong> <?php echo htmlspecialchars($siswa_nis); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($nama_kelas_siswa); ?></p>
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

        function toprofile() {
            window.location.href = 'profil_siswa.php';
        }
    </script>
</body>
</html>