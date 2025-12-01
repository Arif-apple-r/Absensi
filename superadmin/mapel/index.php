<?php
require '../../koneksi.php';
session_start();

// Cek sesi login superadmin
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../login.php");
    exit;
}

$superadmin_name = htmlspecialchars($_SESSION['superadmin_name'] ?? 'SuperAdmin');

// Logika Tambah Mata Pelajaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_mapel'])) {
    $nama = trim($_POST['nama_mapel']);
    $kurikulum = $_POST['kurikulum'];

    // Validasi & Upload Foto
    $file = $_FILES['photo'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        // Handle error...
        exit;
    }
    if (!in_array($file['type'], $allowedTypes)) {
        // Handle error...
        exit;
    }
    if ($file['size'] > $maxSize) {
        // Handle error...
        exit;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('mapel_', true) . '.' . $ext;
    $uploadDir = realpath(__DIR__ . '/../../uploads/mapel');
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("INSERT INTO mapel (nama_mapel, photo, kurikulum) VALUES (?, ?, ?)");
        $stmt->execute([$nama, $filename, $kurikulum]);
    }

    header("Location: index.php");
    exit;
}

// Logika Edit Mata Pelajaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_mapel'])) {
    $id = $_POST['id'];
    $nama = trim($_POST['nama_mapel']);
    $kurikulum = $_POST['kurikulum'];
    $file = $_FILES['photo'];

    $photo_filename = $_POST['old_photo']; // Simpan foto lama

    // Cek jika ada foto baru diupload
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {

            // Hapus foto lama jika ada
            if (!empty($photo_filename)) {
                $old_photo_path = '../../uploads/mapel/' . $photo_filename;
                if (file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }
            }

            // Upload foto baru
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('mapel_', true) . '.' . $ext;
            $uploadDir = realpath(__DIR__ . '/../../uploads/mapel');
            $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $photo_filename = $filename; // Update nama file foto
            }
        }
    }

    $stmt = $pdo->prepare("UPDATE mapel SET nama_mapel = ?, photo = ?, kurikulum = ? WHERE id = ?");
    $stmt->execute([$nama, $photo_filename, $kurikulum, $id]);

    header("Location: index.php");
    exit;
}

// logika hapus mata pelajaran
// Cek apakah ada permintaan untuk menghapus mata pelajaran
if (isset($_GET['action']) && $_GET['action'] === 'hapus' && isset($_GET['id'])) {
    $id = $_GET['id'];

    // Ambil data mata pelajaran berdasarkan ID untuk mendapatkan foto yang terkait
    $stmt = $pdo->prepare("SELECT * FROM mapel WHERE id = ?");
    $stmt->execute([$id]);
    $mapel = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mapel) {
        // Hapus foto dari server jika ada
        $photo_filename = $mapel['photo'];
        if (!empty($photo_filename)) {
            $old_photo_path = '../../uploads/mapel/' . $photo_filename;
            if (file_exists($old_photo_path)) {
                unlink($old_photo_path); // Hapus file foto
            }
        }

        // Hapus data mata pelajaran dari database
        $stmt = $pdo->prepare("DELETE FROM mapel WHERE id = ?");
        $stmt->execute([$id]);

        // Redirect setelah penghapusan
        header("Location: index.php");
        exit;
    } else {
        // Mata pelajaran tidak ditemukan
        echo "Mata pelajaran tidak ditemukan.";
    }
}


