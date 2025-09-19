<?php
session_start();

// Validasi otorisasi
if (!isset($_SESSION['guru_id']) || empty($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

$guru_id = $_SESSION['guru_id'];
$guru_name = htmlspecialchars($_SESSION['guru_name'] ?? 'Guru');
$guru_photo_session = htmlspecialchars($_SESSION['guru_photo'] ?? '');

require '../koneksi.php';

// Fungsi untuk mendapatkan ID tahun akademik aktif
function getActiveTahunAkademikId($pdo) {
    try {
        $stmt = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'] ?? null;
    } catch (PDOException $e) {
        error_log("Error get active academic year: " . $e->getMessage());
        return null;
    }
}

// Ambil id_jadwal
$id_jadwal = filter_input(INPUT_GET, 'id_jadwal', FILTER_SANITIZE_NUMBER_INT);
if (!$id_jadwal) {
    header("Location: jadwal_guru.php?error=" . urlencode("ID Jadwal tidak valid."));
    exit;
}

// Cek apakah jadwal sesuai tahun akademik aktif
$can_edit = false;
$active_tahun_akademik_id = getActiveTahunAkademikId($pdo);

try {
    $stmt_tahun_id = $pdo->prepare("SELECT c.id_tahun_akademik FROM jadwal j 
        LEFT JOIN class c ON j.class_id = c.id WHERE j.id = ?");
    $stmt_tahun_id->execute([$id_jadwal]);
    $jadwal_tahun_id = $stmt_tahun_id->fetchColumn();
    if ($jadwal_tahun_id == $active_tahun_akademik_id) {
        $can_edit = true;
    }
} catch (PDOException $e) {
    error_log("Error checking tahun akademik jadwal: " . $e->getMessage());
}

$success = '';
$error = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_edit) {
        $error = "Tidak dapat menambah atau mengedit pertemuan untuk tahun akademik yang tidak aktif.";
    } else {
        $id_pertemuan = filter_input(INPUT_POST, 'id_pertemuan', FILTER_SANITIZE_NUMBER_INT);
        $tanggal = filter_input(INPUT_POST, 'tanggal', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $topik = filter_input(INPUT_POST, 'topik', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $id_jadwal_form = filter_input(INPUT_POST, 'id_jadwal', FILTER_SANITIZE_NUMBER_INT);

        if (empty($tanggal) || empty($topik) || empty($id_jadwal_form)) {
            $error = "Tanggal dan Topik wajib diisi.";
        } else {
            try {
                if ($id_pertemuan) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE pertemuan 
                        SET tanggal = ?, topik = ? 
                        WHERE id = ? AND id_jadwal = ?");
                    $stmt->execute([$tanggal, $topik, $id_pertemuan, $id_jadwal_form]);
                    $success = "Pertemuan berhasil diperbarui.";
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO pertemuan (id_jadwal, tanggal, topik) 
                        VALUES (?, ?, ?)");
                    $stmt->execute([$id_jadwal_form, $tanggal, $topik]);
                    $success = "Pertemuan baru berhasil ditambahkan.";
                }

                // Redirect supaya gak double-submit
                header("Location: pertemuan_guru.php?id_jadwal=" . urlencode($id_jadwal_form) 
                    . "&success=" . urlencode($success));
                exit;
            } catch (PDOException $e) {
                $error = "Gagal memproses pertemuan: " . $e->getMessage();
            }
        }
    }
}

// Ambil pesan sukses/error dari URL
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Ambil data jadwal
try {
    $stmt_jadwal_info = $pdo->prepare("
        SELECT j.hari, j.jam_mulai, j.jam_selesai,
               g.name AS nama_guru, g.photo AS guru_photo,
               m.nama_mapel, c.nama_kelas
        FROM jadwal j
        JOIN guru g ON j.teacher_id = g.id
        LEFT JOIN mapel m ON j.id_mapel = m.id
        LEFT JOIN class c ON j.class_id = c.id
        WHERE j.id = ? AND j.teacher_id = ?
    ");
    $stmt_jadwal_info->execute([$id_jadwal, $guru_id]);
    $jadwal_info = $stmt_jadwal_info->fetch(PDO::FETCH_ASSOC);

    if (!$jadwal_info) {
        header("Location: jadwal_guru.php?error=" . urlencode("Jadwal tidak ditemukan atau tidak punya akses."));
        exit;
    }

    $guru_photo = htmlspecialchars($jadwal_info['guru_photo']);
} catch (PDOException $e) {
    header("Location: jadwal_guru.php?error=" . urlencode("Kesalahan ambil data jadwal."));
    exit;
}

// Ambil daftar pertemuan
try {
    $stmt_pertemuan = $pdo->prepare("SELECT id, tanggal, topik 
        FROM pertemuan WHERE id_jadwal = ? ORDER BY tanggal DESC");
    $stmt_pertemuan->execute([$id_jadwal]);
    $list_pertemuan = $stmt_pertemuan->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pertemuan: " . $e->getMessage());
    $list_pertemuan = [];
    $error = "Gagal memuat daftar pertemuan.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pertemuan Guru | <?= htmlspecialchars($jadwal_info['nama_mapel']) ?> Kelas <?= htmlspecialchars($jadwal_info['nama_kelas']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            background-color: #253340ff;
            pointer-events: none; /* Tambahan untuk menonaktifkan klik */
        }
        
        /* Tambahan untuk menghilangkan efek hover pada tautan deactive */
        .sidebar nav a.deactive:hover {
            background-color: #253340ff; /* Kembali ke warna asli */
            padding-left: 20px; /* Kembali ke padding asli */
            transition: none; /* Menonaktifkan transisi hover */
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

        .tanggal-column {
            width: 20%;
        }

        .topik-column {
            width: 60%;
        }

        .aksi-column {
            width: 20%;
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
        .action-link.delete { /* Gaya untuk tombol delete */
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
            background: #e9ecef; /* Latar belakang abu-abu muda */
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

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .close-btn {
            color: #aaa;
            position: absolute;
            top: 10px;
            right: 25px;
            font-size: 28px;
            font-weight: bold;
        }
        .close-btn:hover,
        .close-btn:focus {
            color: #000;
            text-decoration: none;
            cursor: pointer;
        }
        .modal form {
            display: flex;
            flex-direction: column;
        }
        .modal label {
            margin-bottom: 5px;
            font-weight: 600;
        }
        .modal input[type="date"], .modal textarea {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }
        .modal textarea {
            min-height: 100px;
            resize: vertical;
        }
        .modal button[type="submit"] {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .modal button[type="submit"]:hover {
            background-color: #16a085;
        }

        /* Delete Modal Specific Styles */
        #deleteModal .modal-content {
            max-width: 400px;
            text-align: center;
        }
        #deleteModal .modal-content p {
            font-size: 1.1em;
            margin-bottom: 25px;
        }
        #deleteModal .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        #deleteModal .modal-buttons button {
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        #deleteModal .modal-buttons #confirmDeleteBtn {
            background-color: #e74c3c;
            color: white;
            border: none;
        }
        #deleteModal .modal-buttons #confirmDeleteBtn:hover {
            background-color: #c0392b;
        }
        #deleteModal .modal-buttons #cancelDeleteBtn {
            background-color: #ccc;
            color: #333;
            border: 1px solid #bbb;
        }
        #deleteModal .modal-buttons #cancelDeleteBtn:hover {
            background-color: #bbb;
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
            <a href="pertemuan_guru.php?id_jadwal=<?= htmlspecialchars($id_jadwal) ?>" class="active">
                <i class="fas fa-clipboard-list"></i>
                <span>Pertemuan</span>
            </a>
            <a href="rekap_absensi_guru.php">
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
        <button class="toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-clipboard-list"></i> Pertemuan Kelas</h1>
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
                <a onclick="showLogoutConfirmation()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- konten utama -->
    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Pertemuan</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="info-header">
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($jadwal_info['nama_kelas']); ?></p>
                <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($jadwal_info['nama_mapel']); ?></p>
                <p><strong>Guru:</strong> <?php echo htmlspecialchars($jadwal_info['nama_guru']); ?></p>
                <p><strong>Jadwal:</strong> <?php echo htmlspecialchars(substr($jadwal_info['hari'], 0, 5)) . ', ' . htmlspecialchars(substr($jadwal_info['jam_mulai'], 0, 5)) . ' - ' . htmlspecialchars(substr($jadwal_info['jam_selesai'], 0, 5)); ?></p>
            </div>

            <a href="#" onclick="openAddModal()" class="add-link">
                <i class="fas fa-plus"></i> Tambah Pertemuan
            </a>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="tanggal-column">Tanggal</th>
                            <th class="topik-column">Topik</th>
                            <th class="aksi-column">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_pertemuan)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">Belum ada pertemuan untuk jadwal ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($list_pertemuan as $pertemuan): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pertemuan['tanggal']); ?></td>
                                    <td><?php echo htmlspecialchars($pertemuan['topik']); ?></td>
                                    <td>
                                        <div class="action-links">
                                            <a href="#" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($pertemuan), ENT_QUOTES, 'UTF-8'); ?>)" class="action-link edit" title="Edit Pertemuan">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="absensi_guru.php?id_pertemuan=<?php echo htmlspecialchars($pertemuan['id']); ?>" class="action-link absensi" title="Isi Absensi">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                            <a href="#" onclick="openDeleteModal(<?php echo htmlspecialchars($pertemuan['id']); ?>)" class="action-link delete" title="Hapus Pertemuan">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="jadwal_guru.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Jadwal Mengajar
            </a>
        </div>
    </div>

    <div id="pertemuanModal" class="modal">
      <div class="modal-content">
          <span class="close-btn" onclick="closeModal()">&times;</span>
          <h2 id="modalTitle">Tambah Pertemuan</h2>

          <form method="POST" autocomplete="off" id="pertemuanForm">
              <input type="hidden" name="id_pertemuan" id="pertemuan_id">
              <input type="hidden" name="id_jadwal" value="<?php echo htmlspecialchars($id_jadwal); ?>">

              <label for="tanggal">Tanggal:</label>
              <input type="date" name="tanggal" id="tanggal" required>

              <label for="topik">Topik Pertemuan:</label>
              <textarea name="topik" id="topik" required></textarea>

              <button type="submit">Simpan Pertemuan</button>
          </form>
      </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeDeleteModal()">&times;</span>
            <h2>Konfirmasi Hapus</h2>
            <p>Apakah Anda yakin ingin menghapus pertemuan ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="modal-buttons">
                <button id="confirmDeleteBtn">Ya, Hapus</button>
                <button id="cancelDeleteBtn" onclick="closeDeleteModal()">Batal</button>
            </div>
        </div>
    </div>

