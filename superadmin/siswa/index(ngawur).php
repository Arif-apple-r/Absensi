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
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun FROM tahun_akademik ORDER BY nama_tahun DESC");
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


// Ambil data untuk dropdown kelas di form (difilter berdasarkan Tahun Akademik yang dipilih)
$kelas_form_options = [];
if ($selected_tahun_akademik_id) {
    $stmt_kelas_form = $pdo->prepare("SELECT id, nama_kelas FROM class WHERE id_tahun_akademik = ? ORDER BY nama_kelas ASC");
    $stmt_kelas_form->execute([$selected_tahun_akademik_id]);
    $kelas_form_options = $stmt_kelas_form->fetchAll(PDO::FETCH_ASSOC);
}


// --- Handle AJAX POST untuk menambah atau mengedit siswa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_start(); // Mulai output buffering untuk mencegah output PHP yang tidak terduga merusak JSON
    $response = ['status' => 'error', 'message' => 'Terjadi kesalahan tidak dikenal.'];

    try { 
        $NIS      = $_POST['NISsiswa'] ?? null; 
        $NIS_for_update = $_POST['siswaNISHidden'] ?? null; 
        $name     = $_POST['namasiswa'] ?? '';
        $email    = $_POST['emailsiswa'] ?? '';
        $gender   = $_POST['gender'] ?? ''; 
        $dob      = $_POST['dobsiswa'] ?? '';
        $alamat   = $_POST['alamatsiswa'] ?? '';
        $class_id = $_POST['class_id'] ?? null;
        $password = $_POST['passwordsiswa'] ?? null; 
        $current_tahun_akademik_id = $_POST['tahun_akademik_id'] ?? $selected_tahun_akademik_id;

        // --- Penanganan no_hp dengan validasi dan casting eksplisit ---
        $no_hp_raw = $_POST['nohpsiswa'] ?? '';
        $no_hp = 0; 
        if (is_numeric($no_hp_raw) && $no_hp_raw !== '') {
            $no_hp = (int)$no_hp_raw;
            if ($no_hp < 0 || $no_hp > 4294967295) { 
                $response['message'] = "Nomor HP terlalu besar atau negatif untuk disimpan.";
                throw new Exception("Nomor HP tidak valid.");
            }
        } else if (!empty($no_hp_raw)) { 
            $response['message'] = "Nomor HP harus berupa angka.";
            throw new Exception("Nomor HP tidak valid.");
        }

        $foto_path_db = null;
        $folder_upload = "../../uploads/siswa/";
        $upload_succeeded = true; 

        if (!is_dir($folder_upload)) {
            mkdir($folder_upload, 0777, true);
        }

        if (isset($_FILES['photosiswa']) && $_FILES['photosiswa']['error'] === UPLOAD_ERR_OK) {
            $foto_tmp = $_FILES['photosiswa']['tmp_name'];
            $foto_name = $_FILES['photosiswa']['name'];
            $ext = pathinfo($foto_name, PATHINFO_EXTENSION);
            $nama_foto_baru = uniqid() . '.' . $ext;
            $dest_path = $folder_upload . $nama_foto_baru;

            if (move_uploaded_file($foto_tmp, $dest_path)) {
                $foto_path_db = $nama_foto_baru;
            } else {
                $response['message'] = "Gagal mengunggah foto siswa. Coba lagi atau pastikan folder 'uploads/siswa/' dapat ditulis.";
                $upload_succeeded = false;
            }
        } else if (isset($_POST['old_photosiswa']) && !empty($_POST['old_photosiswa'])) {
            $foto_path_db = $_POST['old_photosiswa'];
        }

        if ($upload_succeeded) {
            if ($_POST['action'] === 'tambah_siswa') {
                if ($NIS && $name && $email && $gender && $dob && $alamat && $class_id && $password) { 
                    try {
                        $stmt_check_nis = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE NIS = ?");
                        $stmt_check_nis->execute([$NIS]);
                        if ($stmt_check_nis->fetchColumn() > 0) {
                            $response['message'] = "Gagal menambahkan siswa: NIS sudah terdaftar.";
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $admission_date = date('Y-m-d H:i:s'); 
                            
                            $stmt = $pdo->prepare("INSERT INTO siswa (NIS, name, email, gender, dob, no_hp, alamat, class_id, photo, pass, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$NIS, $name, $email, $gender, $dob, $no_hp, $alamat, $class_id, $foto_path_db, $hashed_password, $admission_date]);
                            $response['status'] = 'success';
                            $response['message'] = "Siswa berhasil ditambahkan!";
                        }
                    } catch (PDOException $e) {
                        $response['message'] = "Gagal menambahkan siswa (DB Error): " . $e->getMessage();
                    }
                } else {
                    $response['message'] = "Mohon lengkapi semua field yang diperlukan (termasuk password) untuk menambah siswa.";
                }
            } elseif ($_POST['action'] === 'edit_siswa') {
                if ($NIS_for_update && $name && $email && $gender && $dob && $alamat && $class_id) { 
                    try {
                        if ($foto_path_db && isset($_POST['old_photosiswa']) && $_POST['old_photosiswa'] !== $foto_path_db && file_exists($folder_upload . $_POST['old_photosiswa'])) {
                            unlink($folder_upload . $_POST['old_photosiswa']);
                        }

                        $update_pass_sql = '';
                        $update_pass_params = [];
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $update_pass_sql = ', pass = ?';
                            $update_pass_params = [$hashed_password];
                        }

                        $stmt = $pdo->prepare("UPDATE siswa SET name = ?, email = ?, gender = ?, dob = ?, no_hp = ?, alamat = ?, class_id = ?, photo = ? " . $update_pass_sql . " WHERE NIS = ?");
                        $stmt->execute(array_merge([$name, $email, $gender, $dob, $no_hp, $alamat, $class_id, $foto_path_db], $update_pass_params, [$NIS_for_update]));
                        
                        $response['status'] = 'success';
                        $response['message'] = "Siswa berhasil diupdate!";
                    } catch (PDOException $e) {
                        $response['message'] = "Gagal mengupdate siswa (DB Error): " . $e->getMessage();
                    }
                } else {
                    $response['message'] = "Mohon lengkapi semua field yang diperlukan untuk mengupdate siswa.";
                }
            }
        }
    } catch (Throwable $e) { 
        $response['message'] = "Kesalahan fatal di server: " . $e->getMessage() . " (Line: " . $e->getLine() . " in " . basename($e->getFile()) . ")";
        error_log("Fatal error in siswa AJAX POST: " . $e->getMessage() . " on line " . $e->getLine() . " in file " . $e->getFile() . "\n" . $e->getTraceAsString());
    }
    
    ob_end_clean(); // Hapus semua output yang ditangkap
    header('Content-Type: application/json'); // Set header ke JSON
    echo json_encode($response);
    exit; 
}

