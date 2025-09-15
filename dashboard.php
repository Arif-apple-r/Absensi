<?php
session_start();
if (!isset($_SESSION['siswa'])) {
    header("Location: regis.php");
    exit;
}
$siswa = $_SESSION['siswa'];
?>

<h2>Hai, <?= $siswa['name']; ?></h2>
<img src="uploads/<?= $siswa['photo']; ?>" width="150"><br>
<p>Email: <?= $siswa['email']; ?></p>