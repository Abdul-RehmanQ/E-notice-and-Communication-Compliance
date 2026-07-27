-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 16, 2026 at 10:41 AM
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
-- Database: `project`
--

-- --------------------------------------------------------

--
-- Table structure for table `community_supervisor`
--

CREATE TABLE `community_supervisor` (
  `supervisor_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `community_supervisor`
--

INSERT INTO `community_supervisor` (`supervisor_id`, `name`, `department`) VALUES
(109, 'Ali Ahmed', 'computer science');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_title` varchar(150) NOT NULL,
  `department` varchar(100) NOT NULL,
  `semester_no` tinyint(3) UNSIGNED NOT NULL,
  `credit_hours` tinyint(3) UNSIGNED DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_title`, `department`, `semester_no`, `credit_hours`) VALUES
(17, 'BCS-001', 'course-001', 'computer science', 1, 3),
(18, 'BCS-002', 'course-002', 'computer science', 5, 4),
(19, 'BCS-003', 'course-003', 'computer science', 3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `course_notifications`
--

CREATE TABLE `course_notifications` (
  `notification_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_offerings`
--

CREATE TABLE `course_offerings` (
  `offering_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `session` varchar(255) NOT NULL,
  `semester_no` tinyint(3) UNSIGNED NOT NULL,
  `section` varchar(10) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_offerings`
--

INSERT INTO `course_offerings` (`offering_id`, `course_id`, `department`, `session`, `semester_no`, `section`, `created_at`) VALUES
(6, 17, 'computer science', '2022-2026', 1, 'A', '2026-02-14 20:45:54'),
(7, 18, 'computer science', '2026-2030', 5, 'A', '2026-02-17 09:40:59');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `post_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `status` enum('sent','failed') DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `recipient_id`, `recipient_email`, `post_id`, `subject`, `body`, `sent_at`, `status`) VALUES
(5, 94, 91, 'ahmedalu6y@gmail.com', NULL, 'Re: testing the email feature.', '\r\n            <div style=\'font-family: Arial, sans-serif; padding: 20px;\'>\r\n                <h3>Re: testing the email feature.</h3>\r\n                <p>please send a text on whatsapp if u get this email. Also attach a picture.</p>\r\n                <hr>\r\n                <p style=\'color: #666; font-size: 12px;\'>This message was sent via University Portal by student-01</p>\r\n            </div>\r\n        ', '2026-02-14 21:46:55', 'failed'),
(6, 94, 91, 'ahmedalu6y@gmail.com', NULL, 'Re: testing the email feature.', '\r\n            <div style=\'font-family: Arial, sans-serif; padding: 20px;\'>\r\n                <h3>Re: testing the email feature.</h3>\r\n                <p>please send a text on whatsapp if u get this email. Also attach a picture.</p>\r\n                <hr>\r\n                <p style=\'color: #666; font-size: 12px;\'>This message was sent via University Portal by student-01</p>\r\n            </div>\r\n        ', '2026-02-14 21:49:38', 'sent'),
(7, 94, 91, 'ahmedalu6y@gmail.com', NULL, 'Re: testing the email feature.', '\r\n            <div style=\'font-family: Arial, sans-serif; padding: 20px;\'>\r\n                <h3>Re: testing the email feature.</h3>\r\n                <p>Do the same, but open the section where emails are shown.</p>\r\n                <hr>\r\n                <p style=\'color: #666; font-size: 12px;\'>This message was sent via University Portal by student-01</p>\r\n            </div>\r\n        ', '2026-02-14 22:07:21', 'sent'),
(8, 94, 91, 'ahsinsaghir@gmail.com', NULL, 'Re: test notidbvasfbi', '\r\n            <div style=\'font-family: Arial, sans-serif; padding: 20px;\'>\r\n                <h3>Re: test notidbvasfbi</h3>\r\n                <p>sedrftgyhujik</p>\r\n                <hr>\r\n                <p style=\'color: #666; font-size: 12px;\'>This message was sent via University Portal by student-01</p>\r\n            </div>\r\n        ', '2026-04-08 14:11:12', 'sent');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for table `activity_logs`
-- Comprehensive audit trail for all actors
-- --------------------------------------------------------

