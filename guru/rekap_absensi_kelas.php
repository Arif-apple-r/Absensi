<?php
session_start();
// Pastikan hanya guru yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['guru_id'])) {
    header("Location: ../login.php"); // Sesuaikan path ke halaman login Anda
    exit;
}

// Ambil ID guru dari sesi
$guru_id = $_SESSION['guru_id'];
$guru_name = $_SESSION['guru_name'] ?? 'Guru'; // Default jika nama tidak ada di sesi
$last_login = $_SESSION['last_login'] ?? 'Belum ada data login'; // Default jika waktu login tidak ada

// Sertakan file koneksi database Anda
require '../koneksi.php'; // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

// Ambil class_id dan mapel_id dari URL
$class_id = $_GET['class_id'] ?? null;
$mapel_id = $_GET['mapel_id'] ?? null;

// Redirect jika ID tidak valid
if (!$class_id || !$mapel_id) {
    header("Location: rekap_absensi_guru.php?error=" . urlencode("ID Kelas atau ID Mata Pelajaran tidak valid."));
    exit;
}

// Verifikasi bahwa guru ini memiliki akses ke kombinasi kelas/mata pelajaran ini
$stmt_verify_access = $pdo->prepare("SELECT COUNT(*) FROM jadwal WHERE class_id = ? AND id_mapel = ? AND teacher_id = ?");
$stmt_verify_access->execute([$class_id, $mapel_id, $guru_id]);
if ($stmt_verify_access->fetchColumn() == 0) {
    header("Location: rekap_absensi_guru.php?error=" . urlencode("Anda tidak memiliki akses ke kelas atau mata pelajaran ini."));
    exit;
}

// Ambil informasi nama kelas dan mata pelajaran untuk ditampilkan
$stmt_info = $pdo->prepare("
    SELECT c.nama_kelas, m.nama_mapel
    FROM class AS c, mapel AS m
    WHERE c.id = ? AND m.id = ?
");
$stmt_info->execute([$class_id, $mapel_id]);
$info = $stmt_info->fetch(PDO::FETCH_ASSOC);

$nama_kelas = $info['nama_kelas'] ?? 'N/A';
$nama_mapel = $info['nama_mapel'] ?? 'N/A';

// Dapatkan total pertemuan yang tersedia untuk kelas dan mata pelajaran ini
$stmt_total_meetings = $pdo->prepare("
    SELECT COUNT(p.id) AS total_meetings
    FROM pertemuan AS p
    JOIN jadwal AS j ON p.id_jadwal = j.id
    WHERE j.class_id = ? AND j.id_mapel = ?
");
$stmt_total_meetings->execute([$class_id, $mapel_id]);
$total_available_meetings = $stmt_total_meetings->fetch(PDO::FETCH_ASSOC)['total_meetings'] ?? 0;


// Query untuk mendapatkan rekap absensi setiap siswa di kelas ini untuk mata pelajaran ini
$query_rekap = "
    SELECT
        s.id AS siswa_id,
        s.NIS,
        s.name AS nama_siswa,
        COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) AS count_hadir,
        COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) AS count_sakit,
        COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) AS count_izin,
        COUNT(CASE WHEN a.status = 'Alpha' THEN 1 END) AS count_alpha,
        COUNT(a.id) AS total_absensi_tercatat_siswa -- Jumlah absensi yang tercatat untuk siswa ini di mapel ini
    FROM
        siswa AS s
    LEFT JOIN
        absensi AS a ON s.id = a.id_siswa
    LEFT JOIN
        pertemuan AS p ON a.id_pertemuan = p.id
    LEFT JOIN
        jadwal AS j ON p.id_jadwal = j.id
    WHERE
        s.class_id = ? AND j.id_mapel = ?
    GROUP BY
        s.id, s.NIS, s.name
    ORDER BY
        s.name ASC;
";
$stmt_rekap = $pdo->prepare($query_rekap);
$stmt_rekap->execute([$class_id, $mapel_id]);
$rekap_absensi_siswa = $stmt_rekap->fetchAll(PDO::FETCH_ASSOC);


