<?php
    session_start();
    if (!isset($_SESSION['superadmin'])) {
        header("Location: ../../login.php");
        exit;
    }

    require '../../koneksi.php';

    $id_pertemuan = $_GET['id_pertemuan'] ?? null;
    $tahun_akademik_id_kembali = $_GET['tahun_akademik_id'] ?? '';
    $kelas_filter = $_GET['kelas_filter'] ?? 'all';
    $mapel_filter = $_GET['mapel_filter'] ?? 'all';
    $hari_filter = $_GET['hari_filter'] ?? 'all';
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

        // Bangun query string filter agar rapi
        $filter_params = http_build_query([
            'tahun_akademik_id' => $tahun_akademik_id_kembali,
            'kelas_filter' => $kelas_filter,
            'mapel_filter' => $mapel_filter,
            'hari_filter' => $hari_filter
        ]);

        header("Location: absensi.php?id_pertemuan=" . urlencode($id_pertemuan) . "&success=" . urlencode($success) . "&error=" . urlencode($error) . "&" . $filter_params);
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
    <link rel="stylesheet" href="../../assets/adminpage.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>Rekap Absensi</title>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../../uploads/icon/logo.png" alt="Logo SuperAdminCoy" class="logo-icon">
            <span class="logo-text">SuperAdmin</span>
        </div>
        <nav>
            <a href="../dashboard_superadmin.php">
                <div class="hovertext" data-hover="dashboard"><i class="fas fa-tachometer-alt"></div></i><span>Dashboard</span></a>
            <a href="../admin/index.php">
                <div class="hovertext" data-hover="Admin"><i class="fas fa-users-cog"></div></i><span>Admin</span></a>
            <a href="../guru/index.php">
                <div class="hovertext" data-hover="Guru"><i class="fas fa-chalkboard-teacher"></div></i><span>Guru</span></a>
            <a href="../siswa/index.php">
                <div class="hovertext" data-hover="Siswa"><i class="fas fa-user-graduate"></div></i><span>Siswa</span></a>
            <a href="#" class="active">
                <div class="hovertext" data-hover="Jadwal"><i class="fas fa-calendar-alt"></div></i><span>Jadwal</span></a>
            <a href="../tahun_akademik/index.php">
                <div class="hovertext" data-hover="Tahun Akademik"><i class="fas fa-calendar"></div></i><span>Tahun Akademik</span></a>
            <a href="../kelas/index.php">
                <div class="hovertext" data-hover="Kelas"><i class="fas fa-school"></div></i><span>Kelas</span></a>
            <a href="../mapel/index.php">
                <div class="hovertext" data-hover="Mata Pelajaran"><i class="fas fa-book"></div></i><span>Mata Pelajaran</span></a>
        </nav>
        <div class="logout-button-container hovertext" data-hover="Logout">
            <a onclick="showLogoutConfirm(event)">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="header h-left" id="header">
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

            <div class="table-container-absensi">
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

            <a href="../jadwal/pertemuan.php?id_jadwal=<?= htmlspecialchars($id_jadwal) ?>&tahun_akademik_id=<?= $tahun_akademik_id_kembali ?>&kelas_filter=<?= urlencode($kelas_filter) ?>&mapel_filter=<?= urlencode($mapel_filter) ?>&hari_filter=<?= urlencode($hari_filter) ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Pertemuan
            </a>
        </div>
    </div>

    <div id="absensiModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Isi / Edit Absensi</h2>
                <span class="close-button" onclick="closeModal()">&times;</span>
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
                
                <div class="form-actions">
                    <button type="submit" class="btn-primary">Simpan Absensi</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");
        const absensiModal = document.getElementById("absensiModal");

        function toggleSidebar() {
            const isCollapsed = sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");

            // --- PERBAIKAN: Simpan status sidebar di Local Storage ---
            if (isCollapsed) {
                localStorage.setItem('sidebarState', 'collapsed');
                localStorage.setItem('mainContentState', 'shifted');
                localStorage.setItem('headerState', 'shifted');
            } else {
                localStorage.setItem('sidebarState', 'expanded');
                localStorage.setItem('mainContentState', 'expanded');
                localStorage.setItem('headerState', 'expanded');
            }
        }

        // --- penerapan sidebarnya iki state dari Local Storage ---
        const savedState = localStorage.getItem('sidebarState');
        if (savedState === 'collapsed') {
            // Pastikan variabel elemen DOM sudah terdefinisi/dapat diakses
            if (sidebar && mainContent && header) {
                sidebar.classList.add("no-transition");
                sidebar.classList.add("collapsed");
                mainContent.classList.add("no-transition");
                mainContent.classList.add("shifted");
                header.classList.add("no-transition");
                header.classList.add("shifted");
            }

            setTimeout(() => {
                sidebar.classList.remove("no-transition");
                mainContent.classList.remove("no-transition");
                header.classList.remove("no-transition");
            }, 50); // 50ms sudah cukup singkat dan aman

        }  

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

    </script>
</body>

</html>