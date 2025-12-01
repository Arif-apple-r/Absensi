<?php
session_start();

// Validasi sesi siswa
if (!isset($_SESSION['siswa_id']) || !is_numeric($_SESSION['siswa_id'])) {
    header("Location: ../login.php");
    exit;
}

// Sertakan file koneksi database Anda
require_once '../koneksi.php';

// Ambil data siswa dari sesi
$siswa_id = $_SESSION['siswa_id'];
$siswa_name = $_SESSION['siswa_name'] ?? 'Siswa';
$siswa_nis = $_SESSION['siswa_nis'] ?? 'N/A';

// --- BAGIAN KODE YANG SUDAH DIPERBAIKI SECARA KESELURUHAN ---
// Mengambil data siswa, kelas, dan tahun akademik dalam satu query
$sql_siswa = "SELECT s.id, s.name AS nama, s.nis, s.class_id, s.photo,
                     c.nama_kelas, c.id_tahun_akademik,
                     ta.nama_tahun
              FROM siswa AS s
              LEFT JOIN class AS c ON s.class_id = c.id
              LEFT JOIN tahun_akademik AS ta ON c.id_tahun_akademik = ta.id
              WHERE s.id = ?";
$stmt_siswa = $pdo->prepare($sql_siswa);
$stmt_siswa->execute([$siswa_id]);
$siswa_data = $stmt_siswa->fetch(PDO::FETCH_ASSOC);

$siswa_photo = $siswa_data['photo'] ?? '';
$siswa_class_id = $siswa_data['class_id'] ?? null;
$nama_kelas_siswa = $siswa_data['nama_kelas'] ?? 'Tidak Ditemukan';
$nama_tahun_akademik = $siswa_data['nama_tahun'] ?? 'Tidak Ditemukan';
$id_tahun_akademik_siswa = $siswa_data['id_tahun_akademik'] ?? null;

// --- Bagian PHP untuk mengambil data ringkasan dashboard siswa ---
$total_mapel = 0;
$total_pertemuan_kelas = 0;
$rekap_absensi = [
    'Hadir' => 0,
    'Sakit' => 0,
    'Izin' => 0,
    'Alpha' => 0
];

try {
    if ($siswa_class_id && $id_tahun_akademik_siswa) {
        // 1. Jumlah Mata Pelajaran yang diikuti siswa (difilter berdasarkan tahun akademik)
        // Perbaikan: Gabungkan dengan tabel `class` untuk mendapatkan `id_tahun_akademik`
        $sql_mapel = "SELECT COUNT(DISTINCT j.id_mapel) AS jumlah_mapel
                      FROM jadwal AS j
                      JOIN class AS c ON j.class_id = c.id
                      WHERE c.id = ? AND c.id_tahun_akademik = ?";
        $stmt_mapel = $pdo->prepare($sql_mapel);
        $stmt_mapel->execute([$siswa_class_id, $id_tahun_akademik_siswa]);
        $total_mapel = $stmt_mapel->fetch(PDO::FETCH_ASSOC)['jumlah_mapel'] ?? 0;

        // 2. Jumlah Total Pertemuan di kelas siswa (difilter berdasarkan tahun akademik)
        // Perbaikan: Gabungkan dengan tabel `class` untuk mendapatkan `id_tahun_akademik`
        $sql_pertemuan_kelas = "SELECT COUNT(p.id) AS jumlah_pertemuan
                                FROM pertemuan AS p
                                JOIN jadwal AS j ON p.id_jadwal = j.id
                                JOIN class AS c ON j.class_id = c.id
                                WHERE c.id = ? AND c.id_tahun_akademik = ?";
        $stmt_pertemuan_kelas = $pdo->prepare($sql_pertemuan_kelas);
        $stmt_pertemuan_kelas->execute([$siswa_class_id, $id_tahun_akademik_siswa]);
        $total_pertemuan_kelas = $stmt_pertemuan_kelas->fetch(PDO::FETCH_ASSOC)['jumlah_pertemuan'] ?? 0;
    }

    // 3. Rekap Absensi Siswa (Hadir, Sakit, Izin, Alpha) (difilter berdasarkan tahun akademik)
    // Query ini sudah benar, tidak perlu diubah.
    $sql_rekap_absensi = "SELECT a.status, COUNT(*) AS count
                          FROM absensi AS a
                          JOIN pertemuan AS p ON a.id_pertemuan = p.id
                          JOIN jadwal AS j ON p.id_jadwal = j.id
                          JOIN class AS c ON j.class_id = c.id
                          WHERE a.id_siswa = ? AND c.id_tahun_akademik = ?
                          GROUP BY a.status";
    $stmt_rekap_absensi = $pdo->prepare($sql_rekap_absensi);
    $stmt_rekap_absensi->execute([$siswa_id, $id_tahun_akademik_siswa]);
    while ($row = $stmt_rekap_absensi->fetch(PDO::FETCH_ASSOC)) {
        if (isset($rekap_absensi[$row['status']])) {
            $rekap_absensi[$row['status']] = $row['count'];
        }
    }
} catch (PDOException $e) {
    die("Error mengambil data dari database: " . $e->getMessage());
}

