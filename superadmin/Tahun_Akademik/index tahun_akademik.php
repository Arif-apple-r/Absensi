<?php
session_start();
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../login.php");
    exit;
}

require '../../koneksi.php';

$superadmin_name = htmlspecialchars($_SESSION['superadmin_name'] ?? 'SuperAdmin');
$superadmin_photo = 'https://placehold.co/40x40/cccccc/333333?text=SA'; 

$message = '';
$alert_type = '';

// --- Handle Form Submission (Tambah/Edit Tahun Akademik) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['tambah_tahun_akademik']) || isset($_POST['edit_tahun_akademik']))) {
    $id_tahun = $_POST['id_tahun'] ?? null;
    $nama_tahun = $_POST['nama_tahun'] ?? '';
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? '';
    $tanggal_selesai = $_POST['tanggal_selesai'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($nama_tahun && $tanggal_mulai && $tanggal_selesai) {
        try {
            // Jika ada yang diatur aktif, pastikan semua yang lain non-aktif
            if ($is_active == 1) {
                $pdo->exec("UPDATE tahun_akademik SET is_active = 0");
            }

            if ($id_tahun) {
                // Edit existing
                $stmt = $pdo->prepare("UPDATE tahun_akademik SET nama_tahun = ?, tanggal_mulai = ?, tanggal_selesai = ?, is_active = ? WHERE id = ?");
                $stmt->execute([$nama_tahun, $tanggal_mulai, $tanggal_selesai, $is_active, $id_tahun]);
                $message = "Tahun Akademik berhasil diupdate!";
                $alert_type = 'alert-success';
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO tahun_akademik (nama_tahun, tanggal_mulai, tanggal_selesai, is_active) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama_tahun, $tanggal_mulai, $tanggal_selesai, $is_active]);
                $message = "Tahun Akademik berhasil ditambahkan!";
                $alert_type = 'alert-success';
            }
            header("Location: index.php?success=" . urlencode($message));
            exit;
        } catch (PDOException $e) {
            $error_message = "Gagal memproses Tahun Akademik: " . $e->getMessage();
            if (strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'nama_tahun') !== false) {
                $error_message = "Gagal: Nama Tahun Akademik sudah ada.";
            }
            $message = $error_message;
            $alert_type = 'alert-error';
        }
    } else {
        $message = "Mohon lengkapi semua field untuk Tahun Akademik.";
        $alert_type = 'alert-error';
    }
}

// --- Handle Delete Tahun Akademik ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_tahun_to_delete = $_GET['id'];

    try {
        // Cek apakah ada kelas yang terkait dengan tahun akademik ini
        $stmt_check_class = $pdo->prepare("SELECT COUNT(*) FROM class WHERE id_tahun_akademik = ?");
        $stmt_check_class->execute([$id_tahun_to_delete]);
        if ($stmt_check_class->fetchColumn() > 0) {
            $message = "Tidak dapat menghapus Tahun Akademik ini karena masih ada kelas yang terkait.";
            $alert_type = 'alert-error';
            header("Location: index.php?error=" . urlencode($message));
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM tahun_akademik WHERE id = ?");
        $stmt->execute([$id_tahun_to_delete]);
        $message = "Tahun Akademik berhasil dihapus!";
        $alert_type = 'alert-success';
        header("Location: index.php?success=" . urlencode($message));
        exit;
    } catch (PDOException $e) {
        $message = "Gagal menghapus Tahun Akademik: " . $e->getMessage();
        $alert_type = 'alert-error';
    }
}


