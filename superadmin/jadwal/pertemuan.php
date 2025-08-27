<?php
    session_start();
    if (!isset($_SESSION['superadmin'])) {
        header("Location: ../../login.php");
        exit;
    }

    require '../../koneksi.php';

    $id_jadwal = $_GET['id_jadwal'] ?? null;
    $pertemuan_to_edit = null;

    if (!$id_jadwal) {
        header("Location: index.php");
        exit;
    }

    $success = '';
    $error = '';

    // Handle Form Submission (Tambah & Edit)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_pertemuan = $_POST['id_pertemuan'] ?? null;
        $tanggal = $_POST['tanggal'] ?? '';
        $topik = $_POST['topik'] ?? '';
        $id_jadwal_form = $_POST['id_jadwal'] ?? '';

        if ($tanggal && $topik) {
            try {
                if ($id_pertemuan) {
                    // Update existing meeting
                    $stmt = $pdo->prepare("UPDATE pertemuan SET tanggal = ?, topik = ? WHERE id = ?");
                    $stmt->execute([$tanggal, $topik, $id_pertemuan]);
                    $success = "Pertemuan berhasil diupdate!";
                } else {
                    // Insert new meeting
                    $stmt = $pdo->prepare("INSERT INTO pertemuan (id_jadwal, tanggal, topik) VALUES (?, ?, ?)");
                    $stmt->execute([$id_jadwal_form, $tanggal, $topik]);
                    $success = "Pertemuan berhasil ditambahkan!";
                }

                header("Location: pertemuan.php?id_jadwal=" . urlencode($id_jadwal_form) . "&success=" . urlencode($success));
                exit;

            } catch (PDOException $e) {
                $error = "Gagal memproses pertemuan: " . $e->getMessage();
            }
        } else {
            $error = "Semua field wajib diisi!";
        }
    }

    // Handle Edit action (if ID is present in URL)
    if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt_edit = $pdo->prepare("SELECT * FROM pertemuan WHERE id = ?");
        $stmt_edit->execute([$id]);
        $pertemuan_to_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
    }
    
    // Check for success/error messages from redirect
    if (isset($_GET['success'])) {
        $success = htmlspecialchars($_GET['success']);
    }
    if (isset($_GET['error'])) {
        $error = htmlspecialchars($_GET['error']);
    }

    // Ambil data jadwal untuk judul
    $stmt_jadwal = $pdo->prepare("
        SELECT 
            jadwal.hari, jadwal.jam_mulai, jadwal.jam_selesai, 
            guru.name AS nama_guru, 
            mapel.nama_mapel, 
            class.nama_kelas
        FROM jadwal
        JOIN guru ON jadwal.teacher_id = guru.id
        JOIN mapel ON jadwal.id_mapel = mapel.id
        JOIN class ON jadwal.class_id = class.id
        WHERE jadwal.id = ?
    ");
    $stmt_jadwal->execute([$id_jadwal]);
    $jadwal_info = $stmt_jadwal->fetch(PDO::FETCH_ASSOC);

    // Ambil data pertemuan
    $stmt_pertemuan = $pdo->prepare("SELECT * FROM pertemuan WHERE id_jadwal = ? ORDER BY tanggal DESC");
    $stmt_pertemuan->execute([$id_jadwal]);
    $pertemuan = $stmt_pertemuan->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Pertemuan Kelas</title>
    <style>
        /* Gaya CSS yang sama dengan index.php */
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
        
        .action-link.rekap {
            background-color: #2ecc71;
            color: white;
        }
        .action-link.rekap:hover {
            background-color: #27ae60;
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

        .info-header {
            background: #f0f2f5;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-color);
        }

        .info-header p {
            margin: 5px 0;
            font-size: 16px;
            color: var(--text-color);
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

        /* Modal Konfirmasi */
        .modal-confirm {
            display: none;
            position: fixed;
            z-index: 1002; /* Lebih tinggi dari modal formulir */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            padding-top: 20%;
        }
        .modal-confirm-content {
            background-color: #fefefe;
            margin: auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .modal-confirm-content h3 {
            margin-bottom: 20px;
            font-size: 20px;
        }
        .modal-confirm-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .modal-confirm-buttons button {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-confirm-delete {
            background-color: #e74c3c;
            color: white;
            border: none;
            transition: background-color 0.3s;
        }
        .btn-confirm-delete:hover {
            background-color: #c0392b;
        }
        .btn-cancel-delete {
            background-color: #ccc;
            color: #333;
            border: 1px solid #999;
            transition: background-color 0.3s;
        }
        .btn-cancel-delete:hover {
            background-color: #bbb;
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
            <a href="../guru/index.php">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Guru</span>
            </a>
            <a href="../siswa/index.php">
                <i class="fas fa-user-graduate"></i>
                <span>Siswa</span>
            </a>
            <a href="index.php" class="active">
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
        <h1>Pertemuan</h1>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Pertemuan</h2>
            <?php if ($jadwal_info): ?>
                <div class="info-header">
                    <p><strong>Kelas:</strong> <?= htmlspecialchars($jadwal_info['nama_kelas']) ?></p>
                    <p><strong>Mata Pelajaran:</strong> <?= htmlspecialchars($jadwal_info['nama_mapel']) ?></p>
                    <p><strong>Guru:</strong> <?= htmlspecialchars($jadwal_info['nama_guru']) ?></p>
                    <p><strong>Jadwal:</strong> <?= htmlspecialchars($jadwal_info['hari']) ?>, <?= htmlspecialchars($jadwal_info['jam_mulai']) ?> - <?= htmlspecialchars($jadwal_info['jam_selesai']) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <a href="#" onclick="openModal()" class="add-link">
                <i class="fas fa-plus"></i> Tambah Pertemuan
            </a>

            <div class="table-container">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Topik</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pertemuan as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['tanggal']) ?></td>
                                <td><?= htmlspecialchars($row['topik']) ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="#" onclick="openEditModal(<?= htmlspecialchars(json_encode($row)) ?>)" class="action-link edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" onclick="openDeleteModal(<?= $row['id'] ?>)" class="action-link delete" title="Hapus">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                        <a href="../absensi/absensi.php?id_pertemuan=<?= $row['id'] ?>" class="action-link rekap" title="Rekap Absensi">
                                            <i class="fas fa-clipboard-list"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Jadwal
            </a>
        </div>
    </div>

    <div id="pertemuanModal" class="modal">
      <div class="modal-content">
          <span class="close-btn" onclick="closeModal()">&times;</span>
          <h2 id="modalTitle">Tambah Pertemuan</h2>

          <form method="POST" autocomplete="off" id="pertemuanForm">
              <input type="hidden" name="id_pertemuan" id="pertemuan_id">
              <input type="hidden" name="id_jadwal" value="<?= htmlspecialchars($id_jadwal) ?>">

              <label for="tanggal">Tanggal:</label>
              <input type="date" name="tanggal" id="tanggal" required>

              <label for="topik">Topik:</label>
              <textarea name="topik" id="topik" required></textarea>

              <button type="submit">Simpan Pertemuan</button>
          </form>
      </div>
   </div>

   <div id="deleteModal" class="modal-confirm">
      <div class="modal-confirm-content">
          <h3>Yakin ingin menghapus pertemuan ini?</h3>
          <p>Aksi ini tidak bisa dibatalkan.</p>
          <div class="modal-confirm-buttons">
              <button id="confirmDeleteBtn" class="btn-confirm-delete">Ya, Hapus</button>
              <button onclick="closeDeleteModal()" class="btn-cancel-delete">Batal</button>
          </div>
      </div>
   </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");
        const modalPertemuan = document.getElementById("pertemuanModal");
        const modalPertemuanTitle = document.getElementById("modalTitle");
        const formPertemuan = document.getElementById("pertemuanForm");
        const deleteModal = document.getElementById("deleteModal");
        const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
        let pertemuanToDeleteId;

        function toggleSidebar() {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
        }

            function openModal() {
            modalPertemuan.style.display = "block";
            modalPertemuanTitle.innerText = "Tambah Pertemuan";
            formPertemuan.reset();
            document.getElementById("pertemuan_id").value = '';
        }

        function openEditModal(pertemuan) {
            modalPertemuan.style.display = "block";
            modalPertemuanTitle.innerText = "Edit Pertemuan";
            
            document.getElementById("pertemuan_id").value = pertemuan.id;
            document.getElementById("tanggal").value = pertemuan.tanggal;
            document.getElementById("topik").value = pertemuan.topik;
        }

        function closeModal() {
            modalPertemuan.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modalPertemuan) {
                closeModal();
            }
        }

        function openDeleteModal(id) {
            pertemuanToDeleteId = id;
            deleteModal.style.display = "block";
        }

        function closeDeleteModal() {
            deleteModal.style.display = "none";
        }

        confirmDeleteBtn.onclick = function() {
            window.location.href = `hapus_pertemuan.php?id=${pertemuanToDeleteId}&id_jadwal=<?= $id_jadwal ?>`;
        }

        // Tambahkan ini untuk menutup modal saat mengklik di luar area modal
        window.onclick = function(event) {
            if (event.target == modalPertemuan) {
                closeModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
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
    </script>
</body>

</html>