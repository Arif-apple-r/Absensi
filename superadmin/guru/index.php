<?php
session_start();
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../login.php");
    exit;
}
require '../../koneksi.php';

// Check if the user is an admin
$stmt = $pdo->query("SELECT * FROM guru ORDER BY name ASC");
$gurulist = $stmt->fetchAll();

// Handle AJAX POST for adding guru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'edit_guru') {
        $nip      = $_POST['nipGuru'];
        $name     = $_POST['namaGuru'];
        $email    = $_POST['emailGuru'];
        $gender   = $_POST['gender'];
        $dob      = $_POST['dobGuru'];
        $no_hp    = $_POST['nohpGuru'];
        $alamat   = $_POST['alamatGuru'];

        // Handle photo update if a new file is uploaded
        $foto = $_FILES['photoGuru']['name'] ?? '';
        $tmp  = $_FILES['photoGuru']['tmp_name'] ?? '';
        $folder = "../../uploads/guru/";
        $namaFotoBaru = '';

        if ($foto && $tmp) {
            $ext  = pathinfo($foto, PATHINFO_EXTENSION);
            $namaFotoBaru = uniqid() . '.' . $ext;
            move_uploaded_file($tmp, $folder . $namaFotoBaru);

            // Delete old photo
            $stmtOld = $pdo->prepare("SELECT photo FROM guru WHERE nip = ?");
            $stmtOld->execute([$nip]);
            $oldData = $stmtOld->fetch();
            if ($oldData && !empty($oldData['photo'])) {
                $oldFilePath = $folder . $oldData['photo'];
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            // Update photo in DB
            $stmt = $pdo->prepare("UPDATE guru SET photo = ? WHERE nip = ?");
            $stmt->execute([$namaFotoBaru, $nip]);
        }

        // Update other fields
        $stmt = $pdo->prepare("UPDATE guru SET name = ?, email = ?, gender = ?, dob = ?, alamat = ?, no_hp = ? WHERE nip = ?");
        $stmt->execute([$name, $email, $gender, $dob, $alamat, $no_hp, $nip]);

        echo "success";
        exit;
    } elseif ($_POST['action'] === 'tambah_guru') {
        $name     = $_POST['namaGuru'];
        $email    = $_POST['emailGuru'];
        $password = password_hash($_POST['passwordGuru'], PASSWORD_DEFAULT);
        $nip      = $_POST['nipGuru'];
        $gender   = $_POST['gender'];
        $dob      = $_POST['dobGuru'];
        $no_hp    = $_POST['nohpGuru'];
        $alamat   = $_POST['alamatGuru'];
        $admission_date = date('Y-m-d H:i:s');

        // Upload foto
        $foto = $_FILES['photoGuru']['name'] ?? '';
        $tmp  = $_FILES['photoGuru']['tmp_name'] ?? '';
        $folder = "../../uploads/guru/";
        $namaFotoBaru = '';
        if ($foto && $tmp) {
            $ext  = pathinfo($foto, PATHINFO_EXTENSION);
            $namaFotoBaru = uniqid() . '.' . $ext;
            move_uploaded_file($tmp, $folder . $namaFotoBaru);
        }

        // Simpan ke DB
        $stmt = $pdo->prepare("INSERT INTO guru
            (nip, name, gender, dob, photo, no_hp, email, pass, alamat, admission_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $nip,
            $name,
            $gender,
            $dob,
            $namaFotoBaru,
            $no_hp,
            $email,
            $password,
            $alamat,
            $admission_date,
        ]);
        echo "success";
        exit;
    }
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
    <link rel="stylesheet" href="../../assets/admin.css">
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
        const guruModal = document.getElementById("guru-modal");
        const btnTambahGuru = document.getElementById("btn-tambah-guru");
        const guruList = document.getElementById("guru-list");
        const modalTitle = document.getElementById("modal-title");
        const guruForm = document.getElementById("guru-form");
        const btnCancel = document.getElementById("btn-cancel");

        let isEditMode = false;
        let currentGuruItem = null;

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

        customAlertOkBtn.addEventListener('click', () => {
            customAlertModal.style.display = 'none';
            customAlertResolve(true);
        });

        customAlertCancelBtn.addEventListener('click', () => {
            customAlertModal.style.display = 'none';
            customAlertResolve(false);
        });

        // Event listener for "Tambah Guru" button
        btnTambahGuru.addEventListener("click", () => {
            isEditMode = false;
            modalTitle.textContent = "Tambah Data Guru";
            guruForm.reset();
            document.getElementById("nipGuru").readOnly = false; // Allow editing NIP when adding
            document.getElementById("passwordGuru").style.display = "block";
            document.getElementById("labelPasswordGuru").style.display = "block";
            guruModal.style.display = "flex"; // Use flex to center the modal
        });

        // Event listener for "Edit" buttons
        guruList.addEventListener("click", (e) => {
            if (e.target.classList.contains("btn-edit") || e.target.closest(".btn-edit")) {
                isEditMode = true;
                modalTitle.textContent = "Edit Data Guru";
                currentGuruItem = e.target.closest("li");

                // Populate form fields
                document.getElementById("namaGuru").value = currentGuruItem.dataset.nama;
                document.getElementById("nipGuru").value = currentGuruItem.dataset.nip;
                document.getElementById("nipGuru").readOnly = true; // Prevent editing NIP
                document.getElementById("dobGuru").value = currentGuruItem.dataset.dob;
                document.getElementById("nohpGuru").value = currentGuruItem.dataset.nohp;
                document.getElementById("emailGuru").value = currentGuruItem.dataset.email;
                document.getElementById("alamatGuru").value = currentGuruItem.dataset.alamat;

                // Set gender radio button
                if (currentGuruItem.dataset.gender && currentGuruItem.dataset.gender.trim().toLowerCase() === "laki-laki") {
                    document.getElementById("male").checked = true;
                } else if (currentGuruItem.dataset.gender && currentGuruItem.dataset.gender.trim().toLowerCase() === "perempuan") {
                    document.getElementById("female").checked = true;
                }

                // Hide password field for edit mode
                document.getElementById("passwordGuru").style.display = "none";
                document.getElementById("labelPasswordGuru").style.display = "none";
                guruModal.style.display = "flex"; // Use flex to center the modal
            }
        });

        // Event listener for "Hapus" buttons
        guruList.addEventListener("click", async (e) => {
            if (e.target.classList.contains("btn-hapus") || e.target.closest(".btn-hapus")) {
                const btnHapus = e.target.closest(".btn-hapus");
                const nip = btnHapus.dataset.nip;
                const confirmed = await showCustomConfirm('Yakin ingin menghapus data guru ini?');

                if (confirmed) {
                    window.location.href = `hapus.php?nip=${nip}`;
                }
            }
        });


        // Event listener for "Batal" button in modal
        btnCancel.addEventListener("click", () => {
            guruModal.style.display = "none";
        });

        // Close modal when clicking outside of it
        window.onclick = function (event) {
            if (event.target == guruModal) {
                guruModal.style.display = "none";
            }
        };

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

        // Form submission logic
        guruForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const formData = new FormData(guruForm);
            if (isEditMode) {
                formData.append('action', 'edit_guru');
                formData.append('nipGuru', currentGuruItem.dataset.nip); // Use NIP as identifier
            } else {
                formData.append('action', 'tambah_guru');
            }

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(async result => {
                if (result.trim() === "success") {
                    await showCustomAlert(isEditMode ? "Guru berhasil diupdate!" : "Guru berhasil ditambahkan!");
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
