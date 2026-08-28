-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 17, 2026 at 09:02 AM
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
-- Database: `meditrack_compact`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `fullname` varchar(120) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','manager','nurse','pharmacist','viewer') NOT NULL DEFAULT 'superadmin',
  `theme` varchar(20) NOT NULL DEFAULT 'blue',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `fullname`, `username`, `password`, `role`, `theme`, `created_at`) VALUES
(1, 'Project Admin', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uSa0RtFWi', 'superadmin', 'blue', '2026-05-15 05:56:24');

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `alert_type` varchar(40) NOT NULL,
  `message` varchar(255) NOT NULL,
  `related_url` varchar(255) DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_by` varchar(120) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `alerts`
--

INSERT INTO `alerts` (`id`, `severity`, `alert_type`, `message`, `related_url`, `is_resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES
(1, 'warning', 'unknowntag', 'Unknown RFID tag scanned: DBC51D1A', 'index.php?page=alerts', 1, 'system', '2026-05-15 11:44:56', '2026-05-15 05:57:51'),
(2, 'warning', 'unknowntag', 'Unknown RFID tag scanned: TEST123', 'index.php?page=alerts', 0, NULL, NULL, '2026-05-15 06:21:17'),
(3, 'warning', 'unknowntag', 'Unknown RFID tag scanned: F3E79413', 'index.php?page=alerts', 0, NULL, NULL, '2026-05-15 06:24:02'),
(4, 'warning', 'unknowntag', 'Unknown RFID tag scanned: 04064B0A9F6F80', 'index.php?page=alerts', 0, NULL, NULL, '2026-05-15 06:27:03'),
(5, 'warning', 'unknowntag', 'Unknown RFID tag scanned: DBC51D1A', 'index.php?page=alerts', 0, NULL, NULL, '2026-05-15 06:27:38'),
(6, 'warning', 'unknowntag', 'Unknown RFID tag scanned: 618CD33E', 'index.php?page=alerts', 0, NULL, NULL, '2026-05-15 06:28:23'),
(7, 'warning', 'unknowntag', 'Unknown RFID tag scanned: 643CA6CE', 'index.php?page=alerts', 0, NULL, NULL, '2026-05-15 06:29:20'),
(8, 'warning', 'unknowntag', 'Unknown RFID tag scanned: 1466D4A9', 'index.php?page=alerts', 0, NULL, NULL, '2026-05-15 06:30:03');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL DEFAULT 0,
  `action` varchar(80) NOT NULL,
  `target_table` varchar(60) NOT NULL DEFAULT '',
  `target_id` int(11) NOT NULL DEFAULT 0,
  `detail` varchar(500) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `admin_id`, `action`, `target_table`, `target_id`, `detail`, `ip`, `created_at`) VALUES
(1, 1, 'update_med_rfid', 'items', 2, '640180A9', '127.0.0.1', '2026-05-15 06:12:31'),
(2, 1, 'add_staff', 'staffmembers', 3, 'Pandiamma', '127.0.0.1', '2026-05-15 06:12:57'),
(3, 1, 'add_staff', 'staffmembers', 4, 'Doctor Singh', '127.0.0.1', '2026-05-15 06:26:39'),
(4, 1, 'add_patient', 'patients', 3, 'Nirav Lodi', '127.0.0.1', '2026-05-15 06:29:11'),
(5, 1, 'update_med_rfid', 'items', 1, '1466D4A9', '127.0.0.1', '2026-05-15 06:30:14'),
(6, 1, 'add_med', 'medicationschedule', 3, 'Patient 2', '127.0.0.1', '2026-05-15 06:32:36'),
(7, 1, 'mark_med', 'medicationschedule', 2, 'Manual mark done', '127.0.0.1', '2026-05-15 06:32:46'),
(8, 1, 'mark_med', 'medicationschedule', 3, 'Manual mark done', '127.0.0.1', '2026-05-15 07:12:05');

-- --------------------------------------------------------

--
-- Table structure for table `batch_traces`
--

