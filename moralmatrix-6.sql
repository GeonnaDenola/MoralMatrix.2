-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 17, 2025 at 10:44 AM
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
-- Database: `moralmatrix`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `record_id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `account_type` enum('super_admin','administrator','ccdu','faculty','student','security') NOT NULL,
  `change_pass` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`record_id`, `id_number`, `email`, `password`, `account_type`, `change_pass`) VALUES
(1, '', 'superadmin@test.com', '$2y$10$ZAmvFGaaIvRk8Wvy.VjM3eEM6gStBhs0ODqeby4AlhHYmyuBlPEr6', 'super_admin', 0),
(2, '1111-1111', 'mainadmin@test.com', '$2y$10$FhwgAoap3CavVUvX89M3t.4hcatH8pfl.Mp8fMgZDXItaJSCH9.qS', 'administrator', 0),
(5, '7777-7777', 'mainccdu@test.com', '$2y$10$Sy3ZqVpYYa/R4VNvFv.M1.a/EGuc0jqnlmLS/JVzv.O/8IRV7TMSG', 'ccdu', 0),
(6, '4444-4444', 'mainfaculty@test.com', '$2y$10$8aodg435DmUSDazyuF3o2.NJn.yU6Rq39Rlg/XECKzZ.amHAvH6.i', 'faculty', 0),
(8, '5555-5555', 'student3@test.com', '$2y$10$hbyXXmU3xc0PdWRGnZubE.aDUbOvrkKFWUlNjhxfIcgmNwjFMRtG2', 'student', 1),
(14, '6767-0987', 'student99@test.com', '$2y$10$zoesV7qCHhPtQyKX.Oep0eyERXfkS3ehODhgFSyKbTMvn7iH6RknG', 'student', 1),
(15, '9999-9999', 'student577@test.com', '$2y$10$Tv5a9c0MRZvlbSYpLgKBMelXo3jnbbvbFGR6LSWuBW7omC52EPEy2', 'student', 1),
(16, '1212-2323', 'stdent48t5@test.com', '$2y$10$n9/Qn6SmOO0BEkqBvkTZG.FRiB9guwovztWT2Rb.rUwkjIMcDMu7a', 'student', 1),
(18, '0808-0808', 'student9845@test.com', '$2y$10$lVdCyjCMQSrRw5mRQ8XJruuHU0tOB0WS5LdxA3cRQqcSTRpbm4Iu6', 'student', 1),
(19, '8777-8777', 'mainfaculty02@test.com', '$2y$10$psx7g6wfFi9jpRJFHHT4muDVWw3.FEup6/ehM3fEKgiwhSeO0Rtra', 'faculty', 1),
(23, '8472-8649', 'mainstudent1@test.com', '$2y$10$8rYAyfkigEb/.dbnDiSvyO4R16nR0NNGnvqi1E5ZUX7TvOYjQTPIW', 'student', 0),
(24, '3444-3537', 'student5@test.com', '$2y$10$kE1y/RN.cSXGpTgcm1KKE.B159XGCU2/We3fuABGarmjt3uVMSkra', 'student', 1),
(26, '4534-4545', 'student6@test.com', '$2y$10$Lk2/pzUKn7piFSIVTWv92eIJvovhQcLF4P.z.ZrDF.JhNNzltsWe2', 'student', 1),
(27, '9854-9556', 'student7@test.com', '$2y$10$KnH3V6anj5Ojb8f0KAnjuuu6ZoRN.81t2CwAFB99Cn5vaNFu8mDP6', 'student', 1),
(28, '9090-9090', 'security1@test.com', '$2y$10$j/7zfbB4ytgMO/c4zu6MPuvyzOXRCD4lCzvNTduxYLevcNl4vbTVa', 'security', 0),
(30, '0945-9485', 'faculty1@test.com', '$2y$10$jfoILh3yI/6w9a50QnZ9h.RLo0qo/5mWpLKS7oWynwBehn.8Jtuse', 'faculty', 0),
(31, '3434-3434', 'admin3@test.com', '$2y$10$gX5icjBJV.QJcvKPcFtqSOY7d7WNDaHcY9bPApmpnhm4FDHZCw8ki', 'administrator', 0),
(36, '7548-5438', 'security@test.com', '$2y$10$K/5Nup77eqz4zhAau.l9au0UPVFvTYNTHXcQtS7LBUIehKYUdYHla', 'security', 1),
(37, '3843-7980', 'geodenola@gmail.com', '$2y$10$L66mcR8f0ekqQ6iCdx7qt.ahwkZELDHEy5uZusKZ1gvXAV8AjSDSK', 'ccdu', 0),
(39, '9854-3458', 'test@test.com', '$2y$10$WMQj0w8BTcuj9H9zDcgFceD4meFqauCnBE6ZjxULkPrrSy9zOynlO', 'ccdu', 0),
(42, '9845-4359', 'geonnadenola@gmail.com', '$2y$10$c53oHRwEwkNqHTT7SWIQmuW6gKNX1whP4PtvZNseKJ2n5R1V2Y8y2', 'faculty', 1),
(43, '2223-0775', 'kuropm23@gmail.com', '$2y$10$.81WXxg7lVJriCIkN7RixObaUrBdIs0OEFKN9rO5.Poii9pRwiy42', 'student', 0);

-- --------------------------------------------------------

--
-- Table structure for table `admin_account`
--

CREATE TABLE `admin_account` (
  `record_id` int(11) NOT NULL,
  `admin_id` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(50) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `f_create` varchar(2) DEFAULT NULL,
  `f_update` varchar(2) DEFAULT NULL,
  `f_delete` varchar(2) DEFAULT NULL,
  `s_create` varchar(2) DEFAULT NULL,
  `s_update` varchar(2) DEFAULT NULL,
  `s_delete` varchar(2) DEFAULT NULL,
  `a_create` varchar(2) DEFAULT NULL,
  `a_update` varchar(2) DEFAULT NULL,
  `a_delete` varchar(2) DEFAULT NULL,
  `c_create` varchar(2) DEFAULT NULL,
  `c_update` varchar(2) DEFAULT NULL,
  `c_delete` varchar(2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `change_pass` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_account`
