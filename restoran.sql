-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2025 at 04:45 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `restoran`
--

-- --------------------------------------------------------

--
-- Table structure for table `detail_pesanan`
--

CREATE TABLE `detail_pesanan` (
  `id_detail` int(11) NOT NULL,
  `id_pesanan` int(11) NOT NULL,
  `id_menu` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `harga_satuan` int(10) DEFAULT NULL,
  `subtotal` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_pesanan`
--

INSERT INTO `detail_pesanan` (`id_detail`, `id_pesanan`, `id_menu`, `jumlah`, `harga_satuan`, `subtotal`) VALUES
(172, 110, 5, 2, 30000, 60000),
(173, 111, 5, 2, 30000, 60000),
(174, 112, 5, 2, 30000, 60000),
(175, 113, 5, 2, 30000, 60000),
(176, 114, 1, 1, 20000, 20000),
(177, 115, 2, 1, 25000, 25000),
(178, 116, 1, 1, 20000, 20000),
(179, 117, 4, 1, 40000, 40000),
(180, 118, 30, 1, 20000, 20000),
(181, 119, 30, 1, 20000, 20000),
(182, 120, 1, 1, 20000, 20000),
(183, 121, 7, 5, 50000, 250000),
(184, 121, 29, 3, 20000, 60000),
(185, 121, 30, 3, 20000, 60000),
(186, 121, 24, 3, 25000, 75000),
(187, 121, 25, 3, 20000, 60000),
(188, 121, 26, 1, 10000, 10000),
(189, 122, 26, 5, 10000, 50000),
(190, 122, 27, 1, 13000, 13000),
(191, 122, 38, 1, 18000, 18000),
(192, 122, 2, 1, 25000, 25000),
(193, 122, 4, 2, 40000, 80000),
(194, 122, 34, 3, 20000, 60000),
(195, 122, 33, 4, 20000, 80000),
(196, 123, 2, 1, 25000, 25000),
(197, 123, 4, 1, 40000, 40000),
(198, 123, 5, 1, 30000, 30000),
(199, 123, 6, 1, 35000, 35000),
(200, 123, 7, 1, 50000, 50000),
(201, 124, 1, 1, 20000, 20000),
(202, 124, 23, 1, 17000, 17000),
(203, 125, 23, 1, 17000, 17000),
(204, 125, 1, 1, 20000, 20000),
(205, 126, 1, 1, 20000, 20000),
(206, 127, 1, 1, 20000, 20000),
(207, 128, 1, 1, 20000, 20000),
(208, 129, 1, 1, 20000, 20000),
(209, 130, 1, 1, 20000, 20000),
(210, 131, 1, 1, 20000, 20000),
(211, 132, 1, 1, 20000, 20000),
(212, 133, 4, 1, 40000, 40000),
(213, 134, 7, 1, 50000, 50000),
(214, 135, 6, 2, 35000, 70000),
(215, 136, 34, 1, 20000, 20000),
(216, 137, 33, 1, 20000, 20000),
(217, 138, 4, 1, 40000, 40000),
(218, 139, 5, 1, 30000, 30000),
(219, 140, 5, 1, 30000, 30000),
(220, 141, 4, 1, 40000, 40000),
(221, 142, 2, 1, 25000, 25000),
(222, 143, 1, 1, 20000, 20000),
(223, 144, 30, 1, 20000, 20000),
(224, 145, 34, 1, 20000, 20000),
(225, 146, 2, 1, 25000, 25000),
(226, 147, 2, 1, 25000, 25000),
(227, 148, 1, 1, 20000, 20000),
(228, 149, 1, 1, 20000, 20000),
(229, 150, 22, 1, 15000, 15000),
(230, 151, 5, 1, 30000, 30000),
(231, 152, 5, 1, 30000, 30000),
(232, 153, 5, 1, 30000, 30000),
(233, 154, 29, 1, 20000, 20000),
(234, 155, 1, 1, 20000, 20000),
(235, 156, 4, 1, 40000, 40000),
(236, 157, 4, 1, 40000, 40000),
(237, 158, 1, 1, 20000, 20000),
(238, 159, 1, 7, 20000, 140000),
(239, 159, 2, 4, 25000, 100000),
(240, 159, 4, 1, 40000, 40000),
(241, 159, 5, 1, 30000, 30000),
(242, 159, 6, 4, 35000, 140000),
(243, 159, 7, 1, 50000, 50000),
(244, 159, 29, 5, 20000, 100000),
(245, 159, 27, 1, 13000, 13000),
(246, 159, 38, 1, 18000, 18000),
(247, 159, 22, 1, 15000, 15000),
(248, 160, 5, 1, 30000, 30000),
(249, 161, 1, 1, 20000, 20000),
(250, 162, 6, 1, 35000, 35000),
(251, 163, 32, 1, 20000, 20000),
(252, 164, 4, 1, 40000, 40000),
(253, 165, 1, 1, 20000, 20000),
(254, 166, 31, 1, 20000, 20000),
(255, 167, 41, 40, 70000, 2800000),
(256, 167, 40, 20, 70000, 1400000),
(257, 167, 35, 1, 50000, 50000),
(258, 167, 32, 1, 20000, 20000),
(259, 167, 33, 1, 20000, 20000),
(260, 167, 31, 1, 20000, 20000),
(261, 167, 34, 1, 20000, 20000),
(262, 167, 30, 1, 20000, 20000),
(263, 167, 29, 1, 20000, 20000),
(264, 167, 7, 1, 50000, 50000),
(265, 167, 6, 1, 35000, 35000),
(266, 167, 5, 1, 30000, 30000),
(267, 167, 4, 1, 40000, 40000),
(268, 167, 2, 1, 25000, 25000),
(269, 167, 1, 1, 20000, 20000),
(270, 167, 22, 1, 15000, 15000),
(271, 167, 23, 1, 17000, 17000),
(272, 167, 24, 2, 25000, 50000),
(273, 167, 25, 1, 20000, 20000),
(274, 167, 26, 1, 10000, 10000),
(275, 167, 27, 1, 13000, 13000),
(276, 167, 38, 1, 18000, 18000),
(277, 168, 1, 1, 20000, 20000),
(278, 169, 1, 8, 20000, 160000),
(279, 169, 2, 1, 25000, 25000),
(280, 169, 4, 1, 40000, 40000),
(281, 169, 5, 5, 30000, 150000),
(282, 169, 6, 1, 35000, 35000),
(283, 169, 7, 4, 50000, 200000),
(284, 169, 29, 1, 20000, 20000),
(285, 169, 30, 5, 20000, 100000),
(286, 169, 31, 1, 20000, 20000),
(287, 169, 32, 6, 20000, 120000),
(288, 169, 33, 5, 20000, 100000),
(289, 169, 34, 5, 20000, 100000),
(290, 169, 35, 1, 50000, 50000),
(291, 170, 4, 1, 40000, 40000),
(292, 171, 4, 1, 40000, 40000),
(293, 171, 5, 1, 30000, 30000),
(294, 172, 1, 1, 20000, 20000),
(295, 172, 4, 1, 40000, 40000),
(296, 173, 1, 1, 20000, 20000),
(297, 174, 1, 1, 20000, 20000),
(298, 175, 41, 1, 100000, 100000),
(299, 176, 2, 1, 25000, 25000),
(300, 177, 2, 5, 25000, 125000),
(301, 178, 1, 1, 20000, 20000),
(302, 179, 1, 1, 20000, 20000),
(303, 180, 1, 1, 20000, 20000),
(304, 181, 23, 1, 17000, 17000),
(305, 186, 7, 1, 50000, 50000),
(306, 186, 40, 1, 70000, 70000),
(307, 186, 38, 1, 18000, 18000),
(308, 186, 27, 1, 13000, 13000),
(309, 187, 7, 1, 50000, 50000),
(310, 187, 33, 1, 20000, 20000),
(311, 187, 41, 1, 100000, 100000),
(312, 187, 40, 1, 70000, 70000),
(313, 187, 31, 1, 20000, 20000),
(314, 188, 4, 1, 40000, 40000),
(315, 189, 30, 6, 20000, 120000),
(316, 190, 4, 1, 40000, 40000),
(317, 190, 5, 1, 30000, 30000),
(318, 190, 22, 1, 15000, 15000),
(319, 191, 6, 1, 35000, 35000),
(320, 192, 1, 1, 20000, 20000),
(321, 193, 33, 1, 20000, 20000),
(322, 194, 1, 2, 20000, 40000),
(323, 195, 1, 1, 20000, 20000),
(324, 195, 2, 1, 25000, 25000),
(325, 195, 4, 1, 40000, 40000),
(326, 196, 29, 1, 20000, 20000),
(327, 197, 5, 1, 30000, 30000),
(328, 198, 22, 1, 15000, 15000),
(329, 199, 1, 1, 20000, 20000),
(330, 200, 1, 1, 20000, 20000),
(331, 201, 1, 1, 20000, 20000),
(332, 202, 1, 1, 20000, 20000),
(333, 203, 1, 1, 20000, 20000),
(334, 204, 1, 1, 20000, 20000),
(335, 205, 1, 1, 20000, 20000),
(336, 206, 1, 1, 20000, 20000),
(337, 207, 1, 1, 20000, 20000),
(338, 207, 33, 1, 20000, 20000),
(339, 208, 1, 2, 20000, 40000),
(340, 209, 1, 1, 20000, 20000);

-- --------------------------------------------------------

--
-- Table structure for table `meja`
--

CREATE TABLE `meja` (
  `id_meja` int(11) NOT NULL,
  `image_kode_qr` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meja`
--

INSERT INTO `meja` (`id_meja`, `image_kode_qr`) VALUES
(1, 'meja_1.png'),
(2, 'meja_2.png'),
(3, 'meja_3.png'),
(4, 'meja_4.png'),
(5, 'meja_5.png'),
(6, 'meja_6.png'),
(7, 'meja_7.png'),
(8, 'meja_8.png'),
(9, 'meja_9.png'),
(10, 'meja_10.png'),
(13, 'meja_13.png');

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `id_menu` int(11) NOT NULL,
  `nama_menu` varchar(255) NOT NULL,
  `kategori` enum('Makanan','Minuman') NOT NULL,
  `harga` int(10) NOT NULL,
  `gambar` varchar(255) NOT NULL,
  `likes` int(11) DEFAULT 0,
  `waktu` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`id_menu`, `nama_menu`, `kategori`, `harga`, `gambar`, `likes`, `waktu`) VALUES
