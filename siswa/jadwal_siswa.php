<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../login.php");
    exit;
}

require '../koneksi.php';

$siswa_id = $_SESSION['siswa_id'];
$siswa_name = htmlspecialchars($_SESSION['siswa_name'] ?? 'Siswa');
$siswa_nis = htmlspecialchars($_SESSION['siswa_nis'] ?? 'N/A');
$siswa_class_id_session = $_SESSION['siswa_class_id'] ?? null;
$last_login = htmlspecialchars($_SESSION['last_login'] ?? 'Belum ada data login');
$siswa_photo_session = htmlspecialchars($_SESSION['siswa_photo'] ?? '');

function getActiveTahunAkademikId($pdo)
{
    try {
        $stmt = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'] ?? null;
    } catch (PDOException $e) {
        error_log("Error getting active academic year ID: " . $e->getMessage());
        return null;
    }
}

// Ambil semua daftar Tahun Akademik untuk filter dropdown
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun, is_active FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

// Tentukan tahun akademik yang sedang dipilih (dari GET atau default ke yang aktif)
$selected_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? null;
if ($selected_tahun_akademik_id === null) {
    $selected_tahun_akademik_id = getActiveTahunAkademikId($pdo);
}

// Ambil data siswa dari database untuk mendapatkan class_id
$stmt_siswa = $pdo->prepare("SELECT class_id, photo FROM siswa WHERE id = ?");
$stmt_siswa->execute([$siswa_id]);
$siswa_data = $stmt_siswa->fetch(PDO::FETCH_ASSOC);
$siswa_class_id = $siswa_data['class_id'] ?? null;
$siswa_photo = $siswa_data['photo'] ?? '';

// Ambil semua daftar Mata Pelajaran untuk filter dropdown
$stmt_mapel = $pdo->query("SELECT id, nama_mapel FROM mapel ORDER BY nama_mapel ASC");
$mapel_options = $stmt_mapel->fetchAll(PDO::FETCH_ASSOC);

// Ambil filter yang dipilih dari URL
$selected_hari = $_GET['hari'] ?? null;
$selected_mapel_id = $_GET['mapel_id'] ?? null;

$jadwal_siswa = [];
$nama_kelas_siswa = 'Memuat...';

