-- MySQL dump 10.13  Distrib 9.7.1, for macos26.4 (arm64)
--
-- Host: localhost    Database: bankquest_db
-- ------------------------------------------------------
-- Server version	9.7.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '36173880-8a36-11f1-b84e-d2b92aa7c8f1:1-332';

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action_description` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,2,'Generated AI Exam Paper for Structural Engineering & Geotechnical Mechanics (5 Items)','2026-07-29 05:23:35'),(2,4,'Submitted online exam response for Structural Theory 1 - Prelim Quiz (Score: 9/10)','2026-07-29 04:53:35'),(4,2,'Uploaded new course lesson material: Advanced Reinforced Concrete Beam Design PDF','2026-07-29 00:38:35'),(5,4,'Scanned and evaluated Optical Answer Sheet via Groq AI Vision Engine (Score: 8/10)','2026-07-28 05:38:35'),(7,5,'Created new department \'Department of IDK\' (DOLE).','2026-07-29 05:52:19'),(8,5,'Deleted department record \'Department of IDK\'.','2026-07-29 05:52:23'),(9,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 2.21ms).','2026-07-31 03:13:23'),(10,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.72ms).','2026-07-31 03:13:23'),(11,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.63ms).','2026-07-31 03:13:23'),(12,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.45ms).','2026-07-31 03:13:23'),(13,7,'QA Audit: Teacher reviewed & published submission #5.','2026-07-31 03:13:27'),(14,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.83ms).','2026-07-31 03:13:39'),(15,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.57ms).','2026-07-31 03:13:39'),(16,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.8ms).','2026-07-31 03:13:39'),(17,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.75ms).','2026-07-31 03:13:39'),(18,7,'QA Audit: Teacher reviewed & published submission #6.','2026-07-31 03:13:43'),(19,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.67ms).','2026-07-31 03:13:53'),(20,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (23 words, 1 pages, 1.09ms).','2026-07-31 03:13:53'),(21,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (23 words, 1 pages, 0.93ms).','2026-07-31 03:13:53'),(22,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (13 words, 1 pages, 5.43ms).','2026-07-31 03:13:53'),(23,7,'QA Audit: Teacher reviewed & published submission #7.','2026-07-31 03:13:56'),(24,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 3.97ms).','2026-07-31 03:14:10'),(25,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (38 words, 1 pages, 0.94ms).','2026-07-31 03:14:17'),(26,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (23 words, 1 pages, 0.96ms).','2026-07-31 03:14:17'),(27,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (23 words, 1 pages, 0.9ms).','2026-07-31 03:14:17'),(28,7,'Successfully extracted lesson content for \'Concrete Beam Flexure\' (13 words, 1 pages, 5.74ms).','2026-07-31 03:14:17'),(29,7,'QA Audit: Teacher reviewed & published submission #8.','2026-07-31 03:14:22'),(30,7,'Successfully extracted lesson content for \'Fluid Mechanics PDF\' (13 words, 1 pages, 13.46ms).','2026-07-31 03:21:40'),(31,7,'Successfully extracted lesson content for \'Fluid Mechanics PDF\' (13 words, 1 pages, 7.53ms).','2026-07-31 03:21:53'),(32,7,'Successfully extracted lesson content for \'Fluid Mechanics PDF\' (13 words, 1 pages, 8.83ms).','2026-07-31 03:22:05'),(33,7,'Successfully extracted lesson content for \'Fluid Mechanics PDF\' (13 words, 1 pages, 7.44ms).','2026-07-31 03:22:28'),(34,7,'Successfully extracted lesson content for \'Fluid Mechanics PDF\' (13 words, 1 pages, 7.43ms).','2026-07-31 03:22:38'),(35,7,'Successfully extracted lesson content for \'Fluid Mechanics PDF\' (13 words, 1 pages, 6.92ms).','2026-07-31 03:23:01'),(36,7,'Failed to extract lesson \'Corrupted Test PDF\': Extraction resulted in empty text. The file might contain scanned images without OCR layers or password protection.','2026-07-31 03:23:06'),(37,7,'Failed to extract lesson \'Empty Test DOCX\': Invalid DOCX format: word/document.xml missing.','2026-07-31 03:23:06'),(38,7,'Failed to extract lesson \'Fake MIME PDF\': Extraction resulted in empty text. The file might contain scanned images without OCR layers or password protection.','2026-07-31 03:23:06'),(39,7,'Successfully extracted lesson content for \'Fluid Mechanics PDF\' (13 words, 1 pages, 8.97ms).','2026-07-31 03:23:14'),(40,7,'Failed to extract lesson \'Corrupted Test PDF\': Extraction resulted in empty text. The file might contain scanned images without OCR layers or password protection.','2026-07-31 03:23:19'),(41,7,'Failed to extract lesson \'Fake MIME PDF\': Extraction resulted in empty text. The file might contain scanned images without OCR layers or password protection.','2026-07-31 03:23:19');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'DCE','Department of Civil Engineering','BSCE (Structural, Geotechnical, Water Res., Transportation, Construction)','Prof. Jolas Santos','2026-07-29 05:51:07'),(2,'DCS','Department of Computer Studies','BSCS, BSIT, BSIS','Engr. Nicole Gutierrez','2026-07-29 05:51:07'),(3,'DOE','Department of Education & Technical Training','BSEd Major in Technical Education','Dr. Kevin Dizon','2026-07-29 05:51:07');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_questions`
--