CREATE TABLE `batch_traces` (
  `id` int(11) NOT NULL,
  `batchno` varchar(60) NOT NULL,
  `item_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL DEFAULT 0,
  `location_id` int(11) NOT NULL DEFAULT 0,
  `scan_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `caretakers`
--

CREATE TABLE `caretakers` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `fullname` varchar(120) NOT NULL,
  `relation_name` varchar(60) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `caretakers`
--

INSERT INTO `caretakers` (`id`, `patient_id`, `fullname`, `relation_name`, `phone`, `email`, `address`, `emergency_contact`, `notes`, `created_at`) VALUES
(1, 1, 'Suman Sharma', 'Wife', '9000000001', 'suman@example.com', NULL, '9000000001', NULL, '2026-05-15 05:56:24'),
(2, 2, 'Rakesh Verma', 'Brother', '9000000002', 'rakesh@example.com', NULL, '9000000002', NULL, '2026-05-15 05:56:24');

-- --------------------------------------------------------

--
-- Table structure for table `caretaker_accounts`
--

CREATE TABLE `caretaker_accounts` (
  `id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `username` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `caretaker_accounts`
--

INSERT INTO `caretaker_accounts` (`id`, `caretaker_id`, `patient_id`, `username`, `password`, `is_active`, `last_login`, `created_at`) VALUES
(1, 2, 2, 'anita_care', '$2y$10$VrwKLhhVzeVBnU66p90Ruuftk6lAqjau0kMHHQ/UmWXEqYI/2xOyK', 1, NULL, '2026-05-15 06:31:37');

-- --------------------------------------------------------

--
-- Table structure for table `caretaker_tokens`
--

CREATE TABLE `caretaker_tokens` (
  `id` int(11) NOT NULL,
  `caretaker_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `token` varchar(80) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discharge_checklist`
--

CREATE TABLE `discharge_checklist` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL DEFAULT 0,
  `meds_cleared` tinyint(1) NOT NULL DEFAULT 0,
  `iv_completed` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `uid` varchar(60) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `item_type` enum('medicine','equipment','asset') NOT NULL DEFAULT 'asset',
  `brand` varchar(100) DEFAULT NULL,
  `batch_no` varchar(60) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `expiry_date` date DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` enum('instock','inuse','missing','expired') NOT NULL DEFAULT 'instock',
  `recall_flag` tinyint(1) NOT NULL DEFAULT 0,
  `cold_chain_required` tinyint(1) NOT NULL DEFAULT 0,
  `reorder_threshold` int(11) NOT NULL DEFAULT 10,
  `reorder_qty` int(11) NOT NULL DEFAULT 50,
  `supplier_name` varchar(120) DEFAULT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `uid`, `item_name`, `item_type`, `brand`, `batch_no`, `quantity`, `unit_cost`, `expiry_date`, `location_id`, `status`, `recall_flag`, `cold_chain_required`, `reorder_threshold`, `reorder_qty`, `supplier_name`, `last_seen_at`, `created_at`) VALUES
(1, '1466D4A9', 'Paracetamol 650mg', 'medicine', 'Cipla', NULL, 100, 2.50, '2027-12-31', 3, 'instock', 0, 0, 10, 50, NULL, '2026-05-15 12:32:31', '2026-05-15 05:56:24'),
(2, '640180A9', 'Ceftriaxone 1g', 'medicine', 'Abbott', NULL, 50, 35.00, '2027-10-31', 3, 'instock', 0, 0, 10, 50, NULL, '2026-05-15 12:02:13', '2026-05-15 05:56:24'),
(3, 'EQ001', 'Infusion Pump', 'equipment', 'BPL', NULL, 5, 25000.00, NULL, 3, 'instock', 0, 0, 10, 50, NULL, NULL, '2026-05-15 05:56:24'),
(4, 'AS001', 'Wheelchair', 'asset', 'Karma', NULL, 8, 6000.00, NULL, 1, 'instock', 0, 0, 10, 50, NULL, NULL, '2026-05-15 05:56:24');

-- --------------------------------------------------------

--
-- Table structure for table `iv_drips`
--

CREATE TABLE `iv_drips` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `fluid_name` varchar(120) NOT NULL,
  `total_ml` int(11) NOT NULL,
  `remaining_ml` int(11) NOT NULL,
  `flow_rate_ml_hr` decimal(10,2) NOT NULL,
  `started_at` datetime NOT NULL,
  `eta_end` datetime DEFAULT NULL,
  `status` enum('running','paused','completed') NOT NULL DEFAULT 'running',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `location_name` varchar(120) NOT NULL,
  `location_type` varchar(40) NOT NULL DEFAULT 'ward',
  `readerid` varchar(60) DEFAULT NULL,
  `apikey` varchar(100) DEFAULT NULL,
  `lastheartbeat` datetime DEFAULT NULL,
  `isactive` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `location_name`, `location_type`, `readerid`, `apikey`, `lastheartbeat`, `isactive`, `created_at`) VALUES
