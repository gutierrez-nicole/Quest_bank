-- QuestBank Database Dump & Backup
-- Generated: 2026-08-06 07:39:08
-- Database: bankquest_db
-- Application Version: 2.2-PROD

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- --------------------------------------------------------
-- Table structure for `academic_calendar`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `academic_calendar`;
CREATE TABLE `academic_calendar` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_title` varchar(150) NOT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'school_activity',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `description` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table structure for `activity_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1349 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `activity_logs`

INSERT INTO `activity_logs` (`id`, `user_id`, `action_description`, `created_at`) VALUES 
('712', '10', 'Uploaded new lesson material \'[RUN_1785861751712] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:42:31'),
('713', '10', 'Successfully extracted lesson content for \'[RUN_1785861751712] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.18ms).', '2026-08-05 00:42:31'),
('714', '10', 'Uploaded new lesson material \'[RUN_1785861763505] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:42:43'),
('715', '10', 'Successfully extracted lesson content for \'[RUN_1785861763505] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.72ms).', '2026-08-05 00:42:43'),
('716', '10', 'Uploaded new lesson material \'[RUN_1785861775277] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:42:55'),
('717', '10', 'Successfully extracted lesson content for \'[RUN_1785861775277] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.96ms).', '2026-08-05 00:42:55'),
('718', '10', 'Uploaded new lesson material \'[RUN_1785861786965] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:43:07'),
('719', '10', 'Successfully extracted lesson content for \'[RUN_1785861786965] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 2.78ms).', '2026-08-05 00:43:07'),
('720', '10', 'Uploaded new lesson material \'[RUN_1785861798681] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:43:18'),
('721', '10', 'Successfully extracted lesson content for \'[RUN_1785861798681] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 2.64ms).', '2026-08-05 00:43:18'),
('722', '10', 'Uploaded new lesson material \'[RUN_1785862441865] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:54:02'),
('723', '10', 'Successfully extracted lesson content for \'[RUN_1785862441865] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.98ms).', '2026-08-05 00:54:02'),
('724', '10', 'Uploaded new lesson material \'[RUN_1785862441865] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:54:02'),
('725', '10', 'Successfully extracted lesson content for \'[RUN_1785862441865] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 0.93ms).', '2026-08-05 00:54:02'),
('726', '10', 'Uploaded new lesson material \'[RUN_1785862441865] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:54:02'),
('727', '10', 'Successfully extracted lesson content for \'[RUN_1785862441865] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.04ms).', '2026-08-05 00:54:02'),
('728', '10', 'Uploaded new lesson material \'[RUN_1785862441865] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 00:54:02'),
('729', '10', 'Successfully extracted lesson content for \'[RUN_1785862441865] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.51ms).', '2026-08-05 00:54:02'),
('730', '10', 'Uploaded new lesson material \'[RUN_1785862850199] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:00:50'),
('731', '10', 'Successfully extracted lesson content for \'[RUN_1785862850199] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.66ms).', '2026-08-05 01:00:50'),
('732', '10', 'Uploaded new lesson material \'[RUN_1785862850199] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:00:50'),
('733', '10', 'Successfully extracted lesson content for \'[RUN_1785862850199] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.31ms).', '2026-08-05 01:00:50'),
('734', '10', 'Uploaded new lesson material \'[RUN_1785862850199] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:00:50'),
('735', '10', 'Successfully extracted lesson content for \'[RUN_1785862850199] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 0.91ms).', '2026-08-05 01:00:50'),
('736', '10', 'Uploaded new lesson material \'[RUN_1785862850199] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:00:51'),
('737', '10', 'Successfully extracted lesson content for \'[RUN_1785862850199] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.43ms).', '2026-08-05 01:00:51'),
('738', '10', 'Uploaded new lesson material \'[RUN_1785863095844] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:04:55'),
('739', '10', 'Successfully extracted lesson content for \'[RUN_1785863095844] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.51ms).', '2026-08-05 01:04:55'),
('740', '10', 'Uploaded new lesson material \'[RUN_1785863095844] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:04:56'),
('741', '10', 'Successfully extracted lesson content for \'[RUN_1785863095844] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.46ms).', '2026-08-05 01:04:56'),
('742', '10', 'Uploaded new lesson material \'[RUN_1785863095844] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:04:56'),
('743', '10', 'Successfully extracted lesson content for \'[RUN_1785863095844] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.01ms).', '2026-08-05 01:04:56'),
('744', '10', 'Uploaded new lesson material \'[RUN_1785863095844] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:04:56'),
('745', '10', 'Successfully extracted lesson content for \'[RUN_1785863095844] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.49ms).', '2026-08-05 01:04:56'),
('746', '10', 'Saved AI-generated exam \'[MOCK_MISSING_SOURCE Test]\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 01:06:43'),
('747', '10', 'Saved AI-generated exam \'[MOCK_MISSING_SOURCE Test]\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 01:06:54'),
('748', '10', 'Uploaded new lesson material \'[RUN_1785863244276] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:07:24'),
('749', '10', 'Successfully extracted lesson content for \'[RUN_1785863244276] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.41ms).', '2026-08-05 01:07:24'),
('750', '10', 'Uploaded new lesson material \'[RUN_1785863244276] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:07:25'),
('751', '10', 'Successfully extracted lesson content for \'[RUN_1785863244276] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 4.37ms).', '2026-08-05 01:07:25'),
('752', '10', 'Uploaded new lesson material \'[RUN_1785863244276] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:07:25'),
('753', '10', 'Successfully extracted lesson content for \'[RUN_1785863244276] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.79ms).', '2026-08-05 01:07:25'),
('754', '10', 'Uploaded new lesson material \'[RUN_1785863244276] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:07:25'),
('755', '10', 'Successfully extracted lesson content for \'[RUN_1785863244276] Steel Design Finals Module E2E\' (38 words, 1 pages, 0.96ms).', '2026-08-05 01:07:25'),
('756', '10', 'Saved AI-generated exam \'Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 01:07:57'),
('757', '10', 'Uploaded new lesson material \'[RUN_1785863394663] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:09:54'),
('758', '10', 'Successfully extracted lesson content for \'[RUN_1785863394663] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.09ms).', '2026-08-05 01:09:54'),
('759', '10', 'Uploaded new lesson material \'[RUN_1785863394663] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:09:55'),
('760', '10', 'Successfully extracted lesson content for \'[RUN_1785863394663] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 0.89ms).', '2026-08-05 01:09:55'),
('761', '10', 'Uploaded new lesson material \'[RUN_1785863394663] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:09:55'),
('762', '10', 'Successfully extracted lesson content for \'[RUN_1785863394663] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 0.96ms).', '2026-08-05 01:09:55'),
('763', '10', 'Uploaded new lesson material \'[RUN_1785863394663] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:09:55'),
('764', '10', 'Successfully extracted lesson content for \'[RUN_1785863394663] Steel Design Finals Module E2E\' (38 words, 1 pages, 0.86ms).', '2026-08-05 01:09:55'),
('765', '10', 'Uploaded new lesson material \'[RUN_1785863444795] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:10:44'),
('766', '10', 'Successfully extracted lesson content for \'[RUN_1785863444795] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.58ms).', '2026-08-05 01:10:44'),
('767', '10', 'Uploaded new lesson material \'[RUN_1785863444795] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:10:45'),
('768', '10', 'Successfully extracted lesson content for \'[RUN_1785863444795] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.4ms).', '2026-08-05 01:10:45'),
('769', '10', 'Uploaded new lesson material \'[RUN_1785863444795] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:10:45'),
('770', '10', 'Successfully extracted lesson content for \'[RUN_1785863444795] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.05ms).', '2026-08-05 01:10:45'),
('771', '10', 'Uploaded new lesson material \'[RUN_1785863444795] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:10:45'),
('772', '10', 'Successfully extracted lesson content for \'[RUN_1785863444795] Steel Design Finals Module E2E\' (38 words, 1 pages, 0.92ms).', '2026-08-05 01:10:45'),
('773', '10', 'Uploaded new lesson material \'[RUN_1785863466332] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:11:06'),
('774', '10', 'Successfully extracted lesson content for \'[RUN_1785863466332] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.47ms).', '2026-08-05 01:11:06'),
('775', '10', 'Uploaded new lesson material \'[RUN_1785863466332] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:11:06'),
('776', '10', 'Successfully extracted lesson content for \'[RUN_1785863466332] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.7ms).', '2026-08-05 01:11:06'),
('777', '10', 'Uploaded new lesson material \'[RUN_1785863466332] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:11:07'),
('778', '10', 'Successfully extracted lesson content for \'[RUN_1785863466332] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.56ms).', '2026-08-05 01:11:07'),
('779', '10', 'Uploaded new lesson material \'[RUN_1785863466332] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:11:07'),
('780', '10', 'Successfully extracted lesson content for \'[RUN_1785863466332] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.01ms).', '2026-08-05 01:11:07'),
('781', '10', 'Uploaded new lesson material \'[RUN_1785864397165] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:26:37'),
('782', '10', 'Successfully extracted lesson content for \'[RUN_1785864397165] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.02ms).', '2026-08-05 01:26:37'),
('783', '10', 'Uploaded new lesson material \'[RUN_1785864398292] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:26:38'),
('784', '10', 'Successfully extracted lesson content for \'[RUN_1785864398292] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.16ms).', '2026-08-05 01:26:38'),
('785', '10', 'Uploaded new lesson material \'[RUN_1785864408992] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:26:49'),
('786', '10', 'Successfully extracted lesson content for \'[RUN_1785864408992] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.01ms).', '2026-08-05 01:26:49'),
('787', '10', 'Uploaded new lesson material \'[RUN_1785864420679] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:00'),
('788', '10', 'Successfully extracted lesson content for \'[RUN_1785864420679] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.93ms).', '2026-08-05 01:27:00'),
('789', '10', 'Uploaded new lesson material \'[RUN_1785864432458] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:12'),
('790', '10', 'Successfully extracted lesson content for \'[RUN_1785864432458] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.05ms).', '2026-08-05 01:27:12'),
('791', '10', 'Uploaded new lesson material \'[RUN_1785864432458] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:12'),
('792', '10', 'Successfully extracted lesson content for \'[RUN_1785864432458] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.32ms).', '2026-08-05 01:27:12'),
('793', '10', 'Uploaded new lesson material \'[RUN_1785864432458] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:13'),
('794', '10', 'Successfully extracted lesson content for \'[RUN_1785864432458] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.07ms).', '2026-08-05 01:27:13'),
('795', '10', 'Uploaded new lesson material \'[RUN_1785864432458] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:13'),
('796', '10', 'Successfully extracted lesson content for \'[RUN_1785864432458] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.09ms).', '2026-08-05 01:27:13'),
('797', '10', 'Uploaded new lesson material \'[RUN_1785864440934] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:21'),
('798', '10', 'Successfully extracted lesson content for \'[RUN_1785864440934] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.93ms).', '2026-08-05 01:27:21'),
('799', '10', 'Uploaded new lesson material \'[RUN_1785864440934] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:21'),
('800', '10', 'Successfully extracted lesson content for \'[RUN_1785864440934] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.13ms).', '2026-08-05 01:27:21'),
('801', '10', 'Uploaded new lesson material \'[RUN_1785864440934] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:21'),
('802', '10', 'Successfully extracted lesson content for \'[RUN_1785864440934] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1ms).', '2026-08-05 01:27:21'),
('803', '10', 'Uploaded new lesson material \'[RUN_1785864440934] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:27:21'),
('804', '10', 'Successfully extracted lesson content for \'[RUN_1785864440934] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.02ms).', '2026-08-05 01:27:21'),
('805', '10', 'Uploaded new lesson material \'[RUN_1785866347000] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:59:07'),
('806', '10', 'Successfully extracted lesson content for \'[RUN_1785866347000] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.11ms).', '2026-08-05 01:59:07'),
('807', '10', 'Uploaded new lesson material \'[RUN_1785866347000] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:59:07'),
('808', '10', 'Successfully extracted lesson content for \'[RUN_1785866347000] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 0.97ms).', '2026-08-05 01:59:07'),
('809', '10', 'Uploaded new lesson material \'[RUN_1785866347000] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:59:07'),
('810', '10', 'Successfully extracted lesson content for \'[RUN_1785866347000] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.31ms).', '2026-08-05 01:59:07'),
('811', '10', 'Uploaded new lesson material \'[RUN_1785866347000] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 01:59:08');
INSERT INTO `activity_logs` (`id`, `user_id`, `action_description`, `created_at`) VALUES 
('812', '10', 'Successfully extracted lesson content for \'[RUN_1785866347000] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.48ms).', '2026-08-05 01:59:08'),
('813', '10', 'Saved AI-generated exam \'Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 02:14:43'),
('814', '10', 'Uploaded new lesson material \'[RUN_1785867291182] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 02:14:51'),
('815', '10', 'Successfully extracted lesson content for \'[RUN_1785867291182] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.1ms).', '2026-08-05 02:14:51'),
('816', '10', 'Uploaded new lesson material \'[RUN_1785867291182] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 02:14:51'),
('817', '10', 'Successfully extracted lesson content for \'[RUN_1785867291182] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.27ms).', '2026-08-05 02:14:51'),
('818', '10', 'Uploaded new lesson material \'[RUN_1785867291182] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 02:14:52'),
('819', '10', 'Successfully extracted lesson content for \'[RUN_1785867291182] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.19ms).', '2026-08-05 02:14:52'),
('820', '10', 'Uploaded new lesson material \'[RUN_1785867291182] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 02:14:52'),
('821', '10', 'Successfully extracted lesson content for \'[RUN_1785867291182] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.02ms).', '2026-08-05 02:14:52'),
('822', '10', 'Uploaded new lesson material \'[RUN_1785868226308] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 02:30:26'),
('823', '10', 'Successfully extracted lesson content for \'[RUN_1785868226308] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.87ms).', '2026-08-05 02:30:26'),
('824', '10', 'Uploaded new lesson material \'[RUN_1785868226308] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 02:30:26'),
('825', '10', 'Successfully extracted lesson content for \'[RUN_1785868226308] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 0.93ms).', '2026-08-05 02:30:26'),
('826', '10', 'Uploaded new lesson material \'[RUN_1785868226308] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 02:30:27'),
('827', '10', 'Successfully extracted lesson content for \'[RUN_1785868226308] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 7.41ms).', '2026-08-05 02:30:27'),
('828', '10', 'Uploaded new lesson material \'[RUN_1785868226308] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 02:30:27'),
('829', '10', 'Successfully extracted lesson content for \'[RUN_1785868226308] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.28ms).', '2026-08-05 02:30:27'),
('830', '10', 'Saved AI-generated exam \'Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 03:01:49'),
('831', '10', 'Uploaded new lesson material \'[RUN_1785870139938] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:02:20'),
('832', '10', 'Successfully extracted lesson content for \'[RUN_1785870139938] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.57ms).', '2026-08-05 03:02:20'),
('833', '10', 'Uploaded new lesson material \'[RUN_1785870139938] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:02:20'),
('834', '10', 'Successfully extracted lesson content for \'[RUN_1785870139938] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.62ms).', '2026-08-05 03:02:20'),
('835', '10', 'Uploaded new lesson material \'[RUN_1785870139938] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:02:20'),
('836', '10', 'Successfully extracted lesson content for \'[RUN_1785870139938] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.47ms).', '2026-08-05 03:02:20'),
('837', '10', 'Uploaded new lesson material \'[RUN_1785870139938] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:02:21'),
('838', '10', 'Successfully extracted lesson content for \'[RUN_1785870139938] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.81ms).', '2026-08-05 03:02:21'),
('839', '10', 'Saved AI-generated exam \'Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 03:11:33'),
('840', '10', 'Uploaded new lesson material \'[RUN_1785872596166] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:43:16'),
('841', '10', 'Successfully extracted lesson content for \'[RUN_1785872596166] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.13ms).', '2026-08-05 03:43:16'),
('842', '10', 'Uploaded new lesson material \'[RUN_1785872596166] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:43:16'),
('843', '10', 'Successfully extracted lesson content for \'[RUN_1785872596166] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.52ms).', '2026-08-05 03:43:16'),
('844', '10', 'Uploaded new lesson material \'[RUN_1785872596166] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:43:17'),
('845', '10', 'Successfully extracted lesson content for \'[RUN_1785872596166] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.82ms).', '2026-08-05 03:43:17'),
('846', '10', 'Uploaded new lesson material \'[RUN_1785872596166] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:43:17'),
('847', '10', 'Successfully extracted lesson content for \'[RUN_1785872596166] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.29ms).', '2026-08-05 03:43:17'),
('848', '10', 'Saved AI-generated exam \'Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 03:59:34'),
('849', '10', 'Saved AI-generated exam \'Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 03:59:38'),
('850', '10', 'Uploaded new lesson material \'[RUN_1785873593019] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:59:53'),
('851', '10', 'Successfully extracted lesson content for \'[RUN_1785873593019] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.8ms).', '2026-08-05 03:59:53'),
('852', '10', 'Uploaded new lesson material \'[RUN_1785873593019] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:59:53'),
('853', '10', 'Successfully extracted lesson content for \'[RUN_1785873593019] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.69ms).', '2026-08-05 03:59:53'),
('854', '10', 'Uploaded new lesson material \'[RUN_1785873593019] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:59:53'),
('855', '10', 'Successfully extracted lesson content for \'[RUN_1785873593019] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.56ms).', '2026-08-05 03:59:53'),
('856', '10', 'Uploaded new lesson material \'[RUN_1785873593019] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 03:59:54'),
('857', '10', 'Successfully extracted lesson content for \'[RUN_1785873593019] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.43ms).', '2026-08-05 03:59:54'),
('858', '10', 'Saved AI-generated exam \'[RUN_1785873627257] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 04:00:27'),
('859', '10', 'Saved AI-generated exam \'Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 04:00:49'),
('860', '10', 'Uploaded new lesson material \'[RUN_1785873677914] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 04:01:18'),
('861', '10', 'Successfully extracted lesson content for \'[RUN_1785873677914] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.47ms).', '2026-08-05 04:01:18'),
('862', '10', 'Uploaded new lesson material \'[RUN_1785873677914] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 04:01:18'),
('863', '10', 'Successfully extracted lesson content for \'[RUN_1785873677914] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.57ms).', '2026-08-05 04:01:18'),
('864', '10', 'Uploaded new lesson material \'[RUN_1785873677914] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 04:01:18'),
('865', '10', 'Successfully extracted lesson content for \'[RUN_1785873677914] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.59ms).', '2026-08-05 04:01:18'),
('866', '10', 'Uploaded new lesson material \'[RUN_1785873677914] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 04:01:19'),
('867', '10', 'Successfully extracted lesson content for \'[RUN_1785873677914] Steel Design Finals Module E2E\' (38 words, 1 pages, 0.89ms).', '2026-08-05 04:01:19'),
('868', '10', 'Uploaded new lesson material \'[RUN_1785876530661] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 04:48:50'),
('869', '10', 'Successfully extracted lesson content for \'[RUN_1785876530661] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.31ms).', '2026-08-05 04:48:50'),
('870', '10', 'Uploaded new lesson material \'[RUN_1785876530661] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 04:48:51'),
('871', '10', 'Successfully extracted lesson content for \'[RUN_1785876530661] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 0.96ms).', '2026-08-05 04:48:51'),
('872', '10', 'Uploaded new lesson material \'[RUN_1785876530661] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 04:48:51'),
('873', '10', 'Successfully extracted lesson content for \'[RUN_1785876530661] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.3ms).', '2026-08-05 04:48:51'),
('874', '10', 'Uploaded new lesson material \'[RUN_1785876530661] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 04:48:51'),
('875', '10', 'Successfully extracted lesson content for \'[RUN_1785876530661] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.57ms).', '2026-08-05 04:48:51'),
('876', '10', 'Uploaded new lesson material \'[RUN_1785878410920] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:20:11'),
('877', '10', 'Successfully extracted lesson content for \'[RUN_1785878410920] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.99ms).', '2026-08-05 05:20:11'),
('878', '10', 'Uploaded new lesson material \'[RUN_1785878410920] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:20:11'),
('879', '10', 'Successfully extracted lesson content for \'[RUN_1785878410920] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1ms).', '2026-08-05 05:20:11'),
('880', '10', 'Uploaded new lesson material \'[RUN_1785878410920] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:20:11'),
('881', '10', 'Successfully extracted lesson content for \'[RUN_1785878410920] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 2.39ms).', '2026-08-05 05:20:11'),
('882', '10', 'Uploaded new lesson material \'[RUN_1785878410920] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:20:12'),
('883', '10', 'Successfully extracted lesson content for \'[RUN_1785878410920] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.57ms).', '2026-08-05 05:20:12'),
('884', '10', 'Saved AI-generated exam \'Cross Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 05:35:58'),
('885', '10', 'Saved AI-generated exam \'&lt;br /&gt;\r\n&lt;b&gt;Warning&lt;/b&gt;:  Undefined array key\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 05:35:58'),
('886', '10', 'Uploaded new lesson material \'[RUN_1785879370836] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:36:11'),
('887', '10', 'Successfully extracted lesson content for \'[RUN_1785879370836] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.58ms).', '2026-08-05 05:36:11'),
('888', '10', 'Uploaded new lesson material \'[RUN_1785879370836] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:36:11'),
('889', '10', 'Successfully extracted lesson content for \'[RUN_1785879370836] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.65ms).', '2026-08-05 05:36:11'),
('890', '10', 'Uploaded new lesson material \'[RUN_1785879370836] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:36:11'),
('891', '10', 'Successfully extracted lesson content for \'[RUN_1785879370836] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.07ms).', '2026-08-05 05:36:11'),
('892', '10', 'Uploaded new lesson material \'[RUN_1785879370836] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:36:12'),
('893', '10', 'Successfully extracted lesson content for \'[RUN_1785879370836] Steel Design Finals Module E2E\' (38 words, 1 pages, 10.49ms).', '2026-08-05 05:36:12'),
('894', '10', 'Saved AI-generated exam \'Cross Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 05:51:31'),
('895', '10', 'Uploaded new lesson material \'[RUN_1785880315677] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:51:55'),
('896', '10', 'Successfully extracted lesson content for \'[RUN_1785880315677] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.87ms).', '2026-08-05 05:51:55'),
('897', '10', 'Uploaded new lesson material \'[RUN_1785880315677] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:51:56'),
('898', '10', 'Successfully extracted lesson content for \'[RUN_1785880315677] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.14ms).', '2026-08-05 05:51:56'),
('899', '10', 'Uploaded new lesson material \'[RUN_1785880315677] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:51:56'),
('900', '10', 'Successfully extracted lesson content for \'[RUN_1785880315677] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 2.02ms).', '2026-08-05 05:51:56'),
('901', '10', 'Uploaded new lesson material \'[RUN_1785880315677] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 05:51:57'),
('902', '10', 'Successfully extracted lesson content for \'[RUN_1785880315677] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.79ms).', '2026-08-05 05:51:57'),
('903', '10', 'Uploaded new lesson material \'[RUN_1785882167081] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:22:47'),
('904', '10', 'Successfully extracted lesson content for \'[RUN_1785882167081] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.5ms).', '2026-08-05 06:22:47'),
('905', '10', 'Uploaded new lesson material \'[RUN_1785882167081] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:22:47'),
('906', '10', 'Successfully extracted lesson content for \'[RUN_1785882167081] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.73ms).', '2026-08-05 06:22:47'),
('907', '10', 'Uploaded new lesson material \'[RUN_1785882167081] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:22:48'),
('908', '10', 'Successfully extracted lesson content for \'[RUN_1785882167081] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.3ms).', '2026-08-05 06:22:48'),
('909', '10', 'Uploaded new lesson material \'[RUN_1785882167081] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:22:48'),
('910', '10', 'Successfully extracted lesson content for \'[RUN_1785882167081] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.55ms).', '2026-08-05 06:22:48'),
('911', '10', 'Uploaded new lesson material \'[RUN_1785882179427] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:22:59');
INSERT INTO `activity_logs` (`id`, `user_id`, `action_description`, `created_at`) VALUES 
('912', '10', 'Successfully extracted lesson content for \'[RUN_1785882179427] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 2.36ms).', '2026-08-05 06:22:59'),
('913', '10', 'Uploaded new lesson material \'[RUN_1785882179427] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:23:00'),
('914', '10', 'Successfully extracted lesson content for \'[RUN_1785882179427] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 2.92ms).', '2026-08-05 06:23:00'),
('915', '10', 'Uploaded new lesson material \'[RUN_1785882179427] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:23:00'),
('916', '10', 'Successfully extracted lesson content for \'[RUN_1785882179427] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.61ms).', '2026-08-05 06:23:00'),
('917', '10', 'Uploaded new lesson material \'[RUN_1785882179427] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:23:00'),
('918', '10', 'Successfully extracted lesson content for \'[RUN_1785882179427] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.56ms).', '2026-08-05 06:23:00'),
('919', '10', 'Uploaded new lesson material \'[RUN_1785884190768] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:56:31'),
('920', '10', 'Successfully extracted lesson content for \'[RUN_1785884190768] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.04ms).', '2026-08-05 06:56:31'),
('921', '10', 'Uploaded new lesson material \'[RUN_1785884190768] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:56:31'),
('922', '10', 'Successfully extracted lesson content for \'[RUN_1785884190768] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.02ms).', '2026-08-05 06:56:31'),
('923', '10', 'Uploaded new lesson material \'[RUN_1785884190768] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:56:31'),
('924', '10', 'Successfully extracted lesson content for \'[RUN_1785884190768] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.33ms).', '2026-08-05 06:56:31'),
('925', '10', 'Uploaded new lesson material \'[RUN_1785884190768] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 06:56:32'),
('926', '10', 'Successfully extracted lesson content for \'[RUN_1785884190768] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.51ms).', '2026-08-05 06:56:32'),
('927', '10', 'Uploaded new lesson material \'[RUN_1785886457315] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 07:34:17'),
('928', '10', 'Successfully extracted lesson content for \'[RUN_1785886457315] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.01ms).', '2026-08-05 07:34:17'),
('929', '10', 'Uploaded new lesson material \'[RUN_1785886457315] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 07:34:18'),
('930', '10', 'Successfully extracted lesson content for \'[RUN_1785886457315] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 0.87ms).', '2026-08-05 07:34:18'),
('931', '10', 'Uploaded new lesson material \'[RUN_1785886457315] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 07:34:18'),
('932', '10', 'Successfully extracted lesson content for \'[RUN_1785886457315] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 0.88ms).', '2026-08-05 07:34:18'),
('933', '10', 'Uploaded new lesson material \'[RUN_1785886457315] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 07:34:18'),
('934', '10', 'Successfully extracted lesson content for \'[RUN_1785886457315] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.65ms).', '2026-08-05 07:34:18'),
('935', '10', 'Uploaded new lesson material \'[RUN_1785887691206] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 07:54:51'),
('936', '10', 'Successfully extracted lesson content for \'[RUN_1785887691206] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.82ms).', '2026-08-05 07:54:51'),
('937', '10', 'Uploaded new lesson material \'[RUN_1785887691206] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 07:54:51'),
('938', '10', 'Successfully extracted lesson content for \'[RUN_1785887691206] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 0.86ms).', '2026-08-05 07:54:51'),
('939', '10', 'Uploaded new lesson material \'[RUN_1785887691206] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 07:54:52'),
('940', '10', 'Successfully extracted lesson content for \'[RUN_1785887691206] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.41ms).', '2026-08-05 07:54:52'),
('941', '10', 'Uploaded new lesson material \'[RUN_1785887691206] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 07:54:52'),
('942', '10', 'Successfully extracted lesson content for \'[RUN_1785887691206] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.39ms).', '2026-08-05 07:54:52'),
('943', '10', 'Uploaded new lesson material \'[RUN_1785891501841] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 08:58:22'),
('944', '10', 'Successfully extracted lesson content for \'[RUN_1785891501841] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.98ms).', '2026-08-05 08:58:22'),
('945', '10', 'Uploaded new lesson material \'[RUN_1785891501841] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 08:58:22'),
('946', '10', 'Successfully extracted lesson content for \'[RUN_1785891501841] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.02ms).', '2026-08-05 08:58:22'),
('947', '10', 'Uploaded new lesson material \'[RUN_1785891501841] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 08:58:22'),
('948', '10', 'Successfully extracted lesson content for \'[RUN_1785891501841] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 0.96ms).', '2026-08-05 08:58:22'),
('949', '10', 'Uploaded new lesson material \'[RUN_1785891501841] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 08:58:23'),
('950', '10', 'Successfully extracted lesson content for \'[RUN_1785891501841] Steel Design Finals Module E2E\' (38 words, 1 pages, 0.9ms).', '2026-08-05 08:58:23'),
('951', '10', 'Saved AI-generated exam \'[RUN_1785894676277] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 09:51:16'),
('952', '10', 'Uploaded new lesson material \'[RUN_1785895471206] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 10:04:31'),
('953', '10', 'Successfully extracted lesson content for \'[RUN_1785895471206] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 2.81ms).', '2026-08-05 10:04:31'),
('954', '10', 'Uploaded new lesson material \'[RUN_1785895471206] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 10:04:32'),
('955', '10', 'Successfully extracted lesson content for \'[RUN_1785895471206] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.27ms).', '2026-08-05 10:04:32'),
('956', '10', 'Uploaded new lesson material \'[RUN_1785895471206] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 10:04:32'),
('957', '10', 'Successfully extracted lesson content for \'[RUN_1785895471206] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.5ms).', '2026-08-05 10:04:32'),
('958', '10', 'Uploaded new lesson material \'[RUN_1785895471206] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 10:04:33'),
('959', '10', 'Successfully extracted lesson content for \'[RUN_1785895471206] Steel Design Finals Module E2E\' (38 words, 1 pages, 5.28ms).', '2026-08-05 10:04:33'),
('960', '10', 'Saved AI-generated exam \'[RUN_1785895471206] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 10:04:35'),
('961', '10', 'Saved AI-generated exam \'[RUN_1785895471206] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 10:20:05'),
('962', '10', 'Saved AI-generated exam \'[RUN_1785902016472] Resolved Missing Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:53:36'),
('963', '10', 'Uploaded new lesson material \'[RUN_1785902048150] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:54:08'),
('964', '10', 'Successfully extracted lesson content for \'[RUN_1785902048150] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.04ms).', '2026-08-05 11:54:08'),
('965', '10', 'Uploaded new lesson material \'[RUN_1785902048150] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:54:09'),
('966', '10', 'Successfully extracted lesson content for \'[RUN_1785902048150] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 2.03ms).', '2026-08-05 11:54:09'),
('967', '10', 'Uploaded new lesson material \'[RUN_1785902048150] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:54:09'),
('968', '10', 'Successfully extracted lesson content for \'[RUN_1785902048150] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.99ms).', '2026-08-05 11:54:09'),
('969', '10', 'Uploaded new lesson material \'[RUN_1785902048150] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:54:10'),
('970', '10', 'Successfully extracted lesson content for \'[RUN_1785902048150] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.21ms).', '2026-08-05 11:54:10'),
('971', '10', 'Saved AI-generated exam \'[RUN_1785902048150] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:54:11'),
('972', '10', 'Saved AI-generated exam \'&lt;br /&gt;\r\n&lt;b&gt;Warning&lt;/b&gt;:  Undefined array key\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:54:14'),
('973', '10', 'Saved AI-generated exam \'[RUN_1785902048150] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:55:31'),
('974', '10', 'Saved AI-generated exam \'&lt;br /&gt;\r\n&lt;b&gt;Warning&lt;/b&gt;:  Undefined array key\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:56:01'),
('975', '10', 'Uploaded new lesson material \'[RUN_1785902177648] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:56:17'),
('976', '10', 'Successfully extracted lesson content for \'[RUN_1785902177648] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.31ms).', '2026-08-05 11:56:17'),
('977', '10', 'Uploaded new lesson material \'[RUN_1785902177648] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:56:18'),
('978', '10', 'Successfully extracted lesson content for \'[RUN_1785902177648] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 0.74ms).', '2026-08-05 11:56:18'),
('979', '10', 'Uploaded new lesson material \'[RUN_1785902177648] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:56:18'),
('980', '10', 'Successfully extracted lesson content for \'[RUN_1785902177648] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 2ms).', '2026-08-05 11:56:18'),
('981', '10', 'Uploaded new lesson material \'[RUN_1785902177648] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:56:19'),
('982', '10', 'Successfully extracted lesson content for \'[RUN_1785902177648] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.51ms).', '2026-08-05 11:56:19'),
('983', '10', 'Saved AI-generated exam \'[RUN_1785902177648] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:56:21'),
('984', '10', 'Saved AI-generated exam \'&lt;br /&gt;\r\n&lt;b&gt;Warning&lt;/b&gt;:  Undefined array key\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:56:23'),
('985', '10', 'Saved AI-generated exam \'&lt;br /&gt;\r\n&lt;b&gt;Warning&lt;/b&gt;:  Undefined array key\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:56:26'),
('986', '10', 'Saved AI-generated exam \'[RUN_1785902177648] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:56:27'),
('987', '10', 'Uploaded new lesson material \'[RUN_1785902213872] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:56:54'),
('988', '10', 'Successfully extracted lesson content for \'[RUN_1785902213872] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.98ms).', '2026-08-05 11:56:54'),
('989', '10', 'Uploaded new lesson material \'[RUN_1785902213872] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:56:54'),
('990', '10', 'Successfully extracted lesson content for \'[RUN_1785902213872] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.05ms).', '2026-08-05 11:56:54'),
('991', '10', 'Uploaded new lesson material \'[RUN_1785902213872] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:56:55'),
('992', '10', 'Successfully extracted lesson content for \'[RUN_1785902213872] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.19ms).', '2026-08-05 11:56:55'),
('993', '10', 'Uploaded new lesson material \'[RUN_1785902213872] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 11:56:55'),
('994', '10', 'Successfully extracted lesson content for \'[RUN_1785902213872] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.34ms).', '2026-08-05 11:56:55'),
('995', '10', 'Saved AI-generated exam \'[RUN_1785902213872] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:56:57'),
('996', '10', 'Saved AI-generated exam \'&lt;br /&gt;\r\n&lt;b&gt;Warning&lt;/b&gt;:  Undefined array key\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:56:59'),
('997', '10', 'Saved AI-generated exam \'&lt;br /&gt;\r\n&lt;b&gt;Warning&lt;/b&gt;:  Undefined array key\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:57:01'),
('998', '10', 'Saved AI-generated exam \'[RUN_1785902213872] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 11:57:03'),
('999', '10', 'Uploaded new lesson material \'[RUN_1785902559778] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:02:40'),
('1000', '10', 'Successfully extracted lesson content for \'[RUN_1785902559778] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.23ms).', '2026-08-05 12:02:40'),
('1001', '10', 'Uploaded new lesson material \'[RUN_1785902559778] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:02:40'),
('1002', '10', 'Successfully extracted lesson content for \'[RUN_1785902559778] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.4ms).', '2026-08-05 12:02:40'),
('1003', '10', 'Uploaded new lesson material \'[RUN_1785902559778] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:02:41'),
('1004', '10', 'Successfully extracted lesson content for \'[RUN_1785902559778] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.96ms).', '2026-08-05 12:02:41'),
('1005', '10', 'Uploaded new lesson material \'[RUN_1785902559778] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:02:41'),
('1006', '10', 'Successfully extracted lesson content for \'[RUN_1785902559778] Steel Design Finals Module E2E\' (38 words, 1 pages, 2.21ms).', '2026-08-05 12:02:41'),
('1007', '10', 'Uploaded new lesson material \'[RUN_1785902695445] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:04:55'),
('1008', '10', 'Successfully extracted lesson content for \'[RUN_1785902695445] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.9ms).', '2026-08-05 12:04:55'),
('1009', '10', 'Uploaded new lesson material \'[RUN_1785902695445] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:04:56'),
('1010', '10', 'Successfully extracted lesson content for \'[RUN_1785902695445] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.3ms).', '2026-08-05 12:04:56'),
('1011', '10', 'Uploaded new lesson material \'[RUN_1785902695445] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:04:56');
INSERT INTO `activity_logs` (`id`, `user_id`, `action_description`, `created_at`) VALUES 
('1012', '10', 'Successfully extracted lesson content for \'[RUN_1785902695445] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 2.38ms).', '2026-08-05 12:04:56'),
('1013', '10', 'Uploaded new lesson material \'[RUN_1785902695445] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:04:57'),
('1014', '10', 'Successfully extracted lesson content for \'[RUN_1785902695445] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.18ms).', '2026-08-05 12:04:57'),
('1015', '10', 'Uploaded new lesson material \'[RUN_1785902830719] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:07:11'),
('1016', '10', 'Successfully extracted lesson content for \'[RUN_1785902830719] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 0.95ms).', '2026-08-05 12:07:11'),
('1017', '10', 'Uploaded new lesson material \'[RUN_1785902830719] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:07:11'),
('1018', '10', 'Successfully extracted lesson content for \'[RUN_1785902830719] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.59ms).', '2026-08-05 12:07:11'),
('1019', '10', 'Uploaded new lesson material \'[RUN_1785902830719] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:07:12'),
('1020', '10', 'Successfully extracted lesson content for \'[RUN_1785902830719] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.91ms).', '2026-08-05 12:07:12'),
('1021', '10', 'Uploaded new lesson material \'[RUN_1785902830719] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:07:12'),
('1022', '10', 'Successfully extracted lesson content for \'[RUN_1785902830719] Steel Design Finals Module E2E\' (38 words, 1 pages, 2.49ms).', '2026-08-05 12:07:12'),
('1023', '10', 'Uploaded new lesson material \'[RUN_1785902926489] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:08:46'),
('1024', '10', 'Successfully extracted lesson content for \'[RUN_1785902926489] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.55ms).', '2026-08-05 12:08:46'),
('1025', '10', 'Uploaded new lesson material \'[RUN_1785902926489] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:08:47'),
('1026', '10', 'Successfully extracted lesson content for \'[RUN_1785902926489] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.78ms).', '2026-08-05 12:08:47'),
('1027', '10', 'Uploaded new lesson material \'[RUN_1785902926489] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:08:47'),
('1028', '10', 'Successfully extracted lesson content for \'[RUN_1785902926489] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.99ms).', '2026-08-05 12:08:47'),
('1029', '10', 'Uploaded new lesson material \'[RUN_1785902926489] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:08:48'),
('1030', '10', 'Successfully extracted lesson content for \'[RUN_1785902926489] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.51ms).', '2026-08-05 12:08:48'),
('1031', '10', 'Uploaded new lesson material \'[RUN_1785903040583] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:10:40'),
('1032', '10', 'Successfully extracted lesson content for \'[RUN_1785903040583] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.53ms).', '2026-08-05 12:10:40'),
('1033', '10', 'Uploaded new lesson material \'[RUN_1785903040583] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:10:41'),
('1034', '10', 'Successfully extracted lesson content for \'[RUN_1785903040583] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.38ms).', '2026-08-05 12:10:41'),
('1035', '10', 'Uploaded new lesson material \'[RUN_1785903040583] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:10:42'),
('1036', '10', 'Successfully extracted lesson content for \'[RUN_1785903040583] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.66ms).', '2026-08-05 12:10:42'),
('1037', '10', 'Uploaded new lesson material \'[RUN_1785903040583] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:10:42'),
('1038', '10', 'Successfully extracted lesson content for \'[RUN_1785903040583] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.2ms).', '2026-08-05 12:10:42'),
('1039', '10', 'Saved AI-generated exam \'[RUN_1785903040583] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:10:44'),
('1040', '10', 'Saved AI-generated exam \'[RUN_1785903040583] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:11:06'),
('1041', '10', 'Saved AI-generated exam \'[RUN_1785903040583] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:11:09'),
('1042', '10', 'Saved AI-generated exam \'[RUN_1785903076336] MOCK_INCOMPLETE_BATCH Incomplete Assessment\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:11:16'),
('1043', '10', 'Uploaded new lesson material \'[RUN_1785903099679] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:11:40'),
('1044', '10', 'Successfully extracted lesson content for \'[RUN_1785903099679] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.9ms).', '2026-08-05 12:11:40'),
('1045', '10', 'Uploaded new lesson material \'[RUN_1785903099679] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:11:40'),
('1046', '10', 'Successfully extracted lesson content for \'[RUN_1785903099679] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.5ms).', '2026-08-05 12:11:40'),
('1047', '10', 'Uploaded new lesson material \'[RUN_1785903099679] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:11:41'),
('1048', '10', 'Successfully extracted lesson content for \'[RUN_1785903099679] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.85ms).', '2026-08-05 12:11:41'),
('1049', '10', 'Uploaded new lesson material \'[RUN_1785903099679] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:11:41'),
('1050', '10', 'Successfully extracted lesson content for \'[RUN_1785903099679] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.08ms).', '2026-08-05 12:11:41'),
('1051', '10', 'Saved AI-generated exam \'[RUN_1785903099679] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:11:43'),
('1052', '10', 'Saved AI-generated exam \'[RUN_1785903099679] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:12:05'),
('1053', '10', 'Uploaded new lesson material \'[RUN_1785903191904] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:13:12'),
('1054', '10', 'Successfully extracted lesson content for \'[RUN_1785903191904] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 2.64ms).', '2026-08-05 12:13:12'),
('1055', '10', 'Uploaded new lesson material \'[RUN_1785903191904] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:13:12'),
('1056', '10', 'Successfully extracted lesson content for \'[RUN_1785903191904] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.57ms).', '2026-08-05 12:13:12'),
('1057', '10', 'Uploaded new lesson material \'[RUN_1785903191904] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:13:13'),
('1058', '10', 'Successfully extracted lesson content for \'[RUN_1785903191904] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.63ms).', '2026-08-05 12:13:13'),
('1059', '10', 'Uploaded new lesson material \'[RUN_1785903191904] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:13:13'),
('1060', '10', 'Successfully extracted lesson content for \'[RUN_1785903191904] Steel Design Finals Module E2E\' (38 words, 1 pages, 2.34ms).', '2026-08-05 12:13:13'),
('1061', '10', 'Saved AI-generated exam \'[RUN_1785903191904] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:13:15'),
('1062', '10', 'Saved AI-generated exam \'[RUN_1785903191904] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:13:37'),
('1063', '10', 'Uploaded new lesson material \'[RUN_1785903290477] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:14:50'),
('1064', '10', 'Successfully extracted lesson content for \'[RUN_1785903290477] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 2.49ms).', '2026-08-05 12:14:50'),
('1065', '10', 'Uploaded new lesson material \'[RUN_1785903290477] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:14:51'),
('1066', '10', 'Successfully extracted lesson content for \'[RUN_1785903290477] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.38ms).', '2026-08-05 12:14:51'),
('1067', '10', 'Uploaded new lesson material \'[RUN_1785903290477] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:14:52'),
('1068', '10', 'Successfully extracted lesson content for \'[RUN_1785903290477] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.85ms).', '2026-08-05 12:14:52'),
('1069', '10', 'Uploaded new lesson material \'[RUN_1785903290477] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:14:52'),
('1070', '10', 'Successfully extracted lesson content for \'[RUN_1785903290477] Steel Design Finals Module E2E\' (38 words, 1 pages, 2.05ms).', '2026-08-05 12:14:52'),
('1071', '10', 'Saved AI-generated exam \'[RUN_1785903290477] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:14:54'),
('1072', '10', 'Saved AI-generated exam \'[RUN_1785903290477] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:15:17'),
('1073', '10', 'Uploaded new lesson material \'[RUN_1785903495462] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:18:15'),
('1074', '10', 'Successfully extracted lesson content for \'[RUN_1785903495462] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 2.09ms).', '2026-08-05 12:18:15'),
('1075', '10', 'Uploaded new lesson material \'[RUN_1785903495462] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:18:16'),
('1076', '10', 'Successfully extracted lesson content for \'[RUN_1785903495462] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 2.12ms).', '2026-08-05 12:18:16'),
('1077', '10', 'Uploaded new lesson material \'[RUN_1785903495462] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:18:16'),
('1078', '10', 'Successfully extracted lesson content for \'[RUN_1785903495462] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.28ms).', '2026-08-05 12:18:16'),
('1079', '10', 'Uploaded new lesson material \'[RUN_1785903495462] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:18:17'),
('1080', '10', 'Successfully extracted lesson content for \'[RUN_1785903495462] Steel Design Finals Module E2E\' (38 words, 1 pages, 2.85ms).', '2026-08-05 12:18:17'),
('1081', '10', 'Saved AI-generated exam \'[RUN_1785903495462] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:18:19'),
('1082', '10', 'Saved AI-generated exam \'[RUN_1785903495462] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:18:57'),
('1083', '10', 'Uploaded new lesson material \'[RUN_1785903970427] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:26:10'),
('1084', '10', 'Successfully extracted lesson content for \'[RUN_1785903970427] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 2.79ms).', '2026-08-05 12:26:10'),
('1085', '10', 'Uploaded new lesson material \'[RUN_1785903970427] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:26:11'),
('1086', '10', 'Successfully extracted lesson content for \'[RUN_1785903970427] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.13ms).', '2026-08-05 12:26:11'),
('1087', '10', 'Uploaded new lesson material \'[RUN_1785903970427] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:26:11'),
('1088', '10', 'Successfully extracted lesson content for \'[RUN_1785903970427] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.61ms).', '2026-08-05 12:26:11'),
('1089', '10', 'Uploaded new lesson material \'[RUN_1785903970427] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:26:12'),
('1090', '10', 'Successfully extracted lesson content for \'[RUN_1785903970427] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.42ms).', '2026-08-05 12:26:12'),
('1091', '10', 'Saved AI-generated exam \'[RUN_1785903970427] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:26:14'),
('1092', '10', 'Saved AI-generated exam \'[RUN_1785903970427] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:26:52'),
('1093', '10', 'Uploaded new lesson material \'[RUN_1785904133156] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:28:53'),
('1094', '10', 'Successfully extracted lesson content for \'[RUN_1785904133156] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.86ms).', '2026-08-05 12:28:53'),
('1095', '10', 'Uploaded new lesson material \'[RUN_1785904133156] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:28:54'),
('1096', '10', 'Successfully extracted lesson content for \'[RUN_1785904133156] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.39ms).', '2026-08-05 12:28:54'),
('1097', '10', 'Uploaded new lesson material \'[RUN_1785904133156] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:28:54'),
('1098', '10', 'Successfully extracted lesson content for \'[RUN_1785904133156] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.72ms).', '2026-08-05 12:28:54'),
('1099', '10', 'Uploaded new lesson material \'[RUN_1785904133156] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:28:55'),
('1100', '10', 'Successfully extracted lesson content for \'[RUN_1785904133156] Steel Design Finals Module E2E\' (38 words, 1 pages, 2.33ms).', '2026-08-05 12:28:55'),
('1101', '10', 'Saved AI-generated exam \'[RUN_1785904133156] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:28:57'),
('1102', '10', 'Saved AI-generated exam \'[RUN_1785904133156] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:28:59'),
('1103', '10', 'Saved AI-generated exam \'[RUN_1785904133156] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:29:02'),
('1104', '10', 'Saved AI-generated exam \'[RUN_1785904133156] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:29:04'),
('1105', '10', 'Uploaded new lesson material \'[RUN_1785904312849] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:31:53'),
('1106', '10', 'Successfully extracted lesson content for \'[RUN_1785904312849] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.59ms).', '2026-08-05 12:31:53'),
('1107', '10', 'Uploaded new lesson material \'[RUN_1785904312849] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:31:53'),
('1108', '10', 'Successfully extracted lesson content for \'[RUN_1785904312849] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.58ms).', '2026-08-05 12:31:53'),
('1109', '10', 'Uploaded new lesson material \'[RUN_1785904312849] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:31:54'),
('1110', '10', 'Successfully extracted lesson content for \'[RUN_1785904312849] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.77ms).', '2026-08-05 12:31:54'),
('1111', '10', 'Uploaded new lesson material \'[RUN_1785904312849] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:31:54');
INSERT INTO `activity_logs` (`id`, `user_id`, `action_description`, `created_at`) VALUES 
('1112', '10', 'Successfully extracted lesson content for \'[RUN_1785904312849] Steel Design Finals Module E2E\' (38 words, 1 pages, 2.07ms).', '2026-08-05 12:31:54'),
('1113', '10', 'Saved AI-generated exam \'[RUN_1785904312849] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:31:56'),
('1114', '10', 'Saved AI-generated exam \'[RUN_1785904312849] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:31:59'),
('1115', '10', 'Saved AI-generated exam \'[RUN_1785904312849] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:32:01'),
('1116', '10', 'Saved AI-generated exam \'[RUN_1785904312849] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:32:03'),
('1117', '10', 'Uploaded new lesson material \'[RUN_1785905693524] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:54:53'),
('1118', '10', 'Successfully extracted lesson content for \'[RUN_1785905693524] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.58ms).', '2026-08-05 12:54:53'),
('1119', '10', 'Uploaded new lesson material \'[RUN_1785905693524] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:54:54'),
('1120', '10', 'Successfully extracted lesson content for \'[RUN_1785905693524] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.46ms).', '2026-08-05 12:54:54'),
('1121', '10', 'Uploaded new lesson material \'[RUN_1785905693524] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:54:55'),
('1122', '10', 'Successfully extracted lesson content for \'[RUN_1785905693524] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.51ms).', '2026-08-05 12:54:55'),
('1123', '10', 'Uploaded new lesson material \'[RUN_1785905693524] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:54:55'),
('1124', '10', 'Successfully extracted lesson content for \'[RUN_1785905693524] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.55ms).', '2026-08-05 12:54:55'),
('1125', '10', 'Uploaded new lesson material \'[RUN_1785905797821] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:56:38'),
('1126', '10', 'Successfully extracted lesson content for \'[RUN_1785905797821] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.11ms).', '2026-08-05 12:56:38'),
('1127', '10', 'Uploaded new lesson material \'[RUN_1785905797821] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:56:38'),
('1128', '10', 'Successfully extracted lesson content for \'[RUN_1785905797821] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.63ms).', '2026-08-05 12:56:38'),
('1129', '10', 'Uploaded new lesson material \'[RUN_1785905797821] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:56:39'),
('1130', '10', 'Successfully extracted lesson content for \'[RUN_1785905797821] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.51ms).', '2026-08-05 12:56:39'),
('1131', '10', 'Uploaded new lesson material \'[RUN_1785905797821] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:56:40'),
('1132', '10', 'Successfully extracted lesson content for \'[RUN_1785905797821] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.57ms).', '2026-08-05 12:56:40'),
('1133', '10', 'Uploaded new lesson material \'[RUN_1785905905640] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:58:26'),
('1134', '10', 'Successfully extracted lesson content for \'[RUN_1785905905640] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.73ms).', '2026-08-05 12:58:26'),
('1135', '10', 'Uploaded new lesson material \'[RUN_1785905905640] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:58:26'),
('1136', '10', 'Successfully extracted lesson content for \'[RUN_1785905905640] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.41ms).', '2026-08-05 12:58:26'),
('1137', '10', 'Uploaded new lesson material \'[RUN_1785905905640] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:58:27'),
('1138', '10', 'Successfully extracted lesson content for \'[RUN_1785905905640] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 0.9ms).', '2026-08-05 12:58:27'),
('1139', '10', 'Uploaded new lesson material \'[RUN_1785905905640] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 12:58:27'),
('1140', '10', 'Successfully extracted lesson content for \'[RUN_1785905905640] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.11ms).', '2026-08-05 12:58:27'),
('1141', '10', 'Saved AI-generated exam \'[RUN_1785905905640] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:58:29'),
('1142', '10', 'Saved AI-generated exam \'[RUN_1785905905640] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:58:32'),
('1143', '10', 'Saved AI-generated exam \'[RUN_1785905905640] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:58:34'),
('1144', '10', 'Saved AI-generated exam \'[RUN_1785905905640] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:58:36'),
('1145', '10', 'Saved AI-generated exam \'[RUN_1785905905640] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:58:43'),
('1146', '10', 'Saved AI-generated exam \'[RUN_1785905905640] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 12:58:45'),
('1147', '10', 'Uploaded new lesson material \'[RUN_1785907820521] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:30:20'),
('1148', '10', 'Successfully extracted lesson content for \'[RUN_1785907820521] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.06ms).', '2026-08-05 13:30:20'),
('1149', '10', 'Uploaded new lesson material \'[RUN_1785907820521] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:30:21'),
('1150', '10', 'Successfully extracted lesson content for \'[RUN_1785907820521] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.59ms).', '2026-08-05 13:30:21'),
('1151', '10', 'Uploaded new lesson material \'[RUN_1785907820521] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:30:22'),
('1152', '10', 'Successfully extracted lesson content for \'[RUN_1785907820521] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.36ms).', '2026-08-05 13:30:22'),
('1153', '10', 'Uploaded new lesson material \'[RUN_1785907820521] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:30:22'),
('1154', '10', 'Successfully extracted lesson content for \'[RUN_1785907820521] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.06ms).', '2026-08-05 13:30:22'),
('1155', '10', 'Saved AI-generated exam \'[RUN_1785907820521] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:30:24'),
('1156', '10', 'Saved AI-generated exam \'[RUN_1785907820521] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:30:27'),
('1157', '10', 'Saved AI-generated exam \'[RUN_1785907820521] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:30:29'),
('1158', '10', 'Saved AI-generated exam \'[RUN_1785907820521] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:30:32'),
('1159', '10', 'Uploaded new lesson material \'[RUN_1785908000967] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:33:21'),
('1160', '10', 'Successfully extracted lesson content for \'[RUN_1785908000967] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.18ms).', '2026-08-05 13:33:21'),
('1161', '10', 'Uploaded new lesson material \'[RUN_1785908000967] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:33:22'),
('1162', '10', 'Successfully extracted lesson content for \'[RUN_1785908000967] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.08ms).', '2026-08-05 13:33:22'),
('1163', '10', 'Uploaded new lesson material \'[RUN_1785908000967] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:33:22'),
('1164', '10', 'Successfully extracted lesson content for \'[RUN_1785908000967] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.39ms).', '2026-08-05 13:33:22'),
('1165', '10', 'Uploaded new lesson material \'[RUN_1785908000967] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:33:23'),
('1166', '10', 'Successfully extracted lesson content for \'[RUN_1785908000967] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.61ms).', '2026-08-05 13:33:23'),
('1167', '10', 'Saved AI-generated exam \'[RUN_1785908000967] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:33:25'),
('1168', '10', 'Saved AI-generated exam \'[RUN_1785908000967] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:33:27'),
('1169', '10', 'Saved AI-generated exam \'[RUN_1785908000967] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:33:30'),
('1170', '10', 'Saved AI-generated exam \'[RUN_1785908000967] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:33:32'),
('1171', '10', 'Uploaded new lesson material \'[RUN_1785908415358] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:40:15'),
('1172', '10', 'Successfully extracted lesson content for \'[RUN_1785908415358] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.08ms).', '2026-08-05 13:40:15'),
('1173', '10', 'Uploaded new lesson material \'[RUN_1785908415358] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:40:16'),
('1174', '10', 'Successfully extracted lesson content for \'[RUN_1785908415358] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.6ms).', '2026-08-05 13:40:16'),
('1175', '10', 'Uploaded new lesson material \'[RUN_1785908415358] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:40:17'),
('1176', '10', 'Successfully extracted lesson content for \'[RUN_1785908415358] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.75ms).', '2026-08-05 13:40:17'),
('1177', '10', 'Uploaded new lesson material \'[RUN_1785908415358] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:40:17'),
('1178', '10', 'Successfully extracted lesson content for \'[RUN_1785908415358] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.91ms).', '2026-08-05 13:40:17'),
('1179', '10', 'Saved AI-generated exam \'[RUN_1785908415358] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:40:19'),
('1180', '10', 'Saved AI-generated exam \'[RUN_1785908415358] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:40:22'),
('1181', '10', 'Saved AI-generated exam \'[RUN_1785908415358] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:40:24'),
('1182', '10', 'Saved AI-generated exam \'[RUN_1785908415358] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:40:26'),
('1183', '10', 'Uploaded new lesson material \'[RUN_1785908519563] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:42:00'),
('1184', '10', 'Successfully extracted lesson content for \'[RUN_1785908519563] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 3.5ms).', '2026-08-05 13:42:00'),
('1185', '10', 'Uploaded new lesson material \'[RUN_1785908519563] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:42:00'),
('1186', '10', 'Successfully extracted lesson content for \'[RUN_1785908519563] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.65ms).', '2026-08-05 13:42:00'),
('1187', '10', 'Uploaded new lesson material \'[RUN_1785908519563] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:42:01'),
('1188', '10', 'Successfully extracted lesson content for \'[RUN_1785908519563] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.47ms).', '2026-08-05 13:42:01'),
('1189', '10', 'Uploaded new lesson material \'[RUN_1785908519563] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-05 13:42:02'),
('1190', '10', 'Successfully extracted lesson content for \'[RUN_1785908519563] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.24ms).', '2026-08-05 13:42:02'),
('1191', '10', 'Saved AI-generated exam \'[RUN_1785908519563] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:42:04'),
('1192', '10', 'Saved AI-generated exam \'[RUN_1785908519563] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:42:06'),
('1193', '10', 'Saved AI-generated exam \'[RUN_1785908519563] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:42:08'),
('1194', '10', 'Saved AI-generated exam \'[RUN_1785908519563] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-05 13:42:11'),
('1195', '10', 'Uploaded new lesson material \'[RUN_1785986463573] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-06 11:21:03'),
('1196', '10', 'Successfully extracted lesson content for \'[RUN_1785986463573] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.66ms).', '2026-08-06 11:21:03'),
('1197', '10', 'Uploaded new lesson material \'[RUN_1785986463573] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-06 11:21:03'),
('1198', '10', 'Successfully extracted lesson content for \'[RUN_1785986463573] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 1.95ms).', '2026-08-06 11:21:03'),
('1199', '10', 'Uploaded new lesson material \'[RUN_1785986463573] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-06 11:21:04'),
('1200', '10', 'Successfully extracted lesson content for \'[RUN_1785986463573] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 2.38ms).', '2026-08-06 11:21:04'),
('1201', '10', 'Uploaded new lesson material \'[RUN_1785986463573] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-06 11:21:04'),
('1202', '10', 'Successfully extracted lesson content for \'[RUN_1785986463573] Steel Design Finals Module E2E\' (38 words, 1 pages, 2.05ms).', '2026-08-06 11:21:04'),
('1203', '10', 'Saved AI-generated exam \'[RUN_1785986463573] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:21:05'),
('1204', '10', 'Saved AI-generated exam \'[RUN_1785986463573] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:21:07'),
('1205', '10', 'Saved AI-generated exam \'[RUN_1785986463573] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:21:09'),
('1206', '10', 'Saved AI-generated exam \'[RUN_1785986463573] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:21:11'),
('1207', '10', 'Saved AI-generated exam \'[RUN_1785986463573] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:21:22'),
('1208', '10', 'Saved AI-generated exam \'[RUN_1785986463573] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:21:23'),
('1209', '10', 'Saved AI-generated exam \'[RUN_1785986463573] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:21:25'),
('1210', '10', 'Saved AI-generated exam \'[RUN_1785986463573] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:21:27'),
('1211', '10', 'Uploaded new lesson material \'[RUN_1785987963843] General Civil Engineering Fundamentals E2E\' (lesson_general.txt) for subject \'Structural Engineering\'.', '2026-08-06 11:46:04');
INSERT INTO `activity_logs` (`id`, `user_id`, `action_description`, `created_at`) VALUES 
('1212', '10', 'Successfully extracted lesson content for \'[RUN_1785987963843] General Civil Engineering Fundamentals E2E\' (31 words, 1 pages, 1.12ms).', '2026-08-06 11:46:04'),
('1213', '10', 'Uploaded new lesson material \'[RUN_1785987963843] Structural Analysis Prelim Module E2E\' (lesson_prelim.txt) for subject \'Structural Engineering\'.', '2026-08-06 11:46:04'),
('1214', '10', 'Successfully extracted lesson content for \'[RUN_1785987963843] Structural Analysis Prelim Module E2E\' (36 words, 1 pages, 2.09ms).', '2026-08-06 11:46:04'),
('1215', '10', 'Uploaded new lesson material \'[RUN_1785987963843] Reinforced Concrete Design Midterm Module E2E\' (lesson_midterm.txt) for subject \'Structural Engineering\'.', '2026-08-06 11:46:04'),
('1216', '10', 'Successfully extracted lesson content for \'[RUN_1785987963843] Reinforced Concrete Design Midterm Module E2E\' (39 words, 1 pages, 1.49ms).', '2026-08-06 11:46:04'),
('1217', '10', 'Uploaded new lesson material \'[RUN_1785987963843] Steel Design Finals Module E2E\' (lesson_finals.txt) for subject \'Structural Engineering\'.', '2026-08-06 11:46:04'),
('1218', '10', 'Successfully extracted lesson content for \'[RUN_1785987963843] Steel Design Finals Module E2E\' (38 words, 1 pages, 1.75ms).', '2026-08-06 11:46:04'),
('1219', '10', 'Saved AI-generated exam \'[RUN_1785987963843] Authoritative Cross-Period Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:51:01'),
('1220', '10', 'Saved AI-generated exam \'[RUN_1785987963843] Unresolved Source Save Attempt\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:51:03'),
('1221', '10', 'Saved AI-generated exam \'[RUN_1785987963843] Unacknowledged Incomplete Exam\' (4 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:51:04'),
('1222', '10', 'Saved AI-generated exam \'[RUN_1785987963843] Saved Refill Coverage Exam\' (5 deduplicated questions, Difficulty: medium, Cross-Period).', '2026-08-06 11:51:07'),
('1223', '10', 'Workflow Transition: Submission #1082 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:38:24'),
('1224', '10', 'Workflow Transition: Submission #1083 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:38:24'),
('1225', '10', 'Workflow Transition: Submission #1086 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:38:28'),
('1226', '10', 'Workflow Transition: Submission #1087 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:38:28'),
('1227', '10', 'Workflow Transition: Submission #1092 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:38:44'),
('1228', '10', 'Workflow Transition: Submission #1093 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:38:44'),
('1229', '10', 'Workflow Transition: Submission #1096 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:43:14'),
('1230', '10', 'Workflow Transition: Submission #1097 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:43:14'),
('1231', '10', 'Workflow Transition: Submission #1100 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:14'),
('1232', '10', 'Workflow Transition: Submission #1101 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:14'),
('1233', '10', 'Workflow Transition: Submission #1103 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:43:21'),
('1234', '10', 'Workflow Transition: Submission #1104 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:43:21'),
('1235', '10', 'Workflow Transition: Submission #1107 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:21'),
('1236', '10', 'Workflow Transition: Submission #1108 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:21'),
('1237', '10', 'Workflow Transition: Submission #1110 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:43:26'),
('1238', '10', 'Workflow Transition: Submission #1111 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:43:26'),
('1239', '10', 'Workflow Transition: Submission #1114 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:26'),
('1240', '10', 'Workflow Transition: Submission #1115 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:26'),
('1241', '10', 'Workflow Transition: Submission #1117 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:43:30'),
('1242', '10', 'Workflow Transition: Submission #1118 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:43:30'),
('1243', '10', 'Workflow Transition: Submission #1121 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:30'),
('1244', '10', 'Workflow Transition: Submission #1122 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:30'),
('1245', '10', 'Workflow Transition: Submission #1124 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:43:45'),
('1246', '10', 'Workflow Transition: Submission #1125 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:43:45'),
('1247', '10', 'Workflow Transition: Submission #1128 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:45'),
('1248', '10', 'Workflow Transition: Submission #1129 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:45'),
('1249', '10', 'Workflow Transition: Submission #1131 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:43:51'),
('1250', '10', 'Workflow Transition: Submission #1132 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:43:51'),
('1251', '10', 'Workflow Transition: Submission #1135 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:51'),
('1252', '10', 'Workflow Transition: Submission #1136 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:43:51'),
('1253', '10', 'Workflow Transition: Submission #1140 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:44:04'),
('1254', '10', 'Workflow Transition: Submission #1141 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:44:04'),
('1255', '10', 'Workflow Transition: Submission #1144 moved from \'reviewed\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:44:04'),
('1256', '10', 'Workflow Transition: Submission #1145 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:44:04'),
('1257', '10', 'Workflow Transition: Submission #1149 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 12:49:36'),
('1258', '10', 'Workflow Transition: Submission #1149 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:49:36'),
('1259', '10', 'Workflow Transition: Submission #1150 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:49:36'),
('1260', '10', 'Workflow Transition: Submission #1153 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:49:36'),
('1261', '10', 'Workflow Transition: Submission #1154 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:49:36'),
('1262', '10', 'Workflow Transition: Submission #1160 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 12:49:40'),
('1263', '10', 'Workflow Transition: Submission #1160 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:49:40'),
('1264', '10', 'Workflow Transition: Submission #1161 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:49:40'),
('1265', '10', 'Workflow Transition: Submission #1164 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:49:40'),
('1266', '10', 'Workflow Transition: Submission #1165 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:49:40'),
('1267', '10', 'Workflow Transition: Submission #1171 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 12:49:44'),
('1268', '10', 'Workflow Transition: Submission #1171 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:49:44'),
('1269', '10', 'Workflow Transition: Submission #1172 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:49:44'),
('1270', '10', 'Workflow Transition: Submission #1175 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:49:44'),
('1271', '10', 'Workflow Transition: Submission #1176 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:49:44'),
('1272', '10', 'Workflow Transition: Submission #1181 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 12:49:54'),
('1273', '10', 'Workflow Transition: Submission #1181 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:49:54'),
('1274', '10', 'Workflow Transition: Submission #1182 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:49:54'),
('1275', '10', 'Workflow Transition: Submission #1185 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:49:54'),
('1276', '10', 'Workflow Transition: Submission #1186 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:49:54'),
('1277', '10', 'Workflow Transition: Submission #1194 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 12:50:05'),
('1278', '10', 'Workflow Transition: Submission #1194 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 12:50:05'),
('1279', '10', 'Workflow Transition: Submission #1195 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 12:50:05'),
('1280', '10', 'Workflow Transition: Submission #1198 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:50:05'),
('1281', '10', 'Workflow Transition: Submission #1199 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 12:50:05'),
('1282', '10', 'Scheduled Exam #1163 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:42:43'),
('1283', '10', 'Scheduled Exam #1165 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:43:00'),
('1284', '10', 'Scheduled Exam #1167 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:43:06'),
('1285', '10', 'Scheduled Exam #1169 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:43:12'),
('1286', '10', 'Scheduled Exam #1171 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:43:18'),
('1287', '10', 'Scheduled Exam #1173 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:43:31'),
('1288', '10', 'Scheduled Exam #1175 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:43:40'),
('1289', '10', 'Scheduled Exam #1177 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:43:53'),
('1290', '10', 'Scheduled Exam #1179 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:43:59'),
('1291', '10', 'Scheduled Exam #1181 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:44:05'),
('1292', '10', 'Scheduled Exam #1183 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:44:11'),
('1293', '10', 'Workflow Transition: Submission #1207 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 13:44:27'),
('1294', '10', 'Workflow Transition: Submission #1207 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 13:44:27'),
('1295', '10', 'Workflow Transition: Submission #1208 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 13:44:27'),
('1296', '10', 'Workflow Transition: Submission #1211 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 13:44:27'),
('1297', '10', 'Workflow Transition: Submission #1212 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 13:44:27'),
('1298', '10', 'Scheduled Exam #1202 (\'Priority 4 Schedule Test Exam\') for Section CE-TEST on 2026-08-07 10:00:00-11:30:00', '2026-08-06 13:44:27'),
('1299', '10', 'Scheduled Exam #1207 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-172 on 2025-07-01 10:00:00-11:30:00', '2026-08-06 13:52:41'),
('1300', '10', 'Workflow Transition: Submission #1220 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 13:52:54'),
('1301', '10', 'Workflow Transition: Submission #1220 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 13:52:54'),
('1302', '10', 'Workflow Transition: Submission #1221 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 13:52:54'),
('1303', '10', 'Workflow Transition: Submission #1224 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 13:52:54'),
('1304', '10', 'Workflow Transition: Submission #1225 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 13:52:54'),
('1305', '10', 'Scheduled Exam #1226 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-376 on 2025-07-01 10:00:00-11:30:00', '2026-08-06 13:52:55'),
('1306', '10', 'Scheduled Exam #1228 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-A5F7 on 2025-07-01 10:00:00-11:30:00', '2026-08-06 14:01:12'),
('1307', '10', 'Scheduled Exam #1230 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-7D81 on 2025-07-01 10:00:00-11:30:00', '2026-08-06 14:01:45'),
('1308', '10', 'Workflow Transition: Submission #1233 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 14:02:03'),
('1309', '10', 'Workflow Transition: Submission #1233 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 14:02:03'),
('1310', '10', 'Workflow Transition: Submission #1234 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 14:02:03'),
('1311', '10', 'Workflow Transition: Submission #1237 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:02:03');
INSERT INTO `activity_logs` (`id`, `user_id`, `action_description`, `created_at`) VALUES 
('1312', '10', 'Workflow Transition: Submission #1238 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:02:03'),
('1313', '10', 'Scheduled Exam #1249 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-FB6F on 2025-07-01 10:00:00-11:30:00', '2026-08-06 14:02:03'),
('1314', '10', 'Workflow Transition: Submission #1246 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 14:09:00'),
('1315', '10', 'Workflow Transition: Submission #1246 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 14:09:00'),
('1316', '10', 'Workflow Transition: Submission #1247 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 14:09:00'),
('1317', '10', 'Workflow Transition: Submission #1250 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:09:00'),
('1318', '10', 'Workflow Transition: Submission #1251 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:09:00'),
('1319', '10', 'Scheduled Exam #1276 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-1C50 on 2025-07-01 10:00:00-11:30:00; Exam Owner ID: 10', '2026-08-06 14:13:41'),
('1321', '10', 'Workflow Transition: Submission #1259 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 14:13:53'),
('1322', '10', 'Workflow Transition: Submission #1259 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 14:13:53'),
('1323', '10', 'Workflow Transition: Submission #1260 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 14:13:53'),
('1324', '10', 'Workflow Transition: Submission #1263 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:13:53'),
('1325', '10', 'Workflow Transition: Submission #1264 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:13:53'),
('1326', '10', 'Scheduled Exam #1295 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-719B on 2025-07-01 10:00:00-11:30:00; Exam Owner ID: 10', '2026-08-06 14:13:54'),
('1328', '10', 'Workflow Transition: Submission #1272 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 14:19:31'),
('1329', '10', 'Workflow Transition: Submission #1272 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 14:19:31'),
('1330', '10', 'Workflow Transition: Submission #1273 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 14:19:31'),
('1331', '10', 'Workflow Transition: Submission #1276 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:19:31'),
('1332', '10', 'Workflow Transition: Submission #1277 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:19:31'),
('1333', '10', 'Scheduled Exam #1314 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-6185 on 2025-07-01 10:00:00-11:30:00; Exam Owner ID: 10', '2026-08-06 14:19:31'),
('1334', '44', 'Scheduled Exam #1315 (\'Unowned Exam 2\') for Section CE-P4-SEC-6185 on 2025-07-01 14:45:00-16:00:00; Exam Owner ID: 12', '2026-08-06 14:19:31'),
('1335', '10', 'Workflow Transition: Submission #1285 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 14:26:16'),
('1336', '10', 'Workflow Transition: Submission #1285 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 14:26:16'),
('1337', '10', 'Workflow Transition: Submission #1286 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 14:26:16'),
('1338', '10', 'Workflow Transition: Submission #1289 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:26:16'),
('1339', '10', 'Workflow Transition: Submission #1290 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:26:16'),
('1340', '10', 'Scheduled Exam #1333 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-9259 on 2025-07-01 10:00:00-11:30:00; Exam Owner ID: 10', '2026-08-06 14:26:16'),
('1341', '44', 'Scheduled Exam #1334 (\'Unowned Exam 2\') for Section CE-P4-SEC-9259 on 2025-07-01 14:45:00-16:00:00; Exam Owner ID: 12', '2026-08-06 14:26:16'),
('1342', '10', 'Workflow Transition: Submission #1298 moved from \'reviewed\' to \'finalized\' by User #10 (teacher). Remarks: \'Finalization test\'', '2026-08-06 14:34:20'),
('1343', '10', 'Workflow Transition: Submission #1298 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Single publication test\'', '2026-08-06 14:34:20'),
('1344', '10', 'Workflow Transition: Submission #1299 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Bulk publication test\'', '2026-08-06 14:34:20'),
('1345', '10', 'Workflow Transition: Submission #1302 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:34:20'),
('1346', '10', 'Workflow Transition: Submission #1303 moved from \'finalized\' to \'published\' by User #10 (teacher). Remarks: \'Exam wide publication test\'', '2026-08-06 14:34:20'),
('1347', '10', 'Scheduled Exam #1352 (\'Teacher Owned Exam 1\') for Section CE-P4-SEC-3BD1 on 2025-07-01 10:00:00-11:30:00; Exam Owner ID: 10', '2026-08-06 14:34:20'),
('1348', '44', 'Scheduled Exam #1353 (\'Unowned Exam 2\') for Section CE-P4-SEC-3BD1 on 2025-07-01 14:45:00-16:00:00; Exam Owner ID: 12', '2026-08-06 14:34:20');

