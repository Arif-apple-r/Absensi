<?php
session_start();
// Pastikan hanya superadmin yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../login.php"); // Sesuaikan path ke halaman login Anda
    exit;
}

// Ambil data superadmin dari sesi
$superadmin_name = htmlspecialchars($_SESSION['superadmin_name'] ?? 'SuperAdmin');
// Karena tidak ada foto superadmin, kita langsung pakai placeholder
$superadmin_photo = 'https://placehold.co/40x40/cccccc/333333?text=SA';

// Sertakan file koneksi database Anda
require '../../koneksi.php'; // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

$message = '';
$alert_type = '';

// --- Handle Form Submission (Tambah Admin) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_admin'])) {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $email && $password) {
        // Hash password sebelum disimpan ke database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO admin (username, email, pass) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashed_password]);
            $message = "Admin berhasil ditambahkan!";
            $alert_type = 'alert-success';
            // Redirect untuk menghindari resubmission form
            header("Location: index.php?success=" . urlencode($message));
            exit;
        } catch (PDOException $e) {
            $error_message = "Gagal menambahkan admin: " . $e->getMessage();
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error_message = "Gagal menambahkan admin: Username atau Email sudah terdaftar.";
            }
            $message = $error_message;
            $alert_type = 'alert-error';
        }
    } else {
        $message = "Mohon lengkapi semua field untuk menambah admin.";
        $alert_type = 'alert-error';
    }
}

// --- Handle Delete Admin ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id_admin_to_delete = $_GET['id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM admin WHERE id = ?");
        $stmt->execute([$id_admin_to_delete]);
        $message = "Admin berhasil dihapus!";
        $alert_type = 'alert-success';
        header("Location: index.php?success=" . urlencode($message));
        exit;
    } catch (PDOException $e) {
        $message = "Gagal menghapus admin: " . $e->getMessage();
        $alert_type = 'alert-error';
    }
}

// Ambil data admin dari database
// MENGHAPUS 'created_at' dari SELECT karena kolom ini tidak ada di tabel 'admin' Anda
$stmt = $pdo->prepare("SELECT id, username, email FROM admin ORDER BY username ASC");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Data Admin | SuperAdmin</title>
    <!-- Font Awesome untuk ikon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/adminpage.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            <a href="#" class="active">
                <div class="hovertext" data-hover="Admin"><i class="fas fa-users-cog"></div></i><span>Admin</span></a>
            <a href="../guru/index.php">
                <div class="hovertext" data-hover="Guru"><i class="fas fa-chalkboard-teacher"></div></i><span>Guru</span></a>
            <a href="../siswa/index.php">
                <div class="hovertext" data-hover="Siswa"><i class="fas fa-user-graduate"></div></i><span>Siswa</span></a>
            <a href="../jadwal/index.php">
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


    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-user-shield"></i> Data Admin</h1>
        <div class="user-info" id="userInfoDropdown">
            <span><?= $superadmin_name ?></span>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Administrator</h2>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <a href="#" class="add-link" onclick="openModal(); return false;">
                <i class="fas fa-plus-circle"></i> Tambah Admin
            </a>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['id']); ?></td>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td>
                                    <!-- Tombol Hapus -->
                                    <a href="#" class="action-link delete" onclick="openDeleteModal(<?php echo htmlspecialchars($admin['id']); ?>); return false;">
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

    <!-- Modal Tambah/Edit Admin -->
    <div id="adminModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Tambah Admin</h2>
            <form id="adminForm" method="POST" action="index.php">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-actions">
                    <button type="submit" name="tambah_admin" class="btn-primary">Simpan</button>
                    <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Script JavaScript untuk sidebar dan dropdown -->
    <script>
        // Variabel untuk modal
        const adminModal = document.getElementById("adminModal");
        const modalTitle = document.getElementById("modalTitle");
        const adminForm = document.getElementById("adminForm");

        // Fungsi untuk membuka modal
        function openModal() {
            adminForm.reset(); // Reset form setiap kali modal dibuka
            modalTitle.textContent = "Tambah Admin";
            adminForm.querySelector('button[name="tambah_admin"]').textContent = "Simpan";
            adminModal.style.display = "flex"; // Gunakan flex untuk centering
        }

        // Fungsi untuk menutup modal
        function closeModal() {
            adminModal.style.display = "none";
        }

        // Fungsi untuk konfirmasi hapus dengan SweetAlert
        function openDeleteModal(id) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: 'Data admin ini akan dihapus secara permanen!',
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


        // Logika Dropdown User Info
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");
        const logoutDropdownLink = document.getElementById('logoutDropdownLink'); // Ambil elemen ini

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function() {
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.onclick = function(event) {
                if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                    if (userDropdownContent.style.display === 'block') {
                        userDropdownContent.style.display = 'none';
                    }
                }
            }
        }

        // SweetAlert for Logout Confirmation (untuk tombol di sidebar)
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
    </script>
</body>

</html>