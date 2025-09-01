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

// Ambil daftar Tahun Akademik untuk filter dan dropdown form
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun, is_active FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

$selected_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? null;

if ($selected_tahun_akademik_id === null) {
    $stmt_active_tahun = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
    $active_tahun = $stmt_active_tahun->fetch(PDO::FETCH_ASSOC);
    $selected_tahun_akademik_id = $active_tahun['id'] ?? ($tahun_akademik_options[0]['id'] ?? null);
}

if ($selected_tahun_akademik_id === null) {
    $message = "Tidak ada Tahun Akademik yang ditemukan atau diatur aktif.";
    $alert_type = 'alert-error';
    $selected_tahun_akademik_id = 0;
} else {
    $selected_tahun_akademik_id = (int)$selected_tahun_akademik_id;
}


// --- Handle AJAX POST untuk menambah atau mengedit guru ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response_status = 'error';
    $response_message = 'Terjadi kesalahan tidak dikenal.';

    try { 
        $nip_baru       = $_POST['NIPguru'] ?? null; 
        $nip_lama_for_update = $_POST['NIP_lama_for_update'] ?? null; 
        $name     = $_POST['namaguru'] ?? '';
        $email    = $_POST['emailguru'] ?? '';
        $gender   = $_POST['genderguru'] ?? ''; 
        $dob_raw  = $_POST['dobguru'] ?? '';
        $alamat   = $_POST['alamatguru'] ?? '';
        $password = $_POST['passwordguru'] ?? null; 

        $dob = !empty($dob_raw) ? $dob_raw : null;

        $no_hp_raw = $_POST['nohpguru'] ?? '';
        $no_hp = null; 
        if (is_numeric($no_hp_raw) && $no_hp_raw !== '') {
            $no_hp = (int)$no_hp_raw;
            if ($no_hp < 0 || $no_hp > 99999999999999) { 
                throw new Exception("Nomor HP terlalu besar atau negatif untuk disimpan.");
            }
        } else if (!empty($no_hp_raw)) { 
            throw new Exception("Nomor HP harus berupa angka.");
        }

        $foto_path_db = null;
        $folder_upload = "../../uploads/guru/";
        $upload_succeeded = true; 

        if (!is_dir($folder_upload)) {
            mkdir($folder_upload, 0777, true);
        }

        if (isset($_FILES['photoguru']) && $_FILES['photoguru']['error'] === UPLOAD_ERR_OK) {
            $foto_tmp = $_FILES['photoguru']['tmp_name'];
            $foto_name = $_FILES['photoguru']['name'];
            $ext = pathinfo($foto_name, PATHINFO_EXTENSION);
            $nama_foto_baru = uniqid() . '.' . $ext;
            $dest_path = $folder_upload . $nama_foto_baru;

            if (move_uploaded_file($foto_tmp, $dest_path)) {
                $foto_path_db = $nama_foto_baru;
            } else {
                throw new Exception("Gagal mengunggah foto guru. Coba lagi atau pastikan folder 'uploads/guru/' dapat ditulis.");
            }
        } else if (isset($_POST['old_photoguru']) && !empty($_POST['old_photoguru'])) {
            $foto_path_db = $_POST['old_photoguru'];
        }

        if ($upload_succeeded) {
            if ($_POST['action'] === 'tambah') {
                if ($nip_baru && $name && $email && $password && $dob && $no_hp && $alamat) { 
                    try {
                        $stmt_check_nip = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ?");
                        $stmt_check_nip->execute([$nip_baru]);
                        if ($stmt_check_nip->fetchColumn() > 0) {
                            $response_message = "Gagal menambahkan guru: NIP sudah terdaftar.";
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $admission_date = date('Y-m-d H:i:s'); 
                            
                            $stmt = $pdo->prepare("INSERT INTO guru (nip, name, email, gender, dob, no_hp, alamat, photo, pass, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$nip_baru, $name, $email, $gender, $dob, $no_hp, $alamat, $foto_path_db, $hashed_password, $admission_date]);
                            $response_status = 'success';
                            $response_message = "Guru berhasil ditambahkan!";
                        }
                    } catch (PDOException $e) {
                        $response_message = "Gagal menambahkan guru (DB Error): " . $e->getMessage();
                    }
                } else {
                    $response_message = "Mohon lengkapi semua field yang diperlukan (NIP, Nama, Email, Gender, Tanggal Lahir, Password) untuk menambah guru.";
                }
            } elseif ($_POST['action'] === 'edit') {
                if ($nip_lama_for_update && $nip_baru && $name && $email && $gender && $dob) { 
                    try {
                        if ($nip_baru !== $nip_lama_for_update) {
                            $stmt_check_nip_exist = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ? AND nip != ?");
                            $stmt_check_nip_exist->execute([$nip_baru, $nip_lama_for_update]);
                            if ($stmt_check_nip_exist->fetchColumn() > 0) {
                                $response_message = "Gagal mengupdate guru: NIP baru sudah terdaftar untuk guru lain.";
                                throw new Exception($response_message); 
                            }
                        }

                        if ($foto_path_db && isset($_POST['old_photoguru']) && $_POST['old_photoguru'] !== $foto_path_db && file_exists($folder_upload . $_POST['old_photoguru'])) {
                            unlink($folder_upload . $_POST['old_photoguru']);
                        }
        
                        $update_pass_sql = '';
                        $update_pass_params = [];
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $update_pass_sql = ', pass = ?';
                            $update_pass_params = [$hashed_password];
                        }
        
                        $stmt = $pdo->prepare("UPDATE guru SET nip = ?, name = ?, email = ?, gender = ?, dob = ?, no_hp = ?, alamat = ?, photo = ? " . $update_pass_sql . " WHERE nip = ?");
                        $stmt->execute(array_merge([$nip_baru, $name, $email, $gender, $dob, $no_hp, $alamat, $foto_path_db], $update_pass_params, [$nip_lama_for_update]));
                        
                        $response_status = 'success';
                        $response_message = "Guru berhasil diupdate!";
                    } catch (PDOException $e) {
                        $response_message = "Gagal mengupdate guru (DB Error): " . $e->getMessage();
                    } catch (Exception $e) { 
                        $response_message = $e->getMessage();
                    }
                } else {
                    $response_message = "Mohon lengkapi semua field yang diperlukan untuk mengupdate guru. (NIP, Nama, Email, Gender, Tanggal Lahir, Alamat)";
                }
            }
        }
    } catch (Throwable $e) { 
        $response_message = "Kesalahan fatal di server: " . $e->getMessage() . " (Line: " . $e->getLine() . " in " . basename($e->getFile()) . ")";
        error_log("Fatal error in guru AJAX POST: " . $e->getMessage() . " on line " . $e->getLine() . " in file " . $e->getFile() . "\n" . $e->getTraceAsString());
    }
    
    if ($response_status === 'success') {
        echo "success:" . $response_message;
    } else {
        echo "error: " . $response_message;
    }
    exit; 
} 
// --- END Handle AJAX POST untuk menambah atau mengedit guru ---


