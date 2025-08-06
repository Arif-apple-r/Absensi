<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../login.php");
    exit;
}
require '../../koneksi.php';

// Check if the user is an admin
$stmt = $pdo->query("SELECT * FROM siswa ORDER BY name ASC");
$siswalist = $stmt->fetchAll();

// Handle AJAX POST for adding siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_siswa') {
    $NIS      = $_POST['NISsiswa'];
    $name     = $_POST['namasiswa'];
    $email    = $_POST['emailsiswa'];
    $gender   = $_POST['gender'];
    $dob      = $_POST['dobsiswa'];
    $no_hp    = $_POST['nohpsiswa'];
    $alamat   = $_POST['alamatsiswa'];

    // Handle photo update if a new file is uploaded
    $foto = $_FILES['photosiswa']['name'] ?? '';
    $tmp  = $_FILES['photosiswa']['tmp_name'] ?? '';
    $folder = "../../uploads/siswa/";
    $namaFotoBaru = '';

    if ($foto && $tmp) {
        $ext  = pathinfo($foto, PATHINFO_EXTENSION);
        $namaFotoBaru = uniqid() . '.' . $ext;
        move_uploaded_file($tmp, $folder . $namaFotoBaru);

        // Delete old photo
        $stmtOld = $pdo->prepare("SELECT photo FROM siswa WHERE NIS = ?");
        $stmtOld->execute([$NIS]);
        $oldData = $stmtOld->fetch();
        if ($oldData && !empty($oldData['photo'])) {
            $oldFilePath = $folder . $oldData['photo'];
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        // Update photo in DB
        $stmt = $pdo->prepare("UPDATE siswa SET photo = ? WHERE NIS = ?");
        $stmt->execute([$namaFotoBaru, $NIS]);
    }

    // Update other fields
    $stmt = $pdo->prepare("UPDATE siswa SET name = ?, email = ?, gender = ?, dob = ?, alamat = ?, no_hp = ? WHERE NIS = ?");
    $stmt->execute([$name, $email, $gender, $dob, $alamat, $no_hp, $NIS]);

    echo "success";
    exit;
}

