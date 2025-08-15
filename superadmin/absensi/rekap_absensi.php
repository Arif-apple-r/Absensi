<?php
include '../../koneksi.php';

// Ambil data kelas
$q_kelas = mysqli_query($conn, "SELECT * FROM class");
?>

<form method="GET">
    <label>Pilih Kelas:</label>
    <select name="class_id">
        <?php while ($kelas = mysqli_fetch_assoc($q_kelas)): ?>
            <option value="<?= $kelas['id'] ?>"><?= $kelas['nama_kelas'] ?></option>
        <?php endwhile; ?>
    </select>
    <button type="submit">Lihat Rekap</button>
</form>

<?php
if (isset($_GET['class_id'])) {
    $class_id = $_GET['class_id'];

    $rekap = mysqli_query($conn, "
        SELECT s.name AS nama, 
            SUM(a.status = 'Hadir') AS hadir,
            SUM(a.status = 'Izin') AS izin,
            SUM(a.status = 'Sakit') AS sakit,
            SUM(a.status = 'Alpha' OR a.status IS NULL) AS alpha,
            COUNT(DISTINCT p.id) AS total_pertemuan
        FROM siswa s
        LEFT JOIN absensi a ON s.id = a.id_siswa
        LEFT JOIN pertemuan p ON a.id_pertemuan = p.id
        LEFT JOIN jadwal j ON p.id_jadwal = j.id
        WHERE s.class_id = '$class_id'
        GROUP BY s.id
    ");

    echo "<table border='1'>
        <tr><th>Nama</th><th>Hadir</th><th>Izin</th><th>Sakit</th><th>Alpha</th><th>Total Pertemuan</th></tr>";

    while ($row = mysqli_fetch_assoc($rekap)) { // <-- fixed here
        echo "<tr>
            <td>{$row['nama']}</td>
            <td>{$row['hadir']}</td>
            <td>{$row['izin']}</td>
            <td>{$row['sakit']}</td>
            <td>{$row['alpha']}</td>
            <td>{$row['total_pertemuan']}</td>
        </tr>";
    }

    echo "</table>";
}
?>