<script>
        // Logika untuk toggle sidebar
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

        // Pasang event listener saat dokumen dimuat
        document.addEventListener('DOMContentLoaded', () => {
            // Memastikan toggleButton ada sebelum menambahkan event listener
            if (toggleButton) {
                toggleButton.addEventListener('click', toggleSidebar);
            }

            // Set active class untuk link sidebar
            const currentPath = window.location.pathname.split('/').pop();
            sidebarLinks.forEach(link => {
                // Perbaikan: Pastikan link memiliki atribut href sebelum membandingkan
                const linkHref = link.getAttribute('href');
                if (linkHref && linkHref.includes(currentPath)) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });

        // Logika Modal Pertemuan (Tambah/Edit)
        const modalPertemuan = document.getElementById("pertemuanModal");
        const modalPertemuanTitle = document.getElementById("modalTitle");
        const formPertemuan = document.getElementById("pertemuanForm");
        const inputPertemuanId = document.getElementById("pertemuan_id");
        const inputTanggal = document.getElementById("tanggal");
        const inputTopik = document.getElementById("topik");

        function openAddModal() {
            modalPertemuan.style.display = "block";
            modalPertemuanTitle.innerText = "Tambah Pertemuan Baru";
            formPertemuan.reset();
            inputPertemuanId.value = '';
        }

        function openEditModal(pertemuan) {
            modalPertemuan.style.display = "block";
            modalPertemuanTitle.innerText = "Edit Pertemuan";
            inputPertemuanId.value = pertemuan.id;
            inputTanggal.value = pertemuan.tanggal;
            inputTopik.value = pertemuan.topik;
        }

        function closeModal() {
            modalPertemuan.style.display = "none";
        }

        // Logika Modal Hapus
        const deleteModal = document.getElementById("deleteModal");
        const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
        let pertemuanToDeleteId = null;

        function openDeleteModal(id) {
            pertemuanToDeleteId = id;
            deleteModal.style.display = "block";
        }

        function closeDeleteModal() {
            deleteModal.style.display = "none";
        }

        confirmDeleteBtn.onclick = function() {
            window.location.href = `hapus_pertemuan.php?id=${pertemuanToDeleteId}&id_jadwal=<?php echo htmlspecialchars($id_jadwal); ?>`;
        }

        // Tutup modal jika user klik di luar area modal
        window.onclick = function(event) {
            if (event.target == modalPertemuan) {
                closeModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>