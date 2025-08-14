<?php
session_start();
// Pastikan hanya siswa yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../login.php"); // Sesuaikan path ke halaman login Anda
    exit;
}

// Ambil data siswa dari sesi
$siswa_id = $_SESSION['siswa_id'];
$siswa_name = $_SESSION['siswa_name'] ?? 'Siswa';
$siswa_nis = $_SESSION['siswa_nis'] ?? 'N/A';
$siswa_class_id = $_SESSION['siswa_class_id'] ?? null;
$last_login = $_SESSION['last_login'] ?? 'Belum ada data login';

// Sertakan file koneksi database Anda
require '../koneksi.php'; // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

$siswa_data = [];
$nama_kelas_siswa = 'Tidak Ditemukan';
$siswa_photo = ''; // Default photo

// Ambil data profil lengkap siswa dari database
$stmt_siswa_profil = $pdo->prepare("
    SELECT 
        s.NIS, 
        s.name, 
        s.gender, 
        s.dob, 
        s.photo, 
        s.no_hp, 
        s.email, 
        s.alamat, 
        s.admission_date,
        c.nama_kelas
    FROM siswa AS s
    LEFT JOIN class AS c ON s.class_id = c.id
    WHERE s.id = ?
");
$stmt_siswa_profil->execute([$siswa_id]);
$siswa_data = $stmt_siswa_profil->fetch(PDO::FETCH_ASSOC);

if ($siswa_data) {
    $nama_kelas_siswa = $siswa_data['nama_kelas'] ?? 'Tidak Ditemukan';
    $siswa_photo = $siswa_data['photo'];
} else {
    // Jika data siswa tidak ditemukan di DB (jarang terjadi jika sesi valid)
    // Redirect ke dashboard atau tampilkan error
    header("Location: dashboard_siswa.php?error=" . urlencode("Data profil siswa tidak ditemukan."));
    exit;
}

// Cek jika ada pesan sukses dari operasi sebelumnya
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profil Saya | Siswa</title>
    <!-- Font Awesome untuk ikon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Variabel CSS dari file admin/guru Anda */
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

        /* Reset dan Dasar */
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

        /* Sidebar */
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

        /* Header */
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
            cursor: pointer; /* Menjadikan seluruh area user-info clickable */
            padding: 5px 10px; /* Sedikit padding agar lebih mudah diklik */
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
            margin-left: 10px; /* Spasi dari nama */
        }
        .user-info i.fa-caret-down {
            margin-left: 5px; /* Spasi untuk ikon dropdown */
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%; /* Posisikan di bawah user-info */
            right: 0;
            background-color: var(--card-background);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            z-index: 1002;
            min-width: 160px;
            border-radius: 8px;
            overflow: hidden; /* Pastikan sudut melengkung */
            margin-top: 10px; /* Jarak antara user-info dan dropdown */
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
            width: 20px; /* Agar ikon sejajar */
        }


        /* Konten Utama */
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
            max-width: 800px; /* Lebih kecil untuk profil */
            margin-left: auto;
            margin-right: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .card h2 {
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
        }

        /* Profile specific styles */
        .profile-photo-container {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid var(--primary-color);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            background-color: #eee; /* Placeholder background */
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .profile-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-info {
            width: 100%;
            max-width: 500px;
            text-align: left;
        }
        .profile-info p {
            margin-bottom: 12px;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border-color);
        }
        .profile-info p:last-child {
            border-bottom: none;
        }
        .profile-info strong {
            color: var(--secondary-color);
            flex-basis: 200px; /* Agar label sejajar */
            display: inline-block;
        }
        .profile-info i {
            color: var(--primary-color);
            width: 20px; /* Agar ikon sejajar */
            text-align: center;
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
            border: 1px solid #f5c6cb;
        }

        /* Media Queries untuk Responsivitas */
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
            .header .user-info .last-login {
                display: none; /* Sembunyikan last login di mobile header */
            }
            .sidebar.collapsed + .header, .sidebar.collapsed ~ .content {
                margin-left: var(--sidebar-collapsed-width) !important;
                left: var(--sidebar-collapsed-width) !important;
                width: calc(100% - var(--sidebar-collapsed-width)) !important;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">SiswaCoy</div>
        <nav>
            <a href="dashboard_siswa.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_siswa.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Saya</span>
            </a>
            <a href="absensi_siswa.php">
                <i class="fas fa-check-circle"></i>
                <span>Absensi Saya</span>
            </a>
        </nav>
    </div>

    <!-- Header -->
    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-user"></i> Profil Siswa</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="siswaName"><?php echo htmlspecialchars($siswa_name); ?></span>
            <?php
            // Tampilkan foto profil siswa jika ada, jika tidak pakai placeholder
            $photo_src = !empty($siswa_data['photo']) ? '../uploads/siswa/' . htmlspecialchars($siswa_data['photo']) : 'https://placehold.co/40x40/cccccc/000000?text=SW';
            ?>
            <img src="<?php echo $photo_src; ?>" alt="User Avatar">
            <div class="last-login">Terakhir Login: <span id="lastLogin"><?php echo htmlspecialchars($last_login); ?></span></div>
            <i class="fas fa-caret-down"></i>

            <!-- Dropdown Menu -->
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_siswa.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Konten Utama -->
    <div class="content" id="mainContent">
        <div class="card">
            <h2>Informasi Pribadi</h2>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <div class="profile-photo-container">
                <img src="<?php echo $photo_src; ?>" alt="Foto Profil" class="profile-photo">
            </div>
            
            <div class="profile-info">
                <p><strong><i class="fas fa-id-card"></i> NIS:</strong> <?php echo htmlspecialchars($siswa_data['NIS'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-signature"></i> Nama Lengkap:</strong> <?php echo htmlspecialchars($siswa_data['name'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-venus-mars"></i> Jenis Kelamin:</strong> <?php echo htmlspecialchars(ucfirst($siswa_data['gender'] ?? '-')); ?></p>
                <p><strong><i class="fas fa-birthday-cake"></i> Tanggal Lahir:</strong> <?php echo htmlspecialchars($siswa_data['dob'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-phone"></i> No. HP:</strong> <?php echo htmlspecialchars($siswa_data['no_hp'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($siswa_data['email'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-map-marker-alt"></i> Alamat:</strong> <?php echo htmlspecialchars($siswa_data['alamat'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-school"></i> Kelas:</strong> <?php echo htmlspecialchars($nama_kelas_siswa); ?></p>
                <p><strong><i class="fas fa-calendar-plus"></i> Tanggal Masuk:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($siswa_data['admission_date'] ?? ''))); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Logika untuk toggle sidebar
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");

        function toggleSidebar() {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
        }

        // Logika Dropdown User Info
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");

        userInfoDropdown.addEventListener('click', function() {
            userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
        });

        // Tutup dropdown jika user klik di luar area dropdown
        window.onclick = function(event) {
            if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                if (userDropdownContent.style.display === 'block') {
                    userDropdownContent.style.display = 'none';
                }
            }
        }

        // Jalankan saat halaman dimuat
        window.onload = function() {
            // Set nama dan last login dari sesi PHP
            document.getElementById('siswaName').textContent = '<?php echo htmlspecialchars($siswa_name); ?>';
            document.getElementById('lastLogin').textContent = '<?php echo htmlspecialchars($last_login); ?>';

            // Mengatur link sidebar
            document.querySelector('.sidebar nav a:nth-child(1)').href = 'dashboard_siswa.php';
            document.querySelector('.sidebar nav a:nth-child(2)').href = 'jadwal_siswa.php';
            document.querySelector('.sidebar nav a:nth-child(3)').href = 'absensi_siswa.php';
        };
    </script>
</body>
</html>