// --- Handle Delete Siswa ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus_siswa' && isset($_GET['NIS'])) {
    $nis_to_delete = $_GET['NIS'];
    $current_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? $selected_tahun_akademik_id;

    try {
        $stmt_get_siswa_id = $pdo->prepare("SELECT id FROM siswa WHERE NIS = ?");
        $stmt_get_siswa_id->execute([$nis_to_delete]);
        $siswa_id = $stmt_get_siswa_id->fetchColumn();

        if ($siswa_id) {
            $stmt_delete_absensi = $pdo->prepare("DELETE FROM absensi WHERE id_siswa = ?");
            $stmt_delete_absensi->execute([$siswa_id]);

            $stmt_get_photo = $pdo->prepare("SELECT photo FROM siswa WHERE NIS = ?");
            $stmt_get_photo->execute([$nis_to_delete]);
            $siswa_data = $stmt_get_photo->fetch(PDO::FETCH_ASSOC);
            $foto_to_delete = $siswa_data['photo'] ?? '';

            $stmt = $pdo->prepare("DELETE FROM siswa WHERE NIS = ?");
            $stmt->execute([$nis_to_delete]);

            $folder_upload = "../../uploads/siswa/";
            if (!empty($foto_to_delete) && $foto_to_delete != 'default.jpg' && file_exists($folder_upload . $foto_to_delete)) {
                unlink($folder_upload . $foto_to_delete);
            }

            $message = "Siswa berhasil dihapus!";
            $alert_type = 'alert-success';
        } else {
            $message = "Siswa dengan NIS tersebut tidak ditemukan.";
            $alert_type = 'alert-error';
        }
        
        header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    } catch (PDOException $e) {
        $message = "Gagal menghapus siswa: " . $e->getMessage();
        $alert_type = 'alert-error';
        header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    }
}

$query_siswa = "
    SELECT 
        s.*, 
        c.nama_kelas, 
        ta.nama_tahun 
    FROM siswa AS s
    JOIN class AS c ON s.class_id = c.id
    JOIN tahun_akademik AS ta ON c.id_tahun_akademik = ta.id
    WHERE ta.id = ? 
    ORDER BY c.nama_kelas ASC, s.name ASC
