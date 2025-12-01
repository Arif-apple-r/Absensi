<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Pastikan hanya siswa yang sudah login yang bisa mengakses halaman ini
if (!isset($_SESSION['siswa_id'])) {
    header("Location: ../login.php");
    exit;
}

require '../koneksi.php';

$siswa_id = $_SESSION['siswa_id'];
$siswa_name = $_SESSION['siswa_name'] ?? 'Siswa';

// Inisialisasi variabel untuk pesan notifikasi
$success_message = '';
$error_message = '';

// --- FUNGSI UNTUK MEMPROSES SUBMIT FORM ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Ambil data dari form
        $name = htmlspecialchars($_POST['name']);
        $nis = htmlspecialchars($_POST['nis']);
        $gender = htmlspecialchars($_POST['gender']);
        $email = htmlspecialchars($_POST['email']);
        $dob = htmlspecialchars($_POST['dob']);
        $no_hp = htmlspecialchars($_POST['no_hp']);
        $alamat = htmlspecialchars($_POST['alamat']);
        $class_id = htmlspecialchars($_POST['class_id']); // Menyesuaikan dengan tabel siswa

        try {
            // Cek apakah email sudah digunakan oleh siswa lain
            $stmt_check_email = $pdo->prepare("SELECT id FROM siswa WHERE email = ? AND id != ?");
            $stmt_check_email->execute([$email, $siswa_id]);
            if ($stmt_check_email->rowCount() > 0) {
                $error_message = "Email sudah terdaftar untuk akun lain.";
            } else {
                // Perbarui data di database
                $stmt = $pdo->prepare("UPDATE siswa SET name = ?, nis = ?, gender = ?, email = ?, dob = ?, no_hp = ?, alamat = ? WHERE id = ?");
                $stmt->execute([$name, $nis, $gender, $email, $dob, $no_hp, $alamat, $siswa_id]);
                $success_message = "Profil berhasil diperbarui!";
                $_SESSION['siswa_name'] = $name; // Perbarui nama di sesi
            }
        } catch (PDOException $e) {
            $error_message = "Gagal memperbarui profil: " . $e->getMessage();
        }
    }

    // --- FUNGSI UNTUK MENGUNGGAH FOTO PROFIL ---
    if (isset($_POST['upload_photo'])) {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['photo']['tmp_name'];
            $file_name = uniqid('photo_') . '_' . basename($_FILES['photo']['name']);
            $file_destination = '../uploads/siswa/' . $file_name;

            // Hapus foto lama jika ada
            $stmt_old_photo = $pdo->prepare("SELECT photo FROM siswa WHERE id = ?");
            $stmt_old_photo->execute([$siswa_id]);
            $old_photo_name = $stmt_old_photo->fetchColumn();
            if ($old_photo_name && file_exists('../uploads/siswa/' . $old_photo_name)) {
                unlink('../uploads/siswa/' . $old_photo_name);
            }

            if (move_uploaded_file($file_tmp, $file_destination)) {
                $stmt = $pdo->prepare("UPDATE siswa SET photo = ? WHERE id = ?");
                $stmt->execute([$file_name, $siswa_id]);
                $success_message = "Foto profil berhasil diperbarui!";
            } else {
                $error_message = "Gagal mengunggah foto. Silakan coba lagi.";
            }
        } else {
            $error_message = "Terjadi kesalahan saat mengunggah foto.";
        }
    }

    // --- FUNGSI UNTUK MEMPERBARUI PASSWORD ---
    if (isset($_POST['change_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if ($new_password !== $confirm_password) {
            $error_message = "Konfirmasi password tidak cocok!";
        } else if (strlen($new_password) < 6) {
            $error_message = "Password minimal 6 karakter.";
        } else {
            // Hash password baru
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE siswa SET pass = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $siswa_id]);
            $success_message = "Password berhasil diperbarui!";
        }
    }
}