(1, 'Kimchi Fried Rice with Cheese', 'Makanan', 20000, 'kimci-jigae.png', 141, '2025-05-01 22:31:35'),
(2, 'Kimchi Fried Rice with Oden', 'Makanan', 25000, 'Kmchi+Fried+Rice+Oden+Web.jpg', 11, '2025-04-26 21:24:07'),
(4, 'Jajangmyeon', 'Makanan', 40000, 'Web+Thumbnail+UI+JJM.jpg', 10, '2025-05-26 16:50:52'),
(5, 'Bibimbap Beef ', 'Makanan', 30000, 'Bibimbap+Beef+Bulgogi+Ori+web.jpg', 32, '2025-06-03 16:22:25'),
(6, 'Dosirak Korean Signature', 'Makanan', 35000, 'Dosirak+Korean+Signature+Web.jpg', 9, '2025-04-26 21:24:14'),
(7, 'Dry Ramyun', 'Makanan', 50000, 'Dry+Ramyun+Batagor+Webb.jpg', 10, '2025-05-01 14:56:47'),
(22, 'Classic Coffee', 'Minuman', 15000, 'Classic+Coffee+web.jpg', 2, '2025-04-26 21:25:02'),
(23, 'Banana Milk', 'Minuman', 17000, 'Banana+Milk+web.jpg', 2, '2025-04-26 21:24:59'),
(24, 'Choco Banana Milk', 'Minuman', 25000, 'Choco+Banana+Milk+Web.jpg', 2, '2025-04-26 21:25:00'),
(25, 'Sea Salt Dark Choco', 'Minuman', 20000, 'Sea+Salt+Dark+Choco+web.jpg', 2, '2025-04-26 21:25:01'),
(26, 'Jasmine Tea', 'Minuman', 10000, 'Jasmine+Tea+Web.jpg', 2, '2025-04-26 21:24:58'),
(27, 'Jeju Orange Tea', 'Minuman', 13000, 'Jeju+Orange+Tea+Web.jpg', 2, '2025-04-26 21:24:55'),
(29, 'Bakso', 'Makanan', 20000, 'Bibimbap+Beef+Bulgogi+Ori+web.jpg', 1, '2025-04-26 21:24:11'),
(30, 'mangga', 'Makanan', 20000, 'Matcha+Bingsoo+Web.jpg', 1, '2025-04-26 21:24:10'),
(31, 'mangga', 'Makanan', 20000, 'Matcha+Bingsoo+Web.jpg', 1, '2025-05-14 06:20:42'),
(32, 'tes', 'Makanan', 20000, 'PIC59A.png', 1, '2025-04-26 21:24:16'),
(33, 'Ikan', 'Makanan', 20000, 'Choco+Bingsoo.jpg', 1, '2025-04-26 21:24:17'),
(34, 'rika', 'Makanan', 20000, 'Dosirak+1+Mandu+Ori+Web.jpg', 1, '2025-04-26 21:24:18'),
(35, 'rika', 'Makanan', 50000, 'Kimchi-jigaetopokki-with-mandu.png', 1, '2025-04-27 18:08:46'),
(38, 'rika', 'Minuman', 18000, 'Banana+Milk+web.jpg', 1, '2025-04-27 06:01:25'),
(40, 'menu baru', 'Makanan', 70000, 'Bibimbap+Beef+Bulgogi+Ori+web.jpg', 2, '2025-05-01 12:36:26'),
(41, 'beef', 'Makanan', 100000, 'Beef-ori.png', 1, '2025-05-01 22:27:27'),
(42, 'rika', 'Makanan', 20000, 'pexels-chanwalrus-958545.jpg', 0, '2025-06-16 15:19:31');

