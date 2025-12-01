<?php
session_start();
// Aktifkan reporting error untuk debugging. Pastikan ini selalu ada.
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../login.php");
    exit;
}
require '../../koneksi.php';

$superadmin_name = htmlspecialchars($_SESSION['superadmin_name'] ?? 'superadmin');
$superadmin_photo = 'https://placehold.co/40x40/cccccc/333333?text=SA';

$message = '';
$alert_type = '';

// filter section nih
// Ambil daftar Tahun Akademik untuk filter dan dropdown form
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun, is_active FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

$selected_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? null;

if ($selected_tahun_akademik_id === null) {
    $stmt_active_tahun = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
    $active_tahun = $stmt_active_tahun->fetch(PDO::FETCH_ASSOC);
    // Set ID ke tahun aktif, atau jika tidak ada, ambil tahun pertama dari list
    $selected_tahun_akademik_id = $active_tahun['id'] ?? ($tahun_akademik_options[0]['id'] ?? null);
} else {
    // Pastikan tipe data integer
    $selected_tahun_akademik_id = (int)$selected_tahun_akademik_id;
}

// Ambil daftar KELAS untuk filter dan dropdown form
// [FIX] Ambil daftar KELAS hanya untuk Tahun Akademik yang dipilih
$kelas_options = [];
if ($selected_tahun_akademik_id) {
    // Kita filter berdasarkan id_tahun_akademik
    $stmt_kelas = $pdo->prepare("SELECT id, nama_kelas FROM class WHERE id_tahun_akademik = ? ORDER BY nama_kelas ASC");
    $stmt_kelas->execute([$selected_tahun_akademik_id]);
    $kelas_options = $stmt_kelas->fetchAll(PDO::FETCH_ASSOC);
} 

else {
    // Opsional: Jika user memilih "Semua Tahun", mungkin kita kosongkan kelas 
    // atau tampilkan semua dengan info tahunnya agar tidak bingung
    // Untuk keamanan, kita kosongkan saja agar user memilih tahun dulu
    $kelas_options = []; 
}

// Ambil parameter filter kelas dari URL
$selected_kelas_id = isset($_GET['kelas_id']) ? (int)$_GET['kelas_id'] : null;
// ----------------------------------------- //

if ($selected_tahun_akademik_id === null) {
    $stmt_active_tahun = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
    $active_tahun = $stmt_active_tahun->fetch(PDO::FETCH_ASSOC);
    $selected_tahun_akademik_id = $active_tahun['id'] ?? ($tahun_akademik_options[0]['id'] ?? null);
}

if ($selected_tahun_akademik_id === null) {
    $message = "Tidak ada Tahun Akademik yang ditemukan atau diatur aktif.";
    $alert_type = 'alert-error';
    $selected_tahun_akademik_id = 0;
} else {
    $selected_tahun_akademik_id = (int)$selected_tahun_akademik_id;
}


// Ambil data untuk dropdown kelas di form (difilter berdasarkan Tahun Akademik yang dipilih)
$kelas_form_options = [];
if ($selected_tahun_akademik_id) {
    $stmt_kelas_form = $pdo->prepare("SELECT id, nama_kelas FROM class WHERE id_tahun_akademik = ? ORDER BY nama_kelas ASC");
    $stmt_kelas_form->execute([$selected_tahun_akademik_id]);
    $kelas_form_options = $stmt_kelas_form->fetchAll(PDO::FETCH_ASSOC);
}


