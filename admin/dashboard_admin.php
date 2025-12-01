<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require '../koneksi.php';

// Ambil nama dan foto admin jika ada di database
$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin');
$admin_photo = 'default_admin.jpg'; // opsional, bisa dari DB juga

// Fungsi getCount universal
function getCount($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $count = $result ? mysqli_fetch_assoc($result)['count'] : 0;
    return $count;
}

// Hitung total
$total_siswa = getCount($conn, "SELECT COUNT(*) AS count FROM siswa");
$total_guru = getCount($conn, "SELECT COUNT(*) AS count FROM guru");
$total_kelas = getCount($conn, "SELECT COUNT(*) AS count FROM class");
$total_mapel = getCount($conn, "SELECT COUNT(*) AS count FROM mapel");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Admin</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../assets/adminpage.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <img src="../uploads/icon/logo.png" alt="Logo AdminCoy" class="logo-icon">
            <span class="logo-text">AdminCoy</span>
        </div>
        <nav>
            <a href="#" class="active">
                <div class="hovertext" data-hover="dashboard"><i class="fas fa-tachometer-alt"></div></i><span>Dashboard</span></a>
            <a href="guru/index.php">
                <div class="hovertext" data-hover="Guru"><i class="fas fa-chalkboard-teacher"></div></i><span>Guru</span></a>
            <a href="siswa/index.php">
                <div class="hovertext" data-hover="Siswa"><i class="fas fa-user-graduate"></div></i><span>Siswa</span></a>
            <a href="jadwal/index.php">
                <div class="hovertext" data-hover="Jadwal"><i class="fas fa-calendar-alt"></div></i><span>Jadwal</span></a>
            <a href="tahun_akademik/index.php">
                <div class="hovertext" data-hover="Tahun Akademik"><i class="fas fa-calendar"></div></i><span>Tahun Akademik</span></a>
            <a href="kelas/index.php">
                <div class="hovertext" data-hover="Kelas"><i class="fas fa-school"></div></i><span>Kelas</span></a>
            <a href="mapel/index.php">
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
        <h1><i class="fas fa-tachometer-alt"></i> Dashboard Admin</h1>
        <div class="user-info">
            <span><?= $admin_name ?></span>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Ringkasan Data</h2>
            <div class="dashboard-stats-grid">
                <div class="stat-card green">
                    <div class="icon"><i class="fas fa-user-graduate"></i></div>
                    <p class="value"><?= $total_siswa ?></p>
                    <p class="label">Jumlah Siswa</p>
                </div>
                <div class="stat-card blue">
                    <div class="icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <p class="value"><?= $total_guru ?></p>
                    <p class="label">Jumlah Guru</p>
                </div>
                <div class="stat-card orange">
                    <div class="icon"><i class="fas fa-school"></i></div>
                    <p class="value"><?= $total_kelas ?></p>
                    <p class="label">Jumlah Kelas</p>
                </div>
                <div class="stat-card red">
                    <div class="icon"><i class="fas fa-book"></i></div>
                    <p class="value"><?= $total_mapel ?></p>
                    <p class="label">Jumlah Mapel</p>
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
                    window.location.href = "../logout.php"; // redirect logout
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
