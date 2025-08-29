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

$superadmin_name = htmlspecialchars($_SESSION['superadmin_name'] ?? 'SuperAdmin'); 
$superadmin_photo = 'https://placehold.co/40x40/cccccc/333333?text=SA'; 

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
        $nip_lama_for_update = $_POST['NIP_lama_for_update'] ?? null; 
        $name     = $_POST['namaguru'] ?? '';
        $email    = $_POST['emailguru'] ?? '';
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
        } else if (isset($_POST['old_photoguru']) && !empty($_POST['old_photoguru'])) {
            $foto_path_db = $_POST['old_photoguru'];
        }

        if ($upload_succeeded) {
            if ($_POST['action'] === 'tambah') {
                if ($nip_baru && $name && $email && $gender && $password && $dob && $no_hp && $alamat) { 
                    try {
                        $stmt_check_nip = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ?");
                        $stmt_check_nip->execute([$nip_baru]);
                        if ($stmt_check_nip->fetchColumn() > 0) {
                            $response_message = "Gagal menambahkan guru: NIP sudah terdaftar.";
                        } else {
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
                    $response_message = "Mohon lengkapi semua field yang diperlukan (NIP, Nama, Email, Gender, Tanggal Lahir, Password) untuk menambah guru.";
                }
            } elseif ($_POST['action'] === 'edit') {
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
    
    if ($response_status === 'success') {
        echo "success:" . $response_message;
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
        
        header("Location: index.php?success=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    } catch (PDOException $e) {
        $message = "Gagal menghapus guru: " . $e->getMessage();
        $alert_type = 'alert-error';
        header("Location: index.php?error=" . urlencode($message) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    }
}


// --- Ambil daftar Guru untuk ditampilkan ---
$query_guru = "SELECT * FROM guru ORDER BY name ASC";

$stmt_guru = $pdo->prepare($query_guru);
$stmt_guru->execute();
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/admin1.css">
    <title>Daftar Guru</title>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo"><span>SuperAdminCoy</span></div>
        <nav>
            <a href="../dashboard_superadmin.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../admin/index.php">
                <i class="fas fa-users-cog"></i>
                <span>Admin</span>
            </a>
            <a href="index.php" class="active">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Guru</span>
            </a>
            <a href="../siswa/index.php">
                <i class="fas fa-user-graduate"></i>
                <span>Siswa</span>
            </a>
            <a href="../jadwal/index.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal</span>
            </a>
            <a href="../Tahun_Akademik/index.php">
                <i class="fas fa-calendar"></i>
                <span>Tahun Akademik</span>
            </a>
            <a href="../kelas/index.php">
                <i class="fas fa-school"></i>
                <span>Kelas</span>
            </a>
            <a href="../mapel/index.php">
                <i class="fas fa-book"></i>
                <span>Mata Pelajaran</span>
            </a>
            <div class="logout-button-container">
                <a onclick="showLogoutConfirm(event)">
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
        <h1>Daftar Guru</h1>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Data Guru</h2>
            <button class="add-link" id="btn-tambah-guru">
                <i class="fas fa-plus"></i> Tambah Guru
            </button>

            <ul class="guru-list" id="guru-list">
                <?php foreach ($gurulist as $guru): ?>
                <li
                    data-id="<?= htmlspecialchars($guru['nip']) ?>"
                    data-nama="<?= htmlspecialchars($guru['name']) ?>"
                    data-nip="<?= htmlspecialchars($guru['nip']) ?>"
                    data-gender="<?= htmlspecialchars($guru['gender']) ?>"
                    data-dob="<?= htmlspecialchars($guru['dob']) ?>"
                    data-nohp="<?= htmlspecialchars($guru['no_hp']) ?>"
                    data-email="<?= htmlspecialchars($guru['email']) ?>"
                    data-alamat="<?= htmlspecialchars($guru['alamat']) ?>"
                    data-photo="<?= htmlspecialchars($guru['photo']) ?>"
                >
                    <div class="guru-info">
                        <img
                            src="../../uploads/guru/<?= htmlspecialchars($guru['photo']) ?>"
                            alt="Foto <?= htmlspecialchars($guru['name']) ?>"
                            loading="lazy"
                            onerror="this.onerror=null;this.src='https://placehold.co/60x60/cccccc/333333?text=No+Foto';"
                        >
                        <div class="guru-text">
                            <span class="guru-nama"><?= htmlspecialchars($guru['name']) ?></span>
                            <span class="guru-email"><?= htmlspecialchars($guru['email']) ?></span>
                        </div>
                    </div>
                    <div class="guru-actions">
                        <button class="action-link edit btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="action-link delete btn-hapus" data-nip="<?= urlencode($guru['nip']) ?>">
                            <i class="fas fa-trash-alt"></i> Hapus
                        </button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <!-- Modal for Add/Edit Guru -->
    <div id="guru-modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">Edit Data Guru</h3>
            <form id="guru-form" enctype="multipart/form-data">
                <label for="namaGuru">Nama Guru:</label>
                <input type="text" id="namaGuru" name="namaGuru" required>

                <label for="nipGuru">NIP:</label>
                <input type="text" id="nipGuru" name="nipGuru" required>

                <label>Jenis Kelamin:</label>
                <div class="gender-group">
                    <input type="radio" id="male" name="gender" value="Laki-Laki">
                    <label for="male">Laki-Laki</label>
                    <input type="radio" id="female" name="gender" value="Perempuan">
                    <label for="female">Perempuan</label>
                </div>

                <label for="dobGuru">Tanggal Lahir:</label>
                <input type="date" id="dobGuru" name="dobGuru">

                <label for="photoGuru">Foto Guru:</label>
                <input type="file" id="photoGuru" name="photoGuru">

                <label for="nohpGuru">Nomor HP:</label>
                <input type="text" id="nohpGuru" name="nohpGuru">

                <label for="emailGuru">Email:</label>
                <input type="email" id="emailGuru" name="emailGuru">

                <label for="passwordGuru" id="labelPasswordGuru" style="display:none;">Password:</label>
                <input type="password" id="passwordGuru" name="passwordGuru" style="display:none;">

                <label for="alamatGuru">Alamat:</label>
                <input type="text" id="alamatGuru" name="alamatGuru">

                <div class="modal-buttons">
                    <button type="submit" class="btn-save">Simpan</button>
                    <button type="button" class="btn-close" id="btn-cancel">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Custom Alert/Confirmation Modal -->
    <div id="custom-alert-modal" class="custom-modal-overlay">
        <div class="custom-modal-content">
            <h4 id="custom-alert-message"></h4>
            <div class="modal-buttons">
                <button type="button" class="btn-save" id="custom-alert-ok">OK</button>
                <button type="button" class="btn-close" id="custom-alert-cancel" style="display:none;">Batal</button>
            </div>
        </div>
    </div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
    $(document).ready(function() {
        // Cache DOM elements
        const $sidebar = $('#sidebar');
        const $content = $('#content');
        const $header = $('#header');
        const $toggleBtn = $('#toggle-btn');
        const $userInfo = $('#userInfo');
        const $dropdownMenu = $('#dropdownMenu');
        const $guruModal = $('#guruModal');
        const $modalTitle = $('#modalTitle');
        const $formAction = $('#formAction');
        const $guruForm = $('#guruForm');
        const $NIPguru = $('#NIPguru');
        const $namaguru = $('#namaguru');
        const $emailguru = $('#emailguru');
        const $genderguru = $('#genderguru');
        const $dobguru = $('#dobguru');
        const $nohpguru = $('#nohpguru');
        const $alamatguru = $('#alamatguru');
        const $passwordguru = $('#passwordguru');
        const $passwordReq = $('#password-req');
        const $oldNip = $('#oldNip');
        const $oldPhoto = $('#oldPhoto');
        const $currentPhotoPreview = $('#currentPhotoPreview');
        const $currentPhotoText = $('#currentPhotoText');

        // Sidebar and Header Toggling
        $toggleBtn.on('click', function() {
            $sidebar.toggleClass('collapsed');
            $content.toggleClass('shifted');
            $header.toggleClass('shifted');
        });

        // User Info Dropdown
        $userInfo.on('click', function(e) {
            e.stopPropagation(); 
            $dropdownMenu.fadeToggle(200);
        });

        // Close dropdown when clicking outside
        $(document).on('click', function() {
            $dropdownMenu.fadeOut(200);
        });

        // Guru Modal Logic
        const resetGuruModal = () => {
            $guruForm[0].reset();
            $NIPguru.prop('disabled', false);
            $passwordguru.prop('required', true).val('');
            $passwordReq.show();
            $oldPhoto.val('');
            $currentPhotoPreview.hide().attr('src', '');
            $currentPhotoText.hide();
        };

        $('#tambahGuruBtn').on('click', function() {
            resetGuruModal();
            $modalTitle.text('Tambah Guru');
            $formAction.val('tambah');
            $guruModal.show();
        });

        $('.edit-btn').on('click', function() {
            resetGuruModal(); 
            const data = $(this).data();
            $modalTitle.text('Edit Guru');
            $formAction.val('edit');
            
            // Correctly decode URL-encoded data from PHP
            $oldNip.val(decodeURIComponent(data.nip));
            $NIPguru.val(decodeURIComponent(data.nip)).prop('disabled', true);
            $namaguru.val(decodeURIComponent(data.name));
            $emailguru.val(decodeURIComponent(data.email));
            const decodedGender = decodeURIComponent(data.gender);
            if (decodedGender) {
                // Convert the first letter to uppercase for a proper match
                const normalizedGender = decodedGender.charAt(0).toUpperCase() + decodedGender.slice(1);
                $genderguru.val(normalizedGender);
            }
            $dobguru.val(decodeURIComponent(data.dob));
            $nohpguru.val(decodeURIComponent(data.nohp));
            $alamatguru.val(decodeURIComponent(data.alamat));
            
            $passwordguru.prop('required', false);
            $passwordReq.hide();
            
            if (data.photo) {
                const photoName = decodeURIComponent(data.photo);
                $oldPhoto.val(photoName);
                $currentPhotoPreview.show().attr('src', `../../uploads/guru/${photoName}`);
                $currentPhotoText.show().text(`File lama: ${photoName}`);
            } else {
                $oldPhoto.val('');
            }
            $guruModal.show();
        });

        $('.close-btn, #cancelBtn').on('click', function() {
            $guruModal.hide();
        });

        window.onclick = function(event) {
            if (event.target === $guruModal[0]) {
                $guruModal.hide();
            }
        };

        // Submit form with AJAX
        $guruForm.on('submit', async function(e) {
            $NIPguru.prop('disabled', false); 
            e.preventDefault();

            const formData = new FormData(this);

            Swal.fire({
                title: 'Memproses...',
                text: 'Mohon tunggu sebentar',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            try {
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.text();
                const trimmedResult = result.trim();

                if (trimmedResult.startsWith("success:")) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: trimmedResult.substring(8),
                        confirmButtonText: 'OK'
                    });
                    window.location.reload();
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
            } catch (error) {
                console.error("Fetch error:", error);
                await Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Terjadi kesalahan jaringan atau client: ' + error.message,
                    confirmButtonText: 'OK'
                });
            }
        });

        // SweetAlert for delete confirmation
        $('.delete-btn').on('click', function() {
            const nipToDelete = $(this).data('nip');
            const currentTahunAkademikId = new URLSearchParams(window.location.search).get('tahun_akademik_id');

            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Anda tidak akan dapat mengembalikan data ini!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?action=hapus_guru&NIP=${nipToDelete}&tahun_akademik_id=${currentTahunAkademikId}`;
                }
            });
        });
    });
</script>
</body>
</html>