// --- Handle AJAX POST untuk menambah atau mengedit siswa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'promote_siswa') {
    $response_status = 'error';
    $response_message = 'Terjadi kesalahan tidak dikenal.';

    try {
        $NIS_baru      = $_POST['NISsiswa'] ?? null;
        $NIS_lama_for_update = $_POST['NIS_lama_for_update'] ?? null;
        $name     = $_POST['namasiswa'] ?? '';
        $email    = $_POST['emailsiswa'] ?? '';
        $gender   = $_POST['gender'] ?? '';
        $dob_raw  = $_POST['dobsiswa'] ?? '';
        $alamat   = $_POST['alamatsiswa'] ?? '';
        $class_id = $_POST['class_id'] ?? null;
        $password = $_POST['passwordsiswa'] ?? null;

        $dob = !empty($dob_raw) ? $dob_raw : null;

        $no_hp_raw = $_POST['nohpsiswa'] ?? '';
        $no_hp = null;
        if (is_numeric($no_hp_raw) && $no_hp_raw !== '') {
            $no_hp = (int)$no_hp_raw;
            if ($no_hp < 0 || $no_hp > 4294967295) {
                throw new Exception("Nomor HP terlalu besar atau negatif untuk disimpan.");
            }
        } else if (!empty($no_hp_raw)) {
            throw new Exception("Nomor HP harus berupa angka.");
        }

        $foto_path_db = null;
        $folder_upload = "../../uploads/siswa/";
        $upload_succeeded = true;

        if (!is_dir($folder_upload)) {
            mkdir($folder_upload, 0777, true);
        }

        if (isset($_FILES['photosiswa']) && $_FILES['photosiswa']['error'] === UPLOAD_ERR_OK) {
            $foto_tmp = $_FILES['photosiswa']['tmp_name'];
            $foto_name = $_FILES['photosiswa']['name'];
            $ext = pathinfo($foto_name, PATHINFO_EXTENSION);
            $nama_foto_baru = uniqid() . '.' . $ext;
            $dest_path = $folder_upload . $nama_foto_baru;

            if (move_uploaded_file($foto_tmp, $dest_path)) {
                $foto_path_db = $nama_foto_baru;
            } else {
                throw new Exception("Gagal mengunggah foto siswa. Coba lagi atau pastikan folder 'uploads/siswa/' dapat ditulis.");
            }
        } else if (isset($_POST['old_photosiswa']) && !empty($_POST['old_photosiswa'])) {
            $foto_path_db = $_POST['old_photosiswa'];
        }

        if ($upload_succeeded) {
            if ($_POST['action'] === 'tambah') {
                if ($NIS_baru && $name && $email && $gender && $class_id && $password && $dob) {
                    try {
                        $stmt_check_nis = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE NIS = ?");
                        $stmt_check_nis->execute([$NIS_baru]);
                        if ($stmt_check_nis->fetchColumn() > 0) {
                            $response_message = "Gagal menambahkan siswa: NIS sudah terdaftar.";
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $admission_date = date('Y-m-d H:i:s');

                            $stmt = $pdo->prepare("INSERT INTO siswa (NIS, name, email, gender, dob, no_hp, alamat, class_id, photo, pass, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$NIS_baru, $name, $email, $gender, $dob, $no_hp, $alamat, $class_id, $foto_path_db, $hashed_password, $admission_date]);
                            $response_status = 'success';
                            $response_message = "Siswa berhasil ditambahkan!";
                        }
                    } catch (PDOException $e) {
                        $response_message = "Gagal menambahkan siswa (DB Error): " . $e->getMessage();
                    }
                } else {
                    $response_message = "Mohon lengkapi semua field yang diperlukan (NIS, Nama, Email, Gender, Tanggal Lahir, Kelas, Password) untuk menambah siswa.";
                }
            } elseif ($_POST['action'] === 'edit') {
                if ($NIS_lama_for_update && $NIS_baru && $name && $email && $gender && $dob && $alamat && $class_id) {
                    try {
                        if ($NIS_baru !== $NIS_lama_for_update) {
                            $stmt_check_nis_exist = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE NIS = ? AND NIS != ?");
                            $stmt_check_nis_exist->execute([$NIS_baru, $NIS_lama_for_update]);
                            if ($stmt_check_nis_exist->fetchColumn() > 0) {
                                $response_message = "Gagal mengupdate siswa: NIS baru sudah terdaftar untuk siswa lain.";
                                throw new Exception($response_message);
                            }
                        }

                        if ($foto_path_db && isset($_POST['old_photosiswa']) && $_POST['old_photosiswa'] !== $foto_path_db && file_exists($folder_upload . $_POST['old_photosiswa'])) {
                            unlink($folder_upload . $_POST['old_photosiswa']);
                        }

                        $update_pass_sql = '';
                        $update_pass_params = [];
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $update_pass_sql = ', pass = ?';
                            $update_pass_params = [$hashed_password];
                        }

                        $stmt = $pdo->prepare("UPDATE siswa SET NIS = ?, name = ?, email = ?, gender = ?, dob = ?, no_hp = ?, alamat = ?, class_id = ?, photo = ? " . $update_pass_sql . " WHERE NIS = ?");
                        $stmt->execute(array_merge([$NIS_baru, $name, $email, $gender, $dob, $no_hp, $alamat, $class_id, $foto_path_db], $update_pass_params, [$NIS_lama_for_update]));

                        $response_status = 'success';
                        $response_message = "Siswa berhasil diupdate!";
                    } catch (PDOException $e) {
                        $response_message = "Gagal mengupdate siswa (DB Error): " . $e->getMessage();
                    } catch (Exception $e) {
                        $response_message = $e->getMessage();
                    }
                } else {
                    $response_message = "Mohon lengkapi semua field yang diperlukan untuk mengupdate siswa. (NIS, Nama, Email, Gender, Tanggal Lahir, Alamat, Kelas)";
                }
            }
        }
    } catch (Throwable $e) {
        $response_message = "Kesalahan fatal di server: " . $e->getMessage() . " (Line: " . $e->getLine() . " in " . basename($e->getFile()) . ")";
        error_log("Fatal error in siswa AJAX POST: " . $e->getMessage() . " on line " . $e->getLine() . " in file " . $e->getFile() . "\n" . $e->getTraceAsString());
    }

    if ($response_status === 'success') {
        echo "success";
    } else {
        echo "error: " . $response_message;
    }
    exit;
}
// --- END Handle AJAX POST untuk menambah atau mengedit siswa ---