// Ambil data profil siswa terbaru dari database
$stmt_siswa_profil = $pdo->prepare("
    SELECT 
        nis, 
        name, 
        gender, 
        email, 
        pass, 
        dob, 
        no_hp, 
        photo, 
        alamat, 
        class_id, 
        admission_date
    FROM siswa
    WHERE id = ?
");
$stmt_siswa_profil->execute([$siswa_id]);
$siswa_data = $stmt_siswa_profil->fetch(PDO::FETCH_ASSOC);

// Ambil nama kelas dari tabel `class`
$class_name = 'Tidak Diketahui';
if ($siswa_data && $siswa_data['class_id']) {
    $stmt_class = $pdo->prepare("SELECT nama_kelas FROM class WHERE id = ?");
    $stmt_class->execute([$siswa_data['class_id']]);
    $class_result = $stmt_class->fetch(PDO::FETCH_ASSOC);
    if ($class_result) {
        $class_name = $class_result['nama_kelas'];
    }
}

if (!$siswa_data) {
    // Jika data siswa tidak ditemukan di DB, redirect ke login
    session_destroy();
    header("Location: ../login.php");
    exit;
}
$siswa_photo = $siswa_data['photo'];
$last_login = $_SESSION['last_login'] ?? 'Belum ada data login';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Dashboard Siswa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/userpage.css">
</head>

<body>
    <aside class="sidebar" id="sidebar">
        <div class="logo">
            <span>SiswaCoy</span>
        </div>
        <nav>
            <a href="dashboard_siswa.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="jadwal_siswa.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal Saya</span>
            </a>
            <a href="absensi_siswa.php">
                <i class="fas fa-check-circle"></i>
                <span>Absensi Saya</span>
            </a>
            <div class="logout-button-container">
                <a href="#" id="logoutButton">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
    </aside>

    <header class="header" id="mainHeader">
        <button id="toggleSidebar" class="toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Profil Saya</h1>
        <div class="user-info" id="userInfoDropdown">
            <span class="user-name"><?php echo htmlspecialchars($siswa_name); ?></span>
            <?php
            $siswa_photo_src_header = !empty($siswa_photo) ? '../uploads/siswa/' . htmlspecialchars($siswa_photo) : 'https://placehold.co/40x40/cccccc/000000?text=SW';
            ?>
            <img src="<?php echo $siswa_photo_src_header; ?>" alt="User Avatar"
                loading="lazy"
                onerror="this.onerror=null;this.src='https://placehold.co/40x40/cccccc/333333?text=SW';">
            <div class="dropdown-menu" id="userDropdownContent">
                <a href="profil_siswa.php"><i class="fas fa-user-circle"></i>Profil Saya</a>
                <a href="#" id="logoutDropdownLink"><i class="fas fa-sign-out-alt"></i>Logout</a>
            </div>
        </div>
    </header>

    <main class="content" id="mainContent">
        <div class="card">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <form action="profil_siswa.php" method="POST" enctype="multipart/form-data">
                <div class="profile-photo-container">
                    <?php
                    $siswa_photo_src = !empty($siswa_photo) ? '../uploads/siswa/' . htmlspecialchars($siswa_photo) : 'https://placehold.co/150x150/e0e0e0/333333?text=SW';
                    ?>
                    <img src="<?php echo $siswa_photo_src; ?>" alt="Foto Profil Siswa" class="profile-photo" id="profilePhotoPreview">
                    <div class="form-group mt-4">
                        <label for="photo_file" class="block text-gray-700 font-semibold mb-2">Unggah Foto Profil Baru</label>
                        <input type="file" name="photo" id="photo_file" accept="image/*" class="w-full p-2 border border-gray-300 rounded-lg">
                        <input type="hidden" name="upload_photo" value="1">
                        <button type="submit" class="btn-submit mt-2">Ubah Foto</button>
                    </div>
                </div>
            </form>

            <form action="profil_siswa.php" method="POST">
                <h2 class="text-2xl font-bold mb-6">Informasi Dasar</h2>
                <input type="hidden" name="update_profile" value="1">
                <div class="flex-container">
                    <div class="form-group">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($siswa_data['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nis">NIS</label>
                        <input type="text" id="nis" name="nis" value="<?php echo htmlspecialchars($siswa_data['nis']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label for="gender">Jenis Kelamin</label>
                        <select id="gender" name="gender" required>
                            <option value="Laki-laki" <?php echo ($siswa_data['gender'] == 'Laki-laki') ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="Perempuan" <?php echo ($siswa_data['gender'] == 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($siswa_data['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="dob">Tanggal Lahir</label>
                        <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($siswa_data['dob']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="no_hp">No. HP</label>
                        <input type="tel" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($siswa_data['no_hp']); ?>">
                    </div>
                    <div class="form-group w-full">
                        <label for="class_id">Kelas</label>
                        <input type="text" id="class_id" name="class_id" value="<?php echo htmlspecialchars($class_name); ?>" disabled>
                    </div>
                    <div class="form-group w-full">
                        <label for="alamat">Alamat</label>
                        <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($siswa_data['alamat']); ?></textarea>
                    </div>
                    <div class="form-group w-full">
                        <label for="admission_date">Tanggal Diterima</label>
                        <input type="text" id="admission_date" name="admission_date" value="<?php echo htmlspecialchars($siswa_data['admission_date']); ?>" disabled>
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn-submit">Simpan Perubahan</button>
                </div>
            </form>

            <hr class="my-6">

            <form action="profil_siswa.php" method="POST">
                <h2 class="text-2xl font-bold mb-6">Ubah Password</h2>
                <input type="hidden" name="change_password" value="1">
                <div class="flex-container">
                    <div class="form-group">
                        <label for="new_password">Password Baru</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password Baru</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn-submit">Ubah Password</button>
                </div>
            </form>

            <a href="profil_siswa.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Profil Anda
            </a>
        </div>
    </main>

    <script>
        // Logika Sidebar dan Dropdown
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const mainHeader = document.getElementById("mainHeader");
        const toggleSidebarBtn = document.getElementById("toggleSidebar");
        const userInfoDropdown = document.getElementById("userInfoDropdown");
        const userDropdownContent = document.getElementById("userDropdownContent");
        const logoutButtonSidebar = document.getElementById('logoutButton');
        const logoutDropdownLink = document.getElementById('logoutDropdownLink');
        const photoFileInput = document.getElementById('photo_file');
        const photoPreview = document.getElementById('profilePhotoPreview');
        const successMessage = "<?php echo $success_message; ?>";
        const errorMessage = "<?php echo $error_message; ?>";

        toggleSidebarBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('shifted');
            mainHeader.classList.toggle('shifted');
        });

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
            };
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

        if (logoutButtonSidebar) {
            logoutButtonSidebar.addEventListener('click', showLogoutConfirmation);
        }
        if (logoutDropdownLink) {
            logoutDropdownLink.addEventListener('click', showLogoutConfirmation);
        }

        // Tampilkan pesan SweetAlert jika ada
        window.addEventListener('load', () => {
            if (successMessage) {
                Swal.fire('Berhasil!', successMessage, 'success');
            } else if (errorMessage) {
                Swal.fire('Error!', errorMessage, 'error');
            }
        });

        // Preview foto yang diunggah
        if (photoFileInput) {
            photoFileInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>

</html>