-- --------------------------------------------------------
-- Table structure for `ai_generation_batches`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ai_generation_batches`;
CREATE TABLE `ai_generation_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `generation_batch_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `teacher_id` int NOT NULL,
  `selected_lesson_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `selected_lesson_titles` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `selected_periods` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `selected_subject` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `semester` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `year_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `program` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `total_selected_words` int DEFAULT '0',
  `estimated_tokens` int DEFAULT '0',
  `ai_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `generation_duration` float DEFAULT '0',
  `requested_question_count` int DEFAULT '0',
  `generated_question_count` int DEFAULT '0',
  `failed_question_count` int DEFAULT '0',
  `warnings` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `batch_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'completed',
  `failed_chunk_count` int DEFAULT '0',
  `affected_lesson_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `failure_messages` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `teacher_acknowledged_at` timestamp NULL DEFAULT NULL,
  `teacher_acknowledged_by` int DEFAULT NULL,
  `acknowledgement_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `acknowledgement_token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `batch_consumed_at` timestamp NULL DEFAULT NULL,
  `batch_consumed_by` int DEFAULT NULL,
  `saved_exam_id` int DEFAULT NULL,
  `chunk_generation_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `questions_per_lesson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `questions_per_period` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `uncovered_lesson_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `uncovered_periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `refill_attempt_count` int DEFAULT '0',
  `refill_warnings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `simulated_scenario` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `failed_chunk_index` int DEFAULT NULL,
  `refill_target_chunk_index` int DEFAULT NULL,
  `refill_target_lesson_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `refill_target_periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `refill_generated_count` int DEFAULT '0',
  `initial_questions_per_lesson` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `initial_questions_per_period` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `initial_uncovered_lesson_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `initial_uncovered_periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `affected_periods` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `failed_chunk_indexes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `failed_chunks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `period_weighting_mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'equal',
  `requested_period_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `actual_period_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `requested_question_blueprint` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `actual_question_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `requested_difficulty_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `actual_difficulty_distribution` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `duplicate_count` int DEFAULT '0',
  `replacement_attempt_count` int DEFAULT '0',
  `duplicate_warnings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `replacement_success_count` int DEFAULT '0',
  `unresolved_duplicate_count` int DEFAULT '0',
  `period_distribution_mismatch` tinyint(1) DEFAULT '0',
  `question_blueprint_mismatch` tinyint(1) DEFAULT '0',
  `difficulty_distribution_mismatch` tinyint(1) DEFAULT '0',
  `unresolved_differences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_id` (`generation_batch_id`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1406 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `audit_logs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_id` int DEFAULT NULL,
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `entity_id` int NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor` (`actor_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_audit_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `audit_logs`

INSERT INTO `audit_logs` (`id`, `actor_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `reason`, `created_at`, `user_id`, `details`, `ip_address`) VALUES 
('1', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 13:44:11', '10', 'Type: students, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('2', '10', 'P4 Verification Action', 'system', '0', NULL, NULL, NULL, '2026-08-06 13:44:11', '10', 'Testing audit log recording', '127.0.0.1'),
('3', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 13:44:28', '10', 'Type: students, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('4', '10', 'P4 Verification Action', 'system', '0', NULL, NULL, NULL, '2026-08-06 13:44:28', '10', 'Testing audit log recording', '127.0.0.1'),
('5', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 13:52:41', '10', 'Type: subjects, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('6', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 13:52:41', '10', 'Type: students, Imported: 1 rows, Skipped Invalid: 0', '127.0.0.1'),
('7', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 13:52:55', '10', 'Type: subjects, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('8', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 13:52:55', '10', 'Type: students, Imported: 1 rows, Skipped Invalid: 0', '127.0.0.1'),
('9', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:01:12', '10', 'Type: subjects, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('10', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:01:12', '10', 'Type: students, Imported: 1 rows, Skipped Invalid: 0', '127.0.0.1'),
('11', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:01:45', '10', 'Type: subjects, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('12', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:01:45', '10', 'Type: students, Imported: 1 rows, Skipped Invalid: 0', '127.0.0.1'),
('13', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:02:03', '10', 'Type: subjects, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('14', '10', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:02:04', '10', 'Type: students, Imported: 1 rows, Skipped Invalid: 0', '127.0.0.1'),
('23', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:05', '44', 'Restored from file: qb_backup_2026-08-06_061905_361078.sql', '127.0.0.1'),
('24', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:05', '44', 'Deleted backup: qb_backup_2026-08-06_061905_361078.sql', '127.0.0.1'),
('25', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:12', '44', 'Restored from file: qb_backup_2026-08-06_061912_58b077.sql', '127.0.0.1'),
('26', '44', 'Cleaned Temporary Files', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:12', '44', 'Removed 5 temporary files, freed 870 B', '127.0.0.1'),
('27', '44', 'Terminated Active Session', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:12', '44', 'Session ID/DB ID: 0283b001dc7c336958a98d8e53f5cdec', '127.0.0.1'),
('28', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:12', '44', 'Deleted backup: qb_backup_2026-08-06_061912_58b077.sql', '127.0.0.1'),
('29', '44', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:31', '44', 'Type: subjects, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('30', '44', 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:31', '44', 'Type: students, Imported: 1 rows, Skipped Invalid: 0', '127.0.0.1'),
('31', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:31', '44', 'Restored from file: qb_backup_2026-08-06_061931_6a2919.sql', '127.0.0.1'),
('32', '44', 'Cleaned Temporary Files', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:31', '44', 'Removed 0 temporary files, freed 0 B', '127.0.0.1'),
('33', '44', 'Terminated Active Session', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:31', '44', 'Session ID/DB ID: 989de34236785d2198c54fa02ae2591f', '127.0.0.1'),
('34', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:19:31', '44', 'Deleted backup: qb_backup_2026-08-06_061931_6a2919.sql', '127.0.0.1'),
('35', '44', 'Terminated Active Session', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:25:38', '44', 'Session ID/DB ID: test_sess_728dcec67cc59928', '127.0.0.1'),
('36', '44', 'Deleted Orphaned Files', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:25:39', '44', 'Requested: 1, Deleted: 1, Rejected: 0, Freed: 19 B', '127.0.0.1'),
('37', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:25:39', '44', 'Source File: qb_backup_2026-08-06_062539_82485b.sql, SHA-256: 36de13cf7d94ab656f8fd618b383bc6b3bbf26d2e987483b6eefc30939336d56, Safety Backup: qb_safety_backup_2026-08-06_062539_c45baf.sql', '127.0.0.1'),
('38', NULL, 'CLI Test Action Invalid Actor', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:25:39', NULL, 'Details test', '127.0.0.1'),
('39', NULL, 'System Automated Task', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:25:39', NULL, 'No actor test', '127.0.0.1'),
('40', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:25:39', '44', 'Deleted backup: qb_backup_2026-08-06_062539_82485b.sql', '127.0.0.1'),
('41', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:25:39', '44', 'Deleted backup: qb_safety_backup_2026-08-06_062539_c45baf.sql', '127.0.0.1'),
('42', '44', 'Terminated Active Session', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:02', '44', 'Session ID/DB ID: test_sess_47dd76f6cce665a2', '127.0.0.1'),
('43', '44', 'Deleted Orphaned Files', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:02', '44', 'Requested: 1, Deleted: 1, Rejected: 0, Freed: 19 B', '127.0.0.1'),
('44', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:03', '44', 'Source File: qb_backup_2026-08-06_062602_307fb4.sql, SHA-256: 887d1d302e3a3569fec93b6b743062032eadda84b6051a4efc124db2da90c726, Safety Backup: qb_safety_backup_2026-08-06_062602_7feedc.sql', '127.0.0.1'),
('45', NULL, 'CLI Test Action Invalid Actor', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:03', NULL, 'Details test', '127.0.0.1'),
('46', NULL, 'System Automated Task', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:03', NULL, 'No actor test', '127.0.0.1'),
('47', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:03', '44', 'Deleted backup: qb_backup_2026-08-06_062602_307fb4.sql', '127.0.0.1'),
('48', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:03', '44', 'Deleted backup: qb_safety_backup_2026-08-06_062602_7feedc.sql', '127.0.0.1'),
('49', NULL, 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:16', NULL, 'Type: subjects, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('50', NULL, 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:16', NULL, 'Type: students, Imported: 1 rows, Skipped Invalid: 0', '127.0.0.1'),
('51', '44', 'Terminated Active Session', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:16', '44', 'Session ID/DB ID: test_sess_3342f105b6cc0c8f', '127.0.0.1'),
('52', '44', 'Deleted Orphaned Files', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:17', '44', 'Requested: 1, Deleted: 1, Rejected: 0, Freed: 19 B', '127.0.0.1'),
('53', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:17', '44', 'Source File: qb_backup_2026-08-06_062617_818110.sql, SHA-256: 2e1238f4cef8f48acbef09f48dbf82487e78344dabf508adf86f0ff7c54ddeb9, Safety Backup: qb_safety_backup_2026-08-06_062617_a4355c.sql', '127.0.0.1'),
('54', NULL, 'CLI Test Action Invalid Actor', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:17', NULL, 'Details test', '127.0.0.1'),
('55', NULL, 'System Automated Task', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:17', NULL, 'No actor test', '127.0.0.1'),
('56', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:17', '44', 'Deleted backup: qb_backup_2026-08-06_062617_818110.sql', '127.0.0.1'),
('57', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:26:17', '44', 'Deleted backup: qb_safety_backup_2026-08-06_062617_a4355c.sql', '127.0.0.1'),
('58', '44', 'Terminated Active Session', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:33:52', '44', 'Session ID/DB ID: test_sess_d762359f14979a50', '127.0.0.1'),
('59', '44', 'Deleted Orphaned Files', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:33:53', '44', 'Requested: 1, Deleted: 1, Rejected: 0, Freed: 15 B', '127.0.0.1'),
('60', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:33:53', '44', 'Source File: qb_backup_2026-08-06_063353_cd8087.sql, SHA-256: abfe7f1d355ac8b5756d4b7a63880c4cbc866ffcaed51c07738cbf03ca8cb19c, Safety Backup: qb_safety_backup_2026-08-06_063353_e247e2.sql', '127.0.0.1'),
('61', NULL, 'CLI Test Action Invalid Actor', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:33:53', NULL, 'Details test', '127.0.0.1'),
('62', NULL, 'System Automated Task', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:33:53', NULL, 'No actor test', '127.0.0.1'),
('63', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:33:53', '44', 'Deleted backup: qb_backup_2026-08-06_063353_cd8087.sql', '127.0.0.1'),
('64', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:33:53', '44', 'Deleted backup: qb_safety_backup_2026-08-06_063353_e247e2.sql', '127.0.0.1'),
('65', '44', 'Terminated Active Session', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:05', '44', 'Session ID/DB ID: test_sess_9dd1fd413f48c823', '127.0.0.1'),
('66', '44', 'Deleted Orphaned Files', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:05', '44', 'Requested: 1, Deleted: 1, Rejected: 0, Freed: 15 B', '127.0.0.1'),
('67', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:06', '44', 'Source File: qb_backup_2026-08-06_063405_d17a5e.sql, SHA-256: a0c31388ac34355ecafb65a3b0deb90eaf6369cab13a4314daeeba0db983141a, Safety Backup: qb_safety_backup_2026-08-06_063405_83e10e.sql', '127.0.0.1'),
('68', NULL, 'CLI Test Action Invalid Actor', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:06', NULL, 'Details test', '127.0.0.1'),
('69', NULL, 'System Automated Task', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:06', NULL, 'No actor test', '127.0.0.1'),
('70', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:06', '44', 'Deleted backup: qb_backup_2026-08-06_063405_d17a5e.sql', '127.0.0.1'),
('71', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:06', '44', 'Deleted backup: qb_safety_backup_2026-08-06_063405_83e10e.sql', '127.0.0.1'),
('72', NULL, 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:20', NULL, 'Type: subjects, Imported: 1 rows, Skipped Invalid: 1', '127.0.0.1'),
('73', NULL, 'Bulk Import Completed', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:20', NULL, 'Type: students, Imported: 1 rows, Skipped Invalid: 0', '127.0.0.1'),
('74', '44', 'Terminated Active Session', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:20', '44', 'Session ID/DB ID: test_sess_0d9bfd7ee65d46d4', '127.0.0.1'),
('75', '44', 'Deleted Orphaned Files', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:21', '44', 'Requested: 1, Deleted: 1, Rejected: 0, Freed: 15 B', '127.0.0.1'),
('76', '44', 'Restored Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:21', '44', 'Source File: qb_backup_2026-08-06_063421_8e08b8.sql, SHA-256: fe8379a48027f761d189efcc4eef3a149006ab150cf7c6702493ef3cfd7fd25b, Safety Backup: qb_safety_backup_2026-08-06_063421_93e5fe.sql', '127.0.0.1'),
('77', NULL, 'CLI Test Action Invalid Actor', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:21', NULL, 'Details test', '127.0.0.1'),
('78', NULL, 'System Automated Task', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:21', NULL, 'No actor test', '127.0.0.1'),
('79', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:21', '44', 'Deleted backup: qb_backup_2026-08-06_063421_8e08b8.sql', '127.0.0.1'),
('80', '44', 'Deleted Database Backup', 'system', '0', NULL, NULL, NULL, '2026-08-06 14:34:21', '44', 'Deleted backup: qb_safety_backup_2026-08-06_063421_93e5fe.sql', '127.0.0.1');

-- --------------------------------------------------------
-- Table structure for `department_objectives`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `department_objectives`;
CREATE TABLE `department_objectives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dept_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'BSCE',
  `dept_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Department of Civil Engineering',
  `vision` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `mission` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `quality_policy` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `target_pass_rate` decimal(5,2) NOT NULL DEFAULT '75.00',
  `target_ocr_accuracy` decimal(5,2) NOT NULL DEFAULT '90.00',
  `target_iso_compliance` decimal(5,2) NOT NULL DEFAULT '85.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `departments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dept_code` varchar(50) NOT NULL,
  `dept_name` varchar(255) NOT NULL,
  `programs` varchar(255) NOT NULL,
  `faculty_head` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_dept_code` (`dept_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `departments`

INSERT INTO `departments` (`id`, `dept_code`, `dept_name`, `programs`, `faculty_head`, `created_at`) VALUES 
('1', 'DCE', 'Department of Civil Engineering', 'BSCE (Structural, Geotechnical, Water Res., Transportation, Construction)', 'Prof. Jolas Santos', '2026-07-29 13:51:07'),
('2', 'DCS', 'Department of Computer Studies', 'BSCS, BSIT, BSIS', 'Engr. Nicole Gutierrez', '2026-07-29 13:51:07'),
('3', 'DOE', 'Department of Education & Technical Training', 'BSEd Major in Technical Education', 'Dr. Kevin Dizon', '2026-07-29 13:51:07');

-- --------------------------------------------------------
-- Table structure for `exam_assignments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exam_assignments`;
CREATE TABLE `exam_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `section_id` int DEFAULT NULL,
  `assigned_by` int NOT NULL,
  `available_from` datetime DEFAULT NULL,
  `available_until` datetime DEFAULT NULL,
  `max_attempts` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assignments_exam` (`exam_id`),
  KEY `idx_assignments_student` (`student_id`),
  KEY `idx_assignments_section` (`section_id`),
  KEY `idx_assignments_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_assignments_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `exam_questions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exam_questions`;
CREATE TABLE `exam_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `question_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `question_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'multiple_choice',
  `option_a` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_b` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_c` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_d` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `options_json` json DEFAULT NULL,
  `matching_pairs` json DEFAULT NULL,
  `formula_latex` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `rubric_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `tolerance` decimal(10,4) DEFAULT '0.0500',
  `expected_unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `points` int NOT NULL DEFAULT '1',
  `partial_credit_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `correct_answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `explanation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `difficulty` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'medium',
  `topic` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lesson_id` int DEFAULT NULL,
  `source_review_required` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_questions_exam` (`exam_id`),
  CONSTRAINT `fk_questions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1991 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `exam_questions`

INSERT INTO `exam_questions` (`id`, `exam_id`, `question_text`, `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `options_json`, `matching_pairs`, `formula_latex`, `rubric_json`, `tolerance`, `expected_unit`, `points`, `partial_credit_enabled`, `correct_answer`, `explanation`, `difficulty`, `topic`, `lesson_id`, `source_review_required`) VALUES 
('101', '10', 'What is the standard minimum concrete cover for reinforced concrete beams exposed to soil?', 'multiple_choice', '75 mm', '50 mm', '40 mm', '25 mm', NULL, NULL, NULL, NULL, '0.0500', NULL, '1', '0', '75 mm', NULL, 'medium', NULL, NULL, '0'),
('102', '10', 'Under the National Structural Code of the Philippines (NSCP 2015), flexural strength reduction factor phi for tension-controlled sections is 0.90.', 'true_false', 'true', 'false', NULL, NULL, NULL, NULL, NULL, NULL, '0.0500', NULL, '1', '0', 'true', NULL, 'medium', NULL, NULL, '0'),
('103', '10', 'Calculate the nominal shear capacity of a rectangular concrete beam with b = 250mm, d = 400mm, fc\' = 28 MPa.', 'multiple_choice', '88.36 kN', '95.20 kN', '102.50 kN', '74.10 kN', NULL, NULL, NULL, NULL, '0.0500', NULL, '1', '0', '88.36 kN', NULL, 'medium', NULL, NULL, '0');

-- --------------------------------------------------------
-- Table structure for `exam_schedules`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exam_schedules`;
CREATE TABLE `exam_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `section` varchar(50) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `duration_minutes` int NOT NULL DEFAULT '60',
  `room` varchar(100) DEFAULT NULL,
  `remarks` text,
  `semester_id` int DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table structure for `exam_submissions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exam_submissions`;
CREATE TABLE `exam_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `exam_id` int DEFAULT NULL,
  `student_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `exam_title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `upload_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'scanned',
  `correct_count` int NOT NULL DEFAULT '0',
  `wrong_count` int NOT NULL DEFAULT '0',
  `total_score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_possible_score` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_items` int NOT NULL DEFAULT '0',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Fail',
  `raw_ocr_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `ocr_confidence` decimal(5,2) NOT NULL DEFAULT '0.00',
  `ocr_status` enum('pending','processing','completed','manual_review_required','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
  `ocr_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `suggested_manual_review` tinyint(1) NOT NULL DEFAULT '0',
  `page_count` int NOT NULL DEFAULT '1',
  `evaluation_result` json DEFAULT NULL,
  `teacher_override_log` json DEFAULT NULL,
  `review_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending_review',
  `reviewed_by` int DEFAULT NULL,
  `teacher_remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `uploaded_file_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `original_ocr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `corrected_ocr_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `processed_at` datetime DEFAULT NULL,
  `extraction_mode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'image_ocr',
  `per_page_ocr_metadata` json DEFAULT NULL,
  `processing_duration` decimal(8,2) DEFAULT '0.00',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `qualification_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `attempt_number` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_submissions_teacher` (`teacher_id`),
  KEY `idx_submissions_student` (`student_id`),
  KEY `idx_submissions_exam` (`exam_id`),
  KEY `idx_submissions_review_status` (`review_status`),
  KEY `idx_submissions_student_status` (`student_id`,`review_status`),
  CONSTRAINT `fk_submissions_exam` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_submissions_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1309 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `exam_submissions`

INSERT INTO `exam_submissions` (`id`, `teacher_id`, `student_id`, `exam_id`, `student_name`, `exam_title`, `upload_type`, `correct_count`, `wrong_count`, `total_score`, `total_possible_score`, `total_items`, `percentage`, `status`, `raw_ocr_data`, `ocr_text`, `ocr_confidence`, `ocr_status`, `ocr_error`, `suggested_manual_review`, `page_count`, `evaluation_result`, `teacher_override_log`, `review_status`, `reviewed_by`, `teacher_remarks`, `reviewed_at`, `published_at`, `created_at`, `file_path`, `original_filename`, `uploaded_file_hash`, `original_ocr_text`, `corrected_ocr_text`, `processed_at`, `extraction_mode`, `per_page_ocr_metadata`, `processing_duration`, `is_demo`, `qualification_status`, `attempt_number`) VALUES 
('500', '12', '11', '10', 'Ashley Nicole Gutierrez', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'online', '3', '0', '3.00', '3.00', '3', '100.00', 'Pass', NULL, NULL, '0.00', 'completed', NULL, '0', '1', NULL, NULL, 'published', NULL, NULL, NULL, '2026-08-04 15:05:59', '2026-08-04 15:05:59', NULL, NULL, NULL, NULL, NULL, NULL, 'image_ocr', NULL, '0.00', '1', 'pending', '1'),
('501', '12', '20', '10', 'John Mark Santos', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'scanned', '2', '1', '2.00', '3.00', '3', '66.67', 'Fail', NULL, NULL, '0.00', 'completed', NULL, '0', '1', NULL, NULL, 'pending_review', NULL, NULL, NULL, NULL, '2026-08-04 15:05:59', NULL, NULL, NULL, NULL, NULL, NULL, 'image_ocr', NULL, '0.00', '1', 'pending', '1');

-- --------------------------------------------------------
-- Table structure for `exams`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `exams`;
CREATE TABLE `exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subject` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `specialization` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Structural Engineering',
  `term` enum('Prelim','Midterm','Finals') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Prelim',
  `difficulty` enum('easy','medium','hard') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'medium',
  `time_limit` int DEFAULT '60',
  `total_items` int DEFAULT '0',
  `passing_percentage` decimal(5,2) NOT NULL DEFAULT '75.00',
  `status` enum('draft','active','closed','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `available_from` datetime DEFAULT NULL,
  `available_until` datetime DEFAULT NULL,
  `max_attempts` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `ai_metadata` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `lesson_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `generation_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'completed',
  `generation_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `prompt_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'v1.0',
  `ai_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `exam_category` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'regular',
  `qualifying_passing_percentage` decimal(5,2) DEFAULT '75.00',
  `qualifying_max_attempts` int DEFAULT '1',
  `qualifying_year_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'All Year Levels',
  `qualifying_program` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'All Programs',
  `qualifying_is_required` tinyint(1) DEFAULT '1',
  `qualifying_unlock_date` datetime DEFAULT NULL,
  `qualifying_deadline` datetime DEFAULT NULL,
  `covered_periods` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_lesson_count` int DEFAULT '0',
  `generation_source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `generation_batch_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_exams_teacher` (`teacher_id`),
  KEY `idx_exams_status` (`status`),
  CONSTRAINT `fk_exams_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1354 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `exams`

INSERT INTO `exams` (`id`, `teacher_id`, `title`, `subject`, `specialization`, `term`, `difficulty`, `time_limit`, `total_items`, `passing_percentage`, `status`, `available_from`, `available_until`, `max_attempts`, `created_at`, `updated_at`, `ai_metadata`, `lesson_ids`, `generation_status`, `generation_error`, `prompt_version`, `ai_model`, `created_by`, `is_demo`, `exam_category`, `qualifying_passing_percentage`, `qualifying_max_attempts`, `qualifying_year_level`, `qualifying_program`, `qualifying_is_required`, `qualifying_unlock_date`, `qualifying_deadline`, `covered_periods`, `source_lesson_count`, `generation_source_type`, `generation_batch_id`) VALUES 
('10', '12', 'Civil Engineering Board Exam Review - Structural Design & Construction', 'Structural Engineering', 'Structural Engineering', 'Prelim', 'medium', '60', '3', '75.00', 'active', NULL, NULL, '1', '2026-08-04 15:05:59', NULL, NULL, NULL, 'completed', NULL, 'v1.0', NULL, '12', '1', 'regular', '75.00', '1', 'All Year Levels', 'All Programs', '1', NULL, NULL, NULL, '0', NULL, NULL);

-- --------------------------------------------------------
-- Table structure for `generated_question_sources`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `generated_question_sources`;
CREATE TABLE `generated_question_sources` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question_id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `academic_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source_topic` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `source_confidence` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'high',
  `source_review_required` tinyint(1) DEFAULT '0',
  `source_verified_by` int DEFAULT NULL,
  `source_verified_at` timestamp NULL DEFAULT NULL,
  `source_verification_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_question_lesson` (`question_id`,`lesson_id`),
  KEY `question_id` (`question_id`),
  KEY `lesson_id` (`lesson_id`)
) ENGINE=InnoDB AUTO_INCREMENT=735 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `iso_evaluations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `iso_evaluations`;
CREATE TABLE `iso_evaluations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evaluation_date` date NOT NULL,
  `evaluated_by` int NOT NULL,
  `section_id` int DEFAULT NULL,
  `total_students` int NOT NULL DEFAULT '0',
  `passed_students` int NOT NULL DEFAULT '0',
  `pass_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `iso_compliant` tinyint(1) NOT NULL DEFAULT '0',
  `audit_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_iso_evaluated_by` (`evaluated_by`),
  CONSTRAINT `fk_iso_evaluated_by` FOREIGN KEY (`evaluated_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `lesson_materials`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `lesson_materials`;
CREATE TABLE `lesson_materials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `subject` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `file_size` int NOT NULL,
  `lesson_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `processing_status` enum('pending','processing','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
  `processing_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `word_count` int NOT NULL DEFAULT '0',
  `page_count` int NOT NULL DEFAULT '1',
  `extracted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `stored_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `academic_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
  `semester` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_year` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `year_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `program` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_lessons_teacher` (`teacher_id`),
  CONSTRAINT `fk_lessons_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3795 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `lesson_materials`

INSERT INTO `lesson_materials` (`id`, `teacher_id`, `subject`, `title`, `file_name`, `file_path`, `file_type`, `file_size`, `lesson_text`, `processing_status`, `processing_error`, `word_count`, `page_count`, `extracted_at`, `created_at`, `mime_type`, `original_filename`, `stored_filename`, `is_demo`, `academic_period`, `semester`, `school_year`, `year_level`, `program`) VALUES 
('10', '12', 'Structural Engineering', 'Structural Steel & Reinforced Concrete Design Fundamentals', 'demo_structural_steel.txt', 'teacher/uploads/demo_structural_steel.txt', 'txt', '1024', 'Reinforced concrete flexural design relies on ultimate limit state analysis and steel tensile reinforcement capacity.', 'completed', NULL, '150', '1', NULL, '2026-08-04 15:05:59', NULL, 'demo_structural_steel.txt', 'demo_structural_steel.txt', '1', 'general', NULL, NULL, NULL, NULL),
('1319', '12', 'Soil Mechanics', 'Security Test Lesson A', 'sec_a.pdf', 'uploads/sec_a.pdf', 'pdf', '2048', 'Lecture content A', 'completed', NULL, '0', '1', NULL, '2026-08-04 21:56:33', NULL, NULL, NULL, '0', 'prelim', '1st Semester', '2025-2026', '4th Year', 'BSCE'),
('1543', '10', 'Soil Mechanics', 'E2E General Soil Mechanics Chapter', 'e2e_general.pdf', 'uploads/e2e_general.pdf', 'pdf', '2048', 'Comprehensive lecture content covering soil mechanics, effective stress, shear strength, and foundation design for General exam preparation.', 'completed', NULL, '0', '1', NULL, '2026-08-05 00:10:56', NULL, NULL, NULL, '0', 'general', '1st Semester', '2025-2026', '4th Year', 'BSCE'),
('1544', '10', 'Soil Mechanics', 'E2E Prelim Soil Mechanics Chapter', 'e2e_prelim.pdf', 'uploads/e2e_prelim.pdf', 'pdf', '2048', 'Comprehensive lecture content covering soil mechanics, effective stress, shear strength, and foundation design for Prelim exam preparation.', 'completed', NULL, '0', '1', NULL, '2026-08-05 00:10:56', NULL, NULL, NULL, '0', 'prelim', '1st Semester', '2025-2026', '4th Year', 'BSCE'),
('1545', '10', 'Soil Mechanics', 'E2E Midterm Soil Mechanics Chapter', 'e2e_midterm.pdf', 'uploads/e2e_midterm.pdf', 'pdf', '2048', 'Comprehensive lecture content covering soil mechanics, effective stress, shear strength, and foundation design for Midterm exam preparation.', 'completed', NULL, '0', '1', NULL, '2026-08-05 00:10:56', NULL, NULL, NULL, '0', 'midterm', '1st Semester', '2025-2026', '4th Year', 'BSCE'),
('1546', '10', 'Soil Mechanics', 'E2E Finals Soil Mechanics Chapter', 'e2e_finals.pdf', 'uploads/e2e_finals.pdf', 'pdf', '2048', 'Comprehensive lecture content covering soil mechanics, effective stress, shear strength, and foundation design for Finals exam preparation.', 'completed', NULL, '0', '1', NULL, '2026-08-05 00:10:56', NULL, NULL, NULL, '0', 'finals', '1st Semester', '2025-2026', '4th Year', 'BSCE');

-- --------------------------------------------------------
-- Table structure for `notifications`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table structure for `school_years`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `school_years`;
CREATE TABLE `school_years` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_year` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `school_year` (`school_year`)
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `school_years`

INSERT INTO `school_years` (`id`, `school_year`, `start_date`, `end_date`, `status`, `created_at`) VALUES 
('59', '2025-2026', '2025-06-01', '2026-05-31', 'active', '2026-08-06 14:26:02');

-- --------------------------------------------------------
-- Table structure for `sections`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sections`;
CREATE TABLE `sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int DEFAULT NULL,
  `section_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `course_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `academic_year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `section_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `adviser_id` int DEFAULT NULL,
  `capacity` int NOT NULL DEFAULT '40',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `course` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `school_year_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sections_teacher` (`teacher_id`),
  CONSTRAINT `fk_sections_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `sections`

INSERT INTO `sections` (`id`, `teacher_id`, `section_name`, `course_name`, `academic_year`, `created_at`, `updated_at`, `section_code`, `adviser_id`, `capacity`, `status`, `course`, `school_year_id`) VALUES 
('3', '10', 'CE-TEST-SEC', 'BSCE', '2025-2026', '2026-08-06 13:43:18', NULL, 'CE-TEST-SEC', '10', '35', 'active', NULL, NULL);

-- --------------------------------------------------------
-- Table structure for `semesters`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `school_year_id` int NOT NULL,
  `semester_name` varchar(50) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'inactive',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sy_semester` (`school_year_id`,`semester_name`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `semesters`

INSERT INTO `semesters` (`id`, `school_year_id`, `semester_name`, `status`, `created_at`) VALUES 
('44', '59', 'First Semester', 'active', '2026-08-06 14:26:02');

-- --------------------------------------------------------
-- Table structure for `student_details`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `student_details`;
CREATE TABLE `student_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `student_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `course` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'BS Civil Engineering',
  `section` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'BSCE-4A',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `year_level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '3rd Year',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_student_details_user` (`user_id`),
  CONSTRAINT `fk_student_details_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=283 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `student_details`

INSERT INTO `student_details` (`id`, `user_id`, `student_number`, `course`, `section`, `created_at`, `year_level`) VALUES 
('3', '11', '23-2149184', 'BSCE', 'A', '2026-08-02 18:29:22', '4'),
('188', '20', '23-2149800', 'BSCE', 'Section A', '2026-08-04 15:05:59', '4th Year'),
('263', '26', '23-1785995039280', 'BSCE', 'CE-4A', '2026-08-06 13:44:00', '3rd Year'),
('264', '27', '23-1785995045844', 'BSCE', 'CE-4A', '2026-08-06 13:44:05', '3rd Year'),
('265', '28', '23-1785995051489', 'BSCE', 'CE-4A', '2026-08-06 13:44:11', '3rd Year'),
('266', '29', '23-1785995067660', 'BSCE', 'CE-4A', '2026-08-06 13:44:28', '3rd Year');

-- --------------------------------------------------------
-- Table structure for `student_requests`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `student_requests`;
CREATE TABLE `student_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `section_id` int DEFAULT NULL,
  `student_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `student_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `subject_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','accepted','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_requests_teacher_status` (`teacher_id`,`status`),
  KEY `idx_requests_student` (`student_id`),
  CONSTRAINT `fk_requests_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `students`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `section_id` int NOT NULL,
  `student_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `fullname` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_students_teacher` (`teacher_id`),
  KEY `idx_students_section` (`section_id`),
  CONSTRAINT `fk_students_section` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_students_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `subjects`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `subjects`

INSERT INTO `subjects` (`id`, `code`, `title`, `created_at`) VALUES 
('1', 'CE-401', 'Structural Engineering', '2026-08-04 11:31:11'),
('2', 'CE-402', 'Geotechnical Engineering & Foundation Design', '2026-08-04 15:05:59');

-- --------------------------------------------------------
-- Table structure for `submission_answers`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_answers`;
CREATE TABLE `submission_answers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `question_id` int NOT NULL,
  `student_answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `is_correct` tinyint(1) NOT NULL DEFAULT '0',
  `points_awarded` decimal(10,2) NOT NULL DEFAULT '0.00',
  `points_possible` decimal(10,2) NOT NULL DEFAULT '1.00',
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `exam_id` int DEFAULT NULL,
  `student_id` int DEFAULT NULL,
  `correct_answer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `awarded_points` decimal(5,2) DEFAULT '0.00',
  `max_points` decimal(5,2) DEFAULT '1.00',
  `evaluation_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unanswered',
  `evaluation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `confidence` decimal(5,2) DEFAULT '100.00',
  `requires_review` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_sub_question` (`submission_id`,`question_id`),
  KEY `fk_sub_answers_question` (`question_id`),
  CONSTRAINT `fk_sub_answers_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_answers_submission` FOREIGN KEY (`submission_id`) REFERENCES `exam_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3849 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `submission_answers`

INSERT INTO `submission_answers` (`id`, `submission_id`, `question_id`, `student_answer`, `is_correct`, `points_awarded`, `points_possible`, `feedback`, `created_at`, `exam_id`, `student_id`, `correct_answer`, `awarded_points`, `max_points`, `evaluation_status`, `evaluation_reason`, `confidence`, `requires_review`) VALUES 
('3375', '500', '101', '75 mm', '0', '0.00', '1.00', NULL, '2026-08-04 15:05:59', '10', '11', '75 mm', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('3376', '500', '102', 'true', '0', '0.00', '1.00', NULL, '2026-08-04 15:05:59', '10', '11', 'true', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('3377', '500', '103', '88.36 kN', '0', '0.00', '1.00', NULL, '2026-08-04 15:05:59', '10', '11', '88.36 kN', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('3378', '501', '101', '75 mm', '0', '0.00', '1.00', NULL, '2026-08-04 15:05:59', '10', '20', '75 mm', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('3379', '501', '102', 'true', '0', '0.00', '1.00', NULL, '2026-08-04 15:05:59', '10', '20', 'true', '1.00', '1.00', 'correct', NULL, '100.00', '0'),
('3380', '501', '103', '95.20 kN', '0', '0.00', '1.00', NULL, '2026-08-04 15:05:59', '10', '20', '88.36 kN', '0.00', '1.00', 'incorrect', NULL, '100.00', '0');

-- --------------------------------------------------------
-- Table structure for `submission_reprocessing_history`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_reprocessing_history`;
CREATE TABLE `submission_reprocessing_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `previous_ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `previous_item_scores` json DEFAULT NULL,
  `new_item_scores` json DEFAULT NULL,
  `previous_total` decimal(5,2) DEFAULT '0.00',
  `new_total` decimal(5,2) DEFAULT '0.00',
  `actor_id` int NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `submission_score_overrides`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_score_overrides`;
CREATE TABLE `submission_score_overrides` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `old_score` decimal(10,2) NOT NULL,
  `new_score` decimal(10,2) NOT NULL,
  `reviewer_id` int NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `question_id` int DEFAULT NULL,
  `old_student_answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `new_student_answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `old_points` decimal(5,2) DEFAULT '0.00',
  `new_points` decimal(5,2) DEFAULT '0.00',
  `old_correct_answer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_correct_answer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_overrides_submission` (`submission_id`),
  KEY `idx_overrides_reviewer` (`reviewer_id`),
  CONSTRAINT `fk_overrides_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_overrides_submission` FOREIGN KEY (`submission_id`) REFERENCES `exam_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `submission_snapshots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_snapshots`;
CREATE TABLE `submission_snapshots` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `review_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `total_score` decimal(5,2) DEFAULT '0.00',
  `percentage` decimal(5,2) DEFAULT '0.00',
  `correct_count` int DEFAULT '0',
  `wrong_count` int DEFAULT '0',
  `ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `corrected_ocr_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `evaluation_result` json DEFAULT NULL,
  `item_answers` json DEFAULT NULL,
  `reopened_by` int NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `submission_status_history`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `submission_status_history`;
CREATE TABLE `submission_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `submission_id` int NOT NULL,
  `previous_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `actor_id` int NOT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `submission_id` (`submission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `system_settings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `system_settings`

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES 
('maintenance_mode', 'off', '2026-08-06 14:34:21'),
('passing_percentage', '82.50', '2026-08-06 13:43:31');

-- --------------------------------------------------------
-- Table structure for `teacher_subject_assignments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `teacher_subject_assignments`;
CREATE TABLE `teacher_subject_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `subject` varchar(150) NOT NULL,
  `section_id` int NOT NULL,
  `school_year_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`teacher_id`,`subject`,`section_id`,`school_year_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `teacher_subject_assignments`

INSERT INTO `teacher_subject_assignments` (`id`, `teacher_id`, `subject`, `section_id`, `school_year_id`, `status`, `created_at`) VALUES 
('1', '10', 'Geotechnical Engineering', '3', '10', 'active', '2026-08-06 13:43:18'),
('2', '10', 'Geotechnical Engineering', '3', '12', 'active', '2026-08-06 13:43:31'),
('3', '10', 'Geotechnical Engineering', '3', '14', 'active', '2026-08-06 13:43:40'),
('4', '10', 'Geotechnical Engineering', '3', '16', 'active', '2026-08-06 13:43:53'),
('5', '10', 'Geotechnical Engineering', '3', '18', 'active', '2026-08-06 13:43:59'),
('6', '10', 'Geotechnical Engineering', '3', '20', 'active', '2026-08-06 13:44:05'),
('7', '10', 'Geotechnical Engineering', '3', '22', 'active', '2026-08-06 13:44:11'),
('8', '10', 'Geotechnical Engineering', '3', '24', 'active', '2026-08-06 13:44:27');

-- --------------------------------------------------------
-- Table structure for `used_confirmation_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `used_confirmation_tokens`;
CREATE TABLE `used_confirmation_tokens` (
  `token_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `teacher_id` int NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `teacher_id` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `user_sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` varchar(128) NOT NULL,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `login_time` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for `user_sessions`

INSERT INTO `user_sessions` (`id`, `session_id`, `user_id`, `ip_address`, `user_agent`, `login_time`, `last_activity`, `status`) VALUES 
('1', '0283b001dc7c336958a98d8e53f5cdec', '44', '127.0.0.1', 'Unknown Browser', '2026-08-06 14:19:12', '2026-08-06 14:19:12', 'terminated'),
('2', '989de34236785d2198c54fa02ae2591f', '44', '127.0.0.1', 'Unknown Browser', '2026-08-06 14:19:31', '2026-08-06 14:19:31', 'terminated');

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','teacher','student') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  `is_demo` tinyint(1) NOT NULL DEFAULT '0',
  `force_password_reset` tinyint(1) NOT NULL DEFAULT '0',
  `password_changed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_username` (`username`),
  UNIQUE KEY `idx_users_email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for `users`

INSERT INTO `users` (`id`, `fullname`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`, `status`, `is_demo`, `force_password_reset`, `password_changed_at`) VALUES 
('10', 'Russel Gregorio', 'Russel', 'russel@questbank.edu.ph', '$2y$12$01PjJodYEpdTR5l/D.MRdei1QJC1zkB8rU7u6W5Tl9AzU0BMm516y', 'teacher', '2026-08-02 18:29:22', '2026-08-06 14:34:20', 'active', '0', '0', NULL),
('11', 'Ashley Nicole Gutierrez', 'Nicole', 'nikol@gmail.com', '$2y$12$eDOE.fWOX4l1oWFQK0dY9eXx1xbLpEbXn.6hfgMyBBNGv12h7WZ6y', 'student', '2026-08-02 18:29:22', '2026-08-06 14:23:58', 'active', '0', '0', NULL),
('12', 'jolas', 'lasjo', 'lasjo@gmail.com', '$2y$12$eDOE.fWOX4l1oWFQK0dY9eXx1xbLpEbXn.6hfgMyBBNGv12h7WZ6y', 'teacher', '2026-08-02 18:34:53', '2026-08-06 14:23:58', 'active', '0', '0', NULL),
('20', 'John Mark Santos', 'jmsantos', 'jmsantos@holycross.edu.ph', '$2y$12$kNiudTZvtElbLktwIASCv.cODiZK3fuxIGLNwwMP6ERrf5bmYA/6S', 'student', '2026-08-04 15:05:59', NULL, 'active', '1', '0', NULL),
('21', 'Other Teacher', 'otherteacher', 'otherteacher@test.com', 'hash', 'teacher', '2026-08-04 20:47:48', NULL, 'active', '0', '0', NULL),
('22', 'Professor Smith', 'prof_smith', 'smith@questbank.edu.ph', '$2y$12$01PjJodYEpdTR5l/D.MRdei1QJC1zkB8rU7u6W5Tl9AzU0BMm516y', 'teacher', '2026-08-05 00:11:02', '2026-08-06 14:34:20', 'active', '0', '0', NULL),
('26', 'P4 Test Student', 'p4student_1785995039280_939', 'p4student_1785995039280@questbank.edu.ph', '$2y$12$AJZ4I.VN8Tj.PTZnUrbP1.7b6MMtjvd2W5VJ5Lp2J/DTiOmLHNIDm', 'student', '2026-08-06 13:44:00', NULL, 'active', '0', '0', NULL),
('27', 'P4 Test Student', 'p4student_1785995045844_646', 'p4student_1785995045844@questbank.edu.ph', '$2y$12$xhICBW8hwtUACLhKllH0hupWx32h0uPf.ZudaynX6GU0SKTcIbEMO', 'student', '2026-08-06 13:44:05', NULL, 'active', '0', '0', NULL),
('28', 'P4 Test Student', 'p4student_1785995051489_176', 'p4student_1785995051489@questbank.edu.ph', '$2y$12$uCgVYzyKpNqZku3cXE5ReemlH1jjw339tG4R2vWXKxvspo/ex2CNa', 'student', '2026-08-06 13:44:11', NULL, 'active', '0', '0', NULL),
('29', 'P4 Test Student', 'p4student_1785995067660_972', 'p4student_1785995067660@questbank.edu.ph', '$2y$12$i4BoKvn72NLdvPtt2Yg7hOEk8JONtO9/ZBuGdQ3P6Gsfjk7Ndlj/e', 'student', '2026-08-06 13:44:28', NULL, 'active', '0', '0', NULL),
('44', 'P5 Admin', 'temp_p5_admin', 'temp_p5_admin@questbank.edu.ph', 'pass', 'admin', '2026-08-06 14:19:05', '2026-08-06 14:19:31', 'active', '0', '0', NULL);

SET FOREIGN_KEY_CHECKS=1;
COMMIT;
