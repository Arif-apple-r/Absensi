<?php
session_start();
// Aktifkan reporting error untuk debugging. Pastikan ini selalu ada.
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require '../../koneksi.php';

$superadmin_name = htmlspecialchars($_SESSION['superadmin_name'] ?? 'SuperAdmin');
$superadmin_photo = 'https://placehold.co/40x40/cccccc/333333?text=SA'; 

$message = '';
$alert_type = '';

// Ambil daftar Tahun Akademik untuk dropdown filter dan form
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun, is_active FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

// Tentukan tahun akademik yang sedang dipilih (dari GET atau default ke yang aktif)
$selected_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? null;

if ($selected_tahun_akademik_id === null) {
    // Jika tidak ada parameter tahun_akademik_id di URL, ambil yang aktif
    $stmt_active_tahun = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
    $active_tahun = $stmt_active_tahun->fetch(PDO::FETCH_ASSOC);
    $selected_tahun_akademik_id = $active_tahun['id'] ?? ($tahun_akademik_options[0]['id'] ?? null); // Default ke tahun terbaru jika tidak ada yang aktif
}

// Konversi ke integer jika tidak null
if ($selected_tahun_akademik_id !== null) {
    $selected_tahun_akademik_id = (int)$selected_tahun_akademik_id;
}


// --- Handle Form Submission (Tambah/Edit Kelas) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_kelas']) || isset($_POST['edit_kelas']))) {
    $id_kelas = $_POST['id_kelas'] ?? null;
    $nama_kelas = $_POST['nama_kelas'] ?? '';
    $id_tahun_akademik_form = $_POST['id_tahun_akademik'] ?? null; // Dari form
    $deskripsi = $_POST['deskripsi'] ?? '';

    $foto_lama = $_POST['old_photo'] ?? '';
    $foto_baru = $_FILES['photo']['name'] ?? '';
    $tmp_foto_baru = $_FILES['photo']['tmp_name'] ?? '';
    $folder_upload = "../../uploads/kelas/";

    // Pastikan folder upload ada
    if (!is_dir($folder_upload)) {
        mkdir($folder_upload, 0777, true);
    }

    $nama_foto_untuk_db = $foto_lama; // Default menggunakan foto lama

    if (!empty($foto_baru)) {
        $ext = pathinfo($foto_baru, PATHINFO_EXTENSION);
        $nama_foto_baru_unik = uniqid() . '.' . $ext;
        $path_foto = $folder_upload . $nama_foto_baru_unik;

        if (move_uploaded_file($tmp_foto_baru, $path_foto)) {
            $nama_foto_untuk_db = $nama_foto_baru_unik;
            // Hapus foto lama jika ada dan berbeda dengan yang baru
            if (!empty($foto_lama) && $foto_lama != 'default.jpg' && file_exists($folder_upload . $foto_lama)) {
                unlink($folder_upload . $foto_lama);
            }
        } else {
            $message = "Gagal mengunggah foto.";
            $alert_type = 'alert-error';
            goto end_form_processing; 
        }
    }

    if ($nama_kelas && $id_tahun_akademik_form) { // Validasi id_tahun_akademik_form
        try {
            if ($id_kelas) {
                // Update existing class
                $stmt = $pdo->prepare("UPDATE class SET nama_kelas = ?, id_tahun_akademik = ?, deskripsi = ?, photo = ? WHERE id = ?"); 
                $stmt->execute([$nama_kelas, $id_tahun_akademik_form, $deskripsi, $nama_foto_untuk_db, $id_kelas]);
                $message = "Kelas berhasil diupdate!";
                $alert_type = 'alert-success';
            } else {
                // Insert new class
                $stmt = $pdo->prepare("INSERT INTO class (nama_kelas, id_tahun_akademik, deskripsi, photo) VALUES (?, ?, ?, ?)"); 
                $stmt->execute([$nama_kelas, $id_tahun_akademik_form, $deskripsi, $nama_foto_untuk_db]);
                $message = "Kelas berhasil ditambahkan!";
                $alert_type = 'alert-success';
            }
            header("Location: index.php?success=" . urlencode($message) . "&tahun_akademik_id=" . urlencode($id_tahun_akademik_form));
            exit;
        } catch (PDOException $e) {
            $error_message = "Gagal memproses Kelas: " . $e->getMessage();
            $message = $error_message;
            $alert_type = 'alert-error';
        }
    } else {
        $message = "Mohon lengkapi semua field yang diperlukan (Nama Kelas dan Tahun Akademik).";
        $alert_type = 'alert-error';
    }
    end_form_processing:; 
    // Redirect with error message and keep current filter
    header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . urlencode($selected_tahun_akademik_id));
    exit;
}