// Ambil data mata pelajaran untuk ditampilkan
$mapel = $pdo->query("SELECT * FROM mapel")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>Mata Pelajaran</title>
    <link rel="stylesheet" href="../../assets/adminpage.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../../uploads/icon/logo.png" alt="Logo SuperAdminCoy" class="logo-icon">
            <span class="logo-text">SuperAdmin</span>
        </div>
        <nav>
            <a href="../dashboard_superadmin.php">
                <div class="hovertext" data-hover="dashboard"><i class="fas fa-tachometer-alt"></div></i><span>Dashboard</span></a>
            <a href="../admin/index.php">
                <div class="hovertext" data-hover="Admin"><i class="fas fa-users-cog"></div></i><span>Admin</span></a>
            <a href="../guru/index.php">
                <div class="hovertext" data-hover="Guru"><i class="fas fa-chalkboard-teacher"></div></i><span>Guru</span></a>
            <a href="../siswa/index.php">
                <div class="hovertext" data-hover="Siswa"><i class="fas fa-user-graduate"></div></i><span>Siswa</span></a>
            <a href="../jadwal/index.php">
                <div class="hovertext" data-hover="Jadwal"><i class="fas fa-calendar-alt"></div></i><span>Jadwal</span></a>
            <a href="../tahun_akademik/index.php">
                <div class="hovertext" data-hover="Tahun Akademik"><i class="fas fa-calendar"></div></i><span>Tahun Akademik</span></a>
            <a href="../kelas/index.php">
                <div class="hovertext" data-hover="Kelas"><i class="fas fa-school"></div></i><span>Kelas</span></a>
            <a href=#" class="active">
                <div class="hovertext" data-hover="Mata Pelajaran"><i class="fas fa-book"></div></i><span>Mata Pelajaran</span></a>
        </nav>
        <div class="logout-button-container hovertext" data-hover="Logout">
            <a onclick="showLogoutConfirm(event)">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-book"></i> Manajemen Mata Pelajaran</h1>
        <div class="user-info" id="userInfoDropdown">
            <span><?= $superadmin_name ?></span>
            <div class="dropdown-menu" id="userDropdownContent">
                <!-- <a href="profil_supersuperadmin.php"><i class="fas fa-user-circle"></i> Profil</a> -->
                <a href="#" id="logoutDropdownLink" onclick="showLogoutConfirm(event); return false;"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Mata Pelajaran
            </h2>
            <a class="add-link" onclick="openModal('tambah')">
                <i class="fas fa-plus"></i> Tambah Mapel
            </a>
            <div class="element-grid">
                <?php if (count($mapel) > 0): ?>
                    <?php foreach ($mapel as $m): ?>
                        <div class="element-item">
                            <img src="../../uploads/mapel/<?= htmlspecialchars($m['photo']) ?>" alt="<?= htmlspecialchars($m['nama_mapel']) ?>">
                            <h3><?= htmlspecialchars($m['nama_mapel']) ?></h3>
                            <p>Kurikulum: <?= htmlspecialchars($m['kurikulum']) ?></p>
                            <div class="action-mapel">
                                <a href="#" class="action-edit" onclick="openModal('edit', '<?= htmlspecialchars($m['id']) ?>', '<?= htmlspecialchars($m['nama_mapel']) ?>', '<?= htmlspecialchars($m['kurikulum']) ?>', '<?= htmlspecialchars($m['photo']) ?>')">Edit</a>
                                <a href="#" class="action-delete" onclick="openDeleteModal(<?php echo htmlspecialchars($m['id']); ?>); return false;">Hapus</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Belum ada data mata pelajaran.</p>
                <?php endif; ?>
            </div>
            
            <div id="mapelModal" class="modal">
                <div class="modal-content">
                    <span class="close-btn" onclick="closeModal()">&times;</span>
                    <h2 id="modalTitle"></h2>
                    <form id="mapelForm" action="index.php" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            <input type="hidden" name="id" id="mapelId">
                        </div>

                        <div class="form-group">
                            <input type="hidden" name="old_photo" id="oldPhoto">
                        </div>

                        <div class="form-group">
                            <label>Nama Mapel: <input type="text" name="nama_mapel" id="nama_mapel" required></label>
                        </div>
                        
                        <div id="fotoLamaContainer" class="image-preview" style="display:none;">
                            <label>Foto Lama:<br>
                                <img id="fotoLama" src="" alt="Foto Lama">
                            </label>
                        </div>

                        <div class="form-group">
                            <label>Foto: <input type="file" name="photo" id="photoInput" accept="image/*"></label>
                        </div>

                        <div class="form-group">
                            <label>Kurikulum: 
                                <select name="kurikulum" id="kurikulum" required>
                                    <option value="K13">K13</option>
                                    <option value="KTSP">KTSP</option>
                                    <option value="Merdeka">Merdeka</option>
                                </select>
                            </label>
                        </div>
                        <div class="form-actions">
                            <button type="submit" id="submitButton" class="btn-primary"></button>
                            <button type="button" class="btn-secondary" onclick="closeModal()">Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script>
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

        // --- penerapan sidebarnya iki state dari Local Storage ---
        const savedState = localStorage.getItem('sidebarState');
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

        const mapelModal = document.getElementById('mapelModal');
        const modalTitle = document.getElementById('modalTitle');
        const mapelForm = document.getElementById('mapelForm');
        const mapelId = document.getElementById('mapelId');
        const namaMapelInput = document.getElementById('nama_mapel');
        const kurikulumSelect = document.getElementById('kurikulum');
        const photoInput = document.getElementById('photoInput');
        const submitButton = document.getElementById('submitButton');
        const fotoLamaContainer = document.getElementById('fotoLamaContainer');
        const fotoLama = document.getElementById('fotoLama');
        const oldPhotoInput = document.getElementById('oldPhoto');

        function openModal(action, id = '', nama = '', kurikulum = '', photo = '') {
            mapelModal.style.display = 'block';

            if (action === 'tambah') {
                modalTitle.textContent = 'Tambah Mata Pelajaran';
                mapelForm.action = 'index.php';
                submitButton.name = 'tambah_mapel';
                submitButton.textContent = 'Simpan';
                mapelId.value = '';
                namaMapelInput.value = '';
                kurikulumSelect.value = 'K13';
                photoInput.required = true;
                fotoLamaContainer.style.display = 'none';
                oldPhotoInput.value = '';
            } else if (action === 'edit') {
                modalTitle.textContent = 'Edit Mata Pelajaran';
                mapelForm.action = 'index.php';
                submitButton.name = 'edit_mapel';
                submitButton.textContent = 'Update';
                mapelId.value = id;
                namaMapelInput.value = nama;
                kurikulumSelect.value = kurikulum;
                photoInput.required = false;
                fotoLamaContainer.style.display = 'block';
                fotoLama.src = `../../uploads/mapel/${photo}`;
                oldPhotoInput.value = photo;
            }
        }

        function closeModal() {
            mapelModal.style.display = 'none';
            mapelForm.reset(); // Reset form saat modal ditutup
        }

        function showLogoutConfirm() {
            Swal.fire({
                title: 'Konfirmasi Logout',
                text: 'Apakah kamu yakin ingin logout?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Logout!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "../../logout.php"; // redirect logout
                }
            });
        }

        function openDeleteModal(id) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: 'Menghapus mata pelajaran ini akan menghapus data yang terkait!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#3498db',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `index.php?action=hapus&id=${id}`;
                }
            });
        }

        // Tutup modal jika user klik di luar area modal
        window.onclick = function(event) {
            if (event.target === mapelModal) {
                closeModal();
            }
        }
    </script>
</body>
</html>