// Untuk memastikan semua siswa di kelas tampil, bahkan yang belum punya absensi
$stmt_all_students_in_class = $pdo->prepare("SELECT id, NIS, name FROM siswa WHERE class_id = ? ORDER BY name ASC");
$stmt_all_students_in_class->execute([$class_id]);
$all_students_in_class = $stmt_all_students_in_class->fetchAll(PDO::FETCH_ASSOC);

$final_rekap_data = [];
$rekap_indexed_by_siswa_id = [];
foreach ($rekap_absensi_siswa as $row) {
    $rekap_indexed_by_siswa_id[$row['siswa_id']] = $row;
}

// Gabungkan semua siswa dengan data absensi mereka (jika ada)
foreach ($all_students_in_class as $student) {
    $siswa_id_current = $student['id'];
    if (isset($rekap_indexed_by_siswa_id[$siswa_id_current])) {
        $final_rekap_data[] = $rekap_indexed_by_siswa_id[$siswa_id_current];
    } else {
        // Siswa ada di kelas ini tapi belum memiliki catatan absensi untuk mapel ini
        $final_rekap_data[] = [
            'siswa_id' => $siswa_id_current,
            'NIS' => $student['NIS'],
            'nama_siswa' => $student['name'],
            'count_hadir' => 0,
            'count_sakit' => 0,
            'count_izin' => 0,
            'count_alpha' => 0,
            'total_absensi_tercatat_siswa' => 0 // Ini akan selalu 0 jika tidak ada absensi
        ];
    }
}

$message = '';
$alert_type = '';
if (isset($_GET['success'])) {
    $message = htmlspecialchars($_GET['success']);
    $alert_type = 'alert-success';
} elseif (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $alert_type = 'alert-error';
}

