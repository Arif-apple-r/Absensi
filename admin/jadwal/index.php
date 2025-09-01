<?php
session_start();
if (!isset($_SESSION['admin_id'])) { // Ganti ke admin_id jika ini untuk admin/jadwal/index.php
    header("Location: ../../login.php");
    exit;
}

require '../../koneksi.php';

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); // Ganti ke admin_name
$admin_photo = 'https://placehold.co/40x40/cccccc/333333?text=SA'; 

$success = '';
$error = '';

// Ambil daftar Tahun Akademik untuk filter
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

// Tentukan tahun akademik yang sedang dipilih (dari GET atau default ke yang aktif)
$selected_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? null;

if ($selected_tahun_akademik_id === null) {
    // Jika tidak ada parameter tahun_akademik_id di URL, ambil yang aktif
    $stmt_active_tahun = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
    $active_tahun = $stmt_active_tahun->fetch(PDO::FETCH_ASSOC);
    $selected_tahun_akademik_id = $active_tahun['id'] ?? ($tahun_akademik_options[0]['id'] ?? null); // Fallback ke tahun pertama jika tidak ada yang aktif
}

// Pastikan $selected_tahun_akademik_id adalah integer, jika null, set 0 atau handle error
if ($selected_tahun_akademik_id === null) {
    $error = "Tidak ada Tahun Akademik yang ditemukan atau diatur aktif.";
    $selected_tahun_akademik_id = 0; // Set ke 0 agar query tidak crash, tapi data akan kosong
} else {
    $selected_tahun_akademik_id = (int)$selected_tahun_akademik_id;
}