if ($siswa_class_id && $selected_tahun_akademik_id) {
    // Ambil Nama Kelas Siswa berdasarkan tahun akademik yang dipilih
    $stmt_kelas_nama = $pdo->prepare("SELECT nama_kelas FROM class WHERE id = ? AND id_tahun_akademik = ?");
    $stmt_kelas_nama->execute([$siswa_class_id, $selected_tahun_akademik_id]);
    $kelas_data = $stmt_kelas_nama->fetch(PDO::FETCH_ASSOC);
    $nama_kelas_siswa = $kelas_data['nama_kelas'] ?? 'Tidak Ditemukan';

    // Query untuk mengambil jadwal siswa dengan filter hari dan mapel
    $query_jadwal = "
        SELECT
            j.id AS jadwal_id,
            j.hari,
            j.jam_mulai,
            j.jam_selesai,
            m.nama_mapel,
            g.name AS nama_guru,
            c.nama_kelas
        FROM jadwal AS j
        JOIN mapel AS m ON j.id_mapel = m.id
        JOIN guru AS g ON j.teacher_id = g.id
        JOIN class AS c ON j.class_id = c.id
        WHERE j.class_id = ? AND c.id_tahun_akademik = ?
    ";
    
    $params = [$siswa_class_id, $selected_tahun_akademik_id];
    
    if ($selected_hari && $selected_hari !== 'all') {
        $query_jadwal .= " AND j.hari = ?";
        $params[] = $selected_hari;
    }

    if ($selected_mapel_id && $selected_mapel_id !== 'all') {
        $query_jadwal .= " AND j.id_mapel = ?";
        $params[] = $selected_mapel_id;
    }

    $query_jadwal .= " ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai ASC;";

    $stmt_jadwal = $pdo->prepare($query_jadwal);
    $stmt_jadwal->execute($params);
    $jadwal_siswa = $stmt_jadwal->fetchAll(PDO::FETCH_ASSOC);

    $stmt_mapel_filter = $pdo->prepare("SELECT DISTINCT m.id, m.nama_mapel FROM jadwal j JOIN mapel m ON j.id_mapel = m.id WHERE j.class_id = ?");
    $stmt_mapel_filter->execute([$siswa_class_id_session]);
    $mapel_filter_options = $stmt_mapel_filter->fetchAll(PDO::FETCH_ASSOC);

}

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
    <title>Jadwal Saya | Siswa</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
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
            <a href="jadwal_siswa.php" class="active">
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
        <h1><i class="fas fa-calendar-alt"></i> Jadwal Saya</h1>
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
            <h2>Jadwal Pelajaran Kelas <?php echo htmlspecialchars($nama_kelas_siswa); ?></h2>
            
            <div class="filter-container">
                <label for="tahun_akademik" class="font-semibold text-gray-700">Tahun Akademik:</label>
                <select id="tahun_akademik">
                    <?php foreach ($tahun_akademik_options as $ta): ?>
                        <option 
                            value="<?php echo htmlspecialchars($ta['id']); ?>" 
                            <?php echo ($ta['id'] == $selected_tahun_akademik_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ta['nama_tahun']); ?>
                            <?php echo ($ta['is_active']) ? ' (Aktif)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <label for="hari_filter" class="font-semibold text-gray-700">Hari:</label>
                <select id="hari_filter">
                    <option value="all">Semua Hari</option>
                    <?php
                    $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                    foreach ($days as $day):
                        $selected = ($day == $selected_hari) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $day; ?>" <?php echo $selected; ?>><?php echo $day; ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="mapel_filter" class="font-semibold text-gray-700">Mata Pelajaran:</label>
                <select id="mapel_filter">
                    <option value="all">Semua Mata Pelajaran</option>
                    <?php foreach ($mapel_options as $mapel): ?>
                        <option 
                            value="<?php echo htmlspecialchars($mapel['id']); ?>" 
                            <?php echo ($mapel['id'] == $selected_mapel_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($mapel['nama_mapel']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (empty($jadwal_siswa)): ?>
                <div class="info-header">
                    <p>Tidak ada jadwal yang tersedia untuk kelas Anda dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Hari</th>
                                <th>Jam Mulai</th>
                                <th>Jam Selesai</th>
                                <th>Mata Pelajaran</th>
                                <th>Guru Pengajar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwal_siswa as $jadwal): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($jadwal['hari']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($jadwal['jam_mulai'], 0, 5)); ?></td>
                                    <td><?php echo htmlspecialchars(substr($jadwal['jam_selesai'], 0, 5)); ?></td>
                                    <td><?php echo htmlspecialchars($jadwal['nama_mapel']); ?></td>
                                    <td><?php echo htmlspecialchars($jadwal['nama_guru']); ?></td>
                                <td>
                                    <a href="absensi_siswa.php?id_jadwal=<?php echo htmlspecialchars($jadwal['jadwal_id']); ?>" class="btn">Lihat Absensi</a>
                                </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");
        const tahunAkademikFilter = document.getElementById('tahun_akademik');
        const hariFilter = document.getElementById('hari_filter');
        const mapelFilter = document.getElementById('mapel_filter');

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
        
        // Logika untuk menangani perubahan filter
        function applyFilters() {
            const tahunId = tahunAkademikFilter.value;
            const hari = hariFilter.value;
            const mapelId = mapelFilter.value;
            
            const url = new URL(window.location.href);
            url.searchParams.set('tahun_akademik_id', tahunId);
            
            if (hari && hari !== 'all') {
                url.searchParams.set('hari', hari);
            } else {
                url.searchParams.delete('hari');
            }
            
            if (mapelId && mapelId !== 'all') {
                url.searchParams.set('mapel_id', mapelId);
            } else {
                url.searchParams.delete('mapel_id');
            }

            window.location.href = url.toString();
        }

        tahunAkademikFilter.addEventListener('change', applyFilters);
        hariFilter.addEventListener('change', applyFilters);
        mapelFilter.addEventListener('change', applyFilters);

        // Logika Dropdown User Info
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");

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
            }
        }
    </script>
</body>
</html>