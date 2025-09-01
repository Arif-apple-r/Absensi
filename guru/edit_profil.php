<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Pastikan hanya guru yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

require '../koneksi.php';

$guru_id = $_SESSION['guru_id'];
$guru_name = $_SESSION['guru_name'] ?? 'Guru';

// Inisialisasi variabel untuk pesan notifikasi
$success_message = '';
$error_message = '';

// --- FUNGSI UNTUK MEMPROSES SUBMIT FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Ambil data dari form
        $name = htmlspecialchars($_POST['name']);
        $nip = htmlspecialchars($_POST['nip']);
        $gender = htmlspecialchars($_POST['gender']);
        $email = htmlspecialchars($_POST['email']);
        $dob = htmlspecialchars($_POST['dob']);
        $no_hp = htmlspecialchars($_POST['no_hp']);
        $alamat = htmlspecialchars($_POST['alamat']);
        $mapel = htmlspecialchars($_POST['mapel']);

        try {
            // Cek apakah email sudah digunakan oleh guru lain
            $stmt_check_email = $pdo->prepare("SELECT id FROM guru WHERE email = ? AND id != ?");
            $stmt_check_email->execute([$email, $guru_id]);
            if ($stmt_check_email->rowCount() > 0) {
                $error_message = "Email sudah terdaftar untuk akun lain.";
            } else {
                // Perbarui data di database
                $stmt = $pdo->prepare("UPDATE guru SET name = ?, nip = ?, gender = ?, email = ?, dob = ?, no_hp = ?, alamat = ?, mapel = ? WHERE id = ?");
                $stmt->execute([$name, $nip, $gender, $email, $dob, $no_hp, $alamat, $mapel, $guru_id]);
                $success_message = "Profil berhasil diperbarui!";
                $_SESSION['guru_name'] = $name; // Perbarui nama di sesi
            }
        } catch (PDOException $e) {
            $error_message = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }

    // --- FUNGSI UNTUK MENGUNGGAH FOTO PROFIL ---
    if (isset($_POST['upload_photo'])) {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['photo']['tmp_name'];
            $file_name = uniqid('photo_') . '_' . basename($_FILES['photo']['name']);
            $file_destination = '../uploads/guru/' . $file_name;

            // Hapus foto lama jika ada
            $stmt_old_photo = $pdo->prepare("SELECT photo FROM guru WHERE id = ?");
            $stmt_old_photo->execute([$guru_id]);
            $old_photo_name = $stmt_old_photo->fetchColumn();
            if ($old_photo_name && file_exists('../uploads/guru/' . $old_photo_name)) {
                unlink('../uploads/guru/' . $old_photo_name);
            }

            if (move_uploaded_file($file_tmp, $file_destination)) {
                $stmt = $pdo->prepare("UPDATE guru SET photo = ? WHERE id = ?");
                $stmt->execute([$file_name, $guru_id]);
                $success_message = "Foto profil berhasil diperbarui!";
            } else {
                $error_message = "Gagal mengunggah foto. Silakan coba lagi.";
            }
        } else {
            $error_message = "Terjadi kesalahan saat mengunggah foto.";
        }
    }

    // --- FUNGSI UNTUK MEMPERBARUI PASSWORD ---
    if (isset($_POST['change_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error_message = "Konfirmasi password tidak cocok!";
        } else if (strlen($new_password) < 6) {
            $error_message = "Password minimal 6 karakter.";
        } else {
            // Hash password baru
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE guru SET pass = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $guru_id]);
            $success_message = "Password berhasil diperbarui!";
        }
    }
}

