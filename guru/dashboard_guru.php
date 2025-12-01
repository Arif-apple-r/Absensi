<?php
// Pastikan semua error ditampilkan saat pengembangan
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Mulai sesi
session_start();

// Validasi otentikasi: pastikan hanya guru yang sudah login yang bisa mengakses halaman ini
// Gunakan exit; setelah header() untuk menghentikan eksekusi script
if (!isset($_SESSION['guru_id']) || empty($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

// Ambil ID guru dari sesi dan sanitasi
$guru_id = filter_var($_SESSION['guru_id'], FILTER_SANITIZE_NUMBER_INT);
$guru_id = (int) $guru_id; // Pastikan guru_id adalah integer

// Sertakan file koneksi database
// Pastikan path ini benar!
require '../koneksi.php';

// Periksa koneksi
if (!$conn) {
    die("Koneksi ke database gagal: " . mysqli_connect_error());
}

// --- PERBAIKAN UTAMA: MENGGUNAKAN PREPARED STATEMENT UNTUK MENCEGAH SQL INJECTION ---
// Prepared statement jauh lebih aman karena memisahkan kueri dari data
// Kueri SQL untuk mengambil nama dan foto guru berdasarkan ID
$sql_guru = "SELECT name, photo FROM guru WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql_guru);

// Inisialisasi variabel dengan nilai default
$guru_name = 'Guru';
$guru_photo = 'default.jpg'; // Gunakan nama file default untuk menghindari error path

if ($stmt) {
    // Bind parameter: "i" berarti variabel yang di-bind adalah integer
    mysqli_stmt_bind_param($stmt, "i", $guru_id);
    // Jalankan statement
    mysqli_stmt_execute($stmt);
    // Ambil hasilnya
    $res_guru = mysqli_stmt_get_result($stmt);

    if ($res_guru && mysqli_num_rows($res_guru) > 0) {
        // Ambil data guru dari hasil kueri
        $guru_data = mysqli_fetch_assoc($res_guru);
        // Masukkan data ke variabel, pastikan di-sanitize
        $guru_name = htmlspecialchars($guru_data['name']);
        $guru_photo = htmlspecialchars($guru_data['photo']);
    } else {
        // Jika data guru tidak ditemukan
        error_log("Data guru dengan ID $guru_id tidak ditemukan di database.");
    }
    // Tutup statement
    mysqli_stmt_close($stmt);
} else {
    // Handle error jika prepared statement gagal
    error_log("Gagal membuat prepared statement: " . mysqli_error($conn));
}

// --- Bagian PHP untuk mengambil data ringkasan dashboard dengan prepared statement ---
// Ini lebih aman, meskipun untuk COUNT() injection-nya lebih sulit, tapi ini adalah praktik yang baik.
function getCount($conn, $sql, $param, $type = "i") {
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $type, $param);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $count = $result ? mysqli_fetch_assoc($result)['count'] : 0;
        mysqli_stmt_close($stmt);
        return $count;
    }
    error_log("Gagal membuat prepared statement: " . mysqli_error($conn));
    return 0;
}

$sql_kelas = "SELECT COUNT(DISTINCT class_id) AS count FROM jadwal WHERE teacher_id = ?";
$total_kelas = getCount($conn, $sql_kelas, $guru_id);

$sql_mapel = "SELECT COUNT(DISTINCT id_mapel) AS count FROM jadwal WHERE teacher_id = ?";
$total_mapel = getCount($conn, $sql_mapel, $guru_id);

// Perhatikan bahwa kueri ini lebih kompleks, kita perlu memastikan penanganannya benar
$sql_siswa = "SELECT COUNT(DISTINCT s.id) AS count FROM siswa AS s JOIN class AS c ON s.class_id = c.id JOIN jadwal AS j ON c.id = j.class_id WHERE j.teacher_id = ?";
$total_siswa = getCount($conn, $sql_siswa, $guru_id);

$sql_pertemuan = "SELECT COUNT(p.id) AS count FROM pertemuan AS p JOIN jadwal AS j ON p.id_jadwal = j.id WHERE j.teacher_id = ?";
$total_pertemuan = getCount($conn, $sql_pertemuan, $guru_id);

// Tidak perlu menutup koneksi secara manual jika script akan berakhir.
// mysqli_close($conn); 
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Guru</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/userpage.css">
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>GuruCoy</span></div>
        <nav>
            <a href="#" class="active">
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

    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard Guru</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="guruName"><?php echo htmlspecialchars($guru_name); ?></span>
            <?php
            // Tampilkan foto profil guru jika ada, jika tidak pakai placeholder
            $guru_photo_src_header = !empty($guru_photo) ? '../uploads/guru/' . htmlspecialchars($guru_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
            ?>
            <img src="<?php echo $guru_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';">
            <!-- Dropdown Menu -->
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_guru.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a onclick="showLogoutConfirmation()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Ringkasan Data</h2>
            <div class="dashboard-stats-grid">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <p class="value" id="totalClasses"><?php echo htmlspecialchars($total_kelas); ?></p>
                    <p class="label">Kelas yang Diajar</p>
                </div>

                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-book-open"></i></div>
                    <p class="value" id="totalSubjects"><?php echo htmlspecialchars($total_mapel); ?></p>
                    <p class="label">Mata Pelajaran</p>
                </div>

                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-user-graduate"></i></div>
                    <p class="value" id="totalStudents"><?php echo htmlspecialchars($total_siswa); ?></p>
                    <p class="label">Jumlah Siswa</p>
                </div>

                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-calendar-check"></i></div>
                    <p class="value" id="totalMeetings"><?php echo htmlspecialchars($total_pertemuan); ?></p>
                    <p class="label">Total Pertemuan</p>
                </div>
            </div>

            <h2 style="margin-top: 30px;">Aksi Cepat</h2>
            <p style="color: var(--light-text-color); margin-bottom: 20px;">
                Gunakan tombol di bawah untuk navigasi cepat ke halaman manajemen.
            </p>
            <div>
                <button onclick="window.location.href='jadwal_guru.php';" class="action-button">
                    <i class="fas fa-calendar-alt"></i> Lihat Jadwal Mengajar
                </button>
                <button onclick="window.location.href='pertemuan_guru.php';" class="action-button">
                    <i class="fas fa-clipboard-list"></i> Kelola Pertemuan
                </button>
                <button onclick="window.location.href='rekap_absensi_guru.php';" class="action-button">
                    <i class="fas fa-check-circle"></i> Rekap Absensi
                </button>
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
    </script>
</body>
</html>