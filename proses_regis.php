<?php
require_once 'koneksi.php'; // koneksi PDO
session_start();

// Ambil data dari form
$name     = $_POST['name'];
$email    = $_POST['email'];
$password     = password_hash($_POST['password'], PASSWORD_DEFAULT); // Enkripsi password
$NIS      = $_POST['NIS'];
$gender   = $_POST['gender'];
$dob      = $_POST['dob'];
$no_hp    = $_POST['no_hp'];
$alamat   = $_POST['alamat'];
$admission_date = date('Y-m-d H:i:s');

// Upload foto
$foto = $_FILES['photo']['name'];
$tmp  = $_FILES['photo']['tmp_name'];
$folder = "uploads/siswa/";
$ext  = pathinfo($foto, PATHINFO_EXTENSION);
$namaFotoBaru = uniqid() . '.' . $ext;
move_uploaded_file($tmp, $folder . $namaFotoBaru);

// Simpan ke database
try {
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

    echo "Registrasi berhasil! Silakan <a href='login.php'>login</a>.";

    // header("Location: login.php");
    // exit;
} catch (PDOException $e) {
    echo "Registrasi gagal: " . $e->getMessage();
}
?>