--

INSERT INTO `admin_account` (`record_id`, `admin_id`, `first_name`, `last_name`, `middle_name`, `mobile`, `email`, `photo`, `f_create`, `f_update`, `f_delete`, `s_create`, `s_update`, `s_delete`, `a_create`, `a_update`, `a_delete`, `c_create`, `c_update`, `c_delete`, `created_at`, `updated_at`, `change_pass`) VALUES
(1, '1111-1111', 'Algernon', 'Angeles', 'Cruz', '09887677878', 'mainadmin@test.com', '1757697765_images.png', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-12 17:12:03', '2025-09-12 17:22:45', 1),
(4, '1212-1212', 'Geonna', 'Denola', 'Bcugan', '09567894565', 'admin2@test.com', '1759384389_6cc26574e468770a8b74a833082fc632_8305136916126025774.png', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-02 05:53:09', '2025-10-02 05:53:09', 1),
(6, '3434-3434', 'Maria', 'Enobio', 'Angelica', '09754556789', 'admin3@test.com', '1759502167_asus.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-10-03 14:36:07', '2025-10-03 14:36:07', 1);

-- --------------------------------------------------------

--
-- Table structure for table `ccdu_account`
--

CREATE TABLE `ccdu_account` (
  `record_id` int(11) NOT NULL,
  `ccdu_id` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(50) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `change_pass` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ccdu_account`
--

INSERT INTO `ccdu_account` (`record_id`, `ccdu_id`, `first_name`, `last_name`, `mobile`, `email`, `photo`, `created_at`, `updated_at`, `change_pass`) VALUES
(1, '7777-7777', 'Iba', 'Na', '09754756736', 'mainccdu@test.com', '1757698126_542753109_777680591472046_482913898270641425_n.jpg', '2025-09-12 17:28:46', '2025-09-12 17:28:46', 1),
(14, '3843-7980', 'Algernon', 'Angeles', '09786542435', 'geodenola@gmail.com', '', '2025-10-05 07:43:42', '2025-10-05 07:43:42', 1),
(16, '9854-3458', 'fjkvfj', 'ldkgnwl', '09876545678', 'test@test.com', '', '2025-10-05 07:56:52', '2025-10-05 07:56:52', 1);

-- --------------------------------------------------------

--
-- Table structure for table `community_service_entries`
--

CREATE TABLE `community_service_entries` (
  `entry_id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `violation_id` int(11) DEFAULT NULL,
  `validator_id` int(11) DEFAULT NULL,
  `hours` decimal(5,2) NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `photo_paths` text DEFAULT NULL,
  `service_date` date DEFAULT curdate(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_service_entries`
--

INSERT INTO `community_service_entries` (`entry_id`, `student_id`, `violation_id`, `validator_id`, `hours`, `remarks`, `comment`, `photo_paths`, `service_date`, `created_at`) VALUES
(1, '2223-0775', NULL, 10, 5.00, 'parking duty', 'eyyy', '[\"uploads/service/2223-0775_20251008_174006_8d98fdbf.png\",\"uploads/service/2223-0775_20251008_174006_e4bab25b.png\",\"uploads/service/2223-0775_20251008_174006_adf36823.png\"]', '2025-10-08', '2025-10-08 23:40:06'),
(2, '2223-0775', NULL, 10, 8.00, 'Maintenance cleanup', 'parang ewan', '[\"uploads/service/2223-0775_20251012_140026_a302bbb1.png\",\"uploads/service/2223-0775_20251012_140026_0e8b60ae.png\",\"uploads/service/2223-0775_20251012_140026_64bc30d2.png\",\"uploads/service/2223-0775_20251012_140026_8efbca41.png\"]', '2025-10-12', '2025-10-12 20:00:26'),
(3, '2223-0775', NULL, 10, 47.00, 'Maintenance cleanup', '', '[\"uploads/service/2223-0775_20251016_171345_5ffc5b1e.png\",\"uploads/service/2223-0775_20251016_171345_aa1e94d7.png\",\"uploads/service/2223-0775_20251016_171345_b6fb6cb6.png\"]', '2025-10-16', '2025-10-16 23:13:45');

-- --------------------------------------------------------

--
-- Table structure for table `community_service_evidence`
--

CREATE TABLE `community_service_evidence` (
  `evidence_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `validator_id` int(11) NOT NULL,
  `photo` blob DEFAULT NULL,
  `hours_completed` int(11) NOT NULL,
  `performance_rating` enum('excellent','good','Fair','Poor') NOT NULL,
  `remarks` text DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_account`
--

CREATE TABLE `faculty_account` (
  `record_id` int(11) NOT NULL,
  `faculty_id` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(50) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `institute` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `change_pass` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty_account`
--

INSERT INTO `faculty_account` (`record_id`, `faculty_id`, `first_name`, `last_name`, `mobile`, `email`, `photo`, `institute`, `created_at`, `updated_at`, `change_pass`) VALUES
(1, '4444-4444', 'dfEREHT', 'THDT', '09976567567', 'mainfaculty@test.com', '1757698162_images.png', 'IHTM', '2025-09-12 17:29:22', '2025-09-12 17:29:22', 1),
(2, '8777-8777', 'Algernon', 'Angeles', '09758475847', 'mainfaculty02@test.com', '1757965474_542753109_777680591472046_482913898270641425_n.jpg', 'IHTM', '2025-09-15 19:44:34', '2025-09-15 19:44:34', 1),
(5, '0945-9485', 'eoifrhbr', 'leibje', '09746587346', 'faculty1@test.com', '', 'IBCE', '2025-10-02 17:19:29', '2025-10-02 17:19:29', 1),
(9, '9845-4359', 'HAYAHAY', 'nsysynny', '09786686786', 'geonnadenola@gmail.com', '', '', '2025-10-05 11:57:09', '2025-10-05 11:57:09', 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `target_role` enum('student','faculty','security','ccdu') NOT NULL,
  `target_user_id` varchar(64) DEFAULT NULL,
  `type` enum('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `title` varchar(150) NOT NULL,
  `body` text DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `violation_id` int(11) DEFAULT NULL,
  `created_by` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `target_role`, `target_user_id`, `type`, `title`, `body`, `url`, `violation_id`, `created_by`, `created_at`, `read_at`) VALUES
(1, 'ccdu', NULL, 'warning', 'New violation reported by Security', 'Geonna Denola • Student ID: 2223-0775', '/MoralMatrix/ccdu/pending_reports.php#v42', 42, '9090-9090', '2025-10-16 02:35:49', NULL),
(2, 'ccdu', NULL, 'warning', 'New violation reported by Security', 'Geonna Denola • Student ID: 2223-0775', '/MoralMatrix/ccdu/pending_reports.php#v43', 43, '9090-9090', '2025-10-16 02:36:42', NULL),
(3, 'ccdu', NULL, 'warning', 'New violation reported by Security', 'Geonna Denola • Student ID: 2223-0775', '/MoralMatrix/ccdu/pending_reports.php#v44', 44, '9090-9090', '2025-10-16 20:35:46', NULL),
(4, 'ccdu', NULL, 'warning', 'New violation reported by Security', 'Geonna Denola • Student ID: 2223-0775', '/MoralMatrix/ccdu/pending_reports.php#v45', 45, '9090-9090', '2025-10-16 22:48:04', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `security_account`
--

CREATE TABLE `security_account` (
  `record_id` int(11) NOT NULL,
  `security_id` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(50) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `change_pass` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_account`
--

INSERT INTO `security_account` (`record_id`, `security_id`, `first_name`, `last_name`, `mobile`, `email`, `photo`, `created_at`, `updated_at`, `change_pass`) VALUES
(1, '9090-9090', 'Geonna', 'Denola', '09834475348', 'security1@test.com', '1759424151_Kinich Wallpaper from \'Saurian Egg Adventures\' Web Event.jpg', '2025-10-02 16:55:51', '2025-10-02 16:55:51', 1),
(3, '7548-5438', 'Geonna', 'Angeles', '09457543658', 'security@test.com', '', '2025-10-05 07:32:11', '2025-10-05 07:32:11', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_account`
--

CREATE TABLE `student_account` (
  `record_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(50) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `institute` varchar(255) DEFAULT NULL,
  `course` varchar(255) DEFAULT NULL,
  `level` int(11) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `guardian` varchar(50) DEFAULT NULL,
  `guardian_mobile` varchar(15) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `change_pass` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_account`
--

INSERT INTO `student_account` (`record_id`, `student_id`, `first_name`, `middle_name`, `last_name`, `mobile`, `email`, `photo`, `institute`, `course`, `level`, `section`, `guardian`, `guardian_mobile`, `created_at`, `updated_at`, `change_pass`) VALUES
(3, '5555-5555', 'fmdnah', 'ndysrn', 'nsysynny', '09876535434', 'student3@test.com', '1757699508_5e046493-3797-440f-8c17-a3f9af780d37.jpg', 'IAS', 'ABH', 1, 'B', 'hgfhsga', '09876656555', '2025-09-12 17:51:48', '2025-09-12 17:51:48', 1),
(10, '6767-0987', 'ucyvuyihn', 'tfcvygbuhnij', 'fyvgubhnj', '09567875436', 'student99@test.com', '', 'IBCE', 'BSIT', 1, 'A', 'dtfyuuihiuj', '09976578679', '2025-09-13 02:13:29', '2025-09-13 02:13:29', 1),
(11, '9999-9999', 'ywibKJGW', 'AWWIRUR', 'CWEIUCE', '09875667854', 'student577@test.com', '', 'IBCE', 'BSIT', 2, 'A', 'jdcriu', '09765476879', '2025-09-13 15:56:55', '2025-09-13 15:56:55', 1),
(12, '1212-2323', 'trcyvgbhjk', 'ufhkvnjd', 'iufnv', '09894759745', 'stdent48t5@test.com', '', 'IAS', 'BSBIO', 4, 'A', 'kfdubveoivuo', '09784535347', '2025-09-13 17:13:35', '2025-09-13 17:13:35', 1),
(13, '7777-7777', 'gyurfihp', 'iuerghirug', 'wuhg', '09756756899', 'student43535@test.com', '', 'IAS', 'BSBIO', 3, 'B', 'ueyfiUQ', '09765456778', '2025-09-13 17:23:48', '2025-09-13 17:23:48', 1),
(14, '0808-0808', 'SYUDVHIUdwhv', 'disufh', 'douofghoreu', '09457354785', 'student9845@test.com', '', 'IBCE', 'BSIT', 1, 'C', 'sjdnfufnew', '09764523787', '2025-09-13 17:24:47', '2025-09-13 17:24:47', 1),
(15, '8472-8649', 'Aaron', 'Ken', 'Manalotlot', '09655456789', 'mainstudent1@test.com', '1759400007_6cc26574e468770a8b74a833082fc632_8305136916126025774.png', 'IBCE', 'BSIT', 4, 'A', 'Mommy', '09787459438', '2025-10-02 06:02:09', '2025-10-02 10:13:27', 1),
(16, '3444-3537', 'Geonna', 'Lyzzet', 'Denola', '09676343958', 'student5@test.com', '1759417113_368566555_1368981020356581_2333135039467267829_n.jpg', 'IBCE', 'BSIT', 4, 'A', 'uygiuhoejfp', '09745837454', '2025-10-02 14:58:33', '2025-10-02 14:58:33', 1),
(18, '4534-4545', 'Maria', 'Angelica', 'Enobio', '09775634234', 'student6@test.com', '1759418579_ua.png', 'IBCE', 'BSIT', 4, 'A', 'dkjvksdfh', '09354365544', '2025-10-02 15:22:59', '2025-10-02 15:22:59', 1),
(19, '9854-9556', 'Algernon', 'Cruz', 'Angeles', '09675637485', 'student7@test.com', '1759419895_6cc26574e468770a8b74a833082fc632_8305136916126025774.png', 'IBCE', 'BSIT', 4, 'A', 'igowijr', '09578459834', '2025-10-02 15:44:55', '2025-10-02 15:44:55', 1),
(21, '2223-0775', 'Geonna', 'Lyzzet', 'Denola', '09453211402', 'kuropm23@gmail.com', 'c98c2b02c4d13d6d.png', 'IBCE', 'BSIT', 4, 'A', 'Mama', '09692004136', '2025-10-05 13:11:17', '2025-10-05 13:11:17', 1);

-- --------------------------------------------------------

--
-- Table structure for table `student_qr_keys`
--

CREATE TABLE `student_qr_keys` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `qr_key` char(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_qr_keys`
--

INSERT INTO `student_qr_keys` (`id`, `student_id`, `qr_key`, `created_at`, `revoked`) VALUES
(5, '6767-0987', '978b4f05b1c0af7cbab66d2d578ec176ff428ca94d642e254153dc7ec9390db0', '2025-09-13 02:13:29', 0),
(6, '9999-9999', '5c968ffaeb563afa848d141942020881c5c1644a4dd07890de1500225912ba96', '2025-09-13 15:56:55', 0),
(7, '1212-2323', '710261578667aef88be464d42c525303ac9b6bbc1c078e1959979eb9497745cd', '2025-09-13 17:13:35', 0),
(8, '0808-0808', '97d98a3103cc37464c30fb518816c5bcd2309c43d6cedc7849084b6b37742a95', '2025-09-13 17:24:47', 0),
(9, '8472-8649', 'b457d6b87ed31ecb2997c376364a998dc39119daa46cb455ed97a3bd54cac815', '2025-10-02 06:02:09', 0),
(10, '3444-3537', '807611fc0645ce146f97b4ef6716d1a1dfcca1e6bf6063a4eb499e38d5d039d8', '2025-10-02 14:58:33', 0),
(12, '4534-4545', '1c2f9f33d16725ec4989c21e26c1945c97f18d8cfde491360541b836369852f2', '2025-10-02 15:22:59', 0),
(13, '9854-9556', '0f741bf7f1fba233baefe86c534a12b680b8d702eb880c8fb0ad94e211b1992c', '2025-10-02 15:44:55', 0),
(15, '2223-0775', '1040e9ddb4e9ace5df80bdb3f4eb2995733f34f53eb7a6921d1dad6874f71106', '2025-10-05 13:11:17', 0);

-- --------------------------------------------------------

--
-- Table structure for table `student_violation`
--

CREATE TABLE `student_violation` (
  `violation_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `offense_category` enum('light','moderate','grave') NOT NULL,
  `offense_type` varchar(50) NOT NULL,
  `offense_details` text NOT NULL,
  `description` text NOT NULL,
  `photo` blob DEFAULT NULL,
  `reported_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_by` varchar(50) NOT NULL,
  `submitted_role` enum('faculty','ccdu','security') NOT NULL,
  `reviewed_by` varchar(50) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_violation`
--

INSERT INTO `student_violation` (`violation_id`, `student_id`, `offense_category`, `offense_type`, `offense_details`, `description`, `photo`, `reported_at`, `status`, `submitted_by`, `submitted_role`, `reviewed_by`, `reviewed_at`, `review_notes`) VALUES
(13, '5555-5555', 'light', 'uniform', '[\"skirt\"]', '', 0x313735373934393631345f3534323735333130395f3737373638303539313437323034365f3438323931333839383237303634313432355f6e2e6a7067, '2025-09-15 23:20:14', 'approved', '4444-4444', 'faculty', NULL, NULL, NULL),
(14, '0808-0808', 'grave', 'integrity_dishonesty', '[\"forgery\"]', '', '', '2025-09-16 01:44:40', 'approved', '4444-4444', 'faculty', NULL, NULL, NULL),
(15, '5555-5555', 'grave', 'property_theft', '[\"destruction_of_property\"]', '', '', '2025-09-16 04:48:24', 'approved', '4444-4444', 'faculty', NULL, NULL, NULL),
(16, '5555-5555', 'light', 'id', '[\"borrowed\"]', '', 0x313735383333363930395f494d4732303235303833303136333331322e6a7067, '2025-09-20 10:55:09', 'approved', '7777-7777', 'ccdu', NULL, NULL, NULL),
(17, '5555-5555', 'light', 'id', '[\"no_id\"]', 'vghjbkjnlk', 0x313735383333363933365f494d4732303235303833303136333331322e6a7067, '2025-09-20 10:55:36', 'approved', '7777-7777', 'ccdu', NULL, NULL, NULL),
(18, '5555-5555', 'moderate', 'improper_conduct', '[\"vulgar\"]', 'bbkjl', 0x313735383333373033395f494d4732303235303833303136333331322e6a7067, '2025-09-20 10:57:19', 'approved', '7777-7777', 'ccdu', NULL, NULL, NULL),
(19, '5555-5555', 'grave', 'integrity_dishonesty', '[\"forgery\"]', 'ifhuejiokd', 0x313735383334303635375f4b696e6963682057616c6c70617065722066726f6d20275361757269616e2045676720416476656e74757265732720576562204576656e742e6a7067, '2025-09-20 11:57:37', 'approved', '7777-7777', 'ccdu', NULL, NULL, NULL),
(20, '7777-7777', 'grave', 'integrity_dishonesty', '[\"forgery\"]', 'tarantado kasi', 0x313735383830363932305f32346536386139372d646433352d346430372d383337382d3266343935336435343964312e6a7067, '2025-09-25 21:28:40', 'approved', '7777-7777', 'ccdu', NULL, NULL, NULL),
(21, '8472-8649', 'light', 'id', '[\"no_id\"]', 'maitim', 0x313735393339303035325f33392d636162626167652d706e672d696d6167652e706e67, '2025-10-02 15:27:32', 'approved', '7777-7777', 'faculty', NULL, NULL, NULL),
(22, '9854-9556', 'light', 'uniform', '[\"socks\"]', 'N/A', '', '2025-10-03 01:09:15', 'approved', '9090-9090', 'faculty', NULL, NULL, NULL),
(23, '9854-9556', 'moderate', 'improper_conduct', '[\"vulgar\"]', '', '', '2025-10-03 01:23:24', 'approved', '9090-9090', '', NULL, NULL, NULL),
(24, '5555-5555', 'moderate', 'improper_conduct', '[\"vulgar\"]', '', '', '2025-10-03 01:24:14', 'pending', '0945-9485', 'faculty', NULL, NULL, NULL),
(25, '9854-9556', 'light', 'accessories', '[\"piercings\"]', '', '', '2025-10-03 01:58:18', 'pending', '9090-9090', '', NULL, NULL, NULL),
(26, '4534-4545', 'moderate', 'gadget_misuse', '[\"cp_classes\"]', 'maitim', 0x313735393432383130395f33392d636162626167652d706e672d696d6167652e706e67, '2025-10-03 02:01:49', 'approved', '0945-9485', 'faculty', NULL, NULL, NULL),
(27, '3444-3537', 'light', 'id', '[\"no_id\"]', '', '', '2025-10-03 02:21:15', 'approved', '9090-9090', '', NULL, NULL, NULL),
(28, '3444-3537', 'grave', 'substance_addiction', '[\"smoking\"]', '', '', '2025-10-03 02:31:38', 'approved', '9090-9090', 'security', NULL, NULL, NULL),
(29, '9854-9556', 'moderate', 'gadget_misuse', '[\"gadgets_functions\"]', '', '', '2025-10-05 16:00:16', 'approved', '9854-3458', 'faculty', NULL, NULL, NULL),
(30, '9854-9556', 'moderate', 'gadget_misuse', '[\"gadgets_functions\"]', '', '', '2025-10-05 16:45:09', 'approved', '9854-3458', 'faculty', NULL, NULL, NULL),
(31, '9854-9556', 'light', 'accessories', '[\"skirt\",\"crop_top\",\"sando\",\"hair_color\"]', '', '', '2025-10-05 16:45:55', 'approved', '9854-3458', 'faculty', NULL, NULL, NULL),
(32, '9854-9556', 'light', 'accessories', '[\"crop_top\",\"sando\",\"piercings\",\"hair_color\"]', '', '', '2025-10-05 17:20:48', 'approved', '7777-7777', 'faculty', NULL, NULL, NULL),
(33, '2223-0775', 'light', 'uniform', '[\"no_id\",\"socks\",\"skirt\"]', '', 0x313735393637303335315f36636332363537346534363837373061386237346138333330383266633633325f383330353133363931363132363032353737342e706e67, '2025-10-05 21:19:11', 'approved', '7777-7777', 'faculty', NULL, NULL, NULL),
(34, '2223-0775', 'grave', 'property_theft', '[\"firearms\"]', '', '', '2025-10-08 23:33:13', 'approved', '7777-7777', 'faculty', NULL, NULL, NULL),
(35, '2223-0775', 'grave', 'threats_disrespect', '[\"hooliganism\",\"theft\"]', '', '', '2025-10-09 12:44:24', 'approved', 'unknown', 'faculty', NULL, NULL, NULL),
(36, '9854-9556', 'light', 'uniform', '[\"socks\",\"skirt\"]', '', '', '2025-10-09 13:08:47', 'approved', 'unknown', 'faculty', NULL, NULL, NULL),
(37, '9854-9556', 'light', 'uniform', '[\"socks\",\"skirt\"]', '', '', '2025-10-09 13:08:50', 'approved', 'unknown', 'faculty', NULL, NULL, NULL),
(38, '9854-9556', 'light', 'uniform', '[\"socks\"]', '', '', '2025-10-09 14:17:24', 'approved', '9090-9090', 'faculty', NULL, NULL, NULL),
(39, '2223-0775', 'light', 'id', '[\"borrowed\"]', '', '', '2025-10-16 00:14:43', 'pending', '9090-9090', 'security', NULL, NULL, NULL),
(40, '2223-0775', 'light', 'id', '[\"no_id\"]', '', '', '2025-10-16 00:28:17', 'pending', '9090-9090', 'security', NULL, NULL, NULL),
(41, '2223-0775', 'light', 'uniform', '[\"skirt\"]', '', '', '2025-10-16 02:28:30', 'pending', '9090-9090', 'security', NULL, NULL, NULL),
(42, '2223-0775', 'light', 'uniform', '[\"skirt\"]', '', '', '2025-10-16 02:35:49', 'pending', '9090-9090', 'security', NULL, NULL, NULL),
(43, '2223-0775', 'light', 'id', '[\"borrowed\"]', '', '', '2025-10-16 02:36:42', 'pending', '9090-9090', 'security', NULL, NULL, NULL),
(44, '2223-0775', 'moderate', 'improper_conduct', '[\"rough_behavior\"]', '', '', '2025-10-16 20:35:46', 'pending', '9090-9090', 'security', NULL, NULL, NULL),
(45, '2223-0775', 'light', 'accessories', '[\"piercings\"]', '', '', '2025-10-16 22:48:04', 'pending', '9090-9090', 'security', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `super_admin`
--

CREATE TABLE `super_admin` (
  `record_id` int(11) NOT NULL,
  `id_number` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `email` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admin`
--

INSERT INTO `super_admin` (`record_id`, `id_number`, `first_name`, `last_name`, `mobile`, `email`, `created_at`, `updated_at`) VALUES
(1, '', '', '', '', 'superadmin@test.com', '2025-09-12 17:11:33', '2025-09-12 17:11:33');

-- --------------------------------------------------------

--
-- Table structure for table `validator_account`
--

CREATE TABLE `validator_account` (
  `validator_id` int(11) NOT NULL,
  `v_username` varchar(50) NOT NULL,
  `v_password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `email` varchar(50) NOT NULL,
  `validator_type` enum('inside','outside') NOT NULL DEFAULT 'inside',
  `designation` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `validator_account`
--

INSERT INTO `validator_account` (`validator_id`, `v_username`, `v_password`, `created_at`, `expires_at`, `active`, `email`, `validator_type`, `designation`) VALUES
(1, 'Geonna', '$2y$10$rf3FOycQMV5jtFvADS4GEO.CZpDmTk5sqjEUzTcdFvmdHvUjrQ906', '2025-09-15 04:57:09', '0000-00-00 00:00:00', 1, '', 'inside', NULL),
(2, 'Aaron Manaloto', '', '2025-09-15 04:59:47', '0000-00-00 00:00:00', 1, '', 'inside', NULL),
(3, 'Maria Angelica', '', '2025-09-15 05:00:14', '0000-00-00 00:00:00', 1, '', 'inside', NULL),
(4, 'Algernon Angeles', '2Z4y99FRBGSU', '2025-09-15 05:36:28', '0000-00-00 00:00:00', 1, '', 'inside', NULL),
(5, 'rtgrfwedw', '14*kwtU$JC', NULL, '0000-00-00 00:00:00', 1, 'ddrgs@test.com', 'inside', NULL),
(6, 'abrvjknlk', 'lIxxuqjQjPWD', '2025-09-18 03:30:37', '0000-00-00 00:00:00', 1, 'erbIUB@Ttest.com', 'inside', NULL),
(7, 'sduvhoisdh', '910PqsZ@Gp', '2025-09-18 03:43:46', '0000-00-00 00:00:00', 0, 'dsddinoi@test.com', 'outside', 'LIBRARY'),
(10, 'Lyzzet', '$2y$10$Mr1045stLBoPSK/Rtn4IyO45dcOPPjKPSo0u/q3OxVGfRIC0jRUhW', '2025-10-08 01:16:55', '0000-00-00 00:00:00', 1, 'kuropm23@gmail.com', 'inside', 'Maintenance');

-- --------------------------------------------------------

--
-- Table structure for table `validator_student_assignment`
--

CREATE TABLE `validator_student_assignment` (
  `assignment_id` int(11) NOT NULL,
  `validator_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `starts_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ends_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `validator_student_assignment`
--

INSERT INTO `validator_student_assignment` (`assignment_id`, `validator_id`, `student_id`, `starts_at`, `ends_at`, `notes`, `assigned_at`) VALUES
(13, 10, '5555-5555', '2025-10-09 02:28:24', NULL, NULL, '2025-10-09 21:29:56'),
(15, 2, '5555-5555', '2025-09-18 08:54:41', NULL, NULL, '2025-09-20 13:28:15'),
(18, 7, '5555-5555', '2025-09-20 13:27:55', NULL, NULL, '2025-09-20 13:35:06'),
(19, 5, '5555-5555', '2025-09-20 13:36:13', NULL, NULL, '2025-09-25 20:58:45'),
(20, 5, '7777-7777', '2025-09-25 21:28:58', NULL, NULL, '2025-09-25 21:28:58'),
(21, 10, '8472-8649', '2025-10-09 01:28:00', NULL, NULL, '2025-10-09 01:28:00'),
(23, 10, '9854-9556', '2025-10-08 20:55:11', NULL, NULL, '2025-10-09 02:20:06'),
(33, 10, '2223-0775', '2025-10-08 01:20:45', NULL, NULL, '2025-10-09 01:16:45');

-- --------------------------------------------------------

--
-- Table structure for table `violation_details`
--

CREATE TABLE `violation_details` (
  `detail_id` int(11) NOT NULL,
  `violation_id` int(11) NOT NULL,
  `offense_code` varchar(100) NOT NULL,
  `offense_label` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `id_number` (`id_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_account`
--
ALTER TABLE `admin_account`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `ccdu_account`
--
ALTER TABLE `ccdu_account`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `community_service_entries`
--
ALTER TABLE `community_service_entries`
  ADD PRIMARY KEY (`entry_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `violation_id` (`violation_id`),
  ADD KEY `validator_id` (`validator_id`),
  ADD KEY `service_date` (`service_date`);

--
-- Indexes for table `community_service_evidence`
--
ALTER TABLE `community_service_evidence`
  ADD PRIMARY KEY (`evidence_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `validator_id` (`validator_id`);

--
-- Indexes for table `faculty_account`
--
ALTER TABLE `faculty_account`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_target` (`target_role`,`target_user_id`,`read_at`,`created_at`),
  ADD KEY `idx_violation` (`violation_id`);

--
-- Indexes for table `security_account`
--
ALTER TABLE `security_account`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student_account`
--
ALTER TABLE `student_account`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `student_qr_keys`
--
ALTER TABLE `student_qr_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_key` (`qr_key`),
  ADD KEY `idx_qr_student_id` (`student_id`);

--
-- Indexes for table `student_violation`
--
ALTER TABLE `student_violation`
  ADD PRIMARY KEY (`violation_id`),
  ADD KEY `idx_violation_student` (`student_id`),
  ADD KEY `idx_violation_status` (`status`,`reported_at`),
  ADD KEY `idx_violation_student_status` (`student_id`,`status`),
  ADD KEY `idx_violation_submitter` (`submitted_by`,`status`);

--
-- Indexes for table `super_admin`
--
ALTER TABLE `super_admin`
  ADD PRIMARY KEY (`record_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `validator_account`
--
ALTER TABLE `validator_account`
  ADD PRIMARY KEY (`validator_id`),
  ADD UNIQUE KEY `username` (`v_username`);

--
-- Indexes for table `validator_student_assignment`
--
ALTER TABLE `validator_student_assignment`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uniq_validator_student` (`validator_id`,`student_id`),
  ADD KEY `idx_validator` (`validator_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `violation_details`
--
ALTER TABLE `violation_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `fk_violation_details_violation` (`violation_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `admin_account`
--
ALTER TABLE `admin_account`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `ccdu_account`
--
ALTER TABLE `ccdu_account`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `community_service_entries`
--
ALTER TABLE `community_service_entries`
  MODIFY `entry_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `community_service_evidence`
--
ALTER TABLE `community_service_evidence`
  MODIFY `evidence_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty_account`
--
ALTER TABLE `faculty_account`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `security_account`
--
ALTER TABLE `security_account`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_account`
--
ALTER TABLE `student_account`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `student_qr_keys`
--
ALTER TABLE `student_qr_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `student_violation`
--
ALTER TABLE `student_violation`
  MODIFY `violation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `super_admin`
--
ALTER TABLE `super_admin`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `validator_account`
--
ALTER TABLE `validator_account`
  MODIFY `validator_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `validator_student_assignment`
--
ALTER TABLE `validator_student_assignment`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `violation_details`
--
ALTER TABLE `violation_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `community_service_evidence`
--
ALTER TABLE `community_service_evidence`
  ADD CONSTRAINT `community_service_evidence_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student_account` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `community_service_evidence_ibfk_2` FOREIGN KEY (`validator_id`) REFERENCES `validator_account` (`validator_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_qr_keys`
--
ALTER TABLE `student_qr_keys`
  ADD CONSTRAINT `fk_qr_student` FOREIGN KEY (`student_id`) REFERENCES `student_account` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_violation`
--
ALTER TABLE `student_violation`
  ADD CONSTRAINT `fk_violation_student` FOREIGN KEY (`student_id`) REFERENCES `student_account` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `validator_student_assignment`
--
ALTER TABLE `validator_student_assignment`
  ADD CONSTRAINT `validator_student_assignment_ibfk_1` FOREIGN KEY (`validator_id`) REFERENCES `validator_account` (`validator_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `validator_student_assignment_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student_account` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `violation_details`
--
ALTER TABLE `violation_details`
  ADD CONSTRAINT `fk_violation_details_violation` FOREIGN KEY (`violation_id`) REFERENCES `student_violation` (`violation_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
