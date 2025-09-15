<?php
session_start();
if (!isset($_SESSION['siswa'])) {
    header("Location: login.php");
    exit;
}
$siswa = $_SESSION['siswa'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Halaman Murid</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <!-- CDN SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: Arial, sans-serif;
      display: flex;
    }

    .sidebar {
      background-color: #34495e;
      color: white;
      width: 250px;
      min-height: 100vh;
      padding-top: 80px;
      position: fixed;
      left: 0;
      top: 0;
      transition: width 0.3s ease;
      overflow: hidden;
    }

    .sidebar.collapsed {
      width: 80px;
    }

    .sidebar .profile {
      text-align: center;
      padding: 20px 10px;
      transition: all 0.3s ease;
    }

    .sidebar.collapsed .profile h3,
    .sidebar.collapsed .profile p,
    .sidebar.collapsed .logout-btn {
      display: none;
    }

    .sidebar .profile img {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border-radius: 50%;
      transition: all 0.3s ease;
    }

    .sidebar.collapsed .profile img {
      width: 40px;
      height: 40px;
    }

    .logout-btn {
      background-color: #e74c3c;
      border: none;
      color: white;
      padding: 8px 16px;
      margin-top: 10px;
      border-radius: 4px;
      cursor: pointer;
    }

    .header {
      height: 60px;
      background-color: #2980b9;
      color: white;
      display: flex;
      align-items: center;
      padding: 0 20px;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1000;
    }

    .toggle-btn {
      font-size: 20px;
      cursor: pointer;
      margin-right: 15px;
    }

    .content {
      margin-left: 250px;
      padding: 80px 20px 20px 20px;
      transition: margin-left 0.3s ease;
      flex: 1;
    }

    .sidebar.collapsed ~ .content {
      margin-left: 80px;
    }

    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        height: 100%;
        z-index: 999;
      }

      .header {
        padding-left: 80px;
      }
    }
  </style>
</head>

<body>
  <div class="sidebar" id="sidebar">
    <div class="profile">
      <img src="uploads/siswa/<?= $siswa['photo']; ?>"><br>
      <h3>Hai, <?= $siswa['name']; ?></h3>
      <p>Email: <?= $siswa['email']; ?></p>
      <button class="logout-btn" onclick="showLogoutConfirm()">Logout</button>
    </div>
  </div>

  <div class="header">
    <i class="fas fa-bars toggle-btn" onclick="toggleSidebar()"></i>
    <h1>Halaman Murid</h1>
  </div>

  <div class="content" id="content">
    <h2>Selamat datang, <?= $siswa['name']; ?> !</h2>
    <p>Kamu dapat melihat informasi kelas, jadwal, dan nilai di sini.</p>
  </div>

  <script>
    function toggleSidebar() {
      const sidebar = document.getElementById("sidebar");
      const content = document.getElementById("content");
      sidebar.classList.toggle("collapsed");

      if (sidebar.classList.contains("collapsed")) {
        content.style.marginLeft = "80px";
      } else {
        content.style.marginLeft = "250px";
      }
    }

    function showLogoutConfirm() {
      Swal.fire({
          title: 'Konfirmasi Logout',
          text: 'Apakah kamu yakin ingin logout?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Ya, Logout!',
          cancelButtonText: 'Batal'
      }).then((result) => {
          if (result.isConfirmed) {
              window.location.href = "logout.php"; // redirect logout
          }
      });
    }
  </script>
</body>

</html>