<?php
// File: rekap_absensi_kelas.php

session_start();
if (!isset($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

$guru_id = $_SESSION['guru_id'];
$guru_name = $_SESSION['guru_name'] ?? 'Guru';
require '../koneksi.php';

// Ambil id_jadwal dari URL
$jadwal_id = $_GET['jadwal_id'] ?? null;

if (!$jadwal_id) {
    header("Location: rekap_absensi_guru.php?error=" . urlencode("ID Jadwal tidak valid."));
    exit;
}

// Verifikasi guru memiliki akses ke jadwal ini, dan ambil informasi terkait
$stmt_info = $pdo->prepare("
    SELECT
        j.class_id,
        j.id_mapel,
        c.nama_kelas,
        m.nama_mapel
    FROM
        jadwal AS j
    INNER JOIN
        class AS c ON j.class_id = c.id
    INNER JOIN
        mapel AS m ON j.id_mapel = m.id
    WHERE
        j.id = ? AND j.teacher_id = ?
");
$stmt_info->execute([$jadwal_id, $guru_id]);
$info = $stmt_info->fetch(PDO::FETCH_ASSOC);

if (!$info) {
    header("Location: rekap_absensi_guru.php?error=" . urlencode("Anda tidak memiliki akses ke jadwal ini."));
    exit;
}

$class_id = $info['class_id'];
$mapel_id = $info['id_mapel'];
$nama_kelas = $info['nama_kelas'];
$nama_mapel = $info['nama_mapel'];

// Hitung total pertemuan yang tersedia berdasarkan jadwal_id
$stmt_total_meetings = $pdo->prepare("
    SELECT COUNT(*) AS total_meetings FROM pertemuan WHERE id_jadwal = ?
");
$stmt_total_meetings->execute([$jadwal_id]);
$total_available_meetings = $stmt_total_meetings->fetchColumn();

// Query untuk mendapatkan rekap absensi setiap siswa di kelas ini untuk mata pelajaran ini
$query_rekap = "
    SELECT
        s.id AS siswa_id,
        s.NIS,
        s.name AS nama_siswa,
        COUNT(CASE WHEN a.status = 'Hadir' THEN 1 END) AS count_hadir,
        COUNT(CASE WHEN a.status = 'Sakit' THEN 1 END) AS count_sakit,
        COUNT(CASE WHEN a.status = 'Izin' THEN 1 END) AS count_izin,
        COUNT(CASE WHEN a.status = 'Alpha' THEN 1 END) AS count_alpha,
        COUNT(a.id) AS total_absensi_tercatat_siswa
    FROM
        siswa AS s
    LEFT JOIN
        absensi AS a ON s.id = a.id_siswa
    LEFT JOIN
        pertemuan AS p ON a.id_pertemuan = p.id
    WHERE
        s.class_id = ? AND p.id_jadwal = ?
    GROUP BY
        s.id, s.NIS, s.name
    ORDER BY
        s.name ASC;
";
$stmt_rekap = $pdo->prepare($query_rekap);
$stmt_rekap->execute([$class_id, $jadwal_id]);
$rekap_absensi_siswa = $stmt_rekap->fetchAll(PDO::FETCH_ASSOC);


// Untuk memastikan semua siswa di kelas tampil, bahkan yang belum punya absensi
$stmt_all_students_in_class = $pdo->prepare("SELECT id, NIS, name FROM siswa WHERE class_id = ? ORDER BY name ASC");
$stmt_all_students_in_class->execute([$class_id]);
$all_students_in_class = $stmt_all_students_in_class->fetchAll(PDO::FETCH_ASSOC);

$final_rekap_data = [];
$rekap_indexed_by_siswa_id = [];
foreach ($rekap_absensi_siswa as $row) {
    $rekap_indexed_by_siswa_id[$row['siswa_id']] = $row;
}

// Gabungkan semua siswa dengan data absensi mereka (jika ada)
foreach ($all_students_in_class as $student) {
    $siswa_id_current = $student['id'];
    if (isset($rekap_indexed_by_siswa_id[$siswa_id_current])) {
        $final_rekap_data[] = $rekap_indexed_by_siswa_id[$siswa_id_current];
    } else {
        // Siswa ada di kelas ini tapi belum memiliki catatan absensi untuk mapel ini
        $final_rekap_data[] = [
            'siswa_id' => $siswa_id_current,
            'NIS' => $student['NIS'],
            'nama_siswa' => $student['name'],
            'count_hadir' => 0,
            'count_sakit' => 0,
            'count_izin' => 0,
            'count_alpha' => 0,
            'total_absensi_tercatat_siswa' => 0 // Ini akan selalu 0 jika tidak ada absensi
        ];
    }
}

$message = '';
$alert_type = '';
if (isset($_GET['success'])) {
    $message = htmlspecialchars($_GET['success']);
    $alert_type = 'alert-success';
} elseif (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $alert_type = 'alert-error';
}

// Ambil foto guru dari database untuk header
$guru_photo = '';
if (!empty($guru_id)) {
    $stmt_guru_photo = $pdo->prepare("SELECT photo FROM guru WHERE id = ?");
    $stmt_guru_photo->execute([$guru_id]);
    $result = $stmt_guru_photo->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $guru_photo = htmlspecialchars($result['photo']);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Rekap Absensi <?php echo htmlspecialchars($nama_kelas); ?> - <?php echo htmlspecialchars($nama_mapel); ?> | Guru</title>
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
            <a href="dashboard_guru.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_guru.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Mengajar</span>
            </a>
            <a href="rekap_absensi_guru.php" class="active">
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
        <h1><i class="fas fa-chart-bar"></i> Rekap Absensi</h1>
        <div class="user-info" id="userInfoDropdown">
            <span id="guruName"><?php echo htmlspecialchars($guru_name); ?></span>
            <?php
            $guru_photo_src_header = !empty($guru_photo) ? '../uploads/guru/' . htmlspecialchars($guru_photo) : 'https://placehold.co/40x40/cccccc/000000?text=GR';
            ?>
            <img src="<?php echo $guru_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=GR';">

            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_guru.php"><i class="fas fa-user-circle"></i> Profil</a>
                <a onclick="showLogoutConfirmation()"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Rekap Absensi Kelas <?php echo htmlspecialchars($nama_kelas); ?> - <?php echo htmlspecialchars($nama_mapel); ?></h2>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="info-header">
                <p>Total Pertemuan Mata Pelajaran Ini: <strong><?php echo htmlspecialchars($total_available_meetings); ?></strong></p>
            </div>

            <?php if (empty($final_rekap_data)): ?>
                <div class="info-header">
                    <p>Tidak ada siswa di kelas ini atau tidak ada data absensi yang tercatat untuk mata pelajaran ini.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Hadir</th>
                                <th>Sakit</th>
                                <th>Izin</th>
                                <th>Alpha</th>
                                <th>Total Tercatat</th>
                                <th>Persentase Hadir</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($final_rekap_data as $siswa_rekap): ?>
                                <?php
                                    $persentase_hadir = 0;
                                    if ($total_available_meetings > 0) {
                                        $persentase_hadir = ($siswa_rekap['count_hadir'] / $total_available_meetings) * 100;
                                    }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($siswa_rekap['NIS']); ?></td>
                                    <td>
                                        <a href="detail_absensi_siswa.php?siswa_id=<?php echo htmlspecialchars($siswa_rekap['siswa_id']); ?>&jadwal_id=<?php echo htmlspecialchars($jadwal_id); ?>" style="color: var(--primary-color); text-decoration: none; font-weight: 600;">
                                            <?php echo htmlspecialchars($siswa_rekap['nama_siswa']); ?>
                                        </a>
                                    </td>
                                    <td class="status-hadir-count"><?php echo htmlspecialchars($siswa_rekap['count_hadir']); ?></td>
                                    <td class="status-sakit-count"><?php echo htmlspecialchars($siswa_rekap['count_sakit']); ?></td>
                                    <td class="status-izin-count"><?php echo htmlspecialchars($siswa_rekap['count_izin']); ?></td>
                                    <td class="status-alpha-count"><?php echo htmlspecialchars($siswa_rekap['count_alpha']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa_rekap['total_absensi_tercatat_siswa']); ?></td>
                                    <td><?php echo number_format($persentase_hadir, 2); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <a href="rekap_absensi_guru.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Rekap Kelas
            </a>

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