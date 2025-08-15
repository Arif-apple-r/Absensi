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
$guru_photo_session = $_SESSION['guru_photo'] ?? '';

// Sertakan file koneksi database Anda
require '../koneksi.php'; // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

// Ambil id_pertemuan dari URL
$id_pertemuan = $_GET['id_pertemuan'] ?? null;

// Redirect jika id_pertemuan tidak ada atau tidak valid
if (!$id_pertemuan) {
    // Pastikan redirect ke pertemuan_guru.php, dan jika perlu, sertakan id_jadwal yang relevan
    // Untuk saat ini, kita redirect ke daftar jadwal jika tidak ada info pertemuan
    header("Location: jadwal_guru.php?error=" . urlencode("ID Pertemuan tidak ditemukan."));
    exit;
}


$success = '';
$error = '';

// --- Handle Form Submission (Simpan Absensi) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_absensi'])) {
    $id_pertemuan_form = $_POST['id_pertemuan'] ?? null;
    $absensi_data = $_POST['absensi'] ?? []; // Array berisi status absensi untuk setiap siswa
    $keterangan_data = $_POST['keterangan'] ?? []; // Array berisi keterangan untuk setiap siswa

    if ($id_pertemuan_form && is_array($absensi_data)) {
        try {
            // Dapatkan id_jadwal dan class_id dari id_pertemuan untuk redirect kembali
            $stmt_get_pertemuan_details = $pdo->prepare("SELECT id_jadwal, jadwal.class_id FROM pertemuan JOIN jadwal ON pertemuan.id_jadwal = jadwal.id WHERE pertemuan.id = ?");
            $stmt_get_pertemuan_details->execute([$id_pertemuan_form]);
            $pertemuan_details_for_redirect = $stmt_get_pertemuan_details->fetch(PDO::FETCH_ASSOC);
            $id_jadwal_for_redirect = $pertemuan_details_for_redirect['id_jadwal'] ?? null;
            $class_id_for_validation = $pertemuan_details_for_redirect['class_id'] ?? null; // Digunakan untuk validasi siswa

            // Pastikan guru memiliki akses ke jadwal pertemuan ini (opsional tapi disarankan)
            $stmt_verify_access = $pdo->prepare("SELECT COUNT(*) FROM jadwal WHERE id = ? AND teacher_id = ?");
            $stmt_verify_access->execute([$id_jadwal_for_redirect, $guru_id]);
            if ($stmt_verify_access->fetchColumn() == 0) {
                throw new Exception("Anda tidak memiliki izin untuk mengelola absensi pertemuan ini.");
            }

            $pdo->beginTransaction(); // Mulai transaksi database

            // Fetch current list of students for this class_id to validate incoming data
            $stmt_current_siswa = $pdo->prepare("SELECT id FROM siswa WHERE class_id = ?");
            $stmt_current_siswa->execute([$class_id_for_validation]);
            $valid_siswa_ids = $stmt_current_siswa->fetchAll(PDO::FETCH_COLUMN); // Ambil hanya ID siswa

            foreach ($absensi_data as $siswa_id => $status) {
                // Validasi bahwa siswa_id adalah bagian dari kelas ini
                if (!in_array($siswa_id, $valid_siswa_ids)) {
                    continue; // Skip invalid siswa_id
                }

                $keterangan = $keterangan_data[$siswa_id] ?? null;

                // Menggunakan INSERT ... ON DUPLICATE KEY UPDATE
                $stmt = $pdo->prepare("
                    INSERT INTO absensi (id_pertemuan, id_siswa, status, keterangan, waktu_input)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        keterangan = VALUES(keterangan),
                        waktu_input = NOW()
                ");
                $stmt->execute([$id_pertemuan_form, $siswa_id, $status, $keterangan]);
            }

            $pdo->commit(); // Commit transaksi
            $success = "Absensi berhasil disimpan!";

            // Redirect kembali ke halaman pertemuan dengan id_jadwal yang sesuai
            $redirect_url = '/AbsensiPKL/guru/pertemuan_guru.php?id_jadwal=' . urlencode($id_jadwal_for_redirect) . '&success=' . urlencode($success);
            header("Location: " . $redirect_url);
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack(); // Rollback transaksi jika ada error
            $error = "Gagal menyimpan absensi: " . $e->getMessage();
        } catch (Exception $e) { // Tangani exception dari validasi akses
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = "Data absensi tidak lengkap atau tidak valid.";
    }
}

// --- Ambil data pertemuan dan jadwal terkait untuk konteks ---
$stmt_pertemuan_info = $pdo->prepare("
    SELECT
        p.tanggal,
        p.topik,
        p.id_jadwal,
        j.hari,
        j.jam_mulai,
        j.jam_selesai,
        j.class_id, -- <<< Ini yang ditambahkan!
        m.nama_mapel,
        c.nama_kelas
        
    FROM pertemuan AS p
    JOIN jadwal AS j ON p.id_jadwal = j.id
    JOIN mapel AS m ON j.id_mapel = m.id
    JOIN class AS c ON j.class_id = c.id
    WHERE p.id = ?;
");
$stmt_pertemuan_info->execute([$id_pertemuan]);
$pertemuan_info = $stmt_pertemuan_info->fetch(PDO::FETCH_ASSOC);

$guru_photo = '';
if (!empty($guru_id)) {
    $stmt_guru_photo = $pdo->prepare("SELECT photo FROM guru WHERE id = ?");
    $stmt_guru_photo->execute([$guru_id]);
    $result = $stmt_guru_photo->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $guru_photo = htmlspecialchars($result['photo']);
    }
}

// Jika pertemuan tidak ditemukan, redirect
if (!$pertemuan_info) {
    header("Location: jadwal_guru.php?error=" . urlencode("Pertemuan tidak ditemukan atau Anda tidak memiliki akses."));
    exit;
}

$id_jadwal_current = $pertemuan_info['id_jadwal']; // Digunakan untuk link kembali
$class_id_from_pertemuan = $pertemuan_info['class_id']; // ID Kelas dari pertemuan yang ditemukan

// --- Ambil daftar siswa yang tergabung dalam kelas ini ---
$stmt_siswa = $pdo->prepare("SELECT id, NIS, name FROM siswa WHERE class_id = ? ORDER BY name ASC");
$stmt_siswa->execute([$class_id_from_pertemuan]);
$list_siswa = $stmt_siswa->fetchAll(PDO::FETCH_ASSOC);

// --- Ambil status absensi yang sudah ada untuk pertemuan ini ---
$absensi_existing = [];
$stmt_absensi_existing = $pdo->prepare("SELECT id_siswa, status, keterangan FROM absensi WHERE id_pertemuan = ?");
$stmt_absensi_existing->execute([$id_pertemuan]);
foreach ($stmt_absensi_existing as $row) {
    $absensi_existing[$row['id_siswa']] = [
        'status' => $row['status'],
        'keterangan' => $row['keterangan']
    ];
}

// Cek pesan sukses/error dari redirect sebelumnya
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Isi Absensi Pertemuan</title>
    <!-- Font Awesome untuk ikon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS dari file admin Anda */
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
            display: none;
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

        .data-table tr:hover {
            background-color: #fafafa;
        }

        /* Actions button in table */
        .action-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .action-link {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
        }

        .action-link.edit {
            background-color: #3498db;
            color: white;
        }

        .action-link.edit:hover {
            background-color: #2980b9;
        }

        .action-link.absensi {
            background-color: #2ecc71;
            color: white;
        }

        .action-link.absensi:hover {
            background-color: #27ae60;
        }

        .action-link.delete {
            background-color: #e74c3c;
            color: white;
        }

        .action-link.delete:hover {
            background-color: #c0392b;
        }


        /* Add link (button like) */
        .add-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
        }

        .add-link:hover {
            background-color: #16a085;
            transform: translateY(-2px);
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

        /* Back Link */
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

        /* Absensi form styles */
        .absensi-status-options input[type="radio"] {
            margin-right: 5px;
        }

        .absensi-status-options label {
            margin-right: 15px;
            font-weight: normal;
        }

        .absensi-keterangan textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-top: 5px;
            box-sizing: border-box;
            /* Include padding in element's total width and height */
            resize: vertical;
            min-height: 50px;
        }

        .absensi-keterangan label {
            font-size: 0.9em;
            color: var(--light-text-color);
        }

        .save-absensi-btn {
            background-color: #2ecc71;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
            margin-top: 20px;
        }

        .save-absensi-btn:hover {
            background-color: #27ae60;
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
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>GuruCoy</span></div>
        <nav>
            <a href="dashboard_guru.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_guru.php">
                <i class="fas fa-calendar-alt" class=></i>
                <span>Jadwal Mengajar</span>
            </a>
            <a href="pertemuan_guru.php?id_jadwal=<?= htmlspecialchars($id_jadwal_current); ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Pertemuan</span>
            </a>
            <a href="absensi_guru.php" class="active">
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

    <!-- Header -->
    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-check-circle"></i> Isi Absensi</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="guruName"><?php echo htmlspecialchars($guru_name); ?></span>
            <?php
            // Tampilkan foto profil guru jika ada, jika tidak pakai placeholder
            $guru_photo_src_header = !empty($guru_photo) ? '../uploads/guru/' . htmlspecialchars($guru_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
            ?>
            <img src="<?php echo $guru_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';">

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
            <h2>Absensi Pertemuan</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($pertemuan_info): ?>
                <div class="info-header">
                    <p><strong>Tanggal:</strong> <?php echo htmlspecialchars($pertemuan_info['tanggal']); ?></p>
                    <p><strong>Topik:</strong> <?php echo htmlspecialchars($pertemuan_info['topik']); ?></p>
                    <p><strong>Kelas:</strong> <?php echo htmlspecialchars($pertemuan_info['nama_kelas']); ?></p>
                    <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($pertemuan_info['nama_mapel']); ?></p>
                    <p><strong>Jadwal:</strong> <?php echo htmlspecialchars($pertemuan_info['hari']); ?>, <?php echo htmlspecialchars(substr($pertemuan_info['jam_mulai'], 0, 5)); ?> - <?php echo htmlspecialchars(substr($pertemuan_info['jam_selesai'], 0, 5)); ?></p>
                </div>
            <?php else: ?>
                <div class="alert alert-error">Data pertemuan tidak ditemukan. Pastikan Anda mengakses halaman ini dari pertemuan yang valid.</div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="id_pertemuan" value="<?php echo htmlspecialchars($id_pertemuan); ?>">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Status Absensi</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($list_siswa)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">Tidak ada siswa di kelas ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($list_siswa as $siswa): ?>
                                    <?php
                                    // Dapatkan status dan keterangan yang sudah ada untuk siswa ini
                                    $current_status = $absensi_existing[$siswa['id']]['status'] ?? 'Alpha';
                                    $current_keterangan = $absensi_existing[$siswa['id']]['keterangan'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($siswa['NIS']); ?></td>
                                        <td><?php echo htmlspecialchars($siswa['name']); ?></td>
                                        <td>
                                            <div class="absensi-status-options">
                                                <label>
                                                    <input type="radio"
                                                        name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                        value="Hadir"
                                                        onchange="toggleKeterangan(this)"
                                                        <?php echo ($current_status == 'Hadir') ? 'checked' : ''; ?>> Hadir
                                                </label>

                                                <label>
                                                    <input type="radio"
                                                        name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                        value="Alpha"
                                                        onchange="toggleKeterangan(this)"
                                                        <?php echo ($current_status == 'Alpha') ? 'checked' : ''; ?>> Alpha
                                                </label>
                                                <label>
                                                    <input type="radio"
                                                        name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                        value="Sakit"
                                                        onchange="toggleKeterangan(this)"
                                                        <?php echo ($current_status == 'Sakit') ? 'checked' : ''; ?>> Sakit
                                                </label>
                                                <label>
                                                    <input type="radio"
                                                        name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                        value="Izin"
                                                        onchange="toggleKeterangan(this)"
                                                        <?php echo ($current_status == 'Izin') ? 'checked' : ''; ?>> Izin
                                                </label>
                                            </div>
                                        </td>
                                        <td class="absensi-keterangan">
                                            <textarea name="keterangan[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                placeholder="Tambahkan keterangan (opsional)"
                                                <?php echo (!($current_status == 'Sakit' || $current_status == 'Izin')) ? 'disabled' : ''; ?>><?php echo htmlspecialchars($current_keterangan); ?></textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" name="submit_absensi" class="save-absensi-btn">
                    <i class="fas fa-save"></i> Simpan Absensi
                </button>
            </form>

            <a href="pertemuan_guru.php?id_jadwal=<?php echo htmlspecialchars($id_jadwal_current); ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Pertemuan
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



        // Fungsi untuk mengaktifkan/menonaktifkan textarea keterangan
        function toggleKeterangan(radioElement) {
            const keteranganCell = radioElement.closest('td').nextElementSibling; // Sel sebelahnya (keterangan)
            const keteranganTextarea = keteranganCell.querySelector('textarea');

            if (radioElement.value === 'Sakit' || radioElement.value === 'Izin') {
                keteranganTextarea.disabled = false;
            } else {
                keteranganTextarea.disabled = true;
                keteranganTextarea.value = ''; // Opsional: kosongkan saat dinonaktifkan
            }
        }

        // Jalankan saat halaman dimuat untuk mengatur status awal textarea

        // Jalankan saat halaman dimuat
    </script>
</body>

</html>