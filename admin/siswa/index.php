<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}
require '../../koneksi.php';

// Ambil data untuk dropdown kelas
$kelas_list = $pdo->query("SELECT id, nama_kelas FROM class ORDER BY nama_kelas ASC")->fetchAll(PDO::FETCH_ASSOC);

// Tangani AJAX POST untuk menambah atau mengedit siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_siswa') {
        $NIS      = $_POST['NISsiswa'];
        $name     = $_POST['namasiswa'];
        $email    = $_POST['emailsiswa'];
        $gender   = $_POST['gender'];
        $dob      = $_POST['dobsiswa'];
        $no_hp    = $_POST['nohpsiswa'];
        $alamat   = $_POST['alamatsiswa'];
        $class_id = $_POST['class_id'] ?? null;

        if (isset($_FILES['photosiswa']) && $_FILES['photosiswa']['error'] === UPLOAD_ERR_OK) {
            $foto = $_FILES['photosiswa']['name'];
            $tmp  = $_FILES['photosiswa']['tmp_name'];
            $folder = "../../uploads/siswa/";
            $ext  = pathinfo($foto, PATHINFO_EXTENSION);
            $namaFotoBaru = uniqid() . '.' . $ext;

            if (move_uploaded_file($tmp, $folder . $namaFotoBaru)) {
                $stmtOld = $pdo->prepare("SELECT photo FROM siswa WHERE NIS = ?");
                $stmtOld->execute([$NIS]);
                $oldData = $stmtOld->fetch(PDO::FETCH_ASSOC);
                if ($oldData && !empty($oldData['photo'])) {
                    $oldFilePath = $folder . $oldData['photo'];
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $stmt = $pdo->prepare("UPDATE siswa SET photo = ? WHERE NIS = ?");
                $stmt->execute([$namaFotoBaru, $NIS]);
            }
        }

        $stmt = $pdo->prepare("UPDATE siswa SET name = ?, email = ?, gender = ?, dob = ?, alamat = ?, no_hp = ?, class_id = ? WHERE NIS = ?");
        $stmt->execute([$name, $email, $gender, $dob, $alamat, $no_hp, $class_id, $NIS]);

        echo "success";
        exit;
    } elseif ($_POST['action'] === 'tambah_siswa') {
        $name     = $_POST['namasiswa'];
        $email    = $_POST['emailsiswa'];
        $password = password_hash($_POST['passwordsiswa'], PASSWORD_DEFAULT);
        $NIS      = $_POST['NISsiswa'];
        $gender   = $_POST['gender'];
        $dob      = $_POST['dobsiswa'];
        $no_hp    = $_POST['nohpsiswa'];
        $alamat   = $_POST['alamatsiswa'];
        $class_id = $_POST['class_id'] ?? null;
        $admission_date = date('Y-m-d H:i:s');

        $namaFotoBaru = '';
        if (isset($_FILES['photosiswa']) && $_FILES['photosiswa']['error'] === UPLOAD_ERR_OK) {
            $foto = $_FILES['photosiswa']['name'];
            $tmp  = $_FILES['photosiswa']['tmp_name'];
            $folder = "../../uploads/siswa/";
            $ext  = pathinfo($foto, PATHINFO_EXTENSION);
            $namaFotoBaru = uniqid() . '.' . $ext;
            move_uploaded_file($tmp, $folder . $namaFotoBaru);
        }

        $stmt = $pdo->prepare("INSERT INTO siswa
            (NIS, name, gender, dob, photo, no_hp, email, pass, alamat, admission_date, class_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $NIS,
            $name,
            $gender,
            $dob,
            $namaFotoBaru,
            $no_hp,
            $email,
            $password,
            $alamat,
            $admission_date,
            $class_id,
        ]);
        echo "success";
        exit;
    }
}

// --- Logika GET untuk menampilkan data siswa (termasuk fitur pencarian) ---

$search_query = $_GET['search'] ?? '';

$query = "
    SELECT
        siswa.*,
        class.nama_kelas,
        class.photo AS class_photo
    FROM
        siswa
    LEFT JOIN
        class ON siswa.class_id = class.id
";

$params = [];
$conditions = [];

if (!empty($search_query)) {
    $conditions[] = "siswa.name LIKE ? OR class.nama_kelas LIKE ?";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(' AND ', $conditions);
}