// --- Handle AJAX POST untuk promosi siswa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'promote_siswa') {
    $response_status = 'error';
    $response_message = 'Terjadi kesalahan saat promosi siswa.';

    try {
        $id_tahun_akademik_asal = $_POST['id_tahun_akademik_asal'] ?? null;
        $id_kelas_asal = $_POST['id_kelas_asal'] ?? null;
        $id_tahun_akademik_tujuan = $_POST['id_tahun_akademik_tujuan'] ?? null;
        $id_kelas_tujuan = $_POST['id_kelas_tujuan'] ?? null;
        $stmt_valid = $pdo->prepare("
            SELECT COUNT(*) FROM class 
            WHERE id = ? AND id_tahun_akademik = ?
        ");
        $stmt_valid->execute([$id_kelas_tujuan, $id_tahun_akademik_tujuan]);

        if ($stmt_valid->fetchColumn() == 0) {
            throw new Exception("Kelas tujuan tidak berada dalam Tahun Akademik tujuan.");
        }

        // Validasi input
        if (empty($id_tahun_akademik_asal) || empty($id_kelas_asal) || empty($id_tahun_akademik_tujuan) || empty($id_kelas_tujuan)) {
            throw new Exception("Semua field promosi harus diisi.");
        }
        if ($id_tahun_akademik_asal == $id_tahun_akademik_tujuan) {
            throw new Exception("Tahun Akademik Asal dan Tujuan harus berbeda untuk promosi.");
        }
        // Tambahan validasi: cek apakah tahun tujuan setelah tahun asal (opsional tapi bagus)
        $stmt_check_tahun = $pdo->prepare("SELECT ta1.nama_tahun AS asal, ta2.nama_tahun AS tujuan FROM tahun_akademik ta1 JOIN tahun_akademik ta2 ON ta1.id = ? AND ta2.id = ?");
        $stmt_check_tahun->execute([$id_tahun_akademik_asal, $id_tahun_akademik_tujuan]);
        $tahun_data = $stmt_check_tahun->fetch(PDO::FETCH_ASSOC);

        if (!$tahun_data || ($tahun_data['asal'] >= $tahun_data['tujuan'])) { // Asumsi nama_tahun '2024/2025' > '2023/2024'
            throw new Exception("Tahun Akademik Tujuan harus lebih baru dari Tahun Akademik Asal.");
        }


        // Ambil ID siswa dari kelas asal
        $stmt_siswa_asal = $pdo->prepare("SELECT id FROM siswa WHERE class_id = ?");
        $stmt_siswa_asal->execute([$id_kelas_asal]);
        $siswa_ids = $stmt_siswa_asal->fetchAll(PDO::FETCH_COLUMN);

        if (empty($siswa_ids)) {
            throw new Exception("Tidak ada siswa di kelas asal yang dipilih.");
        }

        // Perbarui class_id untuk siswa yang dipromosikan
        $placeholders = implode(',', array_fill(0, count($siswa_ids), '?'));
        $stmt_update_siswa = $pdo->prepare("UPDATE siswa SET class_id = ? WHERE id IN ($placeholders)");
        $stmt_update_siswa->execute(array_merge([$id_kelas_tujuan], $siswa_ids));

        $response_status = 'success';
        $response_message = count($siswa_ids) . " siswa berhasil dipromosikan!";
    } catch (Throwable $e) {
        $response_message = $e->getMessage();
        error_log("Error Promosi Siswa: " . $e->getMessage() . " on line " . $e->getLine() . " in file " . $e->getFile());
    }

    if ($response_status === 'success') {
        echo "success: " . $response_message;
    } else {
        echo "error: " . $response_message;
    }
    exit;
}
// --- END Handle AJAX POST untuk promosi siswa ---


// --- Handle GET requests (delete siswa) ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus_siswa' && isset($_GET['NIS'])) {
    $nis_to_delete = $_GET['NIS'];
    $current_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? $selected_tahun_akademik_id;

    try {
        $stmt_get_siswa_id = $pdo->prepare("SELECT id FROM siswa WHERE NIS = ?");
        $stmt_get_siswa_id->execute([$nis_to_delete]);
        $siswa_id = $stmt_get_siswa_id->fetchColumn();

        if ($siswa_id) {
            $stmt_delete_absensi = $pdo->prepare("DELETE FROM absensi WHERE id_siswa = ?");
            $stmt_delete_absensi->execute([$siswa_id]);

            $stmt_get_photo = $pdo->prepare("SELECT photo FROM siswa WHERE NIS = ?");
            $stmt_get_photo->execute([$nis_to_delete]);
            $siswa_data = $stmt_get_photo->fetch(PDO::FETCH_ASSOC);
            $foto_to_delete = $siswa_data['photo'] ?? '';

            $stmt = $pdo->prepare("DELETE FROM siswa WHERE NIS = ?");
            $stmt->execute([$nis_to_delete]);

            $folder_upload = "../../uploads/siswa/";
            if (!empty($foto_to_delete) && $foto_to_delete != 'default.jpg' && file_exists($folder_upload . $foto_to_delete)) {
                unlink($folder_upload . $foto_to_delete);
            }

            $message = "Siswa berhasil dihapus!";
            $alert_type = 'alert-success';
        } else {
            $message = "Siswa dengan NIS tersebut tidak ditemukan.";
            $alert_type = 'alert-error';
        }

        header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    } catch (PDOException $e) {
        $message = "Gagal menghapus siswa: " . $e->getMessage();
        $alert_type = 'alert-error';
        header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    }
}


// --- Ambil daftar Siswa untuk ditampilkan (difilter berdasarkan selected_tahun_akademik_id) ---
$query_siswa = "
    SELECT 
        s.*, 
        c.nama_kelas, 
        ta.nama_tahun 
    FROM siswa AS s
    JOIN class AS c ON s.class_id = c.id
    JOIN tahun_akademik AS ta ON c.id_tahun_akademik = ta.id
";
$filters = [];
$params = [];

if ($selected_tahun_akademik_id) {
    $filters[] = "ta.id = ?";
    $params[] = $selected_tahun_akademik_id;
}

if ($selected_kelas_id) {
    $filters[] = "c.id = ?";
    $params[] = $selected_kelas_id;
}

if (!empty($filters)) {
    $query_siswa .= " WHERE " . implode(" AND ", $filters);
}
$query_siswa .= " ORDER BY c.nama_kelas ASC, s.name ASC";

$stmt_siswa = $pdo->prepare($query_siswa);
$stmt_siswa->execute($params);
$siswa_list = $stmt_siswa->fetchAll(PDO::FETCH_ASSOC);


