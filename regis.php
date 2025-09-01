<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/index0.css">
</head>
<body>
    <div class="container-signup">
        <div class="judul">
            <h2>Sign Up</h2>
        </div>
        <div class="signup-list">
            <form action="proses_regis.php" method="post" enctype="multipart/form-data">
                <div class="detail-personal">
                    <div class="fields">
                        <div class="input-field">
                            <label>Photo</label>
                            <input class="custom-file-input" type="file" name="photo" accept="image/*">
                        </div>
                        <div class="input-field">
                            <label>Nama</label>
                            <input type="text" name="name" placeholder="Enter your name" required>
                        </div>

                        <div class="input-field">
                            <label>tanggal lahir</label>
                            <input type="date" name="dob" placeholder="Enter birth date" required>
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
                                <option value="laki-laki">laki-laki</option>
                                <option value="perempuan">perempuan</option>
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
                    </div>
                </div>
                <button type="sumbit">Daftar</button>
            </form>
        </div>
        <div class="kaki">
            <p>Sudah punya akun kembali ke <a href="login.php">login</a></p>
        </div>
    </div>
</body>
</html>