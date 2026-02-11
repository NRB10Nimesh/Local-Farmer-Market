-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 11, 2026 at 06:58 PM
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
-- Database: `farmer_market`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `populate_admin_revenue` ()   BEGIN
    -- Populate revenue for all completed orders that aren't already tracked
    INSERT INTO admin_revenue (order_id, order_detail_id, product_id, quantity, farmer_price, admin_price, profit_amount)
    SELECT 
        o.order_id,
        od.order_detail_id,
        od.product_id,
        od.quantity,
        od.farmer_price,
        od.admin_price,
        (od.admin_price - od.farmer_price) * od.quantity as profit_amount
    FROM orders o
    INNER JOIN order_details od ON o.order_id = od.order_id
    WHERE o.status = 'Completed'
    AND od.profit_per_unit > 0
    AND NOT EXISTS (
        SELECT 1 FROM admin_revenue ar 
        WHERE ar.order_detail_id = od.order_detail_id
    );
    
    SELECT ROW_COUNT() as records_inserted;
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `calculate_potential_profit` (`product_id_param` INT) RETURNS DECIMAL(10,2) DETERMINISTIC BEGIN
    DECLARE potential_profit DECIMAL(10,2);
    
    SELECT (admin_price - price) * quantity INTO potential_profit
    FROM products
    WHERE product_id = product_id_param
    AND approval_status = 'approved'
    AND admin_price IS NOT NULL;
    
    RETURN IFNULL(potential_profit, 0);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `login_attempts` int(11) NOT NULL DEFAULT 0,
  `last_login_attempt` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`admin_id`, `username`, `password_hash`, `email`, `full_name`, `is_active`, `login_attempts`, `last_login_attempt`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$WI9Lg8omPqKXdOnMP0Vdu.8Dr4/WpE1TPQOLK8v3AZV0GgERF4Zsu', 'admin@farmermarket.com', 'Administrator', 1, 0, NULL, '2026-02-11 22:27:51', '2026-01-06 04:18:10');

-- --------------------------------------------------------

