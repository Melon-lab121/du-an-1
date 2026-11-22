-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2026 at 06:48 PM
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
-- Database: `noi_that_luxe_1`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_details`
--

CREATE TABLE `cart_details` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `sort_order`, `created_at`, `updated_at`) VALUES
(6, NULL, 'Lighting', 'lighting', NULL, 1, NULL, '2026-11-21 23:06:03'),
(7, NULL, 'Lamps & light fixtures', 'lamps-light-fixtures', NULL, 2, NULL, '2026-11-21 23:06:03'),
(8, NULL, 'Ceiling lights', 'ceiling-lights', NULL, 3, NULL, '2026-11-21 23:06:03'),
(9, NULL, 'Pendant lighting', 'pendant-lighting', NULL, 4, NULL, '2026-11-21 23:06:03'),
(11, NULL, 'Plants & planters', 'plants-planters', NULL, 6, NULL, '2026-11-21 23:06:03'),
(12, NULL, 'Plants and flowers', 'plants-and-flowers', NULL, 7, NULL, '2026-11-21 23:06:03'),
(13, NULL, 'Living plants for home', 'living-plants-for-home', NULL, 8, NULL, '2026-11-21 23:06:03'),
(14, NULL, 'Office & desk plants', 'office-desk-plants', NULL, 9, NULL, '2026-11-21 23:06:03'),
(16, NULL, 'Home electronics', 'home-electronics', NULL, 11, NULL, '2026-11-21 23:06:03'),
(17, NULL, 'Cell phone, tablet & laptop accessories', 'cell-phone-tablet-laptop-accessories', NULL, 12, NULL, '2026-11-21 23:06:03'),
(18, NULL, 'Laptop stands & phone holders', 'laptop-stands-phone-holders', NULL, 13, NULL, '2026-11-21 23:06:03'),
(20, NULL, 'Indoor trees & floor plants', 'indoor-trees-floor-plants', NULL, 15, NULL, '2026-11-21 23:06:03'),
(22, NULL, 'Storage & organization', 'storage-organization', NULL, 17, NULL, '2026-11-21 23:06:03'),
(23, NULL, 'Dressers & storage drawers', 'dressers-storage-drawers', NULL, 18, NULL, '2026-11-21 23:06:03'),
(24, NULL, 'Nightstands', 'nightstands', NULL, 19, NULL, '2026-11-21 23:06:03'),
(26, NULL, 'Beds & mattresses', 'beds-mattresses', NULL, 21, NULL, '2026-11-21 23:06:03'),
(27, NULL, 'Beds', 'beds', NULL, 22, NULL, '2026-11-21 23:06:03'),
(28, NULL, 'Bed frames', 'bed-frames', NULL, 23, NULL, '2026-11-21 23:06:03'),
(30, NULL, 'Smart home', 'smart-home', NULL, 25, NULL, '2026-11-21 23:06:03'),
(31, NULL, 'Smart lighting', 'smart-lighting', NULL, 26, NULL, '2026-11-21 23:06:03'),
(32, NULL, 'Smart light starter kits', 'smart-light-starter-kits', NULL, 27, NULL, '2026-11-21 23:06:03'),
(34, NULL, 'Integrated lighting', 'integrated-lighting', NULL, 29, NULL, '2026-11-21 23:06:03'),
(35, NULL, 'Bookcase & cabinet lighting', 'bookcase-cabinet-lighting', NULL, 30, NULL, '2026-11-21 23:06:03'),
(37, NULL, 'Tables & chairs', 'tables-chairs', NULL, 32, NULL, '2026-11-21 23:06:03'),
(38, NULL, 'Dining furniture', 'dining-furniture', NULL, 33, NULL, '2026-11-21 23:06:03'),
(39, NULL, 'Dining chairs', 'dining-chairs', NULL, 34, NULL, '2026-11-21 23:06:03'),
(41, NULL, 'Home decor & accessories', 'home-decor-accessories', NULL, 36, NULL, '2026-11-21 23:06:03'),
(42, NULL, 'Wall décor', 'wall-decor', NULL, 37, NULL, '2026-11-21 23:06:03'),
(43, NULL, 'Picture & photo frames', 'picture-photo-frames', NULL, 38, NULL, '2026-11-21 23:06:03'),
(45, NULL, 'Collage picture & gallery wall frames', 'collage-picture-gallery-wall-frames', NULL, 40, NULL, '2026-11-21 23:06:04'),
(47, NULL, 'Art cards', 'art-cards', NULL, 42, NULL, '2026-11-21 23:06:04'),
(49, NULL, 'Kitchen, appliances & supplies', 'kitchen-appliances-supplies', NULL, 44, NULL, '2026-11-21 23:06:04'),
(50, NULL, 'Appliances', 'appliances', NULL, 45, NULL, '2026-11-21 23:06:04'),
(51, NULL, 'Microwaves', 'microwaves', NULL, 46, NULL, '2026-11-21 23:06:04'),
(52, NULL, 'Microwave ovens', 'microwave-ovens', NULL, 47, NULL, '2026-11-21 23:06:04'),
(54, NULL, 'Armoires & wardrobes', 'armoires-wardrobes', NULL, 49, NULL, '2026-11-21 23:06:04'),
(55, NULL, 'Narrow wardrobes', 'narrow-wardrobes', NULL, 50, NULL, '2026-11-21 23:06:04'),
(57, NULL, 'Mirrors', 'mirrors', NULL, 52, NULL, '2026-11-21 23:06:04'),
(58, NULL, 'Decorative mirrors', 'decorative-mirrors', NULL, 53, NULL, '2026-11-21 23:06:04'),
(60, NULL, 'Sofas & armchairs', 'sofas-armchairs', NULL, 55, NULL, '2026-11-21 23:06:04'),
(61, NULL, 'Sleeper sofas & sofa beds', 'sleeper-sofas-sofa-beds', NULL, 56, NULL, '2026-11-21 23:06:04'),
(62, NULL, 'Sleeper sofa beds', 'sleeper-sofa-beds', NULL, 57, NULL, '2026-11-21 23:06:04'),
(64, NULL, 'Sofa & armchairs covers', 'sofa-armchairs-covers', NULL, 59, NULL, '2026-11-21 23:06:04'),
(67, NULL, 'Kitchen systems', 'kitchen-systems', NULL, 62, NULL, '2026-11-21 23:06:04'),
(75, NULL, 'Bathroom: furniture, supplies & more', 'bathroom-furniture-supplies-more', NULL, 70, NULL, '2026-11-21 23:06:04'),
(76, NULL, 'Bathroom faucets & sink taps', 'bathroom-faucets-sink-taps', NULL, 71, NULL, '2026-11-21 23:06:04'),
(78, NULL, 'Dining sets', 'dining-sets', NULL, 73, NULL, '2026-11-21 23:06:04'),
(79, NULL, 'Dining sets up to 4 seats', 'dining-sets-up-to-4-seats', NULL, 74, NULL, '2026-11-21 23:06:04'),
(82, NULL, 'Dining tables', 'dining-tables', NULL, 77, NULL, '2026-11-21 23:06:04'),
(83, NULL, 'Up to 6 seats', 'up-to-6-seats', NULL, 78, NULL, '2026-11-21 23:06:04'),
(87, NULL, 'Flooring, rugs & mats', 'flooring-rugs-mats', NULL, 82, NULL, '2026-11-21 23:06:04'),
(88, NULL, 'Rugs', 'rugs', NULL, 83, NULL, '2026-11-21 23:06:04'),
(89, NULL, 'Medium, large and extra-large rugs', 'medium-large-and-extra-large-rugs', NULL, 84, NULL, '2026-11-21 23:06:04'),
(92, NULL, 'Kitchenware & tableware', 'kitchenware-tableware', NULL, 87, NULL, '2026-11-21 23:06:04'),
(93, NULL, 'Knives & cutting boards', 'knives-cutting-boards', NULL, 88, NULL, '2026-11-21 23:06:04'),
(94, NULL, 'Kitchen knives', 'kitchen-knives', NULL, 89, NULL, '2026-11-21 23:06:04'),
(99, NULL, 'Baby & kids', 'baby-kids', NULL, 94, NULL, '2026-11-21 23:06:04'),
(100, NULL, 'Kids', 'kids', NULL, 95, NULL, '2026-11-21 23:06:04'),
(101, NULL, 'Kids beds', 'kids-beds', NULL, 96, NULL, '2026-11-21 23:06:04'),
(102, NULL, 'Kids single beds', 'kids-single-beds', NULL, 97, NULL, '2026-11-21 23:06:04'),
(105, NULL, 'Bathroom countertop & drawer organizers', 'bathroom-countertop-drawer-organizers', NULL, 100, NULL, '2026-11-21 23:06:04'),
(107, NULL, 'Sofas & sectionals', 'sofas-sectionals', NULL, 102, NULL, '2026-11-21 23:06:04'),
(108, NULL, 'Three-seat sofas', 'three-seat-sofas', NULL, 103, NULL, '2026-11-21 23:06:04'),
(110, NULL, 'Loveseats', 'loveseats', NULL, 105, NULL, '2026-11-21 23:06:04'),
(112, NULL, 'Armchairs & accent chairs', 'armchairs-accent-chairs', NULL, 107, NULL, '2026-11-21 23:06:04'),
(113, NULL, 'Fabric armchairs', 'fabric-armchairs', NULL, 108, NULL, '2026-11-21 23:06:04');

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discount_types`
--

