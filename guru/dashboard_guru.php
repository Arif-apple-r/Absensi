<?php
// Pastikan semua error ditampilkan saat pengembangan
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Mulai sesi
session_start();

// Validasi otentikasi: pastikan hanya guru yang sudah login yang bisa mengakses halaman ini
// Gunakan exit; setelah header() untuk menghentikan eksekusi script
if (!isset($_SESSION['guru_id']) || empty($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

// Ambil ID guru dari sesi dan sanitasi
$guru_id = filter_var($_SESSION['guru_id'], FILTER_SANITIZE_NUMBER_INT);
$guru_id = (int) $guru_id; // Pastikan guru_id adalah integer

// Sertakan file koneksi database
// Pastikan path ini benar!
require '../koneksi.php';

// Periksa koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// --- PERBAIKAN UTAMA: MENGGUNAKAN PREPARED STATEMENT UNTUK MENCEGAH SQL INJECTION ---
// Prepared statement jauh lebih aman karena memisahkan kueri dari data
// Kueri SQL untuk mengambil nama dan foto guru berdasarkan ID
$sql_guru = "SELECT name, photo FROM guru WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql_guru);

// Inisialisasi variabel dengan nilai default
$guru_name = 'Guru';
$guru_photo = 'default.jpg'; // Gunakan nama file default untuk menghindari error path

if ($stmt) {
    // Bind parameter: "i" berarti variabel yang di-bind adalah integer
    mysqli_stmt_bind_param($stmt, "i", $guru_id);
    // Jalankan statement
    mysqli_stmt_execute($stmt);
    // Ambil hasilnya
    $res_guru = mysqli_stmt_get_result($stmt);

    if ($res_guru && mysqli_num_rows($res_guru) > 0) {
        // Ambil data guru dari hasil kueri
        $guru_data = mysqli_fetch_assoc($res_guru);
        // Masukkan data ke variabel, pastikan di-sanitize
        $guru_name = htmlspecialchars($guru_data['name']);
        $guru_photo = htmlspecialchars($guru_data['photo']);
    } else {
        // Jika data guru tidak ditemukan
        error_log("Data guru dengan ID $guru_id tidak ditemukan di database.");
    }
    // Tutup statement
    mysqli_stmt_close($stmt);
} else {
    // Handle error jika prepared statement gagal
    error_log("Gagal membuat prepared statement: " . mysqli_error($conn));
}

// --- Bagian PHP untuk mengambil data ringkasan dashboard dengan prepared statement ---
// Ini lebih aman, meskipun untuk COUNT() injection-nya lebih sulit, tapi ini adalah praktik yang baik.
function getCount($conn, $sql, $param, $type = "i") {
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $type, $param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = $result ? mysqli_fetch_assoc($result)['count'] : 0;
        mysqli_stmt_close($stmt);
        return $count;
    }
    error_log("Gagal membuat prepared statement: " . mysqli_error($conn));
    return 0;
}

$sql_kelas = "SELECT COUNT(DISTINCT class_id) AS count FROM jadwal WHERE teacher_id = ?";
$total_kelas = getCount($conn, $sql_kelas, $guru_id);

$sql_mapel = "SELECT COUNT(DISTINCT id_mapel) AS count FROM jadwal WHERE teacher_id = ?";
$total_mapel = getCount($conn, $sql_mapel, $guru_id);

// Perhatikan bahwa kueri ini lebih kompleks, kita perlu memastikan penanganannya benar
$sql_siswa = "SELECT COUNT(DISTINCT s.id) AS count FROM siswa AS s JOIN class AS c ON s.class_id = c.id JOIN jadwal AS j ON c.id = j.class_id WHERE j.teacher_id = ?";
$total_siswa = getCount($conn, $sql_siswa, $guru_id);

$sql_pertemuan = "SELECT COUNT(p.id) AS count FROM pertemuan AS p JOIN jadwal AS j ON p.id_jadwal = j.id WHERE j.teacher_id = ?";
$total_pertemuan = getCount($conn, $sql_pertemuan, $guru_id);

// Tidak perlu menutup koneksi secara manual jika script akan berakhir.
// mysqli_close($conn); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Guru</title>
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

        .sidebar nav a.deactive {
            background-color: #253340ff;
            pointer-events: none; /* Tambahan untuk menonaktifkan klik */
        }
        
        /* Tambahan untuk menghilangkan efek hover pada tautan deactive */
        .sidebar nav a.deactive:hover {
            background-color: #253340ff; /* Kembali ke warna asli */
            padding-left: 20px; /* Kembali ke padding asli */
            transition: none; /* Menonaktifkan transisi hover */
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
        .user-info:hover {
            cursor: pointer;
            color: var(--primary-color);
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
    <div class="sidebar" id="sidebar">
        <div class="logo">GuruCoy</div>
        <nav>
            <a href="#" class="active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_guru.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Mengajar</span>
            </a>
            <a href="pertemuan_guru.php" class="deactive">
                <i class="fas fa-clipboard-list"></i>
                <span>Pertemuan</span>
            </a>
            <a href="absensi_guru.php" class="deactive">
                <i class="fas fa-check-circle"></i>
                <span>Absensi</span>
            </a>
            <a href="rekap_absensi_guru.php">
                <i class="fas fa-chart-bar"></i>
                <span>Rekap Absensi</span>
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
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard Guru</h1>
        <div class="user-info" onclick="toprofile()">
            <span id="teacherName"><?php echo htmlspecialchars($guru_name); ?></span>
            <img
                onclick="toprofile()"
                src="../uploads/guru/<?= $guru_photo ?>"
                alt="Foto <?= $guru_name ?>"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/60x60/cccccc/333333?text=No+Foto';"
            >
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Ringkasan Data</h2>
            <div class="dashboard-stats-grid">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <p class="value" id="totalClasses"><?php echo htmlspecialchars($total_kelas); ?></p>
                    <p class="label">Kelas yang Diajar</p>
                </div>

                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-book-open"></i></div>
                    <p class="value" id="totalSubjects"><?php echo htmlspecialchars($total_mapel); ?></p>
                    <p class="label">Mata Pelajaran</p>
                </div>

                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-user-graduate"></i></div>
                    <p class="value" id="totalStudents"><?php echo htmlspecialchars($total_siswa); ?></p>
                    <p class="label">Jumlah Siswa</p>
                </div>

                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    <p class="value" id="totalMeetings"><?php echo htmlspecialchars($total_pertemuan); ?></p>
                    <p class="label">Total Pertemuan</p>
                </div>
            </div>

            <h2 style="margin-top: 30px;">Aksi Cepat</h2>
            <p style="color: var(--light-text-color); margin-bottom: 20px;">
                Gunakan tombol di bawah untuk navigasi cepat ke halaman manajemen.
            </p>
            <div>
                <button onclick="window.location.href='jadwal_guru.php';" class="action-button">
                    <i class="fas fa-calendar-alt"></i> Lihat Jadwal Mengajar
                </button>
                <button onclick="window.location.href='pertemuan_guru.php';" class="action-button">
                    <i class="fas fa-clipboard-list"></i> Kelola Pertemuan
                </button>
                <button onclick="window.location.href='absensi_guru.php';" class="action-button">
                    <i class="fas fa-check-circle"></i> Isi Absensi
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
            window.location.href = 'profil_guru.php';
        }
    </script>
</body>
</html>