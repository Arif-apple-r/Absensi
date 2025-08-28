<?php
// api/get_kelas_by_tahun_akademik.php
require '../koneksi.php'; // Sesuaikan path ini sesuai lokasi file koneksi.php Anda

header('Content-Type: application/json');

$response = ['status' => 'error', 'message' => 'Invalid request'];

if (isset($_GET['id_tahun_akademik'])) {
    $id_tahun_akademik = $_GET['id_tahun_akademik'];

    try {
        $stmt = $pdo->prepare("SELECT id, nama_kelas FROM class WHERE id_tahun_akademik = ? ORDER BY nama_kelas ASC");
        $stmt->execute([$id_tahun_akademik]);
        $kelas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = ['status' => 'success', 'kelas' => $kelas];
    } catch (PDOException $e) {
        $response = ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

echo json_encode($response);
exit;
?>