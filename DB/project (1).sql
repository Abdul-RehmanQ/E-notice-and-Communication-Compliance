-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 13, 2026 at 12:35 PM
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
(4, 'Test Supervisor', 'All');

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

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`post_id`, `user_id`, `content`, `image_data`, `image_type`, `image_size`, `created_at`, `expires_at`, `scope`, `status`) VALUES
(8, 4, 'sxdcfvgbhjkl', NULL, NULL, NULL, '2026-02-13 14:51:50', '2026-02-20 10:51:50', 'all', 'approved');

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

--
-- Dumping data for table `post_reviews`
--

INSERT INTO `post_reviews` (`review_id`, `post_id`, `supervisor_id`, `action`, `rejection_reason`, `reviewed_at`) VALUES
(6, 8, 4, 'approved', NULL, '2026-02-13 14:52:01');

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(255) NOT NULL,
  `Roll_no` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `session` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `Roll_no`, `name`, `department`, `session`, `email`) VALUES
(1, 'FA22-BCS-093', 'AR', 'CS', '2022-2026\r\n', ''),
(2, 'FA22-BCS-094', 'Test Student 2', 'Electrical Engineering', '2022-2026', '');

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
(1, 'AR', '');

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
  `role` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `email`, `password`, `login_time`, `logout_time`, `role`) VALUES
(1, 'abdrehmanq76@gmail.com', '$2y$10$GSXTgRBsnWjEbxiXtRjiVeIGg9Ft3wng0FFu5YS4bCQEpxzP7WtJW', '2026-02-13 07:49:59.000000', '2026-02-13 07:49:23.000000', 'student'),
(2, 'student2@test.com', '$2y$10$/O83C1V7CRzzLKR9Y1Fq4uMFAHhKSBk0VCxkHJtIpaXefnzSZJ8ve', '2026-02-13 09:32:28.000000', '2026-02-13 09:29:34.000000', 'student'),
(3, 'teacher@mail.com', 'teacher123', NULL, NULL, 'teacher'),
(4, 'supervisor@university.edu', 'supervisor123', '2026-02-13 09:51:31.000000', '2026-02-13 10:22:21.000000', 'community_supervisor');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `community_supervisor`
--
ALTER TABLE `community_supervisor`
  ADD PRIMARY KEY (`supervisor_id`);

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
  ADD KEY `student_idfk_1` (`student_id`);

--
-- Indexes for table `teacher`
--
ALTER TABLE `teacher`
  ADD KEY `teachers_idfk_1` (`teacher_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `post_reviews`
--
ALTER TABLE `post_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(255) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `community_supervisor`
--
ALTER TABLE `community_supervisor`
  ADD CONSTRAINT `community_supervisor_ibfk_1` FOREIGN KEY (`supervisor_id`) REFERENCES `user` (`user_id`);

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
-- Constraints for table `teacher`
--
ALTER TABLE `teacher`
  ADD CONSTRAINT `teachers_idfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `user` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