// --- Handle Delete Kelas ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_kelas_to_delete = $_GET['id'];
    $current_tahun_akademik_id_after_action = $_GET['tahun_akademik_id'] ?? $selected_tahun_akademik_id; // Capture current filter

    try {
        // Cek apakah ada jadwal atau siswa yang terkait dengan kelas ini
        $stmt_check_jadwal = $pdo->prepare("SELECT COUNT(*) FROM jadwal WHERE class_id = ?");
        $stmt_check_jadwal->execute([$id_kelas_to_delete]);
        if ($stmt_check_jadwal->fetchColumn() > 0) {
            $message = "Tidak dapat menghapus Kelas ini karena masih ada jadwal yang terkait.";
            $alert_type = 'alert-error';
            header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . urlencode($current_tahun_akademik_id_after_action));
            exit;
        }
        
        $stmt_check_siswa = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE class_id = ?");
        $stmt_check_siswa->execute([$id_kelas_to_delete]);
        if ($stmt_check_siswa->fetchColumn() > 0) {
            $message = "Tidak dapat menghapus Kelas ini karena masih ada siswa yang terkait.";
            $alert_type = 'alert-error';
            header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . urlencode($current_tahun_akademik_id_after_action));
            exit;
        }

        // Ambil nama foto untuk dihapus
        $stmt_get_photo = $pdo->prepare("SELECT photo FROM class WHERE id = ?");
        $stmt_get_photo->execute([$id_kelas_to_delete]);
        $class_data = $stmt_get_photo->fetch(PDO::FETCH_ASSOC);
        $folder_upload = "../../uploads/kelas/"; // Define folder_upload here for delete operation
        $foto_to_delete = $class_data['photo'] ?? '';

        $stmt = $pdo->prepare("DELETE FROM class WHERE id = ?");
        $stmt->execute([$id_kelas_to_delete]);

        // Hapus file foto jika ada dan bukan default
        if (!empty($foto_to_delete) && $foto_to_delete != 'default.jpg' && file_exists($folder_upload . $foto_to_delete)) {
            unlink($folder_upload . $foto_to_delete);
        }

        $message = "Kelas berhasil dihapus!";
        $alert_type = 'alert-success';
        header("Location: index.php?success=" . urlencode($message) . "&tahun_akademik_id=" . urlencode($current_tahun_akademik_id_after_action));
        exit;
    } catch (PDOException $e) {
        $message = "Gagal menghapus Kelas: " . $e->getMessage();
        $alert_type = 'alert-error';
    }
    header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . urlencode($current_tahun_akademik_id_after_action));
    exit;
}


// Ambil semua data Kelas, join dengan tahun_akademik (difilter berdasarkan selected_tahun_akademik_id)
$query_kelas = "SELECT c.*, ta.nama_tahun FROM class c LEFT JOIN tahun_akademik ta ON c.id_tahun_akademik = ta.id";
$params = [];

