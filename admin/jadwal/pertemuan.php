<?php
    session_start();
    if (!isset($_SESSION['admin_id'])) {
        header("Location: ../../login.php");
        exit;
    }

    require '../../koneksi.php';

    $id_jadwal = $_GET['id_jadwal'] ?? null;
    $tahun_akademik_id_kembali = $_GET['tahun_akademik_id'] ?? '';
    $kelas_filter = $_GET['kelas_filter'] ?? 'all';
    $mapel_filter = $_GET['mapel_filter'] ?? 'all';
    $hari_filter = $_GET['hari_filter'] ?? 'all';
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
    <link rel="stylesheet" href="../../assets/adminpage.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>Pertemuan Kelas</title>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../../uploads/icon/logo.png" alt="Logo AdminCoy" class="logo-icon">
            <span class="logo-text">AdminCoy</span>
        </div>
        <nav>
            <a href="../dashboard_admin.php">
                <div class="hovertext" data-hover="dashboard"><i class="fas fa-tachometer-alt"></div></i><span>Dashboard</span></a>
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
                                        <a href="../absensi/absensi.php?id_pertemuan=<?= $row['id'] ?>&tahun_akademik_id=<?= $tahun_akademik_id_kembali ?>&kelas_filter=<?= urlencode($kelas_filter) ?>&mapel_filter=<?= urlencode($mapel_filter) ?>&hari_filter=<?= urlencode($hari_filter) ?>" 
                                        class="action-link rekap" title="Rekap Absensi">
                                            <i class="fas fa-clipboard-list"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <a href="index.php?tahun_akademik_id=<?= $tahun_akademik_id_kembali ?>&kelas_filter=<?= urlencode($kelas_filter) ?>&mapel_filter=<?= urlencode($mapel_filter) ?>&hari_filter=<?= urlencode($hari_filter) ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Jadwal
            </a>
        </div>
    </div>

    <div id="pertemuanModal" class="modal">
      <div class="modal-content">
          <span class="close-btn" onclick="closeModal()">&times;</span>
          <h2 id="modalTitle">Tambah Pertemuan</h2>

          <form method="POST" autocomplete="off" id="pertemuanForm">
            <div class="form-group">
              <input type="hidden" name="id_pertemuan" id="pertemuan_id">
              <input type="hidden" name="id_jadwal" value="<?= htmlspecialchars($id_jadwal) ?>">
            </div>

            <div class="form-group">
              <label for="tanggal">Tanggal:</label>
              <input type="date" name="tanggal" id="tanggal" required>
            </div>

            <div class="form-group">
              <label for="topik">Topik:</label>
              <textarea name="topik" id="topik" required></textarea>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn-primary">Simpan Pertemuan</button>
            </div>
          </form>
      </div>
   </div>

   <div id="deleteModal" class="modal-confirm-delete">
      <div class="modal-confirm-delete-content">
          <h3>Yakin ingin menghapus pertemuan ini?</h3>
          <p>Aksi ini tidak bisa dibatalkan.</p>
          <div class="modal-confirm-delete-buttons">
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
