<?php
// File: rekap_absensi_kelas.php

session_start();
if (!isset($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

$guru_id = $_SESSION['guru_id'];
$guru_name = $_SESSION['guru_name'] ?? 'Guru';
require '../koneksi.php';

// Ambil id_jadwal dari URL
$jadwal_id = $_GET['jadwal_id'] ?? null;

if (!$jadwal_id) {
    header("Location: rekap_absensi_guru.php?error=" . urlencode("ID Jadwal tidak valid."));
    exit;
}

// Verifikasi guru memiliki akses ke jadwal ini, dan ambil informasi terkait
$stmt_info = $pdo->prepare("
    SELECT
        j.class_id,
        j.id_mapel,
        c.nama_kelas,
        m.nama_mapel
    FROM
        jadwal AS j
    INNER JOIN
        class AS c ON j.class_id = c.id
    INNER JOIN
        mapel AS m ON j.id_mapel = m.id
    WHERE
        j.id = ? AND j.teacher_id = ?
");
$stmt_info->execute([$jadwal_id, $guru_id]);
$info = $stmt_info->fetch(PDO::FETCH_ASSOC);

if (!$info) {
    header("Location: rekap_absensi_guru.php?error=" . urlencode("Anda tidak memiliki akses ke jadwal ini."));
    exit;
}

$class_id = $info['class_id'];
$mapel_id = $info['id_mapel'];
$nama_kelas = $info['nama_kelas'];
$nama_mapel = $info['nama_mapel'];

// Hitung total pertemuan yang tersedia berdasarkan jadwal_id
$stmt_total_meetings = $pdo->prepare("
    SELECT COUNT(*) AS total_meetings FROM pertemuan WHERE id_jadwal = ?
");
$stmt_total_meetings->execute([$jadwal_id]);
$total_available_meetings = $stmt_total_meetings->fetchColumn();

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
        COUNT(a.id) AS total_absensi_tercatat_siswa
    FROM
        siswa AS s
    LEFT JOIN
        absensi AS a ON s.id = a.id_siswa
    LEFT JOIN
        pertemuan AS p ON a.id_pertemuan = p.id
    WHERE
        s.class_id = ? AND p.id_jadwal = ?
    GROUP BY
        s.id, s.NIS, s.name
    ORDER BY
        s.name ASC;
";
$stmt_rekap = $pdo->prepare($query_rekap);
$stmt_rekap->execute([$class_id, $jadwal_id]);
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        /* Backlink untuk kembali */
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: var(--light-text-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--primary-color);
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
        <div class="logo"><span>GuruCoy</span></div>
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
        <h1><i class="fas fa-chart-bar"></i> Rekap Absensi</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="guruName"><?php echo htmlspecialchars($guru_name); ?></span>
            <?php
            $guru_photo_src_header = !empty($guru_photo) ? '../uploads/guru/' . htmlspecialchars($guru_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
            ?>
            <img src="<?php echo $guru_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';">

            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_guru.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a onclick="showLogoutConfirmation()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

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
                                    <td>
                                        <a href="detail_absensi_siswa.php?siswa_id=<?php echo htmlspecialchars($siswa_rekap['siswa_id']); ?>&jadwal_id=<?php echo htmlspecialchars($jadwal_id); ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($siswa_rekap['nama_siswa']); ?>
                                        </a>
                                    </td>
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
            <a href="rekap_absensi_guru.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Rekap Kelas
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