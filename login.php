<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/index.css">
</head>
<body>
    <div class="container">
        <div class="judul">
            <h2>Login</h2>
        </div>
        <div class="login">
            <form action="proses_login.php" method="POST">
                <p>
                    <legend>Nama :</legend>
                    <input type="text" name="name" placeholder="nama anda">
                </p>
                <p>
                    <legend>Email :</legend>
                    <input type="email" name="email" placeholder="email anda">
                </p>
                <p>
                    <legend>Password :</legend>
                    <input type="password" name="password" placeholder="password">
                </p>
                <button onclick="">Masuk</button>
            </form>
        </div>
        <div class="kaki">
            <p>Belum punya akun <a href="regis.php">daftar sekarang!</a></p>
        </div>
    </div>
</body>
</html>