$total_absensi_tercatat = array_sum($rekap_absensi);
$success_message = $_GET['success'] ?? '';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard Siswa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/userpage.css">
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>SiswaCoy</span></div>
        <nav>
            <a href="#" class="active">
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

    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard Siswa</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="siswaName"><?php echo htmlspecialchars($siswa_name); ?></span>
            <?php
            // Tampilkan foto profil siswa jika ada, jika tidak pakai placeholder
            $siswa_photo_src_header = !empty($siswa_photo) ? '../uploads/siswa/' . htmlspecialchars($siswa_photo) : 'https://placehold.co/40x40/cccccc/333333?text=GR';
            ?>
            <img src="<?php echo $siswa_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';">
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_siswa.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a onclick="showLogoutConfirmation()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Selamat Datang, <?php echo htmlspecialchars($siswa_name); ?>!</h2>
            <div class="info-header">
                <p><strong>NIS:</strong> <?php echo htmlspecialchars($siswa_nis); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($nama_kelas_siswa); ?></p>
            </div>
            
            <div class="info-card">
                <div class="info-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="info-details">
                    <h3>Tahun Akademik</h3>
                    <p><?php echo htmlspecialchars($nama_tahun_akademik); ?></p>
                </div>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <h2>Ringkasan Data Akademik</h2>
            <div class="dashboard-stats-grid">
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-book-open"></i></div>
                    <p class="value"><?php echo htmlspecialchars($total_mapel); ?></p>
                    <p class="label">Mata Pelajaran Diikuti</p>
                </div>

                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                    <p class="value"><?php echo htmlspecialchars($total_pertemuan_kelas); ?></p>
                    <p class="label">Total Pertemuan Kelas</p>
                </div>

                <div class="stat-card purple">
                    <div class="icon"><i class="fas fa-check-circle"></i></div>
                    <p class="value"><?php echo htmlspecialchars($rekap_absensi['Hadir']); ?></p>
                    <p class="label">Kehadiran (Hadir)</p>
                </div>

                <div class="stat-card teal">
                    <div class="icon"><i class="fas fa-heartbeat"></i></div>
                    <p class="value"><?php echo htmlspecialchars($rekap_absensi['Sakit']); ?></p>
                    <p class="label">Kehadiran (Sakit)</p>
                </div>

                <div class="stat-card gray">
                    <div class="icon"><i class="fas fa-user-times"></i></div>
                    <p class="value"><?php echo htmlspecialchars($rekap_absensi['Izin']); ?></p>
                    <p class="label">Kehadiran (Izin)</p>
                </div>

                <div class="stat-card dark-red">
                    <div class="icon"><i class="fas fa-times-circle"></i></div>
                    <p class="value"><?php echo htmlspecialchars($rekap_absensi['Alpha']); ?></p>
                    <p class="label">Kehadiran (Alpha)</p>
                </div>
            </div>

            <h2>Akses Data Saya</h2>
            <p style="color: var(--light-text-color); margin-bottom: 20px;">
                Lihat detail jadwal dan rekap absensi Anda.
            </p>
            <div>
                <button onclick="window.location.href='jadwal_siswa.php';" class="action-button">
                    <i class="fas fa-calendar-alt"></i> Jadwal Saya
                </button>
                <button onclick="window.location.href='absensi_siswa.php';" class="action-button">
                    <i class="fas fa-check-circle"></i> Absensi Saya
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
                    window.location.href = "../logout.php";
                }
            });
        }

        // Logika Dropdown User Info
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");

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
    </script>
</body>
</html>