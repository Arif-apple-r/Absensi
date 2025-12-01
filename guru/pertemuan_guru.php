<?php
session_start();

// Validasi otorisasi
if (!isset($_SESSION['guru_id']) || empty($_SESSION['guru_id'])) {
    header("Location: ../login.php");
    exit;
}

$guru_id = $_SESSION['guru_id'];
$guru_name = htmlspecialchars($_SESSION['guru_name'] ?? 'Guru');
$guru_photo_session = htmlspecialchars($_SESSION['guru_photo'] ?? '');

require '../koneksi.php';

// Fungsi untuk mendapatkan ID tahun akademik aktif
function getActiveTahunAkademikId($pdo) {
    try {
        $stmt = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['id'] ?? null;
    } catch (PDOException $e) {
        error_log("Error get active academic year: " . $e->getMessage());
        return null;
    }
}

// Ambil id_jadwal
$id_jadwal = filter_input(INPUT_GET, 'id_jadwal', FILTER_SANITIZE_NUMBER_INT);
if (!$id_jadwal) {
    header("Location: jadwal_guru.php?error=" . urlencode("ID Jadwal tidak valid."));
    exit;
}

// Cek apakah jadwal sesuai tahun akademik aktif
$can_edit = false;
$active_tahun_akademik_id = getActiveTahunAkademikId($pdo);

