<?php
session_start();
// Pastikan hanya guru yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['guru_id'])) {
    header("Location: ../login.php"); // Sesuaikan path ke halaman login Anda
    exit;
}

// Ambil data guru dari sesi
$guru_id = $_SESSION['guru_id'];
$guru_name = $_SESSION['guru_name'] ?? 'Guru';
$last_login = $_SESSION['last_login'] ?? 'Belum ada data login';

// Sertakan file koneksi database Anda
require '../koneksi.php'; // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

$guru_data = [];
$guru_photo = ''; // Default photo

// Ambil data profil lengkap guru dari database
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

if ($guru_data) {
    $guru_photo = $guru_data['photo'];
} else {
    // Jika data guru tidak ditemukan di DB (jarang terjadi jika sesi valid)
    // Redirect ke dashboard atau tampilkan error
    header("Location: dashboard_guru.php?error=" . urlencode("Data profil guru tidak ditemukan."));
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
    <title>Profil Saya | Guru</title>
    <!-- Font Awesome untuk ikon -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/userpage.css">
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>GuruCoy</span></div>
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
                <a onclick="showLogoutConfirmation()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Header -->
    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-user"></i> Profil Guru</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="guruName"><?php echo htmlspecialchars($guru_name); ?></span>
            <?php
            // Tampilkan foto profil guru jika ada, jika tidak pakai placeholder
            $photo_src = !empty($guru_data['photo']) ? '../uploads/guru/' . htmlspecialchars($guru_data['photo']) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
            ?>
            <img src="<?php echo $photo_src; ?>" alt="User Avatar">

            <!-- Dropdown Menu -->
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_guru.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a onclick="showLogoutConfirmation()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Konten Utama -->
    <div class="content" id="mainContent">
        <div class="card1">
            <h2>Informasi Pribadi Guru</h2>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <div class="profile-photo-container">
                <img src="<?php echo $photo_src; ?>" alt="Foto Profil Guru" class="profile-photo">
            </div>
            
            <div class="profile-info">
                <p><strong><i class="fas fa-id-badge"></i> NIP:</strong> <?php echo htmlspecialchars($guru_data['nip'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-signature"></i> Nama Lengkap:</strong> <?php echo htmlspecialchars($guru_data['name'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-venus-mars"></i> Jenis Kelamin:</strong> <?php echo htmlspecialchars(ucfirst($guru_data['gender'] ?? '-')); ?></p>
                <p><strong><i class="fas fa-birthday-cake"></i> Tanggal Lahir:</strong> <?php echo htmlspecialchars($guru_data['dob'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-phone"></i> No. HP:</strong> <?php echo htmlspecialchars($guru_data['no_hp'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-envelope"></i> Email:</strong> <?php echo htmlspecialchars($guru_data['email'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-map-marker-alt"></i> Alamat:</strong> <?php echo htmlspecialchars($guru_data['alamat'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-book"></i> Mengajar Mapel:</strong> <?php echo htmlspecialchars($guru_data['mapel'] ?? '-'); ?></p>
                <p><strong><i class="fas fa-calendar-plus"></i> Tanggal Masuk:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($guru_data['admission_date'] ?? ''))); ?></p>
            </div>
            <div class="edit-profil">
                <a href="edit_profil.php">Edit Profil</a>
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
                    window.location.href = "../logout.php"; // redirect logout
                }
            });
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
            document.getElementById('guruName').textContent = '<?php echo htmlspecialchars($guru_name); ?>';
            document.getElementById('lastLogin').textContent = '<?php echo htmlspecialchars($last_login); ?>';

            // Mengatur link sidebar
            document.querySelector('.sidebar nav a:nth-child(1)').href = 'dashboard_guru.php';
            document.querySelector('.sidebar nav a:nth-child(2)').href = 'jadwal_guru.php';
            document.querySelector('.sidebar nav a:nth-child(3)').href = 'pertemuan_guru.php';
            document.querySelector('.sidebar nav a:nth-child(4)').href = 'absensi_guru.php';
        };
    </script>
</body>
</html>