-- --------------------------------------------------------

--
-- Table structure for table `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id_pelanggan` int(11) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `notelepon` varchar(20) DEFAULT NULL,
  `waktu_masuk` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pelanggan`
--

INSERT INTO `pelanggan` (`id_pelanggan`, `nama`, `notelepon`, `waktu_masuk`) VALUES
(158, 'percobaan ', '8229179824', '2025-03-15 06:42:43'),
(159, 'percobaan ', '8229179824', '2025-03-15 06:42:52'),
(160, 'percobaan ', '8229179824', '2025-03-15 12:53:56'),
(161, 'rahmat', '085213976352', '2025-03-16 07:55:38'),
(162, 'rahmat', '1', '2025-03-16 08:03:29'),
(163, 'rahmat', '1', '2025-03-16 08:07:39'),
(164, 'rahmat', '1', '2025-03-16 08:07:54'),
(165, 'rahmat', '1', '2025-03-16 08:14:32'),
(166, 'rahmat', '082393570803', '2025-03-16 08:16:43'),
(167, 'rahmat', '082393570803', '2025-03-16 09:08:33'),
(168, 'rahmat', '082393570803', '2025-03-16 09:08:51'),
(169, 'rahmat', '1', '2025-03-16 09:13:48'),
(170, 'rahmat', '1', '2025-03-16 09:14:22'),
(171, 'rahmat', '085824674099', '2025-03-16 09:14:35'),
(172, 'rahmat', '085824674099', '2025-03-16 09:52:10'),
(173, 'dhagjfh', '473294790', '2025-03-16 09:54:23'),
(174, 'Rahmat', '93836383u', '2025-03-16 09:59:35'),
(175, 'rahmat', '923024', '2025-03-30 18:11:18'),
(176, 'rika', '1312844180', '2025-04-02 08:03:55'),
(177, 'rika', '490285903', '2025-04-08 02:58:27'),
(178, 'rika', '489029034', '2025-04-08 02:59:29'),
(179, 'rika', '081753', '2025-04-08 03:10:23'),
(180, 'Rahnat', '484746', '2025-04-13 18:43:46'),
(181, 'rika', '893839082490', '2025-04-14 18:03:24'),
(182, 'Rika', '757545', '2025-04-14 20:07:36'),
(183, 'rika', '194329840', '2025-04-17 06:32:14'),
(184, 'rika', '413u4o', '2025-04-22 04:04:46'),
(185, 'tes', '389080923', '2025-04-26 16:40:26'),
(186, 'rika', '7e3289478932', '2025-04-26 18:54:38'),
(187, 'Rika', 'Halo', '2025-04-26 20:20:40'),
(188, 'Dia', '488373', '2025-04-26 21:05:14'),
(189, 'rika', '7218378927', '2025-04-27 04:36:21'),
(190, 'rika', '389840', '2025-04-27 14:18:26'),
(191, 'Rahmat', '1384477', '2025-04-27 17:10:31'),
(192, 'halo', '438294', '2025-04-27 18:08:03'),
(193, 'rahmat', '31267376', '2025-04-27 18:10:17'),
(194, 'hai', '4378', '2025-04-27 18:20:08'),
(195, 'Rika ', '08286', '2025-04-29 05:02:34'),
(196, 'Rika ', '08286', '2025-04-29 05:05:06'),
(197, 'rika', '08351', '2025-04-29 05:57:42'),
(198, 'Rika ', '07273', '2025-04-29 06:01:58'),
(199, 'Rika ', '0288337', '2025-04-29 06:24:30'),
(200, 'Rika ', '08283', '2025-04-29 06:33:00'),
(201, 'rika', '877887876876', '2025-05-01 12:32:30'),
(202, 'rika', '877887876876', '2025-05-01 12:59:26'),
(203, 'Rika', '75747', '2025-05-01 14:42:25'),
(204, 'Rika ', '383737', '2025-05-01 14:56:31'),
(205, 'Wawan', '085349699104', '2025-05-01 15:05:00'),
(206, 'rika', '877887876876', '2025-05-01 15:28:28'),
(207, 'rika', '877887876876', '2025-05-01 15:33:22'),
(208, 'saya', '382291038', '2025-05-01 15:41:56'),
(209, 'Rika', '377373', '2025-05-01 20:27:05'),
(210, 'amerrr', '085255056767', '2025-05-01 22:29:46'),
(211, 'amerr', '0892737291739', '2025-05-01 22:31:03'),
(212, 'Rahmat', '38363738', '2025-05-01 23:47:27'),
(213, 'Halo', '277272', '2025-05-02 00:29:20'),
(214, 'Rika', 'Halo', '2025-05-05 15:43:09'),
(215, 'tamu', '789687', '2025-05-05 19:21:27'),
(216, 'Rika', 'Rika', '2025-05-08 03:06:55'),
(217, 'Rika', 'Rika', '2025-05-08 03:09:36'),
(218, 'Rika', 'Rika', '2025-05-08 03:15:16'),
(219, 'Saya', '38737383', '2025-05-08 03:22:55'),
(220, 'saya', '9038901283', '2025-05-14 06:14:59'),
(221, 'bye', '90231890', '2025-05-14 06:15:41'),
(222, 'bye', '90231890', '2025-05-14 06:55:20'),
(223, 'rika', 'rika', '2025-05-15 06:16:52'),
(224, 'Saya', '387373', '2025-05-20 03:16:08'),
(225, 'Halo', '39338', '2025-05-20 03:30:43'),
(226, 'rika', 'rika', '2025-05-20 03:40:54'),
(227, 'saya', '310948194', '2025-05-23 16:32:22'),
(228, 'ical', '081266612002', '2025-05-23 16:53:19'),
(229, 'tuuyu', '44224', '2025-05-26 16:50:37'),
(230, 'rika', '8943190348', '2025-05-28 05:25:14'),
(231, 'Tri', '09762441728393', '2025-06-03 16:22:11'),
(232, 'Rika ', 'rika', '2025-06-16 15:07:26'),
(233, 'Hao', '65674', '2025-06-16 15:17:42'),
(234, 'oi', '824395', '2025-06-16 15:35:06'),
(235, 'Juy', '8474', '2025-06-16 15:53:05'),
(236, 'Io', '3773', '2025-06-16 16:03:02'),
(237, 'dampang', 'dampang', '2025-06-17 02:57:47'),
(238, 'bayu', '087263', '2025-06-19 05:41:57'),
(239, 'rika', 'rika', '2025-06-19 05:50:10'),
(240, 'ayu', 'ayu', '2025-06-19 05:51:45'),
(241, 'dali', '0976', '2025-06-19 06:05:06'),
(242, 'Saya', '0497494', '2025-07-02 00:58:37'),
(243, 'Halo', '86567', '2025-07-02 01:52:12'),
(244, 'amer', '081342123008', '2025-07-02 02:11:20'),
(245, 'halo', '88e4920', '2025-07-02 02:44:26'),
(246, 'rika', '988', '2025-07-02 03:15:08'),
(247, 'rika', '7897698', '2025-07-02 05:52:35');

-- --------------------------------------------------------

--
-- Table structure for table `pesanan`
--

CREATE TABLE `pesanan` (
  `id_pesanan` int(11) NOT NULL,
  `id_meja` int(11) DEFAULT NULL,
  `id_pelanggan` int(11) DEFAULT NULL,
  `status` enum('pending','diproses','selesai','dibatalkan') DEFAULT 'pending',
  `total_harga` int(10) NOT NULL,
  `waktu_pesan` timestamp NOT NULL DEFAULT current_timestamp(),
  `metode_pembayaran` enum('kasir','midtrans') NOT NULL DEFAULT 'kasir'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pesanan`
