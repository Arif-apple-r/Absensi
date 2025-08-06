<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
?>

<h1>Dashboard Admin</h1>
<p>Selamat datang, <?= htmlspecialchars($_SESSION['admin_name']) ?></p>
<ul>
    <li><a href="siswa/index.php">Kelola Data Siswa</a></li>
    <li><a href="guru/index.php">Kelola Data Guru</a></li>
    <li><a href="kelas/index.php">Kelola Data Kelas</a></li>
    <li><a href="jadwal/index.php">Kelola Data Jadwal</a></li>
    <li><a href="mapel/index.php">Kelola Data mapel</a></li>
    <li><a href="../logout.php">Logout</a></li>
</ul>
