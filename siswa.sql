-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 22, 2025 at 04:29 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `absensi_siswa`
--

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
(37, 875925, 'ala kadar', 'laki-laki', '1986-05-25', 'foto_1753687033.jpeg', 4294967295, 'kadar@gmail.com', '$2y$10$l4ZsTjQv4znYsaSbCBCZP.UiBKvbOTXgnTO8w3E.HRcEWwpPGnwL6', 'abydos', '2025-07-25 06:39:00', 4),
(41, 86473573, 'ayam', 'laki-laki', '2025-06-10', 'foto_1753759148.jpeg', 234746185, 'ayam@gmail.com', '$2y$10$YgEebhUpkraWA9IkiBmQC.baOE91aXmLxB4thbg25fxBwR65h9AWy', 'Jl. Pattimura', '2025-07-28 06:34:01', 4),
(43, 2147483647, 'johan', 'laki-laki', '1665-06-15', '689443f6c2bfc.gif', 2527460234, 'johan@gmail.com', '$2y$10$LgaHojafaPF7sbv1C.AAR.PPofX46bq1Q7iuj3SWxtWogS13uR6WS', 'german', '2025-08-07 08:13:10', 5),
(44, 893451209, 'faffa', 'laki-laki', '1978-12-31', '68a6d03689ec0.webp', 234746185, 'fwfw@gmail.com', '$2y$10$y4h.ZPloYo1tDnSSCxdZcupDF13XuLGeIvp2zObptz8Ep1H2W8THG', 'Jl. Pattimura', '2025-08-21 09:52:22', 7),
(45, 1232524341, 'Caca', 'perempuan', '2005-06-14', '68a7ccde15b2e.jpg', 4294967295, 'caca@gmail.com', '$2y$10$5o664KoIurIqedcciziyD.0KJJBbuCbXNP.rwCeGR5kn2YkBJEjBO', 'Jl. Paus', '2025-08-22 03:50:22', 4),
(46, 46126349, 'Danang', 'laki-laki', '1998-02-18', '68a7cd2d85b56.jpg', 4294967295, 'danang@gmail.com', '$2y$10$rdKamDEEFeVoiEt2NjnVa.7BrAzJtGFjQ4HNTgbyVBGYVqgqYRKMW', 'Jl. Xianzhou', '2025-08-22 03:51:41', 4),
(47, 357261537, 'Chika', 'perempuan', '2008-08-08', '68a7cd7718952.jpg', 4294967295, 'chika@gmail.com', '$2y$10$8U5wVyeSrNBsBeUOdzYsKOW5pGebrtyitzjXykG48WeA.o9UUyF9O', 'Jl. Sekai', '2025-08-22 03:52:55', 5),
(48, 33550336, 'Paijo', 'laki-laki', '1978-02-17', '68a7ce4b9b74d.jpeg', 4294967295, 'paijo@gmail.com', '$2y$10$KoAlgfNoWQnWBYhR/BSfcOmLP6o3uOBXtPD7Hq54JTy50pT/jB4TK', 'Jl. Aedes Elysiae', '2025-08-22 03:56:27', 7),
(49, 236136482, 'Is Meaning Smile', 'perempuan', '2009-10-14', '68a7cea0a6296.gif', 4294967295, 'emuotori@gmail.com', '$2y$10$0IxFMYYgSwlyDJoqEnMG.eHtqva.0xaDxJiSBPmBpJtZ6BUJyFPXa', 'Jl. Wonderhoy', '2025-08-22 03:57:52', 5),
(50, 231682328, 'Selus', 'laki-laki', '2023-06-21', '68a7cf33be91a.jpg', 4294967295, 'selus@gmail.com', '$2y$10$DCNQ/FqHKR7g97KMojOjRes2Ea54sW7WigoVeKZhPsTTldZ5XZlxa', 'Jl. Astral Ex', '2025-08-22 04:00:19', 7),
(53, 36175283, 'Kuro', 'laki-laki', '2010-10-14', '68a7cf90aee93.png', 4294967295, 'kuro@gmail.com', '$2y$10$HL4SQeMCOPL9Ul/QhPxUVu9.b/FXQd5D0G8rJkR.BnjXwZgB2fxme', 'Jl. PGR', '2025-08-22 04:01:52', 5),
(54, 62514273, 'Entepeh', 'laki-laki', '2007-11-27', '68a7cfffb9142.png', 4294967295, 'intp@gmail.com', '$2y$10$iA/7NySQZ8JOv4SzwwJRv.WG4dsOrmlxhMWgkxjSMCd2uTv5SqQxK', 'Jl. Riau', '2025-08-22 04:03:43', 4),
(55, 6516283, 'Ryo', 'perempuan', '2003-08-19', '68a7d04e1de7e.gif', 4294967295, 'ryo@gmail.com', '$2y$10$cVUDBCJPNG9wbwWWXkbQZ.0QBkgzhdettDGeh/EjUTC3C/KZxwzTa', 'Jl. Band', '2025-08-22 04:05:02', 3),
(56, 76154283, 'Tarou', 'laki-laki', '2005-09-23', '68a7d0a9e7caf.jpg', 4294967295, 'tarou@gmail.com', '$2y$10$l62WD6DHohqG9H79fDkfhu8SD.0olIClC3oA3nk.QdiV28a46IGe.', 'Jl. Kisah', '2025-08-22 04:06:33', 3),
(57, 8715373, 'Kunang', 'perempuan', '2004-04-20', '68a7d0ea7e2a6.jpg', 4294967295, 'kunang@gmail.com', '$2y$10$Shp5IOi8n6/FLZNzSo73Ieb1hwNtOoigWN3SnQY9w7j5dMehqblpK', 'Jl. Hotaru', '2025-08-22 04:07:38', 3),
(58, 761537212, 'Yuji', 'laki-laki', '2006-10-11', '68a7d11da4144.jpg', 4294967295, 'yuji@gmail.com', '$2y$10$6fYOTOwjnNdyV6qVSTcUsu.5d/wGpvRboBf5PxSONC18HYsSfR1xO', 'Jl. Shibuya', '2025-08-22 04:08:29', 7),
(59, 87153716, 'Eha', 'laki-laki', '2020-03-19', '68a7d167f2f91.jpg', 4294967295, 'eha@gmail.com', '$2y$10$hL59smvbiDnV9HDc18dqKO5yRo/AQ8ZFXxgwvkJCAXLLtow8Tx0/W', 'Jl. Bijak', '2025-08-22 04:09:43', 5),
(60, 67251723, 'Guri', 'perempuan', '2010-01-28', '68a7d1a273d24.jpg', 4294967295, 'guri@gmail.com', '$2y$10$qCOjGQD9eOEVVRnO3UQZtOFUnh3JH1.8sbqjwdzCBQSaoL3d08oF6', 'Jl. Mine', '2025-08-22 04:10:42', 3),
(61, 8715332, 'Neko', 'perempuan', '2013-12-25', '68a7d1ed1e843.jpg', 4294967295, 'neko@gmail.com', '$2y$10$.NJJFaqajEaq7V7/JlzLP.g4m/oLnEDkUjgsECN/D7AMyaAHlgoiq', 'Jl. Oh Kami Yo', '2025-08-22 04:11:57', 7);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `siswa`
--
ALTER TABLE `siswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `NIS` (`NIS`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `class_id` (`class_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `siswa`
--
ALTER TABLE `siswa`
  ADD CONSTRAINT `siswa_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
