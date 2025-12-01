<?php
session_start();
if (!isset($_SESSION['admin_id'])) { // Ganti ke admin_id jika ini untuk admin/jadwal/index.php
    header("Location: ../../login.php");
    exit;
}

require '../../koneksi.php';

$admin_name = htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); // Ganti ke admin_name
$admin_photo = 'https://placehold.co/40x40/cccccc/333333?text=SA'; 

$success = '';
$error = '';

// Ambil daftar Tahun Akademik untuk filter
$stmt_tahun_akademik = $pdo->query("SELECT id, nama_tahun, is_active FROM tahun_akademik ORDER BY nama_tahun DESC");
$tahun_akademik_options = $stmt_tahun_akademik->fetchAll(PDO::FETCH_ASSOC);

// Tentukan tahun akademik yang sedang dipilih (dari GET atau default ke yang aktif)
$selected_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? null;
// --- TAMBAHAN BARU ---
$kelas_filter = $_GET['kelas_filter'] ?? 'all';
$mapel_filter = $_GET['mapel_filter'] ?? 'all';
$hari_filter = $_GET['hari_filter'] ?? 'all';

if ($selected_tahun_akademik_id === null) {
    // Jika tidak ada parameter tahun_akademik_id di URL, ambil yang aktif
    $stmt_active_tahun = $pdo->query("SELECT id FROM tahun_akademik WHERE is_active = 1 LIMIT 1");
    $active_tahun = $stmt_active_tahun->fetch(PDO::FETCH_ASSOC);
    $selected_tahun_akademik_id = $active_tahun['id'] ?? ($tahun_akademik_options[0]['id'] ?? null); // Fallback ke tahun pertama jika tidak ada yang aktif
}

// Pastikan $selected_tahun_akademik_id adalah integer, jika null, set 0 atau handle error
if ($selected_tahun_akademik_id === null) {
    $error = "Tidak ada Tahun Akademik yang ditemukan atau diatur aktif.";
    $selected_tahun_akademik_id = 0; // Set ke 0 agar query tidak crash, tapi data akan kosong
} else {
    $selected_tahun_akademik_id = (int)$selected_tahun_akademik_id;
}


