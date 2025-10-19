-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2025 at 11:50 AM
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
-- Database: `sandokdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`category_id`, `category_name`) VALUES
(5, 'Beef'),
(7, 'Best Sellers'),
(3, 'Chicken'),
(1, 'Pasta'),
(4, 'Pork'),
(6, 'Seafood'),
(2, 'Vegetables');

-- --------------------------------------------------------

--
-- Table structure for table `cateringpackages`
--

CREATE TABLE `cateringpackages` (
  `cp_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cp_name` varchar(255) NOT NULL,
  `cp_email` varchar(255) NOT NULL,
  `cp_phone` varchar(11) NOT NULL,
  `cp_place` varchar(255) NOT NULL,
  `cp_date` date NOT NULL,
  `cp_price` decimal(10,2) NOT NULL,
  `cp_addon_pax` varchar(255) DEFAULT NULL,
  `cp_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cateringpackages`
--

INSERT INTO `cateringpackages` (`cp_id`, `user_id`, `cp_name`, `cp_email`, `cp_phone`, `cp_place`, `cp_date`, `cp_price`, `cp_addon_pax`, `cp_notes`, `created_at`) VALUES
(2, 1, 'John Andal', 'jmbmaines17@gmail.com', '09123123234', 'Purok 2 , Sabang , Lipa City , Batangas', '2025-10-26', 36000.00, '5', '', '2025-10-12 18:03:05'),
(3, 1, 'May Kap', 'jmbmaines17@gmail.com', '09603070809', 'Purok 2 , Sabang , Lipa City , Batangas', '2025-10-28', 37000.00, NULL, '', '2025-10-13 20:33:35'),
(4, 1, 'Juan Dela Cruz', 'jmbmaines17@gmail.com', '09893247892', 'adfsawd , wadssad , wadsd , wadas', '2025-10-30', 35000.00, NULL, '', '2025-10-12 18:57:18');

-- --------------------------------------------------------

--
-- Table structure for table `eventbookings`
--

CREATE TABLE `eventbookings` (
  `eb_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `eb_name` varchar(255) NOT NULL,
  `eb_email` varchar(255) NOT NULL,
  `eb_contact` varchar(255) NOT NULL,
  `eb_venue` varchar(255) NOT NULL,
  `eb_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `eb_order` varchar(100) NOT NULL,
  `eb_status` varchar(100) NOT NULL,
  `eb_addon_pax` varchar(255) DEFAULT NULL,
  `eb_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `eventbookings`
--

INSERT INTO `eventbookings` (`eb_id`, `user_id`, `event_type_id`, `package_id`, `eb_name`, `eb_email`, `eb_contact`, `eb_venue`, `eb_date`, `eb_order`, `eb_status`, `eb_addon_pax`, `eb_notes`, `created_at`) VALUES
(1, 1, 2, 2, 'John Andal', '', '09023347823, 09892374982', 'Villa Antonina , J.P Rizal Street , Poblacion , Padre Garcia , Batangas', '2025-10-12 18:04:26', 'Wedding Package - 100', 'Pending', '0', NULL, '2025-10-12 14:03:50'),
(2, 1, 2, 3, 'Cris Carlo', '', '09278334233, 09892374982', 'Villa Antonina , J.P Rizal Street , Poblacion , Padre Garcia , Batangas', '2025-10-27 05:30:00', 'Corporate - 150', 'Downpayment', '0', NULL, '2025-10-12 14:39:58'),
(3, 1, 2, 1, 'Cris Carlo', 'jmbmaines17@gmail.com', '09023347823, 09892374982', 'Villa Antonina , J.P Rizal Street , Poblacion , Padre Garcia , Batangas', '2025-10-12 18:04:00', 'Standard - 50', 'Confirmed', '10', NULL, '2025-10-12 16:39:59');

-- --------------------------------------------------------

--
-- Table structure for table `event_types`
--

