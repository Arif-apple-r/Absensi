<?php
    session_start();
    if (!isset($_SESSION['superadmin'])) {
        header("Location: ../../login.php");
        exit;
    }

    require '../../koneksi.php';

    $id_pertemuan = $_GET['id_pertemuan'] ?? null;
    $success = '';
    $error = '';

    if (!$id_pertemuan) {
        header("Location: ../jadwal/index.php");
        exit;
    }

    // Ambil data pertemuan dan jadwal
    $stmt_pertemuan = $pdo->prepare("
        SELECT 
            pertemuan.*,
            jadwal.class_id,
            jadwal.id_mapel,
            mapel.nama_mapel,
            class.nama_kelas
        FROM pertemuan
        JOIN jadwal ON pertemuan.id_jadwal = jadwal.id
        JOIN mapel ON jadwal.id_mapel = mapel.id
        JOIN class ON jadwal.class_id = class.id
        WHERE pertemuan.id = ?
    ");
    $stmt_pertemuan->execute([$id_pertemuan]);
    $pertemuan_info = $stmt_pertemuan->fetch(PDO::FETCH_ASSOC);

    if (!$pertemuan_info) {
        die("Data pertemuan tidak ditemukan.");
    }
    
    $id_jadwal = $pertemuan_info['id_jadwal'];
    $class_id = $pertemuan_info['class_id'];
    
    // Ambil daftar siswa dari kelas yang terkait
    $stmt_siswa = $pdo->prepare("SELECT id, name FROM siswa WHERE class_id = ? ORDER BY name ASC");
    $stmt_siswa->execute([$class_id]);
    $siswa_list = $stmt_siswa->fetchAll(PDO::FETCH_ASSOC);

    // Ambil data absensi yang sudah ada, termasuk keterangan
    $stmt_absensi = $pdo->prepare("SELECT id_siswa, status, keterangan FROM absensi WHERE id_pertemuan = ?");
    $stmt_absensi->execute([$id_pertemuan]);
    
    $absensi_data = [];
    while ($row = $stmt_absensi->fetch(PDO::FETCH_ASSOC)) {
        $absensi_data[$row['id_siswa']] = ['status' => $row['status'], 'keterangan' => $row['keterangan']];
    }

    // Handle Form Submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $statuses = $_POST['status'] ?? [];
        $keterangans = $_POST['keterangan'] ?? [];

        try {
            $pdo->beginTransaction();
            foreach ($siswa_list as $siswa) {
                $siswa_id = $siswa['id'];
                $status = $statuses[$siswa_id] ?? 'Alpha';
                $keterangan = $keterangans[$siswa_id] ?? null;

                if (isset($absensi_data[$siswa_id])) {
                    // Update existing record
                    $stmt = $pdo->prepare("UPDATE absensi SET status = ?, keterangan = ? WHERE id_pertemuan = ? AND id_siswa = ?");
                    $stmt->execute([$status, $keterangan, $id_pertemuan, $siswa_id]);
                } else {
                    // Insert new record
                    $stmt = $pdo->prepare("INSERT INTO absensi (id_pertemuan, id_siswa, status, keterangan) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$id_pertemuan, $siswa_id, $status, $keterangan]);
                }
            }
            $pdo->commit();
            $success = "Absensi berhasil disimpan!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Gagal menyimpan absensi: " . $e->getMessage();
        }

        header("Location: absensi.php?id_pertemuan=" . urlencode($id_pertemuan) . "&success=" . urlencode($success) . "&error=" . urlencode($error));
        exit;
    }

    // Check for success/error messages from redirect
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>Rekap Absensi</title>
    <style>
        /* CSS yang sama dengan index.php dan pertemuan.php */
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
            background-color: #2ecc71;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
        }
        .add-link:hover {
            background-color: #27ae60;
            transform: translateY(-2px);
        }

        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .absensi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .absensi-table thead {
            background-color: var(--secondary-color);
            color: white;
        }
        .absensi-table th,
        .absensi-table td {
            padding: 16px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .absensi-table tbody tr:last-child td {
            border-bottom: none;
        }
        .absensi-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .absensi-table tbody tr:hover {
            background-color: #f2f2f2;
            transition: background-color 0.2s ease;
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
        
      /* Modal for Absensi Form */
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
          max-width: 600px;
          box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
          position: relative;
          overflow-y: auto; /* Tambahkan ini agar modal bisa di-scroll */
          max-height: 85vh; /* Batasi tinggi modal */
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
      .modal-header {
          border-bottom: 2px solid var(--border-color);
          padding-bottom: 15px;
          margin-bottom: 20px;
          display: flex;
          justify-content: space-between;
          align-items: center;
      }
      .modal-header h2 {
          margin: 0;
          font-size: 24px;
      }
      .modal form {
          display: flex;
          flex-direction: column;
      }
      .student-item {
        
          display: grid;
          grid-template-columns: 1fr 1fr; /* Layout menjadi 2 kolom */
          align-items: center;
          gap: 15px;
          padding: 10px 0;
          border-bottom: 1px solid #eee;
      }
      .student-item:last-child {
          border-bottom: none;
      }
      .student-item select {
        padding: 10px;
          width: 100%;
      }
      .student-item .keterangan-container {
          grid-column: 1 / -1; /* Ini membuat div keterangan mengambil seluruh lebar */
      }

      .student-item textarea {
          min-height: 70px;
          width: 100%;
          border-radius: 5px;
          padding: 8px;
          border: 1px solid #ccc;
          font-family: inherit;
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
          margin-top: 20px;
          width: 100%;
      }
      .modal button[type="submit"]:hover {
          background-color: #16a085;
      }
    </style>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">SuperadminCoy</div>
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
            <a href="../logout.php" onclick="showLogoutConfirm(event)">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Rekap Absensi</h1>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Absensi Pertemuan</h2>

            <?php if ($pertemuan_info): ?>
                <div class="info-header">
                    <p><strong>Kelas:</strong> <?= htmlspecialchars($pertemuan_info['nama_kelas']) ?></p>
                    <p><strong>Mata Pelajaran:</strong> <?= htmlspecialchars($pertemuan_info['nama_mapel']) ?></p>
                    <p><strong>Tanggal:</strong> <?= htmlspecialchars($pertemuan_info['tanggal']) ?></p>
                    <p><strong>Topik:</strong> <?= htmlspecialchars($pertemuan_info['topik']) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= $error ?></div>
            <?php endif; ?>

            <a href="#" onclick="openModal()" class="add-link">
                <i class="fas fa-clipboard-list"></i> Isi / Edit Absensi
            </a>

            <div class="table-container">
                <table class="absensi-table">
                    <thead>
                        <tr>
                            <th>Nama Siswa</th>
                            <th>Status Kehadiran</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($absensi_data)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">Belum ada data absensi untuk pertemuan ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($siswa_list as $siswa): ?>
                                <tr>
                                    <td><?= htmlspecialchars($siswa['name']) ?></td>
                                    <td><?= htmlspecialchars($absensi_data[$siswa['id']]['status'] ?? 'Belum Diisi') ?></td>
                                    <td><?= htmlspecialchars($absensi_data[$siswa['id']]['keterangan'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="../jadwal/pertemuan.php?id_jadwal=<?= htmlspecialchars($id_jadwal) ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Pertemuan
            </a>
        </div>
    </div>

    <div id="absensiModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Isi / Edit Absensi</h2>
                <span class="close-btn" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" autocomplete="off" id="absensiForm">
                <input type="hidden" name="id_pertemuan" value="<?= htmlspecialchars($id_pertemuan) ?>">
                <input type="hidden" name="id_jadwal" value="<?= htmlspecialchars($id_jadwal) ?>">
                
                <?php foreach ($siswa_list as $siswa): ?>
                    <div class="student-item">
                        <strong><?= htmlspecialchars($siswa['name']) ?></strong>
                        <select name="status[<?= $siswa['id'] ?>]" onchange="toggleKeterangan(this)">
                            <?php $currentStatus = $absensi_data[$siswa['id']]['status'] ?? ''; ?>
                            <option value="Hadir" <?= $currentStatus == 'Hadir' ? 'selected' : '' ?>>Hadir</option>
                            <option value="Izin" <?= $currentStatus == 'Izin' ? 'selected' : '' ?>>Izin</option>
                            <option value="Sakit" <?= $currentStatus == 'Sakit' ? 'selected' : '' ?>>Sakit</option>
                            <option value="Alpha" <?= $currentStatus == 'Alpha' ? 'selected' : '' ?>>Alpha</option>
                        </select>
                        <div class="keterangan-container" style="display: <?= $currentStatus == 'Izin' ? 'block' : 'none' ?>;">
                            <textarea name="keterangan[<?= $siswa['id'] ?>]" placeholder="Keterangan (opsional)" <?= $currentStatus != 'Izin' ? 'disabled' : '' ?>><?= htmlspecialchars($absensi_data[$siswa['id']]['keterangan'] ?? '') ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <button type="submit">Simpan Absensi</button>
            </form>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");
        const absensiModal = document.getElementById("absensiModal");

        function toggleKeterangan(selectElement) {
            const keteranganContainer = selectElement.closest('.student-item').querySelector('.keterangan-container');
            const keteranganTextarea = keteranganContainer.querySelector('textarea');
            
            if (selectElement.value === 'Izin' || selectElement.value === 'Sakit') {
                keteranganContainer.style.display = 'block';
                keteranganTextarea.disabled = false;
            } else {
                keteranganContainer.style.display = 'none';
                keteranganTextarea.disabled = true;
                keteranganTextarea.value = ''; // Opsional: bersihkan nilai saat disembunyikan
            }
        }

        function openModal() {
            absensiModal.style.display = "block";
            
            // Panggil fungsi toggleKeterangan untuk setiap select saat modal dibuka
            const selectElements = absensiModal.querySelectorAll('select[name^="status"]');
            selectElements.forEach(select => {
                toggleKeterangan(select);
            });
        }

        function closeModal() {
            absensiModal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == absensiModal) {
                closeModal();
            }
        }

        function showLogoutConfirm(event) {
            event.preventDefault();
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