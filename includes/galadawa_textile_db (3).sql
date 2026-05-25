-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 20, 2026 at 03:31 AM
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
-- Database: `galadawa_textile_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `payout_requests`
--

CREATE TABLE `payout_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `user_unread` tinyint(1) NOT NULL DEFAULT 0,
  `admin_unread` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payout_requests`
--

INSERT INTO `payout_requests` (`id`, `user_id`, `amount`, `status`, `requested_at`, `reviewed_at`, `reviewed_by`, `note`, `review_note`, `user_unread`, `admin_unread`) VALUES
(1, 3, 20000.00, 'approved', '2026-02-02 00:27:14', '2026-02-02 00:28:16', 1, '0835265065', '', 0, 0),
(2, 3, 2000.00, 'rejected', '2026-02-02 00:55:09', '2026-02-02 00:58:58', 1, '0835265065 opay', 'Sorry', 0, 0),
(3, 3, 6000.00, 'approved', '2026-02-02 00:57:41', '2026-02-02 00:59:05', 1, '', '', 0, 0),
(4, 3, 3000.00, 'rejected', '2026-02-02 01:02:38', '2026-02-02 01:03:17', 1, '0835265065 opay', '', 0, 0),
(5, 3, 100.00, 'approved', '2026-02-02 01:17:23', '2026-02-02 01:18:37', 1, '0835265065 opay', 'enjoy your life', 0, 0),
(6, 2, 15000.00, 'approved', '2026-02-02 01:21:35', '2026-02-02 01:22:24', 1, '1131186442, polaris bank', '', 0, 0),
(7, 4, 25000.00, 'approved', '2026-02-02 01:24:34', '2026-02-02 01:26:05', 1, '2204348290 UBA', 'Paid', 0, 0),
(8, 2, 2000.00, 'rejected', '2026-02-02 01:35:34', '2026-02-02 01:36:54', 1, '1131186442, Polaris, ahmad Shuaibu', 'You recently requested', 0, 0),
(9, 2, 5000.00, 'approved', '2026-02-02 01:41:30', '2026-02-02 01:50:51', 1, '1131186442, Polaris, ahmad Shuaibu', '', 0, 0),
(10, 4, 100.00, 'approved', '2026-02-02 01:45:47', '2026-02-02 01:50:53', 1, '1131186442, Polaris, ahmad Shuaibu', '', 0, 0),
(11, 2, 12000.00, 'approved', '2026-02-02 01:50:15', '2026-02-02 01:50:55', 1, '0835265065, opay', '', 0, 0),
(12, 2, 13000.00, 'approved', '2026-02-02 01:51:51', '2026-02-02 01:56:06', 1, '0835265065, opay', '', 0, 0),
(13, 2, 6000.00, 'approved', '2026-02-02 01:55:44', '2026-02-02 02:03:23', 1, '0835265065, opay', '', 0, 0),
(14, 4, 10000.00, 'approved', '2026-02-02 01:59:12', '2026-02-02 02:04:34', 1, '0835265065, opay', '', 0, 0),
(15, 4, 1000.00, 'approved', '2026-02-02 02:03:52', '2026-02-02 02:04:27', 1, '1131186442, Polaris, ahmad Shuaibu', '', 0, 0),
(16, 4, 9000.00, 'rejected', '2026-02-02 10:53:38', '2026-02-02 10:54:39', 1, '0835265065, opay', 'You recently requested', 0, 0),
(17, 4, 5000.00, 'approved', '2026-02-02 21:22:01', '2026-02-02 21:23:10', 1, '0835265065, opay', 'Paid', 0, 0),
(18, 4, 18650.00, 'rejected', '2026-02-24 11:58:35', '2026-02-24 12:03:34', 1, '0835265065, opay', 'Sorry, we are running out of money', 0, 0),
(19, 4, 1500.00, 'approved', '2026-03-07 15:25:05', '2026-03-07 15:25:38', 1, '0835265065, opay', 'Paid', 0, 0),
(20, 2, 5000.00, 'approved', '2026-03-11 01:18:28', '2026-03-11 01:20:11', 1, '0835265065, opay', '', 0, 0),
(21, 2, 5000.00, 'rejected', '2026-03-11 01:19:38', '2026-03-11 15:20:37', 1, '0835265065, opay', '', 0, 0),
(22, 2, 2000.00, 'rejected', '2026-03-12 21:21:10', '2026-03-12 23:01:49', 1, '1131186442, Polaris, ahmad Shuaibu', 'You recently requested', 0, 0),
(23, 2, 1000.00, 'approved', '2026-03-12 23:09:07', '2026-03-12 23:13:59', 1, '0835265065, opay', '', 0, 0),
(24, 2, 3900.00, 'approved', '2026-03-12 23:09:25', '2026-03-12 23:14:06', 1, '0835265065, opay', '', 0, 0),
(25, 2, 35000.00, 'rejected', '2026-03-12 23:09:37', '2026-03-12 23:14:01', 1, '0835265065, opay', '', 0, 0),
(26, 4, 7000.00, 'approved', '2026-03-12 23:13:35', '2026-03-12 23:14:02', 1, '1131186442, Polaris, ahmad Shuaibu', '', 0, 0),
(27, 3, 1000.00, 'approved', '2026-03-20 03:02:45', '2026-03-20 03:03:15', 1, '0835265065, opay', 'Done', 0, 0),
(28, 3, 100.00, 'rejected', '2026-03-20 03:16:34', '2026-03-20 03:17:17', 1, '0835265065, opay', '', 0, 0),
(29, 3, 100.00, 'approved', '2026-03-20 03:17:53', '2026-03-20 03:18:50', 1, '0835265065, opay', 'Paid', 0, 0),
(30, 3, 100.00, 'approved', '2026-03-20 03:25:35', '2026-03-20 03:26:04', 1, '0835265065, opay', 'Paid', 0, 0),
(31, 3, 500.00, 'rejected', '2026-03-20 03:26:30', '2026-03-20 03:27:04', 1, '0835265065, opay', '', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `buy_price` decimal(10,2) NOT NULL,
  `sell_price` decimal(10,2) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 0.00,
  `min_stock` decimal(10,2) DEFAULT 10.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `buy_price`, `sell_price`, `quantity`, `min_stock`, `created_at`, `image`) VALUES
(1, 'New Ciebo Lace', 'Swiss Lace', 50000.00, 57000.00, 53.00, 20.00, '2026-01-15 23:41:13', 'default.png'),
(2, 'Glitter Pure Stone', 'Atampa', 20000.00, 25000.00, 26.00, 10.00, '2026-01-15 23:41:13', 'default.png'),
(3, 'Classic Kindee', 'Zanna Cap', 13000.00, 15000.00, 2.00, 5.00, '2026-01-16 01:20:55', 'default.png'),
(4, 'Ultimate Plus', 'Shadda', 4300.00, 5500.00, 60.00, 30.00, '2026-01-16 01:23:36', 'default.png'),
(5, 'Exclusive Caps', 'Zanna Cap', 15000.00, 18000.00, 5.00, 3.00, '2026-01-16 01:25:08', 'default.png'),
(6, 'Dara', 'Maitama Cap', 18000.00, 20000.00, 2.00, 1.00, '2026-01-16 02:12:47', 'product_69699e9fb3cef.jpg'),
(7, 'Eleganza', 'Bama Cap', 6000.00, 8000.00, 13.00, 6.00, '2026-01-16 10:57:12', 'default.png'),
(8, 'Kafi Tangaran', 'Zanna Cap', 10000.00, 12000.00, 6.00, 6.00, '2026-01-16 10:58:23', 'default.png'),
(9, 'junior getzner', 'Zanna Cap', 8500.00, 10000.00, 62.00, 10.00, '2026-01-16 13:25:42', 'default.png'),
(10, 'VIP Exclusive', 'Tangaran Cap', 25000.00, 26000.00, 7.00, 3.00, '2026-01-16 20:46:00', 'default.png'),
(11, 'Atiku and Bindo', 'Bama Cap', 5500.00, 7000.00, 26.00, 10.00, '2026-01-16 21:49:58', 'default.png'),
(12, 'Suprime Terra', 'Shadda', 4000.00, 5500.00, 112.00, 10.00, '2026-01-16 23:52:01', 'default.png'),
(13, 'Suprime Bangool', 'Tangaran Cap', 21000.00, 23000.00, 16.00, 5.00, '2026-01-23 20:45:31', 'default.png'),
(14, 'VIP Phantom', 'Tangaran Cap', 30000.00, 32000.00, 12.00, 5.00, '2026-01-23 20:47:32', 'default.png'),
(15, 'Oman Dubai', 'Bama Cap', 10000.00, 14000.00, 12.00, 0.00, '2026-03-04 22:36:22', 'default.png'),
(16, 'M-Phantom', 'Tangaran Cap', 12000.00, 15000.00, 11.00, 4.00, '2026-03-04 22:37:58', 'default.png'),
(17, 'VIP Exclusivess', 'Zanna Cap', 12000.00, 16000.00, 3.00, 1.00, '2026-03-11 14:19:02', 'default.png'),
(18, 'phantom jr', 'Zanna Cap', 10000.00, 11000.00, 65.00, 15.00, '2026-03-12 21:58:02', 'default.png');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `status` enum('available','sold_out') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_name`, `status`) VALUES
(5, 7, 'prod_7_696a1988bd321.jpg', 'available'),
(7, 10, 'prod_10_696aa388c50be.jpg', 'available'),
(9, 10, 'prod_10_696aa388d7b77.jpg', 'sold_out'),
(14, 10, 'prod_10_696aa388e36a3.jpg', 'available'),
(15, 10, 'prod_10_696aa388e5703.jpg', 'available'),
(16, 10, 'prod_10_696aa388e774b.jpg', 'available'),
(18, 10, 'prod_10_696aa388ed7d4.jpg', 'available'),
(19, 10, 'prod_10_696aa388efe3a.jpg', 'available'),
(20, 10, 'prod_10_696aa388f2134.jpg', 'available'),
(29, 3, 'prod_3_696ace3d2ce08.jpg', 'available'),
(30, 3, 'prod_3_696ace3d33076.jpg', 'available'),
(31, 3, 'prod_3_696ace3d38f32.jpg', 'sold_out'),
(32, 3, 'prod_3_696ace3d3ac05.jpg', 'sold_out'),
(33, 3, 'prod_3_696ace3d3caac.jpg', 'sold_out'),
(34, 12, 'prod_12_696acf211bfaf.jpg', 'available'),
(49, 8, 'prod_8_6971803912e12.jpg', 'sold_out'),
(50, 8, 'prod_8_69718039190ee.jpg', 'sold_out'),
(51, 8, 'prod_8_697180391af95.jpg', 'available'),
(52, 8, 'prod_8_697180391cd23.jpg', 'available'),
(53, 8, 'prod_8_697180391e5bf.jpg', 'available'),
(54, 8, 'prod_8_6971803920ccd.jpg', 'available'),
(55, 8, 'prod_8_697180392670e.jpg', 'available'),
(56, 8, 'prod_8_697180392835b.jpg', 'available'),
(62, 6, 'prod_6_6973d58dacc71.jpg', 'available'),
(63, 6, 'prod_6_6973d58db7a81.jpg', 'available'),
(84, 11, 'prod_11_6973dc3a5202a.jpg', 'sold_out'),
(85, 11, 'prod_11_6973dc3a53c66.jpg', 'sold_out'),
(86, 11, 'prod_11_6973dc3a55887.jpg', 'sold_out'),
(87, 11, 'prod_11_6973dc3a57006.jpg', 'sold_out'),
(88, 11, 'prod_11_6973dc3a588af.jpg', 'available'),
(89, 11, 'prod_11_6973dc3a5a3a7.jpg', 'available'),
(90, 11, 'prod_11_6973dc3a5bd16.jpg', 'available'),
(91, 11, 'prod_11_6973dc3a60327.jpg', 'sold_out'),
(92, 11, 'prod_11_6973dc3a61fa6.jpg', 'sold_out'),
(93, 11, 'prod_11_6973dc3a6383d.jpg', 'available'),
(94, 11, 'prod_11_6973dc3a65243.jpg', 'available'),
(95, 11, 'prod_11_6973dc3a66e5e.jpg', 'available'),
(96, 11, 'prod_11_6973dc3a687f5.jpg', 'sold_out'),
(97, 11, 'prod_11_6973dc3a6a344.jpg', 'available'),
(98, 11, 'prod_11_6973dc3a6bada.jpg', 'available'),
(99, 11, 'prod_11_6973dc3a6d1ed.jpg', 'available'),
(100, 11, 'prod_11_6973dc3a6ead1.jpg', 'sold_out'),
(101, 11, 'prod_11_6973dc3a7035b.jpg', 'available'),
(102, 11, 'prod_11_6973dc3a71ba4.jpg', 'available'),
(103, 11, 'prod_11_6973dc3a7338c.jpg', 'available'),
(104, 11, 'prod_11_6973dc5681848.jpg', 'available'),
(105, 11, 'prod_11_6973dc5683731.jpg', 'available'),
(106, 11, 'prod_11_6973dc568518e.jpg', 'available'),
(107, 11, 'prod_11_6973dc56868fa.jpg', 'available'),
(108, 11, 'prod_11_6973dc568807a.jpg', 'available'),
(109, 11, 'prod_11_6973dc5689a8a.jpg', 'sold_out'),
(110, 11, 'prod_11_6973dc568b4ff.jpg', 'available'),
(111, 11, 'prod_11_6973dc568ce86.jpg', 'available'),
(112, 11, 'prod_11_6973dc568e775.jpg', 'available'),
(113, 11, 'prod_11_6973dc5692a25.jpg', 'available'),
(114, 11, 'prod_11_6973dc5694423.jpg', 'available'),
(115, 11, 'prod_11_6973dc5695f9a.jpg', 'available'),
(116, 11, 'prod_11_6973dc5697bc2.jpg', 'available'),
(117, 11, 'prod_11_6973dc56993c9.jpg', 'sold_out'),
(118, 11, 'prod_11_6973dc569aea4.jpg', 'sold_out'),
(119, 11, 'prod_11_6973dc569c6b6.jpg', 'available'),
(120, 11, 'prod_11_6973dc569df1d.jpg', 'sold_out'),
(121, 11, 'prod_11_6973dc56a038b.jpg', 'sold_out'),
(122, 11, 'prod_11_6973dc56a21e3.jpg', 'available'),
(123, 11, 'prod_11_6973dc56a4474.jpg', 'sold_out'),
(124, 13, 'prod_13_6973ddebed791.jpg', 'available'),
(125, 13, 'prod_13_6973ddebf1fbc.jpg', 'available'),
(126, 13, 'prod_13_6973ddebf38d6.jpg', 'available'),
(127, 13, 'prod_13_6973ddec0150e.jpg', 'available'),
(128, 13, 'prod_13_6973ddec0304a.jpg', 'available'),
(129, 13, 'prod_13_6973ddec04be7.jpg', 'available'),
(130, 13, 'prod_13_6973ddec06603.jpg', 'available'),
(131, 13, 'prod_13_6973ddec07d5f.jpg', 'available'),
(132, 13, 'prod_13_6973ddec09634.jpg', 'available'),
(133, 13, 'prod_13_6973ddec0ae2e.jpg', 'available'),
(134, 13, 'prod_13_6973ddec0cabe.jpg', 'available'),
(135, 13, 'prod_13_6973ddec10f41.jpg', 'available'),
(136, 13, 'prod_13_6973ddec1255e.jpg', 'available'),
(137, 13, 'prod_13_6973ddec13c53.jpg', 'available'),
(138, 13, 'prod_13_6973ddec153db.jpg', 'available'),
(139, 13, 'prod_13_6973ddec16b1f.jpg', 'available'),
(140, 14, 'prod_14_6973de646fe71.jpg', 'sold_out'),
(141, 14, 'prod_14_6973de6471695.jpg', 'available'),
(142, 14, 'prod_14_6973de6472da0.jpg', 'available'),
(143, 14, 'prod_14_6973de64745d1.jpg', 'available'),
(144, 14, 'prod_14_6973de6475db1.jpg', 'available'),
(145, 14, 'prod_14_6973de64776cf.jpg', 'available'),
(146, 14, 'prod_14_6973de64790be.jpg', 'available'),
(147, 14, 'prod_14_6973de647d776.jpg', 'available'),
(148, 14, 'prod_14_6973de647ee97.jpg', 'available'),
(149, 14, 'prod_14_6973de648055c.jpg', 'available'),
(150, 14, 'prod_14_6973de6481cd5.jpg', 'available'),
(151, 14, 'prod_14_6973de64835b3.jpg', 'available'),
(152, 14, 'prod_14_6973de6484e30.jpg', 'available'),
(153, 9, 'prod_9_6973dea608c54.jpg', 'available'),
(154, 9, 'prod_9_6973dea60b9d9.jpg', 'sold_out'),
(155, 9, 'prod_9_6973dea60d79c.jpg', 'available'),
(156, 9, 'prod_9_6973dea60f955.jpg', 'available'),
(157, 9, 'prod_9_6973dea611a94.jpg', 'available'),
(158, 9, 'prod_9_6973dea61372d.jpg', 'available'),
(159, 9, 'prod_9_6973dea615839.jpg', 'sold_out'),
(160, 9, 'prod_9_6973dea61a458.jpg', 'sold_out'),
(161, 9, 'prod_9_6973dea61be78.jpg', 'sold_out'),
(162, 9, 'prod_9_6973dea61d7ee.jpg', 'available'),
(163, 9, 'prod_9_6973dea61f087.jpg', 'available'),
(164, 9, 'prod_9_6973dea6207d4.jpg', 'available'),
(165, 9, 'prod_9_6973dea623c94.jpg', 'available'),
(166, 9, 'prod_9_6973dea625822.jpg', 'available'),
(167, 9, 'prod_9_6973dea626f00.jpg', 'available'),
(168, 9, 'prod_9_6973dea628968.jpg', 'available'),
(169, 9, 'prod_9_6973dea62a85f.jpg', 'available'),
(170, 9, 'prod_9_6973dea62cd79.jpg', 'available'),
(171, 9, 'prod_9_6973dea62e656.jpg', 'available'),
(172, 9, 'prod_9_6973dea630298.jpg', 'available'),
(173, 9, 'prod_9_6973df222394a.jpg', 'available'),
(174, 9, 'prod_9_6973df222506f.jpg', 'available'),
(175, 9, 'prod_9_6973df2226b31.jpg', 'available'),
(176, 9, 'prod_9_6973df22286de.jpg', 'available'),
(177, 9, 'prod_9_6973df2229eec.jpg', 'available'),
(178, 9, 'prod_9_6973df222b7ca.jpg', 'available'),
(179, 9, 'prod_9_6973df222d70c.jpg', 'available'),
(180, 9, 'prod_9_6973df222f459.jpg', 'available'),
(181, 9, 'prod_9_6973df2230d69.jpg', 'available'),
(182, 9, 'prod_9_6973df2232b45.jpg', 'available'),
(183, 9, 'prod_9_6973df22344ef.jpg', 'available'),
(184, 9, 'prod_9_6973df2235d87.jpg', 'available'),
(185, 9, 'prod_9_6973df22377fc.jpg', 'available'),
(186, 9, 'prod_9_6973df223928e.jpg', 'available'),
(187, 9, 'prod_9_6973df223ad9f.jpg', 'available'),
(188, 9, 'prod_9_6973df223c873.jpg', 'available'),
(189, 9, 'prod_9_6973df223dff5.jpg', 'available'),
(190, 9, 'prod_9_6973df2242c84.jpg', 'available'),
(191, 9, 'prod_9_6973df2244499.jpg', 'available'),
(192, 9, 'prod_9_6973df2245e67.jpg', 'available'),
(193, 9, 'prod_9_6973e1709af04.jpg', 'available'),
(194, 9, 'prod_9_6973e1709daf5.jpg', 'available'),
(195, 9, 'prod_9_6973e1709f731.jpg', 'available'),
(196, 9, 'prod_9_6973e170a2f4f.jpg', 'available'),
(197, 9, 'prod_9_6973e170a544d.jpg', 'available'),
(198, 9, 'prod_9_6973e170a6e89.jpg', 'available'),
(199, 9, 'prod_9_6973e170a8803.jpg', 'available'),
(200, 9, 'prod_9_6973e170a9f92.jpg', 'available'),
(201, 9, 'prod_9_6973e170ab885.jpg', 'available'),
(202, 9, 'prod_9_6973e170ad0f2.jpg', 'available'),
(203, 9, 'prod_9_6973e170ae8ff.jpg', 'available'),
(204, 9, 'prod_9_6973e170b02ac.jpg', 'available'),
(205, 9, 'prod_9_6973e170b1c5b.jpg', 'available'),
(206, 9, 'prod_9_6973e170b33a5.jpg', 'available'),
(207, 9, 'prod_9_6973e170b4e31.jpg', 'available'),
(208, 9, 'prod_9_6973e170ba663.jpg', 'available'),
(209, 9, 'prod_9_6973e170bbd6b.jpg', 'available'),
(210, 9, 'prod_9_6973e170bd449.jpg', 'available'),
(211, 9, 'prod_9_6973e170beabc.jpg', 'available'),
(212, 9, 'prod_9_6973e170c0269.jpg', 'available'),
(213, 9, 'prod_9_6973e170c2e6b.jpg', 'available'),
(214, 9, 'prod_9_6973e170c462f.jpg', 'available'),
(215, 9, 'prod_9_6973e170c70d8.jpg', 'available'),
(216, 9, 'prod_9_6973e170c87e1.jpg', 'available'),
(217, 9, 'prod_9_6973e170ca063.jpg', 'available'),
(218, 9, 'prod_9_6973e170cb6ff.jpg', 'available'),
(219, 7, 'prod_7_6973e37b572a3.jpg', 'available'),
(220, 7, 'prod_7_6973e37b5d33f.jpg', 'available'),
(221, 7, 'prod_7_6973e37b6090f.jpg', 'available'),
(222, 7, 'prod_7_6973e37b6884a.jpg', 'available'),
(223, 7, 'prod_7_6973e37b6a223.jpg', 'available'),
(224, 7, 'prod_7_6973e37b6bb2c.jpg', 'available'),
(225, 7, 'prod_7_6973e37b6d3ce.jpg', 'available'),
(226, 7, 'prod_7_6973e37b6ec1b.jpg', 'available'),
(227, 7, 'prod_7_6973e37b702d0.jpg', 'available'),
(228, 7, 'prod_7_6973e37b718a7.jpg', 'available'),
(229, 7, 'prod_7_6973e37b72f46.jpg', 'available'),
(230, 7, 'prod_7_6973e37b743d1.jpg', 'available'),
(231, 4, 'prod_4_6973e752d0543.jpg', 'available'),
(233, 2, 'prod_2_69a94b95aac3b.jpg', 'available'),
(234, 1, 'prod_1_69a94d4515a81.jpg', 'available'),
(235, 5, 'prod_5_69a94dc205583.jpg', 'sold_out'),
(236, 5, 'prod_5_69a94dc207bfe.jpg', 'sold_out'),
(237, 5, 'prod_5_69a94dc20a089.jpg', 'available'),
(238, 5, 'prod_5_69a94dc20c8be.jpg', 'available'),
(239, 5, 'prod_5_69a94dc20eae7.jpg', 'available'),
(240, 5, 'prod_5_69a94dc210dbf.jpg', 'available'),
(241, 5, 'prod_5_69a94dc2130e3.jpg', 'available'),
(242, 5, 'prod_5_69a94dc215242.jpg', 'sold_out'),
(243, 16, 'prod_16_69a94e36d0642.jpg', 'available'),
(244, 16, 'prod_16_69a94e36d2619.jpg', 'available'),
(245, 16, 'prod_16_69a94e36d46a9.jpg', 'available'),
(246, 16, 'prod_16_69a94e36d65b5.jpg', 'available'),
(247, 16, 'prod_16_69a94e36d85a6.jpg', 'available'),
(248, 16, 'prod_16_69a94e36da81d.jpg', 'available'),
(249, 16, 'prod_16_69a94e36dc6b7.jpg', 'available'),
(250, 16, 'prod_16_69a94e36de5f2.jpg', 'available'),
(251, 16, 'prod_16_69a94e36e06b6.jpg', 'available'),
(252, 16, 'prod_16_69a94e36e25e8.jpg', 'available'),
(253, 16, 'prod_16_69a94e36e474f.jpg', 'sold_out'),
(254, 16, 'prod_16_69a94e36e6fff.jpg', 'available'),
(255, 15, 'prod_15_69a950e38024f.jpg', 'available'),
(256, 15, 'prod_15_69a950e3844ec.jpg', 'available'),
(257, 15, 'prod_15_69a950e38812a.jpg', 'available'),
(258, 15, 'prod_15_69a950e389ada.jpg', 'available'),
(259, 15, 'prod_15_69a950e38b3a2.jpg', 'available'),
(260, 15, 'prod_15_69a950e38ccfb.jpg', 'sold_out'),
(261, 15, 'prod_15_69a950e38e6da.jpg', 'sold_out'),
(262, 15, 'prod_15_69a950e3900e9.jpg', 'available'),
(263, 15, 'prod_15_69a950e391bd1.jpg', 'available'),
(264, 15, 'prod_15_69a950e3947d6.jpg', 'available'),
(265, 15, 'prod_15_69a950e3980b9.jpg', 'available'),
(266, 15, 'prod_15_69a950e3a23d2.jpg', 'available'),
(267, 15, 'prod_15_69a950e3a5985.jpg', 'available'),
(268, 15, 'prod_15_69a950e3a886b.jpg', 'available'),
(269, 17, 'prod_17_69b179d69f13e.jpg', 'sold_out'),
(270, 17, 'prod_17_69b179d6a856a.jpg', 'available'),
(271, 17, 'prod_17_69b179d6af417.jpg', 'available'),
(272, 17, 'prod_17_69b179d6b0d23.png', 'available'),
(273, 18, 'prod_18_69b336ea91d41.jpg', 'sold_out'),
(274, 18, 'prod_18_69b336eaa5e02.jpg', 'available'),
(275, 18, 'prod_18_69b336eaa8330.jpg', 'available'),
(276, 18, 'prod_18_69b336eaa9bef.jpg', 'available'),
(277, 18, 'prod_18_69b336eaabed3.jpg', 'available'),
(278, 18, 'prod_18_69b336eaad7dd.jpg', 'available'),
(279, 18, 'prod_18_69b336eaaf407.jpg', 'available'),
(280, 18, 'prod_18_69b336eab0fd9.jpg', 'available'),
(281, 18, 'prod_18_69b336eab2e3f.jpg', 'available'),
(282, 18, 'prod_18_69b336eab4798.jpg', 'available'),
(283, 18, 'prod_18_69b336eab6be3.jpg', 'available'),
(284, 18, 'prod_18_69b336eab8c18.jpg', 'available'),
(285, 18, 'prod_18_69b336eababf6.jpg', 'available'),
(286, 18, 'prod_18_69b336eabe877.jpg', 'available'),
(287, 18, 'prod_18_69b336eac2369.jpg', 'available'),
(288, 18, 'prod_18_69b336eac7fa1.jpg', 'available'),
(289, 18, 'prod_18_69b336eac9e85.jpg', 'available'),
(290, 18, 'prod_18_69b336eacbcb3.jpg', 'available'),
(291, 18, 'prod_18_69b336eacdbfd.jpg', 'available'),
(292, 18, 'prod_18_69b336eacf6d4.jpg', 'available'),
(293, 18, 'prod_18_69b336ead10cd.jpg', 'available'),
(294, 18, 'prod_18_69b336ead2804.jpg', 'available'),
(295, 18, 'prod_18_69b336ead4099.jpg', 'available'),
(296, 18, 'prod_18_69b336ead5a3b.jpg', 'available'),
(297, 18, 'prod_18_69b336ead75cf.jpg', 'available'),
(298, 18, 'prod_18_69b336eadbaf1.jpg', 'available'),
(299, 18, 'prod_18_69b336eadd4b8.jpg', 'available'),
(300, 18, 'prod_18_69b336eadefd2.jpg', 'available'),
(301, 18, 'prod_18_69b336eae0d20.jpg', 'available'),
(302, 18, 'prod_18_69b336eae25ac.jpg', 'available'),
(303, 18, 'prod_18_69b336eae3f07.jpg', 'available'),
(304, 18, 'prod_18_69b336eae5830.jpg', 'available'),
(305, 18, 'prod_18_69b336eae73e0.jpg', 'available'),
(306, 18, 'prod_18_69b336eae9c03.jpg', 'available'),
(307, 18, 'prod_18_69b336eaeb645.jpg', 'available'),
(308, 18, 'prod_18_69b336eaecdde.jpg', 'available'),
(309, 18, 'prod_18_69b336eaee819.jpg', 'available'),
(310, 18, 'prod_18_69b336eaf040a.jpg', 'available'),
(311, 18, 'prod_18_69b336eaf1c17.jpg', 'available'),
(312, 18, 'prod_18_69b336eaf355a.jpg', 'available'),
(313, 18, 'prod_18_69b336eb00c0f.jpg', 'available'),
(314, 18, 'prod_18_69b336eb027e4.jpg', 'available'),
(315, 18, 'prod_18_69b336eb045bc.jpg', 'available'),
(316, 18, 'prod_18_69b336eb05fb1.jpg', 'available'),
(317, 18, 'prod_18_69b336eb07e39.jpg', 'available'),
(318, 18, 'prod_18_69b336eb09dc1.jpg', 'available'),
(319, 18, 'prod_18_69b336eb0c555.jpg', 'available'),
(320, 18, 'prod_18_69b336eb0e587.jpg', 'available'),
(321, 18, 'prod_18_69b336eb10496.jpg', 'available'),
(322, 18, 'prod_18_69b336eb131bc.jpg', 'available'),
(323, 18, 'prod_18_69b336eb169d1.jpg', 'available'),
(324, 18, 'prod_18_69b336eb195fe.jpg', 'available'),
(325, 18, 'prod_18_69b336eb1b77d.jpg', 'available'),
(326, 18, 'prod_18_69b336eb1d888.jpg', 'available'),
(327, 18, 'prod_18_69b336eb1fdeb.jpg', 'available'),
(328, 18, 'prod_18_69b336eb21cda.jpg', 'available'),
(329, 18, 'prod_18_69b336eb239b2.jpg', 'available'),
(330, 18, 'prod_18_69b336eb254ac.jpg', 'available'),
(331, 18, 'prod_18_69b336eb27212.jpg', 'available'),
(332, 18, 'prod_18_69b336eb29922.jpg', 'available'),
(333, 18, 'prod_18_69b336eb2b721.jpg', 'available'),
(334, 18, 'prod_18_69b336eb2d650.jpg', 'available'),
(335, 18, 'prod_18_69b336eb2f4c6.jpg', 'available'),
(336, 18, 'prod_18_69b336eb312ee.jpg', 'available'),
(337, 18, 'prod_18_69b336eb33027.jpg', 'available'),
(338, 18, 'prod_18_69b336eb34c9f.jpg', 'available'),
(339, 3, 'prod_3_69b33772237ff.jpg', 'sold_out'),
(340, 3, 'prod_3_69b337722577b.jpg', 'sold_out'),
(341, 3, 'prod_3_69b3377227174.jpg', 'sold_out'),
(342, 3, 'prod_3_69b3377229365.jpg', 'sold_out'),
(343, 3, 'prod_3_69b337722acaf.jpg', 'sold_out'),
(344, 3, 'prod_3_69b337722c533.jpg', 'sold_out'),
(345, 3, 'prod_3_69b337722dfb4.jpg', 'sold_out'),
(346, 3, 'prod_3_69b337722fc12.jpg', 'sold_out'),
(347, 3, 'prod_3_69b33772315f4.jpg', 'sold_out'),
(348, 3, 'prod_3_69b3377234a0c.jpg', 'sold_out'),
(349, 3, 'prod_3_69b3377238351.jpg', 'sold_out'),
(350, 3, 'prod_3_69b337723a024.jpg', 'sold_out'),
(351, 3, 'prod_3_69b337723b71c.jpg', 'sold_out');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT 'Walk-in Customer',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','transfer','pos') DEFAULT 'cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `user_id`, `customer_name`, `total_amount`, `payment_method`, `created_at`) VALUES
(1, 4, 'Walk-in Customer', 15000.00, 'cash', '2026-03-06 00:17:25'),
(2, 4, 'Walk-in Customer', 16500.00, 'cash', '2026-03-06 00:29:22'),
(3, 4, 'Walk-in Customer', 10000.00, 'cash', '2026-03-07 14:24:22'),
(4, 2, 'Walk-in Customer', 357000.00, 'cash', '2026-03-07 14:30:51'),
(5, 2, 'Walk-in Customer', 8000.00, 'cash', '2026-03-07 14:31:00'),
(6, 2, 'Walk-in Customer', 7000.00, 'cash', '2026-03-07 15:04:20'),
(7, 2, 'Walk-in Customer', 14000.00, 'cash', '2026-03-07 15:04:58'),
(8, 2, 'Walk-in Customer', 18000.00, 'cash', '2026-03-10 22:33:15'),
(9, 2, 'Walk-in Customer', 228000.00, 'cash', '2026-03-11 14:24:39'),
(10, 2, 'Walk-in Customer', 171000.00, 'cash', '2026-03-11 14:25:14'),
(11, 2, 'Walk-in Customer', 18000.00, 'cash', '2026-03-12 20:18:07'),
(12, 2, 'Walk-in Customer', 25000.00, 'cash', '2026-03-12 20:18:26'),
(13, 2, 'Walk-in Customer', 67500.00, 'cash', '2026-03-12 22:07:14'),
(14, 4, 'Walk-in Customer', 195000.00, 'cash', '2026-03-12 22:12:23'),
(15, 2, 'Walk-in Customer', 25000.00, 'cash', '2026-03-15 18:59:13'),
(16, 3, 'Walk-in Customer', 20000.00, 'cash', '2026-03-20 02:02:04'),
(17, 3, 'Walk-in Customer', 18000.00, 'cash', '2026-03-20 02:15:35'),
(18, 3, 'Walk-in Customer', 57000.00, 'cash', '2026-03-20 02:21:34');