CREATE TABLE `event_types` (
  `event_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `min_package_pax` enum('50','100','150','200') DEFAULT NULL,
  `max_package_pax` enum('50','100','150','200') DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_types`
--

INSERT INTO `event_types` (`event_type_id`, `name`, `min_package_pax`, `max_package_pax`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Wedding', '100', '200', NULL, '2025-10-12 11:36:35', '2025-10-12 11:36:35'),
(2, 'Birthday', '50', '200', NULL, '2025-10-12 12:55:46', '2025-10-12 12:55:46'),
(3, 'Baptism', '50', '200', NULL, '2025-10-15 14:55:29', '2025-10-15 14:55:29'),
(4, 'Anniversaries', '50', '200', NULL, '2025-10-15 14:59:13', '2025-10-15 14:59:13'),
(5, 'Blessings', '50', '200', NULL, '2025-10-15 14:59:28', '2025-10-15 14:59:28'),
(6, 'Gender Reveal', '50', '200', NULL, '2025-10-15 14:59:48', '2025-10-15 14:59:48'),
(7, 'Reunions', '50', '200', NULL, '2025-10-15 15:00:17', '2025-10-15 15:00:17'),
(8, 'Debut (Birthday)', '50', '200', NULL, '2025-10-15 15:03:45', '2025-10-15 15:03:45');

-- --------------------------------------------------------

--
-- Table structure for table `event_type_packages`
--

CREATE TABLE `event_type_packages` (
  `event_type_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_type_packages`
--

INSERT INTO `event_type_packages` (`event_type_id`, `package_id`, `created_at`) VALUES
(1, 2, '2025-10-15 15:00:26'),
(1, 3, '2025-10-15 15:00:26'),
(1, 4, '2025-10-15 15:00:26'),
(2, 1, '2025-10-12 12:55:46'),
(2, 2, '2025-10-12 12:55:46'),
(2, 3, '2025-10-12 12:55:46'),
(3, 1, '2025-10-15 14:55:29'),
(3, 2, '2025-10-15 14:55:29'),
(3, 3, '2025-10-15 14:55:29'),
(3, 4, '2025-10-15 14:55:29'),
(4, 1, '2025-10-15 14:59:13'),
(4, 2, '2025-10-15 14:59:13'),
(4, 3, '2025-10-15 14:59:13'),
(4, 4, '2025-10-15 14:59:13'),
(5, 1, '2025-10-15 14:59:28'),
(5, 2, '2025-10-15 14:59:28'),
(5, 3, '2025-10-15 14:59:28'),
(5, 4, '2025-10-15 14:59:28'),
(6, 1, '2025-10-15 14:59:48'),
(6, 2, '2025-10-15 14:59:48'),
(6, 3, '2025-10-15 14:59:48'),
(6, 4, '2025-10-15 14:59:48'),
(7, 1, '2025-10-15 15:00:17'),
(7, 2, '2025-10-15 15:00:17'),
(7, 3, '2025-10-15 15:00:17'),
(7, 4, '2025-10-15 15:00:17'),
(8, 1, '2025-10-15 15:04:27'),
(8, 2, '2025-10-15 15:04:27'),
(8, 3, '2025-10-15 15:04:27'),
(8, 4, '2025-10-15 15:04:27');

-- --------------------------------------------------------

--
-- Table structure for table `menu`
--

CREATE TABLE `menu` (
  `menu_id` int(11) NOT NULL,
  `menu_name` varchar(100) DEFAULT NULL,
  `menu_desc` text DEFAULT NULL,
  `menu_pax` varchar(100) NOT NULL,
  `menu_price` decimal(10,2) NOT NULL,
  `menu_pic` varchar(255) NOT NULL,
  `menu_avail` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu`
--

INSERT INTO `menu` (`menu_id`, `menu_name`, `menu_desc`, `menu_pax`, `menu_price`, `menu_pic`, `menu_avail`, `created_at`) VALUES
(1, 'Special Pansit', NULL, '10-15 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(2, 'Meaty Spaghetti', NULL, '10-15 pax', 1000.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(3, 'Tuna Carbonara', NULL, '10-15 pax', 1000.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(4, 'Ham/Bacon Carbonara', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(5, 'Tuna Pesto', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(6, 'Special Laing w/ Liempo and Shrimp (Full Pan)', NULL, '10-15 pax', 1500.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(7, 'Special Laing w/ Liempo and Shrimp (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(8, 'Special Chopsuey', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(9, 'Vegetables Kare-kare', NULL, '10-15 pax', 1000.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(10, 'Buttered Mixed Veggies', NULL, '10-15 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(11, 'Special Pakbet', NULL, '10-15 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(12, 'Stirred Veggies w/ Tokwa', NULL, '10-15 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(13, 'Lumpiang Hubad w/ special sauce', NULL, '10-15 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(14, 'Fried Chicken (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(15, 'Fried Chicken (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(16, 'Chicken Afritada (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(17, 'Chicken Afritada (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(18, 'Baked Tahong', '3 kls per pan', '10-15', 1000.00, 'menu_68ee9557244644.46677557.jpg', 1, '2025-06-14 05:05:25'),
(19, 'Butter Shrimp', '1 kl per pan', '10-15 pax', 1300.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(20, 'Shrimp Salvatore', '1 kl per pan', '10-15 pax', 1500.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(21, 'Fish Fillet', '2 kls per pan', '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(22, 'Relyenong Bangus', 'Min. 5 pcs (500g-600g each)', '5 pcs', 285.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(23, 'Seafood Kare-kare', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(24, 'Pork Barbeque', NULL, 'per piece', 35.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(25, 'Chicken Cordon Bleu (Large Pan)', '12 pcs', '12 pcs', 1500.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(26, 'Chicken Cordon Bleu (Medium Pan)', '6 pcs', '6 pcs', 800.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(27, 'Chicken Cordon Bleu (Tub)', '3 pcs', '3 pcs', 360.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(28, 'Pork Shanghai', NULL, '10-15 pax', 600.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(29, 'Special Puto Cheese', NULL, 'per piece', 5.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(30, 'Lumpiang Sariwa', NULL, 'per piece', 40.00, 'default.jpg', 1, '2025-06-14 05:05:25'),
(31, 'Chicken Adobo (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(32, 'Chicken Adobo (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(33, 'Chicken Adobo w/ Liver and Gizzard (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(34, 'Chicken Adobo w/ Liver and Gizzard (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(35, 'Chicken Buffalo (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(36, 'Chicken Buffalo (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(37, 'Orange Chicken (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(38, 'Orange Chicken (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(39, 'Honey Glazed Chicken (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(40, 'Honey Glazed Chicken (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(41, 'Chicken Caldereta (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(42, 'Chicken Caldereta (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(43, 'Ginataang Manok (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(44, 'Ginataang Manok (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(45, 'Tinola (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(46, 'Tinola (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(47, 'Pastel (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(48, 'Pastel (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(49, 'Pininyahang Manok (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(50, 'Pininyahang Manok (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(51, 'Chicken Mushroom Sauce (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(52, 'Chicken Mushroom Sauce (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(53, 'Chicken Barbeque (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(54, 'Chicken Barbeque (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(55, 'Chicken Lollipop (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(56, 'Chicken Lollipop (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(57, 'Honey Butter Glazed Chicken (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(58, 'Honey Butter Glazed Chicken (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(59, 'Paksiw na Lechon Manok (Half Pan)', NULL, '6-8 pax', 700.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(60, 'Paksiw na Lechon Manok (Full Pan)', NULL, '10-15 pax', 1200.00, 'default.jpg', 1, '2025-06-14 05:08:41'),
(61, 'Pork Menudo (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(62, 'Pork Menudo (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(63, 'Pork Afritada (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(64, 'Pork Afritada (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(65, 'Pork Caldereta (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(66, 'Pork Caldereta (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(67, 'Bicol Express (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(68, 'Bicol Express (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(69, 'Pork Binagoongan (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(70, 'Pork Binagoongan (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(71, 'Pork Dinuguan (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(72, 'Pork Dinuguan (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(73, 'Crispy Kare-kare (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(74, 'Crispy Kare-kare (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(75, 'Pata Kare-kare (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(76, 'Pata Kare-kare (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(77, 'Pork Adobo (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(78, 'Pork Adobo (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(79, 'Pork Humba (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(80, 'Pork Humba (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(81, 'Pork Estofado (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(82, 'Pork Estofado (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(83, 'Pork Sinigang (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(84, 'Pork Sinigang (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(85, 'Pork Nilaga (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(86, 'Pork Nilaga (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(87, 'Bbq Spare Ribs (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(88, 'Bbq Spare Ribs (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(89, 'Pork Higado (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(90, 'Pork Higado (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(91, 'Baby Back Ribs (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(92, 'Baby Back Ribs (Full Pan)', '', '10-15', 1400.00, 'menu_68ea6362365639.44210674.jpg', 1, '2025-06-14 05:09:01'),
(93, 'Tokwa\'t Baboy (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(94, 'Tokwa\'t Baboy (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(95, 'Calderobo (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(96, 'Calderobo (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(97, 'Menudillo w/ Quail Egg (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(98, 'Menudillo w/ Quail Egg (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(99, 'Pork and Liver Adobo (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(100, 'Pork and Liver Adobo (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(101, 'Pork Pochero (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(102, 'Pork Pochero (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(103, 'Sweet and Sour Pork (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(104, 'Sweet and Sour Pork (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(105, 'Inihaw na Liempo (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(106, 'Inihaw na Liempo (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(107, 'Pork Steak (Half Pan)', NULL, '6-8 pax', 800.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(108, 'Pork Steak (Full Pan)', NULL, '10-15 pax', 1400.00, 'default.jpg', 1, '2025-06-14 05:09:01'),
(109, 'Beef Broccoli (Half Pan)', NULL, '6-8 pax', 900.00, 'default.jpg', 1, '2025-06-14 05:09:20'),
(110, 'Beef Broccoli (Full Pan)', NULL, '10-15 pax', 1600.00, 'default.jpg', 1, '2025-06-14 05:09:20'),
(111, 'Beef in Mushroom Sauce (Half Pan)', NULL, '6-8 pax', 900.00, 'default.jpg', 1, '2025-06-14 05:09:20'),
(112, 'Beef in Mushroom Sauce (Full Pan)', NULL, '10-15 pax', 1600.00, 'default.jpg', 1, '2025-06-14 05:09:20'),
(113, 'Beef Kare-kare (Half Pan)', NULL, '6-8 pax', 900.00, 'default.jpg', 1, '2025-06-14 05:09:20'),
(114, 'Beef Kare-kare (Full Pan)', NULL, '10-15 pax', 1600.00, 'default.jpg', 1, '2025-06-14 05:09:20'),
(115, 'Beef Caldereta (Half Pan)', NULL, '6-8 pax', 900.00, 'default.jpg', 1, '2025-06-14 05:09:20'),
(116, 'Beef Caldereta (Full Pan)', '', '10-15', 1600.00, '', 1, '2025-06-14 05:09:20'),
(117, 'Beef Sinigang (Half Pan)', '', '6-8', 900.00, '', 1, '2025-06-14 05:09:20'),
(118, 'Beef Sinigang (Full Pan)', '', '10-15', 1600.00, '', 1, '2025-06-14 05:09:20'),
(119, 'Beef Nilaga or Bulalo (Half Pan)', '', '6-8', 900.00, '', 1, '2025-06-14 05:09:20'),
(120, 'Beef Nilaga or Bulalo (Full Pan)', '', '10-15', 1600.00, '', 1, '2025-06-14 05:09:20'),
(121, 'Beef Garlic Pepper Steak (Half Pan)', '', '6-8', 900.00, '', 1, '2025-06-14 05:09:20'),
(122, 'Beef Garlic Pepper Steak (Full Pan)', '', '10-15', 1600.00, '', 1, '2025-06-14 05:09:20'),
(123, 'Beef Steak (Half Pan)', '', '6-8', 900.00, 'menu_68ea1f3828bc97.20447460.jpg', 1, '2025-06-14 05:09:20'),
(124, 'Beef Steak (Full Pan)', '', '10-15', 1600.00, 'menu_68ea1cfa2f7320.44914859.jpg', 1, '2025-06-14 05:09:20');

-- --------------------------------------------------------

--
-- Table structure for table `menucategory`
--

CREATE TABLE `menucategory` (
  `mc_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menucategory`
--

INSERT INTO `menucategory` (`mc_id`, `category_id`, `menu_id`) VALUES
(6, 2, 6),
(7, 2, 7),
(8, 2, 8),
(9, 2, 9),
(10, 2, 10),
(11, 2, 11),
(12, 2, 12),
(13, 2, 13),
(14, 3, 14),
(15, 3, 15),
(16, 3, 16),
(17, 3, 17),
(18, 3, 18),
(19, 3, 19),
(20, 3, 20),
(21, 3, 21),
(22, 3, 22),
(23, 3, 23),
(24, 3, 24),
(25, 3, 25),
(26, 3, 26),
(27, 3, 27),
(28, 3, 28),
(29, 3, 29),
(30, 3, 30),
(31, 4, 31),
(32, 4, 32),
(33, 4, 33),
(34, 4, 34),
(35, 4, 35),
(36, 4, 36),
(37, 4, 37),
(38, 4, 38),
(39, 4, 39),
(40, 4, 40),
(41, 4, 41),
(42, 4, 42),
(43, 4, 43),
(44, 4, 44),
(45, 4, 45),
(46, 4, 46),
(47, 4, 47),
(48, 4, 48),
(49, 4, 49),
(50, 4, 50),
(51, 4, 51),
(52, 4, 52),
(53, 4, 53),
(54, 4, 54),
(63, 6, 63),
(64, 6, 64),
(65, 6, 65),
(66, 6, 66),
(67, 6, 67),
(68, 6, 68),
(69, 7, 69),
(70, 7, 70),
(71, 7, 71),
(72, 7, 72),
(73, 7, 73),
(79, 1, 1),
(80, 1, 2),
(81, 1, 3),
(82, 1, 4),
(83, 1, 5),
(84, 1, 8),
(85, 5, 56),
(86, 5, 55),
(87, 5, 58),
(88, 5, 57),
(89, 5, 60),
(90, 5, 59),
(91, 5, 62),
(92, 5, 61);

-- --------------------------------------------------------

--
-- Table structure for table `orderaddress`
--

CREATE TABLE `orderaddress` (
  `oa_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `oa_street` varchar(100) DEFAULT NULL,
  `oa_city` varchar(100) DEFAULT NULL,
  `oa_province` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orderaddress`
--

INSERT INTO `orderaddress` (`oa_id`, `order_id`, `oa_street`, `oa_city`, `oa_province`, `created_at`) VALUES
(6, 2, '21', 'dsfsdf', 'fdsfesdf', '2025-10-13 18:32:42'),
(7, 3, '21', 'dsfsdf', 'fdsfesdf', '2025-10-13 20:15:39'),
(8, 4, '21', 'dsfsdf', 'fdsfesdf', '2025-10-14 17:06:53');

-- --------------------------------------------------------

--
-- Table structure for table `orderitems`
--

CREATE TABLE `orderitems` (
  `oi_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `oi_quantity` decimal(10,2) DEFAULT NULL,
  `oi_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orderitems`
--

INSERT INTO `orderitems` (`oi_id`, `order_id`, `menu_id`, `oi_quantity`, `oi_price`) VALUES
(3, 2, 18, 2.00, 1000.00),
(4, 3, 18, 1.00, 1000.00),
(5, 3, 91, 1.00, 800.00),
(6, 4, 91, 1.00, 800.00),
(7, 4, 92, 1.00, 1400.00);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `order_status` enum('pending','in progress','completed','canceled') DEFAULT NULL,
  `order_amount` decimal(10,2) NOT NULL,
  `order_needed` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `order_date`, `order_status`, `order_amount`, `order_needed`, `created_at`, `updated_at`) VALUES
(2, 1, '2025-10-13 18:32:42', 'completed', 2000.00, '2025-10-16', '2025-10-13 18:32:42', '2025-10-13 18:33:17'),
(3, 1, '2025-10-13 20:15:39', 'in progress', 1800.00, '2025-10-16', '2025-10-13 20:15:39', '2025-10-13 20:27:02'),
(4, 1, '2025-10-14 17:06:53', 'pending', 2200.00, '2025-10-17', '2025-10-14 17:06:53', '2025-10-14 17:06:53');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `package_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `pax` enum('50','100','150','200') NOT NULL,
  `base_price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,
  `package_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`package_id`, `name`, `pax`, `base_price`, `is_active`, `notes`, `package_image`, `created_at`, `updated_at`) VALUES
(1, 'Standard', '50', 35000.00, 1, '', 'uploads/packages/package_1.jpg', '2025-10-09 17:43:42', '2025-10-11 11:00:23'),
(2, 'Deluxe', '100', 55000.00, 1, '', 'uploads/packages/package_2.jpg', '2025-10-09 18:03:25', '2025-10-15 14:56:08'),
(3, 'Corporate', '150', 78000.00, 1, '', 'uploads/packages/package_3.jpg', '2025-10-09 18:04:56', '2025-10-15 08:02:58'),
(4, 'Supreme', '200', 99000.00, 1, '', 'uploads/packages/package_4.png', '2025-10-15 08:10:20', '2025-10-15 08:10:20');

-- --------------------------------------------------------

--
-- Table structure for table `package_items`
--

CREATE TABLE `package_items` (
  `item_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `item_label` varchar(255) NOT NULL,
  `qty` int(11) DEFAULT NULL,
  `unit` enum('pax','pcs','cups','attendants','dish','other') DEFAULT 'other',
  `is_optional` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `item_pic` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `package_items`
--

INSERT INTO `package_items` (`item_id`, `package_id`, `item_label`, `qty`, `unit`, `is_optional`, `sort_order`, `item_pic`, `created_at`, `updated_at`) VALUES
(153, 1, 'Beef Menu', NULL, 'dish', 0, 0, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(154, 1, 'Pork Menu', NULL, 'dish', 0, 1, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(155, 1, 'Chicken Menu', NULL, 'dish', 0, 2, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(156, 1, 'Rice', NULL, 'dish', 0, 3, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(157, 1, 'Veggies/Pasta/Fish Fillet(choose 1)', NULL, 'dish', 0, 4, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(158, 1, '50 Cups of Desserts', 50, 'cups', 0, 5, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(159, 1, 'Backdrop & Platform/ Complete Setup', NULL, 'other', 0, 6, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(160, 1, 'Elegant Table Buffet', NULL, 'other', 0, 7, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(161, 1, '6 Chaffing Dish', 6, 'other', 0, 8, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(162, 1, 'Banquet Complete Setup', NULL, 'other', 0, 9, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(163, 1, 'Tables and Chairs with cover', NULL, 'other', 0, 10, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(164, 1, 'Artificial Flowers and Balloons for Decoration', NULL, 'other', 0, 11, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(165, 1, '60 Pax Silverware and Dinner ware', 60, 'pcs', 0, 12, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(166, 1, '2 Food Attendants', 2, 'attendants', 0, 13, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(167, 1, 'Elegant Table Buffet', NULL, 'other', 0, 14, '', '2025-10-11 12:33:52', '2025-10-11 12:33:52'),
(214, 4, 'Beef Menu', NULL, 'dish', 0, 0, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(215, 4, 'Pork Menu', NULL, 'dish', 0, 1, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(216, 4, 'Chicken Menu', NULL, 'dish', 0, 2, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(217, 4, 'Rice', NULL, 'dish', 0, 3, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(218, 4, 'Veggies/Pasta/Fish Fillet', NULL, 'dish', 0, 4, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(219, 4, '200 cups of Desserts', 200, 'cups', 0, 5, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(220, 4, 'Drinks', NULL, 'dish', 0, 6, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(221, 4, 'Backdrop and Platform/Complete Setup', NULL, 'other', 0, 7, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(222, 4, 'Table Buffet w/ Skirting Setup', NULL, 'other', 0, 8, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(223, 4, '7 Chaffing Dish w/ Food Heat Lamp', 7, 'pcs', 0, 9, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(224, 4, 'Cake and Gift Table w/ Skirting Designs', NULL, 'other', 0, 10, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(225, 4, 'Chairs w/ cover', NULL, 'other', 0, 11, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(226, 4, 'Table w/ cover', NULL, 'other', 0, 12, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(227, 4, '200 pax Silverware, Glassware, and Dinnerware', 200, 'pax', 0, 13, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(228, 4, '200pcs Serving Spoons', 200, 'pcs', 0, 14, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(229, 4, '8 Food Attendants', 8, 'attendants', 0, 15, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(230, 4, 'Elegant Table Buffet', NULL, 'other', 0, 16, '', '2025-10-15 08:27:55', '2025-10-15 08:27:55'),
(231, 2, 'Beef Menu', NULL, 'dish', 0, 0, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(232, 2, 'Pork Menu', NULL, 'dish', 0, 1, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(233, 2, 'Chicken Menu', NULL, 'dish', 0, 2, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(234, 2, 'Rice', NULL, 'dish', 0, 3, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(235, 2, 'Veggies/Pasta/Fish Fillet', NULL, 'dish', 0, 4, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(236, 2, '100 Cups of Dessert', NULL, 'cups', 0, 5, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(237, 2, 'Drinks', NULL, 'dish', 0, 6, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(238, 2, 'Backdrop & Platform/ Complete Setup', NULL, 'other', 0, 7, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(239, 2, 'Table Buffet w/ Skirting Setup', NULL, 'other', 0, 8, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(240, 2, '7 Chaffing Dish w/ Food Heat Lamp', NULL, 'pcs', 0, 9, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(241, 2, 'Cake & Gift Table w/ Skirting Designs', NULL, 'other', 0, 10, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(242, 2, 'Chairs w/ Cover', NULL, 'other', 0, 11, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(243, 2, 'Tables w/ cover', NULL, 'other', 0, 12, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(244, 2, '100 PAX Silverware, Glassware, Dinner ware', NULL, 'pax', 0, 13, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(245, 2, '100pcs Serving Spoon', NULL, 'pcs', 0, 14, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(246, 2, '4 Food Attendants', NULL, 'attendants', 0, 15, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(247, 2, 'Elegant Table Buffet', NULL, 'other', 0, 16, '', '2025-10-15 14:56:08', '2025-10-15 14:56:08'),
(248, 3, 'Beef Menu', NULL, 'dish', 0, 0, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(249, 3, 'Chicken Menu', NULL, 'dish', 0, 1, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(250, 3, 'Pork Menu', NULL, 'dish', 0, 2, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(251, 3, 'Veggies/Pasta/Fish Fillet', NULL, 'dish', 0, 3, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(252, 3, '100 Cups of Desserts', 100, 'cups', 0, 4, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(253, 3, 'Drinks', NULL, 'dish', 0, 5, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(254, 3, 'Backdrop and Platform / Complete Setup', NULL, 'other', 0, 6, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(255, 3, 'Table Buffet w/ Skirting Setup', NULL, 'other', 0, 7, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(256, 3, '7 Chaffing Dish w/ Food Heat Lamp', 7, 'pcs', 0, 8, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(257, 3, 'Cake and Gift Table w/ Skirting Designs', NULL, 'other', 0, 9, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(258, 3, 'Chairs w/ cover', NULL, 'other', 0, 10, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(259, 3, 'Tables w/ cover', NULL, 'other', 0, 11, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(260, 3, '150 Pax Silverware, Glassware, and Dinner ware', 150, 'pax', 0, 12, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(261, 3, '150pcs Serving spoons', 150, 'pcs', 0, 13, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(262, 3, '6 Food Attendants', 6, 'attendants', 0, 14, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(263, 3, 'Elegant Table Buffet', NULL, 'other', 0, 15, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07'),
(264, 3, 'Rice', NULL, 'other', 0, 3, '', '2025-10-15 14:57:07', '2025-10-15 14:57:07');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `pay_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `cp_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `pay_date` date DEFAULT NULL,
  `pay_amount` decimal(10,2) DEFAULT NULL,
  `pay_method` enum('Cash','Gcash','Card','Paypal','Paymaya') DEFAULT NULL,
  `pay_status` enum('Paid','Partial','Pending') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`pay_id`, `order_id`, `cp_id`, `user_id`, `pay_date`, `pay_amount`, `pay_method`, `pay_status`, `created_at`) VALUES
(1, NULL, 2, 1, '2025-10-15', 36000.00, 'Cash', 'Paid', '2025-10-15 14:39:10'),
(2, NULL, 3, 1, '2025-10-15', 37000.00, 'Cash', 'Paid', '2025-10-15 14:26:48'),
(3, NULL, 4, 1, '2025-10-13', 35000.00, 'Cash', 'Paid', '2025-10-12 18:57:38'),
(4, 2, NULL, 1, '2025-10-14', 2000.00, 'Cash', 'Paid', '2025-10-13 18:33:17'),
(5, 3, NULL, 1, '2025-10-13', 1800.00, 'Gcash', 'Pending', '2025-10-13 20:15:39'),
(6, 4, NULL, 1, '2025-10-14', 2200.00, 'Cash', 'Pending', '2025-10-14 17:06:53');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('collections_images', '[\"uploads/collections/collections_1760473094_0.jpg\",\"uploads/collections/collections_1760473048_0.jpg\"]', '2025-10-14 20:18:14'),
('menu_section_images', '[\"uploads/menu_hero/menu_hero_1760471445_0.jpg\",\"uploads/menu_hero/menu_hero_1760470768_0.jpg\",\"/../uploads/menu_hero/menu_hero_1760470466_0.jpg\"]', '2025-10-14 19:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `user_fn` varchar(255) NOT NULL,
  `user_ln` varchar(255) NOT NULL,
  `user_sex` varchar(6) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `user_phone` varchar(255) NOT NULL,
  `user_username` varchar(255) NOT NULL,
  `user_password` varchar(100) NOT NULL,
  `user_photo` varchar(255) DEFAULT NULL,
  `user_type` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_fn`, `user_ln`, `user_sex`, `user_email`, `user_phone`, `user_username`, `user_password`, `user_photo`, `user_type`, `created_at`, `updated_at`) VALUES
(1, 'John', 'Andal', 'Male', 'jmbmaines17@gmail.com', '09267834238', 'JAB12', '$2y$10$HT.CBUI4fJjKc6Hk24Cic.pDMPhn88A/uImC4Tz/BBe4fRx1fksE.', NULL, 0, '2025-10-12 04:33:59', '2025-10-18 06:32:00'),
(2, 'Sandok', 'Binggay', 'Male', 'sandokdummy@gmail.com', '09238914072', 'sdby', '$2y$10$AGOejyyIW9SoFCyFy/n98u8d9B.VJt6N5uQr09JxCAIvIZolvxU0u', NULL, 1, '2025-10-12 19:10:02', '2025-10-12 19:10:17'),
(3, 'Ace', 'Randolf', 'Male', 'phenominalgamer19@gmail.com', '09238947239', 'AcB22', '$2y$10$ru9qHYNNYAJE6xbeOjMVvODQXOLnTLM54DxFYcGeestXJO7sDV8Se', '../uploads/profile/68f334f35a59b1.27722469.jpg', 0, '2025-10-18 06:34:27', '2025-10-18 07:12:49');

-- --------------------------------------------------------

--
-- Table structure for table `user_cart_items`
--

CREATE TABLE `user_cart_items` (
  `user_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uk_category_name` (`category_name`);

--
-- Indexes for table `cateringpackages`
--
ALTER TABLE `cateringpackages`
  ADD PRIMARY KEY (`cp_id`),
  ADD KEY `idx_cateringpackages_user_id` (`user_id`);

--
-- Indexes for table `eventbookings`
--
ALTER TABLE `eventbookings`
  ADD PRIMARY KEY (`eb_id`),
  ADD KEY `idx_eventbookings_user_id` (`user_id`),
  ADD KEY `idx_eventbookings_event_type` (`event_type_id`),
  ADD KEY `idx_eventbookings_package` (`package_id`),
  ADD KEY `fk_eventbookings_allowed_package` (`event_type_id`,`package_id`);

--
-- Indexes for table `event_types`
--
ALTER TABLE `event_types`
  ADD PRIMARY KEY (`event_type_id`),
  ADD UNIQUE KEY `uk_event_types_name` (`name`);

--
-- Indexes for table `event_type_packages`
--
ALTER TABLE `event_type_packages`
  ADD PRIMARY KEY (`event_type_id`,`package_id`),
  ADD KEY `fk_etp_package` (`package_id`);

--
-- Indexes for table `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`menu_id`);

--
-- Indexes for table `menucategory`
--
ALTER TABLE `menucategory`
  ADD PRIMARY KEY (`mc_id`),
  ADD KEY `idx_mc_category_id` (`category_id`),
  ADD KEY `idx_mc_menu_id` (`menu_id`);

--
-- Indexes for table `orderaddress`
--
ALTER TABLE `orderaddress`
  ADD PRIMARY KEY (`oa_id`),
  ADD UNIQUE KEY `uk_oa_order_id` (`order_id`);

--
-- Indexes for table `orderitems`
--
ALTER TABLE `orderitems`
  ADD PRIMARY KEY (`oi_id`),
  ADD KEY `idx_oi_order_id` (`order_id`),
  ADD KEY `idx_oi_menu_id` (`menu_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_orders_user_id` (`user_id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`package_id`),
  ADD UNIQUE KEY `uk_packages_name_pax` (`name`,`pax`),
  ADD KEY `idx_packages_pax` (`pax`);

--
-- Indexes for table `package_items`
--
ALTER TABLE `package_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `idx_package_items_package_id` (`package_id`,`sort_order`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`pay_id`),
  ADD UNIQUE KEY `uk_payments_order_id` (`order_id`),
  ADD KEY `idx_payments_cp_id` (`cp_id`),
  ADD KEY `idx_payments_user_id` (`user_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_users_username` (`user_username`);

--
-- Indexes for table `user_cart_items`
--
ALTER TABLE `user_cart_items`
  ADD PRIMARY KEY (`user_id`,`menu_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `category`
--
ALTER TABLE `category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `cateringpackages`
--
ALTER TABLE `cateringpackages`
  MODIFY `cp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `eventbookings`
--
ALTER TABLE `eventbookings`
  MODIFY `eb_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_types`
--
ALTER TABLE `event_types`
  MODIFY `event_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `menu`
--
ALTER TABLE `menu`
  MODIFY `menu_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=125;

--
-- AUTO_INCREMENT for table `menucategory`
--
ALTER TABLE `menucategory`
  MODIFY `mc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `orderaddress`
--
ALTER TABLE `orderaddress`
  MODIFY `oa_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `orderitems`
--
ALTER TABLE `orderitems`
  MODIFY `oi_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `package_items`
--
ALTER TABLE `package_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=265;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `pay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cateringpackages`
--
ALTER TABLE `cateringpackages`
  ADD CONSTRAINT `fk_cateringpackages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `eventbookings`
--
ALTER TABLE `eventbookings`
  ADD CONSTRAINT `fk_eventbookings_allowed_package` FOREIGN KEY (`event_type_id`,`package_id`) REFERENCES `event_type_packages` (`event_type_id`, `package_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_eventbookings_event_type` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`event_type_id`),
  ADD CONSTRAINT `fk_eventbookings_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`),
  ADD CONSTRAINT `fk_eventbookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `event_type_packages`
--
ALTER TABLE `event_type_packages`
  ADD CONSTRAINT `fk_etp_event_type` FOREIGN KEY (`event_type_id`) REFERENCES `event_types` (`event_type_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_etp_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON DELETE CASCADE;

--
-- Constraints for table `menucategory`
--
ALTER TABLE `menucategory`
  ADD CONSTRAINT `fk_mc_category` FOREIGN KEY (`category_id`) REFERENCES `category` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mc_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`) ON DELETE CASCADE;

--
-- Constraints for table `orderaddress`
--
ALTER TABLE `orderaddress`
  ADD CONSTRAINT `fk_oa_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `orderitems`
--
ALTER TABLE `orderitems`
  ADD CONSTRAINT `fk_oi_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `package_items`
--
ALTER TABLE `package_items`
  ADD CONSTRAINT `fk_package_items_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_cp` FOREIGN KEY (`cp_id`) REFERENCES `cateringpackages` (`cp_id`),
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
