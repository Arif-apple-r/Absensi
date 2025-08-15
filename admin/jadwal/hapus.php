<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: ../../login.php");
    exit;
}

require '../../koneksi.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // Mulai transaksi untuk memastikan kedua perintah DELETE berhasil
        $pdo->beginTransaction();

        // 1. Hapus semua data pertemuan yang terkait dengan jadwal ini (child rows)
        $stmt_pertemuan = $pdo->prepare("DELETE FROM pertemuan WHERE id_jadwal = ?");
        $stmt_pertemuan->execute([$id]);

        // 2. Hapus data jadwal itu sendiri (parent row)
        $stmt_jadwal = $pdo->prepare("DELETE FROM jadwal WHERE id = ?");
        $stmt_jadwal->execute([$id]);

        // Jika kedua perintah berhasil, commit transaksi
        $pdo->commit();

        header("Location: index.php?success=" . urlencode("Jadwal dan semua pertemuan terkait berhasil dihapus!"));
        exit;

    } catch (PDOException $e) {
        // Jika ada error, batalkan transaksi
        $pdo->rollBack();
        $error = "Gagal menghapus jadwal: " . $e->getMessage();
        header("Location: index.php?error=" . urlencode($error));
        exit;
    }
} else {
    header("Location: index.php?error=" . urlencode("ID jadwal tidak ditemukan."));
    exit;
}
?>