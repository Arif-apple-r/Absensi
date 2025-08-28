-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 25, 2025 at 10:37 AM
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
-- Database: `absensi_siswa`
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
(3, 1, 35, 'Izin', NULL, '2025-07-30 13:36:49'),
(33, 15, 37, 'Hadir', NULL, NULL),
(34, 15, 41, 'Hadir', NULL, NULL),
(39, 20, 37, 'Alpha', NULL, '2025-08-15 09:44:16'),
(40, 20, 41, 'Alpha', NULL, '2025-08-15 09:44:16'),
(71, 22, 35, 'Sakit', 'Alabama has made some changes since the late 20th century and has used new types of voting to increase representation. In the 1980s, an omnibus redistricting case, Dillard v. Crenshaw County, challenged the at-large voting for representative seats of 180 Alabama jurisdictions, including counties and school boards. At-large voting had diluted the votes of any minority in a county, as the majority tended to take all seats. Despite African Americans making up a significant minority in the state, they had been unable to elect any representatives in most of the at-large jurisdictions.[72]', '2025-08-14 15:02:47'),
(84, 24, 47, 'Sakit', '', NULL),
(86, 24, 49, 'Alpha', NULL, NULL),
(88, 24, 53, 'Alpha', NULL, NULL);

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
(1, 'Pak tani', 'admin@gmail.com', '$2y$10$bISNqZMJplmat9O2rykB.e8Gv0.R0wn.5uJsdLmuBMaA96tIGOWCK', 'admin'),
(2, 'jawa', 'rajajawa@gmail.com', '$2y$10$QoG3peVrjOFRCKuw.1Pzx..cgyAV65Bf.2S9f8Jstu.BgwCduPP8G', 'admin');

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
  `deskripsi` text NOT NULL,
  `id_tahun_akademik` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `class`
--

