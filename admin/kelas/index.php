<table border="1">
    <tr>
        <th>Nama Kelas</th>
        <th>Tahun Ajaran</th>
        <th>Foto</th>
        <th>Aksi</th>
    </tr>
    <?php
    require '../../koneksi.php';
    $kelas = $pdo->query("SELECT * FROM class")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($kelas as $row) {
        echo "<tr>";
        echo "<td>{$row['nama_kelas']}</td>";
        echo "<td>{$row['tahun_ajaran']}</td>";
        echo "<td><img src='../../uploads/kelas/{$row['photo']}' width='80'></td>";
        echo "<td>
            <a href='edit.php?id={$row['id']}'>Edit</a> | 
            <a href='hapus.php?id={$row['id']}' onclick='return confirm(\"Yakin?\")'>Hapus</a>
        </td>";
        echo "</tr>";
    }
    ?>
</table>

<a href="tambah.php">+ Tambah Jadwal</a>
<br>
<a href="../dashboard_admin.php">Balek ayoyo</a>
