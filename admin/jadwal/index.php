<?php
    session_start();
    if (!isset($_SESSION['admin'])) {
        header("Location: ../../login.php");
        exit;
    }

    require '../../koneksi.php';

    $jadwal_to_edit = null;
    $success = '';
    $error = '';

    // Ambil data untuk dropdown
    $kelas = $pdo->query("SELECT * FROM class ORDER BY nama_kelas ASC")->fetchAll();
    $mapel = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC")->fetchAll();
    $guru = $pdo->query("SELECT * FROM guru ORDER BY name ASC")->fetchAll();

    // Handle Form Submission (Tambah & Edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF token validation
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }

        $id = $_POST['id'] ?? '';
        $class_id = $_POST['class_id'] ?? '';
        $mapel_id = $_POST['mapel_id'] ?? '';
        $hari = $_POST['hari'] ?? '';
        $jam_mulai = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?? '';
        $teacher_id = $_POST['teacher_id'] ?? '';

        if ($class_id && $mapel_id && $hari && $jam_mulai && $jam_selesai && $teacher_id) {
            try {
                // Get mapel name
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
                
                header("Location: index.php?success=" . urlencode($success));
                exit;

            } catch (PDOException $e) {
                $error = "Gagal memproses jadwal: " . $e->getMessage();
            }
        } else {
            $error = "Semua field wajib diisi!";
        }
    }

    // CSRF token generation
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Handle Edit action
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM jadwal WHERE id = ?");
        $stmt->execute([$id]);
        $jadwal_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Check for success/error messages from redirect
    if (isset($_GET['success'])) {
        $success = htmlspecialchars($_GET['success']);
    }
    if (isset($_GET['error'])) {
        $error = htmlspecialchars($_GET['error']);
    }

    // Fetch all schedule data
    $query = "
        SELECT 
            jadwal.*, 
            guru.name AS nama_guru, 
            class.nama_kelas,
            class.photo
        FROM jadwal
        JOIN guru ON jadwal.teacher_id = guru.id
        JOIN class ON jadwal.class_id = class.id
        JOIN mapel ON jadwal.id_mapel = mapel.id
    ";
    $stmt = $pdo->query($query);

    // Pastikan parameter 'id' ada di URL
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

            .sidebar.collapsed + .header,
            .sidebar.collapsed ~ .content {
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
        .modal select, .modal input[type="time"] {
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

        .filter-group select {
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
            <a href="../jadwal/index.php" class="active">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal</span>
            </a>
            <a href="../kelas/index.php">
                <i class="fas fa-school"></i>
                <span>Kelas</span>
            </a>
            <a href="../mapel/index.php">
                <i class="fas fa-book"></i>
                <span>Mata Pelajaran</span>
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
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Jadwal Kelas</h1>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Data Jadwal</h2>
            <div class="filter-container">
                <div class="filter-group">
                    <label for="filter-kelas">Kelas:</label>
                    <select id="filter-kelas">
                        <option value="all">Semua Kelas</option>
                        <?php foreach ($kelas as $k) : ?>
                            <option value="<?= htmlspecialchars($k['nama_kelas']) ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-mapel">Mata Pelajaran:</label>
                    <select id="filter-mapel">
                        <option value="all">Semua Mapel</option>
                        <?php foreach ($mapel as $m) : ?>
                            <option value="<?= htmlspecialchars($m['nama_mapel']) ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-hari">Hari:</label>
                    <select id="filter-hari">
                        <option value="all">Semua Hari</option>
                        <?php
                        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
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
                        <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) : ?>
                            <tr data-hari="<?= htmlspecialchars($row['hari']) ?>" data-kelas="<?= htmlspecialchars($row['nama_kelas']) ?>" data-mapel="<?= htmlspecialchars($row['mata_pelajaran']) ?>">
                                <td><?= htmlspecialchars($row['hari']) ?></td>
                                <td><?= htmlspecialchars($row['jam_mulai']) ?> - <?= htmlspecialchars($row['jam_selesai']) ?></td>
                                <td><?= htmlspecialchars($row['mata_pelajaran']) ?></td>
                                <td><?= htmlspecialchars($row['nama_guru']) ?></td>
                                <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                                <td>
                                    <?php if (!empty($row['photo'])) : ?>
                                        <img src="../../uploads/kelas/<?= htmlspecialchars($row['photo']) ?>" alt="Foto Kelas">
                                    <?php else : ?>
                                        Tidak ada foto
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
                        <?php endwhile; ?>
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

                <label for="class_id">Kelas:</label>
                <select name="class_id" id="class_id" required>
                    <option value="">--Pilih--</option>
                    <?php foreach ($kelas as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="mapel_id">Mapel:</label>
                <select name="mapel_id" id="mapel_id" required>
                    <option value="">--Pilih--</option>
                    <?php foreach ($mapel as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="hari">Hari:</label>
                <select name="hari" id="hari" required>
                    <option value="">--Pilih Hari--</option>
                    <?php
                    $days = ['Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
                    foreach ($days as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="jam_mulai">Jam Mulai:</label>
                <input type="time" name="jam_mulai" id="jam_mulai" required>

                <label for="jam_selesai">Jam Selesai:</label>
                <input type="time" name="jam_selesai" id="jam_selesai" required>

                <label for="teacher_id">Guru:</label>
                <select name="teacher_id" id="teacher_id" required>
                    <option value="">--Pilih--</option>
                    <?php foreach ($guru as $g): ?>
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

        function openModal() {
            modal.style.display = "block";
            modalTitle.innerText = "Tambah Jadwal";
            form.reset();
            document.getElementById("jadwal_id").value = '';
        }

        /*         * Open modal for editing existing schedule
         * jadwal is an object with properties matching the form fields
         */

        function openEditModal(jadwal) {
            modal.style.display = "block";
            modalTitle.innerText = "Edit Jadwal";

            document.getElementById("jadwal_id").value = jadwal.id;
            document.getElementById("class_id").value = jadwal.class_id;
            document.getElementById("mapel_id").value = jadwal.id_mapel;
            document.getElementById("hari").value = jadwal.hari;
            document.getElementById("jam_mulai").value = jadwal.jam_mulai;
            document.getElementById("jam_selesai").value = jadwal.jam_selesai;
            document.getElementById("teacher_id").value = jadwal.teacher_id;
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

        /*         * Custom Alert Modal Logic         */
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
                window.location.href = `hapus.php?id=${id_jadwal}`;
            }
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

        // ===========================================
        // LOGIKA BARU UNTUK FILTER INTERAKTIF
        // ===========================================
        document.addEventListener('DOMContentLoaded', function() {
            const filterKelas = document.getElementById('filter-kelas');
            const filterMapel = document.getElementById('filter-mapel');
            const filterHari = document.getElementById('filter-hari');
            const resetBtn = document.getElementById('reset-filter-btn');
            const tableRows = document.querySelectorAll('.schedule-table tbody tr');

            function applyFilters() {
                const selectedKelas = filterKelas.value;
                const selectedMapel = filterMapel.value;
                const selectedHari = filterHari.value;

                tableRows.forEach(row => {
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
                applyFilters();
            }

            

            // Event listeners untuk setiap dropdown filter
            filterKelas.addEventListener('change', applyFilters);
            filterMapel.addEventListener('change', applyFilters);
            filterHari.addEventListener('change', applyFilters);

            // Event listener untuk tombol reset
            resetBtn.addEventListener('click', resetFilters);
        });

        
    </script>
</body>

</html>