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
    <link rel="stylesheet" href="../../assets/adminpage.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../../uploads/icon/logo.png" alt="Logo SuperAdmin" class="logo-icon">
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
            <a href="../jadwal/index.php">
                <div class="hovertext" data-hover="Jadwal"><i class="fas fa-calendar-alt"></div></i><span>Jadwal</span></a>
            <a href="#" class="active">
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


    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-calendar-check"></i> Manajemen Tahun Akademik</h1>
        <div class="user-info" id="userInfoDropdown">
            <span><?= $superadmin_name ?></span>
            <div class="dropdown-menu" id="userDropdownContent">
                <!-- <a href="profil_superadmin.php"><i class="fas fa-user-circle"></i> Profil</a> -->
                <a onclick="showLogoutConfirm(event)" id="logoutDropdownLink"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