// Ambil semua data Tahun Akademik
$stmt = $pdo->query("SELECT * FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manajemen Tahun Akademik | SuperAdmin</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            display: inline-block; /* Agar bisa diatur margin */
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
            margin-left: 5px;
        }
        .action-link.delete:hover {
            background-color: #c0392b;
        }
        .action-link.activate {
            background-color: #27ae60; /* Green for Activate */
            color: white;
        }
        .action-link.activate:hover {
            background-color: #229954;
        }
        .action-link.deactivate {
            background-color: #f39c12; /* Orange for Deactivate */
            color: white;
        }
        .action-link.deactivate:hover {
            background-color: #e08e0b;
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
        .form-group input[type="date"],
        .form-group select {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 16px;
            background-color: var(--background-color);
            color: var(--text-color);
        }
        .form-group input[type="checkbox"] {
            margin-right: 10px;
            width: 18px;
            height: 18px;
            vertical-align: middle;
        }
        .form-group .checkbox-label {
            display: inline-block;
            vertical-align: middle;
            font-weight: normal; /* Override bold from main label */
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

        /* Status Tahun Akademik */
        .status-active {
            color: #27ae60; /* Green */
            font-weight: 600;
        }
        .status-inactive {
            color: #e67e22; /* Orange */
            font-weight: 600;
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
            <a href="../dashboard_superadmin.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="../admin/index.php"><i class="fas fa-users-cog"></i><span>Admin</span></a>
            <a href="../guru/index.php"><i class="fas fa-chalkboard-teacher"></i><span>Guru</span></a>
            <a href="../siswa/index.php"><i class="fas fa-user-graduate"></i><span>Siswa</span></a>
            <a href="../jadwal/index.php"><i class="fas fa-calendar-alt"></i><span>Jadwal</span></a>
            <a href="#" class="active"><i class="fas fa-calendar"></i><span>Tahun Akademik</span></a>
            <a href="../kelas/index.php"><i class="fas fa-school"></i><span>Kelas</span></a>
            <a href="../mapel/index.php"><i class="fas fa-book"></i><span>Mata Pelajaran</span></a>
            <div class="logout-button-container">
                <a onclick="showLogoutConfirm(event)">
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
        <h1><i class="fas fa-calendar-check"></i> Manajemen Tahun Akademik</h1>
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
            <h2>Daftar Tahun Akademik</h2>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <a href="#" class="add-link" onclick="openModal('tambah'); return false;">
                <i class="fas fa-plus-circle"></i> Tambah Tahun Akademik
            </a>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama Tahun</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Aktif</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tahun_akademik_list as $tahun): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($tahun['id']); ?></td>
                                <td><?php echo htmlspecialchars($tahun['nama_tahun']); ?></td>
                                <td><?php echo htmlspecialchars($tahun['tanggal_mulai']); ?></td>
                                <td><?php echo htmlspecialchars($tahun['tanggal_selesai']); ?></td>
                                <td>
                                    <?php if ($tahun['is_active']): ?>
                                        <span class="status-active"><i class="fas fa-check-circle"></i> Aktif</span>
                                    <?php else: ?>
                                        <span class="status-inactive"><i class="fas fa-times-circle"></i> Tidak Aktif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="#" class="action-link edit" onclick="openModal('edit', 
                                            <?php echo htmlspecialchars($tahun['id']); ?>, 
                                            '<?php echo htmlspecialchars($tahun['nama_tahun']); ?>', 
                                            '<?php echo htmlspecialchars($tahun['tanggal_mulai']); ?>', 
                                            '<?php echo htmlspecialchars($tahun['tanggal_selesai']); ?>', 
                                            <?php echo htmlspecialchars($tahun['is_active']); ?>
                                        ); return false;">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="#" class="action-link delete" onclick="openDeleteModal(<?php echo htmlspecialchars($tahun['id']); ?>); return false;">
                                        <i class="fas fa-trash"></i> Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>

    <!-- Modal Tambah/Edit Tahun Akademik -->
    <div id="tahunAkademikModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Tambah Tahun Akademik</h2>
            <form id="tahunAkademikForm" method="POST" action="index.php">
                <input type="hidden" id="id_tahun" name="id_tahun">
                <div class="form-group">
                    <label for="nama_tahun">Nama Tahun (e.g., 2023/2024):</label>
                    <input type="text" id="nama_tahun" name="nama_tahun" required>
                </div>
                <div class="form-group">
                    <label for="tanggal_mulai">Tanggal Mulai:</label>
                    <input type="date" id="tanggal_mulai" name="tanggal_mulai" required>
                </div>
                <div class="form-group">
                    <label for="tanggal_selesai">Tanggal Selesai:</label>
                    <input type="date" id="tanggal_selesai" name="tanggal_selesai" required>
                </div>
                <div class="form-group">
                    <input type="checkbox" id="is_active" name="is_active">
                    <label for="is_active" class="checkbox-label">Set sebagai Tahun Akademik Aktif</label>
                </div>
                <div class="form-actions">
                    <button type="submit" name="tambah_tahun_akademik" class="btn-primary">Simpan</button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script JavaScript untuk sidebar, dropdown, dan modal -->
    <script>
        // Variabel untuk modal Tahun Akademik
        const tahunAkademikModal = document.getElementById("tahunAkademikModal");
        const modalTitle = document.getElementById("modalTitle");
        const tahunAkademikForm = document.getElementById("tahunAkademikForm");
        const id_tahun_input = document.getElementById("id_tahun");
        const nama_tahun_input = document.getElementById("nama_tahun");
        const tanggal_mulai_input = document.getElementById("tanggal_mulai");
        const tanggal_selesai_input = document.getElementById("tanggal_selesai");
        const is_active_checkbox = document.getElementById("is_active");
        const submitButton = tahunAkademikForm.querySelector('button[type="submit"]');


        // Fungsi untuk membuka modal
        function openModal(action, id = '', nama = '', mulai = '', selesai = '', aktif = 0) {
            tahunAkademikForm.reset(); // Reset form setiap kali modal dibuka

            if (action === 'tambah') {
                modalTitle.textContent = "Tambah Tahun Akademik";
                submitButton.name = "tambah_tahun_akademik";
                submitButton.textContent = "Simpan";
                id_tahun_input.value = '';
                is_active_checkbox.checked = false; // Pastikan non-aktif secara default
            } else if (action === 'edit') {
                modalTitle.textContent = "Edit Tahun Akademik";
                submitButton.name = "edit_tahun_akademik";
                submitButton.textContent = "Update";
                id_tahun_input.value = id;
                nama_tahun_input.value = nama;
                tanggal_mulai_input.value = mulai;
                tanggal_selesai_input.value = selesai;
                is_active_checkbox.checked = (aktif == 1);
            }
            tahunAkademikModal.style.display = "flex"; // Gunakan flex untuk centering
        }

        // Fungsi untuk menutup modal
        function closeModal() {
            tahunAkademikModal.style.display = "none";
        }

        // Fungsi untuk konfirmasi hapus dengan SweetAlert
        function openDeleteModal(id) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: 'Data Tahun Akademik ini akan dihapus secara permanen! Pastikan tidak ada kelas yang terhubung.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#3498db',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `index.php?action=hapus&id=${id}`;
                }
            });
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

        // Logika Dropdown User Info
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");
        const logoutDropdownLink = document.getElementById('logoutDropdownLink');

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function() {
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.onclick = function(event) {
                if (event.target == tahunAkademikModal) { // Tambahan: Tutup modal jika klik di luar
                    closeModal();
                }
                if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                    if (userDropdownContent.style.display === 'block') {
                        userDropdownContent.style.display = 'none';
                    }
                }
            }
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
    </script>
</body>
</html>
