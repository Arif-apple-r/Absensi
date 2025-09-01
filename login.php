<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/index0.css">
</head>
<body>

    <?php
        session_start();
        if (isset($_SESSION['login_error'])) {
            echo "<div style='color: red;'>" . $_SESSION['login_error'] . "</div>";
            unset($_SESSION['login_error']);
        }
    ?>

    <div class="container">
        <div class="judul">
            <h2>Login</h2>
        </div>
        <div class="login">
            <form action="proses_login.php" method="POST">
                <p>
                    <legend>Email :</legend>
                    <input type="email" name="email" placeholder="email anda">
                </p>
                <p>
                    <legend>Password :</legend>
                    <input type="password" name="password" placeholder="password">
                </p>
                <button type="submit">Masuk</button>
            </form>
        </div>
        <div class="kaki">
            <p>Belum punya akun <a href="regis.php">daftar sekarang!</a></p>
        </div>
    </div>

    <div id="myModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close-btn">&times;</span>
                <h3>Login Gagal</h3>
            </div>
            <p id="modal-message"></p>
        </div>
    </div>

    <script>
        <?php
        session_start();
        if (isset($_SESSION['error_message'])) {
            $message = $_SESSION['error_message'];
            unset($_SESSION['error_message']); 
            ?>
            var modal = document.getElementById("myModal");
            var message = document.getElementById("modal-message");
            var closeBtn = document.getElementsByClassName("close-btn")[0];

            // Tampilkan modal dan tambahkan kelas 'show'
            modal.style.display = "flex";
            modal.classList.add('show');

            // Tampilkan pesan
            message.innerHTML = "<?php echo $message; ?>";

            // Tutup modal ketika tombol 'x' diklik
            closeBtn.onclick = function() {
                modal.style.display = "none";
                modal.classList.remove('show');
            }

            // Tutup modal ketika pengguna klik di luar area modal
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                    modal.classList.remove('show');
                }
            }
        <?php
        }

        
        ?>
    </script>
</body>
</html>