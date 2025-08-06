-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 06, 2025 at 10:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absensi_mahasiswa`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL,
  `id_pertemuan` int(11) DEFAULT NULL,
  `id_siswa` int(11) DEFAULT NULL,
  `status` enum('Hadir','Alpha','Sakit','Izin') DEFAULT 'Alpha',
  `keterangan` text DEFAULT NULL,
  `waktu_input` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `id_pertemuan`, `id_siswa`, `status`, `keterangan`, `waktu_input`) VALUES
(1, 4, 37, 'Hadir', NULL, '2025-07-30 13:36:36'),
(3, 1, 35, 'Izin', NULL, '2025-07-30 13:36:49'),
(5, 4, 41, 'Hadir', NULL, '2025-07-30 13:36:36'),
(28, 9, 37, 'Sakit', NULL, '2025-08-01 13:38:09'),
(29, 9, 41, 'Sakit', NULL, '2025-08-01 13:38:09');

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `role` enum('superadmin','admin') DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `email`, `pass`, `role`) VALUES
(1, 'Pak tani', 'admin@gmail.com', '$2y$10$bISNqZMJplmat9O2rykB.e8Gv0.R0wn.5uJsdLmuBMaA96tIGOWCK', 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `class`
--

CREATE TABLE `class` (
  `id` int(11) NOT NULL,
  `nama_kelas` varchar(50) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `photo` varchar(255) NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `deskripsi` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class`
--

INSERT INTO `class` (`id`, `nama_kelas`, `teacher_id`, `photo`, `tahun_ajaran`, `deskripsi`) VALUES
(3, 'Mukbang', 0, 'Fat Meduka.jpeg', '2025/2089', 'hfajfjvfvqfwgwgwg'),
(4, 'umazing', 0, '1751326776_new_yquvvHq - Imgur.gif', '2026/2027', 'hashire hashire hashire'),
(5, 'Jambi', 0, 'mumei-kronii.gif', '2045/emasemas', 'Kuda jatuhh'),
(7, 'Sega', 0, 'nbaa5g6c7bye1.gif', '2012/2013', 'Uwoooooooghhhhhhhghhhhhhhhhh');

-- --------------------------------------------------------

--
-- Table structure for table `guru`
--