try {
    $stmt_tahun_id = $pdo->prepare("SELECT c.id_tahun_akademik FROM jadwal j 
        LEFT JOIN class c ON j.class_id = c.id WHERE j.id = ?");
    $stmt_tahun_id->execute([$id_jadwal]);
    $jadwal_tahun_id = $stmt_tahun_id->fetchColumn();
    if ($jadwal_tahun_id == $active_tahun_akademik_id) {
        $can_edit = true;
    }
} catch (PDOException $e) {
    error_log("Error checking tahun akademik jadwal: " . $e->getMessage());
}

$success = '';
$error = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_edit) {
        $error = "Tidak dapat menambah atau mengedit pertemuan untuk tahun akademik yang tidak aktif.";
    } else {
        $id_pertemuan = filter_input(INPUT_POST, 'id_pertemuan', FILTER_SANITIZE_NUMBER_INT);
        $tanggal = filter_input(INPUT_POST, 'tanggal', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $topik = filter_input(INPUT_POST, 'topik', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $id_jadwal_form = filter_input(INPUT_POST, 'id_jadwal', FILTER_SANITIZE_NUMBER_INT);

        if (empty($tanggal) || empty($topik) || empty($id_jadwal_form)) {
            $error = "Tanggal dan Topik wajib diisi.";
        } else {
            try {
                if ($id_pertemuan) {
                    // Update
                    $stmt = $pdo->prepare("UPDATE pertemuan 
                        SET tanggal = ?, topik = ? 
                        WHERE id = ? AND id_jadwal = ?");
                    $stmt->execute([$tanggal, $topik, $id_pertemuan, $id_jadwal_form]);
                    $success = "Pertemuan berhasil diperbarui.";
                } else {
                    // Insert
                    $stmt = $pdo->prepare("INSERT INTO pertemuan (id_jadwal, tanggal, topik) 
                        VALUES (?, ?, ?)");
                    $stmt->execute([$id_jadwal_form, $tanggal, $topik]);
                    $success = "Pertemuan baru berhasil ditambahkan.";
                }

                // Redirect supaya gak double-submit
                header("Location: pertemuan_guru.php?id_jadwal=" . urlencode($id_jadwal_form) 
                    . "&success=" . urlencode($success));
                exit;
            } catch (PDOException $e) {
                $error = "Gagal memproses pertemuan: " . $e->getMessage();
            }
        }
    }
}

// Ambil pesan sukses/error dari URL
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// Ambil data jadwal
try {
    $stmt_jadwal_info = $pdo->prepare("
        SELECT j.hari, j.jam_mulai, j.jam_selesai,
               g.name AS nama_guru, g.photo AS guru_photo,
               m.nama_mapel, c.nama_kelas
        FROM jadwal j
        JOIN guru g ON j.teacher_id = g.id
        LEFT JOIN mapel m ON j.id_mapel = m.id
        LEFT JOIN class c ON j.class_id = c.id
        WHERE j.id = ? AND j.teacher_id = ?
    ");
    $stmt_jadwal_info->execute([$id_jadwal, $guru_id]);
    $jadwal_info = $stmt_jadwal_info->fetch(PDO::FETCH_ASSOC);

    if (!$jadwal_info) {
        header("Location: jadwal_guru.php?error=" . urlencode("Jadwal tidak ditemukan atau tidak punya akses."));
        exit;
    }

    $guru_photo = htmlspecialchars($jadwal_info['guru_photo']);
} catch (PDOException $e) {
    header("Location: jadwal_guru.php?error=" . urlencode("Kesalahan ambil data jadwal."));
    exit;
}

// Ambil daftar pertemuan
try {
    $stmt_pertemuan = $pdo->prepare("SELECT id, tanggal, topik 
        FROM pertemuan WHERE id_jadwal = ? ORDER BY tanggal DESC");
    $stmt_pertemuan->execute([$id_jadwal]);
    $list_pertemuan = $stmt_pertemuan->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pertemuan: " . $e->getMessage());
    $list_pertemuan = [];
    $error = "Gagal memuat daftar pertemuan.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Pertemuan Guru | <?= htmlspecialchars($jadwal_info['nama_mapel']) ?> Kelas <?= htmlspecialchars($jadwal_info['nama_kelas']) ?></title>
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
            <a href="pertemuan_guru.php?id_jadwal=<?= htmlspecialchars($id_jadwal) ?>" class="active">
                <i class="fas fa-clipboard-list"></i>
                <span>Pertemuan</span>
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

    <div class="header" id="header">
        <button class="toggle-btn">
            <i class="fas fa-bars"></i>
        </button>
        <h1><i class="fas fa-clipboard-list"></i> Pertemuan Kelas</h1>
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

    <!-- konten utama -->
    <div class="content" id="mainContent">
        <div class="card">
            <h2>Daftar Pertemuan</h2>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="info-header">
                <p><strong>Kelas:</strong> <?php echo htmlspecialchars($jadwal_info['nama_kelas']); ?></p>
                <p><strong>Mata Pelajaran:</strong> <?php echo htmlspecialchars($jadwal_info['nama_mapel']); ?></p>
                <p><strong>Guru:</strong> <?php echo htmlspecialchars($jadwal_info['nama_guru']); ?></p>
                <p><strong>Jadwal:</strong> <?php echo htmlspecialchars(substr($jadwal_info['hari'], 0, 5)) . ', ' . htmlspecialchars(substr($jadwal_info['jam_mulai'], 0, 5)) . ' - ' . htmlspecialchars(substr($jadwal_info['jam_selesai'], 0, 5)); ?></p>
            </div>

            <?php if ($can_edit): ?>
                <a href="#" onclick="openAddModal()" class="add-link">
                    <i class="fas fa-plus"></i> Tambah Pertemuan
                </a>
            <?php else: ?>
                <div class="alert alert-warning" style="margin:10px 0;">
                    Tahun akademik ini <b>tidak aktif</b>. Anda tidak dapat menambah pertemuan baru.
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="tanggal-column">Tanggal</th>
                            <th class="topik-column">Topik</th>
                            <th class="aksi-column">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($list_pertemuan)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">Belum ada pertemuan untuk jadwal ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($list_pertemuan as $pertemuan): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pertemuan['tanggal']); ?></td>
                                    <td><?php echo htmlspecialchars($pertemuan['topik']); ?></td>
                                    <td>
                                        <div class="action-links">
                                            <?php if ($can_edit): ?>
                                                <a href="#" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($pertemuan), ENT_QUOTES, 'UTF-8'); ?>)" class="action-link edit" title="Edit Pertemuan">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="#" onclick="openDeleteModal(<?php echo htmlspecialchars($pertemuan['id']); ?>)" class="action-link delete" title="Hapus Pertemuan">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                <!-- kalau tidak aktif, edit & hapus hilang -->
                                                <span class="text-muted" title="Tahun akademik tidak aktif"><i class="fas fa-lock"></i></span>
                                            <?php endif; ?>

                                            <!-- Isi Absensi tetap boleh dilihat, tapi bisa dikondisikan juga -->
                                            <a href="absensi_guru.php?id_pertemuan=<?php echo htmlspecialchars($pertemuan['id']); ?>" class="action-link absensi" title="Isi Absensi">
                                                <i class="fas fa-check-circle"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <a href="jadwal_guru.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Kembali ke Jadwal Mengajar
            </a>
        </div>
    </div>

    <div id="pertemuanModal" class="modal">
      <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Tambah Pertemuan</h2>

        <form method="POST" autocomplete="off" id="pertemuanForm">
            <input type="hidden" name="id_pertemuan" id="pertemuan_id">
            <input type="hidden" name="id_jadwal" value="<?php echo htmlspecialchars($id_jadwal); ?>">

            <label for="tanggal">Tanggal:</label>
            <input type="date" name="tanggal" id="tanggal" required <?php echo !$can_edit ? 'disabled' : ''; ?>>

            <label for="topik">Topik Pertemuan:</label>
            <textarea name="topik" id="topik" required <?php echo !$can_edit ? 'disabled' : ''; ?>></textarea>

            <?php if ($can_edit): ?>
                <button type="submit">Simpan Pertemuan</button>
            <?php else: ?>
                <button type="button" disabled>Tidak Bisa Disimpan</button>
            <?php endif; ?>
        </form>
      </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeDeleteModal()">&times;</span>
            <h2>Konfirmasi Hapus</h2>
            <p>Apakah Anda yakin ingin menghapus pertemuan ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="modal-buttons">
                <button id="confirmDeleteBtn">Ya, Hapus</button>
                <button id="cancelDeleteBtn" onclick="closeDeleteModal()">Batal</button>
            </div>
        </div>
    </div>

<script>
        // Logika untuk toggle sidebar
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");
        const toggleButton = document.querySelector('.toggle-btn');
        const sidebarLinks = document.querySelectorAll('.sidebar nav a');

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

        // Pasang event listener saat dokumen dimuat
        document.addEventListener('DOMContentLoaded', () => {
            // Memastikan toggleButton ada sebelum menambahkan event listener
            if (toggleButton) {
                toggleButton.addEventListener('click', toggleSidebar);
            }

            // Set active class untuk link sidebar
            const currentPath = window.location.pathname.split('/').pop();
            sidebarLinks.forEach(link => {
                // Perbaikan: Pastikan link memiliki atribut href sebelum membandingkan
                const linkHref = link.getAttribute('href');
                if (linkHref && linkHref.includes(currentPath)) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });

        // Logika Modal Pertemuan (Tambah/Edit)
        const modalPertemuan = document.getElementById("pertemuanModal");
        const modalPertemuanTitle = document.getElementById("modalTitle");
        const formPertemuan = document.getElementById("pertemuanForm");
        const inputPertemuanId = document.getElementById("pertemuan_id");
        const inputTanggal = document.getElementById("tanggal");
        const inputTopik = document.getElementById("topik");

        function openAddModal() {
            modalPertemuan.style.display = "block";
            modalPertemuanTitle.innerText = "Tambah Pertemuan Baru";
            formPertemuan.reset();
            inputPertemuanId.value = '';
        }

        function openEditModal(pertemuan) {
            modalPertemuan.style.display = "block";
            modalPertemuanTitle.innerText = "Edit Pertemuan";
            inputPertemuanId.value = pertemuan.id;
            inputTanggal.value = pertemuan.tanggal;
            inputTopik.value = pertemuan.topik;
        }

        function closeModal() {
            modalPertemuan.style.display = "none";
        }

        // Logika Modal Hapus
        const deleteModal = document.getElementById("deleteModal");
        const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");
        let pertemuanToDeleteId = null;

        function openDeleteModal(id) {
            pertemuanToDeleteId = id;
            deleteModal.style.display = "block";
        }

        function closeDeleteModal() {
            deleteModal.style.display = "none";
        }

        confirmDeleteBtn.onclick = function() {
            window.location.href = `hapus_pertemuan.php?id=${pertemuanToDeleteId}&id_jadwal=<?php echo htmlspecialchars($id_jadwal); ?>`;
        }

        // Tutup modal jika user klik di luar area modal
        window.onclick = function(event) {
            if (event.target == modalPertemuan) {
                closeModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>