(1, 'General Ward 1', 'ward', 'ESP32GW1', 'KEYGW1', NULL, 1, '2026-05-15 05:56:24'),
(2, 'Pharmacy', 'pharmacy', 'ESP32PH1', 'KEYPH1', NULL, 1, '2026-05-15 05:56:24'),
(3, 'ICU', 'icu', 'ESP32ICU1', 'KEYICU1', '2026-05-15 12:44:48', 1, '2026-05-15 05:56:24'),
(4, 'ICU Bed 02 - Anita Verma', 'ward', 'ESP32ANITA', 'KEYANITA', '2026-05-15 12:44:48', 1, '2026-05-15 06:14:56');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_schedule`
--

CREATE TABLE `maintenance_schedule` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `last_service_date` date DEFAULT NULL,
  `next_service_date` date NOT NULL,
  `service_notes` varchar(255) DEFAULT NULL,
  `status` enum('pending','done','overdue') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medicationadministrations`
--

CREATE TABLE `medicationadministrations` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `note_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medicationschedule`
--

CREATE TABLE `medicationschedule` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `dose` varchar(50) NOT NULL,
  `route_name` varchar(40) NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `status` enum('pending','administered','missed','refused') NOT NULL DEFAULT 'pending',
  `compliance_status` enum('pending','on_time','late','missed','refused') NOT NULL DEFAULT 'pending',
  `verified_staff_uid` varchar(60) DEFAULT NULL,
  `verified_patient_uid` varchar(60) DEFAULT NULL,
  `verified_medicine_uid` varchar(60) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `medicationschedule`
--

INSERT INTO `medicationschedule` (`id`, `patient_id`, `item_id`, `dose`, `route_name`, `scheduled_time`, `status`, `compliance_status`, `verified_staff_uid`, `verified_patient_uid`, `verified_medicine_uid`, `verified_at`, `created_at`) VALUES
(1, 1, 1, '1 Tablet', 'oral', '2026-05-15 11:46:24', 'pending', 'pending', NULL, NULL, NULL, NULL, '2026-05-15 05:56:24'),
(2, 2, 2, '1 Vial', 'iv', '2026-05-15 12:06:24', 'administered', 'on_time', NULL, NULL, NULL, '2026-05-15 12:02:46', '2026-05-15 05:56:24'),
(3, 2, 2, '1', 'oral', '2026-05-15 02:45:00', 'administered', 'on_time', NULL, NULL, NULL, '2026-05-15 12:42:05', '2026-05-15 06:32:36');

-- --------------------------------------------------------

--
-- Table structure for table `medication_verifications`
--