--

INSERT INTO `pesanan` (`id_pesanan`, `id_meja`, `id_pelanggan`, `status`, `total_harga`, `waktu_pesan`, `metode_pembayaran`) VALUES
(110, 2, 201, 'pending', 60000, '2025-05-01 12:36:53', 'midtrans'),
(111, 2, 201, 'pending', 60000, '2025-05-01 12:37:07', 'midtrans'),
(112, 2, 201, 'pending', 60000, '2025-05-01 12:37:27', 'midtrans'),
(113, 2, 201, 'diproses', 60000, '2025-05-01 12:40:03', 'midtrans'),
(114, 2, 201, 'pending', 20000, '2025-05-01 12:52:03', 'kasir'),
(115, 2, 201, 'pending', 25000, '2025-05-01 12:52:28', 'midtrans'),
(116, 2, 202, 'pending', 20000, '2025-05-01 13:11:13', 'kasir'),
(117, 2, 202, 'pending', 40000, '2025-05-01 13:11:26', 'midtrans'),
(118, 7, 203, 'pending', 20000, '2025-05-01 14:42:39', 'kasir'),
(119, 7, 203, 'pending', 20000, '2025-05-01 14:42:53', 'midtrans'),
(120, 7, 203, 'pending', 20000, '2025-05-01 14:47:27', 'midtrans'),
(121, 7, 203, 'pending', 515000, '2025-05-01 14:48:42', 'kasir'),
(122, 7, 203, 'pending', 326000, '2025-05-01 14:49:12', 'midtrans'),
(123, 8, 204, 'pending', 180000, '2025-05-01 14:56:51', 'midtrans'),
(124, 8, 205, 'pending', 37000, '2025-05-01 15:05:33', 'kasir'),
(125, 8, 205, 'pending', 37000, '2025-05-01 15:05:47', 'midtrans'),
(126, 2, 208, 'pending', 20000, '2025-05-01 16:31:09', 'kasir'),
(127, 2, 208, 'pending', 20000, '2025-05-01 16:33:58', 'kasir'),
(128, 2, 208, 'pending', 20000, '2025-05-01 16:37:58', 'kasir'),
(129, 2, 208, 'pending', 20000, '2025-05-01 16:49:24', 'kasir'),
(130, 2, 208, 'pending', 20000, '2025-05-01 16:49:35', 'kasir'),
(131, 2, 208, 'pending', 20000, '2025-05-01 16:52:17', 'kasir'),
(132, 2, 208, 'pending', 20000, '2025-05-01 16:59:33', 'kasir'),
(133, 2, 208, 'pending', 40000, '2025-05-01 17:11:38', 'kasir'),
(134, 2, 208, 'diproses', 50000, '2025-05-01 17:14:13', 'kasir'),
(135, 2, 208, 'pending', 70000, '2025-05-01 17:15:32', 'kasir'),
(136, 2, 208, 'pending', 20000, '2025-05-01 17:21:44', 'kasir'),
(137, 2, 208, 'pending', 20000, '2025-05-01 17:23:55', 'kasir'),
(138, 2, 208, 'pending', 40000, '2025-05-01 17:35:03', 'kasir'),
(139, 2, 208, 'pending', 30000, '2025-05-01 17:37:14', 'kasir'),
(140, 2, 208, 'pending', 30000, '2025-05-01 17:37:50', 'kasir'),
(141, 2, 208, 'pending', 40000, '2025-05-01 17:38:21', 'kasir'),
(142, 2, 208, 'pending', 25000, '2025-05-01 17:43:05', 'kasir'),
(143, 2, 208, 'dibatalkan', 20000, '2025-05-01 17:51:46', 'kasir'),
(144, 2, 208, 'diproses', 20000, '2025-05-01 18:18:56', 'kasir'),
(145, 2, 208, 'diproses', 20000, '2025-05-01 18:23:56', 'kasir'),
(146, 2, 208, 'dibatalkan', 25000, '2025-05-01 19:49:31', 'kasir'),
(147, 2, 208, 'diproses', 25000, '2025-05-01 20:01:30', 'kasir'),
(148, 2, 208, 'pending', 20000, '2025-05-01 20:16:28', 'midtrans'),
(149, 2, 208, 'diproses', 20000, '2025-05-01 20:21:09', 'kasir'),
(150, 2, 208, 'diproses', 15000, '2025-05-01 20:28:02', 'kasir'),
(151, 9, 209, 'pending', 30000, '2025-05-01 20:31:03', 'midtrans'),
(152, 9, 209, 'pending', 30000, '2025-05-01 20:31:56', 'midtrans'),
(153, 10, 209, 'pending', 30000, '2025-05-01 20:32:29', 'midtrans'),
(154, 2, 208, 'pending', 20000, '2025-05-01 20:46:59', 'midtrans'),
(155, 10, 209, 'diproses', 20000, '2025-05-01 20:52:07', 'kasir'),
(156, 2, 208, 'pending', 40000, '2025-05-01 21:09:15', 'kasir'),
(157, 2, 208, 'pending', 40000, '2025-05-01 21:10:02', 'kasir'),
(158, 2, 208, 'diproses', 20000, '2025-05-01 21:29:18', 'kasir'),
(159, 2, 208, 'diproses', 646000, '2025-05-01 21:34:56', 'kasir'),
(160, 2, 208, 'pending', 30000, '2025-05-01 21:50:56', 'midtrans'),
(161, 2, 208, 'pending', 20000, '2025-05-01 21:52:27', 'midtrans'),
(162, 2, 208, 'dibatalkan', 35000, '2025-05-01 22:04:28', 'kasir'),
(163, 2, 208, 'pending', 20000, '2025-05-01 22:04:37', 'midtrans'),
(164, 2, 208, 'pending', 40000, '2025-05-01 22:09:06', 'midtrans'),
(165, 2, 208, 'diproses', 20000, '2025-05-01 22:13:53', 'midtrans'),
(166, 2, 208, 'diproses', 20000, '2025-05-01 22:23:36', 'midtrans'),
(167, 2, 208, 'diproses', 4713000, '2025-05-01 22:28:01', 'kasir'),
(168, 9, 211, 'diproses', 20000, '2025-05-01 22:31:51', 'kasir'),
(169, 9, 212, 'selesai', 1120000, '2025-05-01 23:48:11', 'kasir'),
(170, 9, 212, 'selesai', 40000, '2025-05-01 23:49:57', 'kasir'),
(171, 9, 213, 'selesai', 70000, '2025-05-02 00:29:30', 'kasir'),
(172, 10, 214, 'diproses', 60000, '2025-05-05 15:43:41', 'kasir'),
(173, 10, 214, 'pending', 20000, '2025-05-05 16:37:12', 'midtrans'),
(174, 10, 214, 'diproses', 20000, '2025-05-05 16:37:44', 'midtrans'),
(175, 2, 215, 'diproses', 100000, '2025-05-05 19:21:48', 'kasir'),
(176, 2, 215, 'diproses', 25000, '2025-05-05 19:39:15', 'kasir'),
(177, 1, 216, 'diproses', 125000, '2025-05-08 03:07:48', 'kasir'),
(178, 1, 217, 'pending', 20000, '2025-05-08 03:09:51', 'midtrans'),
(179, 1, 218, 'dibatalkan', 20000, '2025-05-08 03:15:28', 'kasir'),
(180, 1, 218, 'diproses', 20000, '2025-05-08 03:15:52', 'midtrans'),
(181, 2, 221, 'diproses', 17000, '2025-05-14 06:20:55', 'kasir'),
(186, 2, 222, 'dibatalkan', 151000, '2025-05-14 06:56:53', 'kasir'),
(187, 2, 222, 'selesai', 260000, '2025-05-14 06:58:45', 'kasir'),
(188, 2, 222, 'diproses', 40000, '2025-05-14 07:02:08', 'kasir'),
(189, 2, 222, 'diproses', 120000, '2025-05-14 07:27:19', 'kasir'),
(190, 1, 224, 'dibatalkan', 85000, '2025-05-20 03:16:45', 'kasir'),
(191, 1, 225, 'selesai', 35000, '2025-05-20 03:30:54', 'midtrans'),
(192, 2, 226, 'selesai', 20000, '2025-05-20 03:41:38', 'midtrans'),
(193, 2, 227, 'selesai', 20000, '2025-05-23 16:35:30', 'kasir'),
(194, 1, 228, 'selesai', 40000, '2025-05-23 16:53:50', 'midtrans'),
(195, 2, 230, 'selesai', 85000, '2025-05-28 05:25:31', 'kasir'),
(196, 2, 230, 'selesai', 20000, '2025-05-28 05:27:01', 'midtrans'),
(197, 1, 231, 'selesai', 30000, '2025-06-03 16:22:55', 'kasir'),
(198, 1, 232, 'selesai', 15000, '2025-06-16 15:08:17', 'kasir'),
(199, 1, 233, 'selesai', 20000, '2025-06-16 15:21:35', 'midtrans'),
(200, 1, 233, 'pending', 20000, '2025-06-16 15:26:26', 'midtrans'),
(201, 1, 233, 'pending', 20000, '2025-06-16 15:26:59', 'midtrans'),
(202, 1, 233, 'pending', 20000, '2025-06-16 15:28:16', 'midtrans'),
(203, 1, 233, 'pending', 20000, '2025-06-16 15:31:12', 'midtrans'),
(204, 1, 233, 'pending', 20000, '2025-06-16 15:32:03', 'midtrans'),
(205, 1, 233, 'selesai', 20000, '2025-06-16 15:32:32', 'midtrans'),
(206, 1, 237, 'selesai', 20000, '2025-06-17 02:58:26', 'kasir'),
(207, 1, 238, 'selesai', 40000, '2025-06-19 05:42:27', 'kasir'),
(208, 1, 240, 'selesai', 40000, '2025-06-19 05:52:41', 'kasir'),
(209, 1, 241, 'diproses', 20000, '2025-06-19 06:05:31', 'kasir');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','pegawai','kasir','chef') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', 'admin', 'admin'),
(2, 'rika', 'rika', 'admin'),
(4, 'dapur', 'dapur', 'chef');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_pesanan` (`id_pesanan`),
  ADD KEY `fk_detail_pesanan_id_menu` (`id_menu`);

--
-- Indexes for table `meja`
--
ALTER TABLE `meja`
  ADD PRIMARY KEY (`id_meja`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id_menu`);

