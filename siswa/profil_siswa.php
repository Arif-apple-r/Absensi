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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/userpage.css">
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>SiswaCoy</span></div>
        <nav>
            <a href="dashboard_siswa.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_siswa.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Saya</span>
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
        <h1><i class="fas fa-user"></i> Profil Siswa</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="siswaName"><?php echo htmlspecialchars($siswa_name); ?></span>
            <?php
            // Tampilkan foto profil siswa jika ada, jika tidak pakai placeholder
            $siswa_photo_src_header = !empty($siswa_photo) ? '../uploads/siswa/' . htmlspecialchars($siswa_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
            ?>
            <img src="<?php echo $siswa_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';">
            <!-- Dropdown Menu -->
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_siswa.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a onclick="showLogoutConfirmation()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <!-- Konten Utama -->
    <div class="content" id="mainContent">
        <div class="card1">
            <h2>Informasi Pribadi</h2>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <div class="profile-photo-container">
                <img src="<?php echo !empty($siswa_photo) ? '../uploads/siswa/' . htmlspecialchars($siswa_photo) : 'https://placehold.co/150x150?text=NO+IMAGE'; ?>" alt="Foto Profil" class="profile-photo">
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

        if (userInfoDropdown && userDropdownContent) { // Pastikan elemen ada
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