// --- Handle GET requests (delete guru) ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus_guru' && isset($_GET['NIP'])) {
    $nip_to_delete = $_GET['NIP'];
    $current_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? $selected_tahun_akademik_id;

    try {
        $stmt_get_photo = $pdo->prepare("SELECT photo FROM guru WHERE nip = ?");
        $stmt_get_photo->execute([$nip_to_delete]);
        $guru_data = $stmt_get_photo->fetch(PDO::FETCH_ASSOC);
        $foto_to_delete = $guru_data['photo'] ?? '';

        $stmt = $pdo->prepare("DELETE FROM guru WHERE nip = ?");
        $stmt->execute([$nip_to_delete]);

        $folder_upload = "../../uploads/guru/";
        if (!empty($foto_to_delete) && $foto_to_delete != 'default.jpg' && file_exists($folder_upload . $foto_to_delete)) {
            unlink($folder_upload . $foto_to_delete);
        }

        $message = "Guru berhasil dihapus!";
        $alert_type = 'alert-success';
        
        header("Location: index.php?success=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    } catch (PDOException $e) {
        $message = "Gagal menghapus guru: " . $e->getMessage();
        $alert_type = 'alert-error';
        header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    }
}


// --- Ambil daftar Guru untuk ditampilkan ---
$query_guru = "SELECT * FROM guru ORDER BY name ASC";