--
-- Stand-in structure for view `admin_dashboard_summary`
-- (See below for the actual view)
--
CREATE TABLE `admin_dashboard_summary` (
`pending_products` bigint(21)
,`approved_products` bigint(21)
,`low_stock_products` bigint(21)
,`pending_orders` bigint(21)
,`completed_orders` bigint(21)
,`total_profit` decimal(32,2)
,`today_profit` decimal(32,2)
,`month_profit` decimal(32,2)
,`potential_profit` decimal(43,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `admin_profit_summary`
-- (See below for the actual view)
--
CREATE TABLE `admin_profit_summary` (
`date` date
,`orders_count` bigint(21)
,`items_sold` decimal(32,0)
,`total_profit` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `admin_revenue`
--

CREATE TABLE `admin_revenue` (
  `revenue_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_detail_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `farmer_price` decimal(10,2) NOT NULL,
  `admin_price` decimal(10,2) NOT NULL,
  `profit_per_unit` decimal(10,2) DEFAULT 0.00,
  `profit_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `commission_rate` decimal(5,2) NOT NULL COMMENT 'Commission % applied',
  `commission_per_unit` decimal(10,2) DEFAULT 0.00,
  `commission_amount` decimal(10,2) NOT NULL COMMENT 'Actual commission earned',
  `farmer_final_amount` decimal(10,2) NOT NULL COMMENT 'Amount farmer receives after commission'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_revenue`
--

INSERT INTO `admin_revenue` (`revenue_id`, `order_id`, `order_detail_id`, `product_id`, `quantity`, `farmer_price`, `admin_price`, `profit_per_unit`, `profit_amount`, `created_at`, `commission_rate`, `commission_per_unit`, `commission_amount`, `farmer_final_amount`) VALUES
(4, 55, 0, 19, 2, 200.00, 214.00, 0.00, 28.00, '2026-02-08 11:34:16', 0.00, 0.00, 0.00, 0.00),
(6, 55, 58, 19, 2, 200.00, 214.00, 0.00, 28.00, '2026-02-11 09:37:40', 0.00, 0.00, 0.00, 0.00),
(7, 60, 64, 24, 10, 25.00, 26.55, 1.55, 15.50, '2026-02-11 15:46:07', 6.20, 0.00, 16.46, 249.04),
(8, 61, 65, 19, 2, 200.00, 214.00, 14.00, 28.00, '2026-02-11 16:15:35', 7.00, 0.00, 29.96, 398.04);

-- --------------------------------------------------------

--
-- Table structure for table `buyer`
--

CREATE TABLE `buyer` (
  `buyer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact` varchar(15) NOT NULL,
  `address` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buyer`
--

INSERT INTO `buyer` (`buyer_id`, `name`, `contact`, `address`, `password`, `created_at`, `is_active`) VALUES
(4, 'albert Raii', '9866091917', 'lalitpur-nepal', '$2y$10$I.wVbNFqZ3xHk9L67BLGRuAs7o21Oee6tKur4ssS8iPBsHS5S45FO', '2025-12-20 14:43:38', 0),
(5, 'albert Raiiii', '9866091918', 'lalitpur-nepal', '$2y$10$Yli/b7JmLcj2Z4tvc06Od.Qa6f.DUf2hzK6OmiaFq0grgykDeTkx.', '2025-12-20 15:16:33', 1),
(7, 'Nimesh pokharel', '9800887555', 'nakhhu-karagar', '$2y$10$ZgTZ7C0aB2yKEIgo6rLeB.ADC6C6e2rJAAnRBgHlXz0hA0kTGSJtC', '2026-01-03 14:29:57', 1),
(8, 'retry', '9866091234', 'lalitpur-nepal', '$2y$10$5coXx388rXsYvu9MhyryfOYJIyhL98ISefaqwq./vujldwS9nfvXq', '2026-01-06 13:39:46', 1),
(9, 'Nimesh Ranabhattt', '9866091565', 'lalitpur-nepal', '$2y$10$qoIvLjxURcTgpiOVyIjcoO3UNFxRuflWmnpWcT1WBHBA/kW6AjlNK', '2026-01-18 14:39:08', 1),
(10, 'Anish shahi', '9857457644', 'bolache-bhaktapur', '$2y$10$CBPzhdk4VMI8BUv3UNCSievSMDxzkbykZ.JPXW9hirRe03VQkRitW', '2026-02-09 12:38:26', 1),
(11, 'Nimesh Pokharell', '9866078898', 'Kaushaltar-uskomanma', '$2y$10$4px5CStjlsZpkJm7Vyps4.Cdn9/4dUp/ZiSBBAAWCrkwBZvQ/xmW6', '2026-02-11 15:37:14', 1);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL CHECK (`quantity` > 0),
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`, `description`, `created_at`) VALUES
(1, 'fruits', NULL, '2026-01-06 04:31:52'),
(2, 'poultry', NULL, '2026-01-06 04:31:52'),
(3, 'grains', NULL, '2026-01-06 04:31:52'),
(4, 'Other', NULL, '2026-01-06 04:31:52');

-- --------------------------------------------------------

--
-- Stand-in structure for view `commission_analytics`
-- (See below for the actual view)
--
CREATE TABLE `commission_analytics` (
`category` varchar(100)
,`total_products` bigint(21)
,`avg_commission_rate` decimal(9,6)
,`total_commission_earned` decimal(32,2)
,`total_farmer_payout` decimal(32,2)
,`total_orders` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `commission_settings`
--

CREATE TABLE `commission_settings` (
  `setting_id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `default_commission_rate` decimal(5,2) NOT NULL DEFAULT 5.00,
  `min_rate` decimal(5,2) NOT NULL DEFAULT 5.00,
  `max_rate` decimal(5,2) NOT NULL DEFAULT 10.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `commission_settings`
--

INSERT INTO `commission_settings` (`setting_id`, `category`, `default_commission_rate`, `min_rate`, `max_rate`, `created_at`, `updated_at`) VALUES
(1, 'Vegetables', 5.00, 5.00, 10.00, '2026-01-16 05:52:15', '2026-01-16 05:52:15'),
(2, 'Fruits', 6.80, 5.00, 10.00, '2026-01-16 05:52:15', '2026-02-11 13:03:36'),
(3, 'Grains', 5.00, 5.00, 10.00, '2026-01-16 05:52:15', '2026-01-16 05:52:15'),
(4, 'Dairy', 7.00, 5.00, 10.00, '2026-01-16 05:52:15', '2026-01-16 05:52:15'),
(5, 'Meat', 8.00, 5.00, 10.00, '2026-01-16 05:52:15', '2026-01-16 05:52:15'),
(6, 'Eggs', 5.00, 5.00, 10.00, '2026-01-16 05:52:15', '2026-01-16 06:38:11'),
(7, 'Spices', 7.00, 5.00, 10.00, '2026-01-16 05:52:15', '2026-01-16 05:52:15'),
(8, 'Herbs', 6.00, 5.00, 10.00, '2026-01-16 05:52:15', '2026-01-16 05:52:15');

-- --------------------------------------------------------

--
-- Table structure for table `farmer`
--

CREATE TABLE `farmer` (
  `farmer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact` varchar(15) NOT NULL,
  `address` varchar(255) NOT NULL,
  `farm_type` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `farmer`
--

INSERT INTO `farmer` (`farmer_id`, `name`, `contact`, `address`, `farm_type`, `password`, `created_at`, `is_active`) VALUES
(9, 'gaurav', '9848393939', 'lalitpur-nepal', 'fruits.fd', '$2y$10$V5IObo//mSJWalYtfe4vLeoFJ6O19NggJVG.Ynanh3ZgOPtRgdjX2', '2025-12-15 13:30:55', 1),
(10, 'nimesh rana', '9866091917', 'lalitpur-nepal', 'MIxed', '$2y$10$yYtwvGXbxBgZQHA74TkAvOsDH0gnIIjud5h1lDWTK9E5UnzWFSgx6', '2025-12-19 15:11:25', 1),
(11, 'narin', '9876548459', 'nepal-nepal', 'fruits', '$2y$10$sXS0ePtl1.CjPVl9FZYE5uZIXduVwNB1sH8dYrXXPvlRM3y9H8Kiy', '2025-12-22 14:13:18', 1),
(14, 'Nimesh Ranabhat', '9866091932', 'lalitpur-nepal', 'vegetable', '$2y$10$gqdVFQXxrlSPIervBCrufuFcncggyIHyozOlsF.NN78VJLoqPNhie', '2026-01-06 13:35:15', 1),
(15, 'GauravG', '9687569875', 'ekantakuna-lalitpur', 'Poultry', '$2y$10$bjmdbz9I7vdqQa9KNR989OaHCk5uEHo7.88O.SQDjIy107SBi2XJa', '2026-02-11 15:24:35', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_type` enum('farmer','buyer') NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_type`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 'buyer', 4, 'Order Update', 'Your order #17 status changed to: Confirmed', 0, '2025-12-30 15:12:44'),
(2, 'buyer', 4, 'Order Update', 'Your order #16 status changed to: Confirmed', 0, '2025-12-30 15:12:56'),
(3, 'buyer', 4, 'Order Update', 'Your order #15 status changed to: Confirmed', 0, '2025-12-30 15:12:58'),
(4, 'buyer', 5, 'Order Update', 'Your order #12 status changed to: Confirmed', 0, '2025-12-30 15:13:02'),
(5, 'buyer', 5, 'Order Update', 'Your order #11 status changed to: Confirmed', 0, '2025-12-30 15:13:05'),
(6, 'farmer', 10, 'New Order', 'New order #21 received for apple', 0, '2025-12-30 15:23:03'),
(7, 'farmer', 10, 'New Order', 'New order #22 received for apple', 0, '2025-12-30 15:24:00'),
(8, 'buyer', 4, 'Order Update', 'Your order #22 status changed to: Confirmed', 0, '2025-12-30 15:24:30'),
(9, 'buyer', 4, 'Order Update', 'Your order #21 status changed to: Confirmed', 0, '2025-12-30 15:24:33'),
(10, 'buyer', 4, 'Order Update', 'Your order #19 status changed to: Confirmed', 0, '2025-12-30 15:24:36'),
(11, 'buyer', 4, 'Order Update', 'Your order #18 status changed to: Confirmed', 0, '2025-12-30 15:24:39'),
(12, 'buyer', 5, 'Order Update', 'Your order #10 status changed to: Confirmed', 0, '2025-12-30 15:24:46'),
(13, 'buyer', 4, 'Order Update', 'Your order #9 status changed to: Confirmed', 0, '2025-12-30 15:24:52'),
(14, 'buyer', 4, 'Order Update', 'Your order #7 status changed to: Confirmed', 0, '2025-12-30 15:24:55'),
(15, 'buyer', 4, 'Order Update', 'Your order #8 status changed to: Confirmed', 0, '2025-12-30 15:25:15'),
(16, 'buyer', 4, 'Order Update', 'Your order #6 status changed to: Confirmed', 0, '2025-12-30 15:25:20'),
(17, 'farmer', 10, 'New Order', 'New order #23 received for apple', 0, '2025-12-30 15:26:15'),
(18, 'buyer', 4, 'Order Update', 'Your order #23 status changed to: Cancelled', 0, '2025-12-30 15:26:44'),
(19, 'farmer', 10, 'New Order', 'New order #24 received for apple', 0, '2025-12-31 14:22:39'),
(20, 'buyer', 4, 'Order Update', 'Your order #24 status changed to: Confirmed', 0, '2025-12-31 14:23:18'),
(21, 'farmer', 11, 'New Order', 'New order #26 received for eggs', 0, '2025-12-31 14:24:13'),
(22, 'farmer', 10, 'New Order', 'New order #27 received for eggs', 0, '2025-12-31 14:42:59'),
(23, 'buyer', 4, 'Order Update', 'Your order #27 status changed to: Confirmed', 0, '2025-12-31 14:44:00'),
(24, 'farmer', 10, 'New Order', 'New order #28 received for apple', 0, '2025-12-31 14:46:38'),
(25, 'farmer', 10, 'New Order', 'New order #29 received for apple', 0, '2025-12-31 14:56:59'),
(26, 'farmer', 10, 'New Order', 'New order #30 received for apple', 0, '2025-12-31 15:11:20'),
(27, 'farmer', 11, 'New Order', 'New order #30 received for eggs', 0, '2025-12-31 15:11:20'),
(28, 'buyer', 4, 'Order Update', 'Your order #30 status changed to: Confirmed', 0, '2025-12-31 15:23:41'),
(29, 'buyer', 4, 'Order Update', 'Your order #29 status changed to: Confirmed', 0, '2025-12-31 15:23:44'),
(30, 'farmer', 10, 'New Order', 'New order #31 received for apple', 0, '2026-01-01 10:38:17'),
(31, 'farmer', 11, 'New Order', 'New order #32 received for eggs', 0, '2026-01-01 11:38:27'),
(32, 'farmer', 10, 'New Order', 'New order #33 received for apple', 0, '2026-01-01 11:40:00'),
(33, 'farmer', 10, 'New Order', 'New order #34 received for apple', 0, '2026-01-01 11:49:36'),
(34, 'farmer', 10, 'New Order', 'New order #35 received for apple', 0, '2026-01-01 12:09:33'),
(35, 'farmer', 10, 'New Order', 'New order #36 received for eggs', 0, '2026-01-03 14:27:21'),
(36, 'farmer', 11, 'New Order', 'New order #37 received for eggs', 0, '2026-01-03 14:30:44'),
(37, 'farmer', 11, 'New Order', 'New order #38 received for eggs', 0, '2026-01-06 08:42:52'),
(38, 'farmer', 11, 'New Order', 'New order #39 received for eggs', 0, '2026-01-06 13:40:23'),
(39, 'farmer', 10, 'New Order', 'New order #40 received for apple', 0, '2026-01-06 13:47:04'),
(40, 'farmer', 11, 'New Order', 'New order #41 received for eggs', 0, '2026-01-07 03:57:44'),
(41, 'farmer', 11, 'New Order', 'New order #42 received for eggs', 0, '2026-01-07 04:03:06'),
(42, 'farmer', 11, 'New Order', 'New order #43 received for eggs', 0, '2026-01-07 04:08:10'),
(43, 'farmer', 10, 'New Order', 'New order #44 received for maze', 0, '2026-01-07 04:12:24'),
(44, 'buyer', 4, 'Order Update', 'Your order #44 status changed to: Completed', 0, '2026-01-07 10:05:09'),
(45, 'buyer', 4, 'Order Update', 'Your order #33 status changed to: Completed', 0, '2026-01-07 10:05:36'),
(46, 'buyer', 8, 'Order Update', 'Your order #40 status changed to: Processing', 0, '2026-01-07 10:05:41'),
(47, 'farmer', 10, 'New Order', 'New order #45 received for apple', 0, '2026-01-07 11:18:01'),
(48, 'farmer', 10, 'New Order', 'New order #45 received for maze', 0, '2026-01-07 11:18:01'),
(49, 'farmer', 11, 'New Order', 'New order #45 received for eggs', 0, '2026-01-07 11:18:01'),
(50, 'farmer', 11, 'New Order', 'New order #45 received for eggs', 0, '2026-01-07 11:18:01'),
(51, 'buyer', 4, 'Order Update', 'Your order #45 status changed to: Pending', 0, '2026-01-07 15:43:05'),
(52, 'buyer', 8, 'Order Update', 'Your order #40 status changed to: Processing', 0, '2026-01-07 15:43:07'),
(53, 'buyer', 8, 'Order Update', 'Your order #40 status changed to: Processing', 0, '2026-01-07 15:43:09'),
(54, 'buyer', 4, 'Order Update', 'Your order #35 status changed to: Pending', 0, '2026-01-07 15:43:11'),
(55, 'buyer', 4, 'Order Update', 'Your order #45 status changed to: Pending', 0, '2026-01-07 15:45:42'),
(56, 'buyer', 4, 'Order Update', 'Your order #45 status changed to: Cancelled', 0, '2026-01-07 15:45:51'),
(57, 'farmer', 11, 'New Order', 'New order #46 received for eggs', 0, '2026-01-09 08:00:26'),
(58, 'buyer', 8, 'Order Update', 'Your order #46 status changed to: Completed', 0, '2026-01-09 08:01:51'),
(59, 'farmer', 10, 'New Order', 'New order #47 received for apple', 0, '2026-01-10 05:59:06'),
(60, 'farmer', 10, 'New Order', 'New order #48 received for apple', 0, '2026-01-10 15:11:12'),
(61, 'farmer', 10, 'New Order', 'New order #49 received for apple', 0, '2026-01-14 10:27:30'),
(62, 'buyer', 4, 'Order Update', 'Your order #49 status changed to: Completed', 0, '2026-02-07 12:26:14'),
(63, 'farmer', 10, 'New Order', 'New order #55 received for apple', 0, '2026-02-08 11:34:16'),
(64, 'farmer', 10, 'New Order', 'New order #55 received for apple', 0, '2026-02-08 11:34:16'),
(65, 'buyer', 4, 'Order Update', 'Your order #55 status changed to: Completed', 0, '2026-02-11 09:37:40'),
(66, 'farmer', 10, 'Product Approved', 'Your product #22 has been approved at Rs262.5 (Commission: 5%).', 0, '2026-02-11 12:45:21'),
(67, 'farmer', 15, 'Product Approved', 'Your product #24 has been approved at Rs26.55 (Commission: 6.2%).', 0, '2026-02-11 15:35:43'),
(68, 'farmer', 15, 'Product Approved', 'Your product #23 has been approved at Rs318 (Commission: 6%).', 0, '2026-02-11 15:36:01');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `buyer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `order_date` datetime DEFAULT current_timestamp(),
  `status` enum('Pending','Processing','Completed','Cancelled') DEFAULT 'Pending',
  `payment_method` enum('cash_on_delivery','esewa','khalti','bank_transfer') DEFAULT 'cash_on_delivery',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `delivery_notes` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estimated_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `buyer_id`, `total_amount`, `order_date`, `status`, `payment_method`, `payment_status`, `payment_reference`, `delivery_notes`, `updated_at`, `estimated_delivery_date`, `actual_delivery_date`, `tracking_number`) VALUES
(6, 4, 250.00, '2025-12-20 20:29:56', 'Pending', 'cash_on_delivery', 'pending', NULL, NULL, '2026-01-03 15:07:02', NULL, NULL, NULL),
(7, 4, 3024.00, '2025-12-20 20:37:00', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:24:55', NULL, NULL, NULL),
(8, 4, 192.00, '2025-12-20 20:37:38', 'Pending', 'cash_on_delivery', 'pending', NULL, NULL, '2026-01-03 14:13:52', NULL, NULL, NULL),
(9, 4, 250.00, '2025-12-20 20:51:04', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:24:52', NULL, NULL, NULL),
(10, 5, 250.00, '2025-12-21 14:25:36', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:24:46', NULL, NULL, NULL),
(11, 5, 32.00, '2025-12-21 14:34:58', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:13:05', NULL, NULL, NULL),
(12, 5, 62640.00, '2025-12-21 14:35:28', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:13:02', NULL, NULL, NULL),
(13, 4, 222.00, '2025-12-22 20:52:47', '', 'cash_on_delivery', 'pending', NULL, NULL, '2026-01-03 14:48:18', NULL, NULL, NULL),
(14, 4, 222000.00, '2025-12-28 19:52:36', '', 'cash_on_delivery', 'pending', NULL, NULL, '2026-01-03 14:48:15', NULL, NULL, NULL),
(15, 4, 250.00, '2025-12-28 19:52:43', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:12:58', NULL, NULL, NULL),
(16, 4, 4320000.00, '2025-12-30 20:56:28', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:12:56', NULL, NULL, NULL),
(17, 4, 250.00, '2025-12-30 20:56:44', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:12:44', NULL, NULL, NULL),
(18, 4, 250.00, '2025-12-30 20:58:31', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:24:39', NULL, NULL, NULL),
(19, 4, 250.00, '2025-12-30 20:59:47', '', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:24:36', NULL, NULL, NULL),
(20, 4, 12000.00, '2025-12-30 21:06:07', 'Pending', 'cash_on_delivery', 'pending', NULL, NULL, '2025-12-30 15:21:07', NULL, NULL, NULL),
(21, 4, 5000.00, '2025-12-30 21:08:03', '', 'esewa', 'pending', NULL, '', '2025-12-30 15:24:33', NULL, NULL, NULL),
(22, 4, 300.00, '2025-12-30 21:09:00', '', 'bank_transfer', 'pending', NULL, '', '2025-12-30 15:24:30', NULL, NULL, NULL),
(23, 4, 550.00, '2025-12-30 21:11:15', 'Cancelled', 'khalti', 'pending', NULL, '', '2025-12-30 15:26:43', NULL, NULL, NULL),
(24, 4, 300.00, '2025-12-31 20:07:39', '', 'khalti', 'pending', NULL, '', '2025-12-31 14:23:18', NULL, NULL, NULL),
(25, 4, 222.00, '2025-12-31 20:08:51', '', 'cash_on_delivery', 'pending', NULL, NULL, '2026-01-03 14:48:10', NULL, NULL, NULL),
(26, 4, 8214.00, '2025-12-31 20:09:13', '', 'cash_on_delivery', 'pending', NULL, '', '2026-01-03 14:47:49', NULL, NULL, NULL),
(27, 4, 482.00, '2025-12-31 20:27:59', '', 'bank_transfer', 'pending', NULL, '', '2025-12-31 14:44:00', NULL, NULL, NULL),
(28, 4, 25000.00, '2025-12-31 20:31:38', 'Pending', 'bank_transfer', 'pending', NULL, '', '2025-12-31 14:46:38', NULL, NULL, NULL),
(29, 4, 300.00, '2025-12-31 20:41:59', '', 'bank_transfer', 'pending', NULL, '', '2025-12-31 15:23:44', NULL, NULL, NULL),
(30, 4, 972.00, '2025-12-31 20:56:20', '', 'esewa', 'pending', NULL, '', '2025-12-31 15:23:41', NULL, NULL, NULL),
(31, 4, 300.00, '2026-01-01 16:23:17', 'Pending', 'esewa', 'pending', NULL, '', '2026-01-01 10:38:17', NULL, NULL, NULL),
(32, 4, 272.00, '2026-01-01 17:23:27', '', 'cash_on_delivery', 'pending', NULL, '', '2026-01-03 14:51:54', NULL, NULL, NULL),
(33, 4, 300.00, '2026-01-01 17:25:00', 'Completed', 'cash_on_delivery', 'pending', NULL, '', '2026-01-07 10:05:36', NULL, NULL, NULL),
(34, 4, 300.00, '2026-01-01 17:34:36', '', 'cash_on_delivery', 'pending', NULL, '', '2026-01-03 14:14:13', NULL, NULL, NULL),
(35, 4, 300.00, '2026-01-01 17:54:33', 'Pending', 'esewa', 'pending', NULL, '', '2026-01-07 15:43:11', NULL, NULL, NULL),
(36, 4, 482.00, '2026-01-03 20:12:21', '', 'bank_transfer', 'pending', NULL, '', '2026-01-03 17:40:35', NULL, NULL, NULL),
(37, 7, 2220.00, '2026-01-03 20:15:44', 'Cancelled', 'esewa', 'pending', NULL, '', '2026-01-03 14:47:44', NULL, NULL, NULL),
(38, 4, 272.00, '2026-01-06 14:27:52', 'Pending', 'esewa', 'pending', NULL, '', '2026-01-06 08:42:52', NULL, NULL, NULL),
(39, 8, 272.00, '2026-01-06 19:25:23', 'Pending', 'esewa', 'pending', NULL, '', '2026-01-06 13:40:23', NULL, NULL, NULL),
(40, 8, 25000.00, '2026-01-06 19:32:04', 'Processing', 'esewa', 'pending', NULL, '', '2026-01-07 10:05:41', NULL, NULL, NULL),
(41, 4, 170.00, '2026-01-07 09:42:44', 'Pending', 'bank_transfer', 'pending', NULL, '', '2026-01-07 03:57:44', NULL, NULL, NULL),
(42, 4, 4440.00, '2026-01-07 09:48:06', 'Pending', 'cash_on_delivery', 'pending', NULL, '', '2026-01-07 04:03:06', NULL, NULL, NULL),
(43, 4, 272.00, '2026-01-07 09:53:10', 'Pending', 'cash_on_delivery', 'pending', NULL, '', '2026-01-07 04:08:10', NULL, NULL, NULL),
(44, 4, 82.00, '2026-01-07 09:57:24', 'Completed', 'bank_transfer', 'pending', NULL, '', '2026-01-07 10:05:09', NULL, NULL, NULL),
(45, 4, 1474.00, '2026-01-07 17:03:01', 'Cancelled', 'cash_on_delivery', 'pending', NULL, '', '2026-01-07 15:45:51', NULL, NULL, NULL),
(46, 8, 666.00, '2026-01-09 13:45:26', 'Completed', 'esewa', 'pending', NULL, '', '2026-01-09 08:01:51', NULL, NULL, NULL),
(47, 4, 800.00, '2026-01-10 11:44:06', 'Pending', 'bank_transfer', 'pending', NULL, '', '2026-01-10 05:59:06', NULL, NULL, NULL),
(48, 4, 20000.00, '2026-01-10 20:56:12', 'Pending', 'bank_transfer', 'pending', NULL, '', '2026-01-10 15:11:12', NULL, NULL, NULL),
(49, 4, 450.00, '2026-01-14 16:12:30', 'Completed', 'esewa', 'pending', NULL, '', '2026-02-07 12:26:14', NULL, NULL, NULL),
(55, 4, 1284.00, '2026-02-08 17:19:16', 'Completed', 'cash_on_delivery', 'pending', NULL, NULL, '2026-02-11 09:37:40', NULL, NULL, NULL),
(60, 11, 265.50, '2026-02-11 21:31:07', 'Pending', 'cash_on_delivery', 'pending', NULL, 'Kaushaltar-uskomanma', '2026-02-11 15:46:07', NULL, NULL, NULL),
(61, 4, 428.00, '2026-02-11 22:00:35', 'Pending', 'esewa', 'pending', NULL, 'lalitpur-nepal', '2026-02-11 16:15:35', NULL, NULL, NULL);

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_order_complete` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    -- Only run if status changed to 'Completed'
    IF NEW.status = 'Completed' AND OLD.status != 'Completed' THEN
        -- Insert into admin_revenue for each order detail
        INSERT INTO admin_revenue (order_id, order_detail_id, product_id, quantity, farmer_price, admin_price, profit_amount)
        SELECT 
            NEW.order_id,
            od.order_detail_id,
            od.product_id,
            od.quantity,
            od.farmer_price,
            od.admin_price,
            (od.admin_price - od.farmer_price) * od.quantity
        FROM order_details od
        WHERE od.order_id = NEW.order_id
        AND NOT EXISTS (
            SELECT 1 FROM admin_revenue ar 
            WHERE ar.order_detail_id = od.order_detail_id
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `order_detail_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `farmer_price` decimal(10,2) NOT NULL,
  `admin_price` decimal(10,2) NOT NULL,
  `profit_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_details`
--

INSERT INTO `order_details` (`order_detail_id`, `order_id`, `product_id`, `quantity`, `price`, `farmer_price`, `admin_price`, `profit_per_unit`) VALUES
(58, 55, 19, 2, 214.00, 200.00, 214.00, 14.00),
(64, 60, 24, 10, 26.55, 25.00, 26.55, 1.55),
(65, 61, 19, 2, 214.00, 200.00, 214.00, 14.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `history_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` varchar(50) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`history_id`, `order_id`, `old_status`, `new_status`, `changed_by`, `changed_at`, `notes`) VALUES
(1, 17, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:12:44', NULL),
(2, 16, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:12:56', NULL),
(3, 15, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:12:58', NULL),
(4, 12, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:13:02', NULL),
(5, 11, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:13:05', NULL),
(6, 22, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:24:30', NULL),
(7, 21, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:24:33', NULL),
(8, 19, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:24:36', NULL),
(9, 18, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:24:39', NULL),
(10, 10, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:24:46', NULL),
(11, 9, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:24:52', NULL),
(12, 7, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:24:55', NULL),
(13, 8, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:25:15', NULL),
(14, 6, 'Pending', 'Confirmed', 'farmer', '2025-12-30 15:25:20', NULL),
(15, 23, 'Pending', 'Cancelled', 'farmer', '2025-12-30 15:26:43', NULL),
(16, 24, 'Pending', 'Confirmed', 'farmer', '2025-12-31 14:23:18', NULL),
(17, 27, 'Pending', 'Confirmed', 'farmer', '2025-12-31 14:44:00', NULL),
(18, 30, 'Pending', 'Confirmed', 'farmer', '2025-12-31 15:23:41', NULL),
(19, 29, 'Pending', 'Confirmed', 'farmer', '2025-12-31 15:23:44', NULL),
(20, 44, '', 'Completed', 'farmer', '2026-01-07 10:05:09', NULL),
(21, 33, 'Pending', 'Completed', 'farmer', '2026-01-07 10:05:36', NULL),
(22, 40, '', 'Processing', 'farmer', '2026-01-07 10:05:41', NULL),
(23, 45, 'Pending', 'Pending', 'farmer', '2026-01-07 15:43:05', NULL),
(24, 40, 'Processing', 'Processing', 'farmer', '2026-01-07 15:43:07', NULL),
(25, 40, 'Processing', 'Processing', 'farmer', '2026-01-07 15:43:09', NULL),
(26, 35, '', 'Pending', 'farmer', '2026-01-07 15:43:11', NULL),
(27, 45, 'Pending', 'Pending', 'farmer', '2026-01-07 15:45:42', NULL),
(28, 45, 'Pending', 'Cancelled', 'farmer', '2026-01-07 15:45:51', NULL),
(29, 46, 'Pending', 'Completed', 'farmer', '2026-01-09 08:01:51', NULL),
(30, 49, 'Processing', 'Completed', 'farmer', '2026-02-07 12:26:14', NULL),
(31, 55, 'Pending', 'Completed', 'farmer', '2026-02-11 09:37:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `farmer_id` int(11) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total_stock` int(11) DEFAULT 0,
  `sold_quantity` int(11) NOT NULL DEFAULT 0,
  `category` varchar(100) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'kg',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_price` decimal(10,2) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT 5.00 COMMENT 'Commission percentage (5-10%)',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `farmer_id`, `product_name`, `description`, `price`, `quantity`, `total_stock`, `sold_quantity`, `category`, `category_id`, `image`, `unit`, `created_at`, `updated_at`, `status`, `admin_notes`, `admin_id`, `is_active`, `deleted_at`, `approval_status`, `admin_price`, `commission_rate`, `approved_by`, `approved_at`, `rejection_reason`) VALUES
(19, 10, 'apple', '', 200.00, 196, 200, 2, 'Fruits', NULL, NULL, 'kg', '2026-01-14 10:26:24', '2026-02-11 16:15:35', 'pending', NULL, NULL, 1, NULL, 'approved', 214.00, 7.00, NULL, NULL, NULL),
(20, 10, 'radish', 'fresh radish', 100.00, 99, 99, 0, 'Vegetables', NULL, '698c386a1580d.png', 'kg', '2026-02-08 12:30:38', '2026-02-11 09:19:32', 'pending', NULL, NULL, 1, NULL, 'approved', 105.00, 5.00, 1, '2026-02-11 15:04:32', NULL),
(22, 10, 'apple', 'local apple', 250.00, 301, 301, 0, 'Fruits', NULL, '698c77edbe6ac.png', 'kg', '2026-02-11 12:37:01', '2026-02-11 12:45:48', 'pending', NULL, NULL, 1, NULL, 'approved', 262.50, 5.00, 1, '2026-02-11 18:30:21', NULL),
(23, 15, 'Eggs', 'Eggs from Local chicken. Being sold in Dozens.', 300.00, 150, 150, 0, 'Other', NULL, '698ca03532139.jpg', 'dozen', '2026-02-11 15:28:53', '2026-02-11 15:36:01', 'pending', NULL, NULL, 1, NULL, 'approved', 318.00, 6.00, 1, '2026-02-11 21:21:01', NULL),
(24, 15, 'Chicks', 'Chicks of Local Chickens.', 25.00, 40, 50, 0, 'Other', NULL, '698ca16de9377.png', 'piece', '2026-02-11 15:34:05', '2026-02-11 15:46:07', 'pending', NULL, NULL, 1, NULL, 'approved', 26.55, 6.20, 1, '2026-02-11 21:20:43', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_price_history`
--

CREATE TABLE `product_price_history` (
  `history_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `original_price` decimal(10,2) NOT NULL,
  `approved_price` decimal(10,2) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `product_profit_analysis`
-- (See below for the actual view)
--
CREATE TABLE `product_profit_analysis` (
`product_id` int(11)
,`product_name` varchar(150)
,`category` varchar(100)
,`farmer_price` decimal(10,2)
,`admin_price` decimal(10,2)
,`profit_per_unit` decimal(11,2)
,`profit_margin_percent` decimal(20,6)
,`current_stock` int(11)
,`total_stock` int(11)
,`sold_quantity` int(11)
,`total_profit_earned` decimal(21,2)
,`potential_profit_remaining` decimal(21,2)
,`farmer_name` varchar(100)
,`approval_status` enum('pending','approved','rejected')
);

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `buyer_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `product_stock_status`
-- (See below for the actual view)
--
CREATE TABLE `product_stock_status` (
`product_id` int(11)
,`product_name` varchar(150)
,`total_stock` int(11)
,`remaining_stock` int(11)
,`sold_quantity` int(11)
,`farmer_price` decimal(10,2)
,`admin_price` decimal(10,2)
,`profit_per_unit` decimal(11,2)
,`approval_status` enum('pending','approved','rejected')
,`farmer_name` varchar(100)
);

-- --------------------------------------------------------

--
-- Structure for view `admin_dashboard_summary`
--
DROP TABLE IF EXISTS `admin_dashboard_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `admin_dashboard_summary`  AS SELECT (select count(0) from `products` where `products`.`approval_status` = 'pending') AS `pending_products`, (select count(0) from `products` where `products`.`approval_status` = 'approved') AS `approved_products`, (select count(0) from `products` where `products`.`quantity` < 10 and `products`.`approval_status` = 'approved') AS `low_stock_products`, (select count(0) from `orders` where `orders`.`status` = 'Pending') AS `pending_orders`, (select count(0) from `orders` where `orders`.`status` = 'Completed') AS `completed_orders`, (select ifnull(sum(`admin_revenue`.`profit_amount`),0) from `admin_revenue`) AS `total_profit`, (select ifnull(sum(`admin_revenue`.`profit_amount`),0) from `admin_revenue` where cast(`admin_revenue`.`created_at` as date) = curdate()) AS `today_profit`, (select ifnull(sum(`admin_revenue`.`profit_amount`),0) from `admin_revenue` where `admin_revenue`.`created_at` >= current_timestamp() - interval 30 day) AS `month_profit`, (select ifnull(sum((`products`.`admin_price` - `products`.`price`) * `products`.`quantity`),0) from `products` where `products`.`approval_status` = 'approved' and `products`.`admin_price` is not null) AS `potential_profit` ;

-- --------------------------------------------------------

--
-- Structure for view `admin_profit_summary`
--
DROP TABLE IF EXISTS `admin_profit_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `admin_profit_summary`  AS SELECT cast(`admin_revenue`.`created_at` as date) AS `date`, count(distinct `admin_revenue`.`order_id`) AS `orders_count`, sum(`admin_revenue`.`quantity`) AS `items_sold`, sum(`admin_revenue`.`profit_amount`) AS `total_profit` FROM `admin_revenue` GROUP BY cast(`admin_revenue`.`created_at` as date) ORDER BY cast(`admin_revenue`.`created_at` as date) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `commission_analytics`
--
DROP TABLE IF EXISTS `commission_analytics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `commission_analytics`  AS SELECT `p`.`category` AS `category`, count(distinct `p`.`product_id`) AS `total_products`, avg(`p`.`commission_rate`) AS `avg_commission_rate`, sum(`ar`.`commission_amount`) AS `total_commission_earned`, sum(`ar`.`farmer_final_amount`) AS `total_farmer_payout`, count(distinct `ar`.`order_id`) AS `total_orders` FROM (`products` `p` left join `admin_revenue` `ar` on(`p`.`product_id` = `ar`.`product_id`)) GROUP BY `p`.`category` ;

-- --------------------------------------------------------

--
-- Structure for view `product_profit_analysis`
--
DROP TABLE IF EXISTS `product_profit_analysis`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_profit_analysis`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `p`.`category` AS `category`, `p`.`price` AS `farmer_price`, `p`.`admin_price` AS `admin_price`, `p`.`admin_price`- `p`.`price` AS `profit_per_unit`, (`p`.`admin_price` - `p`.`price`) / `p`.`price` * 100 AS `profit_margin_percent`, `p`.`quantity` AS `current_stock`, `p`.`total_stock` AS `total_stock`, `p`.`sold_quantity` AS `sold_quantity`, (`p`.`admin_price` - `p`.`price`) * `p`.`sold_quantity` AS `total_profit_earned`, (`p`.`admin_price` - `p`.`price`) * `p`.`quantity` AS `potential_profit_remaining`, `f`.`name` AS `farmer_name`, `p`.`approval_status` AS `approval_status` FROM (`products` `p` join `farmer` `f` on(`p`.`farmer_id` = `f`.`farmer_id`)) WHERE `p`.`approval_status` = 'approved' AND `p`.`admin_price` is not null ;

-- --------------------------------------------------------

--
-- Structure for view `product_stock_status`
--
DROP TABLE IF EXISTS `product_stock_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `product_stock_status`  AS SELECT `p`.`product_id` AS `product_id`, `p`.`product_name` AS `product_name`, `p`.`total_stock` AS `total_stock`, `p`.`quantity` AS `remaining_stock`, `p`.`sold_quantity` AS `sold_quantity`, `p`.`price` AS `farmer_price`, `p`.`admin_price` AS `admin_price`, `p`.`admin_price`- `p`.`price` AS `profit_per_unit`, `p`.`approval_status` AS `approval_status`, `f`.`name` AS `farmer_name` FROM (`products` `p` join `farmer` `f` on(`p`.`farmer_id` = `f`.`farmer_id`)) WHERE `p`.`approval_status` = 'approved' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_revenue`
--
ALTER TABLE `admin_revenue`
  ADD PRIMARY KEY (`revenue_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_revenue_date` (`created_at`),
  ADD KEY `idx_revenue_product` (`product_id`,`created_at`),
  ADD KEY `idx_revenue_order` (`order_id`,`created_at`);

--
-- Indexes for table `buyer`
--
ALTER TABLE `buyer`
  ADD PRIMARY KEY (`buyer_id`),
  ADD UNIQUE KEY `contact` (`contact`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_cart_buyer` (`buyer_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `commission_settings`
--
ALTER TABLE `commission_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `category` (`category`);

--
-- Indexes for table `farmer`
--
ALTER TABLE `farmer`
  ADD PRIMARY KEY (`farmer_id`),
  ADD UNIQUE KEY `contact` (`contact`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user` (`user_type`,`user_id`),
  ADD KEY `idx_read` (`is_read`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_orders_buyer` (`buyer_id`,`order_date`),
  ADD KEY `idx_orders_status` (`status`,`order_date`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`order_detail_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_profit` (`profit_per_unit`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `idx_order` (`order_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_products_farmer` (`farmer_id`,`created_at`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `fk_product_category` (`category_id`),
  ADD KEY `idx_approval_status` (`approval_status`),
  ADD KEY `idx_approved_by` (`approved_by`),
  ADD KEY `idx_stock` (`quantity`,`total_stock`),
  ADD KEY `idx_products_status` (`approval_status`);

--
-- Indexes for table `product_price_history`
--
ALTER TABLE `product_price_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `unique_review` (`product_id`,`buyer_id`,`order_id`),
  ADD KEY `buyer_id` (`buyer_id`),
  ADD KEY `order_id` (`order_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `admin_revenue`
--
ALTER TABLE `admin_revenue`
  MODIFY `revenue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `buyer`
--
ALTER TABLE `buyer`
  MODIFY `buyer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `commission_settings`
--
ALTER TABLE `commission_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `farmer`
--
ALTER TABLE `farmer`
  MODIFY `farmer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `order_details`
--
ALTER TABLE `order_details`
  MODIFY `order_detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `product_price_history`
--
ALTER TABLE `product_price_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_revenue`
--
ALTER TABLE `admin_revenue`
  ADD CONSTRAINT `fk_admin_revenue_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_admin_revenue_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `buyer` (`buyer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`buyer_id`) REFERENCES `buyer` (`buyer_id`) ON DELETE SET NULL;

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`farmer_id`) REFERENCES `farmer` (`farmer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `product_price_history`
--
ALTER TABLE `product_price_history`
  ADD CONSTRAINT `product_price_history_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_price_history_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admin` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`buyer_id`) REFERENCES `buyer` (`buyer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
