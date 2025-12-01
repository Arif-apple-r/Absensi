<?php
session_start();
// Aktifkan reporting error untuk debugging. Pastikan ini selalu ada.
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['admin_id'])) { 
    header("Location: ../../login.php");
    exit;
}
require '../../koneksi.php';

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); 
$admin_photo = 'https://placehold.co/40x40/cccccc/333333?text=SA'; 

$message = '';
$alert_type = '';

// Ambil daftar Tahun Akademik untuk filter dan dropdown form
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun, is_active FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

$selected_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? null;

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


// --- Handle AJAX POST untuk menambah atau mengedit guru ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response_status = 'error';
    $response_message = 'Terjadi kesalahan tidak dikenal.';

    try { 
        $nip_baru       = $_POST['NIPguru'] ?? null; 
        // [HARMONI] Menggunakan NIP_lama_for_update seperti siswapage.php menggunakan NIS_lama_for_update
        $nip_lama_for_update = $_POST['NIP_lama_for_update'] ?? null; 
        $name     = $_POST['namaguru'] ?? '';
        $email    = $_POST['emailguru'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Format email tidak valid.");
        }
        $gender   = $_POST['genderguru'] ?? ''; 
        $dob_raw  = $_POST['dobguru'] ?? '';
        $alamat   = $_POST['alamatguru'] ?? '';
        $password = $_POST['passwordguru'] ?? null; 

        $dob = !empty($dob_raw) ? $dob_raw : null;

        $no_hp_raw = $_POST['nohpguru'] ?? '';
        $no_hp = null; 
        if (is_numeric($no_hp_raw) && $no_hp_raw !== '') {
            $no_hp = (int)$no_hp_raw;
            if ($no_hp < 0 || $no_hp > 99999999999999) { 
                throw new Exception("Nomor HP terlalu besar atau negatif untuk disimpan.");
            }
        } else if (!empty($no_hp_raw)) { 
            throw new Exception("Nomor HP harus berupa angka.");
        }

        $foto_path_db = null;
        $folder_upload = "../../uploads/guru/";
        $upload_succeeded = true; 

        if (!is_dir($folder_upload)) {
            mkdir($folder_upload, 0777, true);
        }

        // Asumsi: Field upload file di form bernama 'photoguru' (seperti 'photosiswa')
        if (isset($_FILES['photoguru']) && $_FILES['photoguru']['error'] === UPLOAD_ERR_OK) {
            $foto_tmp = $_FILES['photoguru']['tmp_name'];
            $foto_name = $_FILES['photoguru']['name'];
            $ext = pathinfo($foto_name, PATHINFO_EXTENSION);
            $nama_foto_baru = uniqid() . '.' . $ext;
            $dest_path = $folder_upload . $nama_foto_baru;

            if (move_uploaded_file($foto_tmp, $dest_path)) {
                $foto_path_db = $nama_foto_baru;
            } else {
                throw new Exception("Gagal mengunggah foto guru. Coba lagi atau pastikan folder 'uploads/guru/' dapat ditulis.");
            }
        // Asumsi: Field hidden old photo di form bernama 'old_photoguru' (seperti 'old_photosiswa')
        } else if (isset($_POST['old_photoguru']) && !empty($_POST['old_photoguru'])) { 
            $foto_path_db = $_POST['old_photoguru'];
        }

        if ($upload_succeeded) {
            if ($_POST['action'] === 'tambah') {
                if ($nip_baru && $name && $email && $password && $dob && $alamat) { 
                try {
                    // Cek apakah NIP sudah terdaftar
                    $stmt_check_nip = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ?");
                    $stmt_check_nip->execute([$nip_baru]);
                    
                    // Cek apakah email sudah terdaftar
                    $stmt_check_email = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE email = ?");
                    $stmt_check_email->execute([$email]);

                    if ($stmt_check_nip->fetchColumn() > 0) {
                        $response_message = "Gagal menambahkan guru: NIP sudah terdaftar.";
                    } elseif ($stmt_check_email->fetchColumn() > 0) {
                        $response_message = "Gagal menambahkan guru: Email sudah terdaftar.";
                    } else {
                        // Jika NIP dan email belum terdaftar, lakukan INSERT
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $admission_date = date('Y-m-d H:i:s'); 
                        
                        $stmt = $pdo->prepare("INSERT INTO guru (nip, name, email, gender, dob, no_hp, alamat, photo, pass, admission_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$nip_baru, $name, $email, $gender, $dob, $no_hp, $alamat, $foto_path_db, $hashed_password, $admission_date]);
                        
                        $response_status = 'success';
                        $response_message = "Guru berhasil ditambahkan!";
                    }
                } catch (PDOException $e) {
                        $response_message = "Gagal menambahkan guru (DB Error): " . $e->getMessage();
                    }
                } else {
                    // [HARMONI] Menambahkan validasi semua field wajib diisi (gender dan no_hp/alamat mungkin opsional tergantung desain DB)
                    $response_message = "Mohon lengkapi semua field yang diperlukan (NIP, Nama, Email, Gender, Tanggal Lahir, Password) untuk menambah guru.";
                }
            } elseif ($_POST['action'] === 'edit') {
                // [HARMONI] Memastikan field wajib ada saat edit
                if ($nip_lama_for_update && $nip_baru && $name && $email && $gender && $dob) { 
                    try {
                        if ($nip_baru !== $nip_lama_for_update) {
                            $stmt_check_nip_exist = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ? AND nip != ?");
                            $stmt_check_nip_exist->execute([$nip_baru, $nip_lama_for_update]);
                            if ($stmt_check_nip_exist->fetchColumn() > 0) {
                                $response_message = "Gagal mengupdate guru: NIP baru sudah terdaftar untuk guru lain.";
                                throw new Exception($response_message); 
                            }
                        }

                        if ($foto_path_db && isset($_POST['old_photoguru']) && $_POST['old_photoguru'] !== $foto_path_db && file_exists($folder_upload . $_POST['old_photoguru'])) {
                            unlink($folder_upload . $_POST['old_photoguru']);
                        }
        
                        $update_pass_sql = '';
                        $update_pass_params = [];
                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $update_pass_sql = ', pass = ?';
                            $update_pass_params = [$hashed_password];
                        }
        
                        $stmt = $pdo->prepare("UPDATE guru SET nip = ?, name = ?, email = ?, gender = ?, dob = ?, no_hp = ?, alamat = ?, photo = ? " . $update_pass_sql . " WHERE nip = ?");
                        $stmt->execute(array_merge([$nip_baru, $name, $email, $gender, $dob, $no_hp, $alamat, $foto_path_db], $update_pass_params, [$nip_lama_for_update]));
                        
                        $response_status = 'success';
                        $response_message = "Guru berhasil diupdate!";
                    } catch (PDOException $e) {
                        $response_message = "Gagal mengupdate guru (DB Error): " . $e->getMessage();
                    } catch (Exception $e) { 
                        $response_message = $e->getMessage();
                    }
                } else {
                    $response_message = "Mohon lengkapi semua field yang diperlukan untuk mengupdate guru. (NIP, Nama, Email, Gender, Tanggal Lahir, Alamat)";
                }
            }
        }
    } catch (Throwable $e) { 
        $response_message = "Kesalahan fatal di server: " . $e->getMessage() . " (Line: " . $e->getLine() . " in " . basename($e->getFile()) . ")";
        error_log("Fatal error in guru AJAX POST: " . $e->getMessage() . " on line " . $e->getLine() . " in file " . $e->getFile() . "\n" . $e->getTraceAsString());
    }
    
    // [PERUBAHAN UTAMA] Menyamakan respons sukses dengan siswapage.php (tanpa pesan/kolon)
    if ($response_status === 'success') {
        echo "success"; 
    } else {
        echo "error: " . $response_message;
    }
    exit; 
} 
// --- END Handle AJAX POST untuk menambah atau mengedit guru ---


