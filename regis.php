<?php
// Sertakan file koneksi database
require_once 'koneksi.php';
session_start();

// Ambil data kelas dari database yang tahun akademiknya aktif
$classes = [];
try {
    $stmt = $pdo->query("SELECT 
                             c.id, 
                             c.nama_kelas 
                         FROM 
                             class c
                         JOIN
                             tahun_akademik ta ON c.id_tahun_akademik = ta.id
                         WHERE
                             ta.is_active = 1
                         ORDER BY c.nama_kelas");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Gagal memuat data kelas: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun Siswa</title>
    <link rel="stylesheet" href="assets/index2.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="container-signup">
        <div class="judul">
            <h2>Sign Up</h2>
        </div>
        <div class="signup-list">
            <form action="proses_regis.php" method="post" enctype="multipart/form-data">
                <div class="signup-content">
                    <div class="left-section">
                        <div class="photo-preview-container">
                            <img id="photo-preview" src="#" alt="Photo Preview">
                        </div>
                        <div class="input-field photo-field">
                            <label>Photo</label>
                            <input class="custom-file-input" type="file" name="photo" accept="image/*" id="photo-input">
                        </div>
                    </div>
                    <div class="right-section">
                        <div class="fields">
                            <div class="input-field">
                                <label>Nama</label>
                                <input type="text" name="name" placeholder="Enter your name" required>
                            </div>
                            <div class="input-field">
                                <label>tanggal lahir</label>
                                <input type="date" name="dob" required>
                            </div>
                            <div class="input-field">
                                <label>Email</label>
                                <input type="email" name="email" placeholder="Enter your email" required>
                            </div>
                            <div class="input-field">
                                <label>Password</label>
                                <input type="password" name="password" placeholder="password" required>
                            </div>
                            <div class="input-field">
                                <label>Gender</label>
                                <select required name="gender">
                                    <option value="laki-laki">Laki-laki</option>
                                    <option value="perempuan">Perempuan</option>
                                </select>
                            </div>
                            <div class="input-field">
                                <label>NIS</label>
                                <input type="number" name="NIS" placeholder="NIS" required>
                            </div>
                            <div class="input-field">
                                <label>Nomor Telp</label>
                                <input type="number" name="no_hp" placeholder="nomor telepon" required>
                            </div>
                            <div class="input-field">
                                <label>Alamat</label>
                                <input type="text" name="alamat" placeholder="alamat" required>
                            </div>
                            <div class="input-field">
                                <label>Kelas</label>
                                <select name="class_id" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class['id']); ?>">
                                            <?php echo htmlspecialchars($class['nama_kelas']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="submit">Daftar</button>
            </form>
        </div>
        <div class="kaki">
            <p>Sudah punya akun kembali ke <a href="login.php">login</a></p>
        </div>
    </div>

    <script>
        const photoInput = document.getElementById('photo-input');
        const photoPreview = document.getElementById('photo-preview');

        photoInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    photoPreview.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Logika JavaScript untuk menampilkan SweetAlert
        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                title: 'Informasi',
                text: '<?php echo htmlspecialchars($_SESSION['message']); ?>',
                icon: '<?php echo htmlspecialchars($_SESSION['alert_type']); ?>',
                confirmButtonText: 'OK'
            });
            // Hapus sesi setelah ditampilkan agar tidak muncul lagi
            <?php unset($_SESSION['message']); ?>
            <?php unset($_SESSION['alert_type']); ?>
        <?php endif; ?>
    </script>
</body>
</html>