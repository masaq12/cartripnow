-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 25, 2025 at 05:20 PM
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
-- Database: `car_trip_now`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_actions`
--

CREATE TABLE `admin_actions` (
  `action_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_actions`
--

INSERT INTO `admin_actions` (`action_id`, `admin_id`, `action_type`, `target_type`, `target_id`, `description`, `created_at`) VALUES
(1, 1, 'release_payment', 'trip', 1, 'Manually released payment for trip #1', '2025-12-25 13:26:41');

-- --------------------------------------------------------

--
-- Table structure for table `disputes`
--

CREATE TABLE `disputes` (
  `dispute_id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `raised_by` int(11) NOT NULL,
  `dispute_type` enum('payment','vehicle_damage','mileage','cancellation','late_return','deposit','other') NOT NULL,
  `description` text NOT NULL,
  `evidence` text DEFAULT NULL,
  `status` enum('open','investigating','resolved','closed') DEFAULT 'open',
  `resolution` text DEFAULT NULL,
  `resolution_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escrow`
--

CREATE TABLE `escrow` (
  `escrow_id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `trip_amount` decimal(10,2) NOT NULL,
  `deposit_amount` decimal(10,2) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('held','released','refunded','partial_released') DEFAULT 'held',
  `held_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `released_at` timestamp NULL DEFAULT NULL,
  `release_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `escrow`
--

INSERT INTO `escrow` (`escrow_id`, `trip_id`, `trip_amount`, `deposit_amount`, `total_amount`, `status`, `held_at`, `released_at`, `release_notes`) VALUES
(1, 1, 410.55, 500.00, 910.55, 'released', '2025-12-24 14:35:45', '2025-12-25 13:26:41', 'Manual admin release'),
(2, 2, 345.00, 300.00, 645.00, 'refunded', '2025-12-24 14:35:45', NULL, NULL),
(3, 4, 81.75, 300.00, 381.75, 'released', '2025-12-25 15:22:05', '2025-12-25 15:23:48', 'Trip completed by renter');

-- --------------------------------------------------------

--
-- Table structure for table `insurance_plans`
--

CREATE TABLE `insurance_plans` (
  `plan_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `plan_description` text DEFAULT NULL,
  `coverage_amount` decimal(12,2) NOT NULL,
  `daily_fee` decimal(10,2) NOT NULL,
  `deductible` decimal(10,2) DEFAULT 0.00,
  `coverage_details` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `insurance_plans`
--

INSERT INTO `insurance_plans` (`plan_id`, `plan_name`, `plan_description`, `coverage_amount`, `daily_fee`, `deductible`, `coverage_details`, `status`, `created_at`) VALUES
(1, 'Basic Protection', 'Basic coverage for your trip', 50000.00, 15.00, 1000.00, 'Covers collision and theft with $1,000 deductible', 'active', '2025-12-24 14:35:21'),
(2, 'Standard Protection', 'Enhanced coverage with lower deductible', 100000.00, 30.00, 500.00, 'Covers collision, theft, and liability with $500 deductible', 'active', '2025-12-24 14:35:21'),
(3, 'Premium Protection', 'Comprehensive coverage with zero deductible', 250000.00, 50.00, 0.00, 'Full coverage including collision, theft, liability, and roadside assistance with no deductible', 'active', '2025-12-24 14:35:21');

-- --------------------------------------------------------

--
-- Table structure for table `owner_balances`
--

CREATE TABLE `owner_balances` (
  `balance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `available_balance` decimal(10,2) DEFAULT 0.00,
  `pending_balance` decimal(10,2) DEFAULT 0.00,
  `total_earned` decimal(10,2) DEFAULT 0.00,
  `total_paid_out` decimal(10,2) DEFAULT 0.00,
  `platform_fees_paid` decimal(10,2) DEFAULT 0.00,
  `insurance_fees_paid` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owner_balances`
--

INSERT INTO `owner_balances` (`balance_id`, `user_id`, `available_balance`, `pending_balance`, `total_earned`, `total_paid_out`, `platform_fees_paid`, `insurance_fees_paid`, `updated_at`) VALUES
(1, 4, 1272.00, -713.75, 2132.00, 710.00, 60.30, 30.00, '2025-12-25 15:23:49'),
(2, 5, 780.00, 0.00, 2500.00, 1720.00, 0.00, 0.00, '2025-12-24 14:35:44'),
(3, 6, 0.00, 0.00, 0.00, 0.00, 0.00, 0.00, '2025-12-24 14:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `owner_verification`
--

CREATE TABLE `owner_verification` (
  `verification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verification_documents` text DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `owner_verification`
--

INSERT INTO `owner_verification` (`verification_id`, `user_id`, `business_name`, `verification_status`, `verification_documents`, `verified_at`, `created_at`) VALUES
(1, 4, NULL, 'verified', NULL, '2025-12-24 14:35:44', '2025-12-24 14:35:44'),
(2, 5, NULL, 'verified', NULL, '2025-12-24 14:35:44', '2025-12-24 14:35:44'),
(3, 6, NULL, 'pending', NULL, NULL, '2025-12-24 14:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `payment_credentials`
--

CREATE TABLE `payment_credentials` (
  `credential_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `credential_type` enum('platform_card','platform_number') NOT NULL,
  `credential_number` varchar(50) NOT NULL,
  `credential_name` varchar(255) DEFAULT NULL,
  `status` enum('active','suspended','expired') DEFAULT 'active',
  `issued_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_credentials`
--

INSERT INTO `payment_credentials` (`credential_id`, `user_id`, `credential_type`, `credential_number`, `credential_name`, `status`, `issued_date`, `expiry_date`, `created_at`) VALUES
(1, 2, 'platform_card', 'PC-A1B2C3D4E5F6', 'John Smith', 'active', '2025-12-24 14:35:44', '2028-12-24', '2025-12-24 14:35:44'),
(2, 3, 'platform_card', 'PC-G7H8I9J0K1L2', 'Sarah Johnson', 'active', '2025-12-24 14:35:44', '2028-12-24', '2025-12-24 14:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `payouts`
--

CREATE TABLE `payouts` (
  `payout_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `payout_method_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payout_type` enum('manual','automatic') DEFAULT 'manual',
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payouts`
--

INSERT INTO `payouts` (`payout_id`, `user_id`, `payout_method_id`, `amount`, `payout_type`, `status`, `requested_at`, `processed_at`, `failure_reason`, `notes`) VALUES
(1, 4, 1, 55.00, 'manual', 'completed', '2025-12-25 13:52:32', '2025-12-25 13:52:55', NULL, '\nAdmin approved: ');

-- --------------------------------------------------------

--
-- Table structure for table `payout_methods`
--

CREATE TABLE `payout_methods` (
  `payout_method_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `method_type` enum('bank_account','crypto_wallet','business_account') NOT NULL,
  `account_details` text NOT NULL,
  `account_holder_name` varchar(255) DEFAULT NULL,
  `routing_number` varchar(50) DEFAULT NULL,
  `account_number_masked` varchar(50) DEFAULT NULL,
  `crypto_address` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payout_methods`
--

INSERT INTO `payout_methods` (`payout_method_id`, `user_id`, `method_type`, `account_details`, `account_holder_name`, `routing_number`, `account_number_masked`, `crypto_address`, `is_default`, `status`, `created_at`, `updated_at`) VALUES
(1, 4, 'bank_account', '{\"bank_name\":\"Chase Bank\",\"account_number\":\"****1234\",\"routing_number\":\"021000021\",\"account_type\":\"checking\"}', 'Mike Wilson', NULL, NULL, NULL, 1, 'active', '2025-12-24 14:35:45', '2025-12-24 14:35:45'),
(2, 5, 'bank_account', '{\"bank_name\":\"Bank of America\",\"account_number\":\"****5678\",\"routing_number\":\"026009593\",\"account_type\":\"savings\"}', 'Lisa Brown', NULL, NULL, NULL, 1, 'active', '2025-12-24 14:35:45', '2025-12-24 14:35:45');

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`setting_id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'platform_fee_percent', '15.00', 'Platform service fee percentage', '2025-12-24 14:35:21'),
(2, 'insurance_fee_percent', '10.00', 'Insurance fee percentage', '2025-12-24 14:35:21'),
(3, 'min_payout_amount', '50.00', 'Minimum payout amount for owners', '2025-12-24 14:35:21'),
(4, 'currency', 'USD', 'Platform currency', '2025-12-24 14:35:21'),
(5, 'platform_name', 'Car Trip Now', 'Platform name', '2025-12-24 14:35:21'),
(6, 'late_return_fee_per_hour', '25.00', 'Late return fee per hour', '2025-12-24 14:35:21'),
(7, 'default_security_deposit', '500.00', 'Default security deposit amount', '2025-12-24 14:35:21'),
(8, 'max_trip_duration_days', '30', 'Maximum trip duration in days', '2025-12-24 14:35:21'),
(9, 'min_driver_age', '25', 'Minimum driver age', '2025-12-24 14:35:21');

-- --------------------------------------------------------

--
-- Table structure for table `renter_balances`
--

CREATE TABLE `renter_balances` (
  `balance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `pending_holds` decimal(10,2) DEFAULT 0.00,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `renter_balances`
--

INSERT INTO `renter_balances` (`balance_id`, `user_id`, `current_balance`, `pending_holds`, `total_spent`, `updated_at`) VALUES
(1, 2, 1518.25, -910.55, 881.75, '2025-12-25 15:23:48'),
(2, 3, 2645.00, 0.00, 0.00, '2025-12-24 16:09:23');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `trip_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewee_id` int(11) NOT NULL,
  `review_type` enum('renter_to_owner','owner_to_renter','guest_to_host') NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `cleanliness_rating` int(11) DEFAULT NULL CHECK (`cleanliness_rating` between 1 and 5),
  `communication_rating` int(11) DEFAULT NULL CHECK (`communication_rating` between 1 and 5),
  `vehicle_condition_rating` int(11) DEFAULT NULL CHECK (`vehicle_condition_rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`review_id`, `trip_id`, `reviewer_id`, `reviewee_id`, `review_type`, `rating`, `cleanliness_rating`, `communication_rating`, `vehicle_condition_rating`, `comment`, `created_at`) VALUES
(1, 3, 2, 5, 'renter_to_owner', 5, 5, 5, 5, 'Excellent vehicle! Super clean and drove perfectly. Lisa was very responsive and helpful. Highly recommend!', '2025-12-24 14:35:45'),
(2, 1, 2, 4, '', 5, NULL, NULL, NULL, 'Happy driving, enjoy journey', '2025-12-25 13:40:09'),
(3, 1, 4, 2, 'owner_to_renter', 3, 4, 4, 4, 'good', '2025-12-25 14:20:06'),
(6, 1, 2, 4, 'renter_to_owner', 3, NULL, NULL, NULL, 'sffdg dfgdfg fgdfg fdgfdg', '2025-12-25 14:58:31'),
(7, 4, 2, 4, 'renter_to_owner', 5, 5, 3, 5, 'Good', '2025-12-25 15:23:49');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_type` enum('deposit','deduction','earning','payout','refund','fee','hold','release','deposit_hold','deposit_release','damage_charge','late_fee') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `balance_before` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `user_id`, `transaction_type`, `amount`, `balance_before`, `balance_after`, `reference_type`, `reference_id`, `description`, `created_at`) VALUES
(1, 2, 'deposit', 1000.00, 0.00, 1000.00, NULL, NULL, 'Welcome bonus - Starting balance', '2025-12-24 14:35:45'),
(2, 2, 'deduction', 910.55, 1500.00, 589.45, 'trip', 1, 'Payment for Tesla Model 3 trip', '2025-12-24 14:35:45'),
(3, 2, 'deduction', 693.25, 1589.45, 896.20, 'trip', 3, 'Payment for Toyota Camry trip', '2025-12-24 14:35:45'),
(4, 2, 'deposit', 1000.00, 896.20, 1896.20, NULL, NULL, 'Balance top-up', '2025-12-24 14:35:45'),
(5, 3, 'deposit', 1000.00, 0.00, 1000.00, NULL, NULL, 'Welcome bonus - Starting balance', '2025-12-24 14:35:45'),
(6, 3, 'deduction', 645.00, 2000.00, 1355.00, 'trip', 2, 'Payment for Honda Civic trip', '2025-12-24 14:35:45'),
(7, 3, 'deposit', 1000.00, 1355.00, 2355.00, NULL, NULL, 'Balance top-up', '2025-12-24 14:35:45'),
(8, 5, 'earning', 140.23, 640.00, 780.23, 'trip', 3, 'Earnings from Toyota Camry trip', '2025-12-24 14:35:45'),
(9, 4, 'payout', 600.00, 1050.00, 450.00, 'payout', NULL, 'Payout to bank account', '2025-12-24 14:35:45'),
(10, 2, 'deposit', 100.00, 1500.00, 1600.00, NULL, NULL, 'Admin Adjustment: Bonus', '2025-12-24 15:30:03'),
(11, 3, 'refund', 645.00, 2000.00, 2645.00, 'trip', 2, 'Refund for trip #2', '2025-12-24 16:09:23'),
(12, 4, 'earning', 857.00, 450.00, 1307.00, 'trip', 1, 'Earning from trip #1 (admin release)', '2025-12-25 13:26:41'),
(13, 4, 'payout', 55.00, 1307.00, 1252.00, 'payout', 1, 'Payout request #1', '2025-12-25 13:52:32'),
(14, 4, 'payout', 55.00, 1252.00, 1197.00, 'payout', 1, 'Payout to bank_account', '2025-12-25 13:52:55'),
(15, 2, 'deduction', 381.75, 1600.00, 1218.25, 'trip', 4, 'Payment for trip #4', '2025-12-25 15:22:05'),
(16, 2, 'deposit_release', 300.00, 1218.25, 1518.25, 'trip', 4, 'Security deposit released for trip #4', '2025-12-25 15:23:48'),
(17, 4, 'earning', 75.00, 1197.00, 1272.00, 'trip', 4, 'Earning from completed trip #4', '2025-12-25 15:23:49');

-- --------------------------------------------------------

--
-- Table structure for table `trips`
--

CREATE TABLE `trips` (
  `trip_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `renter_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `pickup_date` date NOT NULL,
  `return_date` date NOT NULL,
  `pickup_time` time NOT NULL,
  `return_time` time NOT NULL,
  `trip_duration_days` int(11) NOT NULL,
  `daily_rate` decimal(10,2) NOT NULL,
  `total_days_cost` decimal(10,2) NOT NULL,
  `mileage_limit` int(11) NOT NULL,
  `odometer_start` int(11) DEFAULT NULL,
  `odometer_end` int(11) DEFAULT NULL,
  `actual_miles_driven` int(11) DEFAULT NULL,
  `extra_mileage_driven` int(11) DEFAULT 0,
  `extra_mileage_fee` decimal(10,2) DEFAULT 0.00,
  `insurance_plan_id` int(11) DEFAULT NULL,
  `insurance_fee` decimal(10,2) DEFAULT 0.00,
  `platform_fee` decimal(10,2) NOT NULL,
  `service_fee_percent` decimal(5,2) DEFAULT 15.00,
  `security_deposit` decimal(10,2) NOT NULL,
  `deposit_status` enum('held','released','partial_released','forfeited') DEFAULT 'held',
  `deposit_released_amount` decimal(10,2) DEFAULT 0.00,
  `deposit_deducted_amount` decimal(10,2) DEFAULT 0.00,
  `deposit_deduction_reason` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_credential_id` int(11) NOT NULL,
  `trip_status` enum('pending','confirmed','active','completed','cancelled','disputed') DEFAULT 'pending',
  `payment_status` enum('pending','held','completed','refunded','partial_refund') DEFAULT 'pending',
  `vehicle_condition_pickup` text DEFAULT NULL,
  `vehicle_condition_return` text DEFAULT NULL,
  `damage_reported` tinyint(1) DEFAULT 0,
  `damage_description` text DEFAULT NULL,
  `damage_photos` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `late_return_hours` int(11) DEFAULT 0,
  `late_return_fee` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trips`
--

INSERT INTO `trips` (`trip_id`, `vehicle_id`, `renter_id`, `owner_id`, `pickup_date`, `return_date`, `pickup_time`, `return_time`, `trip_duration_days`, `daily_rate`, `total_days_cost`, `mileage_limit`, `odometer_start`, `odometer_end`, `actual_miles_driven`, `extra_mileage_driven`, `extra_mileage_fee`, `insurance_plan_id`, `insurance_fee`, `platform_fee`, `service_fee_percent`, `security_deposit`, `deposit_status`, `deposit_released_amount`, `deposit_deducted_amount`, `deposit_deduction_reason`, `total_amount`, `payment_credential_id`, `trip_status`, `payment_status`, `vehicle_condition_pickup`, `vehicle_condition_return`, `damage_reported`, `damage_description`, `damage_photos`, `cancellation_reason`, `cancelled_by`, `cancelled_at`, `late_return_hours`, `late_return_fee`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 4, '2025-12-29', '2026-01-01', '10:00:00', '10:00:00', 3, 89.00, 267.00, 600, NULL, NULL, NULL, 0, 0.00, 2, 90.00, 53.55, 15.00, 500.00, 'held', 0.00, 0.00, NULL, 910.55, 1, 'completed', 'completed', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0.00, '2025-12-24 14:35:44', '2025-12-25 13:26:41'),
(2, 2, 3, 4, '2026-01-03', '2026-01-08', '09:00:00', '18:00:00', 5, 45.00, 225.00, 750, NULL, NULL, NULL, 0, 0.00, 1, 75.00, 45.00, 15.00, 300.00, 'held', 0.00, 0.00, NULL, 645.00, 2, 'cancelled', 'refunded', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0.00, '2025-12-24 14:35:44', '2025-12-24 16:09:23'),
(3, 4, 2, 5, '2025-12-14', '2025-12-17', '14:00:00', '14:00:00', 3, 55.00, 165.00, 600, NULL, NULL, NULL, 0, 0.00, 2, 90.00, 38.25, 15.00, 400.00, 'held', 0.00, 0.00, NULL, 693.25, 1, 'completed', 'completed', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0.00, '2025-12-24 14:35:44', '2025-12-24 14:35:44'),
(4, 2, 2, 4, '2025-12-24', '2025-12-25', '06:00:00', '07:00:00', 1, 45.00, 45.00, 150, NULL, NULL, NULL, 0, 0.00, 2, 30.00, 6.75, 15.00, 300.00, 'held', 0.00, 0.00, NULL, 381.75, 1, 'completed', 'completed', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 0.00, '2025-12-25 15:22:04', '2025-12-25 15:23:48');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_type` enum('renter','owner','admin') NOT NULL,
  `driver_license_number` varchar(50) DEFAULT NULL,
  `driver_license_verified` tinyint(1) DEFAULT 0,
  `driver_license_expiry` date DEFAULT NULL,
  `status` enum('active','suspended','frozen') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `full_name`, `phone`, `user_type`, `driver_license_number`, `driver_license_verified`, `driver_license_expiry`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin@gmail.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '+1-234-567-8900', 'admin', NULL, 1, NULL, 'active', '2025-12-24 14:35:21', '2025-12-24 14:37:43'),
(2, 'john.renter@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Smith', '+1-555-0101', 'renter', NULL, 1, NULL, 'active', '2025-12-24 14:35:43', '2025-12-24 14:35:43'),
(3, 'sarah.renter@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Johnson', '+1-555-0102', 'renter', NULL, 1, NULL, 'active', '2025-12-24 14:35:43', '2025-12-24 14:35:43'),
(4, 'mike.owner@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Wilson', '+1-555-0201', 'owner', NULL, 0, NULL, 'active', '2025-12-24 14:35:43', '2025-12-24 14:35:43'),
(5, 'lisa.owner@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa Brown', '+1-555-0202', 'owner', NULL, 0, NULL, 'active', '2025-12-24 14:35:43', '2025-12-24 14:35:43'),
(6, 'david.owner@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David Martinez', '+1-555-0203', 'owner', NULL, 0, NULL, 'active', '2025-12-24 14:35:43', '2025-12-24 14:35:43');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `vehicle_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `make` varchar(100) NOT NULL,
  `model` varchar(100) NOT NULL,
  `year` int(11) NOT NULL,
  `vin` varchar(17) DEFAULT NULL,
  `license_plate` varchar(20) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `vehicle_type` enum('sedan','suv','truck','van','sports','luxury','electric','hybrid','other') NOT NULL,
  `transmission` enum('automatic','manual') DEFAULT 'automatic',
  `fuel_type` enum('gasoline','diesel','electric','hybrid') DEFAULT 'gasoline',
  `seats` int(11) DEFAULT 5,
  `doors` int(11) DEFAULT 4,
  `odometer_reading` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `vehicle_features` text DEFAULT NULL,
  `vehicle_rules` text DEFAULT NULL,
  `daily_price` decimal(10,2) NOT NULL,
  `weekly_discount_percent` decimal(5,2) DEFAULT 0.00,
  `monthly_discount_percent` decimal(5,2) DEFAULT 0.00,
  `mileage_limit_per_day` int(11) DEFAULT 200,
  `extra_mileage_fee` decimal(10,2) DEFAULT 0.50,
  `pickup_location_address` text NOT NULL,
  `pickup_city` varchar(100) DEFAULT NULL,
  `pickup_state` varchar(100) DEFAULT NULL,
  `pickup_country` varchar(100) DEFAULT 'USA',
  `pickup_zipcode` varchar(20) DEFAULT NULL,
  `pickup_latitude` decimal(10,8) DEFAULT NULL,
  `pickup_longitude` decimal(11,8) DEFAULT NULL,
  `security_deposit` decimal(10,2) DEFAULT 500.00,
  `insurance_required` tinyint(1) DEFAULT 1,
  `min_driver_age` int(11) DEFAULT 25,
  `status` enum('active','inactive','maintenance','suspended') DEFAULT 'active',
  `instant_book` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`vehicle_id`, `owner_id`, `make`, `model`, `year`, `vin`, `license_plate`, `color`, `vehicle_type`, `transmission`, `fuel_type`, `seats`, `doors`, `odometer_reading`, `description`, `vehicle_features`, `vehicle_rules`, `daily_price`, `weekly_discount_percent`, `monthly_discount_percent`, `mileage_limit_per_day`, `extra_mileage_fee`, `pickup_location_address`, `pickup_city`, `pickup_state`, `pickup_country`, `pickup_zipcode`, `pickup_latitude`, `pickup_longitude`, `security_deposit`, `insurance_required`, `min_driver_age`, `status`, `instant_book`, `created_at`, `updated_at`) VALUES
(1, 4, 'Tesla', 'Model 3', 2023, '5YJ3E1EA1KF123456', 'CAL-2023', 'Pearl White', 'electric', 'automatic', 'electric', 5, 4, 8500, 'Brand new Tesla Model 3 with Autopilot. Perfect for city trips and long drives. Super clean and well-maintained.', 'Autopilot, Premium Sound System, Glass Roof, Heated Seats, Bluetooth, Backup Camera, Navigation', 'No smoking, No pets, Return with same charge level, Keep it clean', 89.00, 0.00, 0.00, 200, 0.50, '123 Tech Drive, Palo Alto', 'Palo Alto', 'California', 'USA', '94301', NULL, NULL, 500.00, 1, 25, 'active', 1, '2025-12-24 14:35:44', '2025-12-25 13:27:25'),
(2, 4, 'Honda', 'Civic', 2022, '19XFC2F59ME123456', 'CAL-2022', 'Silver', 'sedan', 'automatic', 'gasoline', 5, 4, 15200, 'Reliable Honda Civic, great gas mileage. Perfect for everyday use and road trips.', 'Backup Camera, Bluetooth, Apple CarPlay, Android Auto, Lane Keeping Assist', 'No smoking, Keep it clean, Fill tank before return', 45.00, 0.00, 0.00, 150, 0.40, '456 Main Street, San Jose', 'San Jose', 'California', 'USA', '95110', NULL, NULL, 300.00, 1, 25, 'active', 1, '2025-12-24 14:35:44', '2025-12-24 14:35:44'),
(3, 5, 'Ford', 'F-150', 2021, '1FTFW1E84MFA12345', 'TEX-2021', 'Blue', 'truck', 'automatic', 'gasoline', 6, 4, 28000, 'Powerful Ford F-150 pickup truck. Great for hauling and outdoor adventures. 4WD capability.', '4-Wheel Drive, Towing Package, Backup Camera, Bluetooth, Bed Liner, Running Boards', 'No smoking, Clean the bed after use, Report any damage immediately', 75.00, 0.00, 0.00, 180, 0.45, '789 Ranch Road, Austin', 'Austin', 'Texas', 'USA', '78701', NULL, NULL, 600.00, 1, 25, 'active', 0, '2025-12-24 14:35:44', '2025-12-24 14:35:44'),
(4, 5, 'Toyota', 'Camry', 2023, '4T1C11AK8PU123456', 'TEX-2023', 'Black', 'sedan', 'automatic', 'hybrid', 5, 4, 5000, 'Brand new Toyota Camry Hybrid. Excellent fuel economy and comfortable ride.', 'Hybrid Engine, Lane Departure Warning, Adaptive Cruise Control, Heated Seats, Premium Sound', 'No smoking, No pets, Keep it clean', 55.00, 0.00, 0.00, 200, 0.35, '321 Downtown Blvd, Austin', 'Austin', 'Texas', 'USA', '78702', NULL, NULL, 400.00, 1, 25, 'active', 1, '2025-12-24 14:35:44', '2025-12-24 14:35:44'),
(5, 6, 'Jeep', 'Wrangler', 2020, '1C4HJXDG0LW123456', 'COL-2020', 'Green', 'suv', 'manual', 'gasoline', 5, 2, 45000, 'Adventure-ready Jeep Wrangler. Perfect for off-road trips and mountain adventures.', 'Removable Top, 4-Wheel Drive, Off-Road Tires, Bluetooth, Heavy Duty Suspension', 'Off-road use allowed, Clean interior after muddy trips, No smoking', 85.00, 0.00, 0.00, 150, 0.55, '555 Mountain View, Denver', 'Denver', 'Colorado', 'USA', '80202', NULL, NULL, 700.00, 1, 25, 'active', 0, '2025-12-24 14:35:44', '2025-12-24 14:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_availability`
--

CREATE TABLE `vehicle_availability` (
  `availability_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('available','booked','blocked','maintenance') DEFAULT 'available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_availability`
--

INSERT INTO `vehicle_availability` (`availability_id`, `vehicle_id`, `date`, `status`) VALUES
(1, 1, '2025-12-29', 'booked'),
(2, 1, '2025-12-30', 'booked'),
(3, 1, '2025-12-31', 'booked'),
(4, 1, '2026-01-01', 'booked'),
(8, 2, '2026-01-03', 'booked'),
(9, 2, '2026-01-04', 'booked'),
(10, 2, '2026-01-05', 'booked'),
(11, 2, '2026-01-06', 'booked'),
(12, 2, '2026-01-07', 'booked'),
(13, 2, '2026-01-08', 'booked');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_photos`
--

CREATE TABLE `vehicle_photos` (
  `photo_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `photo_url` varchar(500) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_photos`
--

INSERT INTO `vehicle_photos` (`photo_id`, `vehicle_id`, `photo_url`, `is_primary`, `display_order`, `uploaded_at`) VALUES
(3, 2, 'uploads/vehicles/listing_2_1766675722_0.jpg', 1, 1, '2025-12-25 15:15:22');

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `wishlist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`wishlist_id`, `user_id`, `vehicle_id`, `added_at`, `notes`) VALUES
(2, 3, 1, '2025-12-24 14:35:45', 'Always wanted to try electric!'),
(3, 3, 3, '2025-12-24 14:35:45', 'Need for moving furniture'),
(5, 2, 5, '2025-12-25 15:58:55', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_target` (`target_type`,`target_id`);

--
-- Indexes for table `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`dispute_id`),
  ADD KEY `raised_by` (`raised_by`),
  ADD KEY `idx_trip_id` (`trip_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `escrow`
--
ALTER TABLE `escrow`
  ADD PRIMARY KEY (`escrow_id`),
  ADD UNIQUE KEY `trip_id` (`trip_id`),
  ADD KEY `idx_trip_id` (`trip_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `insurance_plans`
--
ALTER TABLE `insurance_plans`
  ADD PRIMARY KEY (`plan_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `owner_balances`
--
ALTER TABLE `owner_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `owner_verification`
--
ALTER TABLE `owner_verification`
  ADD PRIMARY KEY (`verification_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `payment_credentials`
--
ALTER TABLE `payment_credentials`
  ADD PRIMARY KEY (`credential_id`),
  ADD UNIQUE KEY `credential_number` (`credential_number`),
  ADD KEY `idx_credential_number` (`credential_number`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `payouts`
--
ALTER TABLE `payouts`
  ADD PRIMARY KEY (`payout_id`),
  ADD KEY `payout_method_id` (`payout_method_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_requested_at` (`requested_at`);

--
-- Indexes for table `payout_methods`
--
ALTER TABLE `payout_methods`
  ADD PRIMARY KEY (`payout_method_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `renter_balances`
--
ALTER TABLE `renter_balances`
  ADD PRIMARY KEY (`balance_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `idx_trip_id` (`trip_id`),
  ADD KEY `idx_reviewee_id` (`reviewee_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`);

--
-- Indexes for table `trips`
--
ALTER TABLE `trips`
  ADD PRIMARY KEY (`trip_id`),
  ADD KEY `insurance_plan_id` (`insurance_plan_id`),
  ADD KEY `payment_credential_id` (`payment_credential_id`),
  ADD KEY `cancelled_by` (`cancelled_by`),
  ADD KEY `idx_renter_id` (`renter_id`),
  ADD KEY `idx_owner_id` (`owner_id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`),
  ADD KEY `idx_trip_status` (`trip_status`),
  ADD KEY `idx_dates` (`pickup_date`,`return_date`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`vehicle_id`),
  ADD UNIQUE KEY `vin` (`vin`),
  ADD KEY `idx_owner_id` (`owner_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_location` (`pickup_city`,`pickup_state`),
  ADD KEY `idx_vehicle_type` (`vehicle_type`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_make_model` (`make`,`model`);

--
-- Indexes for table `vehicle_availability`
--
ALTER TABLE `vehicle_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD UNIQUE KEY `unique_vehicle_date` (`vehicle_id`,`date`),
  ADD KEY `idx_vehicle_date` (`vehicle_id`,`date`);

--
-- Indexes for table `vehicle_photos`
--
ALTER TABLE `vehicle_photos`
  ADD PRIMARY KEY (`photo_id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`wishlist_id`),
  ADD UNIQUE KEY `unique_user_vehicle` (`user_id`,`vehicle_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_actions`
--
ALTER TABLE `admin_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disputes`
--
ALTER TABLE `disputes`
  MODIFY `dispute_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escrow`
--
ALTER TABLE `escrow`
  MODIFY `escrow_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `insurance_plans`
--
ALTER TABLE `insurance_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `owner_balances`
--
ALTER TABLE `owner_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `owner_verification`
--
ALTER TABLE `owner_verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment_credentials`
--
ALTER TABLE `payment_credentials`
  MODIFY `credential_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payouts`
--
ALTER TABLE `payouts`
  MODIFY `payout_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payout_methods`
--
ALTER TABLE `payout_methods`
  MODIFY `payout_method_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `platform_settings`
--
ALTER TABLE `platform_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `renter_balances`
--
ALTER TABLE `renter_balances`
  MODIFY `balance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `trips`
--
ALTER TABLE `trips`
  MODIFY `trip_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `vehicle_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vehicle_availability`
--
ALTER TABLE `vehicle_availability`
  MODIFY `availability_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `vehicle_photos`
--
ALTER TABLE `vehicle_photos`
  MODIFY `photo_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `wishlist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD CONSTRAINT `admin_actions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `disputes_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`trip_id`),
  ADD CONSTRAINT `disputes_ibfk_2` FOREIGN KEY (`raised_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `escrow`
--
ALTER TABLE `escrow`
  ADD CONSTRAINT `escrow_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`trip_id`) ON DELETE CASCADE;

--
-- Constraints for table `owner_balances`
--
ALTER TABLE `owner_balances`
  ADD CONSTRAINT `owner_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `owner_verification`
--
ALTER TABLE `owner_verification`
  ADD CONSTRAINT `owner_verification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_credentials`
--
ALTER TABLE `payment_credentials`
  ADD CONSTRAINT `payment_credentials_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payouts`
--
ALTER TABLE `payouts`
  ADD CONSTRAINT `payouts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `payouts_ibfk_2` FOREIGN KEY (`payout_method_id`) REFERENCES `payout_methods` (`payout_method_id`);

--
-- Constraints for table `payout_methods`
--
ALTER TABLE `payout_methods`
  ADD CONSTRAINT `payout_methods_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `renter_balances`
--
ALTER TABLE `renter_balances`
  ADD CONSTRAINT `renter_balances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`trip_id`) REFERENCES `trips` (`trip_id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`reviewee_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `trips`
--
ALTER TABLE `trips`
  ADD CONSTRAINT `trips_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`),
  ADD CONSTRAINT `trips_ibfk_2` FOREIGN KEY (`renter_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `trips_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `trips_ibfk_4` FOREIGN KEY (`insurance_plan_id`) REFERENCES `insurance_plans` (`plan_id`),
  ADD CONSTRAINT `trips_ibfk_5` FOREIGN KEY (`payment_credential_id`) REFERENCES `payment_credentials` (`credential_id`),
  ADD CONSTRAINT `trips_ibfk_6` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD CONSTRAINT `vehicles_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_availability`
--
ALTER TABLE `vehicle_availability`
  ADD CONSTRAINT `vehicle_availability_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE;

--
-- Constraints for table `vehicle_photos`
--
ALTER TABLE `vehicle_photos`
  ADD CONSTRAINT `vehicle_photos_ibfk_1` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE;

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlists_ibfk_2` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`vehicle_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