// Ambil data untuk dropdown di form (Kelas, Mapel, Guru)
// Penting: Kelas difilter berdasarkan selected_tahun_akademik_id
$kelas_form_options = [];
if ($selected_tahun_akademik_id) { // Hanya ambil kelas jika ada tahun akademik yang dipilih
    $stmt_kelas_form = $pdo->prepare("SELECT id, nama_kelas FROM class WHERE id_tahun_akademik = ? ORDER BY nama_kelas ASC");
    $stmt_kelas_form->execute([$selected_tahun_akademik_id]);
    $kelas_form_options = $stmt_kelas_form->fetchAll(PDO::FETCH_ASSOC);
}
$mapel_options = $pdo->query("SELECT id, nama_mapel FROM mapel ORDER BY nama_mapel ASC")->fetchAll(PDO::FETCH_ASSOC);
$guru_options = $pdo->query("SELECT id, name FROM guru ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);


// Handle Form Submission (Tambah & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation (Jika Anda menggunakannya, sertakan kembali di sini)
    // if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    //     die('Invalid CSRF token');
    // }

    $id = $_POST['id'] ?? '';
    $class_id = $_POST['class_id'] ?? '';
    $mapel_id = $_POST['mapel_id'] ?? '';
    $hari = $_POST['hari'] ?? '';
    $jam_mulai = $_POST['jam_mulai'] ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    $teacher_id = $_POST['teacher_id'] ?? '';
    $submitted_tahun_akademik_id = $_POST['tahun_akademik_id'] ?? $selected_tahun_akademik_id; // Ambil dari hidden input

    if ($class_id && $mapel_id && $hari && $jam_mulai && $jam_selesai && $teacher_id) {
        try {
            // Get mapel name (Tetap menggunakan ini jika kolom 'mata_pelajaran' masih ada di tabel jadwal)
            $stmt_mapel = $pdo->prepare("SELECT nama_mapel FROM mapel WHERE id = ?");
            $stmt_mapel->execute([$mapel_id]);
            $mapel_name = $stmt_mapel->fetchColumn();

            if ($id) {
                // Update existing schedule
                $stmt = $pdo->prepare("UPDATE jadwal SET class_id=?, id_mapel=?, mata_pelajaran=?, hari=?, jam_mulai=?, jam_selesai=?, teacher_id=? WHERE id=?");
                $stmt->execute([$class_id, $mapel_id, $mapel_name, $hari, $jam_mulai, $jam_selesai, $teacher_id, $id]);
                $success = "Jadwal berhasil diupdate!";
            } else {
                // Insert new schedule
                $stmt = $pdo->prepare("INSERT INTO jadwal (class_id, id_mapel, mata_pelajaran, hari, jam_mulai, jam_selesai, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$class_id, $mapel_id, $mapel_name, $hari, $jam_mulai, $jam_selesai, $teacher_id]);
                $success = "Jadwal berhasil ditambahkan!";
            }

            header("Location: index.php?success=" . urlencode($success) . "&tahun_akademik_id=" . $submitted_tahun_akademik_id);
            exit;
        } catch (PDOException $e) {
            $error = "Gagal memproses jadwal: " . $e->getMessage();
        }
    } else {
        $error = "Semua field wajib diisi!";
    }
}

// Handle Delete action
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_jadwal = $_GET['id'];
    $current_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? $selected_tahun_akademik_id;

    try {
        // Cek apakah ada pertemuan terkait jadwal ini (dan absensi terkait pertemuan)
        $stmt_check_pertemuan = $pdo->prepare("SELECT COUNT(*) FROM pertemuan WHERE id_jadwal = ?");
        $stmt_check_pertemuan->execute([$id_jadwal]);
        if ($stmt_check_pertemuan->fetchColumn() > 0) {
            $error = "Tidak dapat menghapus Jadwal ini karena masih ada pertemuan yang terkait. Harap hapus pertemuan terlebih dahulu.";
            header("Location: index.php?error=" . urlencode($error) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM jadwal WHERE id = ?");
        $stmt->execute([$id_jadwal]);
        $success = "Jadwal berhasil dihapus!";
        header("Location: index.php?success=" . urlencode($success) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    } catch (PDOException $e) {
        $error = "Gagal menghapus jadwal: " . $e->getMessage();
    }
}

// Fetch all schedule data filtered by selected_tahun_akademik_id
$query = "
        SELECT 
            jadwal.*, 
            guru.name AS nama_guru, 
            class.nama_kelas,
            class.photo,
            tahun_akademik.nama_tahun
        FROM jadwal
        JOIN guru ON jadwal.teacher_id = guru.id
        JOIN class ON jadwal.class_id = class.id
        JOIN mapel ON jadwal.id_mapel = mapel.id
        JOIN tahun_akademik ON class.id_tahun_akademik = tahun_akademik.id
        WHERE tahun_akademik.id = ?
        ORDER BY FIELD(jadwal.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), jadwal.jam_mulai ASC
    ";
$stmt = $pdo->prepare($query);
$stmt->execute([$selected_tahun_akademik_id]);
$jadwal_list = $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch all results to be filtered by JS

// Check for success/error messages from redirect
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Data untuk openEditModal (jika ada edit action dari redirect)
$jadwal_to_edit = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt_edit = $pdo->prepare("SELECT * FROM jadwal WHERE id = ?");
    $stmt_edit->execute([$id]);
    $jadwal_to_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>Jadwal Kelas</title>
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

        /* Sidebar dan Header */
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
            justify-content: space-between; /* Added for user-info */
        }

        .header.shifted {
            left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
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

        /* Tombol Toggle Sidebar */
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

        /* Konten Utama */
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

        /* Tabel Jadwal */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .schedule-table thead {
            background-color: var(--secondary-color);
            color: white;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .schedule-table tbody tr:last-child td {
            border-bottom: none;
        }

        .schedule-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .schedule-table tbody tr:hover {
            background-color: #f2f2f2;
            transition: background-color 0.2s ease;
        }

        .schedule-table th:first-child,
        .schedule-table td:first-child {
            padding-left: 20px;
        }

        .schedule-table th:last-child,
        .schedule-table td:last-child {
            padding-right: 20px;
        }

        .schedule-table img {
            width: 100%;
            max-width: 100px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 5px var(--shadow-color);
        }

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

        .action-link.delete {
            background-color: #e74c3c;
            color: white;
        }

        .action-link.delete:hover {
            background-color: #c0392b;
        }

        .action-link.view {
            background-color: #f39c12;
            color: white;
        }

        .action-link.view:hover {
            background-color: #e67e22;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: var(--light-text-color);
            font-weight: 600;
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .sidebar:not(.collapsed) {
                transform: translateX(-100%);
            }

            .sidebar.collapsed {
                transform: translateX(0);
            }

            .content,
            .header {
                margin-left: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding-left: 20px !important;
            }

            .sidebar.collapsed+.header,
            .sidebar.collapsed~.content {
                margin-left: var(--sidebar-collapsed-width) !important;
                left: var(--sidebar-collapsed-width) !important;
                width: calc(100% - var(--sidebar-collapsed-width)) !important;
            }
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

        .modal select,
        .modal input[type="time"] {
            margin-bottom: 15px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
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

        /* Style untuk Custom Alert Modal */
        .custom-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            justify-content: center;
            align-items: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .custom-modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s ease-out;
        }

        .custom-modal-content h4 {
            margin-bottom: 25px;
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 1.2em;
        }

        .custom-modal-content .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .custom-modal-content .modal-buttons .btn-save,
        .custom-modal-content .modal-buttons .btn-close {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .custom-modal-content .modal-buttons .btn-save {
            background-color: var(--primary-color);
            color: white;
        }

        .custom-modal-content .modal-buttons .btn-close {
            background-color: #e74c3c;
            color: white;
        }

        .custom-modal-content .modal-buttons .btn-save:hover,
        .custom-modal-content .modal-buttons .btn-close:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Gaya untuk Filter */
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 25px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .filter-group select,
        .filter-group input { /* Added input for consistency */
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: var(--card-background);
            font-size: 14px;
        }

        #reset-filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            background-color: var(--primary-color);
            color: white;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s;
        }

        #reset-filter-btn:hover {
            background-color: #16a085;
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
            background-color: #e74c3c;
            /* Warna merah untuk Logout */
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

        /* User Info Dropdown Styling - From previous files */
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
    </style>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>AdminCoy</span></div>
        <nav>
            <a href="../dashboard_admin.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../guru/index.php">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Guru</span>
            </a>
            <a href="../siswa/index.php">
                <i class="fas fa-user-graduate"></i>
                <span>Siswa</span>
            </a>
            <a href="index.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal</span>
            </a>
            <a href="../Tahun_Akademik/index.php">
                <i class="fas fa-calendar"></i>
                <span>Tahun Akademik</span>
            </a>
            <a href="../kelas/index.php">
                <i class="fas fa-school"></i>
                <span>Kelas</span>
            </a>
            <a href="../mapel/index.php">
                <i class="fas fa-book"></i>
                <span>Mata Pelajaran</span>
            </a>
        </nav>
        <div class="logout-button-container">
            <a onclick="showLogoutConfirm(event)" id="logoutButtonSidebar">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-calendar-alt"></i>Jadwal Kelas</h1>
        <div class="user-info" id="userInfoDropdown">
            <span><?= $admin_name ?></span>
            <div class="dropdown-menu" id="userDropdownContent">
                <!-- <a href="profil_admin.php"><i class="fas fa-user-circle"></i> Profil</a> -->
                <a href="../../logout.php" id="logoutDropdownLink"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Data Jadwal</h2>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="filter-container">
                <div class="filter-group">
                    <label for="filter-tahun-akademik">Tahun Akademik:</label>
                    <select id="filter-tahun-akademik" onchange="applyTahunAkademikFilter()">
                        <?php if (empty($tahun_akademik_options)): ?>
                            <option value="">Tidak ada Tahun Akademik</option>
                        <?php else: ?>
                            <?php foreach ($tahun_akademik_options as $ta_option): ?>
                                <option value="<?php echo htmlspecialchars($ta_option['id']); ?>"
                                    <?php echo ($ta_option['id'] == $selected_tahun_akademik_id) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ta_option['nama_tahun']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-kelas">Kelas:</label>
                    <select id="filter-kelas">
                        <option value="all">Semua Kelas</option>
                        <?php 
                        // Ambil semua kelas dari tahun akademik yang dipilih untuk filter client-side
                        $all_kelas_for_filter = [];
                        if ($selected_tahun_akademik_id) {
                            $stmt_all_kelas = $pdo->prepare("SELECT id, nama_kelas FROM class WHERE id_tahun_akademik = ? ORDER BY nama_kelas ASC");
                            $stmt_all_kelas->execute([$selected_tahun_akademik_id]);
                            $all_kelas_for_filter = $stmt_all_kelas->fetchAll(PDO::FETCH_ASSOC);
                        }
                        foreach ($all_kelas_for_filter as $k) : ?>
                            <option value="<?= htmlspecialchars($k['nama_kelas']) ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-mapel">Mata Pelajaran:</label>
                    <select id="filter-mapel">
                        <option value="all">Semua Mapel</option>
                        <?php foreach ($mapel_options as $m) : // Menggunakan mapel_options yang sudah ada ?>
                            <option value="<?= htmlspecialchars($m['nama_mapel']) ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-hari">Hari:</label>
                    <select id="filter-hari">
                        <option value="all">Semua Hari</option>
                        <?php
                        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']; // Tambah Minggu untuk konsistensi
                        foreach ($days as $d) : ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button id="reset-filter-btn">Reset Filter</button>
                </div>
            </div>

            <a href="#" onclick="openModal()" class="add-link">
                <i class="fas fa-plus"></i> Tambah Jadwal
            </a>

            <div class="table-container">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th>Kelas</th>
                            <th>Foto Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jadwal_list)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Tidak ada jadwal untuk tahun akademik ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jadwal_list as $row) : ?>
                                <tr data-hari="<?= htmlspecialchars($row['hari']) ?>" data-kelas="<?= htmlspecialchars($row['nama_kelas']) ?>" data-mapel="<?= htmlspecialchars($row['mata_pelajaran']) ?>">
                                    <td><?= htmlspecialchars($row['hari']) ?></td>
                                    <td><?= htmlspecialchars($row['jam_mulai']) ?> - <?= htmlspecialchars($row['jam_selesai']) ?></td>
                                    <td><?= htmlspecialchars($row['mata_pelajaran']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_guru']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                                    <td>
                                        <?php if (!empty($row['photo'])) : ?>
                                            <img src="../../uploads/kelas/<?= htmlspecialchars($row['photo']) ?>" alt="Foto Kelas"
                                                loading="lazy"
                                                onerror="this.onerror=null;this.src='https://placehold.co/100x80/cccccc/333333?text=NO+IMG';"
                                            >
                                        <?php else : ?>
                                            <img src="https://placehold.co/100x80/cccccc/333333?text=NO+IMG" alt="Tidak ada foto"
                                                loading="lazy"
                                                onerror="this.onerror=null;this.src='https://placehold.co/100x80/cccccc/333333?text=NO+IMG';"
                                            >
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-links">
                                            <a href="#" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="action-link edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" onclick="handleDeleteClick(event, '<?= $row['id'] ?>')" class="action-link delete" title="Hapus">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                            <a href="pertemuan.php?id_jadwal=<?= $row['id'] ?>" class="action-link view" title="Lihat Pertemuan">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="../dashboard_admin.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>

    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Tambah Jadwal</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="scheduleForm">
                <input type="hidden" name="id" id="jadwal_id">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="tahun_akademik_id" value="<?= htmlspecialchars($selected_tahun_akademik_id); ?>">

                <label for="class_id_modal">Kelas:</label>
                <select name="class_id" id="class_id_modal" required>
                    <option value="">--Pilih--</option>
                    <?php if (empty($kelas_form_options)): ?>
                        <option value="" disabled>Tidak ada kelas untuk tahun akademik ini.</option>
                    <?php else: ?>
                        <?php foreach ($kelas_form_options as $k): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <label for="mapel_id_modal">Mapel:</label>
                <select name="mapel_id" id="mapel_id_modal" required>
                    <option value="">--Pilih--</option>
                    <?php foreach ($mapel_options as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="hari_modal">Hari:</label>
                <select name="hari" id="hari_modal" required>
                    <option value="">--Pilih Hari--</option>
                    <?php
                    $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                    foreach ($days as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="jam_mulai_modal">Jam Mulai:</label>
                <input type="time" name="jam_mulai" id="jam_mulai_modal" required>

                <label for="jam_selesai_modal">Jam Selesai:</label>
                <input type="time" name="jam_selesai" id="jam_selesai_modal" required>

                <label for="teacher_id_modal">Guru:</label>
                <select name="teacher_id" id="teacher_id_modal" required>
                    <option value="">--Pilih--</option>
                    <?php foreach ($guru_options as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Simpan Jadwal</button>
            </form>
        </div>
    </div>

    <div id="custom-alert-modal" class="custom-modal-overlay">
        <div class="custom-modal-content">
            <h4 id="custom-alert-message"></h4>
            <div class="modal-buttons">
                <button type="button" class="btn-save" id="custom-alert-ok">OK</button>
                <button type="button" class="btn-close" id="custom-alert-cancel" style="display:none;">Batal</button>
            </div>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");

        function toggleSidebar() {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
        }


        const modal = document.getElementById("scheduleModal");
        const modalTitle = document.getElementById("modalTitle");
        const form = document.getElementById("scheduleForm");
        const jadwal_id_input = document.getElementById("jadwal_id");
        const class_id_modal_select = document.getElementById("class_id_modal"); // Updated ID
        const mapel_id_modal_select = document.getElementById("mapel_id_modal"); // Updated ID
        const hari_modal_select = document.getElementById("hari_modal"); // Updated ID
        const jam_mulai_modal_input = document.getElementById("jam_mulai_modal"); // Updated ID
        const jam_selesai_modal_input = document.getElementById("jam_selesai_modal"); // Updated ID
        const teacher_id_modal_select = document.getElementById("teacher_id_modal"); // Updated ID
        const filterTahunAkademik = document.getElementById("filter-tahun-akademik");


        function openModal() {
            modal.style.display = "block";
            modalTitle.innerText = "Tambah Jadwal";
            form.reset();
            jadwal_id_input.value = '';
            // Reset dropdowns explicitly for clarity if needed, though form.reset() should handle most.
            class_id_modal_select.value = '';
            mapel_id_modal_select.value = '';
            hari_modal_select.value = '';
            teacher_id_modal_select.value = '';
        }

        /* * Open modal for editing existing schedule
         * jadwal is an object with properties matching the form fields
         */

        function openEditModal(jadwal) {
            modal.style.display = "block";
            modalTitle.innerText = "Edit Jadwal";

            jadwal_id_input.value = jadwal.id;
            class_id_modal_select.value = jadwal.class_id; // Using updated ID
            mapel_id_modal_select.value = jadwal.id_mapel; // Using updated ID
            hari_modal_select.value = jadwal.hari; // Using updated ID
            jam_mulai_modal_input.value = jadwal.jam_mulai; // Using updated ID
            jam_selesai_modal_input.value = jadwal.jam_selesai; // Using updated ID
            teacher_id_modal_select.value = jadwal.teacher_id; // Using updated ID
        }

        function closeModal() {
            modal.style.display = "none";
            // Clear any success/error messages on close
            const successAlert = document.querySelector('.alert-success');
            const errorAlert = document.querySelector('.alert-error');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        /* * Custom Alert Modal Logic         */
        const customAlertModal = document.getElementById("custom-alert-modal");
        const customAlertMessage = document.getElementById("custom-alert-message");
        const customAlertOkBtn = document.getElementById("custom-alert-ok");
        const customAlertCancelBtn = document.getElementById("custom-alert-cancel");
        let customAlertResolve;

        function showCustomConfirm(message) {
            return new Promise(resolve => {
                customAlertMessage.textContent = message;
                customAlertOkBtn.style.display = 'block';
                customAlertCancelBtn.style.display = 'block';
                customAlertModal.style.display = 'flex';
                customAlertResolve = resolve;
            });
        }

        customAlertOkBtn.addEventListener('click', () => {
            customAlertModal.style.display = 'none';
            customAlertResolve(true);
        });

        customAlertCancelBtn.addEventListener('click', () => {
            customAlertModal.style.display = 'none';
            customAlertResolve(false);
        });

        async function handleDeleteClick(event, id_jadwal) {
            event.preventDefault(); // Mencegah link langsung beraksi
            const confirmed = await showCustomConfirm('Yakin ingin menghapus jadwal ini?');

            if (confirmed) {
                // Sertakan tahun akademik id saat menghapus
                const currentTahunAkademikId = filterTahunAkademik.value;
                window.location.href = `index.php?action=hapus&id=${id_jadwal}&tahun_akademik_id=${currentTahunAkademikId}`;
            }
        }

        function showLogoutConfirm() { // This is now used by SweetAlert2
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah kamu yakin ingin logout?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Logout!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../../logout.php"; // redirect logout
                }
            });
        }
        
        // Bind logout button in sidebar to SweetAlert
        const logoutButtonSidebar = document.getElementById('logoutButtonSidebar'); // Updated ID
        if (logoutButtonSidebar) {
            logoutButtonSidebar.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default link behavior
                showLogoutConfirm();
            });
        }
        // Bind logout button in dropdown to SweetAlert
        const logoutDropdownLink = document.getElementById('logoutDropdownLink');
        if (logoutDropdownLink) {
            logoutDropdownLink.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default link behavior
                showLogoutConfirm();
            });
        }

        // Fungsi untuk menerapkan filter Tahun Akademik
        function applyTahunAkademikFilter() {
            const selectedTahunAkademik = filterTahunAkademik.value;
            window.location.href = `index.php?tahun_akademik_id=${selectedTahunAkademik}`;
        }


        // ===========================================
        // LOGIKA BARU UNTUK FILTER INTERAKTIF CLIENT-SIDE
        // ===========================================
        document.addEventListener('DOMContentLoaded', function() {
            const filterKelas = document.getElementById('filter-kelas');
            const filterMapel = document.getElementById('filter-mapel');
            const filterHari = document.getElementById('filter-hari');
            const resetBtn = document.getElementById('reset-filter-btn');
            const tableRows = document.querySelectorAll('.schedule-table tbody tr');

            function applyClientSideFilters() {
                const selectedKelas = filterKelas.value;
                const selectedMapel = filterMapel.value;
                const selectedHari = filterHari.value;

                tableRows.forEach(row => {
                    // Check for "Tidak ada jadwal" row and skip it
                    if (row.querySelector('td[colspan="7"]')) {
                        return;
                    }

                    const rowKelas = row.getAttribute('data-kelas');
                    const rowMapel = row.getAttribute('data-mapel');
                    const rowHari = row.getAttribute('data-hari');

                    const isKelasMatch = selectedKelas === 'all' || selectedKelas === rowKelas;
                    const isMapelMatch = selectedMapel === 'all' || selectedMapel === rowMapel;
                    const isHariMatch = selectedHari === 'all' || selectedHari === rowHari;

                    if (isKelasMatch && isMapelMatch && isHariMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            function resetFilters() {
                filterKelas.value = 'all';
                filterMapel.value = 'all';
                filterHari.value = 'all';
                applyClientSideFilters();
            }

            // Event listeners untuk setiap dropdown filter
            filterKelas.addEventListener('change', applyClientSideFilters);
            filterMapel.addEventListener('change', applyClientSideFilters);
            filterHari.addEventListener('change', applyClientSideFilters);

            // Event listener untuk tombol reset
            resetBtn.addEventListener('click', resetFilters);

            // Apply filters initially (in case of values from previous navigation or default)
            applyClientSideFilters();
        });

        // Fungsi untuk menandai link sidebar yang aktif dengan benar
        window.addEventListener('DOMContentLoaded', (event) => {
            const currentPathname = window.location.pathname; 
            const pathSegments = currentPathname.split('/');
            const adminIndex = pathSegments.indexOf('admin');
            let relativePathFromAdmin = '';

            if (adminIndex !== -1 && pathSegments.length > adminIndex) {
                relativePathFromAdmin = pathSegments.slice(adminIndex + 1).join('/');
            } else {
                relativePathFromAdmin = currentPathname.split('/').pop();
            }
            
            document.querySelectorAll('.sidebar nav a').forEach(link => {
                link.classList.remove('active'); 

                let linkHref = new URL(link.href).pathname; 
                const linkSegments = linkHref.split('/');
                const linkAdminIndex = linkSegments.indexOf('admin');
                let linkRelativePath = '';

                if (linkAdminIndex !== -1 && linkSegments.length > linkAdminIndex) {
                    linkRelativePath = linkSegments.slice(linkAdminIndex + 1).join('/');
                } else {
                     linkRelativePath = linkHref.split('/').pop();
                }
                
                linkRelativePath = linkRelativePath.split('?')[0];
                let currentPathWithoutQuery = relativePathFromAdmin.split('?')[0];

                // For the "Jadwal" link, specifically check if its folder matches the current URL's folder.
                // Assuming "Jadwal" link refers to "admin/jadwal/index.php"
                if (link.getAttribute('href') === 'index.php') { // This is the "Jadwal" link
                    const currentFolder = pathSegments[adminIndex + 1]; // e.g., 'jadwal'
                    if (currentFolder === 'jadwal' && currentPathWithoutQuery === 'jadwal/index.php') {
                        link.classList.add('active');
                    } else if (currentFolder === 'Tahun_Akademik' && currentPathWithoutQuery === 'Tahun_Akademik/index.php') { // Fix for Tahun Akademik in sidebar
                        link.classList.add('active');
                    } else if (currentFolder === 'kelas' && currentPathWithoutQuery === 'kelas/index.php') { // Fix for Kelas in sidebar
                        link.classList.add('active');
                    }
                } else if (linkRelativePath === currentPathWithoutQuery) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>

</html>