INSERT INTO `class` (`id`, `nama_kelas`, `teacher_id`, `photo`, `tahun_ajaran`, `deskripsi`, `id_tahun_akademik`) VALUES
(3, 'X Uma', 0, '68abed7e0d321.jpg', '2025/2026', 'Kelas Ghoib ayoyoyooyoyoyoyoyoyoyoyooyoyoyyiyiyoiyoiyoyoyoyoyoyoyoyo', 1),
(4, 'XI Uma', 0, '1751326776_new_yquvvHq - Imgur.gif', '2026/2027', 'hashire hashire hashire', 2),
(5, 'X RPL', 0, 'harikitei ikou.webp', '1998/1999', 'Kuda jatuhh', 2),
(7, 'XI RPL', 0, 'nbaa5g6c7bye1.gif', '2012/2013', 'Uwoooooooghhhhhhhghhhhhhhhhh', 2),
(9, 'X RPL', 0, '68ac0d78b6645.webp', '', 'ayamayam', 1);

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
(1, '19811002', 'PakGaga', 'laki-laki', 'rudi@gmail.com', '$2y$10$bqaGg4ApoUJ0zUbg3inKZuV.2n2QfOfGRJ.y/YwCdoQzPfZLznT6e', '1889-03-13', '08123456788', '68996f68b586b.webp', 'Jl. Kenanga No. 5', 'Fisika', '2025-07-24 03:22:44'),
(2, '572523', 'kurnia', 'laki-laki', 'kurni@gmail.com', '$2y$10$fUl6VUbx2M59Vy56YZuVqO3wq/o7.wBFoMlZzvJWMb4.rhDuGimuC', '1945-08-17', '8658544848', 'foto_1753678824.gif', 'trikora', NULL, '2025-07-27 21:03:38'),
(9, '111222333', 'Joko', 'laki-laki', 'joko@gmail.com', '$2y$10$CCte.zoyZn7zZLz7z/e1MejJmpWyAaPwC5svb2N7dWTOWyeh93JGC', '1900-01-01', '016521809365', '68a6d4a3ce53b.jpg', 'kembali ke solo menjadi rakyat biasa', NULL, '2025-08-21 03:11:15'),
(10, '13912424', 'Marinir', 'Perempuan', 'marinir@gmail.com', '$2y$10$QO3QiojH5z.Eb5cNwj4RPeMRcOyw6b3KgLP7JC2PWl2.IMY3i7JQ2', '1980-07-01', '0127439561352', '68a6d539cc0c9.jpeg', 'jl marinir jaya raya merdeka bebas dari ancaman asing', NULL, '2025-08-21 03:13:45'),
(11, '5363247', 'wawan', 'laki-laki', 'wawan@gmail.com', '$2y$10$6IKntDTDMXyR3ho50.IVGe8oz9KS3MWixIEtca/pmi0tLJ7OoGLjW', '2025-08-11', '05656274', '68a7ed03b726d.JPG', 'kaka', NULL, '2025-08-21 23:07:17');

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
(20, 5, 'Senin', '07:20:00', '10:00:00', 'BluArsip', 1, 1),
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
(34, 4, 'Kamis', '14:30:00', '15:00:00', 'Gambling', 9, 11),
(35, 7, 'Selasa', '12:35:00', '11:24:00', 'jambu', 2, 10),
(36, 9, 'Senin', '12:12:00', '03:02:00', 'Gambling', 11, 11);

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
(1, 'BluArsip', 'mapel_68a6d9581b8256.61476217.jpeg', 'K13'),
(7, 'Hijau', 'mapel_688b1d45e0e5b3.79645031.jpeg', 'Merdeka'),
(8, 'jompang', 'mapel_68a6d97ed315d6.41795415.jpeg', 'K13'),
(10, 'jambu', 'mapel_6892ddc3b91825.86222836.gif', 'KTSP'),
(11, 'Gambling', 'mapel_689585202d5f34.87656153.gif', 'Merdeka');

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
(5, 1, '2025-06-15', 'nigga'),
(7, 9, '2025-07-23', 'ioioioioio'),
(12, 12, '2025-08-18', 'klangkabut'),
(13, 9, '2025-08-27', 'nigga'),
(15, 16, '2025-08-27', 'jajan ayam ireng'),
(17, 2, '2025-08-20', 'jojo njir mama\r\n'),
(20, 2, '2025-09-03', '1 kilo steel = 1 kilo feather\r\nanj\r\n\r\n'),
(22, 12, '2025-08-21', 'Alabama is nicknamed the Yellowhammer State, after the state bird. Alabama is also known as the &quot;Heart of Dixie&quot; and the &quot;Cotton State&quot;. The state has diverse geography, with the north dominated by the mountainous Tennessee Valley and '),
(23, 20, '2025-09-01', 'Leaf is extremely short, being able to ride on the protagonist\'s shoulder when she is tired. She has short green hair and green eyes along with pointed ears. She wears a type of green leotard and glass green slippers. Being a fairy, she has two long green'),
(24, 20, '2025-09-08', 'Leaf is a pretty flirty and teasing towards the main protagonist. Sometimes she says things that hint towards a darker side of her, such as when she tells the protagonist that he can do whatever he wants towards others, and not be punished for it. The oth'),
(25, 21, '2025-09-02', 'Dorothy takes the look of a middle aged woman, with white skin, pale blue eyes, and brown, copper hair that reaches to her chest. Her fringe hovers right above her eyes and perfectly frames her face. Her choice of clothing is rather simple, with a purple '),
(26, 21, '2025-09-09', 'Biography\r\nBlack Souls I\r\nWith three servants I\'ve killed countless witches.\r\nSoon enough I\'ll be the greatest wizard of all.\r\n— Fairytale (Wizard of Oz), Black Souls I\r\n\r\nShe was created from the Fairytale, Wizard of Oz.\r\n\r\nDorothy was once Oz, an acclai'),
(27, 22, '2025-09-03', 'Synopsis\r\nElma is a supporting character and one of the companions you can obtain in Black Souls I. Her fairy tale is based on the Little Match Girl. She is found in Rotten Burg tending to her store and waiting for her Mother and Father to come back. She '),
(28, 22, '2025-09-10', 'Appearance\r\nElma is a short girl with shoulder length light brown hair. She is quite pale with rosy cheeks and seems to always have her eyes closed. She wears a long dark green cloak and a dark green beret.'),
(29, 23, '2025-09-04', 'Appearance\r\nElizabeth\'s physical appearance is that of a 20 year old woman with white skin, red colored eyes, light blonde twin drill hair that reaches past her shoulders with black bows on the back of them and two straight face framing strands that reach'),
(30, 23, '2025-09-11', 'Biography\r\nTo recruit you just have to wait for it to appear in the forest, once it appears it is important not to interact with Little Red Riding Hood until you have recruited her, finally once you pass the fort return to the forest and in the northern p'),
(31, 24, '2025-09-05', 'Appearance]\r\nJeanne is a petite with a slender but well proportioned young female with smooth pale skin and small, perky breasts. She has wide, bluish green eyes  She has long, silky, thick, waist-length blonde hair, she has a white headband going across '),
(32, 24, '2025-09-12', 'Personality and Traits\r\nShe is a strong but nervous and easily flattered girl. She is very honorable and helps people.'),
(33, 25, '2025-09-02', 'Rupa is a girl born to a South Asian father and a Japanese mother. She exudes artistic sensibility and possesses sharp intellect. Considered a genius due to her calm way of speaking and modest attitude, she earns the respect of others. However, her flaw l'),
(34, 25, '2025-09-09', 'Rupa is of average height for a woman of her age. She has short, neck-length gray olive hair, with a distinct parting of her long fringe on the left side. Hailing from South Asia, Rupa carries a subtle, sun-kissed tan that complements her heathered grey e'),
(35, 26, '2025-09-03', 'Originally hailing from a well-known affluent family in her hometown, Tomo is now living in an apartment through room-sharing. She maintains a cold and aloof attitude towards the world. Much like a hedgehog, she is highly wary and doesn\'t easily open up t'),
(36, 26, '2025-09-10', 'Tomo, at the age of 16, has a height that could be considered shorter than average for her age group. She possesses light maroon eyes that complement her somewhat somber demeanor. Her grey hair is styled with a dark red headband, which coordinates well wi'),
(37, 27, '2025-09-04', 'Nina comes from a small country town where she had a strict family upbringing. Due to her coming from a country town, she is often referred to as a \"country bumpkin\" and related terms from Momoka Kawaragi. She is also a high school drop-out.'),
(38, 27, '2025-09-11', 'Nina possesses an average height for her age. She often styles her short, dull purple hair into twin ponytails while allowing her asymmetrical bangs to partially obscure her flat, blue eyes.'),
(39, 28, '2025-09-05', 'Momoka is a 20 year-old street musician with a straightforward and tomboyish personality. She dislikes feminine attire and opts for plain and simple hairstyles and clothing. She has a cheerful and caring nature, exuding the aura of a reliable senior membe'),
(40, 28, '2025-09-12', 'Momoka, as described in her official introduction, embraces a tomboyish persona. She sports a stylish silver chalice-colored hair with asymmetrical bangs, styled in a slanted haircut and has amethyst smoke-colored eye.'),
(41, 29, '2025-09-01', 'Raana was born to her mother Utai (要唄) and father Tsuyoshi (要毅).[4] She is also the granddaughter of Tsuzuki Shifune.[5] Her guitar is a hand-me-down from her grandmother, specifically the guitar she used while performing as Shisen.'),
(42, 29, '2025-09-08', 'Raana has short, wavy, grayish-white hair that falls just above her shoulders. Her left eye is steel blue and her right eye is yellow.'),
(43, 31, '2025-09-03', 'Soyo has long, waist-length light brown hair and grayish-blue eyes, often supporting a gentle expression on her face.'),
(44, 31, '2025-09-10', 'Soyo was born as Ichinose Soyo (一ノ瀬そよ). While in elementary school, her parents divorced, leading to her moving out with her mother and adapting her mother\'s surname, Nagasaki. Due to her mother being busy with her work, Soyo had to spend a considerable a'),
(45, 32, '2025-09-04', 'When Anon was in middle school, she was the student council president and was known as a popular and talented girl, however she had no close friends and everyone referred to her with her last name. Due to the aforementioned facts, her schoolmates would co'),
(46, 32, '2025-09-11', 'Anon is someone of average height and has light gray eyes and long, waist-length pink hair. She sometimes wears glasses, especially at home.\r\n\r\nHer original band outfit is a black dress with two vertical blue stripes on each side. The sleeves are mesh, an'),
(47, 33, '2025-09-05', 'Tomori has short, chin-length ash brown hair and rose-gold eyes.\r\n\r\nHer original band outfit consisted of an oversized white jacket with two vertical blue stripes on each sleeve, black stripes just above the cuffs, kangaroo pockets on each side, and four '),
(48, 33, '2025-09-12', 'Tomori was born to mother Hikari (高松 ひかり) and father Yoshiji (高松 由司).[1]\r\n\r\nEver since she was young, Tomori had felt like she didn\'t belong anywhere, and had difficulty making friends due to her awkward personality. She thought that she was always a step'),
(49, 30, '2025-09-02', 'Ave Mujica is an all-girl band in the BanG Dream! franchise. The group consists of five members, namely Misumi Uika (Doloris) on lead guitar and vocals, Wakaba Mutsumi (Mortis) on rhythm guitar, Yahata Umiri (Timoris) on bass, Yuutenji Nyamu (Amoris) on d'),
(50, 30, '2025-09-09', 'Ave Mujica is a band crafted by Sakiko, or Oblivionis, the keyboardist and leader of the now disbanded CRYCHIC. It comprises of 5 members coming from various but seemingly alike backgrounds, either coming from a wealthy family, being the daughter of celeb'),
(51, 34, '2025-09-04', 'Roselia ASIA TOUR \"Neuweltfahrt\" is Roselia\'s second live tour.\r\nThe first day of the tour will be held on November 22, 2025, at Ookini Arena Maishima, while the final two days will be held on February 14-15, 2026, at Tokyo Garden Theater. The live tour w'),
(52, 34, '2025-09-11', 'RAISE A SUILEN (also abbreviated as RAS) is an all-girl band in the BanG Dream! franchise. The group consists of five members, namely Wakana Rei (LAYER) on vocals and bass, Asahi Rokka (LOCK) on guitar, Satou Masuki (MASKING) on drums, Nyubara Reona (PARE'),
(53, 16, '2025-09-12', 'Roselia is an all-girl band in the BanG Dream! franchise. The group consists of five members, namely Minato Yukina on vocals, Hikawa Sayo on guitar, Imai Lisa on bass, Udagawa Ako on drums, and Shirokane Rinko on keyboard. They debuted with the song BLACK');

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
(60, 67251723, 'Guri', 'perempuan', '2010-01-28', '68a7d1a273d24.jpg', 4294967295, 'guri@gmail.com', '$2y$10$qCOjGQD9eOEVVRnO3UQZtOFUnh3JH1.8sbqjwdzCBQSaoL3d08oF6', 'Jl. Mine', '2025-08-22 04:10:42', 3),
(61, 8715332, 'Neko', 'perempuan', '2013-12-25', '68a7d1ed1e843.jpg', 4294967295, 'neko@gmail.com', '$2y$10$.NJJFaqajEaq7V7/JlzLP.g4m/oLnEDkUjgsECN/D7AMyaAHlgoiq', 'Jl. Oh Kami Yo', '2025-08-22 04:11:57', 7);

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
-- Table structure for table `tahun_akademik`
--