CREATE TABLE `discount_types` (
  `id` int(11) NOT NULL,
  `discount_type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `code` varchar(100) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `total` double NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(50) DEFAULT NULL,
  `shipping_address` varchar(255) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `subtotal` int(11) NOT NULL DEFAULT 0,
  `shipping_fee` int(11) NOT NULL DEFAULT 0,
  `discount` int(11) NOT NULL DEFAULT 0,
  `payment_method` varchar(255) NOT NULL,
  `payment_status` varchar(50) NOT NULL DEFAULT 'unpaid',
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `code`, `date`, `total`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `shipping_address`, `note`, `subtotal`, `shipping_fee`, `discount`, `payment_method`, `payment_status`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES
(3, 'DH2611214A6B7F', '0000-00-00', 26225000, 10, 'Customer', 'customer@gmail.com', '0987654321', 'TP. Hồ Chí Minh', NULL, 26225000, 0, 0, 'sepay', 'paid', 'confirmed', '2026-11-21 23:38:44', '2026-11-21 23:38:25', '2026-11-21 23:38:44'),
(4, 'DH261122DB7BA5', '0000-00-00', 1499750, 10, 'Customer', 'customer@gmail.com', '0987654321', 'TP. Hồ Chí Minh', NULL, 1499750, 0, 0, 'sepay', 'paid', 'confirmed', '2026-11-22 00:48:09', '2026-11-22 00:47:55', '2026-11-22 00:48:09'),
(5, 'DH261122EAD726', NULL, 2149750, NULL, 'Khach Vang Lai', 'guest1795326481@example.com', '0987654321', '123 Test St', NULL, 2149750, 0, 0, 'cod', 'unpaid', 'pending', NULL, '2026-11-22 12:48:01', '2026-11-22 12:48:01'),
(6, 'DH2611228F6FC0', '2026-11-22', 2149750, NULL, 'COD Tester', 'cod1795327739@test.com', '0909090909', 'Hanoi', NULL, 2149750, 0, 0, 'cod', 'paid', 'completed', '2026-11-22 13:08:59', '2026-11-22 13:08:59', '2026-11-22 13:08:59'),
(7, 'DH2611220335DA', '2026-11-22', 2149750, NULL, 'Refund Tester', 'refund1795327783@test.com', '0909090909', 'Hanoi', NULL, 2149750, 0, 0, 'sepay', 'refunded', 'cancelled', '2026-11-22 13:09:43', '2026-11-22 13:09:43', '2026-11-22 13:09:43'),
(8, 'DH2611228711E6', NULL, 2149750, NULL, 'Khach Vang Lai', 'guest1795327895@example.com', '0987654321', '123 Test St', NULL, 2149750, 0, 0, 'cod', 'unpaid', 'pending', NULL, '2026-11-22 13:11:35', '2026-11-22 13:11:35'),
(9, 'DH261122CE28B0', NULL, 26225000, NULL, 'Huy Quang', 'huyq8545@gmail.com', '0977214236', 'Thanh Hóa', NULL, 26225000, 0, 0, 'sepay', 'paid', 'confirmed', '2026-11-22 13:21:13', '2026-11-22 13:20:28', '2026-11-22 13:21:13'),
(10, 'DH261122301CFB', NULL, 12475000, NULL, 'fa', 'fsdfaf@faf.com', '0932942930', 'sjfoaf', NULL, 12475000, 0, 0, 'sepay', 'unpaid', 'pending', NULL, '2026-11-22 23:56:07', '2026-11-22 23:56:07');

-- --------------------------------------------------------

--
-- Table structure for table `order_info`
--

CREATE TABLE `order_info` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `variant_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `variant_label` varchar(255) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `price` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `total` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `variant_id`, `product_name`, `variant_label`, `sku`, `price`, `quantity`, `total`) VALUES
(1, 3, 52, NULL, 'VRETSTORP sleeper sofa, Kilanda light beige', NULL, 'SP0052', 26225000, 1, 26225000),
(2, 4, 51, NULL, 'VRIGSTAD mirror, sedge, 24 ¾\"', NULL, 'SP0051', 1499750, 1, 1499750),
(3, 5, 41, NULL, 'YTBERG / TRÅDFRI lighting kit, white', NULL, 'SP0041', 2149750, 1, 2149750),
(4, 6, 41, NULL, 'YTBERG / TRÅDFRI lighting kit, white', NULL, 'SP0041', 2149750, 1, 2149750),
(5, 7, 41, NULL, 'YTBERG / TRÅDFRI lighting kit, white', NULL, 'SP0041', 2149750, 1, 2149750),
(6, 8, 41, NULL, 'YTBERG / TRÅDFRI lighting kit, white', NULL, 'SP0041', 2149750, 1, 2149750),
(7, 9, 52, NULL, 'VRETSTORP sleeper sofa, Kilanda light beige', NULL, 'SP0052', 26225000, 1, 26225000),
(8, 10, 49, NULL, 'YGSBO over-the-range convection microwave, Stainless steel, 30\"', NULL, 'SP0049', 12475000, 1, 12475000);

-- --------------------------------------------------------

--
-- Table structure for table `order_status`
--

