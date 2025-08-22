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
(1, 7, 'Senin', '12:15:00', '16:20:00', 'jambu', 10, 10),
(2, 4, 'Senin', '12:45:00', '14:45:00', 'Hijau', 9, 7),
(9, 4, 'Rabu', '15:30:00', '17:30:00', 'jompang', 9, 8),
(12, 3, 'Selasa', '16:08:00', '18:10:00', 'BluArsip', 2, 1),
(16, 4, 'Jumat', '23:32:00', '11:31:00', 'jambu', 9, 10),
(20, 5, 'Senin', '07:30:00', '10:00:00', 'BluArsip', 1, 1),
(21, 5, 'Selasa', '10:50:00', '12:30:00', 'Gambling', 1, 11),
(22, 5, 'Rabu', '13:15:00', '14:30:00', 'Hijau', 1, 7),
(23, 5, 'Kamis', '07:30:00', '09:30:00', 'jambu', 1, 10),
(24, 5, 'Jumat', '08:40:00', '09:40:00', 'jompang', 1, 8),
(25, 7, 'Selasa', '08:20:00', '09:20:00', 'Gambling', 10, 11),
(26, 7, 'Rabu', '12:45:00', '14:45:00', 'Hijau', 10, 7),
(27, 7, 'Kamis', '12:25:00', '14:30:00', 'jambu', 10, 10),
(28, 7, 'Jumat', '10:30:00', '11:30:00', 'jompang', 10, 8),
(29, 3, 'Senin', '08:30:00', '09:20:00', 'jambu', 2, 10),
(30, 4, 'Selasa', '10:20:00', '12:10:00', 'jompang', 9, 8),
(31, 3, 'Rabu', '11:26:00', '14:28:00', 'Hijau', 2, 7),
(32, 3, 'Kamis', '14:24:00', '15:30:00', 'jompang', 2, 8),
(33, 3, 'Jumat', '07:30:00', '08:20:00', 'BluArsip', 2, 1),
(34, 4, 'Kamis', '14:30:00', '15:00:00', 'Gambling', 9, 11);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `id_mapel` (`id_mapel`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `jadwal`
--
ALTER TABLE `jadwal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `jadwal`
--
ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `class` (`id`),
  ADD CONSTRAINT `jadwal_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `guru` (`id`),
  ADD CONSTRAINT `jadwal_ibfk_3` FOREIGN KEY (`id_mapel`) REFERENCES `mapel` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
