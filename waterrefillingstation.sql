-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2025 at 06:43 AM
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
-- Database: `waterrefillingstation`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`log_id`, `user_id`, `activity_type`, `description`, `ip_address`, `created_at`) VALUES
(1, 1, 'profile_update', 'Updated profile information', NULL, '2025-06-16 14:33:41'),
(2, 1, 'profile_update', 'Updated profile information', NULL, '2025-06-16 14:33:48'),
(3, 1, 'profile_update', 'Updated profile information', NULL, '2025-06-16 14:33:56'),
(4, 1, 'profile_update', 'Updated profile information', NULL, '2025-06-16 14:34:09'),
(5, 1, 'profile_update', 'Updated profile information', NULL, '2025-06-16 14:34:15'),
(6, 1, 'profile_update', 'Updated profile information', NULL, '2025-06-16 14:34:28'),
(7, 1, 'profile_update', 'Updated profile information', NULL, '2025-06-16 14:37:04'),
(8, 1, 'profile_update', 'Updated profile information', NULL, '2025-06-16 14:37:10'),
(9, 1, 'password_change', 'Changed account password', NULL, '2025-06-16 14:37:26'),
(10, 1, 'branch_create', 'Created new branch: 1', NULL, '2025-06-16 14:50:49'),
(11, 1, 'branch_delete', 'Deleted branch: ', NULL, '2025-06-16 14:50:56'),
(12, 1, 'branch_delete', 'Deleted branch: ', NULL, '2025-06-16 14:51:01'),
(13, 1, 'branch_delete', 'Deleted branch: ', NULL, '2025-06-16 14:51:09'),
(14, 1, 'branch_delete', 'Deleted branch: ', NULL, '2025-06-16 14:54:38'),
(15, 1, 'branch_update', 'Updated branch: a', NULL, '2025-06-16 14:55:03'),
(16, 1, 'branch_delete', 'Deleted branch: ', NULL, '2025-06-16 14:55:07'),
(17, 1, 'branch_create', 'Created new branch: d', NULL, '2025-06-16 14:59:32'),
(18, 1, 'admin_create', 'Created new branch admin: Lazareto', NULL, '2025-06-16 15:03:07'),
(19, 1, 'admin_create', 'Created new branch admin: Calero', NULL, '2025-06-16 15:04:03'),
(20, 1, 'admin_update', 'Updated branch admin info: Branch Admin 11', NULL, '2025-06-16 15:12:12'),
(21, 1, 'admin_update', 'Updated branch admin info: Branch Admin 1', NULL, '2025-06-16 15:12:17'),
(22, 1, 'admin_create', 'Created new branch admin: asd', NULL, '2025-06-16 15:12:30'),
(23, 1, 'admin_delete', 'Deactivated branch admin: asd', NULL, '2025-06-16 15:12:34'),
(24, 1, 'branch_create', 'Created new branch: 1', NULL, '2025-06-16 15:14:56'),
(25, 1, 'branch_delete', 'Deleted branch: 1', NULL, '2025-06-16 15:18:00'),
(26, 1, 'logout', 'User logged out', NULL, '2025-06-16 15:24:20'),
(27, 2, 'login', 'User logged in', NULL, '2025-06-16 15:25:21'),
(28, 1, 'login', 'User logged in', NULL, '2025-06-16 15:26:20'),
(29, 1, 'logout', 'User logged out', NULL, '2025-06-16 15:30:31'),
(30, 2, 'login', 'User logged in', NULL, '2025-06-16 15:31:23'),
(31, 2, 'logout', 'User logged out', NULL, '2025-06-16 15:49:21'),
(32, 1, 'login', 'User logged in', NULL, '2025-06-16 15:49:31'),
(33, 1, 'logout', 'User logged out', NULL, '2025-06-16 15:50:10'),
(34, 1, 'login', 'User logged in', NULL, '2025-06-16 15:50:15'),
(35, 1, 'logout', 'User logged out', NULL, '2025-06-16 15:51:23'),
(36, 2, 'login', 'User logged in', NULL, '2025-06-16 15:51:29'),
(37, 2, 'logout', 'User logged out', NULL, '2025-06-16 16:01:25'),
(38, 5, 'registration', 'New customer registered', NULL, '2025-06-16 16:23:40'),
(39, 5, 'login', 'User logged in', NULL, '2025-06-16 16:24:03'),
(40, 5, 'profile_update', 'Updated profile information', NULL, '2025-06-16 16:25:15'),
(41, 5, 'profile_update', 'Updated profile information', NULL, '2025-06-16 16:25:18'),
(42, 5, 'logout', 'User logged out', NULL, '2025-06-16 16:30:04'),
(43, 3, 'login', 'User logged in', NULL, '2025-06-16 16:30:16'),
(44, 3, 'task_create', 'Created new task: test', NULL, '2025-06-16 16:31:04'),
(45, 3, 'task_update', 'Updated task #1 status to: in_progress', NULL, '2025-06-16 16:31:09'),
(46, 3, 'logout', 'User logged out', NULL, '2025-06-16 16:31:21'),
(47, 5, 'login', 'User logged in', NULL, '2025-06-16 16:31:29'),
(48, 5, 'appointment_scheduled', 'New maintenance appointment scheduled for 2025-06-18 08:00:00', NULL, '2025-06-16 16:41:48'),
(49, 5, 'appointment_cancelled', 'Appointment #1 cancelled', NULL, '2025-06-16 16:49:17'),
(50, 5, 'refill_requested', 'Refill order #1 created for Round (5 gallons)', NULL, '2025-06-17 04:01:22'),
(51, 5, 'refill_requested', 'Refill order #2 created for Round (5 gallons)', NULL, '2025-06-17 05:56:47'),
(52, 5, 'logout', 'User logged out', NULL, '2025-06-17 05:56:55'),
(53, 3, 'login', 'User logged in', NULL, '2025-06-17 05:57:01'),
(54, 1, 'login', 'User logged in', NULL, '2025-06-26 11:20:18'),
(55, 1, 'logout', 'User logged out', NULL, '2025-06-26 11:20:55'),
(56, 2, 'login', 'User logged in', NULL, '2025-06-26 11:21:04'),
(57, 2, 'logout', 'User logged out', NULL, '2025-06-26 11:21:43'),
(58, 5, 'login', 'User logged in', NULL, '2025-06-26 11:22:25'),
(59, 5, 'logout', 'User logged out', NULL, '2025-06-26 11:23:00'),
(60, 3, 'login', 'User logged in', NULL, '2025-06-26 11:23:13'),
(61, 3, 'product_created', 'Added new product: 1', NULL, '2025-06-26 11:28:34'),
(62, 3, 'logout', 'User logged out', NULL, '2025-06-26 11:28:51'),
(63, 5, 'login', 'User logged in', NULL, '2025-06-26 11:28:57'),
(64, 5, 'order_placed', 'Order #3 placed', NULL, '2025-06-26 11:34:54'),
(65, 5, 'logout', 'User logged out', NULL, '2025-06-26 11:35:07'),
(66, 3, 'login', 'User logged in', NULL, '2025-06-26 11:35:16'),
(67, 3, 'logout', 'User logged out', NULL, '2025-06-26 11:46:45'),
(68, 5, 'login', 'User logged in', NULL, '2025-06-26 11:46:52'),
(69, 5, 'logout', 'User logged out', NULL, '2025-06-26 12:21:38'),
(70, 3, 'login', 'User logged in', NULL, '2025-06-26 12:21:45'),
(71, 3, 'logout', 'User logged out', NULL, '2025-06-26 12:25:40'),
(72, 5, 'login', 'User logged in', NULL, '2025-06-26 12:25:50'),
(73, 5, 'logout', 'User logged out', NULL, '2025-06-26 12:26:04'),
(74, 3, 'login', 'User logged in', NULL, '2025-06-26 12:26:09'),
(75, 3, 'logout', 'User logged out', NULL, '2025-06-26 12:58:43'),
(76, 5, 'login', 'User logged in', NULL, '2025-06-26 12:58:50'),
(77, 5, 'logout', 'User logged out', NULL, '2025-06-26 12:58:59'),
(78, 3, 'login', 'User logged in', NULL, '2025-06-26 12:59:08'),
(79, 3, 'order_status_update', 'Updated order #3 status to processing', NULL, '2025-06-26 12:59:19'),
(80, 3, 'logout', 'User logged out', NULL, '2025-06-26 12:59:25'),
(81, 1, 'login', 'User logged in', NULL, '2025-06-26 12:59:30'),
(82, 1, 'logout', 'User logged out', NULL, '2025-06-26 13:21:46'),
(83, 3, 'login', 'User logged in', NULL, '2025-06-26 13:21:52'),
(84, 3, 'logout', 'User logged out', NULL, '2025-06-26 13:21:56'),
(85, 5, 'login', 'User logged in', NULL, '2025-06-26 13:22:03'),
(86, 5, 'logout', 'User logged out', NULL, '2025-06-26 13:22:08'),
(87, 3, 'login', 'User logged in', NULL, '2025-06-26 19:16:09'),
(88, 3, 'order_completed', 'Completed order #3 for customer Customer 1', NULL, '2025-06-26 19:33:51'),
(89, 3, 'logout', 'User logged out', NULL, '2025-06-26 19:53:14'),
(90, 1, 'login', 'User logged in', NULL, '2025-06-26 19:53:19'),
(91, 1, 'logout', 'User logged out', NULL, '2025-06-26 22:13:11'),
(92, 3, 'login', 'User logged in', NULL, '2025-06-26 22:13:24'),
(93, 3, 'product_deleted', 'Deleted product ID: 1', NULL, '2025-06-26 22:33:40'),
(94, 3, 'logout', 'User logged out', NULL, '2025-06-28 14:08:14'),
(95, 1, 'login', 'User logged in', NULL, '2025-06-28 14:08:24'),
(96, 1, 'logout', 'User logged out', NULL, '2025-06-28 14:40:57'),
(97, 3, 'login', 'User logged in', NULL, '2025-06-28 14:41:07'),
(98, 3, 'product_created', 'Added new product: Water', NULL, '2025-06-28 14:41:45'),
(99, 3, 'logout', 'User logged out', NULL, '2025-06-28 14:41:56'),
(100, 1, 'login', 'User logged in', NULL, '2025-06-28 14:42:07'),
(101, 1, 'logout', 'User logged out', NULL, '2025-06-28 14:44:04'),
(102, 5, 'login', 'User logged in', NULL, '2025-06-28 14:44:13'),
(103, 5, 'order_placed', 'Order #4 placed', NULL, '2025-06-28 14:44:39'),
(104, 5, 'logout', 'User logged out', NULL, '2025-06-28 14:44:44'),
(105, 3, 'login', 'User logged in', NULL, '2025-06-28 14:44:52'),
(106, 3, 'order_status_update', 'Updated order #4 status to delivered', NULL, '2025-06-28 14:45:06'),
(107, 3, 'logout', 'User logged out', NULL, '2025-06-28 14:45:18'),
(108, 1, 'login', 'User logged in', NULL, '2025-06-28 14:45:24'),
(109, 1, 'logout', 'User logged out', NULL, '2025-06-28 14:56:23'),
(110, 5, 'login', 'User logged in', NULL, '2025-06-28 14:56:29'),
(111, 5, 'logout', 'User logged out', NULL, '2025-06-28 14:56:36'),
(112, 3, 'login', 'User logged in', NULL, '2025-06-28 14:56:40'),
(113, 3, 'logout', 'User logged out', NULL, '2025-06-28 14:57:10'),
(114, 5, 'login', 'User logged in', NULL, '2025-06-28 14:57:16'),
(115, 5, 'order_placed', 'Order #5 placed', NULL, '2025-06-28 18:00:48'),
(116, 5, 'order_cancelled', 'Order #5 cancelled by customer', NULL, '2025-06-28 15:15:52'),
(117, 5, 'order_cancelled', 'Order #1 cancelled by customer', NULL, '2025-06-28 15:16:01'),
(118, 5, 'logout', 'User logged out', NULL, '2025-06-28 15:33:20'),
(119, 1, 'login', 'User logged in', NULL, '2025-06-28 15:33:26'),
(120, 1, 'logout', 'User logged out', NULL, '2025-06-28 15:38:20'),
(121, 3, 'login', 'User logged in', NULL, '2025-06-28 15:38:25'),
(122, 3, 'logout', 'User logged out', NULL, '2025-06-28 15:38:35'),
(123, 5, 'login', 'User logged in', NULL, '2025-06-28 15:38:40'),
(124, 5, 'logout', 'User logged out', NULL, '2025-06-28 15:38:46'),
(125, 1, 'login', 'User logged in', NULL, '2025-06-28 15:39:28'),
(126, 1, 'logout', 'User logged out', NULL, '2025-06-28 15:48:24'),
(127, 3, 'login', 'User logged in', NULL, '2025-06-28 15:48:28'),
(128, 3, 'logout', 'User logged out', NULL, '2025-06-28 15:48:44'),
(129, 5, 'login', 'User logged in', NULL, '2025-06-28 15:48:50'),
(130, 5, 'logout', 'User logged out', NULL, '2025-06-28 15:49:00'),
(131, 5, 'login', 'User logged in', NULL, '2025-06-28 15:51:58'),
(132, 5, 'logout', 'User logged out', NULL, '2025-06-28 15:56:03'),
(133, 5, 'login', 'User logged in', NULL, '2025-06-28 15:56:50'),
(134, 5, 'logout', 'User logged out', NULL, '2025-06-28 16:00:26'),
(135, 5, 'login', 'User logged in', NULL, '2025-06-28 16:00:31'),
(136, 5, 'logout', 'User logged out', NULL, '2025-06-28 16:04:53'),
(137, 5, 'login', 'User logged in', NULL, '2025-06-28 16:05:12'),
(138, 5, 'logout', 'User logged out', NULL, '2025-06-28 16:19:58'),
(139, 7, 'logout', 'User logged out', NULL, '2025-06-29 04:48:41'),
(140, 2, 'login', 'User logged in', NULL, '2025-06-29 04:49:15'),
(141, 2, 'logout', 'User logged out', NULL, '2025-06-29 04:49:43'),
(142, 6, 'registration', 'New customer registered', NULL, '2025-06-29 04:50:56'),
(143, 6, 'login', 'User logged in', NULL, '2025-06-29 04:51:00'),
(144, 6, 'logout', 'User logged out', NULL, '2025-06-29 04:52:59'),
(145, 7, 'login', 'User logged in', NULL, '2025-06-29 04:59:29'),
(146, 7, 'logout', 'User logged out', NULL, '2025-06-29 08:00:24'),
(147, 2, 'login', 'User logged in', NULL, '2025-06-29 08:00:53'),
(148, 2, 'product_created', 'Added new product: Bottle 300ml', NULL, '2025-06-29 05:02:28'),
(149, 2, 'product_created', 'Added new product: Bottle 500ml', NULL, '2025-06-29 05:03:22'),
(150, 2, 'product_created', 'Added new product: Bottle 1ltr', NULL, '2025-06-29 05:04:12'),
(151, 2, 'product_created', 'Added new product: Bottle 1.5 gal', NULL, '2025-06-29 05:04:52'),
(152, 2, 'product_created', 'Added new product: Bottle 1 gal', NULL, '2025-06-29 05:05:10'),
(153, 2, 'logout', 'User logged out', NULL, '2025-06-29 05:11:12'),
(154, 1, 'login', 'User logged in', NULL, '2025-06-29 05:11:33'),
(155, 1, 'logout', 'User logged out', NULL, '2025-06-29 05:14:08'),
(156, 3, 'login', 'User logged in', NULL, '2025-06-29 05:14:20'),
(157, 3, 'product_deleted', 'Deleted product ID: 2', NULL, '2025-06-29 05:16:15'),
(158, 3, 'product_created', 'Added new product: Bottle 300ml', NULL, '2025-06-29 05:16:45'),
(159, 3, 'logout', 'User logged out', NULL, '2025-06-29 05:17:03'),
(160, 1, 'login', 'User logged in', NULL, '2025-06-29 05:17:20'),
(161, 1, 'logout', 'User logged out', NULL, '2025-06-29 05:18:23'),
(162, 2, 'login', 'User logged in', NULL, '2025-06-29 05:18:38'),
(163, 2, 'product_created', 'Added new product: Round 5 gal', NULL, '2025-06-29 05:19:32'),
(164, 2, 'product_created', 'Added new product: Slim 5 gal', NULL, '2025-06-29 05:20:52'),
(165, 2, 'product_created', 'Added new product: Dispenser', NULL, '2025-06-29 05:22:49'),
(166, 2, 'product_deleted', 'Deleted product ID: 11', NULL, '2025-06-29 05:23:11'),
(167, 2, 'product_created', 'Added new product: Dispenser', NULL, '2025-06-29 05:23:27'),
(168, 2, 'logout', 'User logged out', NULL, '2025-06-29 05:25:35'),
(169, 7, 'login', 'User logged in', NULL, '2025-06-29 05:26:13'),
(170, 7, 'logout', 'User logged out', NULL, '2025-06-29 05:27:53'),
(171, 2, 'login', 'User logged in', NULL, '2025-06-29 05:28:12'),
(172, 2, 'inventory_update', 'Updated inventory #7: Quantity=25, Threshold=5', NULL, '2025-06-29 05:28:31'),
(173, 2, 'logout', 'User logged out', NULL, '2025-06-29 05:28:44'),
(174, 7, 'login', 'User logged in', NULL, '2025-06-29 05:29:10'),
(175, 7, 'logout', 'User logged out', NULL, '2025-06-29 05:29:29'),
(176, 3, 'login', 'User logged in', NULL, '2025-06-29 05:29:42'),
(177, 3, 'product_created', 'Added new product: Bottle 500ml', NULL, '2025-06-29 05:30:50'),
(178, 3, 'product_created', 'Added new product: Bottle 1ltr', NULL, '2025-06-29 05:31:32'),
(179, 3, 'product_created', 'Added new product: Bottle 1.5 gal', NULL, '2025-06-29 05:32:24'),
(180, 3, 'product_created', 'Added new product: Bottle 1 gal', NULL, '2025-06-29 05:32:45'),
(181, 3, 'product_created', 'Added new product: Bottle 1.5 ltr', NULL, '2025-06-29 05:33:23'),
(182, 3, 'product_created', 'Added new product: Round 5 gal', NULL, '2025-06-29 05:33:59'),
(183, 3, 'product_created', 'Added new product: Slim 5 gal', NULL, '2025-06-29 05:34:43'),
(184, 3, 'product_created', 'Added new product: Dispenser', NULL, '2025-06-29 05:35:37'),
(185, 3, 'logout', 'User logged out', NULL, '2025-06-29 05:36:23'),
(186, 7, 'login', 'User logged in', NULL, '2025-06-29 05:36:39'),
(187, 7, 'order_placed', 'Order #6 placed', NULL, '2025-06-29 05:39:45'),
(188, 7, 'logout', 'User logged out', NULL, '2025-06-29 05:41:32'),
(189, 8, 'registration', 'New customer registered', NULL, '2025-06-29 05:42:30'),
(190, 8, 'login', 'User logged in', NULL, '2025-06-29 05:42:52'),
(191, 8, 'order_placed', 'Order #7 placed', NULL, '2025-06-29 05:43:42'),
(192, 8, 'logout', 'User logged out', NULL, '2025-06-29 05:44:33'),
(193, 2, 'login', 'User logged in', NULL, '2025-06-29 05:44:48'),
(194, 2, 'order_status_update', 'Updated order #7 status to processing', NULL, '2025-06-29 05:46:12'),
(195, 2, 'order_completed', 'Completed order #7 for customer Marie Manalo', NULL, '2025-06-29 05:46:20'),
(196, 2, 'logout', 'User logged out', NULL, '2025-06-29 05:46:56'),
(197, 1, 'login', 'User logged in', NULL, '2025-06-29 05:47:06'),
(198, 1, 'logout', 'User logged out', NULL, '2025-06-29 06:27:15'),
(199, 9, 'registration', 'New customer registered', NULL, '2025-06-29 07:13:17'),
(200, 9, 'login', 'User logged in', NULL, '2025-06-29 07:13:36'),
(201, 9, 'logout', 'User logged out', NULL, '2025-06-29 09:04:51'),
(202, 2, 'login', 'User logged in', NULL, '2025-06-29 09:05:16'),
(203, 2, 'logout', 'User logged out', NULL, '2025-06-29 09:05:52'),
(204, 1, 'login', 'User logged in', NULL, '2025-06-29 09:06:07'),
(205, 1, 'settings_update', 'Updated system settings', NULL, '2025-06-29 09:06:56'),
(206, 1, 'logout', 'User logged out', NULL, '2025-06-29 09:10:34'),
(207, 7, 'login', 'User logged in', NULL, '2025-06-29 09:10:52'),
(208, 7, 'logout', 'User logged out', NULL, '2025-06-29 09:14:09'),
(209, 3, 'login', 'User logged in', NULL, '2025-06-29 09:14:19'),
(210, 3, 'logout', 'User logged out', NULL, '2025-06-29 09:15:25'),
(211, 7, 'login', 'User logged in', NULL, '2025-06-29 11:30:48'),
(212, 7, 'order_placed', 'Order #8 placed', NULL, '2025-06-29 11:38:36'),
(213, 7, 'refill_requested', 'Refill order #9 created for Round (5 gallons)', NULL, '2025-06-29 11:40:19'),
(214, 7, 'logout', 'User logged out', NULL, '2025-06-29 11:40:41'),
(215, 3, 'login', 'User logged in', NULL, '2025-06-29 11:40:59'),
(216, 3, 'order_status_update', 'Updated order #9 status to processing', NULL, '2025-06-29 11:41:22'),
(217, 3, 'order_completed', 'Completed order #9 for customer Mary Joy Macalindol', NULL, '2025-06-29 11:41:40'),
(218, 3, 'order_status_update', 'Updated order #8 status to processing', NULL, '2025-06-29 11:42:28'),
(219, 3, 'order_status_update', 'Updated order #8 status to processing', NULL, '2025-06-29 11:42:33'),
(220, 3, 'order_completed', 'Completed order #8 for customer Mary Joy Macalindol', NULL, '2025-06-29 11:42:44'),
(221, 3, 'logout', 'User logged out', NULL, '2025-06-29 11:43:26'),
(222, 9, 'login', 'User logged in', NULL, '2025-06-29 11:43:44'),
(223, 9, 'order_placed', 'Order #10 placed', NULL, '2025-06-29 11:44:43'),
(224, 9, 'order_cancelled', 'Order #10 cancelled by customer', NULL, '2025-06-29 11:45:12'),
(225, 9, 'refill_requested', 'Refill order #11 created for 10 liters', NULL, '2025-06-29 11:45:51'),
(226, 9, 'refill_requested', 'Refill order #12 created for 10 liters', NULL, '2025-06-29 11:46:01'),
(227, 9, 'logout', 'User logged out', NULL, '2025-06-29 11:46:05'),
(228, 2, 'login', 'User logged in', NULL, '2025-06-29 11:46:15'),
(229, 2, 'order_status_update', 'Updated order #11 status to cancelled', NULL, '2025-06-29 11:46:48'),
(230, 2, 'order_status_update', 'Updated order #12 status to processing', NULL, '2025-06-29 11:46:55'),
(231, 2, 'order_status_update', 'Updated order #12 status to delivered', NULL, '2025-06-29 11:47:03'),
(232, 2, 'logout', 'User logged out', NULL, '2025-06-29 11:47:36'),
(233, 1, 'login', 'User logged in', NULL, '2025-06-29 11:47:48'),
(234, 1, 'logout', 'User logged out', NULL, '2025-06-29 12:22:15'),
(235, 2, 'login', 'User logged in', NULL, '2025-06-29 12:22:48'),
(236, 9, 'login', 'User logged in', NULL, '2025-06-29 23:20:53'),
(237, 9, 'logout', 'User logged out', NULL, '2025-06-29 23:21:22'),
(238, 3, 'login', 'User logged in', NULL, '2025-06-29 23:21:33'),
(239, 10, 'registration', 'New customer registered', NULL, '2025-06-29 23:27:17'),
(240, 10, 'login', 'User logged in', NULL, '2025-06-29 23:27:32'),
(241, 9, 'login', 'User logged in', NULL, '2025-06-30 02:05:12'),
(242, 9, 'logout', 'User logged out', NULL, '2025-06-30 02:07:30'),
(243, 1, 'login', 'User logged in', NULL, '2025-06-30 02:07:54'),
(244, 1, 'logout', 'User logged out', NULL, '2025-06-30 02:12:48'),
(245, 3, 'login', 'User logged in', NULL, '2025-06-30 02:13:05'),
(246, 3, 'logout', 'User logged out', NULL, '2025-06-30 02:14:38'),
(247, 2, 'login', 'User logged in', NULL, '2025-06-30 04:04:10'),
(248, 2, 'logout', 'User logged out', NULL, '2025-06-30 06:18:50'),
(249, 9, 'login', 'User logged in', NULL, '2025-06-30 06:42:23'),
(250, 9, 'profile_update', 'Updated profile information', NULL, '2025-06-30 06:43:09'),
(251, 9, 'logout', 'User logged out', NULL, '2025-06-30 06:43:15'),
(252, 9, 'login', 'User logged in', NULL, '2025-06-30 06:43:28'),
(253, 9, 'logout', 'User logged out', NULL, '2025-06-30 06:49:33'),
(254, 2, 'login', 'User logged in', NULL, '2025-06-30 07:02:59'),
(255, 2, 'logout', 'User logged out', NULL, '2025-06-30 07:12:59'),
(256, 9, 'login', 'User logged in', NULL, '2025-06-30 07:13:13'),
(257, 9, 'order_placed', 'Order #13 placed', NULL, '2025-06-30 07:16:10'),
(258, 9, 'refill_requested', 'Refill order #14 created for Round (5 gallons)', NULL, '2025-06-30 07:23:29'),
(259, 9, 'logout', 'User logged out', NULL, '2025-06-30 07:36:22'),
(260, 1, 'login', 'User logged in', NULL, '2025-06-30 07:36:30'),
(261, 1, 'logout', 'User logged out', NULL, '2025-06-30 07:41:53'),
(262, 2, 'login', 'User logged in', NULL, '2025-06-30 07:51:42'),
(263, 7, 'login', 'User logged in', NULL, '2025-07-01 13:18:44'),
(264, 7, 'cart_update', 'Added 1 item(s) to cart', NULL, '2025-07-01 13:20:04'),
(265, 7, 'cart_update', 'Added 1 item(s) to cart', NULL, '2025-07-01 13:32:22'),
(266, 7, 'order_placed', 'Order #15 placed', NULL, '2025-07-01 13:33:02'),
(267, 8, 'logout', 'User logged out', NULL, '2025-07-06 15:21:15'),
(268, 2, 'login', 'User logged in', NULL, '2025-07-06 15:21:29'),
(269, 2, 'product_updated', 'Updated product: Bottle 1 gal', NULL, '2025-07-06 15:24:49'),
(270, 2, 'logout', 'User logged out', NULL, '2025-07-06 15:27:53'),
(271, 11, 'registration', 'New customer registered', NULL, '2025-07-06 15:30:22'),
(272, 11, 'login', 'User logged in', NULL, '2025-07-06 15:30:34'),
(273, 11, 'refill_requested', 'Refill order #16 created for Slim (5 gallons)', NULL, '2025-07-06 15:31:21'),
(274, 11, 'refill_requested', 'Refill order #17 created for 10 liters', 'localhost', '2025-07-06 15:50:43'),
(275, 11, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-07-06 15:51:22'),
(276, 11, 'order_placed', 'Order #18 placed', 'localhost', '2025-07-06 15:51:49'),
(277, 11, 'logout', 'User logged out', 'localhost', '2025-07-06 15:52:30'),
(278, 2, 'login', 'User logged in', 'localhost', '2025-07-06 15:52:40'),
(279, 2, 'order_status_update', 'Updated order #18 status to delivered', 'localhost', '2025-07-06 15:54:44'),
(280, 2, 'logout', 'User logged out', 'localhost', '2025-07-06 16:04:16'),
(281, 11, 'login', 'User logged in', 'localhost', '2025-07-06 16:04:33'),
(282, 11, 'logout', 'User logged out', 'localhost', '2025-07-06 16:08:33'),
(283, 1, 'login', 'User logged in', 'localhost', '2025-07-06 16:08:43'),
(284, 1, 'settings_update', 'Updated system settings', 'localhost', '2025-07-06 16:09:43'),
(285, 1, 'logout', 'User logged out', 'localhost', '2025-07-06 16:09:57'),
(286, 2, 'login', 'User logged in', 'localhost', '2025-07-06 16:10:14'),
(287, 2, 'logout', 'User logged out', 'localhost', '2025-07-06 16:24:01'),
(288, 11, 'login', 'User logged in', 'localhost', '2025-07-06 16:24:13'),
(289, 11, 'logout', 'User logged out', 'localhost', '2025-07-06 16:36:07'),
(290, 2, 'login', 'User logged in', 'localhost', '2025-07-06 16:36:16'),
(291, 2, 'product_created', 'Added new product: 5 Gallon Slim Container', 'localhost', '2025-07-06 16:39:14'),
(292, 2, 'product_deleted', 'Deleted product ID: 6', 'localhost', '2025-07-06 16:39:23'),
(293, 2, 'product_deleted', 'Deleted product ID: 5', 'localhost', '2025-07-06 16:39:31'),
(294, 2, 'product_deleted', 'Deleted product ID: 3', 'localhost', '2025-07-06 16:39:34'),
(295, 2, 'product_deleted', 'Deleted product ID: 4', 'localhost', '2025-07-06 16:39:37'),
(296, 2, 'product_deleted', 'Deleted product ID: 9', 'localhost', '2025-07-06 16:39:41'),
(297, 2, 'product_deleted', 'Deleted product ID: 10', 'localhost', '2025-07-06 16:39:45'),
(298, 2, 'product_deleted', 'Deleted product ID: 7', 'localhost', '2025-07-06 16:39:51'),
(299, 2, 'inventory_update', 'Updated inventory #12: Quantity=7, Threshold=2', 'localhost', '2025-07-06 16:40:13'),
(300, 2, 'product_updated', 'Updated product: Dispenser', 'localhost', '2025-07-06 16:40:26'),
(301, 2, 'product_created', 'Added new product: 5 Gallon Round', 'localhost', '2025-07-06 16:41:17'),
(302, 2, 'product_updated', 'Updated product: 5 Gallon Slim', 'localhost', '2025-07-06 16:41:30'),
(303, 2, 'product_created', 'Added new product: 1 Gallon', 'localhost', '2025-07-06 16:45:47'),
(304, 2, 'product_created', 'Added new product: 1 case | 350ml bottled water', 'localhost', '2025-07-06 16:51:35'),
(305, 2, 'product_created', 'Added new product: 1 case | 500ml bottled water', 'localhost', '2025-07-06 16:52:56'),
(306, 2, 'product_updated', 'Updated product: 1 case | 350ml bottled water', 'localhost', '2025-07-06 17:01:58'),
(307, 2, 'product_updated', 'Updated product: Dispenser', 'localhost', '2025-07-06 17:02:30'),
(308, 2, 'product_updated', 'Updated product: 5 Gallon Slim', 'localhost', '2025-07-06 17:02:50'),
(309, 2, 'product_updated', 'Updated product: 5 Gallon Round', 'localhost', '2025-07-06 17:03:08'),
(310, 2, 'product_updated', 'Updated product: 1 case | 500ml bottled water', 'localhost', '2025-07-06 17:03:37'),
(311, 2, 'product_updated', 'Updated product: 1 Gallon', 'localhost', '2025-07-06 17:04:30'),
(312, 2, 'logout', 'User logged out', 'localhost', '2025-07-06 17:05:28'),
(313, 11, 'login', 'User logged in', 'localhost', '2025-07-06 17:05:41'),
(314, 11, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-07-06 17:05:55'),
(315, 11, 'order_placed', 'Order #19 placed', 'localhost', '2025-07-06 17:06:21'),
(316, 11, 'logout', 'User logged out', 'localhost', '2025-07-06 17:06:54'),
(317, 2, 'login', 'User logged in', 'localhost', '2025-07-06 17:07:02'),
(318, 2, 'logout', 'User logged out', 'localhost', '2025-07-06 17:07:13'),
(319, 1, 'login', 'User logged in', 'localhost', '2025-07-06 17:07:21'),
(320, 1, 'logout', 'User logged out', 'localhost', '2025-07-06 17:07:46'),
(321, 3, 'login', 'User logged in', 'localhost', '2025-07-06 17:07:58'),
(322, 3, 'product_deleted', 'Deleted product ID: 16', 'localhost', '2025-07-06 17:08:48'),
(323, 3, 'product_deleted', 'Deleted product ID: 15', 'localhost', '2025-07-06 17:08:57'),
(324, 3, 'product_deleted', 'Deleted product ID: 17', 'localhost', '2025-07-06 17:09:01'),
(325, 3, 'product_deleted', 'Deleted product ID: 14', 'localhost', '2025-07-06 17:09:05'),
(326, 3, 'product_deleted', 'Deleted product ID: 8', 'localhost', '2025-07-06 17:09:09'),
(327, 3, 'product_deleted', 'Deleted product ID: 13', 'localhost', '2025-07-06 17:09:12'),
(328, 3, 'product_deleted', 'Deleted product ID: 18', 'localhost', '2025-07-06 17:09:16'),
(329, 3, 'product_deleted', 'Deleted product ID: 19', 'localhost', '2025-07-06 17:09:22'),
(330, 3, 'product_deleted', 'Deleted product ID: 20', 'localhost', '2025-07-06 17:09:26'),
(331, 3, 'logout', 'User logged out', 'localhost', '2025-07-06 17:10:18'),
(332, 2, 'login', 'User logged in', 'localhost', '2025-07-06 17:10:27'),
(333, 2, 'product_updated', 'Updated product: Hot & Cold Water Dispenser', 'localhost', '2025-07-06 17:11:17'),
(334, 2, 'logout', 'User logged out', 'localhost', '2025-07-06 17:11:35'),
(335, 3, 'login', 'User logged in', 'localhost', '2025-07-06 17:11:45'),
(336, 3, 'product_created', 'Added new product: Hot & Cold Water Dispenser', 'localhost', '2025-07-06 17:12:37'),
(337, 3, 'product_created', 'Added new product: 5 Gallon Round', 'localhost', '2025-07-06 17:14:32'),
(338, 3, 'product_created', 'Added new product: 5 Gallon Slim', 'localhost', '2025-07-06 17:15:26'),
(339, 3, 'product_created', 'Added new product: 1 Gallon', 'localhost', '2025-07-06 17:16:45'),
(340, 3, 'product_created', 'Added new product: 1 case | 350ml bottled water', 'localhost', '2025-07-06 17:17:51'),
(341, 3, 'product_created', 'Added new product: 1 case | 500ml bottled water', 'localhost', '2025-07-06 17:18:53'),
(342, 3, 'logout', 'User logged out', 'localhost', '2025-07-06 17:19:12'),
(343, 11, 'login', 'User logged in', 'localhost', '2025-07-07 00:55:19'),
(344, 11, 'refill_requested', 'Refill order #20 created for Round (5 gallons)', 'localhost', '2025-07-07 00:56:10'),
(345, 11, 'refill_requested', 'Refill order #21 created for Round (5 gallons)', 'localhost', '2025-07-07 00:56:16'),
(346, 11, 'refill_requested', 'Refill order #22 created for Round (5 gallons)', 'localhost', '2025-07-07 00:56:23'),
(347, 11, 'refill_requested', 'Refill order #23 created for Slim (5 gallons)', 'localhost', '2025-07-07 00:57:14'),
(348, 11, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-07-07 00:58:39'),
(349, 11, 'order_placed', 'Order #24 placed', 'localhost', '2025-07-07 00:59:22'),
(350, 11, 'refill_requested', 'Refill order #25 created for Slim (5 gallons)', 'localhost', '2025-07-07 01:03:24'),
(351, 11, 'order_cancelled', 'Order #19 cancelled by customer', NULL, '2025-07-07 01:05:38'),
(352, 11, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-07-07 01:05:59'),
(353, 11, 'order_placed', 'Order #26 placed', 'localhost', '2025-07-07 01:06:21'),
(354, 11, 'logout', 'User logged out', 'localhost', '2025-07-07 01:08:05'),
(355, 2, 'login', 'User logged in', 'localhost', '2025-07-07 01:08:15'),
(356, 2, 'logout', 'User logged out', 'localhost', '2025-07-07 01:13:46'),
(357, 1, 'login', 'User logged in', 'localhost', '2025-07-07 01:13:55'),
(358, 1, 'logout', 'User logged out', 'localhost', '2025-07-07 01:16:42'),
(359, 2, 'login', 'User logged in', 'localhost', '2025-07-07 01:16:49'),
(360, 2, 'logout', 'User logged out', 'localhost', '2025-07-07 01:17:06'),
(361, 11, 'login', 'User logged in', 'localhost', '2025-07-07 01:17:36'),
(362, 11, 'logout', 'User logged out', 'localhost', '2025-07-07 01:18:01'),
(363, 11, 'login', 'User logged in', 'localhost', '2025-07-07 02:34:33'),
(364, 11, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-07-07 02:35:03'),
(365, 11, 'order_placed', 'Order #27 placed', 'localhost', '2025-07-07 02:35:22'),
(366, 11, 'logout', 'User logged out', 'localhost', '2025-07-07 02:36:25'),
(367, 2, 'login', 'User logged in', 'localhost', '2025-07-07 02:36:36'),
(368, 2, 'logout', 'User logged out', 'localhost', '2025-07-07 02:38:02'),
(369, 1, 'login', 'User logged in', 'localhost', '2025-07-07 02:38:10'),
(370, 1, 'logout', 'User logged out', 'localhost', '2025-07-07 02:43:27'),
(371, 2, 'login', 'User logged in', 'localhost', '2025-07-07 02:43:39'),
(372, 2, 'login', 'User logged in', 'localhost', '2025-08-01 12:54:04'),
(373, 2, 'logout', 'User logged out', 'localhost', '2025-08-01 13:04:26'),
(374, 12, 'registration', 'New customer registered', 'localhost', '2025-08-01 13:19:20'),
(375, 13, 'registration', 'New customer registered', 'localhost', '2025-08-01 13:22:23'),
(376, 13, 'login', 'User logged in', 'localhost', '2025-08-01 13:24:39'),
(377, 13, 'logout', 'User logged out', 'localhost', '2025-08-01 13:29:32'),
(378, 12, 'login', 'User logged in', 'localhost', '2025-08-01 13:37:48'),
(379, 12, 'refill_requested', 'Refill order #28 created for Slim (5 gallons)', 'localhost', '2025-08-01 13:38:18'),
(380, 12, 'logout', 'User logged out', 'localhost', '2025-08-01 13:39:05'),
(381, 3, 'login', 'User logged in', 'localhost', '2025-08-01 13:39:14'),
(382, 3, 'logout', 'User logged out', 'localhost', '2025-08-01 13:39:50'),
(383, 12, 'login', 'User logged in', 'localhost', '2025-08-01 13:40:03'),
(384, 12, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-08-01 13:40:15'),
(385, 12, 'order_placed', 'Order #29 placed', 'localhost', '2025-08-01 13:40:58'),
(386, 12, 'refill_requested', 'Refill order #30 created for Slim (5 gallons)', 'localhost', '2025-08-01 13:41:33'),
(387, 12, 'refill_requested', 'Refill order #31 created for Slim (5 gallons)', 'localhost', '2025-08-01 13:41:38'),
(388, 12, 'logout', 'User logged out', 'localhost', '2025-08-01 13:41:59'),
(389, 3, 'login', 'User logged in', 'localhost', '2025-08-01 13:42:16'),
(390, 12, 'login', 'User logged in', 'localhost', '2025-08-11 13:33:25'),
(391, 12, 'order_cancelled', 'Order #29 cancelled by customer', NULL, '2025-08-11 13:33:42'),
(392, 12, 'refill_requested', 'Refill order #32 created for Round (5 gallons)', 'localhost', '2025-08-11 13:34:13'),
(393, 12, 'order_cancelled', 'Order #28 cancelled by customer', NULL, '2025-08-11 13:35:05'),
(394, 12, 'order_cancelled', 'Order #30 cancelled by customer', NULL, '2025-08-11 13:35:13'),
(395, 12, 'order_cancelled', 'Order #31 cancelled by customer', NULL, '2025-08-11 13:35:18'),
(396, 12, 'order_cancelled', 'Order #32 cancelled by customer', NULL, '2025-08-11 13:35:22'),
(397, 12, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-08-11 13:35:39'),
(398, 12, 'order_placed', 'Order #33 placed', 'localhost', '2025-08-11 13:36:10'),
(399, 12, 'logout', 'User logged out', 'localhost', '2025-08-11 13:36:53'),
(400, 3, 'login', 'User logged in', 'localhost', '2025-08-11 13:37:03'),
(401, 3, 'order_status_update', 'Updated order #33 status to delivered', 'localhost', '2025-08-11 13:37:25'),
(402, 3, 'logout', 'User logged out', 'localhost', '2025-08-11 13:43:40'),
(403, 12, 'login', 'User logged in', 'localhost', '2025-08-11 13:44:18'),
(404, 12, 'logout', 'User logged out', 'localhost', '2025-08-11 13:45:33'),
(405, 14, 'registration', 'New customer registered', 'localhost', '2025-08-11 13:48:42'),
(406, 14, 'login', 'User logged in', 'localhost', '2025-08-11 13:49:03'),
(407, 14, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-08-11 13:49:24'),
(408, 14, 'order_placed', 'Order #34 placed', 'localhost', '2025-08-11 13:49:52'),
(409, 3, 'login', 'User logged in', 'localhost', '2025-08-11 13:50:19'),
(410, 3, 'order_status_update', 'Updated order #34 status to delivered', 'localhost', '2025-08-11 13:50:42'),
(411, 3, 'logout', 'User logged out', 'localhost', '2025-08-11 13:51:22'),
(412, 1, 'login', 'User logged in', 'localhost', '2025-08-11 13:51:32'),
(413, 1, 'logout', 'User logged out', 'localhost', '2025-08-11 13:54:08'),
(414, 3, 'login', 'User logged in', 'localhost', '2025-08-11 13:54:18'),
(415, 3, 'logout', 'User logged out', 'localhost', '2025-08-11 13:55:13'),
(416, 13, 'login', 'User logged in', 'localhost', '2025-08-11 13:55:33'),
(417, 13, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-08-11 13:55:48'),
(418, 13, 'order_placed', 'Order #35 placed', 'localhost', '2025-08-11 13:56:10'),
(419, 13, 'logout', 'User logged out', 'localhost', '2025-08-11 13:56:18'),
(420, 3, 'login', 'User logged in', 'localhost', '2025-08-11 13:56:27'),
(421, 3, 'order_status_update', 'Updated order #35 status to delivered', 'localhost', '2025-08-11 13:56:40'),
(422, 1, 'login', 'User logged in', 'localhost', '2025-08-12 02:27:24'),
(423, 1, 'logout', 'User logged out', 'localhost', '2025-08-12 02:28:27'),
(424, 15, 'registration', 'New customer registered', 'localhost', '2025-08-12 02:31:12'),
(425, 15, 'login', 'User logged in', 'localhost', '2025-08-12 02:31:28'),
(426, 15, 'cart_update', 'Added 3 item(s) to cart', 'localhost', '2025-08-12 02:31:41'),
(427, 15, 'order_placed', 'Order #36 placed', 'localhost', '2025-08-12 02:32:03'),
(428, 15, 'logout', 'User logged out', 'localhost', '2025-08-12 02:32:54'),
(429, 1, 'login', 'User logged in', 'localhost', '2025-08-12 02:33:17'),
(430, 1, 'login', 'User logged in', 'localhost', '2025-08-14 04:31:29'),
(431, 3, 'login', 'User logged in', 'localhost', '2025-08-14 04:32:53'),
(432, 1, 'logout', 'User logged out', 'localhost', '2025-08-14 04:47:11'),
(433, 16, 'registration', 'New customer registered', 'localhost', '2025-08-14 04:51:20'),
(434, 16, 'login', 'User logged in', 'localhost', '2025-08-14 04:51:57'),
(435, 16, 'cart_update', 'Added 2 item(s) to cart', 'localhost', '2025-08-14 04:52:17'),
(436, 16, 'cart_update', 'Added 1 item(s) to cart', 'localhost', '2025-08-14 04:52:27'),
(437, 16, 'order_placed', 'Order #37 placed', 'localhost', '2025-08-14 04:52:55'),
(438, 16, 'logout', 'User logged out', 'localhost', '2025-08-14 04:53:04'),
(439, 17, 'registration', 'New customer registered', 'localhost', '2025-08-14 04:53:58'),
(440, 17, 'login', 'User logged in', 'localhost', '2025-08-14 04:54:12'),
(441, 17, 'cart_update', 'Added 5 item(s) to cart', 'localhost', '2025-08-14 04:54:30'),
(442, 17, 'order_placed', 'Order #38 placed', 'localhost', '2025-08-14 04:54:48'),
(443, 17, 'logout', 'User logged out', 'localhost', '2025-08-14 04:54:54'),
(444, 18, 'registration', 'New customer registered', 'localhost', '2025-08-14 04:56:00'),
(445, 18, 'login', 'User logged in', 'localhost', '2025-08-14 04:56:12'),
(446, 18, 'cart_update', 'Added 3 item(s) to cart', 'localhost', '2025-08-14 04:56:26'),
(447, 18, 'order_placed', 'Order #39 placed', 'localhost', '2025-08-14 04:56:46'),
(448, 18, 'logout', 'User logged out', 'localhost', '2025-08-14 04:56:51'),
(449, 19, 'registration', 'New customer registered', 'localhost', '2025-08-14 04:58:05'),
(450, 19, 'login', 'User logged in', 'localhost', '2025-08-14 04:58:16'),
(451, 19, 'cart_update', 'Added 10 item(s) to cart', 'localhost', '2025-08-14 04:58:40'),
(452, 19, 'order_placed', 'Order #40 placed', 'localhost', '2025-08-14 04:58:58'),
(453, 19, 'logout', 'User logged out', 'localhost', '2025-08-14 04:59:04'),
(454, 20, 'registration', 'New customer registered', 'localhost', '2025-08-14 08:00:33'),
(455, 20, 'login', 'User logged in', 'localhost', '2025-08-14 08:00:47'),
(456, 20, 'cart_update', 'Added 30 item(s) to cart', 'localhost', '2025-08-14 08:00:57'),
(457, 20, 'order_placed', 'Order #41 placed', 'localhost', '2025-08-14 05:01:16'),
(458, 20, 'logout', 'User logged out', 'localhost', '2025-08-14 05:01:24'),
(459, 21, 'registration', 'New customer registered', 'localhost', '2025-08-14 05:02:19'),
(460, 21, 'login', 'User logged in', 'localhost', '2025-08-14 05:02:28'),
(461, 21, 'cart_update', 'Added 10 item(s) to cart', 'localhost', '2025-08-14 05:02:38'),
(462, 21, 'order_placed', 'Order #42 placed', 'localhost', '2025-08-14 05:02:57'),
(463, 21, 'logout', 'User logged out', 'localhost', '2025-08-14 05:03:04'),
(464, 3, 'login', 'User logged in', 'localhost', '2025-08-14 05:03:13'),
(465, 3, 'order_status_update', 'Updated order #42 status to pending', 'localhost', '2025-08-14 05:08:39'),
(466, 3, 'order_status_update', 'Updated order #42 status to processing', 'localhost', '2025-08-14 05:08:50'),
(467, 3, 'order_status_update', 'Updated order #41 status to delivered', 'localhost', '2025-08-14 05:09:00'),
(468, 3, 'order_status_update', 'Updated order #40 status to delivered', 'localhost', '2025-08-14 05:09:10'),
(469, 3, 'order_status_update', 'Updated order #38 status to cancelled', 'localhost', '2025-08-14 05:10:44'),
(470, 3, 'logout', 'User logged out', 'localhost', '2025-08-14 05:16:21'),
(471, 1, 'login', 'User logged in', 'localhost', '2025-08-14 05:16:38'),
(472, 22, 'registration', 'New customer registered', 'localhost', '2025-10-19 04:41:26'),
(473, 22, 'login', 'User logged in', 'localhost', '2025-10-19 04:41:43');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('price_update','promo','general','maintenance') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `start_date` datetime NOT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `branch_id`, `title`, `message`, `type`, `status`, `start_date`, `end_date`, `created_by`, `created_at`) VALUES
(2, 1, 'Maintenance', 'We are under maintenance!', 'maintenance', 'active', '2025-07-07 00:00:00', '2025-07-08 00:00:00', 2, '2025-07-06 16:04:10'),
(3, 1, 'Price Adjustment Notice', 'To all valued customer, we are informing you that the water refilling price will increase', 'price_update', 'active', '2025-07-31 00:00:00', '2025-09-30 00:00:00', 2, '2025-07-07 01:13:20'),
(4, 2, 'Price Adjustment Notice', 'Hello our Valued Customers!\r\n\r\nWe wanted to give you a quick heads-up — starting tomorrow (August 12, 2025), there will be a small change in our prices.\r\n\r\nWe’ve done our best to keep the adjustment minimal, but with rising costs, it’s a necessary step to keep delivering the quality and service you deserve.\r\n\r\nThanks so much for your understanding and continued support!', 'price_update', 'active', '2025-08-12 00:00:00', '2025-08-31 00:00:00', 3, '2025-08-11 13:43:21'),
(5, 2, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'general', 'active', '2025-08-16 00:00:00', '2025-08-16 00:00:00', 3, '2025-08-14 05:26:19');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `service_type` enum('delivery','maintenance','installation') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `customer_id`, `branch_id`, `appointment_date`, `status`, `service_type`, `notes`, `created_at`) VALUES
(1, 5, 2, '2025-06-18 08:00:00', 'cancelled', 'maintenance', '1123', '2025-06-16 16:41:48');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `barangay_id` int(11) NOT NULL,
  `barangay_name` varchar(100) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`barangay_id`, `barangay_name`, `branch_id`, `created_at`) VALUES
(2, 'Balite', 1, '2025-06-16 16:20:52'),
(1, 'Baruyan', 1, '2025-06-16 16:20:52'),
(3, 'Bucayao', 1, '2025-06-16 16:20:52'),
(4, 'Batino', 2, '2025-06-16 16:20:52'),
(5, 'Balingayan', 2, '2025-06-16 16:20:52'),
(6, 'Biga', 2, '2025-06-16 16:20:52'),
(7, 'Bayanan I', 2, '2025-06-29 06:33:01'),
(8, 'Bayanan II', 2, '2025-06-29 06:33:59'),
(9, 'Bondoc', 2, '2025-06-29 06:33:59'),
(10, 'Canubing I', 1, '2025-06-29 06:46:32'),
(11, 'Canubing II', 1, '2025-06-29 06:46:32'),
(12, 'Guinobatan', 1, '2025-06-29 06:46:32'),
(13, 'Gulod', 1, '2025-06-29 06:46:32'),
(14, 'Gutad', 1, '2025-06-29 06:46:32'),
(15, 'Ibaba East', 1, '2025-06-29 06:47:17'),
(16, 'Ibaba West', 1, '2025-06-29 06:47:17'),
(17, 'Lalud', 1, '2025-06-29 06:48:32'),
(18, 'Libis', 1, '2025-06-29 06:48:32'),
(19, 'Lazareto', 1, '2025-06-29 06:48:32'),
(20, 'Lumangbayan', 1, '2025-06-29 06:48:32'),
(21, 'Mahal na Pangalan', 1, '2025-06-29 06:48:32'),
(22, 'Maidlang', 1, '2025-06-29 06:48:56'),
(23, 'Malad', 1, '2025-06-29 06:48:56'),
(24, 'Managpi', 1, '2025-06-29 06:50:01'),
(25, 'Nag-Iba I', 2, '2025-06-29 06:50:01'),
(26, 'Navotas', 1, '2025-06-29 06:50:01'),
(27, 'Pachoca', 1, '2025-06-29 06:50:01'),
(28, 'Parang', 1, '2025-06-29 06:50:01'),
(29, 'Patas', 1, '2025-06-29 06:50:18'),
(30, 'Salong', 1, '2025-06-29 06:50:18'),
(31, 'San Antonio', 1, '2025-06-29 06:51:19'),
(32, 'Silonay', 1, '2025-06-29 06:51:19'),
(33, 'Suqi', 1, '2025-06-29 06:51:19'),
(34, 'Tawagan', 1, '2025-06-29 06:51:19'),
(35, 'Navotas', 1, '2025-06-30 03:27:19'),
(36, 'Pachoca', 1, '2025-06-30 03:28:29'),
(37, 'Parang', 1, '2025-06-30 03:28:29'),
(38, 'Parang', 1, '2025-06-30 03:30:59'),
(39, 'Patas', 1, '2025-06-30 03:30:59'),
(40, 'Salong (San Raphael)', 1, '2025-06-30 03:30:59'),
(41, 'San Antonio', 1, '2025-06-30 03:30:59'),
(42, 'Silonay', 1, '2025-06-30 03:30:59'),
(43, 'Suqui', 1, '2025-06-30 03:30:59'),
(44, 'Tawagan', 1, '2025-06-30 03:30:59'),
(45, 'Tibag', 1, '2025-06-30 03:30:59'),
(46, 'Wawa', 1, '2025-06-30 03:30:59'),
(47, 'Buhuan', 2, '2025-06-30 03:39:07'),
(48, 'Bulusan', 2, '2025-06-30 03:39:07'),
(49, 'Calero', 2, '2025-06-30 03:41:51'),
(50, 'Camansihan', 2, '2025-06-30 03:41:51'),
(51, 'Camilmil', 2, '2025-06-30 03:41:51'),
(52, 'Comunal', 2, '2025-06-30 03:41:51'),
(53, 'Ilaya', 2, '2025-06-30 03:41:51'),
(54, 'Masipit', 2, '2025-06-30 03:41:51'),
(55, 'Nag-Iba I', 2, '2025-06-30 03:41:51'),
(56, 'Nag-Iba II', 2, '2025-06-30 03:41:51'),
(57, 'Palhi', 2, '2025-06-30 03:41:51'),
(58, 'Panggalaan', 2, '2025-06-30 03:41:51');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `branch_id` int(11) NOT NULL,
  `branch_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`branch_id`, `branch_name`, `created_at`) VALUES
(1, 'Lazareto', '2025-06-16 14:11:50'),
(2, 'Calero', '2025-06-16 14:11:50');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 10,
  `last_updated` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `branch_id`, `product_id`, `quantity`, `low_stock_threshold`, `last_updated`) VALUES
(30, 2, 30, 217, 30, '2025-08-14 05:01:16'),
(25, 1, 25, 250, 30, '2025-07-06 16:52:56'),
(22, 1, 22, 50, 5, '2025-07-06 16:41:17'),
(24, 1, 24, 247, 30, '2025-07-07 02:35:22'),
(23, 1, 23, 46, 5, '2025-08-12 02:32:03'),
(12, 1, 12, 7, 2, '2025-07-06 16:40:13'),
(29, 2, 29, 50, 5, '2025-07-06 17:16:45'),
(31, 2, 31, 229, 30, '2025-08-14 05:02:57'),
(28, 2, 28, 46, 5, '2025-08-14 04:56:46'),
(27, 2, 27, 45, 5, '2025-08-14 04:54:48'),
(26, 2, 26, 5, 2, '2025-08-14 04:52:55'),
(21, 1, 21, 50, 5, '2025-07-06 16:39:14');

-- --------------------------------------------------------

--
-- Table structure for table `loyalty`
--

CREATE TABLE `loyalty` (
  `loyalty_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `years_of_loyalty` int(11) DEFAULT 0,
  `reward_level` varchar(50) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `last_calculated` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `loyalty`
--

INSERT INTO `loyalty` (`loyalty_id`, `customer_id`, `years_of_loyalty`, `reward_level`, `points`, `last_calculated`) VALUES
(1, 5, 1, NULL, 100, '2025-06-16 16:23:40'),
(2, 7, 0, 'Bronze', 5, '2025-06-29 04:48:30'),
(3, 6, 0, NULL, 0, '2025-06-29 04:50:56'),
(4, 8, 0, NULL, 0, '2025-06-29 05:42:30'),
(5, 9, 0, NULL, 1, '2025-06-29 07:13:17'),
(6, 10, 0, NULL, 0, '2025-06-29 23:27:17'),
(7, 11, 0, NULL, 13, '2025-07-06 15:30:22'),
(8, 12, 0, NULL, 80, '2025-08-01 13:19:20'),
(9, 13, 0, NULL, 3, '2025-08-01 13:22:23'),
(10, 14, 0, NULL, 2, '2025-08-11 13:48:42'),
(11, 15, 0, NULL, 1, '2025-08-12 02:31:12'),
(12, 16, 0, NULL, 77, '2025-08-14 04:51:20'),
(13, 17, 0, NULL, 10, '2025-08-14 04:53:58'),
(14, 18, 0, NULL, 6, '2025-08-14 04:56:00'),
(15, 19, 0, NULL, 45, '2025-08-14 04:58:05'),
(16, 20, 0, NULL, 115, '2025-08-14 08:00:33'),
(17, 21, 0, NULL, 45, '2025-08-14 05:02:19'),
(18, 22, 0, NULL, 0, '2025-10-19 04:41:26');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedule`
--

CREATE TABLE `maintenance_schedule` (
  `schedule_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('scheduled','in_progress','completed','cancelled') DEFAULT 'scheduled',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_schedule`
--

INSERT INTO `maintenance_schedule` (`schedule_id`, `branch_id`, `title`, `description`, `start_date`, `end_date`, `status`, `created_by`, `created_at`) VALUES
(5, 2, 'Sanitation', 'Cleaning the machines with right protocol sanitation', '2025-07-30 10:11:00', '2025-07-31 10:11:00', 'completed', 1, '2025-06-30 02:11:50'),
(4, 2, 'Water filtering', 'sjhfsjfhj', '2025-06-29 13:14:00', '2025-06-29 13:18:00', 'completed', 1, '2025-06-29 05:13:37'),
(6, 2, 'Sanitation Day', 'Staff member of each branch should sanitize equipment for safety purposes of the customer', '2025-08-16 08:21:00', '2025-08-16 16:00:00', 'scheduled', 1, '2025-08-14 05:22:53'),
(7, 1, 'Sanitation Day', 'Staff member of each branch should sanitize equipment for safety purposes of the customer', '2025-08-16 08:22:00', '2025-08-16 16:00:00', 'scheduled', 1, '2025-08-14 05:24:02');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message_text` text NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `message_text`, `timestamp`, `is_read`) VALUES
(7, 3, 7, 'Dear Mary Joy Macalindol,\n\nYour order #8 has been delivered successfully.\nThank you for your business!\n\n\nRegards,\nWater Refilling Station', '2025-06-29 19:42:44', 1),
(6, 3, 7, 'Dear Mary Joy Macalindol,\n\nYour order #9 has been delivered successfully.\nThank you for your business!\n\nYou earned 2 loyalty points from this purchase.\n\nRegards,\nWater Refilling Station', '2025-06-29 19:41:40', 1),
(8, 2, 11, 'good evening!', '2025-07-07 00:14:06', 1),
(9, 11, 2, 'Good Eve!', '2025-07-07 00:24:33', 1),
(10, 22, 2, 'hi', '2025-10-19 12:42:25', 0);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('maintenance','loyalty_reward','order_update','system') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(32, 11, 'Price Adjustment Notice', 'To all valued customer, we are informing you that the water refilling price will increase', 'system', 0, '2025-07-07 01:13:20'),
(31, 10, 'Price Adjustment Notice', 'To all valued customer, we are informing you that the water refilling price will increase', 'system', 0, '2025-07-07 01:13:20'),
(30, 8, 'Price Adjustment Notice', 'To all valued customer, we are informing you that the water refilling price will increase', 'system', 0, '2025-07-07 01:13:20'),
(29, 9, 'Price Adjustment Notice', 'To all valued customer, we are informing you that the water refilling price will increase', 'system', 0, '2025-07-07 01:13:20'),
(28, 3, 'Maintenance Has been completed', 'Maintenance schedule for Calero has been completed.\n\nDetails: Cleaning the machines with right protocol sanitation\nPeriod: Jul 30, 2025 10:11 AM to Jul 31, 2025 10:11 AM', 'maintenance', 1, '2025-07-06 16:09:32'),
(26, 10, 'Maintenance', 'We are under maintenance!', 'system', 0, '2025-07-06 16:04:10'),
(27, 11, 'Maintenance', 'We are under maintenance!', 'system', 1, '2025-07-06 16:04:10'),
(25, 8, 'Maintenance', 'We are under maintenance!', 'system', 0, '2025-07-06 16:04:10'),
(23, 7, 'Maintenance Has started', 'Maintenance schedule for Calero has started.\n\nDetails: Cleaning the machines with right protocol sanitation\nPeriod: Jul 30, 2025 10:11 AM to Jul 31, 2025 10:11 AM', 'maintenance', 1, '2025-06-30 02:12:01'),
(22, 3, 'Maintenance Has started', 'Maintenance schedule for Calero has started.\n\nDetails: Cleaning the machines with right protocol sanitation\nPeriod: Jul 30, 2025 10:11 AM to Jul 31, 2025 10:11 AM', 'maintenance', 1, '2025-06-30 02:12:01'),
(20, 3, 'New Maintenance Schedule', 'A new maintenance has been scheduled for your branch from July 30, 2025 10:11 AM to July 31, 2025 10:11 AM.\n\nDetails: Cleaning the machines with right protocol sanitation\n\nSchedule ID: 5', 'maintenance', 1, '2025-06-30 02:11:50'),
(21, 7, 'Upcoming Maintenance Notice', 'Our branch will undergo maintenance from July 30, 2025 10:11 AM to July 31, 2025 10:11 AM.\n\nDetails: Cleaning the machines with right protocol sanitation', 'maintenance', 1, '2025-06-30 02:11:50'),
(19, 7, 'Maintenance Has been completed', 'Maintenance schedule for Calero has been completed.\n\nDetails: sjhfsjfhj\nPeriod: Jun 29, 2025 01:14 PM to Jun 29, 2025 01:15 PM', 'maintenance', 1, '2025-06-29 09:08:01'),
(18, 3, 'Maintenance Has been completed', 'Maintenance schedule for Calero has been completed.\n\nDetails: sjhfsjfhj\nPeriod: Jun 29, 2025 01:14 PM to Jun 29, 2025 01:15 PM', 'maintenance', 1, '2025-06-29 09:08:01'),
(24, 9, 'Maintenance', 'We are under maintenance!', 'system', 0, '2025-07-06 16:04:10'),
(17, 7, 'Maintenance Has started', 'Maintenance schedule for Calero has started.\n\nDetails: sjhfsjfhj\nPeriod: Jun 29, 2025 01:14 PM to Jun 29, 2025 01:15 PM', 'maintenance', 1, '2025-06-29 09:07:39'),
(16, 3, 'Maintenance Has started', 'Maintenance schedule for Calero has started.\n\nDetails: sjhfsjfhj\nPeriod: Jun 29, 2025 01:14 PM to Jun 29, 2025 01:15 PM', 'maintenance', 1, '2025-06-29 09:07:39'),
(33, 7, 'Price Adjustment Notice', 'Hello our Valued Customers!\r\n\r\nWe wanted to give you a quick heads-up — starting tomorrow (August 12, 2025), there will be a small change in our prices.\r\n\r\nWe’ve done our best to keep the adjustment minimal, but with rising costs, it’s a necessary step to keep delivering the quality and service you deserve.\r\n\r\nThanks so much for your understanding and continued support!', 'system', 0, '2025-08-11 13:43:21'),
(34, 12, 'Price Adjustment Notice', 'Hello our Valued Customers!\r\n\r\nWe wanted to give you a quick heads-up — starting tomorrow (August 12, 2025), there will be a small change in our prices.\r\n\r\nWe’ve done our best to keep the adjustment minimal, but with rising costs, it’s a necessary step to keep delivering the quality and service you deserve.\r\n\r\nThanks so much for your understanding and continued support!', 'system', 1, '2025-08-11 13:43:21'),
(35, 13, 'Price Adjustment Notice', 'Hello our Valued Customers!\r\n\r\nWe wanted to give you a quick heads-up — starting tomorrow (August 12, 2025), there will be a small change in our prices.\r\n\r\nWe’ve done our best to keep the adjustment minimal, but with rising costs, it’s a necessary step to keep delivering the quality and service you deserve.\r\n\r\nThanks so much for your understanding and continued support!', 'system', 0, '2025-08-11 13:43:21'),
(36, 3, 'New Maintenance Schedule', 'A new maintenance has been scheduled for your branch from August 16, 2025 08:21 AM to August 16, 2025 04:00 PM.\n\nDetails: Staff member of each branch should sanitize equipment for safety purposes of the customer\n\nSchedule ID: 6', 'maintenance', 0, '2025-08-14 05:22:53'),
(37, 2, 'New Maintenance Schedule', 'A new maintenance has been scheduled for your branch from August 16, 2025 08:22 AM to August 16, 2025 04:00 PM.\n\nDetails: Staff member of each branch should sanitize equipment for safety purposes of the customer\n\nSchedule ID: 7', 'maintenance', 0, '2025-08-14 05:24:02'),
(38, 7, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(39, 12, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(40, 13, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(41, 14, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(42, 16, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(43, 17, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(44, 18, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(45, 19, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(46, 20, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19'),
(47, 21, 'Sanitation Day', 'To our dear customer we announce that this August 16, 2025 will be our Sanitation Day, thus our business hours is temporary closed', 'system', 0, '2025-08-14 05:26:19');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `status` enum('pending','processing','delivered','cancelled') DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL,
  `delivery_date` datetime DEFAULT NULL,
  `sitio_purok` varchar(100) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `branch_id`, `order_date`, `status`, `total_amount`, `delivery_date`, `sitio_purok`, `delivery_address`, `notes`) VALUES
(12, 9, 1, '2025-06-29 19:46:01', 'delivered', 65.00, '2025-06-30 18:00:00', NULL, NULL, '\n[2025-06-29 19:46:55] Status updated to processing. Notes: \n[2025-06-29 19:47:03] Status updated to delivered. Notes: '),
(10, 9, 1, '2025-06-29 19:44:43', 'cancelled', 167.00, '2025-06-30 10:00:00', 'Irrigation', 'Salong, Irrigation', '\nCancelled by customer on 2025-06-29 19:45:12'),
(11, 9, 1, '2025-06-29 19:45:51', 'cancelled', 65.00, '2025-06-30 18:00:00', NULL, NULL, '\n[2025-06-29 19:46:48] Status updated to cancelled. Notes: '),
(8, 7, 2, '2025-06-29 19:38:36', 'delivered', 42.00, '2025-06-30 08:00:00', 'Centro', 'Balingayan, Centro', '\n[2025-06-29 19:42:28] Status updated to processing. Notes: \n[2025-06-29 19:42:33] Status updated to processing. Notes: \n[2025-06-29 19:42:44] Order marked as delivered by Branch Admin 2'),
(9, 7, 2, '2025-06-29 19:40:19', 'delivered', 265.00, '2025-06-30 13:00:00', NULL, NULL, '\n[2025-06-29 19:41:22] Status updated to processing. Notes: \n[2025-06-29 19:41:40] Order marked as delivered by Branch Admin 2'),
(31, 12, 2, '2025-08-01 21:41:38', 'cancelled', 265.00, '2025-08-09 13:00:00', NULL, NULL, '\nCancelled by customer on 2025-08-11 21:35:18'),
(30, 12, 2, '2025-08-01 21:41:33', 'cancelled', 265.00, '2025-08-09 13:00:00', NULL, NULL, '\nCancelled by customer on 2025-08-11 21:35:13'),
(29, 12, 2, '2025-08-01 21:40:58', 'cancelled', 456.00, '2025-08-02 13:00:00', 'Silangan', 'Buhuan, Silangan', '\nCancelled by customer on 2025-08-11 21:33:42'),
(28, 12, 2, '2025-08-01 21:38:18', 'cancelled', 265.00, '2025-08-02 11:30:00', NULL, NULL, '\nCancelled by customer on 2025-08-11 21:35:05'),
(18, 11, 1, '2025-07-06 23:51:49', 'delivered', 50.00, '2025-07-07 08:00:00', 'Centro', 'Lazareto, Centro', '\n[2025-07-06 23:54:44] Status updated to delivered. Notes: '),
(19, 11, 1, '2025-07-07 01:06:21', 'cancelled', 384.00, '2025-07-07 08:00:00', 'Centro', 'Lazareto, Centro', '\nCancelled by customer on 2025-07-07 09:05:38'),
(32, 12, 2, '2025-08-11 21:34:13', 'cancelled', 65.00, '2025-08-12 18:00:00', NULL, NULL, '\nCancelled by customer on 2025-08-11 21:35:22'),
(33, 12, 2, '2025-08-11 21:36:10', 'delivered', 7000.00, '2025-08-12 09:00:00', 'Silangan', 'Buhuan, Silangan', '\n[2025-08-11 21:37:25] Status updated to delivered. Notes: '),
(34, 14, 2, '2025-08-11 21:49:52', 'delivered', 200.00, '2025-08-12 09:00:00', 'Centro', 'Batino, Centro', '\n[2025-08-11 21:50:42] Status updated to delivered. Notes: '),
(35, 13, 2, '2025-08-11 21:56:10', 'delivered', 384.00, '2025-08-12 18:00:00', 'Centro', 'Buhuan, Centro', '\n[2025-08-11 21:56:40] Status updated to delivered. Notes: '),
(36, 15, 1, '2025-08-12 10:32:03', 'pending', 150.00, '2025-08-12 14:31:00', 'Cadre', 'Lazareto, Cadre', ''),
(37, 16, 2, '2025-08-14 12:52:55', 'pending', 7768.00, '2025-09-04 14:52:00', 'Centr', 'Batino, Centr', ''),
(38, 17, 2, '2025-08-14 12:54:47', 'cancelled', 1000.00, '2025-08-20 15:54:00', 'Centro', 'Batino, Centro', '\n[2025-08-14 13:10:44] Status updated to cancelled. Notes: '),
(39, 18, 2, '2025-08-14 12:56:46', 'pending', 600.00, '2025-10-01 15:56:00', 'centro', 'Batino, centro', ''),
(40, 19, 2, '2025-08-14 12:58:58', 'delivered', 4560.00, '2025-08-30 10:58:00', 'Centro', 'Calero, Centro', '\n[2025-08-14 13:09:10] Status updated to delivered. Notes: '),
(41, 20, 2, '2025-08-14 13:01:16', 'delivered', 11520.00, '2025-10-16 15:01:00', 'West', 'Camilmil, West', '\n[2025-08-14 13:09:00] Status updated to delivered. Notes: '),
(42, 21, 2, '2025-08-14 13:02:57', 'processing', 4560.00, '2025-08-21 15:02:00', 'East', 'Ilaya, East', '\n[2025-08-14 13:08:39] Status updated to pending. Notes: \n[2025-08-14 13:08:50] Status updated to processing. Notes: ');

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `order_detail_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_details`
--

INSERT INTO `order_details` (`order_detail_id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(1, 1, 1, 1, 30.00),
(2, 1, 3, 1, 5.00),
(3, 2, 1, 1, 30.00),
(4, 2, 3, 1, 5.00),
(5, 3, 1, 1, 1.00),
(6, 4, 2, 1, 5.00),
(7, 5, 2, 1, 5.00),
(8, 6, 16, 1, 50.00),
(9, 7, 7, 1, 50.00),
(10, 8, 14, 3, 14.00),
(11, 9, 1, 2, 30.00),
(12, 9, 2, 1, 200.00),
(13, 9, 3, 1, 5.00),
(14, 10, 7, 2, 50.00),
(15, 10, 6, 1, 67.00),
(16, 11, 1, 3, 20.00),
(17, 11, 3, 1, 5.00),
(18, 12, 1, 3, 20.00),
(19, 12, 3, 1, 5.00),
(20, 13, 7, 1, 50.00),
(21, 14, 1, 2, 30.00),
(22, 14, 3, 1, 5.00),
(23, 15, 16, 1, 50.00),
(24, 15, 15, 1, 67.00),
(25, 16, 1, 1, 30.00),
(26, 16, 2, 1, 200.00),
(27, 16, 3, 1, 5.00),
(28, 17, 1, 1, 20.00),
(29, 17, 2, 1, 200.00),
(30, 17, 3, 1, 5.00),
(31, 18, 7, 1, 50.00),
(32, 19, 24, 1, 384.00),
(33, 20, 1, 1, 30.00),
(34, 20, 3, 1, 5.00),
(35, 21, 1, 1, 30.00),
(36, 21, 3, 1, 5.00),
(37, 22, 1, 1, 30.00),
(38, 22, 3, 1, 5.00),
(39, 23, 1, 1, 30.00),
(40, 23, 3, 1, 5.00),
(41, 24, 24, 1, 384.00),
(42, 25, 1, 1, 30.00),
(43, 25, 3, 1, 5.00),
(44, 26, 23, 1, 50.00),
(45, 27, 24, 1, 384.00),
(46, 28, 1, 2, 30.00),
(47, 28, 2, 1, 200.00),
(48, 28, 3, 1, 5.00),
(49, 29, 31, 1, 456.00),
(50, 30, 1, 2, 30.00),
(51, 30, 2, 1, 200.00),
(52, 30, 3, 1, 5.00),
(53, 31, 1, 2, 30.00),
(54, 31, 2, 1, 200.00),
(55, 31, 3, 1, 5.00),
(56, 32, 1, 2, 30.00),
(57, 32, 3, 1, 5.00),
(58, 33, 26, 1, 7000.00),
(59, 34, 28, 1, 200.00),
(60, 35, 30, 1, 384.00),
(61, 36, 23, 3, 50.00),
(62, 37, 30, 2, 384.00),
(63, 37, 26, 1, 7000.00),
(64, 38, 27, 5, 200.00),
(65, 39, 28, 3, 200.00),
(66, 40, 31, 10, 456.00),
(67, 41, 30, 30, 384.00),
(68, 42, 31, 10, 456.00);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `category` enum('container','dispenser','refill','accessory') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `product_name`, `price`, `description`, `image_path`, `category`, `status`, `created_at`) VALUES
(25, '1 case | 500ml bottled water', 456.00, '48 pcs', 'uploads/products/product_686aac692c899.jpg', 'container', 'active', '2025-07-06 16:52:56'),
(23, '1 Gallon', 50.00, 'Container only', 'uploads/products/product_686aac9df31ae.jpg', 'container', 'active', '2025-07-06 16:45:47'),
(24, '1 case | 350ml bottled water', 384.00, '48 pcs', 'uploads/products/product_686aac06e3a73.jpg', 'container', 'active', '2025-07-06 16:51:35'),
(12, 'Hot & Cold Water Dispenser', 7000.00, 'Water Dispenser only', 'uploads/products/product_686aac26c26b3.jpg', 'dispenser', 'active', '2025-06-29 05:23:27'),
(27, '5 Gallon Round', 200.00, 'Container only', 'uploads/products/product_686aaef8ebce1.png', 'container', 'active', '2025-07-06 17:14:32'),
(28, '5 Gallon Slim', 200.00, 'Container only', 'uploads/products/product_686aaf2e632b3.png', 'container', 'active', '2025-07-06 17:15:26'),
(22, '5 Gallon Round', 200.00, 'Container only', 'uploads/products/product_686aac4c52ac3.png', 'container', 'active', '2025-07-06 16:41:17'),
(21, '5 Gallon Slim', 200.00, 'Container only', 'uploads/products/product_686aac3a5ccc3.png', 'container', 'active', '2025-07-06 16:39:14'),
(26, 'Hot & Cold Water Dispenser', 7000.00, 'Water Dispenser only', 'uploads/products/product_686aae851e48e.jpg', 'dispenser', 'active', '2025-07-06 17:12:37'),
(29, '1 Gallon', 50.00, 'Container only', 'uploads/products/product_686aaf7da0277.jpg', 'container', 'active', '2025-07-06 17:16:45'),
(30, '1 case | 350ml bottled water', 384.00, '48pcs', 'uploads/products/product_686aafbf7572f.jpg', 'container', 'active', '2025-07-06 17:17:51'),
(31, '1 case | 500ml bottled water', 456.00, '48pcs', 'uploads/products/product_686aaffdcae7a.jpg', 'container', 'active', '2025-07-06 17:18:53');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `setting_description`, `updated_at`) VALUES
(1, 'company_name', 'Water Refilling Station', 'Company name displayed throughout the system', '2025-06-16 14:29:18'),
(2, 'contact_email', '', 'Primary contact email for the business', '2025-06-16 14:29:18'),
(3, 'contact_phone', '09927176131', 'Primary contact phone number', '2025-06-29 09:06:56'),
(4, 'business_hours', '8:00 AM - 8:00 PM', 'Regular business hours', '2025-06-16 14:29:18'),
(5, 'minimum_order', '1', 'Minimum quantity per order', '2025-06-16 14:29:18'),
(6, 'delivery_fee', '5', 'Standard delivery fee in pesos', '2025-07-06 16:09:43'),
(7, 'loyalty_points_ratio', '1', 'Points earned per peso spent', '2025-06-16 14:29:18'),
(8, 'maintenance_alert_days', '30', 'Days before maintenance due to show alert', '2025-06-16 14:29:18'),
(9, 'low_stock_threshold', '10', 'Product quantity to trigger low stock alert', '2025-06-16 14:29:18'),
(10, 'max_appointments_per_day', '10', 'Maximum number of appointments allowed per day', '2025-06-16 14:29:18'),
(11, 'sms_notifications', '0', 'Enable SMS notifications (1 for enabled, 0 for disabled)', '2025-06-16 14:29:18'),
(12, 'email_notifications', '1', 'Enable email notifications (1 for enabled, 0 for disabled)', '2025-06-16 14:29:18');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `task_type` enum('maintenance','compliance') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` datetime NOT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`task_id`, `branch_id`, `task_type`, `title`, `description`, `due_date`, `status`, `created_at`) VALUES
(1, 2, 'maintenance', 'test', 'test', '2025-06-17 00:31:00', 'in_progress', '2025-06-16 16:31:04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('customer','branch_admin','super_admin') NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `sitio_purok` varchar(100) DEFAULT NULL,
  `registration_date` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `role`, `branch_id`, `barangay_id`, `name`, `contact_number`, `sitio_purok`, `registration_date`, `last_login`, `status`) VALUES
(1, 'superadmin', '$2y$10$W2lQP2QYa4NCzDnZ4xtBuuDZZPLwJXFP/TWNMtE2stRGdMO/NiD3i', 'superadmin@water.com', 'super_admin', NULL, NULL, 'Super Admin', '09123456789', NULL, '2025-06-16 22:11:50', '2025-08-14 13:16:38', 'active'),
(2, 'Lazareto', '$2y$10$W2lQP2QYa4NCzDnZ4xtBuuDZZPLwJXFP/TWNMtE2stRGdMO/NiD3i', 'branchadmin1@water.com', 'branch_admin', 1, NULL, 'Branch Admin 1', '09123456788', NULL, '2025-06-16 23:03:07', '2025-08-01 20:54:04', 'active'),
(3, 'Calero', '$2y$10$W2lQP2QYa4NCzDnZ4xtBuuDZZPLwJXFP/TWNMtE2stRGdMO/NiD3i', 'branchadmin2@water.com', 'branch_admin', 2, NULL, 'Branch Admin 2', '09123456787', NULL, '2025-06-16 23:04:03', '2025-08-14 13:03:13', 'active'),
(9, 'harvey12', '$2y$10$TVTEkqv6NOMmXYHwdvu/d.SCnc5DkObNnn5r389T2kAwtkEAmpNSO', 'harveypotpottt@gmail.com', 'customer', 1, 30, 'Harvey Manibo', '09071777752', 'Irrigation', '2025-06-29 15:13:17', '2025-06-30 15:13:13', 'active'),
(7, 'marymm', '$2y$10$55FAxiuY9inS3p6/y7UIvepe72ChPy.CM1HAV0hWr3ioE37w8jTyy', 'maryjoymacalindol1209@gmail.com', 'customer', 2, 5, 'Mary Joy Macalindol', '09914113375', 'Centro', '2025-06-29 12:57:56', '2025-07-01 21:18:43', 'active'),
(8, 'Marie', '$2y$10$M.XdHzJzhFMSzd2JzT9FseEAAdPaUDstqWqzBuF49vjmNNo4Kxz6.', 'marie@gmail.com', 'customer', 1, 1, 'Marie Manalo', '09876543211', 'Silangan', '2025-06-29 13:42:30', '2025-06-29 13:42:52', 'active'),
(10, 'anne123', '$2y$10$24TnzsQtnreimT/Nq.hVheiB/U3tsHG9A7Ncjc2AT493ES1NI8AiS', 'anne@gmail.com', 'customer', 1, 20, 'Anne Manalo', '09876543211', 'Centro', '2025-06-30 07:27:17', '2025-06-30 07:27:32', 'active'),
(11, 'mary', '$2y$10$ts.mZ61te5VySC.N4TXqt.LmWvSeKW0/DJ6dtXDcpadk9fZE6bw5u', 'maryjoymacalindol06@gmail.com', 'customer', 1, 19, 'Mary Joy Macalindol', '09914113375', 'Centro', '2025-07-06 23:30:22', '2025-07-07 10:34:33', 'active'),
(12, 'sarahreyes23', '$2y$10$2KpXklH6Wbj/ONgJ1C8Nn./KCOWkKxBNGCy33rx9AynyZPEaO/iS2', 'sarahreyes23@gmail.com', 'customer', 2, 47, 'Sarah Reyes', '09925672570', 'Silangan', '2025-08-01 21:19:20', '2025-08-11 21:44:18', 'active'),
(13, 'johnny47', '$2y$10$cXZHJ9LpTy7gbelwk8efU.q3xxH1PkPNI5JBqkDkDdIItVLpl4hqC', 'johnsantos47@gmail.com', 'customer', 2, 47, 'John Santos', '09183452861', 'Centro', '2025-08-01 21:22:23', '2025-08-11 21:55:33', 'active'),
(14, 'marialopez56', '$2y$10$udR8Y61MSRd9SPAcTWO0z.5T3G1THFAfnOhI27HRks5AYyBpcgLfa', 'marialopez56@gmail.com', 'customer', 2, 4, 'Maria Lopez', '09067824593', 'Centro', '2025-08-11 21:48:42', '2025-08-11 21:49:03', 'active'),
(15, 'gellie', '$2y$10$rqKCGMKsQpoDOm48LKIcbOWxkU45ENHL/GWCDlW.Qza96zWV1kxTS', 'gill@gmail.com', 'customer', 1, 19, 'gellie', '09309195009', 'Cadre', '2025-08-12 10:31:12', '2025-08-12 10:31:28', 'active'),
(16, 'jamescruz82', '$2y$10$wAcnGQdHJchQvZ29wAem6u7bFpqa2gIWWDeBTNBCYPwcaY1v/UUiG', 'jamescruz82@outlook.com', 'customer', 2, 4, 'James Cruz', '09214563728', 'Centr', '2025-08-14 12:51:20', '2025-08-14 12:51:57', 'active'),
(17, 'angelatorres91', '$2y$10$Q.9OC86QP3CTJhcaB59lpOVP55Kvn1plJuemtORl0mY9LKx2KuWcC', 'angelatorres91@gmail.com', 'customer', 2, 4, 'Angela Torres', '09352849016', 'Centro', '2025-08-14 12:53:58', '2025-08-14 12:54:12', 'active'),
(18, 'markvillanueva74', '$2y$10$Cm6thL/IiFwxTgss5yTyaemKUY2QRNrIUo.Ch635Dng.DAKHvbo7W', 'markvillanueva74@yahoo.com', 'customer', 2, 4, 'Mark Villanueva', '09176538490', 'centro', '2025-08-14 12:56:00', '2025-08-14 12:56:12', 'active'),
(19, 'nicoleramos69', '$2y$10$d4047YX.MTyzqmQUHRA91OUsMB93bb/z/g3KaQh.p6tROOUMBfuqG', 'Nicoleramos69@gmail.com', 'customer', 2, 49, 'Nicole Ramos', '09916572834', 'Centro', '2025-08-14 12:58:05', '2025-08-14 12:58:16', 'active'),
(20, 'joshuadelacruz88', '$2y$10$2VNqmUauX.UqJYKcJc2hDO1gp3kwoj.P8oGm48ssshArO/dnQfW4O', 'joshuadelacruz88@outlook.com', 'customer', 2, 51, 'Joshua dela Cruz', '09052394718', 'West', '2025-08-14 13:00:33', '2025-08-14 13:00:47', 'active'),
(21, 'camillemendoza50', '$2y$10$O5eRr1/lH5lqcDpalnXaFOoauRhuVSiwQ6Pfi1rIBWo/xtlFPMK2q', 'camillemendoza50@gmail.com', 'customer', 2, 53, 'Camille Mendoza', '09283674529', 'East', '2025-08-14 13:02:19', '2025-08-14 13:02:28', 'active'),
(22, 'mjmm', '$2y$10$3YC802vvDD34Nrt8twPr4eMdL4Ttriy6JDZ7u4LhL2UjmOFhczgum', 'mary@gmail.com', 'customer', 1, 18, 'Mary MM', '09914223376', 'Centro', '2025-10-19 12:41:26', '2025-10-19 12:41:43', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`barangay_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`branch_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `loyalty`
--
ALTER TABLE `loyalty`
  ADD PRIMARY KEY (`loyalty_id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`);

--
-- Indexes for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`order_detail_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `barangay_id` (`barangay_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=474;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `barangay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `branch_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `loyalty`
--
ALTER TABLE `loyalty`
  MODIFY `loyalty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `order_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
