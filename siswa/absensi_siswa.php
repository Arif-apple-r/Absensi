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
$siswa_photo_session = $_SESSION['siswa_photo'] ?? '';

// Ambil ID jadwal dari URL. Jika tidak ada, nilainya null.
$id_jadwal = $_GET['id_jadwal'] ?? null;

// Sertakan file koneksi database Anda
require '../koneksi.php';

$rekap_absensi_siswa = [];
$nama_kelas_siswa = 'Memuat...';
$nama_tahun_akademik = 'Memuat...';

// Variabel untuk menyimpan informasi jadwal yang dipilih
$jadwal_info = null;

if ($siswa_class_id) {
    // Ambil Nama Kelas Siswa dan Nama Tahun Akademik
    $stmt_kelas_nama = $pdo->prepare("SELECT c.nama_kelas, ta.nama_tahun FROM class c JOIN tahun_akademik ta ON c.id_tahun_akademik = ta.id WHERE c.id = ?");
    $stmt_kelas_nama->execute([$siswa_class_id]);
    $kelas_data = $stmt_kelas_nama->fetch(PDO::FETCH_ASSOC);
    $nama_kelas_siswa = $kelas_data['nama_kelas'] ?? 'Tidak Ditemukan';
    $nama_tahun_akademik = $kelas_data['nama_tahun'] ?? 'Tidak Ditemukan';

    // Jika id_jadwal ada di URL, ambil detail jadwal terkait
    if ($id_jadwal) {
        $query_jadwal_info = "
            SELECT m.nama_mapel, g.name AS nama_guru
            FROM jadwal j
            JOIN mapel m ON j.id_mapel = m.id
            JOIN guru g ON j.teacher_id = g.id
            WHERE j.id = ? AND j.class_id = ?
        ";
        $stmt_jadwal_info = $pdo->prepare($query_jadwal_info);
        $stmt_jadwal_info->execute([$id_jadwal, $siswa_class_id]);
        $jadwal_info = $stmt_jadwal_info->fetch(PDO::FETCH_ASSOC);
    }
    
    // Query untuk mengambil absensi siswa
    // Modifikasi kueri agar JOIN ke tabel 'pertemuan' dan 'jadwal'
    $query_absensi = "
        SELECT
            a.status,
            a.keterangan,
            a.waktu_input,
            p.tanggal AS tanggal_pertemuan,
            p.topik AS topik_pertemuan,
            m.nama_mapel,
            g.name AS nama_guru,
            j.hari,
            j.jam_mulai,
            j.jam_selesai
        FROM absensi AS a
        JOIN pertemuan AS p ON a.id_pertemuan = p.id
        JOIN jadwal AS j ON p.id_jadwal = j.id
        JOIN mapel AS m ON j.id_mapel = m.id
        JOIN guru AS g ON j.teacher_id = g.id
        WHERE a.id_siswa = ?
    ";
    
    // Siapkan parameter
    $params = [$siswa_id];
    
    // Kondisi WHERE tambahan jika id_jadwal tersedia
    if ($id_jadwal) {
        $query_absensi .= " AND j.id = ?";
        $params[] = $id_jadwal;
    }
    
    $query_absensi .= " ORDER BY p.tanggal DESC, j.jam_mulai DESC";
    
    $stmt_absensi = $pdo->prepare($query_absensi);
    $stmt_absensi->execute($params);
    $rekap_absensi_siswa = $stmt_absensi->fetchAll(PDO::FETCH_ASSOC);
}
// Cek jika ada pesan sukses dari operasi sebelumnya
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = htmlspecialchars($_GET['success']);
}

$siswa_photo = '';
if (!empty($siswa_id)) {
    $stmt_siswa_photo = $pdo->prepare("SELECT photo FROM siswa WHERE id = ?");
    $stmt_siswa_photo->execute([$siswa_id]);
    $result = $stmt_siswa_photo->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $siswa_photo = htmlspecialchars($result['photo']);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Absensi Saya | Siswa</title>
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
            <a href="dashboard_siswa.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_siswa.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Saya</span>
            </a>
            <a href="absensi_siswa.php" class="active">
                <i class="fas fa-check-circle"></i>
                <span>Absensi Saya</span>
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
        <h1><i class="fas fa-check-circle"></i> Absensi Saya</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="siswaName"><?php echo htmlspecialchars($siswa_name); ?></span>
            <?php
            // Tampilkan foto profil siswa jika ada, jika tidak pakai placeholder
            $siswa_photo_src_header = !empty($siswa_photo) ? '../uploads/siswa/' . htmlspecialchars($siswa_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
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
            <h2>Rekap Absensi Pribadi</h2>
            <div class="info-header">
                <p><strong>Nama:</strong> <?php echo htmlspecialchars($siswa_name); ?></p>
                <p><strong>NIS:</strong> <?php echo htmlspecialchars($siswa_nis); ?></p>
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($nama_kelas_siswa); ?></p>
                <p><strong>Tahun Akademik:</strong> <?php echo htmlspecialchars($nama_tahun_akademik); ?></p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (empty($rekap_absensi_siswa)): ?>
                <div class="info-header">
                    <p>Belum ada data absensi yang tercatat untuk Anda.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Mata Pelajaran</th>
                                <th>Topik Pertemuan</th>
                                <th>Guru Pengajar</th>
                                <th>Status</th>
                                <th>Keterangan</th>
                                <th>Waktu Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rekap_absensi_siswa as $absensi): ?>
                                <?php
                                    $status_class = '';
                                    switch ($absensi['status']) {
                                        case 'Hadir':
                                            $status_class = 'status-hadir';
                                            break;
                                        case 'Sakit':
                                            $status_class = 'status-sakit';
                                            break;
                                        case 'Izin':
                                            $status_class = 'status-izin';
                                            break;
                                        case 'Alpha':
                                            $status_class = 'status-alpha';
                                            break;
                                        default:
                                            $status_class = 'status-default';
                                            break;
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($absensi['tanggal_pertemuan']); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['nama_mapel']); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['topik_pertemuan']); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['nama_guru']); ?></td>
                                    <td class="<?php echo $status_class; ?>"><?php echo htmlspecialchars($absensi['status']); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['keterangan'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($absensi['waktu_input'] ? date('d M Y H:i', strtotime($absensi['waktu_input'])) : '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

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
        };
    </script>
</body>
</html>