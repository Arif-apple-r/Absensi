<?php
session_start();

// Pastikan hanya guru yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['guru_id']) || empty($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

$guru_id = $_SESSION['guru_id'];
$guru_name = htmlspecialchars($_SESSION['guru_name'] ?? 'Guru');
$guru_photo_session = htmlspecialchars($_SESSION['guru_photo'] ?? '');

require '../koneksi.php';

// Fungsi ambil tahun akademik aktif
function getActiveTahunAkademikId($pdo) {
    try {
        $stmt = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'] ?? null;
    } catch (PDOException $e) {
        error_log("Error getting active academic year: " . $e->getMessage());
        return null;
    }
}

// Ambil id_pertemuan
$id_pertemuan = filter_input(INPUT_GET, 'id_pertemuan', FILTER_SANITIZE_NUMBER_INT);
if (!$id_pertemuan) {
    header("Location: jadwal_guru.php?error=" . urlencode("ID Pertemuan tidak ditemukan."));
    exit;
}

$success = '';
$error   = '';
$can_edit = false;

// Cek apakah pertemuan berada di tahun akademik aktif
$active_tahun_akademik_id = getActiveTahunAkademikId($pdo);
try {
    $stmt_check = $pdo->prepare("
        SELECT c.id_tahun_akademik 
        FROM pertemuan p
        LEFT JOIN jadwal j ON p.id_jadwal = j.id
        LEFT JOIN class c ON j.class_id = c.id
        WHERE p.id = ?
    ");
    $stmt_check->execute([$id_pertemuan]);
    $pertemuan_tahun_id = $stmt_check->fetchColumn();
    if ($pertemuan_tahun_id == $active_tahun_akademik_id) {
        $can_edit = true;
    }
} catch (PDOException $e) {
    error_log("Error checking academic year: " . $e->getMessage());
}

// --- Handle Form Submission (Simpan Absensi) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_absensi'])) {
    if (!$can_edit) {
        $error = "Tidak dapat menyimpan absensi untuk tahun akademik yang tidak aktif.";
    } else {
        $id_pertemuan_form = filter_input(INPUT_POST, 'id_pertemuan', FILTER_SANITIZE_NUMBER_INT);
        $absensi_data      = $_POST['absensi'] ?? [];
        $keterangan_data   = $_POST['keterangan'] ?? [];

        if ($id_pertemuan_form && is_array($absensi_data)) {
            try {
                // Ambil id_jadwal & class_id untuk validasi siswa dan redirect
                $stmt_get = $pdo->prepare("
                    SELECT p.id_jadwal, j.class_id 
                    FROM pertemuan p 
                    JOIN jadwal j ON p.id_jadwal = j.id 
                    WHERE p.id = ?
                ");
                $stmt_get->execute([$id_pertemuan_form]);
                $details = $stmt_get->fetch(PDO::FETCH_ASSOC);
                $id_jadwal_for_redirect = $details['id_jadwal'] ?? null;
                $class_id_for_validation = $details['class_id'] ?? null;

                // Validasi akses guru
                $stmt_verify = $pdo->prepare("SELECT COUNT(*) FROM jadwal WHERE id = ? AND teacher_id = ?");
                $stmt_verify->execute([$id_jadwal_for_redirect, $guru_id]);
                if ($stmt_verify->fetchColumn() == 0) {
                    throw new Exception("Anda tidak memiliki izin untuk mengelola absensi ini.");
                }

                // Ambil daftar siswa valid di kelas ini
                $stmt_siswa = $pdo->prepare("SELECT id FROM siswa WHERE class_id = ?");
                $stmt_siswa->execute([$class_id_for_validation]);
                $valid_siswa_ids = $stmt_siswa->fetchAll(PDO::FETCH_COLUMN);

                $pdo->beginTransaction();

                foreach ($absensi_data as $siswa_id => $status) {
                    if (!in_array($siswa_id, $valid_siswa_ids)) {
                        continue; // skip invalid siswa
                    }
                    $keterangan = $keterangan_data[$siswa_id] ?? null;

                    // Insert atau update absensi
                    $stmt = $pdo->prepare("
                        INSERT INTO absensi (id_pertemuan, id_siswa, status, keterangan, waktu_input)
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                            status = VALUES(status),
                            keterangan = VALUES(keterangan),
                            waktu_input = NOW()
                    ");
                    $stmt->execute([$id_pertemuan_form, $siswa_id, $status, $keterangan]);
                }

                $pdo->commit();
                $success = "Absensi berhasil disimpan!";

                // Redirect kembali ke halaman pertemuan
                header("Location: pertemuan_guru.php?id_jadwal=" . urlencode($id_jadwal_for_redirect) 
                    . "&success=" . urlencode($success));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Gagal menyimpan absensi: " . $e->getMessage();
            }
        } else {
            $error = "Data absensi tidak lengkap.";
        }
    }
}

// --- Ambil data pertemuan untuk konteks ---
try {
    $stmt_info = $pdo->prepare("
        SELECT p.tanggal, p.topik, p.id_jadwal,
               j.hari, j.jam_mulai, j.jam_selesai, j.class_id,
               m.nama_mapel, c.nama_kelas
        FROM pertemuan p
        JOIN jadwal j ON p.id_jadwal = j.id
        JOIN mapel m ON j.id_mapel = m.id
        JOIN class c ON j.class_id = c.id
        WHERE p.id = ?
    ");
    $stmt_info->execute([$id_pertemuan]);
    $pertemuan_info = $stmt_info->fetch(PDO::FETCH_ASSOC);

    if (!$pertemuan_info) {
        header("Location: jadwal_guru.php?error=" . urlencode("Pertemuan tidak ditemukan."));
        exit;
    }
} catch (PDOException $e) {
    header("Location: jadwal_guru.php?error=" . urlencode("Kesalahan ambil data pertemuan."));
    exit;
}

$id_jadwal_current = $pertemuan_info['id_jadwal'];
$class_id_from_pertemuan = $pertemuan_info['class_id'];

// --- Ambil daftar siswa ---
try {
    $stmt_siswa = $pdo->prepare("SELECT id, NIS, name FROM siswa WHERE class_id = ? ORDER BY name ASC");
    $stmt_siswa->execute([$class_id_from_pertemuan]);
    $list_siswa = $stmt_siswa->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $list_siswa = [];
    $error = "Gagal memuat daftar siswa.";
}

// --- Ambil absensi existing ---
$absensi_existing = [];
try {
    $stmt_abs = $pdo->prepare("SELECT id_siswa, status, keterangan FROM absensi WHERE id_pertemuan = ?");
    $stmt_abs->execute([$id_pertemuan]);
    $rows = $stmt_abs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $absensi_existing[$row['id_siswa']] = [
            'status' => $row['status'],
            'keterangan' => $row['keterangan']
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching absensi: " . $e->getMessage());
}

// --- Tambahan: Hitung Ringkasan Kehadiran Berdasarkan Data Existing ---
$summary_kehadiran = [
    'Hadir' => 0,
    'Alpha' => 0,
    'Sakit' => 0,
    'Izin'  => 0,
    'Total_Siswa' => count($list_siswa)
];

foreach ($absensi_existing as $siswa_id => $data) {
    $status = $data['status'];
    if (isset($summary_kehadiran[$status])) {
        $summary_kehadiran[$status]++;
    }
}
// Tambahan: Hitung Siswa yang Belum Diisi Absensinya
$siswa_belum_diisi = $summary_kehadiran['Total_Siswa'] - array_sum($summary_kehadiran) + $summary_kehadiran['Total_Siswa'];
// Perbaikan: Hitung Siswa yang Belum Diisi Absensinya (Total Siswa dikurangi yang sudah diisi)
$siswa_sudah_diisi = count($absensi_existing);
$siswa_belum_diisi = $summary_kehadiran['Total_Siswa'] - $siswa_sudah_diisi;


$summary_kehadiran['Belum_Diisi'] = $siswa_belum_diisi;

// --- Akhir Tambahan Ringkasan Kehadiran ---

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
    <title>Isi Absensi Pertemuan</title>
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
                <i class="fas fa-calendar-alt" class=></i>
                <span>Jadwal Mengajar</span>
            </a>
            <a href="pertemuan_guru.php?id_jadwal=<?= htmlspecialchars($id_jadwal_current); ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>Pertemuan</span>
            </a>
            <a href="absensi_guru.php" class="active">
                <i class="fas fa-check-circle"></i>
                <span>Absensi</span>
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
        <h1><i class="fas fa-check-circle"></i> Isi Absensi</h1>
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

    <!-- Konten Utama -->
    <div class="content" id="mainContent">
        <div class="card">
            <h2>Absensi Pertemuan</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($pertemuan_info): ?>
                <div class="info-header">
                    <p><strong>Tanggal:</strong> <?php echo htmlspecialchars($pertemuan_info['tanggal']); ?></p>
                    <p><strong>Topik:</strong> <?php echo htmlspecialchars($pertemuan_info['topik']); ?></p>
                    <p><strong>Kelas:</strong> <?php echo htmlspecialchars($pertemuan_info['nama_kelas']); ?></p>
                    <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($pertemuan_info['nama_mapel']); ?></p>
                    <p><strong>Jadwal:</strong> <?php echo htmlspecialchars($pertemuan_info['hari']); ?>, <?php echo htmlspecialchars(substr($pertemuan_info['jam_mulai'], 0, 5)); ?> - <?php echo htmlspecialchars(substr($pertemuan_info['jam_selesai'], 0, 5)); ?></p>
                </div>
            <?php else: ?>
                <div class="alert alert-error">Data pertemuan tidak ditemukan. Pastikan Anda mengakses halaman ini dari pertemuan yang valid.</div>
            <?php endif; ?>

            <div class="summary-card-container">
                <div class="summary-card summary-hadir">
                    <h4><i class="fas fa-user-check"></i> Hadir</h4>
                    <p><?php echo htmlspecialchars($summary_kehadiran['Hadir']); ?></p>
                </div>
                <div class="summary-card summary-alpha">
                    <h4><i class="fas fa-user-times"></i> Alpha</h4>
                    <p><?php echo htmlspecialchars($summary_kehadiran['Alpha']); ?></p>
                </div>
                <div class="summary-card summary-sakit">
                    <h4><i class="fas fa-procedures"></i> Sakit</h4>
                    <p><?php echo htmlspecialchars($summary_kehadiran['Sakit']); ?></p>
                </div>
                <div class="summary-card summary-izin">
                    <h4><i class="fas fa-envelope-open-text"></i> Izin</h4>
                    <p><?php echo htmlspecialchars($summary_kehadiran['Izin']); ?></p>
                </div>
                <div class="summary-card summary-belum">
                    <h4><i class="fas fa-question-circle"></i> Belum Diisi</h4>
                    <p><?php echo htmlspecialchars($summary_kehadiran['Belum_Diisi']); ?></p>
                </div>
                <div class="summary-card">
                    <h4><i class="fas fa-users"></i> Total Siswa</h4>
                    <p><?php echo htmlspecialchars($summary_kehadiran['Total_Siswa']); ?></p>
                </div>
            </div>

            <?php if (!$can_edit): ?>
                <div class="alert alert-warning">
                    Tahun akademik ini <b>tidak aktif</b>. Anda hanya bisa melihat absensi tanpa mengubahnya.
                </div>
            <?php endif; ?>


            <form method="POST" autocomplete="off">
                <input type="hidden" name="id_pertemuan" value="<?php echo htmlspecialchars($id_pertemuan); ?>">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Status Absensi</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($list_siswa)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">Tidak ada siswa di kelas ini.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($list_siswa as $siswa): ?>
                                    <?php
                                    // Dapatkan status dan keterangan yang sudah ada untuk siswa ini
                                    $current_status = $absensi_existing[$siswa['id']]['status'] ?? 'Hadir';
                                    $current_keterangan = $absensi_existing[$siswa['id']]['keterangan'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($siswa['NIS']); ?></td>
                                        <td><?php echo htmlspecialchars($siswa['name']); ?></td>
                                        <td>
                                            <div class="absensi-status-options">
                                                <label>
                                                    <input type="radio"
                                                        name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                        value="Hadir"
                                                        onchange="toggleKeterangan(this)"
                                                        <?php echo ($current_status == 'Hadir') ? 'checked' : ''; ?>
                                                        <?php echo !$can_edit ? 'disabled' : ''; ?>> Hadir
                                                </label>

                                                <label>
                                                    <input type="radio"
                                                        name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                        value="Alpha"
                                                        onchange="toggleKeterangan(this)"
                                                        <?php echo ($current_status == 'Alpha') ? 'checked' : ''; ?>
                                                        <?php echo !$can_edit ? 'disabled' : ''; ?>> Alpha
                                                </label>
                                                <label>
                                                    <input type="radio"
                                                        name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                        value="Sakit"
                                                        onchange="toggleKeterangan(this)"
                                                        <?php echo ($current_status == 'Sakit') ? 'checked' : ''; ?>
                                                        <?php echo !$can_edit ? 'disabled' : ''; ?>> Sakit
                                                </label>
                                                <label>
                                                    <input type="radio"
                                                        name="absensi[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                        value="Izin"
                                                        onchange="toggleKeterangan(this)"
                                                        <?php echo ($current_status == 'Izin') ? 'checked' : ''; ?>
                                                        <?php echo !$can_edit ? 'disabled' : ''; ?>> Izin
                                                </label>
                                            </div>
                                        </td>
                                        <td class="absensi-keterangan">
                                            <textarea name="keterangan[<?php echo htmlspecialchars($siswa['id']); ?>]"
                                                placeholder="Tambahkan keterangan (opsional)"
                                                <?php echo (!($current_status == 'Sakit' || $current_status == 'Izin') || !$can_edit) ? 'disabled' : ''; ?>
                                                ><?php echo htmlspecialchars($current_keterangan); ?>
                                            </textarea>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($can_edit): ?>
                    <button type="submit" name="submit_absensi" class="save-absensi-btn">
                        <i class="fas fa-save"></i> Simpan Absensi
                    </button>
                <?php else: ?>
                    <button type="button" class="save-absensi-btn disabled" disabled>
                        <i style="margin-right: 12px;" class="fas fa-lock"></i>Absensi Tidak Bisa Disimpan
                    </button>
                <?php endif; ?>
            </form>

            <a href="pertemuan_guru.php?id_jadwal=<?php echo htmlspecialchars($id_jadwal_current); ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Pertemuan
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



        // Fungsi untuk mengaktifkan/menonaktifkan textarea keterangan
        function toggleKeterangan(radioElement) {
            const keteranganCell = radioElement.closest('td').nextElementSibling; // Sel sebelahnya (keterangan)
            const keteranganTextarea = keteranganCell.querySelector('textarea');

            if (radioElement.value === 'Sakit' || radioElement.value === 'Izin') {
                keteranganTextarea.disabled = false;
            } else {
                keteranganTextarea.disabled = true;
                keteranganTextarea.value = ''; // Opsional: kosongkan saat dinonaktifkan
            }
        }

        // Jalankan saat halaman dimuat untuk mengatur status awal textarea

        // Jalankan saat halaman dimuat
    </script>
</body>

</html>
