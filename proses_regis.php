<?php
require_once 'koneksi.php'; // koneksi PDO
session_start();

// Inisialisasi variabel pesan dan tipe alert
$alert_type = '';
$message = '';

// Validasi input
if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['NIS']) || empty($_POST['gender']) || empty($_POST['dob']) || empty($_POST['no_hp']) || empty($_POST['alamat']) || empty($_POST['class_id'])) {
    $alert_type = 'error';
    $message = 'Semua field wajib diisi.';
    $_SESSION['alert_type'] = $alert_type;
    $_SESSION['message'] = $message;
    header('Location: regis.php');
    exit;
}

// Ambil data dari form
$name     = $_POST['name'];
$email    = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
$NIS      = $_POST['NIS'];
$gender   = $_POST['gender'];
$dob      = $_POST['dob'];
$no_hp    = $_POST['no_hp'];
$alamat   = $_POST['alamat'];
$class_id = $_POST['class_id'];
$admission_date = date('Y-m-d H:i:s');

// Cek apakah NIS sudah terdaftar
$stmt_nis = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE NIS = ?");
$stmt_nis->execute([$NIS]);
if ($stmt_nis->fetchColumn() > 0) {
    $alert_type = 'warning';
    $message = 'Registrasi gagal: NIS sudah terdaftar. Silakan gunakan NIS lain.';
    $_SESSION['alert_type'] = $alert_type;
    $_SESSION['message'] = $message;
    header('Location: regis.php');
    exit;
}

// Cek apakah email sudah terdaftar
$stmt_email = $pdo->prepare("SELECT COUNT(*) FROM siswa WHERE email = ?");
$stmt_email->execute([$email]);
if ($stmt_email->fetchColumn() > 0) {
    $alert_type = 'warning';
    $message = 'Registrasi gagal: Email sudah terdaftar. Silakan gunakan email lain.';
    $_SESSION['alert_type'] = $alert_type;
    $_SESSION['message'] = $message;
    header('Location: regis.php');
    exit;
}

// Upload foto
$foto = $_FILES['photo']['name'];
$tmp  = $_FILES['photo']['tmp_name'];
$folder = "uploads/siswa/";
$ext  = pathinfo($foto, PATHINFO_EXTENSION);
$namaFotoBaru = uniqid() . '.' . $ext;
if (!empty($foto)) {
    if (!move_uploaded_file($tmp, $folder . $namaFotoBaru)) {
        $alert_type = 'error';
        $message = 'Gagal mengunggah foto.';
        $_SESSION['alert_type'] = $alert_type;
        $_SESSION['message'] = $message;
        header('Location: regis.php');
        exit;
    }
} else {
    $namaFotoBaru = null; 
}


// Simpan ke database
try {
    $stmt = $pdo->prepare("INSERT INTO siswa 
        (NIS, name, gender, dob, photo, no_hp, email, pass, alamat, class_id, admission_date) 
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
        $class_id,
        $admission_date,
    ]);

    $alert_type = 'success';
    $message = 'Registrasi berhasil! Silakan login.';
    $_SESSION['alert_type'] = $alert_type;
    $_SESSION['message'] = $message;
    header("Location: regis.php");
    exit;

} catch (PDOException $e) {
    $alert_type = 'error';
    $message = 'Registrasi gagal: ' . $e->getMessage();
    $_SESSION['alert_type'] = $alert_type;
    $_SESSION['message'] = $message;
    header('Location: regis.php');
    exit;
}
?>