// Ambil data profil guru terbaru dari database
$stmt_guru_profil = $pdo->prepare("
    SELECT 
        nip, 
        name, 
        gender, 
        email, 
        pass, 
        dob, 
        no_hp, 
        photo, 
        alamat, 
        mapel, 
        admission_date
    FROM guru
    WHERE id = ?
");
$stmt_guru_profil->execute([$guru_id]);
$guru_data = $stmt_guru_profil->fetch(PDO::FETCH_ASSOC);

if (!$guru_data) {
    // Jika data guru tidak ditemukan di DB, redirect ke login
    session_destroy();
    header("Location: ../login.php");
    exit;
}
$guru_photo = $guru_data['photo'];
$last_login = $_SESSION['last_login'] ?? 'Belum ada data login';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Dashboard Guru</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS yang disalin dari file-file sebelumnya */
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
            transition: width 0.3s ease, transform 0.3s ease;
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

        .sidebar.collapsed .logo span {
            font-size: 0.5em;
            transition: font-size 0.3s ease;
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

        .sidebar nav a.deactive {
            background-color: #253340ff;
            pointer-events: none;
        }

        .sidebar nav a.deactive:hover {
            background-color: #253340ff;
            padding-left: 20px;
            transition: none;
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

        .user-info .last-login {
            color: var(--light-text-color);
            font-size: 12px;
            margin-left: 10px;
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
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
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

        .profile-photo-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            margin-bottom: 2rem;
            text-align: center;
        }

        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
            margin-bottom: 1rem;
        }

        .profile-photo:hover {
            transform: scale(1.05);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background-color: var(--background-color);
            transition: border-color 0.2s ease;
        }

        .form-group input[type="file"] {
            padding: 5px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(26, 188, 156, 0.2);
        }

        .flex-container {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .flex-container>.form-group {
            flex: 1 1 45%;
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }

        .btn-submit {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-submit:hover {
            background-color: #16a085;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.collapsed {
                transform: translateX(0);
                width: var(--sidebar-collapsed-width);
            }

            .content,
            .header {
                margin-left: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding-left: 20px !important;
            }

            .header .user-info .last-login {
                display: none;
            }

            .sidebar.collapsed+.header,
            .sidebar.collapsed~.content {
                margin-left: var(--sidebar-collapsed-width) !important;
                left: var(--sidebar-collapsed-width) !important;
                width: calc(100% - var(--sidebar-collapsed-width)) !important;
            }
        }

        /* Back Link */
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: var(--light-text-color);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s ease;
        }

        .back-link:hover {
            color: var(--primary-color);
        }

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
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <span>GuruCoy</span>
        </div>
        <nav>
            <a href="dashboard_guru.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_guru.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Mengajar</span>
            </a>
            <a href="rekap_absensi_guru.php">
                <i class="fas fa-chart-bar"></i>
                <span>Rekap Absensi</span>
            </a>
            <div class="logout-button-container">
                <a href="#" id="logoutButton">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <header class="header" id="mainHeader">
        <button id="toggleSidebar" class="toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Profil Saya</h1>
        <div class="user-info" id="userInfoDropdown">
            <span class="user-name"><?php echo htmlspecialchars($guru_name); ?></span>
            <?php
            $guru_photo_src_header = !empty($guru_photo) ? '../uploads/guru/' . htmlspecialchars($guru_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
            ?>
            <img src="<?php echo $guru_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';">
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_guru.php"><i class="fas fa-user-circle"></i>Profil Saya</a>
                <a href="#" id="logoutDropdownLink"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>
    </header>

    <main class="content" id="mainContent">
        <div class="card">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form action="profil_guru.php" method="POST" enctype="multipart/form-data">
                <div class="profile-photo-container">
                    <?php
                    $guru_photo_src = !empty($guru_photo) ? '../uploads/guru/' . htmlspecialchars($guru_photo) : 'https://placehold.co/150x150/e0e0e0/333333?text=GR';
                    ?>
                    <img src="<?php echo $guru_photo_src; ?>" alt="Foto Profil Guru" class="profile-photo" id="profilePhotoPreview">
                    <div class="form-group mt-4">
                        <label for="photo_file" class="block text-gray-700 font-semibold mb-2">Unggah Foto Profil Baru</label>
                        <input type="file" name="photo" id="photo_file" accept="image/*" class="w-full p-2 border border-gray-300 rounded-lg">
                        <input type="hidden" name="upload_photo" value="1">
                        <button type="submit" class="btn-submit mt-2">Ubah Foto</button>
                    </div>
                </div>
            </form>

            <form action="profil_guru.php" method="POST">
                <h2 class="text-2xl font-bold mb-6">Informasi Dasar</h2>
                <input type="hidden" name="update_profile" value="1">
                <div class="flex-container">
                    <div class="form-group">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($guru_data['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nip">NIP</label>
                        <input type="text" id="nip" name="nip" value="<?php echo htmlspecialchars($guru_data['nip']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="gender">Jenis Kelamin</label>
                        <select id="gender" name="gender" required>
                            <option value="Laki-laki" <?php echo ($guru_data['gender'] == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Perempuan" <?php echo ($guru_data['gender'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($guru_data['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="dob">Tanggal Lahir</label>
                        <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($guru_data['dob']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="no_hp">No. HP</label>
                        <input type="tel" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($guru_data['no_hp']); ?>">
                    </div>
                    <div class="form-group w-full">
                        <label for="mapel">Mata Pelajaran</label>
                        <input type="text" id="mapel" name="mapel" value="<?php echo htmlspecialchars($guru_data['mapel']); ?>">
                    </div>
                    <div class="form-group w-full">
                        <label for="alamat">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($guru_data['alamat']); ?></textarea>
                    </div>
                    <div class="form-group w-full">
                        <label for="admission_date">Tanggal Diterima</label>
                        <input type="text" id="admission_date" name="admission_date" value="<?php echo htmlspecialchars($guru_data['admission_date']); ?>" disabled>
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn-submit">Simpan Perubahan</button>
                </div>
            </form>

            <hr class="my-6">

            <form action="profil_guru.php" method="POST">
                <h2 class="text-2xl font-bold mb-6">Ubah Password</h2>
                <input type="hidden" name="change_password" value="1">
                <div class="flex-container">
                    <div class="form-group">
                        <label for="new_password">Password Baru</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password Baru</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn-submit">Ubah Password</button>
                </div>
            </form>
            <a href="profil_guru.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Profil Anda
            </a>
        </div>
    </main>

    <script>
        // Logika Sidebar dan Dropdown
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const mainHeader = document.getElementById("mainHeader");
        const toggleSidebarBtn = document.getElementById("toggleSidebar");
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");
        const logoutButtonSidebar = document.getElementById('logoutButton');
        const logoutDropdownLink = document.getElementById('logoutDropdownLink');
        const photoFileInput = document.getElementById('photo_file');
        const photoPreview = document.getElementById('profilePhotoPreview');
        const successMessage = "<?php echo $success_message; ?>";
        const errorMessage = "<?php echo $error_message; ?>";

        toggleSidebarBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('shifted');
            mainHeader.classList.toggle('shifted');
        });

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function(event) {
                event.stopPropagation();
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });
            window.onclick = function(event) {
                if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                    if (userDropdownContent.style.display === 'block') {
                        userDropdownContent.style.display = 'none';
                    }
                }
            };
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
                    window.location.href = "../logout.php";
                }
            });
        }

        if (logoutButtonSidebar) {
            logoutButtonSidebar.addEventListener('click', showLogoutConfirmation);
        }
        if (logoutDropdownLink) {
            logoutDropdownLink.addEventListener('click', showLogoutConfirmation);
        }

        // Tampilkan pesan SweetAlert jika ada
        window.addEventListener('load', () => {
            if (successMessage) {
                Swal.fire('Berhasil!', successMessage, 'success');
            } else if (errorMessage) {
                Swal.fire('Error!', errorMessage, 'error');
            }
        });

        // Preview foto yang diunggah
        if (photoFileInput) {
            photoFileInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>

</html>