// Ambil foto guru dari database untuk header
$guru_photo = '';
if (!empty($guru_id)) {
    $stmt_guru_photo = $pdo->prepare("SELECT photo FROM guru WHERE id = ?");
    $stmt_guru_photo->execute([$guru_id]);
    $result = $stmt_guru_photo->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $guru_photo = htmlspecialchars($result['photo']);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rekap Absensi <?php echo htmlspecialchars($nama_kelas); ?> - <?php echo htmlspecialchars($nama_mapel); ?> | Guru</title>
    <!-- Font Awesome untuk ikon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS dari file admin/guru Anda */
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

        /* Reset dan Dasar */
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

        /* Sidebar */
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

        /* Header */
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
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
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


        /* Konten Utama */
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

        /* Info Header for context */
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

        /* Gaya Tabel */
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

        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .data-table tr:hover {
            background-color: #fafafa;
        }
        
        /* Status Absensi Styling (untuk highlight angka) */
        .status-hadir-count { color: #27ae60; font-weight: 600; } /* Green */
        .status-sakit-count { color: #e67e22; font-weight: 600; } /* Orange */
        .status-izin-count { color: #3498db; font-weight: 600; } /* Blue */
        .status-alpha-count { color: #e74c3c; font-weight: 600; } /* Red */


        /* Alerts */
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
            border: 1px solid #f5c6fb;
        }

        /* Media Queries untuk Responsivitas */
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
            .header .user-info .last-login {
                display: none;
            }
            .sidebar.collapsed + .header, .sidebar.collapsed ~ .content {
                margin-left: var(--sidebar-collapsed-width) !important;
                left: var(--sidebar-collapsed-width) !important;
                width: calc(100% - var(--sidebar-collapsed-width)) !important;
            }
             .data-table th,
            .data-table td {
                padding: 10px; /* Kurangi padding untuk layar kecil */
                font-size: 0.85em; /* Kecilkan font */
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">GuruCoy</div>
        <nav>
            <a href="dashboard_guru.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_guru.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Mengajar</span>
            </a>
            <a href="pertemuan_guru.php">
                <i class="fas fa-clipboard-list"></i>
                <span>Pertemuan</span>
            </a>
            <a href="absensi_guru.php">
                <i class="fas fa-check-circle"></i>
                <span>Absensi</span>
            </a>
            <a href="rekap_absensi_guru.php" class="active">
                <i class="fas fa-chart-bar"></i>
                <span>Rekap Absensi</span>
            </a>
        </nav>
    </div>

    <!-- Header -->
    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-chart-bar"></i> Rekap Absensi</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="guruName"><?php echo htmlspecialchars($guru_name); ?></span>
            <?php
            $guru_photo_src_header = !empty($guru_photo) ? '../uploads/guru/' . htmlspecialchars($guru_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
            ?>
            <img src="<?php echo $guru_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';"
            >
            <div class="last-login">Terakhir Login: <span id="lastLogin"><?php echo htmlspecialchars($last_login); ?></span></div>
            <i class="fas fa-caret-down"></i>

            <!-- Dropdown Menu -->
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_guru.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Konten Utama -->
    <div class="content" id="mainContent">
        <div class="card">
            <h2>Rekap Absensi Kelas <?php echo htmlspecialchars($nama_kelas); ?> - <?php echo htmlspecialchars($nama_mapel); ?></h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="info-header">
                <p>Total Pertemuan Mata Pelajaran Ini: <strong><?php echo htmlspecialchars($total_available_meetings); ?></strong></p>
            </div>

            <?php if (empty($final_rekap_data)): ?>
                <div class="info-header">
                    <p>Tidak ada siswa di kelas ini atau tidak ada data absensi yang tercatat untuk mata pelajaran ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Hadir</th>
                                <th>Sakit</th>
                                <th>Izin</th>
                                <th>Alpha</th>
                                <th>Total Tercatat</th>
                                <th>Persentase Hadir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($final_rekap_data as $siswa_rekap): ?>
                                <?php
                                    $persentase_hadir = 0;
                                    if ($total_available_meetings > 0) {
                                        $persentase_hadir = ($siswa_rekap['count_hadir'] / $total_available_meetings) * 100;
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($siswa_rekap['NIS']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa_rekap['nama_siswa']); ?></td>
                                    <td class="status-hadir-count"><?php echo htmlspecialchars($siswa_rekap['count_hadir']); ?></td>
                                    <td class="status-sakit-count"><?php echo htmlspecialchars($siswa_rekap['count_sakit']); ?></td>
                                    <td class="status-izin-count"><?php echo htmlspecialchars($siswa_rekap['count_izin']); ?></td>
                                    <td class="status-alpha-count"><?php echo htmlspecialchars($siswa_rekap['count_alpha']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa_rekap['total_absensi_tercatat_siswa']); ?></td>
                                    <td><?php echo number_format($persentase_hadir, 2); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <a href="rekap_absensi_guru.php" class="back-link" style="margin-top: 20px;">
                <i class="fas fa-arrow-left"></i> Kembali ke Pilih Kelas
            </a>

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

        // Logika Dropdown User Info
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");

        if (userInfoDropdown && userDropdownContent) { // Pastikan elemen ada
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
            }
        }

        // Jalankan saat halaman dimuat
        window.onload = function() {
            // Set nama dan last login dari sesi PHP
            document.getElementById('guruName').textContent = '<?php echo htmlspecialchars($guru_name); ?>';
            document.getElementById('lastLogin').textContent = '<?php echo htmlspecialchars($last_login); ?>';

            // Mengatur link sidebar
            document.querySelector('.sidebar nav a:nth-child(1)').href = 'dashboard_guru.php';
            document.querySelector('.sidebar nav a:nth-child(2)').href = 'jadwal_guru.php';
            document.querySelector('.sidebar nav a:nth-child(3)').href = 'pertemuan_guru.php';
            document.querySelector('.sidebar nav a:nth-child(4)').href = 'absensi_guru.php';
            document.querySelector('.sidebar nav a:nth-child(5)').href = 'rekap_absensi_guru.php'; // Aktifkan link rekap absensi
        };
    </script>
</body>
</html>