CREATE TABLE `medication_verifications` (
  `id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `result_text` enum('pass','fail') NOT NULL DEFAULT 'pass',
  `message_text` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL,
  `alert_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `read_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_reads`
--

INSERT INTO `notification_reads` (`id`, `alert_id`, `admin_id`, `read_at`) VALUES
(1, 1, 1, '2026-05-15 11:28:59'),
(22, 2, 1, '2026-05-15 11:52:15'),
(25, 3, 1, '2026-05-15 11:54:10'),
(33, 4, 1, '2026-05-15 11:57:11'),
(36, 5, 1, '2026-05-15 11:57:59'),
(40, 6, 1, '2026-05-15 11:58:29'),
(45, 7, 1, '2026-05-15 11:59:58'),
(51, 8, 1, '2026-05-15 12:01:24');

-- --------------------------------------------------------

--
-- Table structure for table `offline_scan_queue`
--

CREATE TABLE `offline_scan_queue` (
  `id` int(11) NOT NULL,
  `uid` varchar(60) NOT NULL,
  `readerid` varchar(60) NOT NULL,
  `queued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `processed_at` datetime DEFAULT NULL,
  `result` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `patient_code` varchar(30) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `rfiduid` varchar(60) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `blood_group` varchar(10) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `diagnosis` varchar(255) DEFAULT NULL,
  `ward_id` int(11) DEFAULT NULL,
  `bed_no` varchar(20) DEFAULT NULL,
  `status` enum('admitted','icu','critical','discharged') NOT NULL DEFAULT 'admitted',
  `fall_risk` tinyint(1) NOT NULL DEFAULT 0,
  `elopement_risk` tinyint(1) NOT NULL DEFAULT 0,
  `watch_level` varchar(20) NOT NULL DEFAULT 'normal',
  `last_seen_at` datetime DEFAULT NULL,
  `last_seen_location_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `patient_code`, `fullname`, `rfiduid`, `gender`, `age`, `blood_group`, `phone`, `diagnosis`, `ward_id`, `bed_no`, `status`, `fall_risk`, `elopement_risk`, `watch_level`, `last_seen_at`, `last_seen_location_id`, `created_at`) VALUES
(1, 'PAT001', 'Rahul Sharma', 'DBC51D1A', 'Male', 45, 'B+', '9876543210', 'Post-op care', 1, 'GW1-01', 'admitted', 0, 0, 'normal', '2026-05-15 11:57:58', 3, '2026-05-15 05:56:24'),
(2, 'PAT002', 'Anita Verma', '04064B0A9F6F80', 'Female', 31, 'O+', '9876543211', 'Pneumonia', 3, 'ICU-02', 'icu', 0, 0, 'normal', '2026-05-15 11:57:30', 3, '2026-05-15 05:56:24'),
(3, 'PAT003', 'Nirav Lodi', '618CD33E', 'Other', 78, 'B+ve', '1234567890', 'Hanta Virus', 1, '3', 'admitted', 0, 0, 'normal', '2026-05-15 11:59:13', 3, '2026-05-15 06:29:11');

-- --------------------------------------------------------

--
-- Table structure for table `patientsafetyprofiles`
--

CREATE TABLE `patientsafetyprofiles` (
  `id` int(11) NOT NULL,
  `patientid` int(11) NOT NULL,
  `allowedlocations` varchar(255) DEFAULT NULL,
  `restrictedlocations` varchar(255) DEFAULT NULL,
  `maxunseenminutes` int(11) NOT NULL DEFAULT 120,
  `washroomlimitminutes` int(11) NOT NULL DEFAULT 15,
  `bedexitenabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patientvitals`
--

CREATE TABLE `patientvitals` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `systolic_bp` int(11) DEFAULT NULL,
  `diastolic_bp` int(11) DEFAULT NULL,
  `pulse_rate` int(11) DEFAULT NULL,
  `spo2` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `alert_summary` varchar(255) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scanlogs`
--

CREATE TABLE `scanlogs` (
  `id` int(11) NOT NULL,
  `uid` varchar(60) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `from_location_id` int(11) DEFAULT NULL,
  `to_location_id` int(11) DEFAULT NULL,
  `readerid` varchar(60) NOT NULL,
  `action_type` varchar(40) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `scanlogs`
--

INSERT INTO `scanlogs` (`id`, `uid`, `item_id`, `patient_id`, `staff_id`, `from_location_id`, `to_location_id`, `readerid`, `action_type`, `notes`, `scan_time`) VALUES
(1, 'DBC51D1A', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 05:57:51'),
(2, 'DBC51D1A', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 05:58:16'),
(3, 'DBC51D1A', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 05:59:13'),
(4, 'DBC51D1A', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 05:59:16'),
(5, 'DBC51D1A', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 06:01:05'),
(6, 'TEST123', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 06:21:17'),
(7, 'F3E79413', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 06:24:02'),
(8, 'F3E79413', NULL, NULL, NULL, NULL, 4, 'ESP32ANITA', 'unknown', 'Unknown tag', '2026-05-15 06:24:07'),
(9, 'F3E79413', NULL, NULL, NULL, NULL, 4, 'ESP32ANITA', 'unknown', 'Unknown tag', '2026-05-15 06:26:25'),
(10, 'F3E79413', NULL, NULL, 4, NULL, 4, 'ESP32ANITA', 'staffscan', 'Staff seen at reader', '2026-05-15 06:26:43'),
(11, 'F3E79413', NULL, NULL, 4, NULL, 4, 'ESP32ANITA', 'staffscan', 'Staff seen at reader', '2026-05-15 06:26:46'),
(12, 'F3E79413', NULL, NULL, 4, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 06:26:49'),
(13, 'F3E79413', NULL, NULL, 4, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 06:26:52'),
(14, '04064B0A9F6F80', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 06:27:03'),
(15, '04064B0A9F6F80', NULL, 2, NULL, NULL, 3, 'ESP32ICU1', 'patientscan', 'Patient seen at reader', '2026-05-15 06:27:30'),
(16, 'DBC51D1A', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 06:27:38'),
(17, 'DBC51D1A', NULL, 1, NULL, NULL, 3, 'ESP32ICU1', 'patientscan', 'Patient seen at reader', '2026-05-15 06:27:58'),
(18, 'D6BDD13E', NULL, NULL, 3, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 06:28:03'),
(19, 'F3E79413', NULL, NULL, 4, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 06:28:15'),
(20, 'F3E79413', NULL, NULL, 4, NULL, 4, 'ESP32ANITA', 'staffscan', 'Staff seen at reader', '2026-05-15 06:28:20'),
(21, '618CD33E', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 06:28:23'),
(22, '618CD33E', NULL, 3, NULL, NULL, 3, 'ESP32ICU1', 'patientscan', 'Patient seen at reader', '2026-05-15 06:29:13'),
(23, '643CA6CE', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 06:29:20'),
(24, '643CA6CE', NULL, NULL, 1, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 06:29:56'),
(25, '1466D4A9', NULL, NULL, NULL, NULL, 3, 'ESP32ICU1', 'unknown', 'Unknown tag', '2026-05-15 06:30:03'),
(26, '1466D4A9', 1, NULL, NULL, 2, 3, 'ESP32ICU1', 'transfer', 'Item scan', '2026-05-15 06:30:18'),
(27, '640180A9', 2, NULL, NULL, 2, 3, 'ESP32ICU1', 'transfer', 'Item scan', '2026-05-15 06:30:25'),
(28, '640180A9', 2, NULL, NULL, 3, 4, 'ESP32ANITA', 'transfer', 'Item scan', '2026-05-15 06:31:54'),
(29, '643CA6CE', NULL, NULL, 1, NULL, 4, 'ESP32ANITA', 'staffscan', 'Staff seen at reader', '2026-05-15 06:32:02'),
(30, '643CA6CE', NULL, NULL, 1, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 06:32:07'),
(31, '640180A9', 2, NULL, NULL, 4, 3, 'ESP32ICU1', 'transfer', 'Item scan', '2026-05-15 06:32:13'),
(32, '643CA6CE', NULL, NULL, 1, NULL, 4, 'ESP32ANITA', 'staffscan', 'Staff seen at reader', '2026-05-15 06:45:27'),
(33, '643CA6CE', NULL, NULL, 1, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 06:45:31'),
(34, '1466D4A9', 1, NULL, NULL, 3, 3, 'ESP32ICU1', 'scanned', 'Item scan', '2026-05-15 07:02:31'),
(35, '643CA6CE', NULL, NULL, 1, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 07:02:39'),
(36, '643CA6CE', NULL, NULL, 1, NULL, 4, 'ESP32ANITA', 'staffscan', 'Staff seen at reader', '2026-05-15 07:02:43'),
(37, 'F3E79413', NULL, NULL, 4, NULL, 4, 'ESP32ANITA', 'staffscan', 'Staff seen at reader', '2026-05-15 07:08:50'),
(38, 'F3E79413', NULL, NULL, 4, NULL, 3, 'ESP32ICU1', 'staffscan', 'Staff seen at reader', '2026-05-15 07:08:58');

-- --------------------------------------------------------

--
-- Table structure for table `staffmembers`
--

CREATE TABLE `staffmembers` (
  `id` int(11) NOT NULL,
  `employee_id` varchar(30) NOT NULL,
  `fullname` varchar(120) NOT NULL,
  `role` varchar(80) NOT NULL,
  `rfiduid` varchar(60) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staffmembers`
--

INSERT INTO `staffmembers` (`id`, `employee_id`, `fullname`, `role`, `rfiduid`, `is_active`, `created_at`) VALUES
(1, 'EMP001', 'Nurse Priya', 'Nurse', '643CA6CE', 1, '2026-05-15 05:56:24'),
(2, 'EMP002', 'Pharmacist Arun', 'Pharmacist', 'STAFF002', 1, '2026-05-15 05:56:24'),
(3, 'EMP003', 'Pandiamma', 'Nurse', 'D6BDD13E', 1, '2026-05-15 06:12:57'),
(4, 'EMP004', 'Doctor Singh', 'Doctor', 'F3E79413', 1, '2026-05-15 06:26:39');

-- --------------------------------------------------------

--
-- Table structure for table `systemlogs`
--

CREATE TABLE `systemlogs` (
  `id` int(11) NOT NULL,
  `log_level` varchar(20) NOT NULL,
  `source_name` varchar(60) NOT NULL,
  `message_text` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `systemlogs`
--

INSERT INTO `systemlogs` (`id`, `log_level`, `source_name`, `message_text`, `created_at`) VALUES
(1, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 05:58:59'),
(2, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 05:59:29'),
(3, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:00:51'),
(4, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:01:00'),
(5, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:01:19'),
(6, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:01:49'),
(7, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:02:31'),
(8, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:05:55'),
(9, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:06:25'),
(10, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:06:55'),
(11, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:07:25'),
(12, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:07:55'),
(13, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:08:57'),
(14, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:09:49'),
(15, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:10:22'),
(16, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:10:53'),
(17, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:11:23'),
(18, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:11:53'),
(19, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:12:22'),
(20, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:13:39'),
(21, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:14:10'),
(22, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:15:14'),
(23, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:15:44'),
(24, 'error', 'core', '[one] Table \'meditrack_compact.notificationreads\' doesn\'t exist | SELECT COUNT(*) AS c\r\n         FROM alerts a\r\n         LEFT JOIN notificationreads n\r\n           ON n.`alert_id`=a.id\r\n ', '2026-05-15 06:16:14');

-- --------------------------------------------------------

--
-- Table structure for table `workflowtasks`
--

CREATE TABLE `workflowtasks` (
  `id` int(11) NOT NULL,
  `task_key` varchar(120) NOT NULL,
  `task_type` varchar(40) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `patient_id` int(11) NOT NULL DEFAULT 0,
  `item_id` int(11) NOT NULL DEFAULT 0,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `status` enum('open','inprogress','done') NOT NULL DEFAULT 'open',
  `due_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `workflowtasks`
--

INSERT INTO `workflowtasks` (`id`, `task_key`, `task_type`, `title`, `description`, `patient_id`, `item_id`, `priority`, `status`, `due_at`, `completed_at`, `created_at`) VALUES
(1, 'reader-1', 'ops', 'Reader offline', 'General Ward 1 reader heartbeat missing', 0, 0, 'medium', 'open', '2026-05-15 13:15:01', NULL, '2026-05-15 05:56:29'),
(2, 'reader-2', 'ops', 'Reader offline', 'Pharmacy reader heartbeat missing', 0, 0, 'medium', 'open', '2026-05-15 13:15:01', NULL, '2026-05-15 05:56:29'),
(3, 'reader-3', 'ops', 'Reader offline', 'ICU reader heartbeat missing', 0, 0, 'medium', 'open', '2026-05-15 11:56:29', NULL, '2026-05-15 05:56:29'),
(50, 'unknown-DBC51D1A-2026051511', 'ops', 'Unknown RFID tag', 'Unregistered tag DBC51D1A scanned at ICU', 0, 0, 'medium', 'done', '2026-05-15 12:27:38', '2026-05-15 11:44:56', '2026-05-15 05:57:51'),
(923, 'reader-4', 'ops', 'Reader offline', 'ICU Bed 02 - Anita Verma reader heartbeat missing', 0, 0, 'medium', 'open', '2026-05-15 12:14:58', NULL, '2026-05-15 06:14:58'),
(1258, 'unknown-TEST123-2026051511', 'ops', 'Unknown RFID tag', 'Unregistered tag TEST123 scanned at ICU', 0, 0, 'medium', 'open', '2026-05-15 12:21:17', NULL, '2026-05-15 06:21:17'),
(1405, 'unknown-F3E79413-2026051511', 'ops', 'Unknown RFID tag', 'Unregistered tag F3E79413 scanned at ICU Bed 02 - Anita Verma', 0, 0, 'medium', 'open', '2026-05-15 12:26:25', NULL, '2026-05-15 06:24:02'),
(1572, 'unknown-04064B0A9F6F80-2026051511', 'ops', 'Unknown RFID tag', 'Unregistered tag 04064B0A9F6F80 scanned at ICU', 0, 0, 'medium', 'open', '2026-05-15 12:27:03', NULL, '2026-05-15 06:27:03'),
(1654, 'unknown-618CD33E-2026051511', 'ops', 'Unknown RFID tag', 'Unregistered tag 618CD33E scanned at ICU', 0, 0, 'medium', 'open', '2026-05-15 12:28:23', NULL, '2026-05-15 06:28:23'),
(1703, 'unknown-643CA6CE-2026051511', 'ops', 'Unknown RFID tag', 'Unregistered tag 643CA6CE scanned at ICU', 0, 0, 'medium', 'open', '2026-05-15 12:29:20', NULL, '2026-05-15 06:29:20'),
(1738, 'unknown-1466D4A9-2026051512', 'ops', 'Unknown RFID tag', 'Unregistered tag 1466D4A9 scanned at ICU', 0, 0, 'medium', 'open', '2026-05-15 12:30:03', NULL, '2026-05-15 06:30:03'),
(1863, 'med-overdue-3', 'medication', 'Overdue medication', 'Ceftriaxone 1g overdue for Anita Verma', 2, 2, 'high', 'open', '2026-05-15 13:12:05', NULL, '2026-05-15 06:32:36'),
(2241, 'med-overdue-1', 'medication', 'Overdue medication', 'Paracetamol 650mg overdue for Rahul Sharma', 1, 1, 'high', 'open', '2026-05-15 13:15:01', NULL, '2026-05-15 06:46:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_resolved` (`is_resolved`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `batch_traces`
--
ALTER TABLE `batch_traces`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_batch` (`batchno`),
  ADD KEY `idx_scan_time` (`scan_time`);

--
-- Indexes for table `caretakers`
--
ALTER TABLE `caretakers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient` (`patient_id`);

--
-- Indexes for table `caretaker_accounts`
--
ALTER TABLE `caretaker_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_caretaker` (`caretaker_id`);

--
-- Indexes for table `caretaker_tokens`
--
ALTER TABLE `caretaker_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `discharge_checklist`
--
ALTER TABLE `discharge_checklist`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uid` (`uid`),
  ADD KEY `idx_item_type` (`item_type`),
  ADD KEY `idx_location` (`location_id`);

--
-- Indexes for table `iv_drips`
--
ALTER TABLE `iv_drips`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_status` (`patient_id`,`status`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `readerid` (`readerid`);

--
-- Indexes for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medicationadministrations`
--
ALTER TABLE `medicationadministrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `medicationschedule`
--
ALTER TABLE `medicationschedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_time` (`patient_id`,`scheduled_time`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `medication_verifications`
--
ALTER TABLE `medication_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_read` (`alert_id`,`admin_id`);

--
-- Indexes for table `offline_scan_queue`
--
ALTER TABLE `offline_scan_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patient_code` (`patient_code`),
  ADD UNIQUE KEY `rfiduid` (`rfiduid`),
  ADD KEY `idx_ward` (`ward_id`);

--
-- Indexes for table `patientsafetyprofiles`
--
ALTER TABLE `patientsafetyprofiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `patientid` (`patientid`);

--
-- Indexes for table `patientvitals`
--
ALTER TABLE `patientvitals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient` (`patient_id`);

--
-- Indexes for table `scanlogs`
--
ALTER TABLE `scanlogs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_time` (`scan_time`);

--
-- Indexes for table `staffmembers`
--
ALTER TABLE `staffmembers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `rfiduid` (`rfiduid`);

--
-- Indexes for table `systemlogs`
--
ALTER TABLE `systemlogs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `workflowtasks`
--
ALTER TABLE `workflowtasks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_key` (`task_key`),
  ADD KEY `idx_status_priority` (`status`,`priority`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `batch_traces`
--
ALTER TABLE `batch_traces`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `caretakers`
--
ALTER TABLE `caretakers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `caretaker_accounts`
--
ALTER TABLE `caretaker_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `caretaker_tokens`
--
ALTER TABLE `caretaker_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discharge_checklist`
--
ALTER TABLE `discharge_checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `iv_drips`
--
ALTER TABLE `iv_drips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `maintenance_schedule`
--
ALTER TABLE `maintenance_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medicationadministrations`
--
ALTER TABLE `medicationadministrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medicationschedule`
--
ALTER TABLE `medicationschedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `medication_verifications`
--
ALTER TABLE `medication_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=492;

--
-- AUTO_INCREMENT for table `offline_scan_queue`
--
ALTER TABLE `offline_scan_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patientsafetyprofiles`
--
ALTER TABLE `patientsafetyprofiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patientvitals`
--
ALTER TABLE `patientvitals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scanlogs`
--
ALTER TABLE `scanlogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `staffmembers`
--
ALTER TABLE `staffmembers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `systemlogs`
--
ALTER TABLE `systemlogs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `workflowtasks`
--
ALTER TABLE `workflowtasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4335;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
