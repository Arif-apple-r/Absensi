<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>Daftar Guru</title>
    <style>
        :root {
            --primary-color: #1abc9c;
            --secondary-color: #34495e;
            --background-color: #f0f2f5;
            --card-background: #ffffff;
            --text-color: #2c3e50;
            --light-text-color: #7f8c8d;
            --border-color: #e0e0e0;
            --shadow-color: rgba(0, 0, 0, 0.08);
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-color);
            display: flex;
            min-height: 100vh;
            color: var(--text-color);
            overflow-x: hidden;
        }

        /* Sidebar dan Header */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--secondary-color);
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            transition: width 0.3s ease;
            z-index: 1000;
            padding-top: 70px;
            overflow: hidden;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar .logo {
            color: #fff;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            padding: 15px 0;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            background: var(--primary-color);
        }

        .sidebar nav a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: #fff;
            text-decoration: none;
            transition: background-color 0.2s ease, padding-left 0.2s ease;
        }

        .sidebar nav a i {
            width: 25px;
            text-align: center;
            margin-right: 20px;
            font-size: 18px;
        }

        .sidebar.collapsed nav a i {
            margin-right: 0;
        }

        .sidebar.collapsed nav a span {
            display: none;
        }

        .sidebar nav a:hover,
        .sidebar nav a.active {
            background-color: #3e566d;
            padding-left: 25px;
        }

        .header {
            height: 65.5px; /* Adjusted to match jadwal */
            background-color: var(--card-background);
            box-shadow: 0 2px 10px var(--shadow-color);
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            z-index: 999;
            transition: left 0.3s ease, width 0.3s ease;
        }

        .header.shifted {
            left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
        }

        .content {
            flex-grow: 1;
            padding: 90px 30px 30px 30px; /* Adjusted to match jadwal */
            margin-left: var(--sidebar-width);
            transition: margin-left 0.3s ease;
            max-width: 100%;
        }

        .content.shifted {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Tombol Toggle Sidebar */
        .toggle-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            margin-right: 20px;
            transition: background-color 0.3s;
        }

        .toggle-btn:hover {
            background-color: #16a085;
        }

        /* Konten Utama */
        .card {
            background: var(--card-background);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px var(--shadow-color);
            margin-bottom: 25px;
            max-width: 1200px; /* Increased max-width for better display */
            margin-left: auto;
            margin-right: auto;
        }

        .card h2 {
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
        }

        .add-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 25px;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
            outline: none !important; /* Added: Force remove focus outline */
            box-shadow: none; /* Added: Ensure no box-shadow is mistaken for a stroke */
        }

        .add-link:hover {
            background-color: #16a085;
            transform: translateY(-2px);
        }

        /* Guru List */
        .guru-list {
            list-style: none;
            padding: 0;
        }

        .guru-list li {
            display: flex;
            align-items: center;
            background: #f9f9f9; /* Lighter background for list items */
            margin-bottom: 10px;
            padding: 15px 20px; /* Increased padding */
            border-radius: 8px; /* More rounded corners */
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); /* Subtle shadow */
            transition: background-color 0.2s ease;
        }

        .guru-list li:hover {
            background-color: #f2f2f2;
        }

        .guru-info {
            display: flex;
            align-items: center;
            flex-grow: 1;
        }

        .guru-info img {
            width: 60px; /* Larger photo */
            height: 60px; /* Larger photo */
            object-fit: cover;
            border-radius: 50%; /* Circular photo */
            margin-right: 20px;
            box-shadow: 0 2px 8px rgba(44,62,80,0.15);
            background: #fff;
        }

        .guru-text {
            display: flex;
            flex-direction: column;
        }

        .guru-nama {
            font-weight: 600; /* Bolder font */
            font-size: 17px; /* Slightly larger font */
            color: var(--text-color);
            margin-bottom: 3px;
        }

        .guru-email {
            font-weight: 400;
            color: var(--light-text-color);
            font-size: 14px;
        }

        .guru-actions {
            display: flex;
            gap: 10px; /* Space between buttons */
            flex-wrap: wrap;
        }

        /* Action Link Styles (copied from jadwal) */
        .action-link {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.2s, color 0.2s;
            display: inline-flex; /* To align icon and text */
            align-items: center;
            gap: 5px; /* Space between icon and text */
            outline: none !important; /* Added: Force remove focus outline */
            box-shadow: none; /* Added: Ensure no box-shadow is mistaken for a stroke */
        }

        .action-link.edit {
            background-color: #3498db;
            color: white;
        }
        .action-link.edit:hover {
            background-color: #2980b9;
        }

        .action-link.delete {
            background-color: #e74c3c;
            color: white;
        }
        .action-link.delete:hover {
            background-color: #c0392b;
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5); /* Darker overlay */
            align-items: center; /* Used to center the modal content */
            justify-content: center; /* Used to center the modal content */
        }

        .modal-content {
            background-color: var(--card-background);
            padding: 30px; /* Increased padding */
            border-radius: 12px; /* More rounded corners */
            width: 90%;
            max-width: 550px; /* Slightly wider modal */
            box-shadow: 0 8px 30px var(--shadow-color); /* Stronger shadow */
            position: relative;
            animation: fadeIn 0.3s ease-out;
            max-height: 90vh; /* Added: Ensure modal content doesn't exceed 90% viewport height */
            overflow-y: auto; /* Added: Enable vertical scrolling if content overflows */
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-content h3 {
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: 600;
            color: var(--text-color);
            text-align: center;
        }

        .modal-content label {
            display: block;
            margin-top: 15px; /* Increased margin */
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color);
        }

        .modal-content input[type="text"],
        .modal-content input[type="email"],
        .modal-content input[type="password"],
        .modal-content input[type="date"],
        .modal-content input[type="file"] {
            width: 100%;
            padding: 12px; /* Increased padding */
            margin-bottom: 15px; /* Increased margin */
            border-radius: 8px; /* More rounded corners */
            border: 1px solid var(--border-color);
            font-size: 16px;
            color: var(--text-color);
            background-color: var(--background-color);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .modal-content input[type="text"]:focus,
        .modal-content input[type="email"]:focus,
        .modal-content input[type="password"]:focus,
        .modal-content input[type="date"]:focus,
        .modal-content input[type="file"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(26, 188, 156, 0.2); /* Focus ring */
        }

        .gender-group {
            margin-bottom: 15px;
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .gender-group label {
            font-weight: normal;
            margin-top: 0;
            margin-bottom: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .gender-group input[type="radio"] {
            margin: 0;
            width: auto;
            height: auto;
        }

        .modal-buttons {
            text-align: right;
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-buttons button {
            padding: 10px 20px;
            border: none; /* Ensured no border */
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s, transform 0.2s;
            outline: none !important; /* Added: Force remove focus outline */
            box-shadow: none; /* Added: Ensure no box-shadow is mistaken for a stroke */
        }

        .btn-close {
            background-color: #e74c3c;
            color: white;
        }
        .btn-close:hover {
            background-color: #c0392b;
            transform: translateY(-1px);
        }

        .btn-save {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-save:hover {
            background-color: #16a085;
            transform: translateY(-1px);
        }

        /* Custom Alert/Confirmation Modal */
        .custom-modal-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            z-index: 3000;
            justify-content: center;
            align-items: center;
        }

        .custom-modal-content {
            background-color: var(--card-background);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 30px var(--shadow-color);
            width: 90%;
            max-width: 400px;
            text-align: center;
            animation: fadeIn 0.3s ease-out;
        }

        .custom-modal-content h4 {
            margin-bottom: 20px;
            font-size: 20px;
            color: var(--text-color);
        }

        .custom-modal-content .modal-buttons {
            justify-content: center;
            margin-top: 20px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar:not(.collapsed) {
                transform: translateX(-100%);
            }

            .sidebar.collapsed {
                transform: translateX(0);
            }

            .content,
            .header {
                margin-left: 0 !important;
                left: 0 !important;
                width: 100% !important;
                padding-left: 20px !important;
            }

            .sidebar.collapsed + .header,
            .sidebar.collapsed ~ .content {
                margin-left: var(--sidebar-collapsed-width) !important;
                left: var(--sidebar-collapsed-width) !important;
                width: calc(100% - var(--sidebar-collapsed-width)) !important;
            }
            .guru-list li {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .guru-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">AdminCoy</div>
        <nav>
            <a href="../dashboard_admin.php">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="index.php" class="active">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Guru</span>
            </a>
            <a href="../siswa/index.php">
                <i class="fas fa-user-graduate"></i>
                <span>Murid</span>
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
