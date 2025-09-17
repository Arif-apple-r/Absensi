<?php
session_start();
// Pastikan hanya siswa yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../login.php"); // Sesuaikan path ke halaman login Anda
    exit;
}

// Ambil data siswa dari sesi
$siswa_id = $_SESSION['siswa_id'];
$siswa_name = $_SESSION['siswa_name'] ?? 'Siswa';
$siswa_nis = $_SESSION['siswa_nis'] ?? 'N/A';
$siswa_class_id = $_SESSION['siswa_class_id'] ?? null;
$last_login = $_SESSION['last_login'] ?? 'Belum ada data login';
$siswa_photo_session = $_SESSION['siswa_photo'] ?? '';
$id_jadwal = $_GET['id_jadwal'] ?? null;

// Sertakan file koneksi database Anda
require '../koneksi.php'; // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

$rekap_absensi_siswa = [];
$nama_kelas_siswa = 'Memuat...';
$nama_tahun_akademik = 'Memuat...'; // Tambahan untuk Tahun Akademik

if ($siswa_class_id) {
    // Ambil Nama Kelas Siswa dan Nama Tahun Akademik (untuk ditampilkan di header)
    $stmt_kelas_nama = $pdo->prepare("SELECT c.nama_kelas, ta.nama_tahun FROM class c JOIN tahun_akademik ta ON c.id_tahun_akademik = ta.id WHERE c.id = ?");
    $stmt_kelas_nama->execute([$siswa_class_id]);
    $kelas_data = $stmt_kelas_nama->fetch(PDO::FETCH_ASSOC);
    $nama_kelas_siswa = $kelas_data['nama_kelas'] ?? 'Tidak Ditemukan';
    $nama_tahun_akademik = $kelas_data['nama_tahun'] ?? 'Tidak Ditemukan'; // Ambil nama tahun akademik

    // Query untuk mengambil semua absensi siswa yang sedang login
    // Join dengan pertemuan, jadwal, mapel, dan guru untuk mendapatkan detail lengkap
    $query_absensi = "
        SELECT
            a.status,
            a.keterangan,
            a.waktu_input,
            p.tanggal AS tanggal_pertemuan,
            p.topik AS topik_pertemuan,
            m.nama_mapel,
            c.nama_kelas,
            g.name AS nama_guru,
            j.hari,
            j.jam_mulai,
            j.jam_selesai,
            ta.nama_tahun  -- Tambahan: Nama Tahun Akademik
        FROM absensi AS a
        JOIN siswa AS s ON a.id_siswa = s.id
        JOIN pertemuan AS p ON a.id_pertemuan = p.id
        JOIN jadwal AS j ON p.id_jadwal = j.id
        JOIN mapel AS m ON j.id_mapel = m.id
        JOIN guru AS g ON j.teacher_id = g.id
        JOIN class AS c ON j.class_id = c.id 
        JOIN tahun_akademik AS ta ON c.id_tahun_akademik = ta.id -- Tambahan: Join dengan tahun_akademik
        WHERE a.id_siswa = ?
        ORDER BY p.tanggal DESC, j.jam_mulai DESC;
    ";

    $stmt_absensi = $pdo->prepare($query_absensi);
    $stmt_absensi->execute([$siswa_id]);
    $rekap_absensi_siswa = $stmt_absensi->fetchAll(PDO::FETCH_ASSOC);
}

// Cek jika ada pesan sukses dari operasi sebelumnya
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
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

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Absensi Saya | Siswa</title>
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
        
        /* Status Absensi Styling */
        .status-hadir {
            color: #27ae60; /* Green */
            font-weight: 600;
        }
        .status-sakit {
            color: #e67e22; /* Orange */
            font-weight: 600;
        }
        .status-izin {
            color: #3498db; /* Blue */
            font-weight: 600;
        }
        .status-alpha {
            color: #e74c3c; /* Red */
            font-weight: 600;
        }
        .status-default {
            color: #7f8c8d; /* Grey */
            font-weight: 400;
        }

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
            border: 1px solid #f5c6cb;
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
    </style>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>SiswaCoy</span></div>
        <nav>
            <a href="dashboard_siswa.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_siswa.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Saya</span>
            </a>
            <a href="absensi_siswa.php" class="active">
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
        <h1><i class="fas fa-check-circle"></i> Absensi Saya</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="siswaName"><?php echo htmlspecialchars($siswa_name); ?></span>
            <?php
            // Tampilkan foto profil siswa jika ada, jika tidak pakai placeholder
            $siswa_photo_src_header = !empty($siswa_photo) ? '../uploads/siswa/' . htmlspecialchars($siswa_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
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
            <h2>Rekap Absensi Pribadi</h2>
            <div class="info-header">
                <p><strong>Nama:</strong> <?php echo htmlspecialchars($siswa_name); ?></p>
                <p><strong>NIS:</strong> <?php echo htmlspecialchars($siswa_nis); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($nama_kelas_siswa); ?></p>
                <p><strong>Tahun Akademik:</strong> <?php echo htmlspecialchars($nama_tahun_akademik); ?></p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (empty($rekap_absensi_siswa)): ?>
                <div class="info-header">
                    <p>Belum ada data absensi yang tercatat untuk Anda.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Mata Pelajaran</th>
                                <th>Topik Pertemuan</th>
                                <th>Guru Pengajar</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Waktu Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rekap_absensi_siswa as $absensi): ?>
                                <?php
                                    $status_class = '';
                                    switch ($absensi['status']) {
                                        case 'Hadir':
                                            $status_class = 'status-hadir';
                                            break;
                                        case 'Sakit':
                                            $status_class = 'status-sakit';
                                            break;
                                        case 'Izin':
                                            $status_class = 'status-izin';
                                            break;
                                        case 'Alpha':
                                            $status_class = 'status-alpha';
                                            break;
                                        default:
                                            $status_class = 'status-default';
                                            break;
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($absensi['tanggal_pertemuan']); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['nama_mapel']); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['topik_pertemuan']); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['nama_guru']); ?></td>
                                    <td class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($absensi['status']); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['keterangan'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['waktu_input'] ? date('d M Y H:i', strtotime($absensi['waktu_input'])) : '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

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
                    window.location.href = "../logout.php"; // redirect logout
                }
            });
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
            document.getElementById('siswaName').textContent = '<?php echo htmlspecialchars($siswa_name); ?>';
        };
    </script>
</body>
</html>