// Ambil data untuk dropdown di form (Kelas, Mapel, Guru)
// Penting: Kelas difilter berdasarkan selected_tahun_akademik_id
$kelas_form_options = [];
if ($selected_tahun_akademik_id) { // Hanya ambil kelas jika ada tahun akademik yang dipilih
    $stmt_kelas_form = $pdo->prepare("SELECT id, nama_kelas FROM class WHERE id_tahun_akademik = ? ORDER BY nama_kelas ASC");
    $stmt_kelas_form->execute([$selected_tahun_akademik_id]);
    $kelas_form_options = $stmt_kelas_form->fetchAll(PDO::FETCH_ASSOC);
}
$mapel_options = $pdo->query("SELECT id, nama_mapel FROM mapel ORDER BY nama_mapel ASC")->fetchAll(PDO::FETCH_ASSOC);
$guru_options = $pdo->query("SELECT id, name FROM guru ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);


// Handle Form Submission (Tambah & Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token validation (Jika Anda menggunakannya, sertakan kembali di sini)
    // if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    //     die('Invalid CSRF token');
    // }

    $id = $_POST['id'] ?? '';
    $class_id = $_POST['class_id'] ?? '';
    $mapel_id = $_POST['mapel_id'] ?? '';
    $hari = $_POST['hari'] ?? '';
    $jam_mulai = $_POST['jam_mulai'] ?? '';
    $jam_selesai = $_POST['jam_selesai'] ?? '';
    $teacher_id = $_POST['teacher_id'] ?? '';
    $submitted_tahun_akademik_id = $_POST['tahun_akademik_id'] ?? $selected_tahun_akademik_id; // Ambil dari hidden input

    if ($class_id && $mapel_id && $hari && $jam_mulai && $jam_selesai && $teacher_id) {
        try {
            // Get mapel name (Tetap menggunakan ini jika kolom 'mata_pelajaran' masih ada di tabel jadwal)
            $stmt_mapel = $pdo->prepare("SELECT nama_mapel FROM mapel WHERE id = ?");
            $stmt_mapel->execute([$mapel_id]);
            $mapel_name = $stmt_mapel->fetchColumn();

            if ($id) {
                // Update existing schedule
                $stmt = $pdo->prepare("UPDATE jadwal SET class_id=?, id_mapel=?, mata_pelajaran=?, hari=?, jam_mulai=?, jam_selesai=?, teacher_id=? WHERE id=?");
                $stmt->execute([$class_id, $mapel_id, $mapel_name, $hari, $jam_mulai, $jam_selesai, $teacher_id, $id]);
                $success = "Jadwal berhasil diupdate!";
            } else {
                // Insert new schedule
                $stmt = $pdo->prepare("INSERT INTO jadwal (class_id, id_mapel, mata_pelajaran, hari, jam_mulai, jam_selesai, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$class_id, $mapel_id, $mapel_name, $hari, $jam_mulai, $jam_selesai, $teacher_id]);
                $success = "Jadwal berhasil ditambahkan!";
            }

            header("Location: index.php?success=" . urlencode($success) . "&tahun_akademik_id=" . $submitted_tahun_akademik_id);
            exit;
        } catch (PDOException $e) {
            $error = "Gagal memproses jadwal: " . $e->getMessage();
        }
    } else {
        $error = "Semua field wajib diisi!";
    }
}

// Handle Delete action
if (isset($_GET['action']) && $_GET['action'] == 'hapus' && isset($_GET['id'])) {
    $id_jadwal = $_GET['id'];
    $current_tahun_akademik_id = $_GET['tahun_akademik_id'] ?? $selected_tahun_akademik_id;

    try {
        // Cek apakah ada pertemuan terkait jadwal ini (dan absensi terkait pertemuan)
        $stmt_check_pertemuan = $pdo->prepare("SELECT COUNT(*) FROM pertemuan WHERE id_jadwal = ?");
        $stmt_check_pertemuan->execute([$id_jadwal]);
        if ($stmt_check_pertemuan->fetchColumn() > 0) {
            $error = "Tidak dapat menghapus Jadwal ini karena masih ada pertemuan yang terkait. Harap hapus pertemuan terlebih dahulu.";
            header("Location: index.php?error=" . urlencode($error) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM jadwal WHERE id = ?");
        $stmt->execute([$id_jadwal]);
        $success = "Jadwal berhasil dihapus!";
        header("Location: index.php?success=" . urlencode($success) . "&tahun_akademik_id=" . $current_tahun_akademik_id);
        exit;
    } catch (PDOException $e) {
        $error = "Gagal menghapus jadwal: " . $e->getMessage();
    }
}

// Fetch all schedule data filtered by selected_tahun_akademik_id
$conditions = ["class.id_tahun_akademik = ?"];
$params = [$selected_tahun_akademik_id];

if ($kelas_filter !== 'all') {
    $conditions[] = "jadwal.class_id = ?";
    $params[] = $kelas_filter;
}

if ($mapel_filter !== 'all') {
    $conditions[] = "jadwal.id_mapel = ?";
    $params[] = $mapel_filter;
}

if ($hari_filter !== 'all') {
    $conditions[] = "jadwal.hari = ?";
    $params[] = $hari_filter;
}

$query = "
    SELECT 
        jadwal.id,
        jadwal.class_id,
        jadwal.id_mapel,
        jadwal.hari,
        jadwal.jam_mulai,
        jadwal.jam_selesai,
        mapel.nama_mapel AS mata_pelajaran,
        guru.name AS nama_guru,
        class.nama_kelas,
        class.photo,
        tahun_akademik.nama_tahun
    FROM jadwal
    JOIN guru ON jadwal.teacher_id = guru.id
    JOIN class ON jadwal.class_id = class.id
    JOIN mapel ON jadwal.id_mapel = mapel.id
    JOIN tahun_akademik ON class.id_tahun_akademik = tahun_akademik.id
    WHERE " . implode(" AND ", $conditions) . "
    ORDER BY FIELD(jadwal.hari, 'Senin','Selasa','Rabu','Kamis','Jumat','Sabtu','Minggu'),
             jadwal.jam_mulai ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$jadwal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Check for success/error messages from redirect
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error']);
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Data untuk openEditModal (jika ada edit action dari redirect)
$jadwal_to_edit = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt_edit = $pdo->prepare("SELECT * FROM jadwal WHERE id = ?");
    $stmt_edit->execute([$id]);
    $jadwal_to_edit = $stmt_edit->fetch(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" 
            integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" 
            crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../../assets/adminpage.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>Jadwal Kelas</title>
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
            <a href="../guru/index.php">
                <div class="hovertext" data-hover="Guru"><i class="fas fa-chalkboard-teacher"></div></i><span>Guru</span></a>
            <a href="../siswa/index.php">
                <div class="hovertext" data-hover="Siswa"><i class="fas fa-user-graduate"></div></i><span>Siswa</span></a>
            <a href="index.php">
                <div class="hovertext" data-hover="Jadwal"><i class="fas fa-calendar-alt"></div></i><span>Jadwal</span></a>
            <a href="../tahun_akademik/index.php">
                <div class="hovertext" data-hover="Tahun Akademik"><i class="fas fa-calendar"></div></i><span>Tahun Akademik</span></a>
            <a href="../kelas/index.php">
                <div class="hovertext" data-hover="Kelas"><i class="fas fa-school"></div></i><span>Kelas</span></a>
            <a href="../mapel/index.php">
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
        <h1><i class="fas fa-calendar-alt"></i>Jadwal Kelas</h1>
        <div class="user-info" id="userInfoDropdown">
            <span><?= $admin_name ?></span>
            <div class="dropdown-menu" id="userDropdownContent">
                <!-- <a href="profil_admin.php"><i class="fas fa-user-circle"></i> Profil</a> -->
                <a href="../../logout.php" id="logoutDropdownLink"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Data Jadwal</h2>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="filter-section">
                <div class="filter-group">
                    <label for="filter-tahun-akademik">Tahun Akademik:</label>
                    <select id="filter-tahun-akademik">
                        <?php if (empty($tahun_akademik_options)): ?>
                            <option value="">Tidak ada Tahun Akademik</option>
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
                    <label for="filter-kelas">Kelas:</label>
                    <select id="filter-kelas">
                        <option value="all">Semua Kelas</option>
                        <?php 
                        // Ambil semua kelas dari tahun akademik yang dipilih untuk filter client-side
                        $all_kelas_for_filter = [];
                        if ($selected_tahun_akademik_id) {
                            $stmt_all_kelas = $pdo->prepare("SELECT id, nama_kelas FROM class WHERE id_tahun_akademik = ? ORDER BY nama_kelas ASC");
                            $stmt_all_kelas->execute([$selected_tahun_akademik_id]);
                            $all_kelas_for_filter = $stmt_all_kelas->fetchAll(PDO::FETCH_ASSOC);
                        }
                        foreach ($all_kelas_for_filter as $k) : ?>
                            <option value="<?= htmlspecialchars($k['id']) ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-mapel">Mata Pelajaran:</label>
                    <select id="filter-mapel">
                        <option value="all">Semua Mapel</option>
                        <?php foreach ($mapel_options as $m) : // Menggunakan mapel_options yang sudah ada ?>
                            <option value="<?= htmlspecialchars($m['id']) ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="filter-hari">Hari:</label>
                    <select id="filter-hari">
                        <option value="all">Semua Hari</option>
                        <?php
                        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']; // Tambah Minggu untuk konsistensi
                        foreach ($days as $d) : ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <button id="reset-filter-btn">Reset Filter</button>
                </div>
            </div>

            <a href="#" onclick="openModal()" class="add-link">
                <i class="fas fa-plus"></i> Tambah Jadwal
            </a>

            <div class="table-container-schedule">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Mata Pelajaran</th>
                            <th>Guru</th>
                            <th>Kelas</th>
                            <th>Foto Kelas</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="jadwalTableBody">
                        <?php if (empty($jadwal_list)): ?>
                            <tr id="noDataRow">
                                <td colspan="7" style="text-align: center;">Tidak ada jadwal untuk filter ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($jadwal_list as $row) : ?>
                                <tr 
                                    data-hari="<?= htmlspecialchars($row['hari']) ?>"
                                    data-kelas="<?= htmlspecialchars($row['class_id']) ?>"
                                    data-mapel="<?= htmlspecialchars($row['id_mapel']) ?>"
                                >
                                    <td><?= htmlspecialchars($row['hari']) ?></td>
                                    <td><?= htmlspecialchars($row['jam_mulai']) ?> - <?= htmlspecialchars($row['jam_selesai']) ?></td>
                                    <td><?= htmlspecialchars($row['mata_pelajaran']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_guru']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_kelas']) ?></td>
                                    <td>
                                        <img src="<?= !empty($row['photo']) 
                                            ? "../../uploads/kelas/".htmlspecialchars($row['photo'])
                                            : "https://placehold.co/100x80/cccccc/333333?text=NO+IMG" ?>"
                                            loading="lazy"
                                            onerror="this.onerror=null;this.src='https://placehold.co/100x80/cccccc/333333?text=NO+IMG';">
                                    </td>
                                    <td>
                                        <div class="action-links">
                                            <a href="#" onclick='openEditModal(<?= json_encode($row) ?>)' class="action-link edit" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>

                                            <a href="#" class="action-link delete" onclick="openDeleteModal(<?= $row['id'] ?>)">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>

                                            <?php
                                                $filter_params = http_build_query([
                                                    'tahun_akademik_id' => $selected_tahun_akademik_id,
                                                    'kelas_filter' => $kelas_filter,
                                                    'mapel_filter' => $mapel_filter,
                                                    'hari_filter' => $hari_filter,
                                                ]);
                                            ?>

                                            <a href="pertemuan.php?id_jadwal=<?= $row['id'] ?>&<?= $filter_params ?>" 
                                            class="action-link view" title="Lihat Pertemuan">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle">Tambah Jadwal</h2>

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="scheduleForm">
                <input type="hidden" name="id" id="jadwal_id">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="tahun_akademik_id" value="<?= htmlspecialchars($selected_tahun_akademik_id); ?>">

                <div class="form-group">
                    <label for="class_id_modal">Kelas:</label>
                    <select name="class_id" id="class_id_modal" required>
                        <option value="">--Pilih--</option>
                        <?php if (empty($kelas_form_options)): ?>
                            <option value="" disabled>Tidak ada kelas untuk tahun akademik ini.</option>
                        <?php else: ?>
                            <?php foreach ($kelas_form_options as $k): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="mapel_id_modal">Mapel:</label>
                    <select name="mapel_id" id="mapel_id_modal" required>
                        <option value="">--Pilih--</option>
                        <?php foreach ($mapel_options as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                    <?php endforeach; ?>
                </select>
                </div>

                <div class="form-group">
                    <label for="hari_modal">Hari:</label>
                    <select name="hari" id="hari_modal" required>
                        <option value="">--Pilih Hari--</option>
                        <?php
                        $days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                        foreach ($days as $d): ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="jam_mulai_modal">Jam Mulai:</label>
                    <input type="time" name="jam_mulai" id="jam_mulai_modal" required>
                </div>

                <div class="form-group">
                    <label for="jam_selesai_modal">Jam Selesai:</label>
                    <input type="time" name="jam_selesai" id="jam_selesai_modal" required>
                </div>

                <div class="form-group">
                    <label for="teacher_id_modal">Guru:</label>
                    <select name="teacher_id" id="teacher_id_modal" required>
                        <option value="">--Pilih--</option>
                        <?php foreach ($guru_options as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Simpan Jadwal</button>
                </div>

            </form>
        </div>
    </div>

    <div id="custom-alert-modal" class="custom-modal-overlay">
        <div class="custom-modal-content">
            <h4 id="custom-alert-message"></h4>
            <div class="modal-buttons">
                <button type="button" class="btn-save" id="custom-alert-ok">OK</button>
                <button type="button" class="btn-close" id="custom-alert-cancel" style="display:none;">Batal</button>
            </div>
        </div>
    </div>

    <script>
        /****************************************
         * DELETE CONFIRM WITH SWEETALERT
         ****************************************/
        function openDeleteModal(id) {
            const tahunId = document.getElementById("filter-tahun-akademik")?.value ?? "";
            Swal.fire({
                title: "Apakah Anda yakin?",
                text: "Jadwal ini akan dihapus. (Catatan: Jadwal tidak bisa dihapus jika masih memiliki data pertemuan terkait).",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#e74c3c",
                cancelButtonColor: "#3498db",
                confirmButtonText: "Ya, Hapus!",
                cancelButtonText: "Batal"
            }).then(result => {
                if (result.isConfirmed) {
                    window.location.href = `index.php?action=hapus&id=${id}&tahun_akademik_id=${tahunId}`;
                }
            });
        }

        /****************************************
         * Toggle Sidebar
         ****************************************/

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

        /****************************************
         * FILTER HANDLING
         ****************************************/
        function applyAllFilters() {
            const tahunId = $("#filter-tahun-akademik").val();
            const kelasVal = $("#filter-kelas").val();
            const mapelVal = $("#filter-mapel").val();
            const hariVal = $("#filter-hari").val();

            const params = new URLSearchParams({
                tahun_akademik_id: tahunId,
                kelas_filter: kelasVal,
                mapel_filter: mapelVal,
                hari_filter: hariVal
            });

            window.location.href = `index.php?${params.toString()}`;
        }

        /****************************************
         * MODAL HANDLING FOR ADD/EDIT JADWAL
         ****************************************/
        const DOM = {
            modal: document.getElementById("scheduleModal"),
            modalTitle: document.getElementById("modalTitle"),
            form: document.getElementById("scheduleForm"),
            inputs: {
                id: document.getElementById("jadwal_id"),
                class: document.getElementById("class_id_modal"),
                mapel: document.getElementById("mapel_id_modal"),
                hari: document.getElementById("hari_modal"),
                jamMulai: document.getElementById("jam_mulai_modal"),
                jamSelesai: document.getElementById("jam_selesai_modal"),
                teacher: document.getElementById("teacher_id_modal"),
            }
        };

        function openModal() {
            DOM.modal.style.display = "block";
            DOM.modalTitle.textContent = "Tambah Jadwal";
            DOM.form.reset();
        }

        function openEditModal(jadwal) {
            DOM.modal.style.display = "block";
            DOM.modalTitle.textContent = "Edit Jadwal";

            DOM.inputs.id.value = jadwal.id;
            DOM.inputs.class.value = jadwal.class_id;
            DOM.inputs.mapel.value = jadwal.id_mapel;
            DOM.inputs.hari.value = jadwal.hari;
            DOM.inputs.jamMulai.value = jadwal.jam_mulai;
            DOM.inputs.jamSelesai.value = jadwal.jam_selesai;
            DOM.inputs.teacher.value = jadwal.teacher_id;
        }

        function closeModal() {
            DOM.modal.style.display = "none";
        }

        document.addEventListener("click", e => {
            if (e.target === DOM.modal) closeModal();
        });

        /****************************************
         * LOGOUT HANDLING
         ****************************************/
        function showLogoutConfirm() {
            Swal.fire({
                title: "Konfirmasi Logout",
                text: "Apakah kamu yakin ingin logout?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ya, Logout!",
                cancelButtonText: "Batal",
            }).then(res => {
                if (res.isConfirmed) {
                    window.location.href = "../../logout.php";
                }
            });
        }

        ["logoutButtonSidebar", "logoutDropdownLink"].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener("click", e => {
                e.preventDefault();
                showLogoutConfirm();
            });
        });

        /****************************************
         * SIDEBAR ACTIVE STATE
         ****************************************/
        window.addEventListener("DOMContentLoaded", () => {
            const current = location.pathname.split("/").pop().split("?")[0];
            document.querySelectorAll(".sidebar nav a").forEach(link => {
                link.classList.toggle("active", link.getAttribute("href") === current);
            });
        });

        /****************************************
         * DOCUMENT READY BLOCK (jQuery)
         ****************************************/
        $(document).ready(function () {

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

            // Init DataTable if exists
            if ($("#myTable").length) $("#myTable").DataTable();

            // Filter change listener
            $("#filter-tahun-akademik, #filter-kelas, #filter-mapel, #filter-hari")
                .on("change", applyAllFilters);

            // Reset filter button
            $("#reset-filter-btn").on("click", () => {
                $("#filter-kelas, #filter-mapel, #filter-hari").val("all");
                applyAllFilters();
            });

            // Restore dropdown filter state from URL
            const urlParams = new URLSearchParams(window.location.search);
            ["kelas_filter", "mapel_filter", "hari_filter"].forEach(key => {
                const element = $(`#filter-${key.replace("_filter","")}`);
                const value = urlParams.get(key) || "all";
                element.val(value);
            });

            // Ensure "Lihat" links carry filter params
            // $(".action-link.view").each(function () {
            //     const base = new URL(this.href, window.location.href);
            //     const updated = new URLSearchParams(window.location.search);
            //     base.search = updated.toString();
            //     this.href = base.toString();
            // });
        });
    </script>
</body>

</html>