CREATE TABLE `guru` (
  `id` int(11) NOT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `gender` enum('laki-laki','Perempuan') DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pass` varchar(255) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `photo` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `mapel` varchar(100) DEFAULT NULL,
  `admission_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guru`
--

INSERT INTO `guru` (`id`, `nip`, `name`, `gender`, `email`, `pass`, `dob`, `no_hp`, `photo`, `alamat`, `mapel`, `admission_date`) VALUES
(1, '19811002', 'PakGaga', 'laki-laki', 'rudi@gmail.com', '$2y$10$bqaGg4ApoUJ0zUbg3inKZuV.2n2QfOfGRJ.y/YwCdoQzPfZLznT6e', '1889-03-13', '08123456788', 'foto_1753945306.jpeg', 'Jl. Kenanga No. 5', 'Fisika', '2025-07-24 03:22:44'),
(2, '572523', 'kurnia', 'laki-laki', 'kurni@gmail.com', '$2y$10$fUl6VUbx2M59Vy56YZuVqO3wq/o7.wBFoMlZzvJWMb4.rhDuGimuC', '1945-08-17', '8658544848', 'foto_1753678824.gif', 'trikora', NULL, '2025-07-27 21:03:38');

-- --------------------------------------------------------

--
-- Table structure for table `jadwal`
--

CREATE TABLE `jadwal` (
  `id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `hari` varchar(20) DEFAULT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `mata_pelajaran` varchar(100) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `id_mapel` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jadwal`
--

INSERT INTO `jadwal` (`id`, `class_id`, `hari`, `jam_mulai`, `jam_selesai`, `mata_pelajaran`, `teacher_id`, `id_mapel`) VALUES
(1, 3, 'Senin', '12:15:00', '13:15:00', 'jambu', 1, 10),
(2, 4, 'Senin', '12:45:00', '14:45:00', 'Hijau', 2, 7),
(9, 4, 'Rabu', '15:30:00', '17:30:00', 'jompang', 2, 8),
(12, 3, 'Selasa', '16:08:00', '18:10:00', 'BluArsip', 2, 1),
(16, 4, 'Jumat', '23:32:00', '11:31:00', 'jambu', 2, 10),
(17, 7, 'Sabtu', '14:00:00', '15:00:00', 'BluArsip', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `mapel`
--

CREATE TABLE `mapel` (
  `id` int(11) NOT NULL,
  `nama_mapel` varchar(100) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `kurikulum` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mapel`
--

INSERT INTO `mapel` (`id`, `nama_mapel`, `photo`, `kurikulum`) VALUES
(1, 'BluArsip', 'mapel_688b19cd539b73.34166883.jpeg', 'K13'),
(7, 'Hijau', 'mapel_688b1d45e0e5b3.79645031.jpeg', 'Merdeka'),
(8, 'jompang', 'mapel_688c40c61f02b8.49404682.jpeg', 'K13'),
(10, 'jambu', 'mapel_6892ddc3b91825.86222836.gif', 'KTSP');

-- --------------------------------------------------------

--
-- Table structure for table `pertemuan`
--

CREATE TABLE `pertemuan` (
  `id` int(11) NOT NULL,
  `id_jadwal` int(11) DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `topik` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pertemuan`
--

INSERT INTO `pertemuan` (`id`, `id_jadwal`, `tanggal`, `topik`) VALUES
(1, 1, '2025-07-01', 'The Su Impreza (Japanese: スバル・インプレッサ, Hepburn: Subaru Inpuressa) is a compact car that has been manufactured by the Japanese automaker Subaru since 1992. It was introduced as a replacement for the Leone, with the predecessor\'s EA series engines replac'),
(4, 2, '2025-06-29', 'ayamayamayamayamayam ayammayamayamayamayamayamyamayamaya'),
(5, 1, '2025-06-15', 'nigga'),
(7, 9, '2025-07-23', 'ioioioioio'),
(8, 12, '2025-08-13', 't3wt2t2y2w2'),
(9, 16, '2029-10-01', 'pagar luar dalam'),
(12, 12, '2025-08-18', 'klangkabut'),
(13, 9, '2025-08-27', 'nigga');

-- --------------------------------------------------------

--
-- Table structure for table `siswa`
--

CREATE TABLE `siswa` (
  `id` int(11) NOT NULL,
  `NIS` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `gender` enum('laki-laki','perempuan') NOT NULL,
  `dob` date NOT NULL,
  `photo` varchar(40) DEFAULT NULL,
  `no_hp` int(10) UNSIGNED NOT NULL,
  `email` varchar(50) DEFAULT NULL,
  `pass` varchar(255) DEFAULT NULL,
  `alamat` varchar(50) DEFAULT NULL,
  `admission_date` datetime NOT NULL,
  `class_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `siswa`
--

INSERT INTO `siswa` (`id`, `NIS`, `name`, `gender`, `dob`, `photo`, `no_hp`, `email`, `pass`, `alamat`, `admission_date`, `class_id`) VALUES
(35, 4636426, 'tere', 'perempuan', '1983-05-01', 'foto_1753677676.gif', 4294967295, 'tere@gmail.com', '$2y$10$qtR9w2HxBh/gUeIc8av24eWCQoXKomsPrYiijob5kUK29/2yVRrJW', 'kazdel', '2025-07-24 06:18:29', 3),
(37, 875925, 'ala kadar', 'laki-laki', '2012-01-25', 'foto_1753687033.jpeg', 4294967295, 'kadar@gmail.com', '$2y$10$l4ZsTjQv4znYsaSbCBCZP.UiBKvbOTXgnTO8w3E.HRcEWwpPGnwL6', 'abydos', '2025-07-25 06:39:00', 4),
(41, 86473573, 'ayam', 'laki-laki', '2025-06-10', 'foto_1753759148.jpeg', 234746185, 'ayam@gmail.com', '$2y$10$YgEebhUpkraWA9IkiBmQC.baOE91aXmLxB4thbg25fxBwR65h9AWy', 'Jl. Pattimura', '2025-07-28 06:34:01', 4);

-- --------------------------------------------------------

--
-- Table structure for table `superadmin`
--

CREATE TABLE `superadmin` (
  `id` int(11) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pass` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `superadmin`
--

INSERT INTO `superadmin` (`id`, `username`, `email`, `pass`, `created_at`) VALUES
(1, 'Superadmin', 'superA@gmail.com', '$2y$10$kThAO5obVRXBn6WjvryNmeBi/a89xRcMcCsMEwlQj/MoYinLGhfYq', '2025-07-24 03:52:04');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `gender` enum('laki-laki','perempuan') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `no_hp` varchar(50) NOT NULL,
  `status` enum('active','pending','deleted','') NOT NULL DEFAULT 'pending',
  `role` enum('admin','guru','siswa') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_pertemuan` (`id_pertemuan`,`id_siswa`),
  ADD KEY `id_siswa` (`id_siswa`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `class`
--
ALTER TABLE `class`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip` (`nip`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `id_mapel` (`id_mapel`);

--
-- Indexes for table `mapel`
--
ALTER TABLE `mapel`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pertemuan`
--
ALTER TABLE `pertemuan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jadwal_id` (`id_jadwal`);

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `NIS` (`NIS`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `superadmin`
--
ALTER TABLE `superadmin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `jadwal`
--
ALTER TABLE `jadwal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `mapel`
--
ALTER TABLE `mapel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `pertemuan`
--
ALTER TABLE `pertemuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `superadmin`
--
ALTER TABLE `superadmin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`id_pertemuan`) REFERENCES `pertemuan` (`id`),
  ADD CONSTRAINT `absensi_ibfk_2` FOREIGN KEY (`id_siswa`) REFERENCES `siswa` (`id`);

--
-- Constraints for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`id`),
  ADD CONSTRAINT `jadwal_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `guru` (`id`),
  ADD CONSTRAINT `jadwal_ibfk_3` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`);

--
-- Constraints for table `pertemuan`
--
ALTER TABLE `pertemuan`
  ADD CONSTRAINT `pertemuan_ibfk_1` FOREIGN KEY (`id_jadwal`) REFERENCES `jadwal` (`id`);

--
-- Constraints for table `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
