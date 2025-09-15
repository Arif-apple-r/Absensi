<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="assets/index1.css">
</head>
<body>

    <?php
        session_start();
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
                <h3>Login Gagal</h3>
                <span class="close-btn">&times;</span>
            </div>
            <p id="modal-message"></p>
        </div>
    </div>

    <script>
        <?php
        if (isset($_SESSION['error_message'])) {
            $message = $_SESSION['error_message'];
            unset($_SESSION['error_message']); 
            ?>
            var modal = document.getElementById("myModal");
            var message = document.getElementById("modal-message");
            var closeBtn = document.getElementsByClassName("close-btn")[0];

            // Display the modal
            modal.style.display = "flex";

            // Set the message
            message.innerHTML = "<?php echo $message; ?>";

            // Close the modal when the 'x' button is clicked
            closeBtn.onclick = function() {
                modal.style.display = "none";
            }

            // Close the modal when the user clicks outside of the modal
            window.onclick = function(event) {
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        <?php
        }
        ?>
    </script>
</body>
</html>