$stmt_guru = $pdo->prepare($query_guru);
$stmt_guru->execute();
$guru_list = $stmt_guru->fetchAll(PDO::FETCH_ASSOC);


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
    <title>Manajemen Guru | SuperAdmin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
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
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .data-table th, .data-table td {
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
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
            display: inline-block;
            margin-right: 5px;
        }

        .action-link {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
            display: inline-block;
            margin-right: 5px;
            /* Added spacing */
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
            margin-right: 15px;
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
            display: flex;
            align-items: center;
            gap: 12px;
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
        /* Modals */
        .modal {
            display: none; 
            position: fixed; 
            z-index: 1001; 
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            padding-top: 60px;
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            border: 1px solid #888;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 22px;
            font-weight: 600;
        }
        .close-btn {
            color: var(--light-text-color);
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-btn:hover,
        .close-btn:focus {
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
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            gap: 10px;
        }
        .modal-footer .btn {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: background-color 0.2s, color 0.2s;
            border: none;
        }
        .modal-footer .btn-cancel {
            background-color: #bdc3c7;
            color: white;
        }
        .modal-footer .btn-cancel:hover {
            background-color: #95a5a6;
        }
        .modal-footer .btn-submit {
            background-color: var(--primary-color);
            color: white;
        }
        .modal-footer .btn-submit:hover {
            background-color: #16a085;
        }
        .profile-photo {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
        }
        .photo-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }
        .photo-upload img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
        }
        .table-responsive {
            overflow-x: auto;
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
            <a href="index.php" class="active">
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
            <a onclick="showLogoutConfirmation()" id="logoutButtonSidebar">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="content" id="content">
        <div class="header" id="header">
            <button class="toggle-btn" id="toggle-btn"><i class="fas fa-bars"></i></button>
            <h1>Manajemen Guru</h1>
            <div class="user-info" id="userInfo">
                <img src="<?= htmlspecialchars($superadmin_photo) ?>" alt="User Photo">
                <span><?= htmlspecialchars($superadmin_name) ?></span>
                <i class="fas fa-caret-down"></i>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Daftar Guru</h2>
            <?php if ($message): ?>
                <div class="alert <?= htmlspecialchars($alert_type) ?>">
                    <i class="fas fa-info-circle"></i>
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <button type="button" class="add-link" id="tambahGuruBtn"><i class="fas fa-plus-circle"></i> Tambah Guru</button>
            </div>

           <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NIP</th>
                            <th style="text-align: center;">Profil</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Jenis Kelamin</th>
                            <th>No. HP</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($guru_list)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Tidak ada data guru.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($guru_list as $guru): ?>
                                <tr>
                                    <td><?= htmlspecialchars($guru['nip'] ?? '') ?></td>
                                    <td style="text-align: center;"><img src="../../uploads/guru/<?= htmlspecialchars($guru['photo'] ?? 'default.jpg') ?>" alt="Foto Guru" class="profile-photo"></td>
                                    <td><?= htmlspecialchars($guru['name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($guru['email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($guru['gender'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($guru['no_hp'] ?? '') ?></td>
                                    <td>
                                        <button class="action-link edit" 
                                            data-nip="<?= urlencode($guru['nip'] ?? '') ?>"
                                            data-name="<?= urlencode($guru['name'] ?? '') ?>" 
                                            data-email="<?= urlencode($guru['email'] ?? '') ?>" 
                                            data-gender="<?= urlencode($guru['gender'] ?? '') ?>"
                                            data-dob="<?= urlencode($guru['dob'] ?? '') ?>" 
                                            data-nohp="<?= urlencode($guru['no_hp'] ?? '') ?>"
                                            data-alamat="<?= urlencode($guru['alamat'] ?? '') ?>"
                                            data-photo="<?= urlencode($guru['photo'] ?? '') ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-link delete" data-nip="<?= urlencode($guru['nip'] ?? '') ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="guruModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Guru</h3>
                <span class="close-btn">&times;</span>
            </div>
            <form id="guruForm" action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="NIP_lama_for_update" id="oldNip">
                <input type="hidden" name="old_photoguru" id="oldPhoto">
                
                <div class="form-group">
                    <label for="NIPguru">NIP <span style="color: red;">*</span></label>
                    <input type="text" id="NIPguru" name="NIPguru" required>
                </div>
                <div class="form-group">
                    <label for="namaguru">Nama <span style="color: red;">*</span></label>
                    <input type="text" id="namaguru" name="namaguru" required>
                </div>
                <div class="form-group">
                    <label for="emailguru">Email <span style="color: red;">*</span></label>
                    <input type="email" id="emailguru" name="emailguru" required>
                </div>
                <div class="form-group">
                    <label for="genderguru">Jenis Kelamin <span style="color: red;">*</span></label>
                    <select id="genderguru" name="genderguru">
                        <option value="">Pilih Jenis Kelamin</option>
                        <option value="Laki-laki">laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dobguru">Tanggal Lahir <span style="color: red;">*</span></label>
                    <input type="date" id="dobguru" name="dobguru" required>
                </div>
                <div class="form-group">
                    <label for="nohpguru">No. HP</label>
                    <input type="text" id="nohpguru" name="nohpguru">
                </div>
                <div class="form-group">
                    <label for="alamatguru">Alamat</label>
                    <textarea id="alamatguru" name="alamatguru"></textarea>
                </div>
                <div class="form-group">
                    <label for="passwordguru">Password <span id="password-req" style="color: red;">*</span></label>
                    <input type="password" id="passwordguru" name="passwordguru">
                </div>
                <div class="form-group">
                    <label for="photoguru">Foto Profil</label>
                    <input type="file" id="photoguru" name="photoguru" accept="image/*">
                </div>
                <div class="photo-upload">
                    <img id="currentPhotoPreview" src="" alt="Foto Profil" style="display:none;">
                    <p id="currentPhotoText" style="display:none;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" id="cancelBtn">Batal</button>
                    <button type="submit" class="btn btn-submit">Simpan</button>
                </div>
            </form>
        </div>
    </div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    $(document).ready(function() {
        // Cache DOM elements
        const $sidebar = $('#sidebar');
        const $content = $('#content');
        const $header = $('#header');
        const $toggleBtn = $('#toggle-btn');
        const $userInfo = $('#userInfo');
        const $dropdownMenu = $('#dropdownMenu');
        const $guruModal = $('#guruModal');
        const $modalTitle = $('#modalTitle');
        const $formAction = $('#formAction');
        const $guruForm = $('#guruForm');
        const $NIPguru = $('#NIPguru');
        const $namaguru = $('#namaguru');
        const $emailguru = $('#emailguru');
        const $genderguru = $('#genderguru');
        const $dobguru = $('#dobguru');
        const $nohpguru = $('#nohpguru');
        const $alamatguru = $('#alamatguru');
        const $passwordguru = $('#passwordguru');
        const $passwordReq = $('#password-req');
        const $oldNip = $('#oldNip');
        const $oldPhoto = $('#oldPhoto');
        const $currentPhotoPreview = $('#currentPhotoPreview');
        const $currentPhotoText = $('#currentPhotoText');

        // Sidebar and Header Toggling
        $toggleBtn.on('click', function() {
            $sidebar.toggleClass('collapsed');
            $content.toggleClass('shifted');
            $header.toggleClass('shifted');
        });

        // User Info Dropdown
        $userInfo.on('click', function(e) {
            e.stopPropagation(); 
            $dropdownMenu.fadeToggle(200);
        });

        // Close dropdown when clicking outside
        $(document).on('click', function() {
            $dropdownMenu.fadeOut(200);
        });

        // Guru Modal Logic
        const resetGuruModal = () => {
            $guruForm[0].reset();
            $NIPguru.prop('disabled', false);
            $passwordguru.prop('required', true).val('');
            $passwordReq.show();
            $oldPhoto.val('');
            $currentPhotoPreview.hide().attr('src', '');
            $currentPhotoText.hide();
        };

        $('#tambahGuruBtn').on('click', function() {
            resetGuruModal();
            $modalTitle.text('Tambah Guru');
            $formAction.val('tambah');
            $guruModal.show();
        });

        $('.edit').on('click', function() {
            resetGuruModal(); 
            const data = $(this).data();
            $modalTitle.text('Edit Guru');
            $formAction.val('edit');
            
            // Correctly decode URL-encoded data from PHP
            $oldNip.val(decodeURIComponent(data.nip));
            $NIPguru.val(decodeURIComponent(data.nip)).prop('disabled', true);
            $namaguru.val(decodeURIComponent(data.name));
            $emailguru.val(decodeURIComponent(data.email));
            const decodedGender = decodeURIComponent(data.gender);
            if (decodedGender) {
                // Convert the first letter to uppercase for a proper match
                const normalizedGender = decodedGender.charAt(0).toUpperCase() + decodedGender.slice(1);
                $genderguru.val(normalizedGender);
            }
            $dobguru.val(decodeURIComponent(data.dob));
            $nohpguru.val(decodeURIComponent(data.nohp));
            $alamatguru.val(decodeURIComponent(data.alamat));
            
            $passwordguru.prop('required', false);
            $passwordReq.hide();
            
            if (data.photo) {
                const photoName = decodeURIComponent(data.photo);
                $oldPhoto.val(photoName);
                $currentPhotoPreview.show().attr('src', `../../uploads/guru/${photoName}`);
                $currentPhotoText.show().text(`File lama: ${photoName}`);
            } else {
                $oldPhoto.val('');
            }
            $guruModal.show();
        });

        $('.close-btn, #cancelBtn').on('click', function() {
            $guruModal.hide();
        });

        window.onclick = function(event) {
            if (event.target === $guruModal[0]) {
                $guruModal.hide();
            }
        };

        // Submit form with AJAX
        $guruForm.on('submit', async function(e) {
            $NIPguru.prop('disabled', false); 
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Memproses...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.text();
                const trimmedResult = result.trim();

                if (trimmedResult.startsWith("success:")) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: trimmedResult.substring(8),
                        confirmButtonText: 'OK'
                    });
                    window.location.reload();
                } else if (trimmedResult.startsWith("error:")) {
                    await Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: trimmedResult.substring(6),
                        confirmButtonText: 'OK'
                    });
                } else {
                    console.error("Server responded with unexpected output:", result);
                    await Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Respons server tidak terduga. Output PHP: ' + trimmedResult.substring(0, 300) + '...',
                        confirmButtonText: 'OK'
                    });
                }
            } catch (error) {
                console.error("Fetch error:", error);
                await Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan jaringan atau client: ' + error.message,
                    confirmButtonText: 'OK'
                });
            }
        });

        // SweetAlert for delete confirmation
        $('.delete').on('click', function() {
            const nipToDelete = $(this).data('nip');
            const currentTahunAkademikId = new URLSearchParams(window.location.search).get('tahun_akademik_id');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Anda tidak akan dapat mengembalikan data ini!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?action=hapus_guru&NIP=${nipToDelete}&tahun_akademik_id=${currentTahunAkademikId}`;
                }
            });
        });
    });
</script>
</body>
</html>