// Ambil pesan dari URL jika ada
if (isset($_GET['success'])) {
    $message = htmlspecialchars($_GET['success']);
    $alert_type = 'alert-success';
} elseif (isset($_GET['error'])) {
    $message = htmlspecialchars($_GET['error']);
    $alert_type = 'alert-error';
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Siswa | superadmin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/adminpage.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.7.1.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/2.0.7/js/dataTables.js"></script>
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../../uploads/icon/logo.png" alt="Logo SuperAdminCoy" class="logo-icon">
            <span class="logo-text">SuperAdmin</span>
        </div>
        <nav>
            <a href="../dashboard_superadmin.php">
                <div class="hovertext" data-hover="Dashboard"><i class="fas fa-tachometer-alt"></i></div>
                <span>Dashboard</span>
            </a>
            <a href="../admin/index.php">
                <div class="hovertext" data-hover="Admin"><i class="fas fa-users-cog"></div></i><span>Admin</span></a>
            <a href="../guru/index.php">
                <div class="hovertext" data-hover="Guru"><i class="fas fa-chalkboard-teacher"></i></div>
                <span>Guru</span>
            </a>
            <a href="index.php">
                <div class="hovertext" data-hover="Siswa"><i class="fas fa-user-graduate"></i></div>
                <span>Siswa</span>
            </a>
            <a href="../jadwal/index.php">
                <div class="hovertext" data-hover="Jadwal"><i class="fas fa-calendar-alt"></i></div>
                <span>Jadwal</span>
            </a>
            <a href="../Tahun_Akademik/index.php">
                <div class="hovertext" data-hover="Tahun Akademik"><i class="fas fa-calendar"></i></div>
                <span>Tahun Akademik</span>
            </a>
            <a href="../kelas/index.php">
                <div class="hovertext" data-hover="Kelas"><i class="fas fa-school"></i></div>
                <span>Kelas</span>
            </a>
            <a href="../mapel/index.php">
                <div class="hovertext" data-hover="Mata Pelajaran"><i class="fas fa-book"></i></div>
                <span>Mata Pelajaran</span>
            </a>
        </nav>
        <div class="logout-button-container hovertext" data-hover="Logout">
            <a onclick="showLogoutConfirmation()" id="logoutButtonSidebar">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>


    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-user-graduate"></i> Manajemen Siswa</h1>
        <div class="user-info" id="userInfoDropdown">
            <span><?= $superadmin_name ?></span>
            <div class="dropdown-menu" id="userDropdownContent">
                <!-- <a href="profil_superadmin.php"><i class="fas fa-user-circle"></i> Profil</a> -->
                <a onclick="showLogoutConfirmation()" id="logoutButtonSidebar">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Siswa</h2>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $alert_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="GET" action="index.php">
                <div class="filter-section">
                    <div class="filter-group">
                        <label for="filter_tahun_akademik">Tahun Akademik:</label>
                        <select id="filter_tahun_akademik" name="tahun_akademik_id">
                            <option value="0">Semua Tahun Akademik</option> <!-- Value 0 for "All" -->
                            <?php if (empty($tahun_akademik_options)): ?>
                                <option value="" disabled>Tidak ada Tahun Akademik</option>
                            <?php else: ?>
                                <?php foreach ($tahun_akademik_options as $ta_option): ?>
                                    <option value="<?php echo htmlspecialchars($ta_option['id']); ?>"
                                        <?php echo ($ta_option['id'] == $selected_tahun_akademik_id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ta_option['nama_tahun']); ?>
                                        <?php echo ($ta_option['is_active']) ? ' (Aktif)' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="kelas_filter">Kelas</label>
                        <select id="kelas_filter" name="kelas_id">
                            <option value="all">Semua Kelas</option>
                            
                            <?php if (empty($kelas_options) && $selected_tahun_akademik_id == 0): ?>
                                <option value="" disabled>Pilih Tahun Akademik Terlebih Dahulu</option>
                            <?php else: ?>
                                <?php foreach ($kelas_options as $kelas): ?>
                                    <option value="<?= htmlspecialchars($kelas['id']) ?>"
                                        <?= $selected_kelas_id == $kelas['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                        </select>
                    </div>
                    <div class="filter-group" style="flex-grow: 0;">
                        <button type="submit">Terapkan Filter</button>
                    </div>
                </div>
            </form>

            <a href="#" class="add-link" onclick="openSiswaModal('tambah'); return false;">
                <i class="fas fa-plus-circle"></i> Tambah Siswa
            </a>
            <a href="#" class="promote-link" onclick="openPromoteSiswaModal(); return false;">
                <i class="fas fa-level-up-alt"></i> Promosikan Siswa
            </a>

            <div class="table-responsive">
                <?php if (!empty($siswa_list)): ?>
                    <table id="myTable" class="data-table">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Foto</th>
                                <th>Nama</th>
                                <th>Email</th>
                                <th>No. HP</th>
                                <th>Kelas</th>
                                <th>Tahun Akademik</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswa_list as $siswa): // [B] Jika data ADA, lakukan loop ?>
                                <tr data-nis="<?= htmlspecialchars($siswa['NIS']) ?>">
                                    <td><?php echo htmlspecialchars($siswa['NIS']); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars('../../uploads/siswa/' . ($siswa['photo'] ?? 'default.jpg')); ?>" 
                                            alt="Foto Siswa" 
                                            style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;"
                                            loading="lazy"
                                            onerror="this.onerror=null;this.src='https://placehold.co/50x50/cccccc/333333?text=NO+IMG';">
                                    </td>
                                    <td><?php echo htmlspecialchars($siswa['name']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['email']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['no_hp'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_kelas']); ?></td>
                                    <td><?php echo htmlspecialchars($siswa['nama_tahun']); ?></td>
                                    <td>
                                        <a href="#" class="action-link edit" onclick="openSiswaModal('edit', 
                                                '<?php echo htmlspecialchars($siswa['NIS']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['name']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['email']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['gender']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['dob']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['no_hp'] ?? ''); ?>', 
                                                '<?php echo htmlspecialchars($siswa['alamat'] ?? ''); ?>', 
                                                '<?php echo htmlspecialchars($siswa['class_id']); ?>', 
                                                '<?php echo htmlspecialchars($siswa['photo'] ?? ''); ?>'
                                            ); return false;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="action-link delete" onclick="openDeleteModal('<?php echo htmlspecialchars($siswa['NIS']); ?>'); return false;">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: // Tampilkan pesan alert jika array data KOSONG ?>
                    <div class="alert alert-info" style="display: flex; align-items: center; gap: 10px; background-color: #e0f7fa; color: #006064; border-left: 5px solid #00acc1;">
                        <i class="fas fa-info-circle fa-lg"></i>
                        <strong>Informasi:</strong> Tidak ada data siswa yang ditemukan untuk tahun akademik yang dipilih.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Tambah/Edit Siswa -->
    <div id="siswaModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeSiswaModal()">&times;</span>
            <h2 id="siswaModalTitle">Tambah Siswa</h2>
            <form id="siswaForm" method="POST" action="index.php" enctype="multipart/form-data">
                <input type="hidden" name="action" id="siswaAction">
                <input type="hidden" name="NIS_lama_for_update" id="siswaNISHidden"> <!-- NIS lama untuk UPDATE -->
                <input type="hidden" name="old_photosiswa" id="siswaOldPhotoHidden">
                <!-- Tidak perlu tahun_akademik_id di sini, karena class_id sudah cukup -->

                <div class="form-group">
                    <label for="NISsiswa">NIS:</label>
                    <input type="text" id="NISsiswa" name="NISsiswa" required>
                </div>
                <div class="form-group">
                    <label for="namasiswa">Nama:</label>
                    <input type="text" id="namasiswa" name="namasiswa" required>
                </div>
                <div class="form-group">
                    <label for="emailsiswa">Email:</label>
                    <input type="email" id="emailsiswa" name="emailsiswa" required>
                </div>
                <div class="form-group" id="passwordGroup">
                    <label for="passwordsiswa">Password:</label>
                    <input type="password" id="passwordsiswa" name="passwordsiswa" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label>Gender:</label>
                    <div class="radio-group">
                        <input type="radio" id="genderL" name="gender" value="laki-laki" required>
                        <label for="genderL">Laki-laki</label>
                        <input type="radio" id="genderP" name="gender" value="perempuan">
                        <label for="genderP">Perempuan</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="dobsiswa">Tanggal Lahir:</label>
                    <input type="date" id="dobsiswa" name="dobsiswa" required>
                </div>
                <div class="form-group">
                    <label for="nohpsiswa">No. HP:</label>
                    <input type="tel" id="nohpsiswa" name="nohpsiswa">
                </div>
                <div class="form-group">
                    <label for="alamatsiswa">Alamat:</label>
                    <textarea id="alamatsiswa" name="alamatsiswa" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="class_id_modal">Kelas:</label>
                    <select id="class_id_modal" name="class_id" required>
                        <option value="">Pilih Kelas</option>
                        <?php if (empty($kelas_form_options)): ?>
                            <option value="" disabled>Tidak ada kelas untuk tahun akademik ini.</option>
                        <?php else: ?>
                            <?php foreach ($kelas_form_options as $k): ?>
                                <option value="<?= htmlspecialchars($k['id']) ?>">
                                    <?= htmlspecialchars($k['nama_kelas']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="photosiswa">Foto Siswa (Opsional):</label>
                    <input type="file" id="photosiswa" name="photosiswa" accept="image/*">
                    <div class="photo-upload">
                        <img id="photosiswa_preview" class="photo-preview" src="https://placehold.co/100x100/cccccc/333333?text=NO+IMG" alt="Preview Foto">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitSiswaBtn">Simpan</button>
                    <button type="button" class="btn-secondary" onclick="closeSiswaModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Promosi Siswa -->
    <div id="promoteSiswaModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closePromoteSiswaModal()">&times;</span>
            <h2>Promosikan Siswa ke Kelas Baru</h2>
            <form id="promoteSiswaForm" method="POST" action="index.php">
                <input type="hidden" name="action" value="promote_siswa">

                <div class="form-group">
                    <label for="id_tahun_akademik_asal">Tahun Akademik Asal:</label>
                    <select id="id_tahun_akademik_asal" name="id_tahun_akademik_asal" required>
                        <option value="">Pilih Tahun Akademik Asal</option>
                        <?php foreach ($tahun_akademik_options as $ta_option): ?>
                            <option value="<?php echo htmlspecialchars($ta_option['id']); ?>">
                                <?php echo htmlspecialchars($ta_option['nama_tahun']); ?>
                                <?php echo ($ta_option['is_active']) ? ' (Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="id_kelas_asal">Kelas Asal:</label>
                    <select id="id_kelas_asal" name="id_kelas_asal" required>
                        <option value="">Pilih Kelas Asal</option>
                        <!-- Options will be loaded via JavaScript -->
                    </select>
                </div>

                <hr style="margin: 20px 0; border: 0; border-top: 1px solid var(--border-color);">

                <div class="form-group">
                    <label for="id_tahun_akademik_tujuan">Tahun Akademik Tujuan:</label>
                    <select id="id_tahun_akademik_tujuan" name="id_tahun_akademik_tujuan" required>
                        <option value="">Pilih Tahun Akademik Tujuan</option>
                        <?php foreach ($tahun_akademik_options as $ta_option): ?>
                            <option value="<?php echo htmlspecialchars($ta_option['id']); ?>">
                                <?php echo htmlspecialchars($ta_option['nama_tahun']); ?>
                                <?php echo ($ta_option['is_active']) ? ' (Aktif)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="id_kelas_tujuan">Kelas Tujuan:</label>
                    <select id="id_kelas_tujuan" name="id_kelas_tujuan" required>
                        <option value="">Pilih Kelas Tujuan</option>
                        <!-- Options will be loaded via JavaScript -->
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Promosikan</button>
                    <button type="button" class="btn-secondary" onclick="closePromoteSiswaModal()">Batal</button>
                </div>
            </form>
        </div>
    </div>


    <!-- Script JavaScript -->
    <script>
        let isEditMode = false; // Deklarasi global

        const siswaModal = document.getElementById("siswaModal");
        const siswaModalTitle = document.getElementById("siswaModalTitle");
        const siswaForm = document.getElementById("siswaForm");
        const siswaActionInput = document.getElementById("siswaAction");
        const siswaNISHiddenInput = document.getElementById("siswaNISHidden");
        const siswaOldPhotoHiddenInput = document.getElementById("siswaOldPhotoHidden");
        const NISsiswaInput = document.getElementById("NISsiswa");
        const namasiswaInput = document.getElementById("namasiswa");
        const emailsiswaInput = document.getElementById("emailsiswa");
        const passwordGroup = document.getElementById("passwordGroup");
        const passwordsiswaInput = document.getElementById("passwordsiswa");
        const genderLInput = document.getElementById("genderL");
        const genderPInput = document.getElementById("genderP");
        const dobsiswaInput = document.getElementById("dobsiswa");
        const nohpsiswaInput = document.getElementById("nohpsiswa");
        const alamatsiswaTextarea = document.getElementById("alamatsiswa");
        const classIdModalSelect = document.getElementById("class_id_modal");
        const photosiswaInput = document.getElementById("photosiswa");
        const photosiswaPreview = document.getElementById("photosiswa_preview");
        const submitSiswaBtn = document.getElementById("submitSiswaBtn");
        const filterTahunAkademik = document.getElementById("filter_tahun_akademik");

        // Variabel untuk modal promosi siswa
        const promoteSiswaModal = document.getElementById("promoteSiswaModal");
        const promoteSiswaForm = document.getElementById("promoteSiswaForm");
        const idTahunAkademikAsalSelect = document.getElementById("id_tahun_akademik_asal");
        const idKelasAsalSelect = document.getElementById("id_kelas_asal");
        const idTahunAkademikTujuanSelect = document.getElementById("id_tahun_akademik_tujuan");
        const idKelasTujuanSelect = document.getElementById("id_kelas_tujuan");

        function openSiswaModal(action, NIS = '', name = '', email = '', gender = '', dob = '', no_hp = '', alamat = '', class_id = '', photo = '') {
            siswaForm.reset();
            siswaActionInput.value = action;
            isEditMode = (action === 'edit');

            if (action === 'tambah') {
                siswaModalTitle.textContent = "Tambah Siswa";
                submitSiswaBtn.textContent = "Simpan";
                NISsiswaInput.readOnly = false;
                NISsiswaInput.value = '';
                siswaNISHiddenInput.value = '';
                siswaOldPhotoHiddenInput.value = '';
                photosiswaPreview.src = "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";
                classIdModalSelect.value = '';

                passwordGroup.style.display = 'block';
                passwordsiswaInput.required = true;
                dobsiswaInput.required = true;
            } else if (action === 'edit') {
                siswaModalTitle.textContent = "Edit Siswa";
                submitSiswaBtn.textContent = "Update";
                NISsiswaInput.readOnly = false;

                NISsiswaInput.value = NIS;
                siswaNISHiddenInput.value = NIS;
                namasiswaInput.value = name;
                emailsiswaInput.value = email;

                if (gender && gender.toLowerCase() === 'laki-laki') {
                    genderLInput.checked = true;
                } else if (gender && gender.toLowerCase() === 'perempuan') {
                    genderPInput.checked = true;
                }

                dobsiswaInput.value = dob;
                nohpsiswaInput.value = no_hp;
                alamatsiswaTextarea.value = alamat;
                classIdModalSelect.value = class_id;
                siswaOldPhotoHiddenInput.value = photo;
                photosiswaPreview.src = photo ? `../../uploads/siswa/${photo}` : "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";

                passwordGroup.style.display = 'none';
                passwordsiswaInput.required = false;
                passwordsiswaInput.value = '';
                dobsiswaInput.required = true;
            }
            siswaModal.style.display = "flex";
        }

        function closeSiswaModal() {
            siswaModal.style.display = "none";
            const successAlert = document.querySelector('.alert-success');
            const errorAlert = document.querySelector('.alert-error');
            if (successAlert) successAlert.style.display = 'none';
            if (errorAlert) errorAlert.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == siswaModal) {
                closeSiswaModal();
            }
            if (event.target == promoteSiswaModal) { // Close promote modal too
                closePromoteSiswaModal();
            }
        };

        photosiswaInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photosiswaPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            } else {
                photosiswaPreview.src = "https://placehold.co/100x100/cccccc/333333?text=NO+IMG";
            }
        });


        siswaForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const formData = new FormData(siswaForm);

            fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(async result => {
                    const actionMode = siswaActionInput.value;

                    if (result.trim().startsWith("success")) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: result.trim().substring(7) || (actionMode === 'edit' ? "Siswa berhasil diupdate!" : "Siswa berhasil ditambahkan!"),
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            const currentTahunAkademikId = filterTahunAkademik.value;
                            window.location.href = `index.php?success=${encodeURIComponent(result.trim().substring(7) || (actionMode === 'edit' ? "Siswa berhasil diupdate!" : "Siswa berhasil ditambahkan!"))}&tahun_akademik_id=${currentTahunAkademikId}`;
                        });
                    } else if (result.trim().startsWith("error:")) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: result.trim().substring(6),
                            confirmButtonText: 'OK'
                        });
                    } else {
                        console.error("Server responded with unexpected output:", result);
                        await Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Respons server tidak terduga. Output PHP: ' + result.substring(0, 300) + '...',
                            confirmButtonText: 'OK'
                        });
                    }
                })
                .catch(async error => {
                    console.error("Fetch error:", error);
                    await Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Terjadi kesalahan jaringan atau client: ' + error.message,
                        confirmButtonText: 'OK'
                    });
                });
        });

        function openDeleteModal(NIS) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: 'Menghapus siswa ini juga akan menghapus semua data absensi yang terkait!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#3498db',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    const currentTahunAkademikId = filterTahunAkademik.value;
                    window.location.href = `index.php?action=hapus_siswa&NIS=${NIS}&tahun_akademik_id=${currentTahunAkademikId}`;
                }
            });
        }

        function applyTahunAkademikFilter() {
            const selectedTahunAkademik = filterTahunAkademik.value;
            window.location.href = `index.php?tahun_akademik_id=${selectedTahunAkademik}`;
        }

        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");

        function toggleSidebar() {
            const isCollapsed = sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");

            // --- PERBAIKAN: Simpan status sidebar di Local Storage ---
            if (isCollapsed) {
                localStorage.setItem('sidebarState', 'collapsed');
                localStorage.setItem('mainContentState', 'shifted');
                localStorage.setItem('headerState', 'shifted');
            } else {
                localStorage.setItem('sidebarState', 'expanded');
                localStorage.setItem('mainContentState', 'expanded');
                localStorage.setItem('headerState', 'expanded');
            }
        }

        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");
        const logoutDropdownLink = document.getElementById('logoutDropdownLink');
        const logoutButtonSidebar = document.getElementById('logoutButtonSidebar');

        if (userInfoDropdown && userDropdownContent) {
            userInfoDropdown.addEventListener('click', function() {
                userDropdownContent.style.display = userDropdownContent.style.display === 'block' ? 'none' : 'block';
            });

            window.onclick = function(event) {
                if (event.target == siswaModal) {
                    closeSiswaModal();
                }
                if (event.target == promoteSiswaModal) { // Close promote modal too
                    closePromoteSiswaModal();
                }
                if (!event.target.matches('#userInfoDropdown') && !event.target.closest('#userInfoDropdown')) {
                    if (userDropdownContent.style.display === 'block') {
                        userDropdownContent.style.display = 'none';
                    }
                }
            }
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
                    window.location.href = "../../logout.php";
                }
            });
        }

        if (logoutButtonSidebar) {
            logoutButtonSidebar.addEventListener('click', function(e) {
                e.preventDefault();
                showLogoutConfirmation();
            });
        }

        window.addEventListener('DOMContentLoaded', (event) => {
            const currentPathname = window.location.pathname;
            const pathSegments = currentPathname.split('/');
            const superadminIndex = pathSegments.indexOf('superadmin');
            let relativePathFromsuperadmin = '';

            if (superadminIndex !== -1 && pathSegments.length > superadminIndex) {
                relativePathFromsuperadmin = pathSegments.slice(superadminIndex + 1).join('/');
            } else {
                relativePathFromsuperadmin = currentPathname.split('/').pop();
            }

            document.querySelectorAll('.sidebar nav a').forEach(link => {
                link.classList.remove('active');

                let linkHref = new URL(link.href).pathname;
                const linkSegments = linkHref.split('/');
                const linksuperadminIndex = linkSegments.indexOf('superadmin');
                let linkRelativePath = '';

                if (linksuperadminIndex !== -1 && linkSegments.length > linksuperadminIndex) {
                    linkRelativePath = linkSegments.slice(linksuperadminIndex + 1).join('/');
                } else {
                    linkRelativePath = linkHref.split('/').pop();
                }

                linkRelativePath = linkRelativePath.split('?')[0];
                let currentPathWithoutQuery = relativePathFromsuperadmin.split('?')[0];

                if (linkRelativePath === currentPathWithoutQuery) {
                    link.classList.add('active');
                }
            });
        });

        // --- Fungsi untuk Modal Promosi Siswa ---
        function openPromoteSiswaModal() {
            promoteSiswaForm.reset();
            idKelasAsalSelect.innerHTML = '<option value="">Pilih Kelas Asal</option>';
            idKelasTujuanSelect.innerHTML = '<option value="">Pilih Kelas Tujuan</option>';
            promoteSiswaModal.style.display = "flex";
        }

        function closePromoteSiswaModal() {
            promoteSiswaModal.style.display = "none";
        }

        // Fungsi AJAX untuk mendapatkan daftar kelas berdasarkan Tahun Akademik
        async function getKelasByTahunAkademik(tahunAkademikId, targetSelectElement) {
            targetSelectElement.innerHTML = '<option value="">Memuat Kelas...</option>'; // Loading state
            if (!tahunAkademikId) {
                targetSelectElement.innerHTML = '<option value="">Pilih Tahun Akademik terlebih dahulu</option>';
                return;
            }

            try {
                const response = await fetch(`../../api/get_kelas_by_tahun_akademik.php?id_tahun_akademik=${tahunAkademikId}`);
                const data = await response.json();

                targetSelectElement.innerHTML = '<option value="">Pilih Kelas</option>';
                if (data.status === 'success' && data.kelas.length > 0) {
                    data.kelas.forEach(kelas => {
                        const option = document.createElement('option');
                        option.value = kelas.id;
                        option.textContent = kelas.nama_kelas;
                        targetSelectElement.appendChild(option);
                    });
                } else {
                    targetSelectElement.innerHTML = '<option value="" disabled>Tidak ada kelas tersedia</option>';
                }
            } catch (error) {
                console.error('Error fetching classes:', error);
                targetSelectElement.innerHTML = '<option value="" disabled>Gagal memuat kelas</option>';
            }
        }

        // Event Listeners untuk dropdown Tahun Akademik di modal promosi
        idTahunAkademikAsalSelect.addEventListener('change', function() {
            getKelasByTahunAkademik(this.value, idKelasAsalSelect);
        });

        idTahunAkademikTujuanSelect.addEventListener('change', function() {
            getKelasByTahunAkademik(this.value, idKelasTujuanSelect);
        });

        // Event Listener untuk submit form promosi
        promoteSiswaForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(promoteSiswaForm);

            Swal.fire({
                title: 'Konfirmasi Promosi Siswa',
                text: 'Anda akan mempromosikan semua siswa dari Kelas Asal ke Kelas Tujuan. Lanjutkan?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Promosikan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('index.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(async result => {
                            if (result.trim().startsWith("success:")) {
                                await Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: result.trim().substring(8),
                                    showConfirmButton: false,
                                    timer: 2000
                                }).then(() => {
                                    const currentTahunAkademikId = filterTahunAkademik.value;
                                    window.location.href = `index.php?success=${encodeURIComponent(result.trim().substring(8))}&tahun_akademik_id=${currentTahunAkademikId}`;
                                });
                            } else if (result.trim().startsWith("error:")) {
                                await Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: result.trim().substring(6),
                                    confirmButtonText: 'OK'
                                });
                            } else {
                                console.error("Server responded with unexpected output:", result);
                                await Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: 'Respons server tidak terduga. Output PHP: ' + result.substring(0, 300) + '...',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(async error => {
                            console.error("Fetch error:", error);
                            await Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Terjadi kesalahan jaringan atau client: ' + error.message,
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });
        });

        $(document).ready(function() {

            // --- penerapan sidebarnya iki state dari Local Storage ---
            const savedState = localStorage.getItem('sidebarState');
            const sidebar = document.getElementById("sidebar");
            const mainContent = document.getElementById("mainContent");
            const header = document.getElementById("header");
            if (savedState === 'collapsed') {
                // Pastikan variabel elemen DOM sudah terdefinisi/dapat diakses
                if (sidebar && mainContent && header) {
                    sidebar.classList.add("no-transition");
                    sidebar.classList.add("collapsed");
                    mainContent.classList.add("no-transition");
                    mainContent.classList.add("shifted");
                    header.classList.add("no-transition");
                    header.classList.add("shifted");
                }

                setTimeout(() => {
                    sidebar.classList.remove("no-transition");
                    mainContent.classList.remove("no-transition");
                    header.classList.remove("no-transition");
                }, 50); // 50ms sudah cukup singkat dan aman

            }

            // Gunakan ID yang benar: #filter_tahun_akademik dan #kelas_filter
            $('#filter_tahun_akademik, #kelas_filter').on('change', function() {
                
                // Ambil nilai dari dropdown Tahun Akademik (ID: filter_tahun_akademik)
                let tahunId = $('#filter_tahun_akademik').val(); 
                
                // Ambil nilai dari dropdown Kelas (ID: kelas_filter)
                let kelasId = $('#kelas_filter').val();
                
                // Debugging (Opsional: Cek di Console browser jika masih error)
                console.log("Tahun ID:", tahunId, "Kelas ID:", kelasId);

                // Jika tahunId tidak ditemukan (undefined), set ke 0 atau string kosong
                if (typeof tahunId === 'undefined' || tahunId === null) {
                    tahunId = ''; 
                }

                // Buat URL baru dengan kedua parameter
                let url = 'index.php?tahun_akademik_id=' + tahunId + '&kelas_id=' + kelasId;
                
                // Redirect ke URL baru
                window.location.href = url;
            });

            $(document).ready(function() {
                
                // Logika Filter: Tahun Akademik berubah
                $('#filter_tahun_akademik').on('change', function() {
                    let tahunId = $(this).val();
                    
                    // [PENTING] Saat tahun berubah, reset kelas jadi 'all' atau kosong
                    // karena kelas di tahun lama belum tentu ada di tahun baru
                    let url = 'index.php?tahun_akademik_id=' + tahunId + '&kelas_id=all';
                    window.location.href = url;
                });

                // Logika Filter: Kelas berubah
                $('#kelas_filter').on('change', function() {
                    let tahunId = $('#filter_tahun_akademik').val();
                    let kelasId = $(this).val();
                    
                    let url = 'index.php?tahun_akademik_id=' + tahunId + '&kelas_id=' + kelasId;
                    window.location.href = url;
                });

                // ... sisa kode DataTables ...
            });

            // Inisialisasi DataTables (Tetap pertahankan ini)
            if ($('#myTable').length) {
                $('#myTable').DataTable();
            }
        });
    </script>
</body>

</html>