-- --------------------------------------------------------

--
-- Table structure for table `sales_backup_20260305_155318`
--

CREATE TABLE `sales_backup_20260305_155318` (
  `id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT 'Walk-in Customer',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','transfer','pos') DEFAULT 'cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_backup_20260305_155318`
--

INSERT INTO `sales_backup_20260305_155318` (`id`, `user_id`, `customer_name`, `total_amount`, `payment_method`, `created_at`) VALUES
(1, 2, 'Walk-in Customer', 33100.00, 'cash', '2026-01-16 22:56:08'),
(2, 2, 'Walk-in Customer', 27000.00, 'cash', '2026-01-16 23:46:08'),
(3, 3, 'Walk-in Customer', 9000.00, 'cash', '2026-01-17 00:49:12'),
(4, 1, 'Walk-in Customer', 77000.00, 'cash', '2026-01-17 01:13:37'),
(5, 3, 'Walk-in Customer', 137500.00, 'cash', '2026-01-17 01:15:33'),
(6, 2, 'Walk-in Customer', 27500.00, 'cash', '2026-01-17 01:18:13'),
(7, 3, 'Walk-in Customer', 15000.00, 'cash', '2026-01-17 01:20:52'),
(8, 3, 'Walk-in Customer', 365000.00, 'cash', '2026-01-17 01:40:15'),
(9, 3, 'Walk-in Customer', 120000.00, 'cash', '2026-01-17 01:41:54'),
(10, 2, 'Walk-in Customer', 247500.00, 'cash', '2026-01-17 02:21:44'),
(11, 2, 'Walk-in Customer', 27500.00, 'cash', '2026-01-17 02:22:19'),
(12, 2, 'Walk-in Customer', 15000.00, 'cash', '2026-01-17 02:41:41'),
(13, 2, 'Walk-in Customer', 15000.00, 'cash', '2026-01-17 02:42:04'),
(14, 2, 'Walk-in Customer', 38500.00, 'cash', '2026-01-17 02:54:41'),
(15, 2, 'Walk-in Customer', 96000.00, 'cash', '2026-01-17 02:55:18'),
(16, 2, 'Walk-in Customer', 30000.00, 'cash', '2026-01-17 02:57:58'),
(17, 2, 'Walk-in Customer', 467500.00, 'cash', '2026-01-17 10:02:51'),
(18, 2, 'Walk-in Customer', 15000.00, 'cash', '2026-01-17 13:29:34'),
(19, 2, 'Walk-in Customer', 9000.00, 'cash', '2026-01-17 17:28:23'),
(20, 2, 'Walk-in Customer', 22000.00, 'cash', '2026-01-17 17:50:09'),
(21, 2, 'Walk-in Customer', 15000.00, 'cash', '2026-01-17 19:28:22'),
(22, 3, 'Walk-in Customer', 9000.00, 'cash', '2026-01-17 20:45:58'),
(23, 3, 'Walk-in Customer', 30000.00, 'cash', '2026-01-18 11:09:41'),
(24, 4, 'Walk-in Customer', 30000.00, 'cash', '2026-01-18 14:25:36'),
(25, 4, 'Walk-in Customer', 30000.00, 'cash', '2026-01-19 08:26:16'),
(26, 2, 'Walk-in Customer', 210000.00, 'cash', '2026-01-19 12:35:59'),
(27, 2, 'Walk-in Customer', 60000.00, 'cash', '2026-01-19 12:36:12'),
(28, 4, 'Walk-in Customer', 90000.00, 'cash', '2026-01-19 14:36:40'),
(29, 4, 'Walk-in Customer', 42000.00, 'cash', '2026-01-22 02:24:54'),
(30, 2, 'Walk-in Customer', 30000.00, 'cash', '2026-01-23 15:51:30'),
(31, 2, 'Walk-in Customer', 110000.00, 'cash', '2026-01-23 15:52:33'),
(32, 4, 'Walk-in Customer', 25000.00, 'cash', '2026-01-23 20:21:49'),
(33, 4, 'Walk-in Customer', 20000.00, 'cash', '2026-01-23 21:21:31'),
(34, 4, 'Walk-in Customer', 10000.00, 'cash', '2026-01-23 21:21:53'),
(35, 4, 'Walk-in Customer', 14000.00, 'cash', '2026-01-30 11:54:51'),
(36, 4, 'Walk-in Customer', 27500.00, 'cash', '2026-01-30 11:55:05'),
(37, 4, 'Walk-in Customer', 429000.00, 'cash', '2026-01-30 11:57:50'),
(38, 4, 'Walk-in Customer', 396000.00, 'cash', '2026-01-30 11:58:01'),
(39, 4, 'Walk-in Customer', 21000.00, 'cash', '2026-02-24 10:49:12'),
(40, 4, 'Walk-in Customer', 60500.00, 'cash', '2026-02-24 10:49:42'),
(41, 2, 'Walk-in Customer', 15000.00, 'cash', '2026-02-25 22:49:08'),
(42, 2, 'Walk-in Customer', 21000.00, 'cash', '2026-02-26 00:47:08'),
(43, 2, 'Walk-in Customer', 15000.00, 'cash', '2026-02-26 01:02:36'),
(44, 2, 'Walk-in Customer', 20000.00, 'cash', '2026-03-03 00:37:41'),
(45, 4, 'Walk-in Customer', 7000.00, 'cash', '2026-03-03 11:36:26'),
(46, 4, 'Walk-in Customer', 197000.00, 'cash', '2026-03-05 10:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `sales_backup_20260306_011643`
--

CREATE TABLE `sales_backup_20260306_011643` (
  `id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(100) DEFAULT 'Walk-in Customer',
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','transfer','pos') DEFAULT 'cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sales_backup_20260306_011643`
--

INSERT INTO `sales_backup_20260306_011643` (`id`, `user_id`, `customer_name`, `total_amount`, `payment_method`, `created_at`) VALUES
(47, 2, 'Walk-in Customer', 16000.00, 'cash', '2026-03-05 14:58:51'),
(48, 2, 'Walk-in Customer', 50000.00, 'cash', '2026-03-05 14:59:12'),
(49, 4, 'Walk-in Customer', 32000.00, 'cash', '2026-03-06 00:15:47');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `buy_price_at_sale` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price`, `buy_price_at_sale`, `subtotal`) VALUES
(1, 1, 3, 1.00, 15000.00, 13000.00, 15000.00),
(2, 2, 12, 3.00, 5500.00, 4000.00, 16500.00),
(3, 3, 9, 1.00, 10000.00, 8500.00, 10000.00),
(4, 4, 1, 5.00, 57000.00, 50000.00, 285000.00),
(5, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(6, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(7, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(8, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(9, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(10, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(11, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(12, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(13, 4, 7, 1.00, 8000.00, 6000.00, 8000.00),
(14, 5, 7, 1.00, 8000.00, 6000.00, 8000.00),
(15, 6, 11, 1.00, 7000.00, 5500.00, 7000.00),
(16, 7, 11, 1.00, 7000.00, 5500.00, 7000.00),
(17, 7, 11, 1.00, 7000.00, 5500.00, 7000.00),
(18, 8, 5, 1.00, 18000.00, 15000.00, 18000.00),
(19, 9, 1, 4.00, 57000.00, 50000.00, 228000.00),
(20, 10, 1, 3.00, 57000.00, 50000.00, 171000.00),
(21, 11, 5, 1.00, 18000.00, 15000.00, 18000.00),
(22, 12, 2, 1.00, 25000.00, 20000.00, 25000.00),
(23, 13, 10, 1.00, 26000.00, 25000.00, 26000.00),
(24, 13, 12, 5.00, 5500.00, 4000.00, 27500.00),
(25, 13, 15, 1.00, 14000.00, 10000.00, 14000.00),
(26, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(27, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(28, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(29, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(30, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(31, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(32, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(33, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(34, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(35, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(36, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(37, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(38, 14, 3, 1.00, 15000.00, 13000.00, 15000.00),
(39, 15, 2, 1.00, 25000.00, 20000.00, 25000.00),
(40, 16, 9, 1.00, 10000.00, 8500.00, 10000.00),
(41, 16, 9, 1.00, 10000.00, 8500.00, 10000.00),
(42, 17, 5, 1.00, 18000.00, 15000.00, 18000.00),
(43, 18, 1, 1.00, 57000.00, 50000.00, 57000.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_items_backup_20260305_155318`
--

CREATE TABLE `sale_items_backup_20260305_155318` (
  `id` int(11) NOT NULL DEFAULT 0,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `buy_price_at_sale` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items_backup_20260305_155318`
--

INSERT INTO `sale_items_backup_20260305_155318` (`id`, `sale_id`, `product_id`, `quantity`, `price`, `buy_price_at_sale`, `subtotal`) VALUES
(1, 1, 5, 1.00, 8000.00, NULL, 8000.00),
(2, 1, 11, 1.00, 9000.00, NULL, 9000.00),
(3, 1, 1, 23.00, 700.00, NULL, 16100.00),
(4, 2, 8, 1.00, 9000.00, NULL, 9000.00),
(5, 2, 7, 1.00, 9000.00, NULL, 9000.00),
(6, 2, 7, 1.00, 9000.00, NULL, 9000.00),
(7, 3, 11, 1.00, 9000.00, NULL, 9000.00),
(8, 4, 12, 14.00, 5500.00, NULL, 77000.00),
(9, 5, 12, 25.00, 5500.00, NULL, 137500.00),
(10, 6, 12, 5.00, 5500.00, NULL, 27500.00),
(11, 7, 3, 1.00, 15000.00, NULL, 15000.00),
(12, 8, 10, 1.00, 30000.00, NULL, 30000.00),
(13, 8, 10, 1.00, 30000.00, NULL, 30000.00),
(14, 8, 10, 1.00, 30000.00, NULL, 30000.00),
(15, 8, 4, 50.00, 5500.00, NULL, 275000.00),
(16, 9, 10, 1.00, 30000.00, NULL, 30000.00),
(17, 9, 10, 1.00, 30000.00, NULL, 30000.00),
(18, 9, 10, 1.00, 30000.00, NULL, 30000.00),
(19, 9, 10, 1.00, 30000.00, NULL, 30000.00),
(20, 10, 12, 45.00, 5500.00, NULL, 247500.00),
(21, 11, 12, 5.00, 5500.00, NULL, 27500.00),
(22, 12, 3, 1.00, 15000.00, NULL, 15000.00),
(23, 13, 3, 1.00, 15000.00, NULL, 15000.00),
(24, 14, 12, 7.00, 5500.00, NULL, 38500.00),
(25, 15, 12, 12.00, 5500.00, NULL, 66000.00),
(26, 15, 10, 1.00, 30000.00, NULL, 30000.00),
(27, 16, 10, 1.00, 30000.00, NULL, 30000.00),
(28, 17, 12, 85.00, 5500.00, NULL, 467500.00),
(29, 18, 3, 1.00, 15000.00, NULL, 15000.00),
(30, 19, 7, 1.00, 9000.00, NULL, 9000.00),
(31, 20, 4, 4.00, 5500.00, NULL, 22000.00),
(32, 21, 3, 1.00, 15000.00, NULL, 15000.00),
(33, 22, 11, 1.00, 9000.00, NULL, 9000.00),
(34, 23, 10, 1.00, 30000.00, NULL, 30000.00),
(35, 24, 10, 1.00, 30000.00, NULL, 30000.00),
(36, 25, 10, 1.00, 30000.00, NULL, 30000.00),
(37, 26, 10, 3.00, 30000.00, NULL, 90000.00),
(38, 26, 10, 1.00, 30000.00, NULL, 30000.00),
(39, 26, 10, 1.00, 30000.00, NULL, 30000.00),
(40, 26, 10, 1.00, 30000.00, NULL, 30000.00),
(41, 26, 10, 1.00, 30000.00, NULL, 30000.00),
(42, 27, 10, 1.00, 30000.00, NULL, 30000.00),
(43, 27, 10, 1.00, 30000.00, NULL, 30000.00),
(44, 28, 10, 1.00, 30000.00, NULL, 30000.00),
(45, 28, 10, 1.00, 30000.00, NULL, 30000.00),
(46, 28, 10, 1.00, 30000.00, NULL, 30000.00),
(47, 29, 8, 1.00, 14000.00, NULL, 14000.00),
(48, 29, 8, 2.00, 14000.00, NULL, 28000.00),
(49, 30, 10, 1.00, 30000.00, NULL, 30000.00),
(50, 31, 4, 20.00, 5500.00, NULL, 110000.00),
(51, 32, 6, 1.00, 25000.00, NULL, 25000.00),
(52, 33, 11, 1.00, 10000.00, NULL, 10000.00),
(53, 33, 11, 1.00, 10000.00, NULL, 10000.00),
(54, 34, 11, 1.00, 10000.00, NULL, 10000.00),
(55, 35, 11, 1.00, 7000.00, NULL, 7000.00),
(56, 35, 11, 1.00, 7000.00, NULL, 7000.00),
(57, 36, 4, 5.00, 5500.00, NULL, 27500.00),
(58, 37, 12, 78.00, 5500.00, NULL, 429000.00),
(59, 38, 12, 72.00, 5500.00, NULL, 396000.00),
(60, 39, 11, 1.00, 7000.00, NULL, 7000.00),
(61, 39, 11, 1.00, 7000.00, NULL, 7000.00),
(62, 39, 11, 1.00, 7000.00, NULL, 7000.00),
(63, 40, 4, 11.00, 5500.00, NULL, 60500.00),
(64, 41, 3, 1.00, 15000.00, NULL, 15000.00),
(65, 42, 11, 1.00, 7000.00, NULL, 7000.00),
(66, 42, 11, 1.00, 7000.00, NULL, 7000.00),
(67, 42, 11, 1.00, 7000.00, NULL, 7000.00),
(68, 43, 3, 1.00, 15000.00, NULL, 15000.00),
(69, 44, 6, 1.00, 20000.00, NULL, 20000.00),
(70, 45, 11, 1.00, 7000.00, NULL, 7000.00),
(71, 46, 15, 1.00, 14000.00, NULL, 14000.00),
(72, 46, 16, 1.00, 15000.00, NULL, 15000.00),
(73, 46, 9, 1.00, 10000.00, NULL, 10000.00),
(74, 46, 7, 1.00, 8000.00, NULL, 8000.00),
(75, 46, 2, 6.00, 25000.00, NULL, 150000.00);

-- --------------------------------------------------------

--
-- Table structure for table `sale_items_backup_20260306_011643`
--

CREATE TABLE `sale_items_backup_20260306_011643` (
  `id` int(11) NOT NULL DEFAULT 0,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `buy_price_at_sale` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sale_items_backup_20260306_011643`
--

INSERT INTO `sale_items_backup_20260306_011643` (`id`, `sale_id`, `product_id`, `quantity`, `price`, `buy_price_at_sale`, `subtotal`) VALUES
(76, 47, 7, 1.00, 8000.00, 6000.00, 8000.00),
(77, 47, 7, 1.00, 8000.00, 6000.00, 8000.00),
(78, 48, 2, 2.00, 25000.00, 20000.00, 50000.00),
(79, 49, 14, 1.00, 32000.00, 30000.00, 32000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','sale_attendant') NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','suspended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `fullname`, `created_at`, `status`) VALUES
(1, 'admin', '1cecc7a77928ca8133fa24680a88d2f9', 'admin', 'Abubakar Aminu', '2026-01-15 20:07:45', 'active'),
(2, 'jaz', 'a01610228fe998f515a72dd730294d87', 'sale_attendant', 'Ahmad Shuaibu', '2026-01-15 20:07:45', 'active'),
(3, 'umaa', '81dc9bdb52d04dc20036dbd8313ed055', 'sale_attendant', 'Umar Shuaibu', '2026-01-16 01:43:57', 'active'),
(4, 'sadiq', '81dc9bdb52d04dc20036dbd8313ed055', 'sale_attendant', 'Sales Attendant', '2026-01-18 14:24:08', 'active'),
(5, 'CEO', 'a01610228fe998f515a72dd730294d87', 'admin', 'Chief Executive Officer', '2026-02-26 00:07:02', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `payout_requests`
--
ALTER TABLE `payout_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `product_id` (`product_id`);

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
-- AUTO_INCREMENT for table `payout_requests`
--
ALTER TABLE `payout_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=352;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales`
--
ALTER TABLE `sales`
  ADD CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