CREATE TABLE `tahun_akademik` (
  `id` int(11) NOT NULL,
  `nama_tahun` varchar(50) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tahun_akademik`
--

INSERT INTO `tahun_akademik` (`id`, `nama_tahun`, `tanggal_mulai`, `tanggal_selesai`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '2023/2024', '2023-07-17', '2024-06-30', 0, '2025-08-22 07:39:30', '2025-08-22 07:39:30'),
(2, '2024/2025', '2024-07-08', '2025-06-30', 1, '2025-08-22 07:39:30', '2025-08-25 03:22:13'),
(5, '2089/2090', '2025-07-27', '2110-09-22', 0, '2025-08-25 02:28:32', '2025-08-25 03:22:13');

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
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `fk_class_tahun_akademik` (`id_tahun_akademik`);

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
-- Indexes for table `tahun_akademik`
--
ALTER TABLE `tahun_akademik`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nama_tahun` (`nama_tahun`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `class`
--
ALTER TABLE `class`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `guru`
--
ALTER TABLE `guru`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `jadwal`
--
ALTER TABLE `jadwal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `mapel`
--
ALTER TABLE `mapel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `pertemuan`
--
ALTER TABLE `pertemuan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `siswa`
--
ALTER TABLE `siswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `superadmin`
--
ALTER TABLE `superadmin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tahun_akademik`
--
ALTER TABLE `tahun_akademik`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
-- Constraints for table `class`
--
ALTER TABLE `class`
  ADD CONSTRAINT `fk_class_tahun_akademik` FOREIGN KEY (`id_tahun_akademik`) REFERENCES `tahun_akademik` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