--
-- Indexes for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id_pelanggan`);

--
-- Indexes for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD PRIMARY KEY (`id_pesanan`),
  ADD KEY `fk_pesanan_id_user` (`id_pelanggan`),
  ADD KEY `fk_pesanan_meja` (`id_meja`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=341;

--
-- AUTO_INCREMENT for table `meja`
--
ALTER TABLE `meja`
  MODIFY `id_meja` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `id_menu` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id_pelanggan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=248;

--
-- AUTO_INCREMENT for table `pesanan`
--
ALTER TABLE `pesanan`
  MODIFY `id_pesanan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=210;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detail_pesanan`
--
ALTER TABLE `detail_pesanan`
  ADD CONSTRAINT `detail_pesanan_ibfk_1` FOREIGN KEY (`id_pesanan`) REFERENCES `pesanan` (`id_pesanan`),
  ADD CONSTRAINT `detail_pesanan_ibfk_2` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`),
  ADD CONSTRAINT `fk_detail_pesanan_id_menu` FOREIGN KEY (`id_menu`) REFERENCES `menu` (`id_menu`);

--
-- Constraints for table `pesanan`
--
ALTER TABLE `pesanan`
  ADD CONSTRAINT `fk_pesanan_id_user` FOREIGN KEY (`id_pelanggan`) REFERENCES `pelanggan` (`id_pelanggan`),
  ADD CONSTRAINT `fk_pesanan_meja` FOREIGN KEY (`id_meja`) REFERENCES `meja` (`id_meja`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