if ($selected_tahun_akademik_id !== null && $selected_tahun_akademik_id !== 0) {
    $query_kelas .= " WHERE c.id_tahun_akademik = ?";
    $params[] = $selected_tahun_akademik_id;
}
$query_kelas .= " ORDER BY ta.nama_tahun DESC, c.nama_kelas ASC";

$stmt = $pdo->prepare($query_kelas);
$stmt->execute($params);
$kelas_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil pesan dari URL jika ada
if (isset($_GET['success'])) {
    $message = htmlspecialchars($_GET['success']);
    $alert_type = 'alert-success';
} elseif (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $alert_type = 'alert-error';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kelas | SuperAdmin</title>
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
        .data-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .data-table tr:hover {
            background-color: #fafafa;
        }
        .action-link {
            padding: 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
            display: inline-flex; /* Agar bisa diatur margin */
        }
        .action-link.edit {
            background-color: #3498db; /* Blue for Edit */
            color: white;
        }
        .action-link.edit:hover {
            background-color: #2980b9;
        }
        .action-link.delete {
            background-color: #e74c3c;
            color: white;
            margin-top: 10px;
        }
        .action-link.delete:hover {
            background-color: #c0392b;
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

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1001; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
            padding-top: 50px; /* Jarak dari atas */
        }

        .modal-content {
            background-color: var(--card-background);
            margin: auto;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
            position: relative;
            animation-name: animatetop;
            animation-duration: 0.4s;
        }

        @keyframes animatetop {
            from {top: -300px; opacity: 0}
            to {top: 0; opacity: 1}
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .modal-content h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--text-color);
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }

        .form-group input[type="text"],
        .form-group textarea,
        .form-group input[type="file"],
        .form-group select { /* Tambahkan select di sini */
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            background-color: var(--background-color);
            color: var(--text-color);
        }
        .form-group input[type="file"] {
            padding: 8px 10px; /* Sedikit beda padding untuk input file */
        }
        .form-group .photo-preview {
            max-width: 100px;
            height: auto;
            display: block;
            margin-top: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .form-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
        }

        .form-actions .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .form-actions .btn-primary:hover {
            background-color: #16a085;
            transform: translateY(-2px);
        }

        .form-actions .btn-secondary {
            background-color: #ccc;
            color: var(--text-color);
        }

        .form-actions .btn-secondary:hover {
            background-color: #bbb;
            transform: translateY(-2px);
        }

        /* Filter Section */
        .filter-section {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
            background: var(--background-color);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            align-items: flex-end; /* Align items to the bottom */
        }
        .filter-section .filter-group {
            margin-bottom: 0; /* Override default margin */
            flex: 1; /* Allow groups to take equal space */
            min-width: 150px; /* Minimum width for filter dropdowns */
        }
        .filter-section .filter-group label {
            font-size: 0.9em;
            margin-bottom: 5px;
            color: var(--light-text-color);
            display: block; /* Ensure label takes full width */
        }
        .filter-section .filter-group select,
        .filter-section .filter-group button {
            width: 100%; 
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: var(--card-background);
            color: var(--text-color);
            font-size: 0.95em;
        }
        .filter-section .filter-group button {
            background-color: var(--primary-color);
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
            height: 42px;
        }
        .filter-section .filter-group button:hover {
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
        /* Responsive adjustments */
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
            .data-table th, .data-table td {
                padding: 10px;
                font-size: 0.85em;
            }
            .modal-content {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>SuperAdminCoy</span></div>
        <nav>
            <a href="../dashboard_superadmin.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../admin/index.php">
                <i class="fas fa-users-cog"></i>
                <span>Admin</span>
            </a>
            <a href="../guru/index.php">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Guru</span>
            </a>
            <a href="../siswa/index.php">
                <i class="fas fa-user-graduate"></i>
                <span>Siswa</span>
            </a>
            <a href="../jadwal/index.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal</span>
            </a>
            <a href="../Tahun_Akademik/index.php">
                <i class="fas fa-calendar"></i>
                <span>Tahun Akademik</span>
            </a>
            <a href="index.php">
                <i class="fas fa-school"></i>
                <span>Kelas</span>
            </a>
            <a href="../mapel/index.php">
                <i class="fas fa-book"></i>
                <span>Mata Pelajaran</span>
            </a>
            <div class="logout-button-container">
                <a onclick="showLogoutConfirm(); return false;" id="logoutButton"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
            </div>
        </nav>
    </div>


    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-school"></i> Manajemen Kelas</h1>
        <div class="user-info" id="userInfoDropdown">
            <span><?= $superadmin_name ?></span>
            <div class="dropdown-menu" id="userDropdownContent">
                <!-- <a href="profil_superadmin.php"><i class="fas fa-user-circle"></i> Profil</a> -->
                <a href="#" id="logoutDropdownLink" onclick="showLogoutConfirm(); return false;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Kelas</h2>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Filter Section for Tahun Akademik -->
            <div class="filter-section">
                <div class="filter-group">
                    <label for="filter_tahun_akademik">Tahun Akademik:</label>
                    <select id="filter_tahun_akademik" onchange="applyTahunAkademikFilter()">
                        <option value="all">Semua Tahun Akademik</option>
                        <?php foreach ($tahun_akademik_options as $ta_option): ?>
                            <option value="<?php echo htmlspecialchars($ta_option['id']); ?>"
                                <?php echo ($ta_option['id'] == $selected_tahun_akademik_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ta_option['nama_tahun']); ?>
                                <?php echo ($ta_option['is_active']) ? ' (Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <a href="#" class="add-link" onclick="openModal('tambah'); return false;">
                <i class="fas fa-plus-circle"></i> Tambah Kelas
            </a>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Foto</th>
                            <th>Nama Kelas</th>
                            <th>Tahun Akademik</th>
                            <th>Deskripsi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kelas_list)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Tidak ada data kelas untuk tahun akademik ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kelas_list as $kelas): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($kelas['id']); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars('../../uploads/kelas/' . ($kelas['photo'] ?? 'default.jpg')); ?>" alt="Foto Kelas" style="width: 50px; height: 50px; border-radius: 8px; object-fit: cover;"
                                            loading="lazy"
                                            onerror="this.onerror=null;this.src='https://placehold.co/50x50/cccccc/333333?text=NO+IMG';"
                                        >
                                    </td>
                                    <td><?php echo htmlspecialchars($kelas['nama_kelas']); ?></td>
                                    <td><?php echo htmlspecialchars($kelas['nama_tahun'] ?? 'Tidak Ditetapkan'); ?></td>
                                    <td><?php echo htmlspecialchars($kelas['deskripsi'] ?? '-'); ?></td>
                                    <td>
                                        <a href="#" class="action-link edit" onclick="openModal('edit', 
                                                <?php echo htmlspecialchars($kelas['id']); ?>, 
                                                '<?php echo htmlspecialchars($kelas['nama_kelas']); ?>', 
                                                '<?php echo htmlspecialchars($kelas['deskripsi'] ?? ''); ?>', 
                                                '<?php echo htmlspecialchars($kelas['photo'] ?? ''); ?>',
                                                '<?php echo htmlspecialchars($kelas['id_tahun_akademik'] ?? ''); ?>'
                                            ); return false;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="action-link delete" onclick="openDeleteModal(<?php echo htmlspecialchars($kelas['id']); ?>); return false;">
                                            <i class="fas fa-trash"></i>
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

    <!-- Modal Tambah/Edit Kelas -->
    <div id="kelasModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Tambah Kelas</h2>
            <form id="kelasForm" method="POST" action="index.php" enctype="multipart/form-data">
                <input type="hidden" id="id_kelas" name="id_kelas">
                <input type="hidden" id="old_photo" name="old_photo">
                
                <div class="form-group">
                    <label for="nama_kelas">Nama Kelas:</label>
                    <input type="text" id="nama_kelas" name="nama_kelas" required>
                </div>
                
                <div class="form-group">
                    <label for="id_tahun_akademik">Tahun Akademik:</label>
                    <select id="id_tahun_akademik" name="id_tahun_akademik" required>
                        <option value="">Pilih Tahun Akademik</option>
                        <?php if (empty($tahun_akademik_options)): ?>
                            <option value="" disabled>Tidak ada Tahun Akademik tersedia</option>
                        <?php else: ?>
                            <?php foreach ($tahun_akademik_options as $ta_option): ?>
                                <option value="<?php echo htmlspecialchars($ta_option['id']); ?>">
                                    <?php echo htmlspecialchars($ta_option['nama_tahun']); ?>
                                    <?php echo ($ta_option['is_active']) ? ' (Aktif)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="deskripsi">Deskripsi (Opsional):</label>
                    <textarea id="deskripsi" name="deskripsi" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="photo">Foto Kelas (Opsional):</label>
                    <input type="file" id="photo" name="photo" accept="image/*">
                    <img id="photo_preview" class="photo-preview" src="https://placehold.co/100x100/cccccc/333333?text=NO+IMG" alt="Preview Foto">
                </div>

                <div class="form-actions">
                    <button type="submit" name="tambah_kelas" class="btn-primary">Simpan</button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script JavaScript -->
    <script>
        // Variabel untuk modal Kelas
        const kelasModal = document.getElementById("kelasModal");
        const modalTitle = kelasModal.querySelector("h2");
        const kelasForm = document.getElementById("kelasForm");
        const id_kelas_input = document.getElementById("id_kelas");
        const old_photo_input = document.getElementById("old_photo");
        const nama_kelas_input = document.getElementById("nama_kelas");
        const id_tahun_akademik_select = document.getElementById("id_tahun_akademik"); 
        const deskripsi_textarea = document.getElementById("deskripsi");
        const photo_input = document.getElementById("photo");
        const photo_preview = document.getElementById("photo_preview");
        const submitButton = kelasForm.querySelector('button[type="submit"]');
        const filterTahunAkademik = document.getElementById("filter_tahun_akademik");

        // Fungsi untuk membuka modal
        function openModal(action, id = '', nama = '', deskripsi = '', photo = '', idTahunAkademik = '') { 
            kelasForm.reset(); // Reset form setiap kali modal dibuka

            if (action === 'tambah') {
                modalTitle.textContent = "Tambah Kelas";
                submitButton.name = "tambah_kelas";
                submitButton.textContent = "Simpan";
                id_kelas_input.value = '';
                old_photo_input.value = '';
                photo_preview.src = "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";
                
                // Set default tahun akademik di form ke tahun yang aktif atau yang sedang difilter
                const currentFilterTahunAkademik = filterTahunAkademik.value;
                if (currentFilterTahunAkademik && currentFilterTahunAkademik !== 'all') {
                    id_tahun_akademik_select.value = currentFilterTahunAkademik;
                } else {
                    // Coba cari tahun aktif dari daftar options jika filter 'all'
                    let activeTaOption = Array.from(id_tahun_akademik_select.options).find(option => option.text.includes('(Aktif)'));
                    if (activeTaOption) {
                        id_tahun_akademik_select.value = activeTaOption.value;
                    } else {
                        id_tahun_akademik_select.value = ''; // Jika tidak ada tahun aktif, kosongkan
                    }
                }
            } else if (action === 'edit') {
                modalTitle.textContent = "Edit Kelas";
                submitButton.name = "edit_kelas";
                submitButton.textContent = "Update";
                id_kelas_input.value = id;
                nama_kelas_input.value = nama;
                id_tahun_akademik_select.value = idTahunAkademik; // Set nilai dropdown
                deskripsi_textarea.value = deskripsi;
                old_photo_input.value = photo;
                photo_preview.src = photo ? `../../uploads/kelas/${photo}` : "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";
            }
            kelasModal.style.display = "flex";
        }

        // Fungsi untuk menutup modal
        function closeModal() {
            kelasModal.style.display = "none";
        }

        // Fungsi untuk konfirmasi hapus dengan SweetAlert
        function openDeleteModal(id) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: 'Menghapus kelas ini juga akan menghapus semua jadwal, pertemuan, dan absensi yang terkait! Pastikan tidak ada siswa yang terdaftar di kelas ini.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#3498db',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const currentTahunAkademikId = filterTahunAkademik.value;
                    window.location.href = `index.php?action=hapus&id=${id}&tahun_akademik_id=${currentTahunAkademikId}`;
                }
            });
        }

        function showLogoutConfirm() {
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

        // Preview foto saat dipilih
        photo_input.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photo_preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                photo_preview.src = "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";
            }
        });

        // Fungsi untuk menerapkan filter Tahun Akademik
        function applyTahunAkademikFilter() {
            const selectedTahunAkademik = filterTahunAkademik.value;
            window.location.href = `index.php?tahun_akademik_id=${selectedTahunAkademik}`;
        }


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
        const logoutDropdownLink = document.getElementById('logoutDropdownLink');

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function() {
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.onclick = function(event) {
                if (event.target == kelasModal) { // Tambahan: Tutup modal jika klik di luar
                    closeModal();
                }
                if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                    if (userDropdownContent.style.display === 'block') {
                        userDropdownContent.style.display = 'none';
                    }
                }
            }
        }
        
        // SweetAlert for Logout Confirmation (untuk tombol di sidebar)
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
                    window.location.href = "../../logout.php"; // redirect logout (adjust path if needed)
                }
            });
        }
        
        // Bind logout button in sidebar to SweetAlert
        const logoutButtonSidebar = document.getElementById('logoutButton');
        if (logoutButtonSidebar) {
            logoutButtonSidebar.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default link behavior
                showLogoutConfirmation();
            });
        }
        // Bind logout button in dropdown to SweetAlert
        if (logoutDropdownLink) {
            logoutDropdownLink.addEventListener('click', function(e) {
                e.preventDefault(); // Prevent default link behavior
                showLogoutConfirmation();
            });
        }

        // Fungsi baru untuk menandai link sidebar yang aktif dengan benar
        window.addEventListener('DOMContentLoaded', (event) => {
            const currentPathname = window.location.pathname; 
            const pathSegments = currentPathname.split('/');
            const superadminIndex = pathSegments.indexOf('superadmin');
            let relativePathFromSuperadmin = '';

            if (superadminIndex !== -1 && pathSegments.length > superadminIndex) {
                relativePathFromSuperadmin = pathSegments.slice(superadminIndex + 1).join('/');
            } else {
                if (pathSegments.includes('kelas') && pathSegments.pop() === 'index.php') {
                    relativePathFromSuperadmin = 'kelas/index.php';
                } else {
                    relativePathFromSuperadmin = currentPathname.split('/').pop();
                }
            }
            
            document.querySelectorAll('.sidebar nav a').forEach(link => {
                link.classList.remove('active'); 

                let linkHref = new URL(link.href).pathname; 
                const linkSegments = linkHref.split('/');
                const linkSuperadminIndex = linkSegments.indexOf('superadmin');
                let linkRelativePath = '';

                if (linkSuperadminIndex !== -1 && linkSegments.length > linkSuperadminIndex) {
                    linkRelativePath = linkSegments.slice(linkSuperadminIndex + 1).join('/');
                } else {
                     linkRelativePath = linkHref.split('/').pop();
                }
                
                linkRelativePath = linkRelativePath.split('?')[0];
                let currentPathWithoutQuery = relativePathFromSuperadmin.split('?')[0];

                if (linkRelativePath === currentPathWithoutQuery) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>