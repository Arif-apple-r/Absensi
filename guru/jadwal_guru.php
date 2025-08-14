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
// Perbaiki path jika diperlukan. Sesuaikan dengan lokasi file koneksi.php Anda.
require_once '../koneksi.php';

// Inisialisasi variabel dari sesi
$guru_id = $_SESSION['guru_id'];
$guru_name = htmlspecialchars($_SESSION['guru_name'] ?? 'Guru');
$guru_photo = htmlspecialchars($_SESSION['guru_photo'] ?? '');

// Cek jika ada pesan sukses dari operasi sebelumnya
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

// Query untuk mengambil jadwal mengajar guru yang sedang login
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
    WHERE j.teacher_id = :guru_id
    ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC;
";

$jadwal_mengajar = [];
$guru_photo = ''; // Inisialisasi ulang
try {
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':guru_id', $guru_id, PDO::PARAM_INT);
    $stmt->execute();
    $jadwal_mengajar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil data foto guru dari hasil query (jika ada)
    if (!empty($jadwal_mengajar)) {
        $guru_photo = htmlspecialchars($jadwal_mengajar[0]['guru_photo']);
    }

} catch (PDOException $e) {
    die("Error mengambil data: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Jadwal Mengajar - <?php echo $guru_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS yang tidak diubah dari kode sebelumnya */
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
            color: #fff;
            background-color: #27ae60;
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
        <div class="logo"><span>GuruCoy</span></div>
        <nav>
            <a href="dashboard_guru.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="active">
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
        <button class="toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-calendar-alt"></i> Jadwal Mengajar</h1>
        <div class="user-info">
            <span id="teacherName"><?php echo $guru_name; ?></span>
            <img
                src="../uploads/guru/<?= $guru_photo ?>"
                alt="Foto <?= $guru_name ?>"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/60x60/cccccc/333333?text=No+Foto';"
            >
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <?php if (!empty($success_message)): ?>
                <div class="alert"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <h2>Daftar Jadwal Anda</h2>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Kelas</th>
                            <th>Mata Pelajaran</th>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jadwal_mengajar)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Anda tidak memiliki jadwal mengajar.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jadwal_mengajar as $jadwal): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <img src="<?php echo htmlspecialchars('../uploads/kelas/' . $jadwal['class_photo']); ?>" alt="Foto Kelas" style="width: 40px; height: 40px; border-radius: 8px; margin-right: 10px; object-fit: cover;">
                                            <span><?php echo htmlspecialchars($jadwal['nama_kelas']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($jadwal['nama_mapel']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($jadwal['hari'], 0, 5)); ?></td>
                                    <td><?php echo htmlspecialchars(substr($jadwal['jam_mulai'], 0, 5) . ' - ' . substr($jadwal['jam_selesai'], 0, 5)); ?></td>
                                    <td>
                                        <a href="pertemuan_guru.php?id_jadwal=<?php echo htmlspecialchars($jadwal['jadwal_id']); ?>" class="action-button btn-view">
                                            <i class="fas fa-eye"></i> Lihat Pertemuan
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");
        const toggleButton = document.querySelector('.toggle-btn');
        const sidebarLinks = document.querySelectorAll('.sidebar nav a');

        function toggleSidebar() {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleButton.addEventListener('click', toggleSidebar);

            const currentPath = window.location.pathname.split('/').pop();
            sidebarLinks.forEach(link => {
                if (link.getAttribute('href') === currentPath) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>