CREATE TABLE `activity_logs` (
  `activity_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `actor_role` ENUM('student','teacher','community_supervisor','super_admin') NOT NULL,
  `action_type` VARCHAR(80) NOT NULL,
  `entity_type` VARCHAR(80) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `action_details` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_created` (`user_id`, `created_at`),
  INDEX `idx_action_type` (`action_type`, `created_at`),
  INDEX `idx_entity` (`entity_type`, `entity_id`),
  INDEX `idx_actor_role` (`actor_role`, `created_at`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_recipients`
--

CREATE TABLE `notification_recipients` (
  `notification_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `post_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `image_data` longblob DEFAULT NULL,
  `image_type` varchar(50) DEFAULT NULL,
  `image_size` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `scope` varchar(20) DEFAULT 'department',
  `status` enum('pending','approved','rejected') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_reviews`
--

CREATE TABLE `post_reviews` (
  `review_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `rejection_reason` text DEFAULT NULL,
  `reviewed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(255) NOT NULL,
  `Roll_no` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `semester_no` tinyint(3) UNSIGNED NOT NULL,
  `section` varchar(10) NOT NULL DEFAULT 'A',
  `session` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `Roll_no`, `name`, `department`, `semester_no`, `section`, `session`) VALUES
(94, 'FA22-BCS-001', 'student-01', 'computer science', 1, 'A', '2022-2026'),
(95, 'FA22-BCS-002', 'student-02', 'computer science', 1, 'A', '2022-2026'),
(96, 'FA22-BCS-003', 'student-03', 'computer science', 1, 'A', '2022-2026'),
(97, 'FA22-BCS-004', 'student-04', 'computer science', 1, 'A', '2022-2026'),
(98, 'FA22-BCS-005', 'student-05', 'computer science', 1, 'A', '2022-2026'),
(99, 'FA22-BCS-006', 'student-06', 'computer science', 1, 'A', '2022-2026'),
(100, 'FA22-BCS-007', 'student-07', 'computer science', 1, 'A', '2022-2026'),
(101, 'FA22-BCS-008', 'student-08', 'computer science', 1, 'A', '2022-2026'),
(102, 'FA22-BCS-009', 'student-09', 'computer science', 1, 'A', '2022-2026'),
(103, 'FA22-BCS-010', 'student-10', 'computer science', 1, 'B', '2022-2026'),
(104, 'FA22-BCS-011', 'student-11', 'computer science', 1, 'B', '2022-2026'),
(105, 'FA22-BCS-012', 'student-12', 'computer science', 1, 'B', '2022-2026'),
(106, 'FA22-BCS-013', 'student-13', 'computer science', 1, 'B', '2022-2026'),
(107, 'FA22-BCS-014', 'student-14', 'computer science', 1, 'B', '2022-2026'),
(108, 'FA22-BCS-015', 'student-15', 'computer science', 1, 'B', '2022-2026');

-- --------------------------------------------------------

--
-- Table structure for table `student_course_enrollments`
--

CREATE TABLE `student_course_enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `enrolled_by` int(11) NOT NULL,
  `enrolled_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','dropped') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_course_enrollments`
--

INSERT INTO `student_course_enrollments` (`enrollment_id`, `student_id`, `course_id`, `offering_id`, `enrolled_by`, `enrolled_at`, `status`) VALUES
(47, 94, 17, 6, 45, '2026-02-14 21:14:53', 'active'),
(48, 95, 17, 6, 45, '2026-02-14 21:14:53', 'active'),
(49, 96, 17, 6, 45, '2026-02-14 21:14:53', 'active'),
(50, 97, 17, 6, 45, '2026-02-14 21:14:53', 'active'),
(51, 98, 17, 6, 45, '2026-02-14 21:14:53', 'active'),
(52, 99, 17, 6, 45, '2026-02-14 21:14:53', 'active'),
(53, 100, 17, 6, 45, '2026-02-14 21:14:53', 'active'),
(54, 101, 17, 6, 45, '2026-02-14 21:14:53', 'active'),
(55, 102, 17, 6, 45, '2026-02-14 21:14:53', 'active');

--
-- Triggers `student_course_enrollments`
--
DELIMITER $$
CREATE TRIGGER `trg_validate_student_offering_ins` BEFORE INSERT ON `student_course_enrollments` FOR EACH ROW BEGIN
    DECLARE v_s_department VARCHAR(255);
    DECLARE v_s_session VARCHAR(255);
    DECLARE v_s_semester TINYINT UNSIGNED;
    DECLARE v_s_section VARCHAR(10);

    DECLARE v_o_department VARCHAR(100);
    DECLARE v_o_session VARCHAR(255);
    DECLARE v_o_semester TINYINT UNSIGNED;
    DECLARE v_o_section VARCHAR(10);
    DECLARE v_o_course_id INT;

    SELECT department, session, semester_no, UPPER(TRIM(section))
      INTO v_s_department, v_s_session, v_s_semester, v_s_section
    FROM student
    WHERE student_id = NEW.student_id;

    SELECT department, session, semester_no, UPPER(TRIM(section)), course_id
      INTO v_o_department, v_o_session, v_o_semester, v_o_section, v_o_course_id
    FROM course_offerings
    WHERE offering_id = NEW.offering_id;

    IF v_o_course_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid offering_id.';
    END IF;

    IF v_s_department IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid student_id.';
    END IF;

    IF v_s_department <> v_o_department
       OR v_s_session <> v_o_session
       OR v_s_semester <> v_o_semester
       OR v_s_section <> v_o_section THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Student group (dept/session/semester/section) does not match selected offering.';
    END IF;

    SET NEW.course_id = v_o_course_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_validate_student_offering_upd` BEFORE UPDATE ON `student_course_enrollments` FOR EACH ROW BEGIN
    DECLARE v_s_department VARCHAR(255);
    DECLARE v_s_session VARCHAR(255);
    DECLARE v_s_semester TINYINT UNSIGNED;
    DECLARE v_s_section VARCHAR(10);

    DECLARE v_o_department VARCHAR(100);
    DECLARE v_o_session VARCHAR(255);
    DECLARE v_o_semester TINYINT UNSIGNED;
    DECLARE v_o_section VARCHAR(10);
    DECLARE v_o_course_id INT;

    SELECT department, session, semester_no, UPPER(TRIM(section))
      INTO v_s_department, v_s_session, v_s_semester, v_s_section
    FROM student
    WHERE student_id = NEW.student_id;

    SELECT department, session, semester_no, UPPER(TRIM(section)), course_id
      INTO v_o_department, v_o_session, v_o_semester, v_o_section, v_o_course_id
    FROM course_offerings
    WHERE offering_id = NEW.offering_id;

    IF v_o_course_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid offering_id.';
    END IF;

    IF v_s_department IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid student_id.';
    END IF;

    IF v_s_department <> v_o_department
       OR v_s_session <> v_o_session
       OR v_s_semester <> v_o_semester
       OR v_s_section <> v_o_section THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Student group (dept/session/semester/section) does not match selected offering.';
    END IF;

    SET NEW.course_id = v_o_course_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `super_admin`
--

CREATE TABLE `super_admin` (
  `super_admin_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admin`
--

INSERT INTO `super_admin` (`super_admin_id`, `name`, `department`) VALUES
(45, 'AR', 'computer science');

-- --------------------------------------------------------

--
-- Table structure for table `teacher`
--

CREATE TABLE `teacher` (
  `teacher_id` int(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher`
--

INSERT INTO `teacher` (`teacher_id`, `name`, `department`) VALUES
(91, 'teacher01', 'computer science'),
(92, 'teacher02', 'computer science'),
(93, 'teacher03', 'computer science');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_course_assignments`
--

CREATE TABLE `teacher_course_assignments` (
  `assignment_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `offering_id` int(11) NOT NULL,
  `assigned_by` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_course_assignments`
--

INSERT INTO `teacher_course_assignments` (`assignment_id`, `teacher_id`, `course_id`, `offering_id`, `assigned_by`, `assigned_at`) VALUES
(10, 91, 17, 6, 45, '2026-02-14 20:45:54'),
(11, 92, 18, 7, 45, '2026-02-17 09:40:59');

--
-- Triggers `teacher_course_assignments`
--
DELIMITER $$
CREATE TRIGGER `trg_sync_tca_course_ins` BEFORE INSERT ON `teacher_course_assignments` FOR EACH ROW BEGIN
    DECLARE v_course_id INT;
    DECLARE v_off_department VARCHAR(100);
    DECLARE v_teacher_department VARCHAR(255);

    SELECT course_id, department
      INTO v_course_id, v_off_department
    FROM course_offerings
    WHERE offering_id = NEW.offering_id;

    IF v_course_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid offering_id in teacher assignment.';
    END IF;

    SELECT department
      INTO v_teacher_department
    FROM teacher
    WHERE teacher_id = NEW.teacher_id;

    IF v_teacher_department IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid teacher_id.';
    END IF;

    IF v_teacher_department <> v_off_department THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Teacher department must match offering department.';
    END IF;

    SET NEW.course_id = v_course_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_sync_tca_course_upd` BEFORE UPDATE ON `teacher_course_assignments` FOR EACH ROW BEGIN
    DECLARE v_course_id INT;
    DECLARE v_off_department VARCHAR(100);
    DECLARE v_teacher_department VARCHAR(255);

    SELECT course_id, department
      INTO v_course_id, v_off_department
    FROM course_offerings
    WHERE offering_id = NEW.offering_id;

    IF v_course_id IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid offering_id in teacher assignment.';
    END IF;

    SELECT department
      INTO v_teacher_department
    FROM teacher
    WHERE teacher_id = NEW.teacher_id;

    IF v_teacher_department IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid teacher_id.';
    END IF;

    IF v_teacher_department <> v_off_department THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Teacher department must match offering department.';
    END IF;

    SET NEW.course_id = v_course_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `login_time` timestamp(6) NULL DEFAULT NULL,
  `logout_time` timestamp(6) NULL DEFAULT NULL,
  `role` enum('student','teacher','community_supervisor','super_admin') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `email`, `password`, `login_time`, `logout_time`, `role`) VALUES
(45, 'superadmin01@mail.com', '$argon2id$v=19$m=65536,t=4,p=1$azdyWGxvNmlPc3QuNXU5Vw$p62vDX5PquhkhRNE9+5114/D33zArTcDj+ystvex/u0', '2026-04-16 08:21:57.000000', '2026-02-14 15:36:49.000000', 'super_admin'),
(91, 'ahmedalu6y@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$OEVmWHJFY080RGJ3dzVuVg$p+Pzu/FeVPk3s3cK3hCgXeeKSrY5pi2aaYwtwJvx/00', '2026-04-16 08:21:06.000000', '2026-04-08 09:08:26.000000', 'teacher'),
(92, 'teacher02@uni.edu', '$argon2id$v=19$m=65536,t=4,p=1$TlBDYzFjYmN6Y05nN3llMg$Q7rvbZG2OsTj7RBbXbkX126HtNHNE2cU4bjBWLwcoEs', NULL, NULL, 'teacher'),
(93, 'teacher03@uni.edu', '$argon2id$v=19$m=65536,t=4,p=1$TmtlS1IxUms5NWp1bTl5Sg$iE88T4RcsHhp5QVpy3jMMp5DDmfAHyPhFqIBlIoH43M', NULL, NULL, 'teacher'),
(94, 'abdrehmanq76@gmail.com', '$argon2id$v=19$m=65536,t=4,p=1$RzhSRHFwTHRBUUNoVTZ2Wg$GvWWaH/eTzLLBPPL5VUVlp5H4OwWYJc7L0GYugH01Nw', '2026-04-16 08:20:38.000000', '2026-04-15 10:02:31.000000', 'student'),
(95, 'test@test.com002', '$argon2id$v=19$m=65536,t=4,p=1$SktodlBpcjU5ZnlsZ3dPWg$qAJw983QR75U6edrNm4gbW+S1iM3cFwV+HtlK7M/xE0', NULL, NULL, 'student'),
(96, 'test@test.com003', '$argon2id$v=19$m=65536,t=4,p=1$VjJ0TzNLOFBuQ2RjdXhIbA$kpx0g8m2a9cuY9ZdzOXqei678ahVGHsg+XDsWAvmMbw', NULL, NULL, 'student'),
(97, 'test@test.com004', '$argon2id$v=19$m=65536,t=4,p=1$c0R4Y1BkM05IcmhDTHc2aQ$ELilL4sjQh60fKzX5sxAfUxhEslBZ+L8s4I0aA0ks6o', NULL, NULL, 'student'),
(98, 'test@test.com005', '$argon2id$v=19$m=65536,t=4,p=1$VUFtWmRnUkUyTHVwWC83Mg$gUt8Msgk2yGF+FpW1gxoj32/Gi2LUY6f4dBaWd6vixU', NULL, NULL, 'student'),
(99, 'test@test.com006', '$argon2id$v=19$m=65536,t=4,p=1$N2dYdDdDNEFLazg4bmRILg$cbvTzdJgGgwxH/yj33+T+3AF8eILNK6on+Xr8RkpHeI', NULL, NULL, 'student'),
(100, 'test@test.com007', '$argon2id$v=19$m=65536,t=4,p=1$VWlRVHUxc2ZnNlYvME1tOQ$6AsWeLmFQMOACdH4Bc3zcXMfAXCMZPEKiUfdCiUptqc', NULL, NULL, 'student'),
(101, 'test@test.com008', '$argon2id$v=19$m=65536,t=4,p=1$RzZGdERZaW1uaUhGMlplRw$a5X9Y9tk/JGb1MIVeuNxIAyCw5pR+9VxTxj/yTfO8VI', NULL, NULL, 'student'),
(102, 'test@test.com009', '$argon2id$v=19$m=65536,t=4,p=1$ZUhoYjZmY0oudHFBLzBXaw$IYK/aTY1FRuJtcZRv+WayOqtdZjT2DrXihyK2MDSPHM', NULL, NULL, 'student'),
(103, 'test@test.com010', '$argon2id$v=19$m=65536,t=4,p=1$MVRpWENUR09sQi41UXFWOA$UHoselFxwZqqMMN9R/SMS0fJCKELPxE0MP+fA5JBpYE', NULL, NULL, 'student'),
(104, 'test@test.com011', '$argon2id$v=19$m=65536,t=4,p=1$Nkoyai5ZeTVtU3ZqOFpSMA$+IdKO6DwUqpsQ2Ou3lQ3AWJkq+jVPOGFa44V1TDxoqU', NULL, NULL, 'student'),
(105, 'test@test.com012', '$argon2id$v=19$m=65536,t=4,p=1$WWJqMTkxTnFxTUZOcWVJRw$5mo3P2rhK01CnQX9GAwocr06Re0Wd5F7JEoKji28H4E', NULL, NULL, 'student'),
(106, 'test@test.com013', '$argon2id$v=19$m=65536,t=4,p=1$by5aRGdUQ2p2VGUuOEdaOA$dQ6u2pLBd4InAhWCabqfsQswxuQIKUBVsLD7gX+GDZ0', NULL, NULL, 'student'),
(107, 'test@test.com014', '$argon2id$v=19$m=65536,t=4,p=1$eUFFNWp0VEZrTkkycHZvZA$NUWDK+uWyH4kc42spLj/sQo7Ky73SAmx2HmJw03OO80', NULL, NULL, 'student'),
(108, 'test@test.com015', '$argon2id$v=19$m=65536,t=4,p=1$RDlZNzhmLkZHVEJFTTFJaw$wC8YenSBdhfcZ//TLjVbLe1CnjXERKB+b2U71c5bRRM', NULL, NULL, 'student'),
(109, 'supervisor@university.edu', '$argon2id$v=19$m=65536,t=4,p=1$OEVmWHJFY080RGJ3dzVuVg$p+Pzu/FeVPk3s3cK3hCgXeeKSrY5pi2aaYwtwJvx/00', '2026-04-16 08:21:41.000000', '2026-04-15 10:02:40.000000', 'community_supervisor');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `community_supervisor`
--
ALTER TABLE `community_supervisor`
  ADD PRIMARY KEY (`supervisor_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);

--
-- Indexes for table `course_notifications`
--
ALTER TABLE `course_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_cn_teacher` (`teacher_id`),
  ADD KEY `fk_cn_course` (`course_id`);

--
-- Indexes for table `course_offerings`
--
ALTER TABLE `course_offerings`
  ADD PRIMARY KEY (`offering_id`),
  ADD UNIQUE KEY `uq_offering` (`course_id`,`department`,`session`,`semester_no`,`section`),
  ADD KEY `idx_offering_lookup` (`department`,`session`,`semester_no`,`section`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `recipient_id` (`recipient_id`);

--
-- Indexes for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  ADD PRIMARY KEY (`notification_id`,`student_id`),
  ADD KEY `fk_nr_student` (`student_id`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`post_id`);

--
-- Indexes for table `post_reviews`
--
ALTER TABLE `post_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `supervisor_id` (`supervisor_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`Roll_no`),
  ADD UNIQUE KEY `uq_student_roll` (`Roll_no`),
  ADD KEY `student_idfk_1` (`student_id`);

--
-- Indexes for table `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD UNIQUE KEY `uq_student_offering` (`student_id`,`offering_id`),
  ADD KEY `fk_sce_course` (`course_id`),
  ADD KEY `fk_sce_admin` (`enrolled_by`),
  ADD KEY `fk_sce_offering` (`offering_id`);

--
-- Indexes for table `super_admin`
--
ALTER TABLE `super_admin`
  ADD PRIMARY KEY (`super_admin_id`);

--
-- Indexes for table `teacher`
--
ALTER TABLE `teacher`
  ADD PRIMARY KEY (`teacher_id`);

--
-- Indexes for table `teacher_course_assignments`
--
ALTER TABLE `teacher_course_assignments`
  ADD PRIMARY KEY (`assignment_id`),
  ADD UNIQUE KEY `uq_teacher_offering` (`teacher_id`,`offering_id`),
  ADD UNIQUE KEY `uq_offering_single_teacher` (`offering_id`),
  ADD KEY `fk_tca_course` (`course_id`),
  ADD KEY `fk_tca_admin` (`assigned_by`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `course_notifications`
--
ALTER TABLE `course_notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `course_offerings`
--
ALTER TABLE `course_offerings`
  MODIFY `offering_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `post_reviews`
--
ALTER TABLE `post_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `teacher_course_assignments`
--
ALTER TABLE `teacher_course_assignments`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `community_supervisor`
--
ALTER TABLE `community_supervisor`
  ADD CONSTRAINT `community_supervisor_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `course_notifications`
--
ALTER TABLE `course_notifications`
  ADD CONSTRAINT `fk_cn_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cn_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `course_offerings`
--
ALTER TABLE `course_offerings`
  ADD CONSTRAINT `fk_offering_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `teacher` (`teacher_id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`recipient_id`) REFERENCES `student` (`student_id`);

--
-- Constraints for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  ADD CONSTRAINT `fk_nr_notification` FOREIGN KEY (`notification_id`) REFERENCES `course_notifications` (`notification_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_nr_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `post_reviews`
--
ALTER TABLE `post_reviews`
  ADD CONSTRAINT `post_reviews_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`post_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `post_reviews_ibfk_2` FOREIGN KEY (`supervisor_id`) REFERENCES `community_supervisor` (`supervisor_id`);

--
-- Constraints for table `student`
--
ALTER TABLE `student`
  ADD CONSTRAINT `student_idfk_1` FOREIGN KEY (`student_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  ADD CONSTRAINT `fk_sce_admin` FOREIGN KEY (`enrolled_by`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `fk_sce_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sce_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`offering_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sce_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `super_admin`
--
ALTER TABLE `super_admin`
  ADD CONSTRAINT `super_admin_ibfk_1` FOREIGN KEY (`super_admin_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher`
--
ALTER TABLE `teacher`
  ADD CONSTRAINT `teachers_idfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `teacher_course_assignments`
--
ALTER TABLE `teacher_course_assignments`
  ADD CONSTRAINT `fk_tca_admin` FOREIGN KEY (`assigned_by`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `fk_tca_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tca_offering` FOREIGN KEY (`offering_id`) REFERENCES `course_offerings` (`offering_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tca_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`) ON DELETE CASCADE;

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