// Handle AJAX POST for adding siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_siswa') {
    $name     = $_POST['namasiswa'];
    $email    = $_POST['emailsiswa'];
    $password = password_hash($_POST['passwordsiswa'], PASSWORD_DEFAULT);
    $NIS      = $_POST['NISsiswa'];
    $gender   = $_POST['gender'];
    $dob      = $_POST['dobsiswa'];
    $no_hp    = $_POST['nohpsiswa'];
    $alamat   = $_POST['alamatsiswa'];
    $admission_date = date('Y-m-d H:i:s');

    // Upload foto
    $foto = $_FILES['photosiswa']['name'] ?? '';
    $tmp  = $_FILES['photosiswa']['tmp_name'] ?? '';
    $folder = "../../uploads/siswa/";
    $namaFotoBaru = '';
    if ($foto && $tmp) {
        $ext  = pathinfo($foto, PATHINFO_EXTENSION);
        $namaFotoBaru = uniqid() . '.' . $ext;
        move_uploaded_file($tmp, $folder . $namaFotoBaru);
    }

    // Simpan ke DB
    $stmt = $pdo->prepare("INSERT INTO siswa 
        (NIS, name, gender, dob, photo, no_hp, email, pass, alamat, admission_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
    ]);
    echo "success";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Daftar siswa</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background-color: #f4f4f4;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            transition: width 0.3s ease;
            z-index: 1000;
            padding-top: 60px;
            overflow-x: hidden;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
        }

        .sidebar a i {
            margin-right: 15px;
            min-width: 20px;
            text-align: center;
        }

        .sidebar.collapsed a span {
            display: none;
        }

        .sidebar a:hover {
            background-color: #34495e;
        }

        .sidebar .logo {
            color: white;
            font-size: 24px;
            text-align: center;
            position: absolute;
            top: 10px;
            left: 0;
            width: 100%;
        }

        .header {
            height: 60px;
            background-color: #1abc9c;
            color: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: fixed;
            top: 0;
            left: 250px;
            width: calc(100% - 250px);
            z-index: 999;
            transition: left 0.3s ease, width 0.3s ease;
        }

        .header.shifted {
            left: 70px;
            width: calc(100% - 70px);
        }

        .content {
            padding: 80px 20px 20px 20px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
            width: 100%;
        }

        .content.shifted {
            margin-left: 70px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            max-width: 800px;
            margin-inline: auto;
        }

        .card h2 {
            margin-bottom: 15px;
        }

        .btn-tambah {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            float: right;
            margin-bottom: 15px;
        }

        .btn-tambah:hover {
            background-color: #2980b9;
        }

        /* siswalist */
        .siswa-list {
        list-style: none;
        padding-left: 0;
        }

        .siswa-list li {
        display: flex;
        align-items: center;
        background: #ecf0f1;
        margin-bottom: 10px;
        padding: 10px 15px;
        border-radius: 5px;
        justify-content: space-between;
        }

        .siswa-info {
        display: flex; /* Menggunakan flexbox untuk merapikan info siswa */
        align-items: center;
        flex-grow: 1; /* Memberikan fleksibilitas agar kolom ini bisa melebar */
        }

        /* Mengatur spasi antara foto dan teks */
        .siswa-info img {
        margin-right: 15px;
        }

        .siswa-text {
        /* Hapus display: flex; di sini */
        display: flex;
        flex-direction: column; /* Mengubah arah flex menjadi kolom agar nama dan email berada di baris berbeda */
        /* Kita bisa beri sedikit margin-left jika perlu */
        }

        /* Mengatur margin dan font */
        .siswa-nama {
        font-weight: bold;
        font-size: 16px;
        margin-right: 0; /* Menghilangkan margin-right agar tidak ada jarak berlebih */
        margin-bottom: 5px; /* Menambah sedikit jarak di bawah nama */
        display: block; /* Memastikan setiap span nama memiliki baris baru */
        }

        .siswa-email {
        font-weight: normal;
        color: #555;
        font-size: 15px;
        margin-left: 0; /* Menghilangkan margin-left */
        display: block; /* Memastikan setiap span email memiliki baris baru */
        }

        .siswa-actions {
        display: flex;
        align-items: center;
        }

        .siswa-actions button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 6px 10px;
            
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px; /* Memberikan jarak antar tombol */
        }
        .siswa-actions button:hover {
            background-color: #2980b9;
        }

        .siswa-actions .btn-hapus {
            background-color: #e74c3c;
        }
        .siswa-actions .btn-hapus:hover {
            background-color: #c0392b;
        }
        

        .siswa-list button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .siswa-list button:hover {
            background-color: #2980b9;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 500px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-content h3 {
            margin-bottom: 10px;
        }

        .modal-content label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        .modal-content input[type="text"],
        .modal-content input[type="email"],
        .modal-content input[type="password"],
        .modal-content input[type="date"],
        .modal-content input[type="file"] {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 12px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }

        .gender-group {
            margin-bottom: 12px;
        }

        .gender-group label {
            display: inline-block;
            font-weight: normal;
            margin-right: 15px;
        }
        
        .gender-group input[type="radio"] {
            margin-right: 5px;
        }

        .modal-buttons {
            text-align: right;
            margin-top: 20px;
        }

        .modal-buttons button {
            padding: 8px 16px;
            margin-left: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-close {
            background-color: #e74c3c;
            color: white;
        }

        .btn-save {
            background-color: #2ecc71;
            color: white;
        }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="logo">Admin</div>
        <a href="#" id="toggle-btn"><i>☰</i><span>ㅤToggle</span></a>
        <a href="../dashboard_admin.php">📊<span>ㅤDashboard</span></a>
        <a href="../guru/index.php">👨‍🏫<span>ㅤGuru</span></a>
        <a href="index.php">👨‍🎓<span>ㅤSiswa</span></a>
        <a href="../jadwal/index.php">📅<span>ㅤJadwal</span></a>
        <a href="../kelas/index.php">🏫<span>ㅤKelas</span></a>
        <a href="../mapel/index.php">📚<span>ㅤMata Pelajaran</span></a>
    </div>
    <div class="header" id="header">
        <h1>Daftar siswa</h1>
    </div>
    <div class="content" id="mainContent">
        <div class="card">
            <button class="btn-tambah" id="btn-tambah-siswa"><b>+</b> Tambah siswa</button>
            <h2>Daftar Siswa</h2><br>
            <ul class="siswa-list" id="siswa-list">
                <?php foreach ($siswalist as $siswa): ?>
                <li 
                    data-id="<?= htmlspecialchars($siswa['NIS']) ?>"
                    data-nama="<?= htmlspecialchars($siswa['name']) ?>"
                    data-nis="<?= htmlspecialchars($siswa['NIS']) ?>"
                    data-gender="<?= htmlspecialchars($siswa['gender']) ?>"
                    data-dob="<?= htmlspecialchars($siswa['dob']) ?>"
                    data-nohp="<?= htmlspecialchars($siswa['no_hp']) ?>"
                    data-email="<?= htmlspecialchars($siswa['email']) ?>"
                    data-alamat="<?= htmlspecialchars($siswa['alamat']) ?>"
                    data-photo="<?= htmlspecialchars($siswa['photo']) ?>"
                >
                    <div class="siswa-info">
                        <img 
                            src="../../uploads/siswa/<?= htmlspecialchars($siswa['photo']) ?>" 
                            alt="Foto <?= htmlspecialchars($siswa['name']) ?>" 
                            width="50" 
                            height="50"
                            style="border-radius: 50%; margin-right: 15px; box-shadow: 0 2px 8px rgba(44,62,80,0.15); object-fit: cover; background: #fff;"
                            loading="lazy"
                            onerror="this.onerror=null;this.src='../../uploads/siswa/default.png';"
                        >
                        <div class="siswa-text">
                        <span class="siswa-nama"><?= htmlspecialchars($siswa['name']) ?></span>
                        <span class="siswa-email"><?= htmlspecialchars($siswa['email']) ?></span>
                        </div>
                    </div>
                    <div class="siswa-actions">
                        <button class="btn-edit">Edit</button>
                        <button href="hapus.php?NIS=<?= urlencode($siswa['NIS']) ?>" onclick="return confirm('Yakin hapus data?')" class="btn-hapus">Hapus</button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div id="siswa-modal" class="modal">
        <div class="modal-content">
            <h3 id="modal-title">Edit Data siswa</h3>
            <form id="siswa-form" enctype="multipart/form-data">
                <label for="namasiswa">Nama siswa:</label>
                <input type="text" id="namasiswa" name="namasiswa" required>
                <label for="NISsiswa">NIS:</label>
                <input type="text" id="NISsiswa" name="NISsiswa" required>
                <label>JeNIS Kelamin:</label>
                <div class="gender-group">
                    <input type="radio" id="male" name="gender" value="Laki-Laki">
                    <label for="male">Laki-Laki</label>
                    <input type="radio" id="female" name="gender" value="Perempuan">
                    <label for="female">Perempuan</label>
                </div>
                <label for="dobsiswa">Tanggal Lahir:</label>
                <input type="date" id="dobsiswa" name="dobsiswa">
                <label for="photosiswa">Foto siswa:</label>
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
    <script>
        // Sidebar toggle
        const sidebar = document.getElementById("sidebar");
        const mainContent = document.getElementById("mainContent");
        const header = document.getElementById("header");
        const toggleBtn = document.getElementById("toggle-btn");
        toggleBtn.addEventListener("click", () => {
            sidebar.classList.toggle("collapsed");
            mainContent.classList.toggle("shifted");
            header.classList.toggle("shifted");
        });

        // Modal logic
        const siswaModal = document.getElementById("siswa-modal");
        const btnTambahsiswa = document.getElementById("btn-tambah-siswa");
        const siswaList = document.getElementById("siswa-list");
        const modalTitle = document.getElementById("modal-title");
        const siswaForm = document.getElementById("siswa-form");
        const btnCancel = document.getElementById("btn-cancel");
        let isEditMode = false;
        let currentsiswaItem = null;

        btnTambahsiswa.addEventListener("click", () => {
            isEditMode = false;
            modalTitle.textContent = "Tambah Data siswa";
            siswaForm.reset();
            // Show password field
            document.getElementById("passwordsiswa").style.display = "block";
            document.getElementById("labelPasswordsiswa").style.display = "block";
            siswaModal.style.display = "block";
        });

        siswaList.addEventListener("click", (e) => {
            if (e.target.classList.contains("btn-edit")) {
                isEditMode = true;
                modalTitle.textContent = "Edit Data siswa";
                currentsiswaItem = e.target.closest("li");
                document.getElementById("namasiswa").value = currentsiswaItem.dataset.nama;
                document.getElementById("NISsiswa").value = currentsiswaItem.dataset.nis;
                document.getElementById("NISsiswa").readOnly = true; // Prevent editing NIS
                document.getElementById("dobsiswa").value = currentsiswaItem.dataset.dob;
                document.getElementById("nohpsiswa").value = currentsiswaItem.dataset.nohp;
                document.getElementById("emailsiswa").value = currentsiswaItem.dataset.email;
                document.getElementById("alamatsiswa").value = currentsiswaItem.dataset.alamat;
                if (currentsiswaItem.dataset.gender && currentsiswaItem.dataset.gender.trim().toLowerCase() === "laki-laki") {
                    document.getElementById("male").checked = true;
                } else if (currentsiswaItem.dataset.gender && currentsiswaItem.dataset.gender.trim().toLowerCase() === "perempuan") {
                    document.getElementById("female").checked = true;
                }
                // Hide password field
                document.getElementById("passwordsiswa").style.display = "none";
                document.getElementById("labelPasswordsiswa").style.display = "none";
                siswaModal.style.display = "block";
            }
        });

        btnTambahsiswa.addEventListener("click", () => {
            isEditMode = false;
            modalTitle.textContent = "Tambah Data siswa";
            siswaForm.reset();
            document.getElementById("NISsiswa").readOnly = false; // Allow editing NIS when adding
            document.getElementById("passwordsiswa").style.display = "block";
            document.getElementById("labelPasswordsiswa").style.display = "block";
            siswaModal.style.display = "block";
        });

        btnCancel.addEventListener("click", () => {
            siswaModal.style.display = "none";
        });


        siswaForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const formData = new FormData(siswaForm);
            if (isEditMode) {
                formData.append('action', 'edit_siswa');
                formData.append('NISsiswa', currentsiswaItem.dataset.NIS); // Use NIS as identifier
            } else {
                formData.append('action', 'tambah_siswa');
            }

            fetch('index.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(result => {
                if (result.trim() === "success") {
                    alert(isEditMode ? "siswa berhasil diupdate!" : "siswa berhasil ditambahkan!");
                    window.location.reload();
                } else {
                    alert("Gagal: " + result);
                }
            })
            .catch(error => {
                alert("Terjadi kesalahan: " + error);
            });
        });

        window.onclick = function (event) {
            if (event.target == siswaModal) {
                siswaModal.style.display = "none";
            }
        };
    </script>
</body>
</html>