DROP TABLE IF EXISTS `exam_questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exam_id` int NOT NULL,
  `question_text` text COLLATE utf8mb4_general_ci NOT NULL,
  `question_type` enum('multiple_choice','true_false','identification','fill_in_the_blank','matching_type','problem_solving','math_formula') COLLATE utf8mb4_general_ci NOT NULL,
  `option_a` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_b` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_c` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `option_d` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `correct_answer` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `matching_pairs` json DEFAULT NULL,
  `formula_latex` text COLLATE utf8mb4_general_ci,
  `points` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `exam_id` (`exam_id`),
  CONSTRAINT `exam_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_questions`
--

LOCK TABLES `exam_questions` WRITE;
/*!40000 ALTER TABLE `exam_questions` DISABLE KEYS */;
INSERT INTO `exam_questions` VALUES (1,1,'What is the primary factor that affects the flexural strength of a reinforced concrete beam?','multiple_choice','Concrete compressive strength fc','Steel reinforcement area As','Steel yield strength fy','All of the above','d',NULL,NULL,1),(2,1,'Identify the formula that represents the flexural strength of a reinforced concrete beam','identification',NULL,NULL,NULL,NULL,'Mn = As * fy * (d - a/2)',NULL,NULL,1),(3,1,'A reinforced concrete beam has a steel reinforcement area As of 4.2 square inches, a concrete compressive strength fc of 4000 psi, and a steel yield strength fy of 60000 psi. What is the primary factor that the engineer should consider to increase the flexural strength of the beam?','multiple_choice','Increase the steel reinforcement area As','Increase the concrete compressive strength fc','Increase the steel yield strength fy','Decrease the steel reinforcement area As','a',NULL,NULL,1),(4,1,'What is the term for the distance from the extreme compression fiber to the centroid of the longitudinal tension reinforcement?','identification',NULL,NULL,NULL,NULL,'d',NULL,NULL,1),(5,1,'An engineer is designing a reinforced concrete beam with a rectangular cross-section. The beam has a width of 12 inches and a height of 24 inches. If the steel reinforcement area As is 3.5 square inches and the concrete compressive strength fc is 3500 psi, what is the most critical factor that the engineer should consider to ensure the beam can withstand the expected flexural loads?','multiple_choice','Increasing the steel yield strength fy','Increasing the concrete compressive strength fc','Increasing the steel reinforcement area As','Verifying the beam\'s shear capacity','c',NULL,NULL,1),(6,100,'What is the standard unit of Soil Shear Strength?','multiple_choice','kPa','kN','m/s','Joules','kPa',NULL,NULL,1),(7,100,'Terzaghi\'s Bearing Capacity theory applies primarily to shallow foundations.','multiple_choice','True','False','N/A','N/A','True',NULL,NULL,1),(8,100,'Which soil classification has the smallest particle grain size?','multiple_choice','Gravel','Sand','Silt','Clay','Clay',NULL,NULL,1),(9,100,'Darcy\'s Law governs fluid flow velocity through porous soil media.','multiple_choice','True','False','N/A','N/A','True',NULL,NULL,1),(10,100,'Consolidation settlement in clay soils occurs over extended periods of time.','multiple_choice','True','False','N/A','N/A','True',NULL,NULL,1),(11,101,'What is the flexural formula for M_u?','multiple_choice',NULL,NULL,NULL,NULL,'M_u = phi * M_n',NULL,NULL,1),(12,101,'Concrete strength increases with water-cement ratio.','true_false',NULL,NULL,NULL,NULL,'False',NULL,NULL,1),(13,101,'Identify the formula parameter for b_w.','identification',NULL,NULL,NULL,NULL,'Beam Web Width',NULL,NULL,1),(14,101,'Shear capacity V_c = _____ * sqrt(f_c) * b_w * d.','fill_in_the_blank',NULL,NULL,NULL,NULL,'0.17',NULL,NULL,1),(15,101,'Match structural elements to definition.','matching_type',NULL,NULL,NULL,NULL,'1-A, 2-B',NULL,NULL,1),(16,101,'Calculate moment capacity for b=300mm, d=500mm.','problem_solving',NULL,NULL,NULL,NULL,'250 kN-m',NULL,NULL,2),(17,101,'Express stress formula in LaTeX.','math_formula',NULL,NULL,NULL,NULL,'\\sigma = \\frac{P}{A}',NULL,NULL,2),(18,102,'What is the flexural formula for M_u?','multiple_choice',NULL,NULL,NULL,NULL,'M_u = phi * M_n',NULL,NULL,1),(19,102,'Concrete strength increases with water-cement ratio.','true_false',NULL,NULL,NULL,NULL,'False',NULL,NULL,1),(20,102,'Identify the formula parameter for b_w.','identification',NULL,NULL,NULL,NULL,'Beam Web Width',NULL,NULL,1),(21,102,'Shear capacity V_c = _____ * sqrt(f_c) * b_w * d.','fill_in_the_blank',NULL,NULL,NULL,NULL,'0.17',NULL,NULL,1),(22,102,'Match structural elements to definition.','matching_type',NULL,NULL,NULL,NULL,'1-A, 2-B',NULL,NULL,1),(23,102,'Calculate moment capacity for b=300mm, d=500mm.','problem_solving',NULL,NULL,NULL,NULL,'250 kN-m',NULL,NULL,2),(24,102,'Express stress formula in LaTeX.','math_formula',NULL,NULL,NULL,NULL,'\\sigma = \\frac{P}{A}',NULL,NULL,2),(25,103,'What is the flexural formula for M_u?','multiple_choice',NULL,NULL,NULL,NULL,'M_u = phi * M_n',NULL,NULL,1),(26,103,'Concrete strength increases with water-cement ratio.','true_false',NULL,NULL,NULL,NULL,'False',NULL,NULL,1),(27,103,'Identify the formula parameter for b_w.','identification',NULL,NULL,NULL,NULL,'Beam Web Width',NULL,NULL,1),(28,103,'Shear capacity V_c = _____ * sqrt(f_c) * b_w * d.','fill_in_the_blank',NULL,NULL,NULL,NULL,'0.17',NULL,NULL,1),(29,103,'Match structural elements to definition.','matching_type',NULL,NULL,NULL,NULL,'1-A, 2-B',NULL,NULL,1),(30,103,'Calculate moment capacity for b=300mm, d=500mm.','problem_solving',NULL,NULL,NULL,NULL,'250 kN-m',NULL,NULL,2),(31,103,'Express stress formula in LaTeX.','math_formula',NULL,NULL,NULL,NULL,'\\sigma = \\frac{P}{A}',NULL,NULL,2),(32,104,'What is the flexural formula for M_u?','multiple_choice',NULL,NULL,NULL,NULL,'M_u = phi * M_n',NULL,NULL,1),(33,104,'Concrete strength increases with water-cement ratio.','true_false',NULL,NULL,NULL,NULL,'False',NULL,NULL,1),(34,104,'Identify the formula parameter for b_w.','identification',NULL,NULL,NULL,NULL,'Beam Web Width',NULL,NULL,1),(35,104,'Shear capacity V_c = _____ * sqrt(f_c) * b_w * d.','fill_in_the_blank',NULL,NULL,NULL,NULL,'0.17',NULL,NULL,1),(36,104,'Match structural elements to definition.','matching_type',NULL,NULL,NULL,NULL,'1-A, 2-B',NULL,NULL,1),(37,104,'Calculate moment capacity for b=300mm, d=500mm.','problem_solving',NULL,NULL,NULL,NULL,'250 kN-m',NULL,NULL,2),(38,104,'Express stress formula in LaTeX.','math_formula',NULL,NULL,NULL,NULL,'\\sigma = \\frac{P}{A}',NULL,NULL,2),(39,105,'What is the primary factor that affects the energy loss calculation in a hydraulic system?','multiple_choice','Friction factor','Velocity of flow','Density of fluid','Viscosity of fluid','opt_a',NULL,NULL,1),(40,105,'A rectangular channel has a width of 5 meters and a flow rate of 10 m^3/s. If the critical depth is 2 meters, what is the velocity of flow?','multiple_choice','1.0 m/s','2.0 m/s','3.0 m/s','4.0 m/s','opt_b',NULL,NULL,1),(41,105,'What is the critical depth equation for a rectangular channel?','multiple_choice','y_c = \\frac{q^2}{g}','y_c = \\frac{q}{g}','y_c = \\sqrt{\\frac{q^2}{g}}','y_c = \\frac{1}{2} * \\sqrt{\\frac{q^2}{g}}','opt_c',NULL,NULL,1),(42,105,'A hydraulic jump occurs in a rectangular channel with a width of 10 meters. The upstream depth is 1 meter and the downstream depth is 2 meters. What is the energy loss per unit weight of fluid?','multiple_choice','0.5 m','1.0 m','1.5 m','2.0 m','opt_b',NULL,NULL,1),(43,105,'What is the purpose of calculating the energy loss in a hydraulic system?','multiple_choice','To determine the pressure drop','To determine the flow rate','To determine the efficiency of the system','To determine the required pump power','opt_d',NULL,NULL,1),(44,106,'What is the primary factor that affects the energy loss calculation in a hydraulic system?','multiple_choice','Friction factor','Reynolds number','Mach number','Froude number','opt_a',NULL,NULL,1),(45,106,'What is the critical depth equation for a rectangular channel?','multiple_choice','y_c = (q^2 / g)^(1/3)','y_c = (q / g)^(1/2)','y_c = (g / q)^(1/3)','y_c = (g / q)^(1/2)','opt_a',NULL,NULL,1),(46,106,'A hydraulic jump occurs when the flow changes from','multiple_choice','Subcritical to supercritical','Supercritical to subcritical','Laminar to turbulent','Turbulent to laminar','opt_b',NULL,NULL,1),(47,106,'The energy loss due to friction in a pipe flow can be calculated using','multiple_choice','Darcy-Weisbach equation','Bernoulli\'s equation','Continuity equation','Momentum equation','opt_a',NULL,NULL,1),(48,106,'The Froude number is a dimensionless parameter that characterizes the','multiple_choice','Ratio of inertial to viscous forces','Ratio of inertial to gravitational forces','Ratio of viscous to gravitational forces','Ratio of gravitational to inertial forces','opt_b',NULL,NULL,1),(49,107,'What is the primary factor that affects the energy loss calculation in a hydraulic system?','multiple_choice','Friction','Viscosity','Velocity','Pressure','opt_a',NULL,NULL,1),(50,107,'What is the critical depth equation for a rectangular channel?','multiple_choice','y_c = (q^2 / g)^(1/3)','y_c = (q / g)^(1/2)','y_c = (g / q)^(1/3)','y_c = (g / q)^(1/2)','opt_a',NULL,NULL,1),(51,107,'A hydraulic jump occurs in a channel when the','multiple_choice','flow changes from subcritical to supercritical','flow changes from supercritical to subcritical','flow remains subcritical','flow remains supercritical','opt_b',NULL,NULL,1),(52,107,'What is the purpose of calculating the energy loss in a hydraulic system?','multiple_choice','To determine the flow rate','To determine the pressure drop','To determine the required pump power','To determine the pipe size','opt_c',NULL,NULL,1),(53,107,'The Darcy-Weisbach equation is used to calculate the','multiple_choice','head loss due to friction','head loss due to minor losses','pressure drop','flow rate','opt_a',NULL,NULL,1),(54,108,'What is the primary factor that affects the energy loss calculation in a hydraulic system?','multiple_choice','Friction','Viscosity','Velocity','Pressure','opt_a',NULL,NULL,1),(55,108,'What is the critical depth equation in open-channel flow, and what does it represent?','multiple_choice','y_c = (q^2 / g)^(1/3)','y_c = (g / q^2)^(1/3)','y_c = (g * q^2)^(1/3)','y_c = (q^2 * g)^(2/3)','opt_a',NULL,NULL,1),(56,108,'A rectangular channel has a width of 5 m and a flow rate of 10 m^3/s. What is the critical velocity of the flow, given that the acceleration due to gravity is 9.81 m/s^2?','multiple_choice','0.5 m/s','1.0 m/s','2.0 m/s','3.0 m/s','opt_c',NULL,NULL,1),(57,108,'Which of the following is a correct statement regarding the energy loss calculation in a hydraulic jump?','multiple_choice','The energy loss is directly proportional to the Froude number.','The energy loss is inversely proportional to the Froude number.','The energy loss is proportional to the difference in specific energy between the upstream and downstream flows.','The energy loss is proportional to the sum of specific energy between the upstream and downstream flows.','opt_c',NULL,NULL,1),(58,108,'What is the primary purpose of calculating the critical depth in open-channel flow?','multiple_choice','To determine the flow rate','To determine the flow velocity','To determine the transition from subcritical to supercritical flow','To determine the channel slope','opt_c',NULL,NULL,1),(59,110,'What is the primary factor that affects the energy loss calculation in a hydraulic system?','multiple_choice','Friction','Viscosity','Velocity','Pressure','opt_a',NULL,NULL,1),(60,110,'What is the critical depth equation in open-channel flow, and what does it represent?','multiple_choice','y_c = (q^2 / g)^(1/3), which represents the depth at which the flow changes from subcritical to supercritical','y_c = (q / g)^(1/2), which represents the depth at which the flow changes from laminar to turbulent','y_c = (g / q)^(1/3), which represents the depth at which the flow changes from subcritical to supercritical','y_c = (g / q^2)^(1/2), which represents the depth at which the flow changes from laminar to turbulent','opt_a',NULL,NULL,1),(61,110,'A hydraulic system consists of a pipe with a diameter of 0.5 m and a length of 1000 m. The flow rate is 0.1 m^3/s, and the friction factor is 0.02. What is the head loss due to friction in the pipe?','multiple_choice','1.23 m','2.45 m','3.67 m','4.89 m','opt_a',NULL,NULL,1),(62,110,'What is the difference between the hydraulic gradient and the energy grade line in a hydraulic system?','multiple_choice','The hydraulic gradient represents the slope of the water surface, while the energy grade line represents the slope of the total energy of the flow','The hydraulic gradient represents the slope of the total energy of the flow, while the energy grade line represents the slope of the water surface','The hydraulic gradient represents the slope of the water surface, while the energy grade line represents the slope of the pressure head','The hydraulic gradient represents the slope of the pressure head, while the energy grade line represents the slope of the water surface','opt_a',NULL,NULL,1),(63,110,'A river has a width of 50 m and a flow rate of 20 m^3/s. If the critical depth is 2.5 m, what is the critical velocity of the flow?','multiple_choice','1.41 m/s','2.83 m/s','3.54 m/s','4.27 m/s','opt_b',NULL,NULL,1),(64,111,'What is the primary cause of energy loss in a hydraulic system?','multiple_choice','Friction','Viscosity','Turbulence','All of the above','opt_d',NULL,NULL,1),(65,111,'A rectangular channel has a width of 5m and a flow rate of 10m^3/s. If the critical depth is 2m, what is the velocity of the flow?','multiple_choice','2m/s','4m/s','6m/s','8m/s','opt_b',NULL,NULL,1),(66,111,'Which of the following equations represents the critical depth equation for a rectangular channel?','multiple_choice','y_c = (q^2/(g*b^2))^(1/3)','y_c = (q/(g*b))^(1/2)','y_c = (q^2/(g*b))^(1/3)','y_c = (q^2/(g*b^2))^(2/3)','opt_a',NULL,NULL,1),(67,111,'A pipe of diameter 0.1m carries water at a flow rate of 0.01m^3/s. If the pipe is 100m long and the friction factor is 0.02, what is the head loss due to friction?','multiple_choice','0.1m','1m','5m','10m','opt_b',NULL,NULL,1),(68,111,'What is the purpose of calculating the energy loss in a hydraulic system?','multiple_choice','To determine the flow rate','To determine the pressure drop','To design the system for optimal performance','To determine the pipe size','opt_c',NULL,NULL,1);
/*!40000 ALTER TABLE `exam_questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exam_submissions`
--

DROP TABLE IF EXISTS `exam_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_submissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `student_id` int DEFAULT NULL,
  `exam_id` int DEFAULT NULL,
  `student_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `exam_title` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `upload_type` enum('image','pdf','scanned','handwritten','printed') COLLATE utf8mb4_general_ci NOT NULL,
  `correct_count` int NOT NULL DEFAULT '0',
  `wrong_count` int NOT NULL DEFAULT '0',
  `total_score` int NOT NULL DEFAULT '0',
  `total_items` int NOT NULL DEFAULT '0',
  `percentage` decimal(5,2) NOT NULL DEFAULT '0.00',
  `status` enum('Pass','Fail') COLLATE utf8mb4_general_ci NOT NULL,
  `raw_ocr_data` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `term` enum('Prelim','Midterm','Finals') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Prelim',
  `ocr_text` longtext COLLATE utf8mb4_general_ci,
  `ocr_confidence` decimal(5,2) NOT NULL DEFAULT '0.00',
  `ocr_status` enum('pending','processing','completed','failed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'completed',
  `ocr_error` text COLLATE utf8mb4_general_ci,
  `page_count` int NOT NULL DEFAULT '1',
  `evaluation_result` json DEFAULT NULL,
  `teacher_override_log` json DEFAULT NULL,
  `review_status` enum('draft','pending_review','reviewed','published','archived') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'draft',
  `teacher_remarks` text COLLATE utf8mb4_general_ci,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `idx_submissions_student` (`student_id`),
  KEY `idx_submissions_perc` (`percentage`),
  KEY `idx_submissions_teacher_term` (`teacher_id`,`term`),
  CONSTRAINT `exam_submissions_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exam_submissions`
--

LOCK TABLES `exam_submissions` WRITE;
/*!40000 ALTER TABLE `exam_submissions` DISABLE KEYS */;
INSERT INTO `exam_submissions` VALUES (1,2,4,NULL,'Ashley Nicole Gutierrez','Structural Theory 1 - Prelim Quiz','scanned',9,1,9,10,90.00,'Pass',NULL,'2026-07-28 13:39:20','Prelim',NULL,0.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:09:15'),(2,2,4,NULL,'Ashley Nicole Gutierrez','Geotechnical Mechanics - Midterm Exam','pdf',8,2,8,10,80.00,'Pass',NULL,'2026-07-28 13:39:20','Midterm',NULL,0.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:09:15'),(3,2,4,NULL,'Ashley Nicole Gutierrez','Reinforced Concrete Design - Finals Exam','handwritten',9,1,9,10,95.00,'Pass',NULL,'2026-07-28 13:39:20','Finals',NULL,0.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:09:15'),(4,2,4,1,'Ashley Nicole Gutierrez','Flexural Design Quiz 1','scanned',4,1,4,5,80.00,'Pass',NULL,'2026-07-29 05:04:14','Prelim',NULL,0.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:09:15'),(5,7,8,101,'QA Student Alpha','QA Capstone Comprehensive Exam','image',6,2,5,7,85.70,'Pass',NULL,'2026-07-31 03:13:27','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:13:27'),(6,7,8,102,'QA Student Alpha','QA Capstone Comprehensive Exam','image',6,2,5,7,85.70,'Pass',NULL,'2026-07-31 03:13:43','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:13:43'),(7,7,8,103,'QA Student Alpha','QA Capstone Comprehensive Exam','image',6,2,5,7,85.70,'Pass',NULL,'2026-07-31 03:13:56','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:13:56'),(8,7,8,104,'QA Student Alpha','QA Capstone Comprehensive Exam','image',6,2,5,7,85.70,'Pass',NULL,'2026-07-31 03:14:22','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:14:22'),(9,7,8,105,'QA Student Alpha','E2E Verification Exam','image',4,1,4,5,80.00,'Pass',NULL,'2026-07-31 03:21:44','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:21:44'),(10,7,8,106,'QA Student Alpha','E2E Verification Exam','image',4,1,4,5,80.00,'Pass',NULL,'2026-07-31 03:21:57','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:21:57'),(11,7,8,107,'QA Student Alpha','E2E Verification Exam','image',4,1,4,5,80.00,'Pass',NULL,'2026-07-31 03:22:08','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:22:08'),(12,7,8,108,'QA Student Alpha','E2E Verification Exam','image',4,1,4,5,80.00,'Pass',NULL,'2026-07-31 03:22:33','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:22:33'),(13,7,8,109,'QA Student Alpha','E2E Verification Exam','image',4,1,4,5,80.00,'Pass',NULL,'2026-07-31 03:22:42','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:22:42'),(14,7,8,110,'QA Student Alpha','E2E Verification Exam','image',4,1,4,5,80.00,'Pass',NULL,'2026-07-31 03:23:06','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:23:06'),(15,7,8,111,'QA Student Alpha','E2E Verification Exam','image',4,1,4,5,80.00,'Pass',NULL,'2026-07-31 03:23:19','Prelim','1. A\n2. B\n3. C\n4. D\n5. True\n6. Identification Answer\n7. Stress = P/A = 150 MPa',88.00,'completed',NULL,1,NULL,NULL,'published',NULL,NULL,'2026-07-31 03:23:19');
/*!40000 ALTER TABLE `exam_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `exams`
--

DROP TABLE IF EXISTS `exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `subject` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `time_limit` int DEFAULT '60',
  `total_items` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `specialization` varchar(100) COLLATE utf8mb4_general_ci DEFAULT 'Structural Engineering',
  `term` enum('Prelim','Midterm','Finals') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Prelim',
  `difficulty` enum('easy','medium','hard','mixed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'medium',
  `ai_metadata` json DEFAULT NULL,
  `lesson_ids` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=112 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `exams`
--

LOCK TABLES `exams` WRITE;
/*!40000 ALTER TABLE `exams` DISABLE KEYS */;
INSERT INTO `exams` VALUES (1,2,'Flexural Design Quiz 1','Structural Design',60,5,'2026-07-28 13:20:37','Structural Engineering','Prelim','medium',NULL,NULL),(100,2,'Geotechnical Engineering & Soil Mechanics Midterm Exam','CE 402 - Geotechnical Engineering',45,5,'2026-07-28 16:00:27','Geotechnical Engineering','Midterm','medium',NULL,NULL),(101,7,'QA Capstone Comprehensive Exam','Civil Engineering',60,7,'2026-07-31 03:13:26','Structural Engineering','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Structural Engineering and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Structural Engineering\' (Specialization: Structural Engineering) titled \'Concrete Quiz 1\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_\", \"difficulty\": \"medium\", \"token_usage\": 1325, \"generation_time_ms\": 2706.3}',NULL),(102,7,'QA Capstone Comprehensive Exam','Civil Engineering',60,7,'2026-07-31 03:13:43','Structural Engineering','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Structural Engineering and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Structural Engineering\' (Specialization: Structural Engineering) titled \'Concrete Quiz 1\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_\", \"difficulty\": \"medium\", \"token_usage\": 1589, \"generation_time_ms\": 3342.15}',NULL),(103,7,'QA Capstone Comprehensive Exam','Civil Engineering',60,7,'2026-07-31 03:13:55','Structural Engineering','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Structural Engineering and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Structural Engineering\' (Specialization: Structural Engineering) titled \'Concrete Quiz 1\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_\", \"difficulty\": \"medium\", \"token_usage\": 1287, \"generation_time_ms\": 2288.52}',NULL),(104,7,'QA Capstone Comprehensive Exam','Civil Engineering',60,7,'2026-07-31 03:14:21','Structural Engineering','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Structural Engineering and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Structural Engineering\' (Specialization: Structural Engineering) titled \'Concrete Quiz 1\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_\", \"difficulty\": \"medium\", \"token_usage\": 1683, \"generation_time_ms\": 3957.9}',NULL),(105,7,'E2E Verification Exam','Civil Engineering',60,5,'2026-07-31 03:21:43','Hydraulics','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Hydraulics and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Civil Engineering\' (Specialization: Hydraulics) titled \'Fluid Mechanics Quiz\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_formula). based on the f\", \"difficulty\": \"medium\", \"token_usage\": 1433, \"generation_time_ms\": 2998.61}',NULL),(106,7,'E2E Verification Exam','Civil Engineering',60,5,'2026-07-31 03:21:56','Hydraulics','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Hydraulics and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Civil Engineering\' (Specialization: Hydraulics) titled \'Fluid Mechanics Quiz\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_formula). based on the f\", \"difficulty\": \"medium\", \"token_usage\": 1423, \"generation_time_ms\": 2908.89}',NULL),(107,7,'E2E Verification Exam','Civil Engineering',60,5,'2026-07-31 03:22:07','Hydraulics','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Hydraulics and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Civil Engineering\' (Specialization: Hydraulics) titled \'Fluid Mechanics Quiz\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_formula). based on the f\", \"difficulty\": \"medium\", \"token_usage\": 1359, \"generation_time_ms\": 2683.41}',NULL),(108,7,'E2E Verification Exam','Civil Engineering',60,5,'2026-07-31 03:22:31','Hydraulics','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Hydraulics and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Civil Engineering\' (Specialization: Hydraulics) titled \'Fluid Mechanics Quiz\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_formula). based on the f\", \"difficulty\": \"medium\", \"token_usage\": 1571, \"generation_time_ms\": 3389.13}',NULL),(109,7,'E2E Verification Exam','Civil Engineering',60,5,'2026-07-31 03:22:41','Hydraulics','Prelim','medium','[]',NULL),(110,7,'E2E Verification Exam','Civil Engineering',60,5,'2026-07-31 03:23:05','Hydraulics','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Hydraulics and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Civil Engineering\' (Specialization: Hydraulics) titled \'Fluid Mechanics Quiz\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_formula). based on the f\", \"difficulty\": \"medium\", \"token_usage\": 2039, \"generation_time_ms\": 4205.22}',NULL),(111,7,'E2E Verification Exam','Civil Engineering',60,5,'2026-07-31 03:23:18','Hydraulics','Prelim','medium','{\"model\": \"llama-3.3-70b-versatile\", \"prompt\": \"You are an expert Civil Engineering professor specializing in Hydraulics and academic assessment creation. Generate exactly 5 high-quality Civil Engineering examination questions for the subject \'Civil Engineering\' (Specialization: Hydraulics) titled \'Fluid Mechanics Quiz\'. Target Difficulty Level: \'medium\'. Target Question Type Format: \'multiple_choice\' (Supported types: multiple_choice, true_false, identification, fill_in_the_blank, matching_type, problem_solving, math_formula). based on the f\", \"difficulty\": \"medium\", \"token_usage\": 1594, \"generation_time_ms\": 3319.78}',NULL);
/*!40000 ALTER TABLE `exams` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `iso_evaluations`
--

DROP TABLE IF EXISTS `iso_evaluations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iso_evaluations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `evaluator_name` varchar(255) NOT NULL,
  `evaluator_role` enum('student','faculty','it_expert') NOT NULL,
  `functional_suitability` decimal(3,2) NOT NULL,
  `performance_efficiency` decimal(3,2) NOT NULL,
  `compatibility` decimal(3,2) NOT NULL,
  `interaction_capability` decimal(3,2) NOT NULL,
  `reliability` decimal(3,2) NOT NULL,
  `security` decimal(3,2) NOT NULL,
  `maintainability` decimal(3,2) NOT NULL,
  `flexibility` decimal(3,2) NOT NULL,
  `safety` decimal(3,2) NOT NULL,
  `feedback_text` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `iso_evaluations`
--

LOCK TABLES `iso_evaluations` WRITE;
/*!40000 ALTER TABLE `iso_evaluations` DISABLE KEYS */;
INSERT INTO `iso_evaluations` VALUES (1,'Engr. Nicole Gutierrez','it_expert',4.00,3.85,4.00,4.00,3.90,4.00,3.95,3.85,4.00,'Excellent AI OCR response time and seamless FPDF reporting.','2026-07-29 06:02:33'),(2,'Engr. Kevin Dizon','it_expert',3.90,3.95,3.85,3.90,4.00,3.95,3.90,3.80,3.95,'High functional suitability and clean Tailwind CSS UI layout.','2026-07-29 06:02:33'),(3,'Prof. Jolas Santos','faculty',4.00,3.80,3.90,3.95,3.85,4.00,3.85,3.90,4.00,'Automated answer checking reduces teacher stress significantly.','2026-07-29 06:02:33'),(4,'Ashley Nicole Gutierrez','student',3.85,3.90,3.95,4.00,3.90,3.85,3.90,3.85,3.90,'Instant exam feedback helps identify learning gaps easily.','2026-07-29 06:02:33');
/*!40000 ALTER TABLE `iso_evaluations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lesson_materials`
--

DROP TABLE IF EXISTS `lesson_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lesson_materials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `subject` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `file_type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `file_size` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lesson_text` longtext COLLATE utf8mb4_general_ci,
  `processing_status` enum('pending','processing','completed','failed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `processing_error` text COLLATE utf8mb4_general_ci,
  `word_count` int NOT NULL DEFAULT '0',
  `page_count` int NOT NULL DEFAULT '0',
  `extracted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `lesson_materials_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lesson_materials`
--

LOCK TABLES `lesson_materials` WRITE;
/*!40000 ALTER TABLE `lesson_materials` DISABLE KEYS */;
INSERT INTO `lesson_materials` VALUES (1,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','tests/fixtures/sample_lesson.txt','TXT',225,'2026-07-31 03:13:23','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:13:23'),(2,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','tests/fixtures/sample_lesson.txt','TXT',636,'2026-07-31 03:13:23','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:13:23'),(3,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','tests/fixtures/sample_lesson.txt','TXT',683,'2026-07-31 03:13:23','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:13:23'),(4,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','tests/fixtures/sample_lesson.txt','TXT',1682,'2026-07-31 03:13:23','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:13:23'),(5,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','../tests/fixtures/sample_lesson.txt','TXT',225,'2026-07-31 03:13:39','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:13:39'),(6,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','../tests/fixtures/sample_lesson.txt','TXT',636,'2026-07-31 03:13:39','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:13:39'),(7,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','../tests/fixtures/sample_lesson.txt','TXT',683,'2026-07-31 03:13:39','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:14:10'),(8,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','../tests/fixtures/sample_lesson.txt','TXT',1682,'2026-07-31 03:13:39','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:13:39'),(9,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','../tests/fixtures/sample_lesson.txt','TXT',225,'2026-07-31 03:13:53','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:13:53'),(10,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.docx','../tests/fixtures/sample_lesson.docx','DOCX',636,'2026-07-31 03:13:53','Geotechnical Soil Mechanics and Foundation Settlement calculation under Terzaghi bearing capacity q_ult = c * N_c + q * N_q + 0.5 * gamma * B * N_gamma.','completed',NULL,23,1,'2026-07-31 03:13:53'),(11,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.pptx','../tests/fixtures/sample_lesson.pptx','PPTX',683,'2026-07-31 03:13:53','--- Slide 1 ---\nStructural Dynamics and Earthquake Engineering: Fundamental natural period T = 2 * pi * sqrt(m / k). Seismic response spectrum analysis for high-rise frames.','completed',NULL,23,1,'2026-07-31 03:13:53'),(12,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:13:53','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:13:53'),(13,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.txt','../tests/fixtures/sample_lesson.txt','TXT',225,'2026-07-31 03:14:17','Flexural resistance of reinforced concrete beams under Ultimate Limit State (ULS) flexural moment M_u = 250 kN-m. Shear capacity V_c = 0.17 * sqrt(f_c) * b_w * d. Steel ratio rho = A_s / (b * d). Yield strength f_y = 420 MPa.','completed',NULL,38,1,'2026-07-31 03:14:17'),(14,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.docx','../tests/fixtures/sample_lesson.docx','DOCX',636,'2026-07-31 03:14:17','Geotechnical Soil Mechanics and Foundation Settlement calculation under Terzaghi bearing capacity q_ult = c * N_c + q * N_q + 0.5 * gamma * B * N_gamma.','completed',NULL,23,1,'2026-07-31 03:14:17'),(15,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.pptx','../tests/fixtures/sample_lesson.pptx','PPTX',683,'2026-07-31 03:14:17','--- Slide 1 ---\nStructural Dynamics and Earthquake Engineering: Fundamental natural period T = 2 * pi * sqrt(m / k). Seismic response spectrum analysis for high-rise frames.','completed',NULL,23,1,'2026-07-31 03:14:17'),(16,7,'Structural Engineering','Concrete Beam Flexure','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:14:17','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:14:17'),(17,7,'Civil Engineering','Fluid Mechanics PDF','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:21:40','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:21:40'),(18,7,'Civil Engineering','Fluid Mechanics PDF','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:21:53','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:21:53'),(19,7,'Civil Engineering','Fluid Mechanics PDF','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:22:05','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:22:05'),(20,7,'Civil Engineering','Fluid Mechanics PDF','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:22:28','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:22:28'),(21,7,'Civil Engineering','Fluid Mechanics PDF','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:22:38','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:22:38'),(22,7,'Civil Engineering','Fluid Mechanics PDF','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:23:01','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:23:01'),(23,7,'Civil Engineering','Corrupted Test PDF','corrupted.pdf','../tests/fixtures/corrupted.pdf','PDF',36,'2026-07-31 03:23:06',NULL,'failed','Extraction resulted in empty text. The file might contain scanned images without OCR layers or password protection.',0,0,NULL),(24,7,'Civil Engineering','Empty Test DOCX','empty.docx','../tests/fixtures/empty.docx','DOCX',0,'2026-07-31 03:23:06',NULL,'failed','Invalid DOCX format: word/document.xml missing.',0,0,NULL),(25,7,'Civil Engineering','Fake MIME PDF','fake_mime.pdf','../tests/fixtures/fake_mime.pdf','PDF',41,'2026-07-31 03:23:06',NULL,'failed','Extraction resulted in empty text. The file might contain scanned images without OCR layers or password protection.',0,0,NULL),(26,7,'Civil Engineering','Fluid Mechanics PDF','sample_lesson.pdf','../tests/fixtures/sample_lesson.pdf','PDF',1682,'2026-07-31 03:23:14','Hydraulics and Fluid Mechanics Lesson Material energy loss calculation and critical depth equation.','completed',NULL,13,1,'2026-07-31 03:23:14'),(27,7,'Civil Engineering','Corrupted Test PDF','corrupted.pdf','../tests/fixtures/corrupted.pdf','PDF',36,'2026-07-31 03:23:19',NULL,'failed','Extraction resulted in empty text. The file might contain scanned images without OCR layers or password protection.',0,0,NULL),(28,7,'Civil Engineering','Empty Test DOCX','empty.docx','../tests/fixtures/empty.docx','DOCX',0,'2026-07-31 03:23:19',NULL,'failed','File not found on server at path: ../tests/fixtures/empty.docx',0,0,NULL),(29,7,'Civil Engineering','Fake MIME PDF','fake_mime.pdf','../tests/fixtures/fake_mime.pdf','PDF',41,'2026-07-31 03:23:19',NULL,'failed','Extraction resulted in empty text. The file might contain scanned images without OCR layers or password protection.',0,0,NULL);
/*!40000 ALTER TABLE `lesson_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sections`
--

DROP TABLE IF EXISTS `sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sections` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `section_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `course_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `academic_year` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sections`
--

LOCK TABLES `sections` WRITE;
/*!40000 ALTER TABLE `sections` DISABLE KEYS */;
INSERT INTO `sections` VALUES (1,2,'BSCE 4-A','BS Civil Engineering','2025-2026','2026-07-28 13:31:30');
/*!40000 ALTER TABLE `sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_details`
--

DROP TABLE IF EXISTS `student_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `student_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `course` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `year_level` int NOT NULL,
  `section` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `student_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_details`
--

LOCK TABLES `student_details` WRITE;
/*!40000 ALTER TABLE `student_details` DISABLE KEYS */;
INSERT INTO `student_details` VALUES (2,4,'23-2149184','BSCE',4,'A'),(3,8,'QA-STD-2026-A','BSCE',4,'A'),(4,9,'QA-STD-2026-B','BSCE',4,'B');
/*!40000 ALTER TABLE `student_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_requests`
--

DROP TABLE IF EXISTS `student_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `teacher_id` int NOT NULL,
  `section_id` int DEFAULT NULL,
  `student_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `student_name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `subject_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','accepted','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_requests_teacher_status` (`teacher_id`,`status`),
  KEY `idx_requests_student` (`student_id`),
  CONSTRAINT `fk_requests_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_requests_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_requests`
--

LOCK TABLES `student_requests` WRITE;
/*!40000 ALTER TABLE `student_requests` DISABLE KEYS */;
INSERT INTO `student_requests` VALUES (1,4,2,1,'23-2149184','Ashley Nicole Gutierrez','Structural Theory 1','accepted','2026-07-28 13:23:43');
/*!40000 ALTER TABLE `student_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `students`
--

DROP TABLE IF EXISTS `students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `students` (
  `id` int NOT NULL AUTO_INCREMENT,
  `teacher_id` int NOT NULL,
  `section_id` int NOT NULL,
  `student_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `fullname` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_number` (`student_number`),
  KEY `teacher_id` (`teacher_id`),
  KEY `section_id` (`section_id`),
  CONSTRAINT `students_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `students_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `students`
--

LOCK TABLES `students` WRITE;
/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` VALUES (1,2,1,'23-2149184','Ashley Nicole Gutierrez',NULL,'2026-07-28 13:31:41');
/*!40000 ALTER TABLE `students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fullname` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','teacher','student') COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (2,'jolas','lasjo','lasjo@gmail.com','$2y$10$WUsMFtmboYjhnLqaDbIGve.ocPLTkNUdzBtNW8Aw3yHPqYKQJI8fy','teacher','2026-07-20 14:21:13'),(4,'Ashley Nicole Gutierrez','Nicole','nikol@gmail.com','$2y$10$Rqr7bLIFxDeyG0qiPaNqBe6461nHLOi3xWUzgllgZtPcQ54ulfMi6','student','2026-07-21 09:42:05'),(5,'Russel Gregorio','Russel','russel@gmail.com','$2y$10$7cZuxT8tktcT2Q9xUdGzxuKNtheikTEKBL3.NcsJRSvMJrD0xvOg.','admin','2026-07-21 11:25:28'),(6,'QA Test Administrator','qa_admin','qa_admin@questbank.test','$2y$12$nv9Rc5MLxQQP2ODPpRqdT.ndEmh3HIIfcRcG/wq5sjoNVMPCtdWtC','admin','2026-07-31 03:12:50'),(7,'QA Test Professor','qa_teacher','qa_teacher@questbank.test','$2y$12$nv9Rc5MLxQQP2ODPpRqdT.ndEmh3HIIfcRcG/wq5sjoNVMPCtdWtC','teacher','2026-07-31 03:12:50'),(8,'QA Student Alpha','qa_student_a','qa_student_a@questbank.test','$2y$12$nv9Rc5MLxQQP2ODPpRqdT.ndEmh3HIIfcRcG/wq5sjoNVMPCtdWtC','student','2026-07-31 03:12:50'),(9,'QA Student Beta','qa_student_b','qa_student_b@questbank.test','$2y$12$nv9Rc5MLxQQP2ODPpRqdT.ndEmh3HIIfcRcG/wq5sjoNVMPCtdWtC','student','2026-07-31 03:12:50');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-31 11:31:41