";
$stmt_siswa = $pdo->prepare($query_siswa);
$stmt_siswa->execute([$selected_tahun_akademik_id]);
$siswa_list = $stmt_siswa->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manajemen Siswa | SuperAdmin</title>
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
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
            display: inline-block;
            margin-right: 5px; /* Added spacing */
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
            width: 100%; /* Changed to 100% */
            padding: 10px; /* Increased padding */
            border-radius: 8px; /* More rounded corners */
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
            height: 42px; /* Match height of select with new padding */
        }
        .filter-section .filter-group button:hover {
            background-color: #16a085;
        }


        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
            justify-content: center;
            align-items: center;
            padding-top: 50px;
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
        .form-group input[type="email"],
        .form-group input[type="date"],
        .form-group input[type="tel"],
        .form-group textarea,
        .form-group input[type="file"],
        .form-group input[type="password"], /* Added password input */
        .form-group select {
            width: 100%; 
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            background-color: var(--background-color);
            color: var(--text-color);
        }
        .form-group input[type="file"] {
            padding: 8px 10px;
        }
        .form-group .photo-preview {
            max-width: 100px;
            height: auto;
            display: block;
            margin-top: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        /* Radio button styling */
        .form-group .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .form-group .radio-group input[type="radio"] {
            margin-right: 5px;
            width: auto; 
        }
        .form-group .radio-group label {
            display: inline-block;
            margin-bottom: 0;
            font-weight: normal;
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
        /* New CSS for User Info dropdown */
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
        /* Logout button at bottom of sidebar */
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
            <a href="index.php">
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


    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-user-graduate"></i> Manajemen Siswa</h1>
        <div class="user-info" id="userInfoDropdown">
            <span><?= $superadmin_name ?></span>
            <img src="<?= $superadmin_photo ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=SA';"
            >
            <i class="fas fa-caret-down"></i>
            <div class="dropdown-menu" id="userDropdownContent">
                <!-- <a href="profil_superadmin.php"><i class="fas fa-user-circle"></i> Profil</a> -->
                <a href="../../logout.php" id="logoutDropdownLink"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Siswa</h2>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="filter-section">
                <div class="filter-group">
                    <label for="filter_tahun_akademik">Tahun Akademik:</label>
                    <select id="filter_tahun_akademik" onchange="applyTahunAkademikFilter()">
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
            </div>

            <a href="#" class="add-link" onclick="openSiswaModal('tambah'); return false;">
                <i class="fas fa-plus-circle"></i> Tambah Siswa
            </a>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Foto</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>No. HP</th>
                            <th>Kelas</th>
                            <th>Tahun Akademik</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($siswa_list)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">Tidak ada data siswa untuk tahun akademik ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($siswa_list as $siswa): ?>
                                <tr data-nis="<?= htmlspecialchars($siswa['NIS']) ?>">
                                    <td><?php echo htmlspecialchars($siswa['NIS']); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars('../../uploads/siswa/' . ($siswa['photo'] ?? 'default.jpg')); ?>" alt="Foto Siswa" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;"
                                            loading="lazy"
                                            onerror="this.onerror=null;this.src='https://placehold.co/50x50/cccccc/333333?text=NO+IMG';"
                                        >
                                    </td>
                                    <td><?php echo htmlspecialchars($siswa['name']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['email']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['no_hp']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_tahun']); ?></td>
                                    <td>
                                        <a href="#" class="action-link edit" onclick="openSiswaModal('edit', 
                                                '<?php echo htmlspecialchars($siswa['NIS']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['name']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['email']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['gender']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['dob']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['no_hp']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['alamat']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['class_id']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['photo'] ?? ''); ?>'
                                            ); return false;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="action-link delete" onclick="openDeleteModal('<?php echo htmlspecialchars($siswa['NIS']); ?>'); return false;">
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

    <!-- Modal Tambah/Edit Siswa -->
    <div id="siswaModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeSiswaModal()">&times;</span>
            <h2 id="siswaModalTitle">Tambah Siswa</h2>
            <form id="siswaForm" method="POST" action="index.php" enctype="multipart/form-data">
                <input type="hidden" name="action" id="siswaAction">
                <input type="hidden" name="NISsiswa" id="siswaNISHidden">
                <input type="hidden" name="old_photosiswa" id="siswaOldPhotoHidden">
                <input type="hidden" name="tahun_akademik_id" value="<?= htmlspecialchars($selected_tahun_akademik_id); ?>">

                <div class="form-group">
                    <label for="NISsiswa">NIS:</label>
                    <input type="text" id="NISsiswa" name="NISsiswa" required>
                </div>
                <div class="form-group">
                    <label for="namasiswa">Nama:</label>
                    <input type="text" id="namasiswa" name="namasiswa" required>
                </div>
                <div class="form-group">
                    <label for="emailsiswa">Email:</label>
                    <input type="email" id="emailsiswa" name="emailsiswa" required>
                </div>
                <div class="form-group" id="passwordGroup">
                    <label for="passwordsiswa">Password:</label>
                    <input type="password" id="passwordsiswa" name="passwordsiswa" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Gender:</label>
                    <div class="radio-group">
                        <input type="radio" id="genderL" name="gender" value="laki-laki" required>
                        <label for="genderL">Laki-laki</label>
                        <input type="radio" id="genderP" name="gender" value="perempuan">
                        <label for="genderP">Perempuan</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="dobsiswa">Tanggal Lahir:</label>
                    <input type="date" id="dobsiswa" name="dobsiswa" required>
                </div>
                <div class="form-group">
                    <label for="nohpsiswa">No. HP:</label>
                    <input type="tel" id="nohpsiswa" name="nohpsiswa">
                </div>
                <div class="form-group">
                    <label for="alamatsiswa">Alamat:</label>
                    <textarea id="alamatsiswa" name="alamatsiswa" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="class_id_modal">Kelas:</label>
                    <select id="class_id_modal" name="class_id" required>
                        <option value="">Pilih Kelas</option>
                        <?php if (empty($kelas_form_options)): ?>
                            <option value="" disabled>Tidak ada kelas untuk tahun akademik ini.</option>
                        <?php else: ?>
                            <?php foreach ($kelas_form_options as $k): ?>
                                <option value="<?= htmlspecialchars($k['id']) ?>">
                                    <?= htmlspecialchars($k['nama_kelas']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="photosiswa">Foto Siswa (Opsional):</label>
                    <input type="file" id="photosiswa" name="photosiswa" accept="image/*">
                    <img id="photosiswa_preview" class="photo-preview" src="https://placehold.co/100x100/cccccc/333333?text=NO+IMG" alt="Preview Foto">
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitSiswaBtn">Simpan</button>
                    <button type="button" class="btn-secondary" onclick="closeSiswaModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Script JavaScript -->
    <script>
        const siswaModal = document.getElementById("siswaModal");
        const siswaModalTitle = document.getElementById("siswaModalTitle");
        const siswaForm = document.getElementById("siswaForm");
        const siswaActionInput = document.getElementById("siswaAction");
        const siswaNISHiddenInput = document.getElementById("siswaNISHidden");
        const siswaOldPhotoHiddenInput = document.getElementById("siswaOldPhotoHidden");
        const NISsiswaInput = document.getElementById("NISsiswa");
        const namasiswaInput = document.getElementById("namasiswa");
        const emailsiswaInput = document.getElementById("emailsiswa");
        const passwordGroup = document.getElementById("passwordGroup");
        const passwordsiswaInput = document.getElementById("passwordsiswa");
        const genderLInput = document.getElementById("genderL");
        const genderPInput = document.getElementById("genderP");
        const dobsiswaInput = document.getElementById("dobsiswa");
        const nohpsiswaInput = document.getElementById("nohpsiswa");
        const alamatsiswaTextarea = document.getElementById("alamatsiswa");
        const classIdModalSelect = document.getElementById("class_id_modal");
        const photosiswaInput = document.getElementById("photosiswa");
        const photosiswaPreview = document.getElementById("photosiswa_preview");
        const submitSiswaBtn = document.getElementById("submitSiswaBtn");
        const filterTahunAkademik = document.getElementById("filter_tahun_akademik");


        function openSiswaModal(action, NIS = '', name = '', email = '', gender = '', dob = '', no_hp = '', alamat = '', class_id = '', photo = '') {
            siswaForm.reset(); 
            siswaActionInput.value = action;
            if (action === 'tambah') {
                siswaModalTitle.textContent = "Tambah Siswa";
                submitSiswaBtn.textContent = "Simpan";
                NISsiswaInput.readOnly = false;
                NISsiswaInput.value = '';
                siswaNISHiddenInput.value = '';
                siswaOldPhotoHiddenInput.value = '';
                photosiswaPreview.src = "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";
                classIdModalSelect.value = '';
                
                passwordGroup.style.display = 'block';
                passwordsiswaInput.required = true;
            } else if (action === 'edit') {
                siswaModalTitle.textContent = "Edit Siswa";
                submitSiswaBtn.textContent = "Update";
                NISsiswaInput.readOnly = true;
                
                NISsiswaInput.value = NIS;
                siswaNISHiddenInput.value = NIS;
                namasiswaInput.value = name;
                emailsiswaInput.value = email;
                
                if (gender && gender.toLowerCase() === 'laki-laki') { 
                    genderLInput.checked = true;
                } else if (gender && gender.toLowerCase() === 'perempuan') { 
                    genderPInput.checked = true;
                }
                
                dobsiswaInput.value = dob;
                nohpsiswaInput.value = no_hp;
                alamatsiswaTextarea.value = alamat;
                classIdModalSelect.value = class_id;
                siswaOldPhotoHiddenInput.value = photo;
                photosiswaPreview.src = photo ? `../../uploads/siswa/${photo}` : "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";

                passwordGroup.style.display = 'none';
                passwordsiswaInput.required = false;
                passwordsiswaInput.value = '';
            }
            siswaModal.style.display = "flex";
        }

        function closeSiswaModal() {
            siswaModal.style.display = "none";
            const successAlert = document.querySelector('.alert-success');
            const errorAlert = document.querySelector('.alert-error');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }

        window.onclick = function (event) {
            if (event.target == siswaModal) {
                closeSiswaModal();
            }
        };

        photosiswaInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photosiswaPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                photosiswaPreview.src = "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";
            }
        });


        siswaForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const formData = new FormData(siswaForm);
            
            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Selalu coba baca sebagai teks dulu
                return response.text().then(text => {
                    // Cek apakah output adalah JSON yang valid
                    try {
                        const json = JSON.parse(text);
                        return json;
                    } catch (error) {
                        console.error("Server responded with non-JSON output:", text); // Log the full raw text to console
                        // Tampilkan sebagian kecil dari output mentah PHP di SweetAlert
                        throw new Error("Respons server bukan JSON. Output PHP Mentah (awal): " + text.substring(0, 300) + "...");
                    }
                });
            })
            .then(result => {
                if (result.status === "success") {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: result.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        const currentTahunAkademikId = filterTahunAkademik.value;
                        window.location.href = `index.php?success=${encodeURIComponent(result.message)}&tahun_akademik_id=${currentTahunAkademikId}`;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal!',
                        text: result.message, 
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error("Fetch error:", error); // Log error lebih detail ke console
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan saat memproses data: ' + error.message,
                    confirmButtonText: 'OK'
                });
            });
        });

        function openDeleteModal(NIS) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: 'Menghapus siswa ini juga akan menghapus semua data absensi yang terkait!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#3498db',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const currentTahunAkademikId = filterTahunAkademik.value;
                    window.location.href = `index.php?action=hapus_siswa&NIS=${NIS}&tahun_akademik_id=${currentTahunAkademikId}`;
                }
            });
        }

        function applyTahunAkademikFilter() {
            const selectedTahunAkademik = filterTahunAkademik.value;
            window.location.href = `index.php?tahun_akademik_id=${selectedTahunAkademik}`;
        }

        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");

        function toggleSidebar() {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
        }

        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");
        const logoutDropdownLink = document.getElementById('logoutDropdownLink');
        const logoutButtonSidebar = document.getElementById('logoutButtonSidebar');

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function() {
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.onclick = function(event) {
                if (event.target == siswaModal) { 
                    closeSiswaModal();
                }
                if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                    if (userDropdownContent.style.display === 'block') {
                        userDropdownContent.style.display = 'none';
                    }
                }
            }
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
                    window.location.href = "../../logout.php";
                }
            });
        }
        
        if (logoutButtonSidebar) {
            logoutButtonSidebar.addEventListener('click', function(e) {
                e.preventDefault();
                showLogoutConfirmation();
            });
        }
        if (logoutDropdownLink) {
            logoutDropdownLink.addEventListener('click', function(e) {
                e.preventDefault();
                showLogoutConfirmation();
            });
        }

        window.addEventListener('DOMContentLoaded', (event) => {
            const currentPathname = window.location.pathname; 
            const pathSegments = currentPathname.split('/');
            const superadminIndex = pathSegments.indexOf('superadmin');
            let relativePathFromSuperadmin = '';

            if (superadminIndex !== -1 && pathSegments.length > superadminIndex) {
                relativePathFromSuperadmin = pathSegments.slice(superadminIndex + 1).join('/');
            } else {
                relativePathFromSuperadmin = currentPathname.split('/').pop();
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