$query .= " ORDER BY siswa.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$siswalist = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="../../assets/admin.css">
    <title>Daftar Siswa</title>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">AdminCoy</div>
        <nav>
            <a href="../dashboard_admin.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../guru/index.php">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Guru</span>
            </a>
            <a href="index.php" class="active">
                <i class="fas fa-user-graduate"></i>
                <span>Siswa</span>
            </a>
            <a href="../jadwal/index.php">
                <i class="fas fa-calendar-alt"></i>
                <span>Jadwal</span>
            </a>
            <a href="../kelas/index.php">
                <i class="fas fa-school"></i>
                <span>Kelas</span>
            </a>
            <a href="../mapel/index.php">
                <i class="fas fa-book"></i>
                <span>Mata Pelajaran</span>
            </a>
            <a  onclick="showLogoutConfirm()">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <div class="header" id="header">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <h1>Daftar Siswa</h1>
    </div>

    <div class="content" id="mainContent">
        <div class="card">
            <h2>Data Siswa</h2>
            <div class="card-header-actions">
                <button class="add-link" id="btn-tambah-siswa">
                    <i class="fas fa-plus"></i> Tambah Siswa
                </button>
                <form class="search-form" action="" method="get">
                    <input type="text" name="search" placeholder="Cari siswa atau kelas..." value="<?= htmlspecialchars($search_query) ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
            <ul class="siswa-list" id="siswa-list">
                <?php foreach ($siswalist as $siswa): ?>
                <li
                    data-nis="<?= htmlspecialchars($siswa['NIS']) ?>"
                    data-nama="<?= htmlspecialchars($siswa['name']) ?>"
                    data-gender="<?= htmlspecialchars($siswa['gender']) ?>"
                    data-dob="<?= htmlspecialchars($siswa['dob']) ?>"
                    data-nohp="<?= htmlspecialchars($siswa['no_hp']) ?>"
                    data-email="<?= htmlspecialchars($siswa['email']) ?>"
                    data-alamat="<?= htmlspecialchars($siswa['alamat']) ?>"
                    data-photo="<?= htmlspecialchars($siswa['photo']) ?>"
                    data-class-id="<?= htmlspecialchars($siswa['class_id']) ?>"
                >
                    <div class="siswa-info">
                        <img
                            src="../../uploads/siswa/<?= htmlspecialchars($siswa['photo']) ?>"
                            alt="Foto <?= htmlspecialchars($siswa['name']) ?>"
                            loading="lazy"
                            onerror="this.onerror=null;this.src='https://placehold.co/60x60/cccccc/333333?text=No+Foto';"
                        >
                        <div class="siswa-text">
                            <span class="siswa-nama"><?= htmlspecialchars($siswa['name']) ?></span>
                            <?php if (!empty($siswa['nama_kelas'])): ?>
                                <span class="siswa-kelas-nama"> (<?= htmlspecialchars($siswa['nama_kelas']) ?>)</span>
                            <?php endif; ?>
                            <span class="siswa-email"><?= htmlspecialchars($siswa['email']) ?></span>
                        </div>
                    </div>
                    <div class="siswa-actions">
                        <button class="action-link edit btn-edit">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="action-link delete btn-hapus" data-nis="<?= urlencode($siswa['NIS']) ?>">
                            <i class="fas fa-trash-alt"></i> Hapus
                        </button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div id="siswa-modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">Edit Data Siswa</h3>
            <form id="siswa-form" enctype="multipart/form-data">
                <label for="namasiswa">Nama Siswa:</label>
                <input type="text" id="namasiswa" name="namasiswa" required>

                <label for="NISsiswa">NIS:</label>
                <input type="text" id="NISsiswa" name="NISsiswa" required>

                <label for="class_id">Kelas:</label>
                <select id="class_id" name="class_id">
                    <option value="">Pilih Kelas</option>
                    <?php foreach ($kelas_list as $kelas): ?>
                    <option value="<?= htmlspecialchars($kelas['id']) ?>">
                        <?= htmlspecialchars($kelas['nama_kelas']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <label>Jenis Kelamin:</label>
                <div class="gender-group">
                    <input type="radio" id="male" name="gender" value="Laki-Laki">
                    <label for="male">Laki-Laki</label>
                    <input type="radio" id="female" name="gender" value="Perempuan">
                    <label for="female">Perempuan</label>
                </div>

                <label for="dobsiswa">Tanggal Lahir:</label>
                <input type="date" id="dobsiswa" name="dobsiswa">

                <label for="photosiswa">Foto Siswa:</label>
                <input type="file" id="photosiswa" name="photosiswa">

                <label for="nohpsiswa">Nomor HP:</label>
                <input type="text" id="nohpsiswa" name="nohpsiswa">

                <label for="emailsiswa">Email:</label>
                <input type="email" id="emailsiswa" name="emailsiswa">

                <label for="passwordsiswa" id="labelPasswordsiswa" style="display:none;">Password:</label>
                <input type="password" id="passwordsiswa" name="passwordsiswa" style="display:none;">

                <label for="alamatsiswa">Alamat:</label>
                <input type="text" id="alamatsiswa" name="alamatsiswa">

                <div class="modal-buttons">
                    <button type="submit" class="btn-save">Simpan</button>
                    <button type="button" class="btn-close" id="btn-cancel">Batal</button>
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
        // Sidebar toggle logic
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");

        function toggleSidebar() {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
        }

        // Modal logic
        const siswaModal = document.getElementById("siswa-modal");
        const btnTambahsiswa = document.getElementById("btn-tambah-siswa");
        const siswaList = document.getElementById("siswa-list");
        const modalTitle = document.getElementById("modal-title");
        const siswaForm = document.getElementById("siswa-form");
        const btnCancel = document.getElementById("btn-cancel");

        let isEditMode = false;
        let currentsiswaItem = null;

        // Custom Alert/Confirmation functions
        const customAlertModal = document.getElementById("custom-alert-modal");
        const customAlertMessage = document.getElementById("custom-alert-message");
        const customAlertOkBtn = document.getElementById("custom-alert-ok");
        const customAlertCancelBtn = document.getElementById("custom-alert-cancel");
        let customAlertResolve;

        function showCustomAlert(message) {
            return new Promise(resolve => {
                customAlertMessage.textContent = message;
                customAlertOkBtn.style.display = 'block';
                customAlertCancelBtn.style.display = 'none';
                customAlertModal.style.display = 'flex';
                customAlertResolve = resolve;
            });
        }

        function showCustomConfirm(message) {
            return new Promise(resolve => {
                customAlertMessage.textContent = message;
                customAlertOkBtn.style.display = 'block';
                customAlertCancelBtn.style.display = 'block';
                customAlertModal.style.display = 'flex';
                customAlertResolve = resolve;
            });
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

        customAlertOkBtn.addEventListener('click', () => {
            customAlertModal.style.display = 'none';
            customAlertResolve(true);
        });

        customAlertCancelBtn.addEventListener('click', () => {
            customAlertModal.style.display = 'none';
            customAlertResolve(false);
        });

        // Event listener for "Tambah Siswa" button
        btnTambahsiswa.addEventListener("click", () => {
            isEditMode = false;
            modalTitle.textContent = "Tambah Data Siswa";
            siswaForm.reset();
            document.getElementById("NISsiswa").readOnly = false;
            document.getElementById("passwordsiswa").style.display = "block";
            document.getElementById("labelPasswordsiswa").style.display = "block";
            siswaModal.style.display = "flex";
        });

        // Event listener for "Edit" buttons
        siswaList.addEventListener("click", (e) => {
            if (e.target.classList.contains("btn-edit") || e.target.closest(".btn-edit")) {
                isEditMode = true;
                modalTitle.textContent = "Edit Data Siswa";
                currentsiswaItem = e.target.closest("li");

                // Populate form fields
                document.getElementById("namasiswa").value = currentsiswaItem.dataset.nama;
                document.getElementById("NISsiswa").value = currentsiswaItem.dataset.nis;
                document.getElementById("NISsiswa").readOnly = true;
                document.getElementById("dobsiswa").value = currentsiswaItem.dataset.dob;
                document.getElementById("nohpsiswa").value = currentsiswaItem.dataset.nohp;
                document.getElementById("emailsiswa").value = currentsiswaItem.dataset.email;
                document.getElementById("alamatsiswa").value = currentsiswaItem.dataset.alamat;
                document.getElementById("class_id").value = currentsiswaItem.dataset.classId;

                // Set gender radio button
                if (currentsiswaItem.dataset.gender && currentsiswaItem.dataset.gender.trim().toLowerCase() === "laki-laki") {
                    document.getElementById("male").checked = true;
                } else if (currentsiswaItem.dataset.gender && currentsiswaItem.dataset.gender.trim().toLowerCase() === "perempuan") {
                    document.getElementById("female").checked = true;
                }

                // Hide password field for edit mode
                document.getElementById("passwordsiswa").style.display = "none";
                document.getElementById("labelPasswordsiswa").style.display = "none";
                siswaModal.style.display = "flex";
            }
        });

        // Event listener for "Hapus" buttons
        siswaList.addEventListener("click", async (e) => {
            if (e.target.classList.contains("btn-hapus") || e.target.closest(".btn-hapus")) {
                const btnHapus = e.target.closest(".btn-hapus");
                const nis = btnHapus.dataset.nis;
                const confirmed = await showCustomConfirm('Yakin ingin menghapus data siswa ini?');

                if (confirmed) {
                    window.location.href = `hapus.php?NIS=${nis}`;
                }
            }
        });


        // Event listener for "Batal" button in modal
        btnCancel.addEventListener("click", () => {
            siswaModal.style.display = "none";
        });

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            if (event.target == siswaModal) {
                siswaModal.style.display = "none";
            }
        };

        // Form submission logic
        siswaForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const formData = new FormData(siswaForm);
            if (isEditMode) {
                formData.append('action', 'edit_siswa');
                formData.append('NISsiswa', currentsiswaItem.dataset.nis);
            } else {
                formData.append('action', 'tambah_siswa');
            }

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(async result => {
                if (result.trim() === "success") {
                    await showCustomAlert(isEditMode ? "Siswa berhasil diupdate!" : "Siswa berhasil ditambahkan!");
                    window.location.reload();
                } else {
                    await showCustomAlert("Gagal: " + result);
                }
            })
            .catch(async error => {
                await showCustomAlert("Terjadi kesalahan: " + error);
            });
        });
    </script>
</body>
</html>