CREATE TABLE `order_status` (
  `id` int(11) NOT NULL,
  `status` varchar(255) NOT NULL,
  `order_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `gateway` varchar(100) NOT NULL,
  `txn_ref` varchar(191) NOT NULL,
  `amount` int(11) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'pending',
  `transaction_no` varchar(255) DEFAULT NULL,
  `bank_code` varchar(100) DEFAULT NULL,
  `response_code` varchar(100) DEFAULT NULL,
  `gateway_create_date` varchar(100) DEFAULT NULL,
  `raw_data` longtext DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `gateway`, `txn_ref`, `amount`, `status`, `transaction_no`, `bank_code`, `response_code`, `gateway_create_date`, `raw_data`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 3, 'sepay', '2611212338254520', 26225000, 'success', 'fec58061-e5c9-11f1-8895-0afffe87053b', NULL, 'CAPTURED', '20261121233825', '{\"data\":{\"id\":\"fec58061-e5c9-11f1-8895-0afffe87053b\",\"customer_id\":null,\"order_id\":\"PAY127376B01C90B0583D\",\"order_invoice_number\":\"2611212338254520\",\"order_status\":\"CAPTURED\",\"order_amount\":\"26225000.00\",\"order_currency\":\"VND\",\"order_description\":\"Thanh toan don hang DH2611214A6B7F\",\"authentication_status\":null,\"created_at\":\"2026-11-21 23:38:35\",\"updated_at\":\"2026-11-21 23:38:39\",\"transactions\":[{\"id\":\"01433b90-e5ca-11f1-8895-0afffe87053b\",\"payment_method\":\"BANK_TRANSFER\",\"transaction_type\":\"PAYMENT\",\"transaction_amount\":\"26225000\",\"transaction_currency\":\"VND\",\"transaction_status\":\"APPROVED\",\"authentication_status\":\"AUTHENTICATION_SUCCESSFUL\",\"card_number\":null,\"card_holder_name\":null,\"card_expiry\":null,\"card_funding_method\":null,\"card_brand\":null,\"transaction_date\":\"2026-11-21 23:38:39\",\"transaction_last_updated_date\":\"2026-11-21 23:38:39\"}]}}', '2026-11-21 23:38:44', '2026-11-21 23:38:25', '2026-11-21 23:38:44'),
(2, 4, 'sepay', '2611220047556170', 1499750, 'success', 'b0e6f79e-e5d3-11f1-8895-0afffe87053b', NULL, 'CAPTURED', '20261122004755', '{\"data\":{\"id\":\"b0e6f79e-e5d3-11f1-8895-0afffe87053b\",\"customer_id\":null,\"order_id\":\"PAY127376B01D94F53352\",\"order_invoice_number\":\"2611220047556170\",\"order_status\":\"CAPTURED\",\"order_amount\":\"1499750.00\",\"order_currency\":\"VND\",\"order_description\":\"Thanh toan don hang DH261122DB7BA5\",\"authentication_status\":null,\"created_at\":\"2026-11-22 00:47:59\",\"updated_at\":\"2026-11-22 00:48:03\",\"transactions\":[{\"id\":\"b37b4ad4-e5d3-11f1-8895-0afffe87053b\",\"payment_method\":\"BANK_TRANSFER\",\"transaction_type\":\"PAYMENT\",\"transaction_amount\":\"1499750\",\"transaction_currency\":\"VND\",\"transaction_status\":\"APPROVED\",\"authentication_status\":\"AUTHENTICATION_SUCCESSFUL\",\"card_number\":null,\"card_holder_name\":null,\"card_expiry\":null,\"card_funding_method\":null,\"card_brand\":null,\"transaction_date\":\"2026-11-22 00:48:03\",\"transaction_last_updated_date\":\"2026-11-22 00:48:03\"}]}}', '2026-11-22 00:48:09', '2026-11-22 00:47:55', '2026-11-22 00:48:09'),
(3, 9, 'sepay', 'TXN2611222298CFCD6864', 26225000, 'success', 'dd3ae768-e63c-11f1-8895-0afffe87053b', NULL, 'CAPTURED', '20261122132028', '{\"data\":{\"id\":\"dd3ae768-e63c-11f1-8895-0afffe87053b\",\"customer_id\":null,\"order_id\":\"PAY127376B0289C2D42AE\",\"order_invoice_number\":\"TXN2611222298CFCD6864\",\"order_status\":\"CAPTURED\",\"order_amount\":\"26225000.00\",\"order_currency\":\"VND\",\"order_description\":\"Thanh toan don hang DH261122CE28B0\",\"authentication_status\":null,\"created_at\":\"2026-11-22 13:20:50\",\"updated_at\":\"2026-11-22 13:21:06\",\"transactions\":[{\"id\":\"e649b7e1-e63c-11f1-8895-0afffe87053b\",\"payment_method\":\"BANK_TRANSFER\",\"transaction_type\":\"PAYMENT\",\"transaction_amount\":\"26225000\",\"transaction_currency\":\"VND\",\"transaction_status\":\"APPROVED\",\"authentication_status\":\"AUTHENTICATION_SUCCESSFUL\",\"card_number\":null,\"card_holder_name\":null,\"card_expiry\":null,\"card_funding_method\":null,\"card_brand\":null,\"transaction_date\":\"2026-11-22 13:21:06\",\"transaction_last_updated_date\":\"2026-11-22 13:21:06\"}]}}', '2026-11-22 13:21:13', '2026-11-22 13:20:28', '2026-11-22 13:21:13'),
(4, 10, 'sepay', '2611222356078362', 12475000, 'pending', NULL, NULL, NULL, '20261122235607', NULL, NULL, '2026-11-22 23:56:07', '2026-11-22 23:56:07');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `description` text NOT NULL,
  `price` int(11) NOT NULL DEFAULT 0,
  `sale_price` int(11) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'active',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `has_variants` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `name`, `slug`, `sku`, `short_description`, `description`, `price`, `sale_price`, `stock`, `image`, `status`, `is_featured`, `has_variants`, `created_at`, `updated_at`, `deleted_at`) VALUES
(29, 6, 'ZEBRASÄV pendant lamp, white/plastic, 18\"', 'zebrasv-pendant-lamp-whiteplastic-18', 'SP0029', 'ZEBRASÄV pendant lamp, white/plastic, 18\" ZEBRASÄV pendant lamp surprises – an asymmetrical shape that looks different from all angles, made from plastic, of which as least 50% is recycled, and just as decorative on or off. Love it for many years!...', 'ZEBRASÄV pendant lamp, white/plastic, 18\" ZEBRASÄV pendant lamp surprises – an asymmetrical shape that looks different from all angles, made from plastic, of which as least 50% is recycled, and just as decorative on or off. Love it for many years!', 249750, NULL, 10, 'https://www.ikea.com/us/en/images/p/26c1ffc49d0915a9/zebrasaev-pendant-lamp-white-plastic/PE933702.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(30, 6, 'ZEBRASÄV pendant lamp, brown/paper, 14\"', 'zebrasv-pendant-lamp-brownpaper-14', 'SP0030', 'ZEBRASÄV pendant lamp, brown/paper, 14\" ZEBRASÄV pendant lamp surprises with its playful asymmetrical shape that looks different based on where you stand. Made from paper pulp, of which as least 90% is recycled, and just as decorative on or off....', 'ZEBRASÄV pendant lamp, brown/paper, 14\" ZEBRASÄV pendant lamp surprises with its playful asymmetrical shape that looks different based on where you stand. Made from paper pulp, of which as least 90% is recycled, and just as decorative on or off.', 299750, NULL, 10, 'https://www.ikea.com/us/en/images/p/76853593a26ddf5b/sandkorn-havsdjup-pendant-lamp-handmade-rattan-black/PH207263.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(31, 11, 'ZAMIOCULCAS potted plant, Aroid palm, 6\"', 'zamioculcas-potted-plant-aroid-palm-6', 'SP0031', 'ZAMIOCULCAS potted plant, Aroid palm, 6\" Plants are perfect roommates – invite them into your home and create your own green oasis where you can wind down from the hustle and bustle of life. And they’re also quiet when it’s time to go to sleep!...', 'ZAMIOCULCAS potted plant, Aroid palm, 6\" Plants are perfect roommates – invite them into your home and create your own green oasis where you can wind down from the hustle and bustle of life. And they’re also quiet when it’s time to go to sleep!', 324750, NULL, 10, 'https://www.ikea.com/us/en/images/p/73b9428a65de9752/zamioculcas-potted-plant-aroid-palm/PE914138.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(32, 16, 'YUPPIENALLE holder for mobile phone, gray', 'yuppienalle-holder-for-mobile-phone-gray', 'SP0032', 'YUPPIENALLE holder for mobile phone, gray This small and foldable holder for mobile phones allow you to have your hands free when watching films or having virtual meetings. Perfect when travelling and easy to grab and bring with you in a flash....', 'YUPPIENALLE holder for mobile phone, gray This small and foldable holder for mobile phones allow you to have your hands free when watching films or having virtual meetings. Perfect when travelling and easy to grab and bring with you in a flash.', 24750, NULL, 10, 'https://www.ikea.com/us/en/images/p/3410c31871aebc59/yuppienalle-holder-for-mobile-phone-gray/PH182934.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(33, 11, 'YUCCA ELEPHANTIPES potted plant, Spineless yucca/2-stem, 9 ½\"', 'yucca-elephantipes-potted-plant-spineless-yucca2-stem-9', 'SP0033', 'YUCCA ELEPHANTIPES potted plant, Spineless yucca/2-stem, 9 ½\" Plants are wonderful roommates that make your home more vibrant. This easy-to-care plant withstands dry environments – so it’s also ideal when you want to enjoy greenery in the office....', 'YUCCA ELEPHANTIPES potted plant, Spineless yucca/2-stem, 9 ½\" Plants are wonderful roommates that make your home more vibrant. This easy-to-care plant withstands dry environments – so it’s also ideal when you want to enjoy greenery in the office.', 749750, NULL, 10, 'https://www.ikea.com/us/en/images/p/20b6e8fb4e3a06d/yucca-elephantipes-potted-plant-spineless-yucca-2-stem/PE895580.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(34, 22, 'YTTERVÅG nightstand, gray-brown, 181/2x161/8\"', 'yttervg-nightstand-gray-brown-1812x1618', 'SP0034', 'YTTERVÅG nightstand, gray-brown, 181/2x161/8\" This bedside table in solid wood stands sturdy on metal legs and has lots of room for everything you want close at hand in the deep drawer and open shelf. Matches YTTERVÅG four-poster bed perfectly. Plent...', 'YTTERVÅG nightstand, gray-brown, 181/2x161/8\" This bedside table in solid wood stands sturdy on metal legs and has lots of room for everything you want close at hand in the deep drawer and open shelf. Matches YTTERVÅG four-poster bed perfectly. Plenty of room in the large drawer for your belongings.', 3749750, NULL, 10, 'https://www.ikea.com/us/en/images/p/3ea1b4929adc1965/storemolla-nightstand-gray-brown-stained/PE982335.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(35, 26, 'YTTERVÅG four-poster bed frame, gray-brown, King', 'yttervg-four-poster-bed-frame-gray-brown-king', 'SP0035', 'YTTERVÅG four-poster bed frame, gray-brown, King You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home....', 'YTTERVÅG four-poster bed frame, gray-brown, King You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home.', 17225000, NULL, 10, 'https://www.ikea.com/us/en/images/p/6f4ae0e91f28923c/yttervag-four-poster-bed-frame-gray-brown/PE982037.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(36, 26, 'YTTERVÅG four-poster bed frame, gray-brown/Lyngör white, Queen', 'yttervg-four-poster-bed-frame-gray-brownlyngr-white-queen', 'SP0036', 'YTTERVÅG four-poster bed frame, gray-brown/Lyngör white, Queen The timeless look of YTTERVÅG four-poster bed frame fits a modern home, yet allows you to sleep like royalty – and LYNGÖR mattress base gives extra height and helps your mattress keep its...', 'YTTERVÅG four-poster bed frame, gray-brown/Lyngör white, Queen The timeless look of YTTERVÅG four-poster bed frame fits a modern home, yet allows you to sleep like royalty – and LYNGÖR mattress base gives extra height and helps your mattress keep its shape for longer.', 20475000, NULL, 10, 'https://www.ikea.com/us/en/images/p/4f1d41873062a8dc/yttervag-four-poster-bed-frame-gray-brown-lyngoer-white/PE953124.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(37, 26, 'YTTERVÅG four-poster bed frame, gray-brown/Lyngör white, King', 'yttervg-four-poster-bed-frame-gray-brownlyngr-white-king', 'SP0037', 'YTTERVÅG four-poster bed frame, gray-brown/Lyngör white, King You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home....', 'YTTERVÅG four-poster bed frame, gray-brown/Lyngör white, King You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home.', 24725000, NULL, 10, 'https://www.ikea.com/us/en/images/p/4f1d41873062a8dc/yttervag-four-poster-bed-frame-gray-brown-lyngoer-white/PE953124.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(38, 26, 'YTTERVÅG four-poster bed frame, gray-brown/Luröy, King', 'yttervg-four-poster-bed-frame-gray-brownlury-king', 'SP0038', 'YTTERVÅG four-poster bed frame, gray-brown/Luröy, King You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home....', 'YTTERVÅG four-poster bed frame, gray-brown/Luröy, King You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home.', 18725000, NULL, 10, 'https://www.ikea.com/us/en/images/p/6f4ae0e91f28923c/yttervag-four-poster-bed-frame-gray-brown-luroey/PE982037.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(39, 26, 'YTTERVÅG four-poster bed frame, gray-brown/Luröy, Queen', 'yttervg-four-poster-bed-frame-gray-brownlury-queen', 'SP0039', 'YTTERVÅG four-poster bed frame, gray-brown/Luröy, Queen You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home....', 'YTTERVÅG four-poster bed frame, gray-brown/Luröy, Queen You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home.', 14975000, NULL, 10, 'https://www.ikea.com/us/en/images/p/6f4ae0e91f28923c/yttervag-four-poster-bed-frame-gray-brown-luroey/PE982037.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(40, 26, 'YTTERVÅG four-poster bed frame, gray-brown/Lönset, King', 'yttervg-four-poster-bed-frame-gray-brownlnset-king', 'SP0040', 'YTTERVÅG four-poster bed frame, gray-brown/Lönset, King You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home....', 'YTTERVÅG four-poster bed frame, gray-brown/Lönset, King You don’t need to live in a castle to sleep like royalty. The timeless and simple expression of this four-poster bed frame in a Scandinavian style fits just as well in a modern home.', 20725000, NULL, 10, 'https://www.ikea.com/us/en/images/p/6f4ae0e91f28923c/yttervag-four-poster-bed-frame-gray-brown-loenset/PE982037.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(41, 30, 'YTBERG / TRÅDFRI lighting kit, white', 'ytberg-trdfri-lighting-kit-white', 'SP0041', 'YTBERG / TRÅDFRI lighting kit, white You can bend, angle and move this slim lamp sideways to direct the soft light precisely over your cabinet or wardrobe. A perfect choice if there is little space between the top and the ceiling. Spreads a soft ligh...', 'YTBERG / TRÅDFRI lighting kit, white You can bend, angle and move this slim lamp sideways to direct the soft light precisely over your cabinet or wardrobe. A perfect choice if there is little space between the top and the ceiling. Spreads a soft light that creates a cosy atmosphere in the room.', 2149750, NULL, 7, 'https://www.ikea.com/us/en/images/p/19e2760789227493/tradfri-starter-kit-smart-wireless-dimmable-white-spectrum/PE756272.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(42, 6, 'YTBERG cabinet lighting, white/dimmable', 'ytberg-cabinet-lighting-whitedimmable', 'SP0042', 'YTBERG cabinet lighting, white/dimmable You can bend, angle and move this slim lamp sideways to direct the soft light precisely over your cabinet or wardrobe. A perfect choice if there is little space between the top and the ceiling. Spreads a soft l...', 'YTBERG cabinet lighting, white/dimmable You can bend, angle and move this slim lamp sideways to direct the soft light precisely over your cabinet or wardrobe. A perfect choice if there is little space between the top and the ceiling. Spreads a soft light that creates a cosy atmosphere in the room.', 450000, NULL, 10, 'https://www.ikea.com/us/en/images/p/78550345d93debdc/ytberg-cabinet-lighting-white-dimmable/PE856686.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(43, 6, 'YTBERG cabinet lighting, black/dimmable', 'ytberg-cabinet-lighting-blackdimmable', 'SP0043', 'YTBERG cabinet lighting, black/dimmable You can bend, angle and move this slim lamp sideways to direct the soft light precisely over your cabinet or wardrobe. A perfect choice if there is little space between the top and the ceiling. Spreads a soft l...', 'YTBERG cabinet lighting, black/dimmable You can bend, angle and move this slim lamp sideways to direct the soft light precisely over your cabinet or wardrobe. A perfect choice if there is little space between the top and the ceiling. Spreads a soft light that creates a cosy atmosphere in the room.', 450000, NULL, 10, 'https://www.ikea.com/us/en/images/p/720d5586391b478b/ytberg-cabinet-lighting-black-dimmable/PE856628.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(44, 37, 'YNGVAR chair, anthracite', 'yngvar-chair-anthracite', 'SP0044', 'YNGVAR chair, anthracite The soft and rounded back support feels like a nice embrace and makes the chair easy to hang on the table edge when you want to sweep the floor after dinner. You sit comfortably thanks to the generous size and the soft rounde...', 'YNGVAR chair, anthracite The soft and rounded back support feels like a nice embrace and makes the chair easy to hang on the table edge when you want to sweep the floor after dinner. You sit comfortably thanks to the generous size and the soft rounded shape of the seat and back support.', 3625000, NULL, 10, 'https://www.ikea.com/us/en/images/p/7201cb4dbd5f9141/yngvar-chair-anthracite/PE846701.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(45, 41, 'YLLEVAD frame, black, 5x7\"', 'yllevad-frame-black-5x7', 'SP0045', 'YLLEVAD frame, black, 5x7\" Together with ALFTA adhesive hook, you can hang this frame without nails or screws. The snap-on function allows you to easily and quickly replace your pictures whenever you like. The frame is made of plastic, which makes it...', 'YLLEVAD frame, black, 5x7\" Together with ALFTA adhesive hook, you can hang this frame without nails or screws. The snap-on function allows you to easily and quickly replace your pictures whenever you like. The frame is made of plastic, which makes it strong, lightweight and easier to handle.', 37250, NULL, 10, 'https://www.ikea.com/us/en/images/p/3dab171a61dc334b/yllevad-frame-black/PE770938.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(46, 41, 'YLLEVAD frame, black, 8 ¼x11 ¾\"', 'yllevad-frame-black-8-x11', 'SP0046', 'YLLEVAD frame, black, 8 ¼x11 ¾\" This frame is ideal to use with ALFTA adhesive hook. With the hook you can easily hang the frame without nails or screws and decorate your walls with pictures. Can be used hanging or standing, both horizontally and ver...', 'YLLEVAD frame, black, 8 ¼x11 ¾\" This frame is ideal to use with ALFTA adhesive hook. With the hook you can easily hang the frame without nails or screws and decorate your walls with pictures. Can be used hanging or standing, both horizontally and vertically, to fit in the space available.', 124750, NULL, 10, 'https://www.ikea.com/us/en/images/p/786f019f8c02795e/yllevad-frame-black/PE781591.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(47, 41, 'YLLEVAD Collage frame for 4 photos, black', 'yllevad-collage-frame-for-4-photos-black', 'SP0047', 'YLLEVAD Collage frame for 4 photos, black. The smart snap-on function allows you to easily fix your pictures in place, and quickly replace them whenever you like. Holds 4 pictures so you can create your own personal collage....', 'YLLEVAD Collage frame for 4 photos, black. The smart snap-on function allows you to easily fix your pictures in place, and quickly replace them whenever you like. Holds 4 pictures so you can create your own personal collage.', 124750, NULL, 10, 'https://www.ikea.com/us/en/images/p/143dfae56ce8b387/roedalm-frame-for-9-pictures-black/PE924271.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(48, 41, 'YLLEVAD art card, flowers-blooming, 4x6\"', 'yllevad-art-card-flowers-blooming-4x6', 'SP0048', 'YLLEVAD art card, flowers-blooming, 4x6\" Artwork makes a difference and adds a personal touch to your home, even if you’re not the artist. With YLLEVAD art cards you can create your own mini-gallery on a wall or shelf in a flash. Motif created by Iri...', 'YLLEVAD art card, flowers-blooming, 4x6\" Artwork makes a difference and adds a personal touch to your home, even if you’re not the artist. With YLLEVAD art cards you can create your own mini-gallery on a wall or shelf in a flash. Motif created by Irika Sandström and Sophie Wiking Ekman.', 24750, NULL, 10, 'https://www.ikea.com/us/en/images/products/vaexbo-art-card-wild-herbs__1416537_pe975626_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(49, 49, 'YGSBO over-the-range convection microwave, Stainless steel, 30\"', 'ygsbo-over-the-range-convection-microwave-stainless-steel-30', 'SP0049', 'YGSBO over-the-range convection microwave, Stainless steel, 30\" A microwave with convection function and range hood all in one efficient appliance – a perfect choice when you’re tight for space. Best of all? The interior surface wipes clean effortles...', 'YGSBO over-the-range convection microwave, Stainless steel, 30\" A microwave with convection function and range hood all in one efficient appliance – a perfect choice when you’re tight for space. Best of all? The interior surface wipes clean effortlessly.', 12475000, NULL, 9, 'https://www.ikea.com/us/en/images/p/4e64d079ae3aa31b/ygsbo-over-the-range-convection-microwave-stainless-steel/PE970369.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(50, 22, 'VUKU wardrobe, white, 291/8x201/8x585/8\"', 'vuku-wardrobe-white-2918x2018x5858', 'SP0050', 'VUKU wardrobe, white, 291/8x201/8x585/8\" Inspired by the ingenuity of tents, we worked with tent suppliers to develop this wardrobe. Great for seasonal clothes or when you need to bring your wardrobe with you. Roll it and bring it along. Easy to brin...', 'VUKU wardrobe, white, 291/8x201/8x585/8\" Inspired by the ingenuity of tents, we worked with tent suppliers to develop this wardrobe. Great for seasonal clothes or when you need to bring your wardrobe with you. Roll it and bring it along. Easy to bring home since it is roll packed.', 324750, NULL, 10, 'https://www.ikea.com/us/en/images/p/906d1eeeb66890e/vuku-wardrobe-white/PH196296.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(51, 41, 'VRIGSTAD mirror, sedge, 24 ¾\"', 'vrigstad-mirror-sedge-24', 'SP0051', 'VRIGSTAD mirror, sedge, 24 ¾\" Embrace nature! This mirror in hand-woven rattan gives your room a depth that makes it feel extra vibrant. Whether you hang up one, several or combine them with paintings, they make a strong impression. Provided with saf...', 'VRIGSTAD mirror, sedge, 24 ¾\" Embrace nature! This mirror in hand-woven rattan gives your room a depth that makes it feel extra vibrant. Whether you hang up one, several or combine them with paintings, they make a strong impression. Provided with safety film - reduces damage if glass is broken.', 1499750, NULL, 9, 'https://www.ikea.com/us/en/images/p/4b6514ac1d035ed6/hovet-mirror-gold/PE985737.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(52, 60, 'VRETSTORP sleeper sofa, Kilanda light beige', 'vretstorp-sleeper-sofa-kilanda-light-beige', 'SP0052', 'VRETSTORP sleeper sofa, Kilanda light beige This sleeper sofa makes it easy to have a good night\'s sleep. Effortless to convert and with practical storage for bedding and pajamas. But most importantly – the comfort makes it hard to keep your eyes ope...', 'VRETSTORP sleeper sofa, Kilanda light beige This sleeper sofa makes it easy to have a good night\'s sleep. Effortless to convert and with practical storage for bedding and pajamas. But most importantly – the comfort makes it hard to keep your eyes open.', 26225000, NULL, 8, 'https://www.ikea.com/us/en/images/p/181caf7533def9fb/kivik-1-seat-sleeper-sofa-kelinge-gray-turquoise/PE890987.jpg?f=xl', 'active', 1, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(53, 60, 'VRETSTORP sleeper sofa, Kilanda dark blue', 'vretstorp-sleeper-sofa-kilanda-dark-blue', 'SP0053', 'VRETSTORP sleeper sofa, Kilanda dark blue This sleeper sofa makes it easy to have a good night\'s sleep. Effortless to convert and with practical storage for bedding and pajamas. But most importantly – the comfort makes it hard to keep your eyes open....', 'VRETSTORP sleeper sofa, Kilanda dark blue This sleeper sofa makes it easy to have a good night\'s sleep. Effortless to convert and with practical storage for bedding and pajamas. But most importantly – the comfort makes it hard to keep your eyes open.', 26225000, NULL, 10, 'https://www.ikea.com/us/en/images/products/vretstorp-sleeper-sofa-kilanda-dark-blue__1155335_pe886565_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(54, 60, 'VRETSTORP cover for sleeper sofa, Hakebo dark gray', 'vretstorp-cover-for-sleeper-sofa-hakebo-dark-gray', 'SP0054', 'VRETSTORP cover for sleeper sofa, Hakebo dark gray This cover will make your sofa even more inviting. It is soft and smooth with a slight structure - and the cover is also easy to remove and machine wash if needed....', 'VRETSTORP cover for sleeper sofa, Hakebo dark gray This cover will make your sofa even more inviting. It is soft and smooth with a slight structure - and the cover is also easy to remove and machine wash if needed.', 2475000, NULL, 10, 'https://www.ikea.com/us/en/images/products/vretstorp-cover-for-sleeper-sofa-hakebo-dark-gray__1194800_pe902059_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(55, 49, 'VOXTORP door, matte white, 24x40\"', 'voxtorp-door-matte-white-24x40', 'SP0055', 'VOXTORP door, matte white, 24x40\" VOXTORP fronts with matte white surface create a modern look and give the impression of light and space. Integrated handles blend perfectly into the design enhancing the minimalist expression. The depth of the handle...', 'VOXTORP door, matte white, 24x40\" VOXTORP fronts with matte white surface create a modern look and give the impression of light and space. Integrated handles blend perfectly into the design enhancing the minimalist expression. The depth of the handle makes it easy to open and close the door.', 6775000, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxtorp-door-matte-white__0638953_pe699267_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(56, 49, 'VOXTORP door, matte white, 12x30\"', 'voxtorp-door-matte-white-12x30', 'SP0056', 'VOXTORP door, matte white, 12x30\" VOXTORP fronts with matte white surface create a modern look and give the impression of light and space. Integrated handles blend perfectly into the design enhancing the minimalist expression. The depth of the handle...', 'VOXTORP door, matte white, 12x30\" VOXTORP fronts with matte white surface create a modern look and give the impression of light and space. Integrated handles blend perfectly into the design enhancing the minimalist expression. The depth of the handle makes it easy to open and close the door.', 3525000, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxtorp-door-matte-white__0638796_pe699159_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(57, 49, 'VOXTORP cover panel, high gloss dark gray-brown, 15x421/2\"', 'voxtorp-cover-panel-high-gloss-dark-gray-brown-15x4212', 'SP0057', 'VOXTORP cover panel, high gloss dark gray-brown, 15x421/2\" Covered with high-gloss foil; gives an easy care finish. 25-year Limited Warranty. Read about the terms in the Limited Warranty brochure....', 'VOXTORP cover panel, high gloss dark gray-brown, 15x421/2\" Covered with high-gloss foil; gives an easy care finish. 25-year Limited Warranty. Read about the terms in the Limited Warranty brochure.', 1925000, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxtorp-cover-panel-high-gloss-dark-gray-brown__1346959_pe959566_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(58, 75, 'VOXNAN bath faucet, tall, chrome plated', 'voxnan-bath-faucet-tall-chrome-plated', 'SP0058', 'VOXNAN bath faucet, tall, chrome plated You save hot water and energy every time you lift the lever. The cold start function avoids the unnecessary use of hot water for daily rituals like washing your hands or brushing your teeth....', 'VOXNAN bath faucet, tall, chrome plated You save hot water and energy every time you lift the lever. The cold start function avoids the unnecessary use of hot water for daily rituals like washing your hands or brushing your teeth.', 3250000, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxnan-bath-faucet-tall-chrome-plated__0755174_pe748301_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(59, 37, 'VOXLÖV / VOXLÖV table and 4 chairs, bamboo/bamboo, 707/8x353/8\"', 'voxlv-voxlv-table-and-4-chairs-bamboobamboo-7078x3538', 'SP0059', 'VOXLÖV / VOXLÖV table and 4 chairs, bamboo/bamboo, 707/8x353/8\" A gift from nature, shaped and bent for optimal beauty and comfort. The sturdy table and chair frames are made of hardwearing bamboo and the handwoven paper twine gives a strong seat wit...', 'VOXLÖV / VOXLÖV table and 4 chairs, bamboo/bamboo, 707/8x353/8\" A gift from nature, shaped and bent for optimal beauty and comfort. The sturdy table and chair frames are made of hardwearing bamboo and the handwoven paper twine gives a strong seat with a resilient feel.', 22499750, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxloev-voxloev-table-and-4-chairs-bamboo-bamboo__0926660_pe789444_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(60, 37, 'VOXLÖV / ODGER table and 4 chairs, bamboo/anthracite, 707/8x353/8\"', 'voxlv-odger-table-and-4-chairs-bambooanthracite-7078x3538', 'SP0060', 'VOXLÖV / ODGER table and 4 chairs, bamboo/anthracite, 707/8x353/8\" Comfortable to sit on thanks to the bowl-shaped seat and rounded shape of the backrest. No tools are required to assemble the chair, you just click it together with a simple mechanism...', 'VOXLÖV / ODGER table and 4 chairs, bamboo/anthracite, 707/8x353/8\" Comfortable to sit on thanks to the bowl-shaped seat and rounded shape of the backrest. No tools are required to assemble the chair, you just click it together with a simple mechanism under the seat.', 22499750, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxloev-odger-table-and-4-chairs-bamboo-anthracite__0997402_pe822662_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(61, 37, 'VOXLÖV dining table, light bamboo, 707/8x353/8\"', 'voxlv-dining-table-light-bamboo-7078x3538', 'SP0061', 'VOXLÖV dining table, light bamboo, 707/8x353/8\" The natural bamboo makes this table light and versatile, but the hardwearing material is also warm and inviting. The bent wood enhances the Scandinavian expression. The rounded corners are a safe choice...', 'VOXLÖV dining table, light bamboo, 707/8x353/8\" The natural bamboo makes this table light and versatile, but the hardwearing material is also warm and inviting. The bent wood enhances the Scandinavian expression. The rounded corners are a safe choice when living with children in smaller spaces.', 9999750, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxloev-dining-table-light-bamboo__0997396_pe822660_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(62, 37, 'VOXLÖV chair, light bamboo', 'voxlv-chair-light-bamboo', 'SP0062', 'VOXLÖV chair, light bamboo A gift from nature, shaped and bent for optimal beauty and comfort. The sturdy frame is made of durable bamboo, and handwoven paper twine creates a strong seat with a resilient feel when you sit on it....', 'VOXLÖV chair, light bamboo A gift from nature, shaped and bent for optimal beauty and comfort. The sturdy frame is made of durable bamboo, and handwoven paper twine creates a strong seat with a resilient feel when you sit on it.', 3125000, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxloev-chair-light-bamboo__0948161_pe798889_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(63, 37, 'VOXLÖV / ÄLVSTA table and 4 chairs, light bamboo/rattan white, 707/8x353/8\"', 'voxlv-lvsta-table-and-4-chairs-light-bamboorattan-white-7078x3538', 'SP0063', 'VOXLÖV / ÄLVSTA table and 4 chairs, light bamboo/rattan white, 707/8x353/8\" The natural bamboo makes this table light and versatile, but the hardwearing material is also warm and inviting. The bent wood enhances the Scandinavian expression....', 'VOXLÖV / ÄLVSTA table and 4 chairs, light bamboo/rattan white, 707/8x353/8\" The natural bamboo makes this table light and versatile, but the hardwearing material is also warm and inviting. The bent wood enhances the Scandinavian expression.', 19999750, NULL, 10, 'https://www.ikea.com/us/en/images/products/voxloev-aelvsta-table-and-4-chairs-light-bamboo-rattan-white__1121766_pe874359_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(64, 87, 'VONSBÄK rug, low pile, dark blue, 5\'7\"x7\'7\"', 'vonsbk-rug-low-pile-dark-blue-57x77', 'SP0064', 'VONSBÄK rug, low pile, dark blue, 5\'7\"x7\'7\" The vintage expression has the charm of looking worn and adds a special character to the room. The pile is very low and works just as well by the sofa as under the dining table. The pattern looks faded and ...', 'VONSBÄK rug, low pile, dark blue, 5\'7\"x7\'7\" The vintage expression has the charm of looking worn and adds a special character to the room. The pile is very low and works just as well by the sofa as under the dining table. The pattern looks faded and worn, which gives the rug a vintage look.', 2499750, NULL, 10, 'https://www.ikea.com/us/en/images/products/vonsbaek-rug-low-pile-dark-blue__1062215_pe850715_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(65, 87, 'VONSBÄK rug, low pile, dark blue, 6\'7\"x9\'10\"', 'vonsbk-rug-low-pile-dark-blue-67x910', 'SP0065', 'VONSBÄK rug, low pile, dark blue, 6\'7\"x9\'10\" The vintage expression has the charm of looking worn and adds a special character to the room. The pile is very low and works just as well by the sofa as under the dining table. The pattern looks faded and...', 'VONSBÄK rug, low pile, dark blue, 6\'7\"x9\'10\" The vintage expression has the charm of looking worn and adds a special character to the room. The pile is very low and works just as well by the sofa as under the dining table. The pattern looks faded and worn, which gives the rug a vintage look.', 3249750, NULL, 10, 'https://www.ikea.com/us/en/images/products/vonsbaek-rug-low-pile-dark-blue__1062215_pe850715_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(66, 87, 'VOLLERSLEV rug, high pile, white, 6\'7\"x9\'10\"', 'vollerslev-rug-high-pile-white-67x910', 'SP0066', 'VOLLERSLEV rug, high pile, white, 6\'7\"x9\'10\" A new take on traditional shaggy! The long white pile is woven with varying yarn thicknesses to create a soft, irresistible texture that is also cozy to sit, walk, play or lie on. Extra thick and comfy und...', 'VOLLERSLEV rug, high pile, white, 6\'7\"x9\'10\" A new take on traditional shaggy! The long white pile is woven with varying yarn thicknesses to create a soft, irresistible texture that is also cozy to sit, walk, play or lie on. Extra thick and comfy under your feet.', 6249750, NULL, 10, 'https://www.ikea.com/us/en/images/products/vollerslev-rug-high-pile-white__0933663_pe792171_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(67, 92, 'VÖRDA vegetable knife, black, 6\"', 'vrda-vegetable-knife-black-6', 'SP0067', 'VÖRDA vegetable knife, black, 6\" Why is it smart to have a special knife for vegetables? The answer is simple – the indentations on the blade prevent carrot slices from sticking when you slice. A small detail that makes a big difference. Materials in...', 'VÖRDA vegetable knife, black, 6\" Why is it smart to have a special knife for vegetables? The answer is simple – the indentations on the blade prevent carrot slices from sticking when you slice. A small detail that makes a big difference. Materials in the handle provide a firm grip.', 324750, NULL, 10, 'https://www.ikea.com/us/en/images/products/voerda-vegetable-knife-black__0710385_pe727513_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(68, 92, 'VÖRDA utility knife, black, 6\"', 'vrda-utility-knife-black-6', 'SP0068', 'VÖRDA utility knife, black, 6\" Cut, trim and dice – a utility knife can handle most jobs. Sharp, well-balanced and with an ergonomically shaped handle that gives you a good grip. For easy slicing and trimming. Materials in the handle provide a firm g...', 'VÖRDA utility knife, black, 6\" Cut, trim and dice – a utility knife can handle most jobs. Sharp, well-balanced and with an ergonomically shaped handle that gives you a good grip. For easy slicing and trimming. Materials in the handle provide a firm grip.', 224750, NULL, 10, 'https://www.ikea.com/us/en/images/products/voerda-utility-knife-black__0710376_pe727504_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(69, 92, 'VÖRDA 3-piece knife set', 'vrda-3-piece-knife-set', 'SP0069', 'VÖRDA 3-piece knife set This knife set is a whiz in the kitchen – from chopping onions to cutting up a fried chicken fillet or an oven-baked head of cauliflower. There’s also a paring knife in the same series for the small jobs....', 'VÖRDA 3-piece knife set This knife set is a whiz in the kitchen – from chopping onions to cutting up a fried chicken fillet or an oven-baked head of cauliflower. There’s also a paring knife in the same series for the small jobs.', 624750, NULL, 10, 'https://www.ikea.com/us/en/images/products/voerda-3-piece-knife-set__0448546_pe598209_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(70, 16, 'VIVALLA tablet stand, bamboo, 101/4x63/4\"', 'vivalla-tablet-stand-bamboo-1014x634', 'SP0070', 'VIVALLA tablet stand, bamboo, 101/4x63/4\" This bamboo holder creates a warm expression in your kitchen. Place it on the countertop or hang it on KUNGSFORS or SUNNERSTA rail to free up space and put your tablet or cookbook at eye-height....', 'VIVALLA tablet stand, bamboo, 101/4x63/4\" This bamboo holder creates a warm expression in your kitchen. Place it on the countertop or hang it on KUNGSFORS or SUNNERSTA rail to free up space and put your tablet or cookbook at eye-height.', 374750, NULL, 10, 'https://www.ikea.com/us/en/images/products/vivalla-tablet-stand-bamboo__0713732_pe729730_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(71, 99, 'VITVAL loft bed frame with desk top, white/light gray, Twin', 'vitval-loft-bed-frame-with-desk-top-whitelight-gray-twin', 'SP0071', 'VITVAL loft bed frame with desk top, white/light gray, Twin It is the details that make this loft bed unique. Soft shapes, fabric guardrails and a desk with a practical pegboard. You can also accessorize with products from the MÖJLIGHET and SKÅDIS se...', 'VITVAL loft bed frame with desk top, white/light gray, Twin It is the details that make this loft bed unique. Soft shapes, fabric guardrails and a desk with a practical pegboard. You can also accessorize with products from the MÖJLIGHET and SKÅDIS series.', 7475000, NULL, 10, 'https://www.ikea.com/us/en/images/products/vitval-loft-bed-frame-with-desk-top-white-light-gray__0688141_pe722337_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(72, 99, 'VITVAL loft bed frame, white/light gray, Twin', 'vitval-loft-bed-frame-whitelight-gray-twin', 'SP0072', 'VITVAL loft bed frame, white/light gray, Twin It is the details that make this loft bed unique. Soft shapes, sturdy ladder and fabric guardrails with yellow accents. You can also dress it up with bedlinens and accessories from the MÖJLIGHET series....', 'VITVAL loft bed frame, white/light gray, Twin It is the details that make this loft bed unique. Soft shapes, sturdy ladder and fabric guardrails with yellow accents. You can also dress it up with bedlinens and accessories from the MÖJLIGHET series.', 6225000, NULL, 10, 'https://www.ikea.com/us/en/images/products/vitval-loft-bed-frame-white-light-gray__0688128_pe722324_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(73, 75, 'VISSLAÅN drawer organizers, set of 3, gray', 'visslan-drawer-organizers-set-of-3-gray', 'SP0073', 'VISSLAÅN drawer organizers, set of 3, gray Open your drawers and treat yourself to some inner peace. This set of 3 organizers is ideal for holding toothbrushes and other things you need nearby. Pairs perfectly with other VISSLAÅN drawer organizers....', 'VISSLAÅN drawer organizers, set of 3, gray Open your drawers and treat yourself to some inner peace. This set of 3 organizers is ideal for holding toothbrushes and other things you need nearby. Pairs perfectly with other VISSLAÅN drawer organizers.', 174750, NULL, 10, 'https://www.ikea.com/us/en/images/products/visslaan-drawer-organizers-set-of-3-gray__1499227_pe1006532_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(74, 60, 'VISKAFORS sofa, Lejde light beige/brown', 'viskafors-sofa-lejde-light-beigebrown', 'SP0074', 'VISKAFORS sofa, Lejde light beige/brown VISKAFORS is a sofa that becomes a statement piece in the room. The classic design has generous, inviting shapes – and when you sink into the deep soft seat, the sofa gives you an embracing feel....', 'VISKAFORS sofa, Lejde light beige/brown VISKAFORS is a sofa that becomes a statement piece in the room. The classic design has generous, inviting shapes – and when you sink into the deep soft seat, the sofa gives you an embracing feel.', 31225000, NULL, 10, 'https://www.ikea.com/us/en/images/products/viskafors-sofa-lejde-light-beige-brown__1088069_pe861044_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(75, 60, 'VISKAFORS loveseat, Lejde light beige/brown', 'viskafors-loveseat-lejde-light-beigebrown', 'SP0075', 'VISKAFORS loveseat, Lejde light beige/brown VISKAFORS is a sofa that becomes a statement piece in the room. The classic design has generous, inviting shapes – and when you sink into the deep soft seat, the sofa gives you an embracing feel....', 'VISKAFORS loveseat, Lejde light beige/brown VISKAFORS is a sofa that becomes a statement piece in the room. The classic design has generous, inviting shapes – and when you sink into the deep soft seat, the sofa gives you an embracing feel.', 28725000, NULL, 10, 'https://www.ikea.com/us/en/images/products/viskafors-loveseat-lejde-light-beige-brown__1088057_pe861046_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(76, 60, 'VISKAFORS 1.5-seat armchair, Lejde light beige/birch', 'viskafors-15-seat-armchair-lejde-light-beigebirch', 'SP0076', 'VISKAFORS 1.5-seat armchair, Lejde light beige/birch VISKAFORS is an armchair that becomes a statement piece in the room. The classic design has generous, inviting shapes – and when you sink into the deep soft seat, the sofa gives you an embracing fe...', 'VISKAFORS 1.5-seat armchair, Lejde light beige/birch VISKAFORS is an armchair that becomes a statement piece in the room. The classic design has generous, inviting shapes – and when you sink into the deep soft seat, the sofa gives you an embracing feel.', 19975000, NULL, 10, 'https://www.ikea.com/us/en/images/products/viskafors-1-5-seat-armchair-lejde-light-beige-birch__1088077_pe861049_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL),
(77, 41, 'VISBÄCK Poster hanger, bamboo, 15 3/4\"', 'visbck-poster-hanger-bamboo-15-34', 'SP0077', 'VISBÄCK Poster hanger, bamboo, 15 3/4\". With the two included bars, you can choose to have one on the top and one on the bottom of your chosen poster, either you can have the poster hanger only on the top, and then you can have two different motifs....', 'VISBÄCK Poster hanger, bamboo, 15 3/4\". With the two included bars, you can choose to have one on the top and one on the bottom of your chosen poster, either you can have the poster hanger only on the top, and then you can have two different motifs.', 199750, NULL, 10, 'https://www.ikea.com/us/en/images/products/visbaeck-poster-hanger-bamboo__0661105_pe711340_s5.jpg?f=xl', 'active', 0, 0, '2026-11-21 23:06:04', '2026-11-21 23:06:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `product_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_categories`
--

INSERT INTO `product_categories` (`product_id`, `category_id`) VALUES
(29, 6),
(29, 7),
(29, 8),
(29, 9),
(30, 6),
(30, 7),
(30, 8),
(30, 9),
(31, 11),
(31, 12),
(31, 13),
(31, 14),
(32, 16),
(32, 17),
(32, 18),
(33, 11),
(33, 12),
(33, 13),
(33, 20),
(34, 22),
(34, 23),
(34, 24),
(35, 26),
(35, 27),
(35, 28),
(36, 26),
(36, 27),
(36, 28),
(37, 26),
(37, 27),
(37, 28),
(38, 26),
(38, 27),
(38, 28),
(39, 26),
(39, 27),
(39, 28),
(40, 26),
(40, 27),
(40, 28),
(41, 30),
(41, 31),
(41, 32),
(42, 6),
(42, 34),
(42, 35),
(43, 6),
(43, 34),
(43, 35),
(44, 37),
(44, 38),
(44, 39),
(45, 41),
(45, 42),
(45, 43),
(46, 41),
(46, 42),
(46, 43),
(47, 41),
(47, 42),
(47, 45),
(48, 41),
(48, 42),
(48, 47),
(49, 49),
(49, 50),
(49, 51),
(49, 52),
(50, 22),
(50, 54),
(50, 55),
(51, 41),
(51, 57),
(51, 58),
(52, 60),
(52, 61),
(52, 62),
(53, 60),
(53, 61),
(53, 62),
(54, 60),
(54, 64),
(55, 49),
(55, 67),
(56, 49),
(56, 67),
(57, 49),
(57, 67),
(58, 75),
(58, 76),
(59, 37),
(59, 38),
(59, 78),
(59, 79),
(60, 37),
(60, 38),
(60, 78),
(60, 79),
(61, 37),
(61, 38),
(61, 82),
(61, 83),
(62, 37),
(62, 38),
(62, 39),
(63, 37),
(63, 38),
(63, 78),
(63, 79),
(64, 87),
(64, 88),
(64, 89),
(65, 87),
(65, 88),
(65, 89),
(66, 87),
(66, 88),
(66, 89),
(67, 92),
(67, 93),
(67, 94),
(68, 92),
(68, 93),
(68, 94),
(69, 92),
(69, 93),
(69, 94),
(70, 16),
(70, 17),
(70, 18),
(71, 99),
(71, 100),
(71, 101),
(71, 102),
(72, 99),
(72, 100),
(72, 101),
(72, 102),
(73, 75),
(73, 105),
(74, 60),
(74, 107),
(74, 108),
(75, 60),
(75, 107),
(75, 110),
(76, 60),
(76, 112),
(76, 113),
(77, 41),
(77, 42),
(77, 43);

-- --------------------------------------------------------

--
-- Table structure for table `product_options`
--

CREATE TABLE `product_options` (
  `id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `option_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_option_values`
--

CREATE TABLE `product_option_values` (
  `id` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `option_value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_status`
--

CREATE TABLE `product_status` (
  `id` int(11) NOT NULL,
  `status_name` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_status`
--

INSERT INTO `product_status` (`id`, `status_name`) VALUES
(1, 0),
(2, 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `option_key` varchar(255) DEFAULT 'default',
  `title` varchar(255) DEFAULT 'Tiêu chuẩn',
  `sku` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sale_price` int(11) DEFAULT NULL,
  `stock` int(11) NOT NULL,
  `product_status_id` int(11) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `option_key`, `title`, `sku`, `price`, `sale_price`, `stock`, `product_status_id`, `image`, `deleted_at`, `created_at`, `updated_at`) VALUES
(1, 29, 'default', 'Tiêu chuẩn', NULL, 249750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/26c1ffc49d0915a9/zebrasaev-pendant-lamp-white-plastic/PE933702.jpg?f=xl', NULL, NULL, NULL),
(2, 30, 'default', 'Tiêu chuẩn', NULL, 299750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/76853593a26ddf5b/sandkorn-havsdjup-pendant-lamp-handmade-rattan-black/PH207263.jpg?f=xl', NULL, NULL, NULL),
(3, 31, 'default', 'Tiêu chuẩn', NULL, 324750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/73b9428a65de9752/zamioculcas-potted-plant-aroid-palm/PE914138.jpg?f=xl', NULL, NULL, NULL),
(4, 32, 'default', 'Tiêu chuẩn', NULL, 24750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/3410c31871aebc59/yuppienalle-holder-for-mobile-phone-gray/PH182934.jpg?f=xl', NULL, NULL, NULL),
(5, 33, 'default', 'Tiêu chuẩn', NULL, 749750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/20b6e8fb4e3a06d/yucca-elephantipes-potted-plant-spineless-yucca-2-stem/PE895580.jpg?f=xl', NULL, NULL, NULL),
(6, 34, 'default', 'Tiêu chuẩn', NULL, 3749750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/3ea1b4929adc1965/storemolla-nightstand-gray-brown-stained/PE982335.jpg?f=xl', NULL, NULL, NULL),
(7, 35, 'default', 'Tiêu chuẩn', NULL, 17225000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/6f4ae0e91f28923c/yttervag-four-poster-bed-frame-gray-brown/PE982037.jpg?f=xl', NULL, NULL, NULL),
(8, 36, 'default', 'Tiêu chuẩn', NULL, 20475000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/4f1d41873062a8dc/yttervag-four-poster-bed-frame-gray-brown-lyngoer-white/PE953124.jpg?f=xl', NULL, NULL, NULL),
(9, 37, 'default', 'Tiêu chuẩn', NULL, 24725000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/4f1d41873062a8dc/yttervag-four-poster-bed-frame-gray-brown-lyngoer-white/PE953124.jpg?f=xl', NULL, NULL, NULL),
(10, 38, 'default', 'Tiêu chuẩn', NULL, 18725000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/6f4ae0e91f28923c/yttervag-four-poster-bed-frame-gray-brown-luroey/PE982037.jpg?f=xl', NULL, NULL, NULL),
(11, 39, 'default', 'Tiêu chuẩn', NULL, 14975000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/6f4ae0e91f28923c/yttervag-four-poster-bed-frame-gray-brown-luroey/PE982037.jpg?f=xl', NULL, NULL, NULL),
(12, 40, 'default', 'Tiêu chuẩn', NULL, 20725000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/6f4ae0e91f28923c/yttervag-four-poster-bed-frame-gray-brown-loenset/PE982037.jpg?f=xl', NULL, NULL, NULL),
(14, 41, 'default', 'Tiêu chuẩn', NULL, 2149750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/475ed80a5853188b/ytberg-cabinet-lighting-white-dimmable/PH199007.jpg?f=xl', NULL, NULL, NULL),
(15, 42, 'default', 'Tiêu chuẩn', NULL, 450000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/78550345d93debdc/ytberg-cabinet-lighting-white-dimmable/PE856686.jpg?f=xl', NULL, NULL, NULL),
(16, 43, 'default', 'Tiêu chuẩn', NULL, 450000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/720d5586391b478b/ytberg-cabinet-lighting-black-dimmable/PE856628.jpg?f=xl', NULL, NULL, NULL),
(17, 44, 'default', 'Tiêu chuẩn', NULL, 3625000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/7201cb4dbd5f9141/yngvar-chair-anthracite/PE846701.jpg?f=xl', NULL, NULL, NULL),
(18, 45, 'default', 'Tiêu chuẩn', NULL, 37250.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/3dab171a61dc334b/yllevad-frame-black/PE770938.jpg?f=xl', NULL, NULL, NULL),
(19, 46, 'default', 'Tiêu chuẩn', NULL, 124750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/786f019f8c02795e/yllevad-frame-black/PE781591.jpg?f=xl', NULL, NULL, NULL),
(20, 47, 'default', 'Tiêu chuẩn', NULL, 124750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/143dfae56ce8b387/roedalm-frame-for-9-pictures-black/PE924271.jpg?f=xl', NULL, NULL, NULL),
(21, 48, 'default', 'Tiêu chuẩn', NULL, 24750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vaexbo-art-card-wild-herbs__1416537_pe975626_s5.jpg?f=xl', NULL, NULL, NULL),
(22, 49, 'default', 'Tiêu chuẩn', NULL, 12475000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/4e64d079ae3aa31b/ygsbo-over-the-range-convection-microwave-stainless-steel/PE970369.jpg?f=xl', NULL, NULL, NULL),
(23, 50, 'default', 'Tiêu chuẩn', NULL, 324750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/906d1eeeb66890e/vuku-wardrobe-white/PH196296.jpg?f=xl', NULL, NULL, NULL),
(24, 51, 'default', 'Tiêu chuẩn', NULL, 1499750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/4b6514ac1d035ed6/hovet-mirror-gold/PE985737.jpg?f=xl', NULL, NULL, NULL),
(25, 52, 'default', 'Tiêu chuẩn', NULL, 26225000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/p/181caf7533def9fb/kivik-1-seat-sleeper-sofa-kelinge-gray-turquoise/PE890987.jpg?f=xl', NULL, NULL, NULL),
(26, 53, 'default', 'Tiêu chuẩn', NULL, 26225000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vretstorp-sleeper-sofa-kilanda-dark-blue__1155335_pe886565_s5.jpg?f=xl', NULL, NULL, NULL),
(27, 54, 'default', 'Tiêu chuẩn', NULL, 2475000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vretstorp-cover-for-sleeper-sofa-hakebo-dark-gray__1194800_pe902059_s5.jpg?f=xl', NULL, NULL, NULL),
(28, 55, 'default', 'Tiêu chuẩn', NULL, 6775000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxtorp-door-matte-white__0638953_pe699267_s5.jpg?f=xl', NULL, NULL, NULL),
(29, 56, 'default', 'Tiêu chuẩn', NULL, 3525000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxtorp-door-matte-white__0638796_pe699159_s5.jpg?f=xl', NULL, NULL, NULL),
(30, 57, 'default', 'Tiêu chuẩn', NULL, 1925000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxtorp-cover-panel-high-gloss-dark-gray-brown__1346959_pe959566_s5.jpg?f=xl', NULL, NULL, NULL),
(31, 58, 'default', 'Tiêu chuẩn', NULL, 3250000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxnan-bath-faucet-tall-chrome-plated__0755174_pe748301_s5.jpg?f=xl', NULL, NULL, NULL),
(32, 59, 'default', 'Tiêu chuẩn', NULL, 22499750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxloev-voxloev-table-and-4-chairs-bamboo-bamboo__0926660_pe789444_s5.jpg?f=xl', NULL, NULL, NULL),
(33, 60, 'default', 'Tiêu chuẩn', NULL, 22499750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxloev-odger-table-and-4-chairs-bamboo-anthracite__0997402_pe822662_s5.jpg?f=xl', NULL, NULL, NULL),
(34, 61, 'default', 'Tiêu chuẩn', NULL, 9999750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxloev-dining-table-light-bamboo__0997396_pe822660_s5.jpg?f=xl', NULL, NULL, NULL),
(35, 62, 'default', 'Tiêu chuẩn', NULL, 3125000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxloev-chair-light-bamboo__0948161_pe798889_s5.jpg?f=xl', NULL, NULL, NULL),
(36, 63, 'default', 'Tiêu chuẩn', NULL, 19999750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voxloev-aelvsta-table-and-4-chairs-light-bamboo-rattan-white__1121766_pe874359_s5.jpg?f=xl', NULL, NULL, NULL),
(37, 64, 'default', 'Tiêu chuẩn', NULL, 2499750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vonsbaek-rug-low-pile-dark-blue__1062215_pe850715_s5.jpg?f=xl', NULL, NULL, NULL),
(38, 65, 'default', 'Tiêu chuẩn', NULL, 3249750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vonsbaek-rug-low-pile-dark-blue__1062215_pe850715_s5.jpg?f=xl', NULL, NULL, NULL),
(39, 66, 'default', 'Tiêu chuẩn', NULL, 6249750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vollerslev-rug-high-pile-white__0933663_pe792171_s5.jpg?f=xl', NULL, NULL, NULL),
(40, 67, 'default', 'Tiêu chuẩn', NULL, 324750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voerda-vegetable-knife-black__0710385_pe727513_s5.jpg?f=xl', NULL, NULL, NULL),
(41, 68, 'default', 'Tiêu chuẩn', NULL, 224750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voerda-utility-knife-black__0710376_pe727504_s5.jpg?f=xl', NULL, NULL, NULL),
(42, 69, 'default', 'Tiêu chuẩn', NULL, 624750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/voerda-3-piece-knife-set__0448546_pe598209_s5.jpg?f=xl', NULL, NULL, NULL),
(43, 70, 'default', 'Tiêu chuẩn', NULL, 374750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vivalla-tablet-stand-bamboo__0713732_pe729730_s5.jpg?f=xl', NULL, NULL, NULL),
(44, 71, 'default', 'Tiêu chuẩn', NULL, 7475000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vitval-loft-bed-frame-with-desk-top-white-light-gray__0688141_pe722337_s5.jpg?f=xl', NULL, NULL, NULL),
(45, 72, 'default', 'Tiêu chuẩn', NULL, 6225000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/vitval-loft-bed-frame-white-light-gray__0688128_pe722324_s5.jpg?f=xl', NULL, NULL, NULL),
(46, 73, 'default', 'Tiêu chuẩn', NULL, 174750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/visslaan-drawer-organizers-set-of-3-gray__1499227_pe1006532_s5.jpg?f=xl', NULL, NULL, NULL),
(47, 74, 'default', 'Tiêu chuẩn', NULL, 31225000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/viskafors-sofa-lejde-light-beige-brown__1088069_pe861044_s5.jpg?f=xl', NULL, NULL, NULL),
(48, 75, 'default', 'Tiêu chuẩn', NULL, 28725000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/viskafors-loveseat-lejde-light-beige-brown__1088057_pe861046_s5.jpg?f=xl', NULL, NULL, NULL),
(49, 76, 'default', 'Tiêu chuẩn', NULL, 19975000.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/viskafors-1-5-seat-armchair-lejde-light-beige-birch__1088077_pe861049_s5.jpg?f=xl', NULL, NULL, NULL),
(50, 77, 'default', 'Tiêu chuẩn', NULL, 199750.00, NULL, 10, 1, 'https://www.ikea.com/us/en/images/products/visbaeck-poster-hanger-bamboo__0661105_pe711340_s5.jpg?f=xl', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_variant_values`
--

CREATE TABLE `product_variant_values` (
  `id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `option_value_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role`) VALUES
(1, 'admin'),
(2, 'customer');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `key` varchar(191) NOT NULL,
  `value` longtext DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'customer',
  `role_id` int(11) DEFAULT 2,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `password`, `email`, `phone`, `address`, `role`, `role_id`, `created_at`, `updated_at`) VALUES
(7, 'Quản trị viên', '$2y$10$5WBeTcdJAjSF/k3rLdzyJOzHHtAh0J5rTtH8ROzUQWE6HaytSGnY2', 'admin@example.com', '0901234567', 'Hà Nội', 'admin', 1, '2026-11-21 23:06:05', '2026-11-21 23:06:05'),
(8, 'Khách hàng Demo', '$2y$10$.R1lqv2bDoCAZINLDmpdVehO411IMioXWIp1e2bdV4Ajn4NV0nzaS', 'customer@example.com', '0987654321', 'TP. Hồ Chí Minh', 'customer', 2, '2026-11-21 23:06:05', '2026-11-21 23:06:05'),
(9, 'Admin', '$2y$10$I2r0UlqncyeGjxtcNgxmBuHOzBTQX8U0n901SWvy0Q8fWhXHI5q8q', 'admin@gmail.com', '0901234567', 'Hà Nội', 'admin', 1, '2026-11-21 23:06:43', '2026-11-21 23:06:43'),
(10, 'Customer', '$2y$10$I2r0UlqncyeGjxtcNgxmBuHOzBTQX8U0n901SWvy0Q8fWhXHI5q8q', 'customer@gmail.com', '0987654321', 'TP. Hồ Chí Minh', 'customer', 2, '2026-11-21 23:06:43', '2026-11-21 23:06:43'),
(12, 'Test User', '$2y$10$2y76fOjy/bP9VN7L20BYYe5JWKoryZPxEHYdxIyGVh5WC5SxWVvyW', 'testuser1795326367@example.com', '0901234567', NULL, 'customer', 2, '2026-11-22 12:46:07', '2026-11-22 12:46:07'),
(13, 'Test User', '$2y$10$iHx0XqTHsRcL.TtH45IIvO3nu51mXjoicrrsazHt0R5JPKv02tGQ6', 'testuser1795327884@example.com', '0901234567', NULL, 'customer', 2, '2026-11-22 13:11:24', '2026-11-22 13:11:24');

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int(11) NOT NULL,
  `code` varchar(255) NOT NULL,
  `voucher_type` int(11) NOT NULL,
  `discount_type` int(11) NOT NULL,
  `discount_value` int(11) DEFAULT NULL,
  `min_spending` int(11) NOT NULL,
  `max_usage_per_user` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_categories`
--

CREATE TABLE `voucher_categories` (
  `voucher_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_products`
--

CREATE TABLE `voucher_products` (
  `voucher_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voucher_types`
--

CREATE TABLE `voucher_types` (
  `id` int(11) NOT NULL,
  `voucher_type` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_addresses_users` (`user_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_carts_users` (`user_id`);

--
-- Indexes for table `cart_details`
--
ALTER TABLE `cart_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cart_details_carts` (`cart_id`),
  ADD KEY `fk_cart_details_products` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comments_users` (`user_id`),
  ADD KEY `fk_comments_products` (`product_id`);

--
-- Indexes for table `discount_types`
--
ALTER TABLE `discount_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_code_unique` (`code`),
  ADD KEY `fk_orders_users` (`user_id`);

--
-- Indexes for table `order_info`
--
ALTER TABLE `order_info`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_order_info_products` (`product_id`),
  ADD KEY `fk_order_info_orders` (`order_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_items_order_index` (`order_id`);

--
-- Indexes for table `order_status`
--
ALTER TABLE `order_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orders_order_status` (`order_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_gateway_txn` (`gateway`,`txn_ref`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_sku_unique` (`sku`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`product_id`,`category_id`),
  ADD KEY `fk_product_categories_categories` (`category_id`);

--
-- Indexes for table `product_options`
--
ALTER TABLE `product_options`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_option_values`
--
ALTER TABLE `product_option_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_options_option_values` (`option_id`);

--
-- Indexes for table `product_status`
--
ALTER TABLE `product_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `variants_product_option_key_unique` (`product_id`,`option_key`),
  ADD KEY `fk_variants_products` (`product_id`),
  ADD KEY `fk_variants_status` (`product_status_id`);

--
-- Indexes for table `product_variant_values`
--
ALTER TABLE `product_variant_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_variant_values_variants` (`variant_id`),
  ADD KEY `fk_variant_values_option_values` (`option_value_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_roles` (`role_id`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `fk_vouchers_voucher_types` (`voucher_type`),
  ADD KEY `fk_vouchers_discount_types` (`discount_type`);

--
-- Indexes for table `voucher_categories`
--
ALTER TABLE `voucher_categories`
  ADD PRIMARY KEY (`voucher_id`,`category_id`),
  ADD KEY `fk_voucher_categories_categories` (`category_id`);

--
-- Indexes for table `voucher_products`
--
ALTER TABLE `voucher_products`
  ADD PRIMARY KEY (`voucher_id`,`product_id`),
  ADD KEY `fk_voucher_products_products` (`product_id`);

--
-- Indexes for table `voucher_types`
--
ALTER TABLE `voucher_types`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_details`
--
ALTER TABLE `cart_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `discount_types`
--
ALTER TABLE `discount_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `order_info`
--
ALTER TABLE `order_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_status`
--
ALTER TABLE `order_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `product_options`
--
ALTER TABLE `product_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_option_values`
--
ALTER TABLE `product_option_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_status`
--
ALTER TABLE `product_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `product_variant_values`
--
ALTER TABLE `product_variant_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `voucher_types`
--
ALTER TABLE `voucher_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `fk_addresses_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_carts_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cart_details`
--
ALTER TABLE `cart_details`
  ADD CONSTRAINT `fk_cart_details_carts` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`),
  ADD CONSTRAINT `fk_cart_details_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `fk_comments_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_comments_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `order_info`
--
ALTER TABLE `order_info`
  ADD CONSTRAINT `fk_order_info_orders` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `fk_order_info_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `order_status`
--
ALTER TABLE `order_status`
  ADD CONSTRAINT `fk_orders_order_status` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`);

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `fk_product_categories_categories` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `fk_product_categories_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `product_option_values`
--
ALTER TABLE `product_option_values`
  ADD CONSTRAINT `fk_options_option_values` FOREIGN KEY (`option_id`) REFERENCES `product_options` (`id`);

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `fk_variants_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_variants_status` FOREIGN KEY (`product_status_id`) REFERENCES `product_status` (`id`);

--
-- Constraints for table `product_variant_values`
--
ALTER TABLE `product_variant_values`
  ADD CONSTRAINT `fk_variant_values_option_values` FOREIGN KEY (`option_value_id`) REFERENCES `product_option_values` (`id`),
  ADD CONSTRAINT `fk_variant_values_variants` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD CONSTRAINT `fk_vouchers_discount_types` FOREIGN KEY (`discount_type`) REFERENCES `discount_types` (`id`),
  ADD CONSTRAINT `fk_vouchers_voucher_types` FOREIGN KEY (`voucher_type`) REFERENCES `voucher_types` (`id`);

--
-- Constraints for table `voucher_categories`
--
ALTER TABLE `voucher_categories`
  ADD CONSTRAINT `fk_voucher_categories_categories` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `fk_voucher_categories_vouchers` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`);

--
-- Constraints for table `voucher_products`
--
ALTER TABLE `voucher_products`
  ADD CONSTRAINT `fk_voucher_products_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `fk_voucher_products_vouchers` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