// --- Handle GET requests (delete guru) ---
if (isset($_GET['action']) && $_GET['action'] === 'hapus_guru' && isset($_GET['NIP'])) {
    $nip_to_delete = $_GET['NIP'];
    $current_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? $selected_tahun_akademik_id;

    try {
        $stmt_get_photo = $pdo->prepare("SELECT photo FROM guru WHERE nip = ?");
        $stmt_get_photo->execute([$nip_to_delete]);
        $guru_data = $stmt_get_photo->fetch(PDO::FETCH_ASSOC);
        $foto_to_delete = $guru_data['photo'] ?? '';

        $stmt = $pdo->prepare("DELETE FROM guru WHERE nip = ?");
        $stmt->execute([$nip_to_delete]);

        $folder_upload = "../../uploads/guru/";
        if (!empty($foto_to_delete) && $foto_to_delete != 'default.jpg' && file_exists($folder_upload . $foto_to_delete)) {
            unlink($folder_upload . $foto_to_delete);
        }

        $message = "Guru berhasil dihapus!";
        $alert_type = 'alert-success';
        
        // Redirect menggunakan 'success' key untuk konsistensi pesan notifikasi
        header("Location: index.php?success=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    } catch (PDOException $e) {
        $message = "Gagal menghapus guru: " . $e->getMessage();
        $alert_type = 'alert-error';
        // Redirect menggunakan 'error' key
        header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    }
}


// --- Ambil daftar Guru untuk ditampilkan + filter nama sih ---
$search = $_GET['search'] ?? '';
$params = [];

if (!empty($search)) {
    $search_keyword_start = $search . "%";
    $query_guru = "SELECT * FROM guru 
                   WHERE nip LIKE ? 
                      OR name LIKE ?
                   ORDER BY name ASC";
    $params = [$search_keyword_start, $search_keyword_start];
} else {
    $query_guru = "SELECT * FROM guru ORDER BY name ASC";
}

$stmt_guru = $pdo->prepare($query_guru);
$stmt_guru->execute($params);

$guru_list = $stmt_guru->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Manajemen Guru | Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">    
    <link rel="stylesheet" href="../../assets/adminpage.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

</head>
<body>

    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../../uploads/icon/logo.png" alt="Logo AdminCoy" class="logo-icon">
            <span class="logo-text">AdminCoy</span>
        </div>
        <nav>
            <a href="../dashboard_admin.php">
                <div class="hovertext" data-hover="dashboard"><i class="fas fa-tachometer-alt"></div></i><span>Dashboard</span></a>
            <a href="#" class="active">
                <div class="hovertext" data-hover="Guru"><i class="fas fa-chalkboard-teacher"></div></i><span>Guru</span></a>
            <a href="../siswa/index.php">
                <div class="hovertext" data-hover="Siswa"><i class="fas fa-user-graduate"></div></i><span>Siswa</span></a>
            <a href="../jadwal/index.php">
                <div class="hovertext" data-hover="Jadwal"><i class="fas fa-calendar-alt"></div></i><span>Jadwal</span></a>
            <a href="../tahun_akademik/index.php">
                <div class="hovertext" data-hover="Tahun Akademik"><i class="fas fa-calendar"></div></i><span>Tahun Akademik</span></a>
            <a href="../kelas/index.php">
                <div class="hovertext" data-hover="Kelas"><i class="fas fa-school"></div></i><span>Kelas</span></a>
            <a href="../mapel/index.php">
                <div class="hovertext" data-hover="Mata Pelajaran"><i class="fas fa-book"></div></i><span>Mata Pelajaran</span></a>
        </nav>
        <div class="logout-button-container hovertext" data-hover="Logout">
            <a onclick="showLogoutConfirmation()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="header" id="header">
            <button class="toggle-btn" id="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
            <h1><i class="fas fa-chalkboard-teacher"></i> Manajemen Guru</h1>
            <div class="user-info" id="userInfo">
                <span><?= htmlspecialchars($admin_name) ?></span>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="../../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Daftar Guru</h2>
            <?php if ($message): ?>
                <div class="alert <?= htmlspecialchars($alert_type) ?>">
                    <i class="fas fa-info-circle"></i>
                    <p><?= htmlspecialchars($message) ?></p>
                </div>
            <?php endif; ?>

            <form id ="searchForm" method="GET" style="margin-bottom: 15px;">
                <div class="filter-section">
                    <div class="filter-group">
                        <label for="liveSearch">Pencarian:</label>
                        <input id="liveSearch" type="text" name="search" placeholder="Cari NIP / Nama Guru..."
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    <div class="filter-group" style="flex-grow: 0;">
                        <button type="submit" style="padding:8px;">Cari</button>
                    </div>
                </div>
            </form>

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <a href="#" class="add-link" id="tambahGuruBtn" onclick="openGuruModal('tambah'); return false;"><i class="fas fa-plus-circle"></i> Tambah Guru</a>
            </div>

           <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>NIP</th>
                            <th style="text-align: center;">Profil</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Jenis Kelamin</th>
                            <th>No. HP</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="guruTableBody">
                        <?php if (empty($guru_list)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Tidak ada data guru.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($guru_list as $guru): ?>
                                <tr>
                                    <td><?= htmlspecialchars($guru['nip'] ?? '') ?></td>
                                    <td style="text-align: center;"><img src="../../uploads/guru/<?= htmlspecialchars($guru['photo'] ?? 'default.jpg') ?>" alt="Foto Guru" class="profile-photo"></td>
                                    <td><?= htmlspecialchars($guru['name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($guru['email'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($guru['gender'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($guru['no_hp'] ?? '') ?></td>
                                    <td>
                                        <a href="#" class="action-link edit" 
                                            data-nip="<?= htmlspecialchars($guru['nip'] ?? '') ?>"
                                            data-name="<?= htmlspecialchars($guru['name'] ?? '') ?>" 
                                            data-email="<?= htmlspecialchars($guru['email'] ?? '') ?>" 
                                            data-gender="<?= htmlspecialchars($guru['gender'] ?? '') ?>"
                                            data-dob="<?= htmlspecialchars($guru['dob'] ?? '') ?>" 
                                            data-nohp="<?= htmlspecialchars($guru['no_hp'] ?? '') ?>"
                                            data-alamat="<?= htmlspecialchars($guru['alamat'] ?? '') ?>"
                                            data-photo="<?= htmlspecialchars($guru['photo'] ?? '') ?>">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="action-link delete" data-nip="<?= htmlspecialchars($guru['nip'] ?? '') ?>">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div id="guruModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Guru</h3>
                <span class="close-btn">&times;</span>
            </div>
            <form id="guruForm" action="index.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="tambah">
                <input type="hidden" name="NIP_lama_for_update" id="oldNip">
                <input type="hidden" name="old_photoguru" id="oldPhoto">
                
                <div class="form-group">
                    <label for="NIPguru">NIP <span style="color: red;">*</span></label>
                    <input type="text" id="NIPguru" name="NIPguru" required>
                </div>
                <div class="form-group">
                    <label for="namaguru">Nama <span style="color: red;">*</span></label>
                    <input type="text" id="namaguru" name="namaguru" required>
                </div>
                <div class="form-group">
                    <label for="emailguru">Email <span style="color: red;">*</span></label>
                    <input type="email" id="emailguru" name="emailguru" required>
                </div>
                <div class="form-group">
                    <label for="genderguru">Jenis Kelamin <span style="color: red;">*</span></label>
                    <select id="genderguru" name="genderguru">
                        <option value="">Pilih Jenis Kelamin</option>
                        <option value="laki-laki">laki-laki</option>
                        <option value="perempuan">Perempuan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dobguru">Tanggal Lahir <span style="color: red;">*</span></label>
                    <input type="date" id="dobguru" name="dobguru" required>
                </div>
                <div class="form-group">
                    <label for="nohpguru">No. HP</label>
                    <input type="text" id="nohpguru" name="nohpguru">
                </div>
                <div class="form-group">
                    <label for="alamatguru">Alamat</label>
                    <textarea id="alamatguru" name="alamatguru"></textarea>
                </div>
                <div class="form-group">
                    <label for="passwordguru">Password <span id="password-req" style="color: red;">*</span></label>
                    <input type="password" id="passwordguru" name="passwordguru">
                </div>
                <div class="form-group">
                    <label for="photoguru">Foto Profil</label>
                    <input type="file" id="photoguru" name="photoguru" accept="image/*">
                </div>
                <div class="photo-upload">
                    <img id="currentPhotoPreview" src="" alt="Foto Profil" style="display:none;">
                    <p id="currentPhotoText" style="display:none;"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" id="cancelBtn">Batal</button>
                    <button type="submit" class="btn btn-submit" id="submitGuruBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
    <script>
         // Deklarasi Elemen DOM (Serasi dengan siswapage.js)
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");
        const logoutButtonSidebar = document.getElementById('logoutButtonSidebar'); // Pastikan ini ada di HTML

        const guruModal = document.getElementById("guruModal");
        const guruModalTitle = document.getElementById("modalTitle"); // Menggunakan ID yang sudah ada
        const guruForm = document.getElementById("guruForm");
        const guruActionInput = document.getElementById("formAction"); // Menggunakan ID yang sudah ada
        const oldNipInput = document.getElementById("oldNip");
        const oldPhotoInput = document.getElementById("oldPhoto");

        const NIPguruInput = document.getElementById("NIPguru");
        const namaguruInput = document.getElementById("namaguru");
        const emailguruInput = document.getElementById("emailguru");
        const passwordGroup = document.getElementById("passwordGroup"); // Tambahkan grup di HTML atau gunakan ID password
        const passwordguruInput = document.getElementById("passwordguru");
        const passwordReqText = document.getElementById("password-req");

        // Asumsi: Gender diubah dari Select menjadi Radio Button atau Select dengan value:
        const genderguruInput = document.getElementById("genderguru"); // Asumsi: Select
        const dobguruInput = document.getElementById("dobguru");
        const nohpguruInput = document.getElementById("nohpguru");
        const alamatguruTextarea = document.getElementById("alamatguru");

        const currentPhotoPreview = document.getElementById("currentPhotoPreview");
        const currentPhotoText = document.getElementById("currentPhotoText");
        const submitGuruBtn = document.getElementById("submitGuruBtn"); // Pastikan ini ada di HTML

        // Fungsi Toggling Sidebar (Sama persis dengan siswapage.js)
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

        // Fungsi Buka Modal Guru (Disamakan dengan openSiswaModal)
        function openGuruModal(action, NIP = '', name = '', email = '', gender = '', dob = '', no_hp = '', alamat = '', photo = '') {
            guruForm.reset();
            guruActionInput.value = action;

            // Reset preview dan hidden fields
            oldNipInput.value = '';
            oldPhotoInput.value = '';
            currentPhotoPreview.src = '';
            currentPhotoPreview.style.display = 'none';
            currentPhotoText.style.display = 'none';

            if (action === 'tambah') {
                guruModalTitle.textContent = "Tambah Guru";
                submitGuruBtn.textContent = "Simpan";
                NIPguruInput.readOnly = false;
                
                // Asumsi: Password group/field harus ditampilkan
                if (passwordGroup) passwordGroup.style.display = 'block';
                passwordguruInput.required = true;
                if (passwordReqText) passwordReqText.style.display = 'block';

            } else if (action === 'edit') {
                guruModalTitle.textContent = "Edit Guru";
                submitGuruBtn.textContent = "Update";
                NIPguruInput.readOnly = false; // Mungkin tidak perlu readOnly true saat edit, tergantung logika backend
                
                // Mengisi data
                NIPguruInput.value = decodeURIComponent(NIP);
                oldNipInput.value = decodeURIComponent(NIP);
                namaguruInput.value = decodeURIComponent(name);
                emailguruInput.value = decodeURIComponent(email);
                
                // Pengisian Gender (Asumsi: Select/Input ID: genderguru)
                if (genderguruInput) {
                    const normalizedGender = decodeURIComponent(gender).charAt(0).toUpperCase() + decodeURIComponent(gender).slice(1);
                    genderguruInput.value = normalizedGender;
                }

                dobguruInput.value = decodeURIComponent(dob);
                nohpguruInput.value = decodeURIComponent(no_hp);
                alamatguruTextarea.value = decodeURIComponent(alamat);
                oldPhotoInput.value = decodeURIComponent(photo);

                if (photo) {
                    const photoName = decodeURIComponent(photo);
                    currentPhotoPreview.style.display = 'block';
                    currentPhotoPreview.src = `../../uploads/guru/${photoName}`;
                    currentPhotoText.style.display = 'block';
                    currentPhotoText.textContent = `File lama: ${photoName}`;
                }

                // Asumsi: Password group/field disembunyikan dan tidak required
                if (passwordGroup) passwordGroup.style.display = 'none';
                passwordguruInput.required = false;
                passwordguruInput.value = '';
                if (passwordReqText) passwordReqText.style.display = 'none';
            }
            guruModal.style.display = "flex";
        }

        // Fungsi Tutup Modal Guru
        function closeGuruModal() {
            guruModal.style.display = "none";
        }


        // Fungsi SweetAlert Logout (Sama persis dengan siswapage.js)
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

        // Event Listener untuk Logout Sidebar
        if (logoutButtonSidebar) {
            logoutButtonSidebar.addEventListener('click', function(e) {
                e.preventDefault();
                showLogoutConfirmation();
            });
        }


        // Event Listener Form Submission (Disamakan dengan siswapage.js)
        guruForm.addEventListener("submit", function(e) {
            e.preventDefault();
            // Re-enable NIP input sebelum submit jika disabled untuk memastikan NIP terkirim
            NIPguruInput.disabled = false;
            const formData = new FormData(guruForm);
            // Setelah mendapatkan FormData, disable lagi jika mode edit
            if (guruActionInput.value === 'edit') {
                // NIPguruInput.disabled = true; // Tidak perlu, karena sudah diatur di openGuruModal
            }

            fetch('index.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(async result => {
                    const actionMode = guruActionInput.value;
                    const trimmedResult = result.trim();

                    if (trimmedResult.startsWith("success")) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: trimmedResult.substring(8) || (actionMode === 'edit' ? "Guru berhasil diupdate!" : "Guru berhasil ditambahkan!"),
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            // Redirect atau reload halaman setelah berhasil
                            window.location.href = `index.php?success=${encodeURIComponent(trimmedResult.substring(8) || (actionMode === 'edit' ? "Guru berhasil diupdate!" : "Guru berhasil ditambahkan!"))}`;
                        });
                    } else if (trimmedResult.startsWith("error:")) {
                        await Swal.fire({
                            icon: 'error',
                            title: 'Gagal!',
                            text: trimmedResult.substring(6),
                            confirmButtonText: 'OK'
                        });
                    } else {
                        console.error("Server responded with unexpected output:", result);
                        await Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'Respons server tidak terduga. Output PHP: ' + trimmedResult.substring(0, 300) + '...',
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

        // Event Listener Penutupan Modal ketika klik di luar (Sama persis dengan siswapage.js)
        window.onclick = function(event) {
            if (event.target == guruModal) {
                closeGuruModal();
            }
        };


        // jQuery ready function (Untuk live search, delete, dan inisialisasi DataTables)
        $(document).ready(function() {
            
            // --- penerapan sidebarnya iki state dari Local Storage (Sama persis dengan siswapage.js) ---
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

            // Mengganti penggunaan jQuery click handler untuk Modal dengan fungsi baru
            $('#tambahGuruBtn').on('click', function() {
                openGuruModal('tambah');
            });

            // Mengganti event .edit dengan pemicu fungsi openGuruModal baru
            $('.edit').on('click', function() {
                const data = $(this).data();
                openGuruModal(
                    'edit', 
                    data.nip, 
                    data.name, 
                    data.email, 
                    data.gender, 
                    data.dob, 
                    data.nohp, 
                    data.alamat, 
                    data.photo
                );
            });
            
            // Event listener untuk tombol close/cancel
            $('.close-btn, #cancelBtn').on('click', function() {
                closeGuruModal();
            });

            // SweetAlert for delete confirmation (Disamakan dengan logika delete siswa)
            $('.delete').on('click', function() {
                const nipToDelete = $(this).data('nip');
                const currentTahunAkademikId = new URLSearchParams(window.location.search).get('tahun_akademik_id');

                Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: "Menghapus guru ini juga akan menghapus data yang terkait (jika ada)! Anda tidak akan dapat mengembalikan data ini!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e74c3c', // Warna merah yang serasi
                    cancelButtonColor: '#3498db', // Warna biru yang serasi
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Gunakan redirect seperti di siswapage.js
                        window.location.href = `index.php?action=hapus_guru&NIP=${nipToDelete}&tahun_akademik_id=${currentTahunAkademikId || ''}`;
                    }
                });
            });

            // live search (Tetap menggunakan jQuery AJAX karena lebih ringkas)
            $('#liveSearch').on('keyup', function() {
                const keyword = $(this).val();

                $.get('search_guru.php', { q: keyword }, function(data) {
                    $('#guruTableBody').html(data);

                    // Re-bind edit/delete events for new rows
                    bindEditButtons();
                    bindDeleteButtons();
                });
            });

            function bindEditButtons() {
                // Re-bind dengan fungsi baru
                $('.edit').off().on('click', function() {
                    const data = $(this).data();
                    openGuruModal(
                        'edit', 
                        data.nip, 
                        data.name, 
                        data.email, 
                        data.gender, 
                        data.dob, 
                        data.nohp, 
                        data.alamat, 
                        data.photo
                    );
                });
            }

            function bindDeleteButtons() {
                $('.delete').off().on('click', function() {
                    const nipToDelete = $(this).data('nip');
                    const currentTahunAkademikId = new URLSearchParams(window.location.search).get('tahun_akademik_id');

                    Swal.fire({
                        title: 'Apakah Anda yakin?',
                        text: "Menghapus guru ini juga akan menghapus data yang terkait (jika ada)! Anda tidak akan dapat mengembalikan data ini!",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e74c3c', 
                        cancelButtonColor: '#3498db', 
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `index.php?action=hapus_guru&NIP=${nipToDelete}&tahun_akademik_id=${currentTahunAkademikId || ''}`;
                        }
                    });
                });
            }
            
            // Inisialisasi DataTables (Tetap pertahankan ini)
            if ($('#myTable').length) {
                $('#myTable').DataTable();
            }
        });
    </script>
</body>
</html>
