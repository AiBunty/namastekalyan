-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: mysql.gb.stackcp.com    Database: NamasteKalyan-353030350416
-- ------------------------------------------------------
-- Server version	5.5.5-10.6.18-MariaDB-log

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

--
-- Table structure for table `admin_cash_ledger`
--

DROP TABLE IF EXISTS `admin_cash_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_cash_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` varchar(60) NOT NULL,
  `ledger_date` date NOT NULL,
  `admin_username` varchar(50) NOT NULL,
  `admin_display_name` varchar(100) NOT NULL DEFAULT '',
  `transaction_id` varchar(60) NOT NULL DEFAULT '',
  `event_id` varchar(50) NOT NULL DEFAULT '',
  `event_title` varchar(200) NOT NULL DEFAULT '',
  `customer_name` varchar(150) NOT NULL DEFAULT '',
  `customer_phone` varchar(20) NOT NULL DEFAULT '',
  `qty` smallint(5) unsigned NOT NULL DEFAULT 1,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(5) NOT NULL DEFAULT 'INR',
  `status` varchar(30) NOT NULL DEFAULT 'issued',
  `issued_at` datetime NOT NULL DEFAULT current_timestamp(),
  `handover_requested_at` datetime DEFAULT NULL,
  `handover_approved_at` datetime DEFAULT NULL,
  `handover_approved_by` varchar(50) NOT NULL DEFAULT '',
  `handover_batch_key` varchar(60) NOT NULL DEFAULT '',
  `notes` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_entry_id` (`entry_id`),
  KEY `idx_ledger_date` (`ledger_date`),
  KEY `idx_admin_username` (`admin_username`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_cash_ledger`
--

LOCK TABLES `admin_cash_ledger` WRITE;
/*!40000 ALTER TABLE `admin_cash_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_cash_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `api_settings`
--

DROP TABLE IF EXISTS `api_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(60) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `api_settings`
--

LOCK TABLES `api_settings` WRITE;
/*!40000 ALTER TABLE `api_settings` DISABLE KEYS */;
INSERT INTO `api_settings` VALUES (1,'RAZORPAY_KEY_ID','','2026-04-14 12:06:52'),(2,'RAZORPAY_KEY_SECRET','','2026-04-14 12:06:52'),(3,'RAZORPAY_WEBHOOK_SECRET','','2026-04-14 12:06:52'),(4,'CRM_API_TOKEN','','2026-04-14 12:06:52'),(5,'EVENT_QR_SIGNING_SECRET','','2026-04-14 12:06:52');
/*!40000 ALTER TABLE `api_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auth_audit`
--

DROP TABLE IF EXISTS `auth_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auth_audit` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `logged_at` datetime NOT NULL DEFAULT current_timestamp(),
  `action` varchar(60) NOT NULL,
  `username` varchar(50) NOT NULL DEFAULT '',
  `outcome` varchar(30) NOT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'web',
  `details` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_logged_at` (`logged_at`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auth_audit`
--

LOCK TABLES `auth_audit` WRITE;
/*!40000 ALTER TABLE `auth_audit` DISABLE KEYS */;
INSERT INTO `auth_audit` VALUES (1,'2026-04-16 12:06:23','auth_login','9371519999','success','web','force_password_change'),(2,'2026-04-16 12:06:30','auth_logout','9371519999','success','web','token'),(3,'2026-04-16 12:06:40','auth_login','9371519999','success','web','force_password_change'),(4,'2026-04-16 12:07:28','auth_login','9371519999','success','web','force_password_change'),(5,'2026-04-16 12:13:02','auth_login','9371519999','success','web','force_password_change'),(6,'2026-04-16 12:13:09','auth_logout','9371519999','success','web','token'),(7,'2026-04-16 12:13:58','auth_login','9371519999','success','web','force_password_change'),(8,'2026-04-16 12:20:10','auth_login','9371519999','success','web','force_password_change'),(9,'2026-04-16 12:27:54','auth_login','9371519999','success','web','force_password_change'),(10,'2026-04-16 12:28:50','auth_logout','9371519999','success','web','token'),(11,'2026-04-16 12:29:31','auth_login','9371519999','success','web','force_password_change'),(12,'2026-04-16 12:35:08','auth_login','9371519999','success','web','force_password_change'),(13,'2026-04-16 12:35:13','auth_login','9371519999','success','web','force_password_change'),(14,'2026-04-16 12:49:18','auth_login','9371519999','success','web','force_password_change'),(15,'2026-04-16 12:49:19','auth_create_user','9371519999','success','web','target=9000000001'),(16,'2026-04-16 12:49:20','auth_login','9000000001','success','web','force_password_change'),(17,'2026-04-16 13:07:51','auth_login','9371519999','success','web','force_password_change'),(18,'2026-04-16 13:07:53','auth_login','9000000001','success','web','force_password_change'),(19,'2026-04-16 13:12:00','auth_login','9371519999','success','web','force_password_change'),(20,'2026-04-16 13:12:02','auth_login','9000000001','success','web','force_password_change'),(21,'2026-04-16 13:28:42','auth_login','9371519999','success','web','force_password_change'),(22,'2026-04-16 13:34:44','auth_login','9371519999','success','web','force_password_change'),(23,'2026-04-16 13:34:46','auth_login','9000000001','success','web','force_password_change'),(24,'2026-04-16 13:44:12','auth_logout','9371519999','success','web','token'),(25,'2026-04-16 13:44:16','auth_login','9371519999','success','web','force_password_change'),(26,'2026-04-16 13:49:07','auth_login','9371519999','success','web','force_password_change'),(27,'2026-04-16 13:49:12','auth_login','9371519999','success','web','force_password_change'),(28,'2026-04-16 13:50:07','auth_logout','9371519999','success','web','token'),(29,'2026-04-16 13:50:11','auth_login','9371519999','success','web','force_password_change'),(30,'2026-04-16 13:51:47','auth_login','9371519999','success','web','force_password_change'),(31,'2026-04-16 13:52:17','auth_login','9371519999','success','web','force_password_change'),(32,'2026-04-16 13:52:18','auth_login','9000000001','success','web','force_password_change'),(33,'2026-04-16 14:08:17','auth_login','9371519999','success','web','force_password_change'),(34,'2026-04-16 14:08:19','auth_login','admin','failed','web','user_not_found'),(35,'2026-04-16 14:13:23','auth_login','9371519999','success','web','force_password_change'),(36,'2026-04-16 14:13:25','auth_login','9371519999','success','web','force_password_change'),(37,'2026-04-16 14:17:39','auth_login','9371519999','success','web','force_password_change'),(38,'2026-04-16 14:23:40','auth_login','9371519999','success','web','force_password_change'),(39,'2026-04-16 14:25:21','auth_login','9371519999','success','web','force_password_change'),(40,'2026-04-16 14:49:41','auth_login','9371519999','success','web','force_password_change'),(41,'2026-04-16 15:00:01','auth_login','9371519999','success','web','force_password_change');
/*!40000 ALTER TABLE `auth_audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `event_transactions`
--

DROP TABLE IF EXISTS `event_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(60) NOT NULL,
  `event_id` varchar(50) NOT NULL,
  `event_title` varchar(200) NOT NULL DEFAULT '',
  `customer_name` varchar(150) NOT NULL DEFAULT '',
  `customer_email` varchar(150) NOT NULL DEFAULT '',
  `customer_phone` varchar(20) NOT NULL DEFAULT '',
  `qty` smallint(5) unsigned NOT NULL DEFAULT 1,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(5) NOT NULL DEFAULT 'INR',
  `gateway` enum('razorpay','cash','free') NOT NULL DEFAULT 'free',
  `order_id` varchar(40) DEFAULT NULL,
  `payment_id` varchar(40) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `qr_url` varchar(500) NOT NULL DEFAULT '',
  `qr_payload` varchar(500) NOT NULL DEFAULT '',
  `crm_sync_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `crm_sync_code` varchar(20) NOT NULL DEFAULT '',
  `crm_sync_message` varchar(255) NOT NULL DEFAULT '',
  `email_status` varchar(20) NOT NULL DEFAULT 'Pending',
  `email_sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL,
  `cancel_requested_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `refund_status` varchar(20) NOT NULL DEFAULT '',
  `refund_id` varchar(40) NOT NULL DEFAULT '',
  `checkin_status` enum('pending','checked_in') NOT NULL DEFAULT 'pending',
  `checked_in_at` datetime DEFAULT NULL,
  `verified_by` varchar(50) NOT NULL DEFAULT '',
  `attendee_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attendee_details`)),
  `guest_passes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`guest_passes_json`)),
  `issued_by` varchar(50) NOT NULL DEFAULT '',
  `cancel_request_by` varchar(50) NOT NULL DEFAULT '',
  `cancel_request_reason` varchar(500) NOT NULL DEFAULT '',
  `cancel_reviewed_by` varchar(50) NOT NULL DEFAULT '',
  `cancel_reviewed_at` datetime DEFAULT NULL,
  `cancel_decision` varchar(20) NOT NULL DEFAULT '',
  `cash_ledger_entry_id` varchar(60) NOT NULL DEFAULT '',
  `checked_in_count` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transaction_id` (`transaction_id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_status` (`status`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_payment_id` (`payment_id`),
  KEY `idx_customer_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `event_transactions`
--

LOCK TABLES `event_transactions` WRITE;
/*!40000 ALTER TABLE `event_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `event_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `subtitle` varchar(300) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `image_url` varchar(500) NOT NULL DEFAULT '',
  `video_url` varchar(500) NOT NULL DEFAULT '',
  `show_video` tinyint(1) NOT NULL DEFAULT 0,
  `cta_text` varchar(100) NOT NULL DEFAULT '',
  `cta_url` varchar(500) NOT NULL DEFAULT '',
  `badge_text` varchar(60) NOT NULL DEFAULT '',
  `start_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `time_display_format` enum('12h','24h') NOT NULL DEFAULT '12h',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `priority` smallint(6) NOT NULL DEFAULT 0,
  `popup_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `show_once_per_session` tinyint(1) NOT NULL DEFAULT 0,
  `popup_delay_hours` decimal(5,2) NOT NULL DEFAULT 0.00,
  `popup_cooldown_hours` decimal(5,2) NOT NULL DEFAULT 24.00,
  `event_type` enum('free','paid') NOT NULL DEFAULT 'free',
  `ticket_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(5) NOT NULL DEFAULT 'INR',
  `max_tickets` int(10) unsigned NOT NULL DEFAULT 0,
  `payment_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `cancellation_policy` text NOT NULL,
  `refund_policy` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_id` (`event_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `events`
--

LOCK TABLES `events` WRITE;
/*!40000 ALTER TABLE `events` DISABLE KEYS */;
INSERT INTO `events` VALUES (16,'paid-test-2026','Bollywood Night - Paid Entry','Live DJ, premium access','Paid entry test event. For booking call 9371519999. Venue: Rockmount Commercial Hub, 4th Floor, Khadakpada Circle, Kalyan West, Thane, Maharashtra 421301','https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=1200&q=80','',0,'Book Now','','Paid Event','2026-04-14','00:00:20','2026-04-14','00:00:23','12h',0,300,1,0,0.00,0.00,'paid',399.00,'INR',4,1,'No refund once pass is purchased.','No Refund','2026-04-16 14:16:32',NULL),(17,'dj-raj-2026-apr','DJ Night by DJ Raj in the House with Mack','April 15th, 8 PM onwards','April 15th, 8 PM onwards\nWhere Beats Drop and Stress Stops\nFor Booking Call 9371519999\nVenue: Rockmount Commercial Hub, 4th Floor, Khadakpada Circle, Kalyan West, Thane, Maharashtra 421301\nFree Event','https://storagev2.files-vault.com/uploads/blacklabel-765/sub-account-82800/1776054554-WdMnjl2uZA.webp','',0,'I\'m Interested','','Free Event','2026-04-15','00:00:20','2026-04-15','00:00:23','12h',1,220,1,0,0.00,0.00,'free',0.00,'INR',0,0,'No refund once pass is purchased.','No Refund','2026-04-16 14:16:32',NULL),(18,'dj-adaa-2026-apr','Night With DJ Adaa','25th April, 8:00 PM onwards','25th April, 8:00 PM onwards\nWhere Music Meets Madness\nFor Booking Call 9371519999\nVenue: Rockmount Commercial Hub, 4th Floor, Khadakpada Circle, Kalyan West, Thane, Maharashtra 421301\nFree Event','https://storagev2.files-vault.com/uploads/blacklabel-765/sub-account-82800/1776054530-O5HQuYBXJh.webp','',0,'I\'m Interested','','Free Event','2026-04-25','00:00:20','2026-04-25','00:00:23','12h',1,210,1,0,0.00,0.00,'free',0.00,'INR',0,0,'No refund once pass is purchased.','No Refund','2026-04-16 14:16:32',NULL);
/*!40000 ALTER TABLE `events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leads` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `name` varchar(150) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `prize` varchar(200) NOT NULL DEFAULT '',
  `status` enum('Unredeemed','Redeemed') NOT NULL DEFAULT 'Unredeemed',
  `date_of_birth` date DEFAULT NULL,
  `date_of_anniversary` date DEFAULT NULL,
  `source` varchar(60) NOT NULL DEFAULT 'menu-blocker-web',
  `visit_count` smallint(5) unsigned NOT NULL DEFAULT 1,
  `coupon_code` varchar(30) NOT NULL DEFAULT '',
  `crm_sync_status` enum('Pending','Success','Failed','Skipped') NOT NULL DEFAULT 'Pending',
  `crm_sync_code` varchar(20) NOT NULL DEFAULT '',
  `crm_sync_message` varchar(255) NOT NULL DEFAULT '',
  `redeemed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_phone` (`phone`),
  KEY `idx_coupon_code` (`coupon_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leads`
--

LOCK TABLES `leads` WRITE;
/*!40000 ALTER TABLE `leads` DISABLE KEYS */;
INSERT INTO `leads` VALUES (58,'2026-04-11 17:47:47','Live Test User','9587805925','Try Again Next Time','Redeemed','1998-07-11','2022-02-14','live-test',0,'','','','',NULL),(59,'2026-04-12 04:54:32','Parin Daulat','9330033000','Try Again Next Time','Unredeemed',NULL,NULL,'menu-blocker-web',3,'NK-COUP-00003-3000','','','',NULL),(60,'2026-04-12 05:11:44','Visit Count Test','9200168185','Try Again Next Time','Unredeemed','1997-01-01','2021-01-01','visit-count-test',0,'','','','',NULL),(61,'2026-04-12 09:14:46','Test 20 Coupon','9118681645','20% OFF','Redeemed','1999-01-01','2020-01-01','manual-test-20',1,'NK-OFF20-00005-1645','','','',NULL),(62,'2026-04-12 09:21:49','Dummy Push Test','9411485637','Try Again Next Time','Unredeemed',NULL,NULL,'manual-dummy-retry',1,'','','','',NULL),(63,'2026-04-12 09:31:55','Dummy Push Test UI','9898284900','Try Again Next Time','Unredeemed',NULL,NULL,'manual-dummy-ui',1,'','Success','200','Manual dummy entry from new deployment',NULL),(64,'2026-04-12 09:37:53','Dummy Push Retest','9428308222','Try Again Next Time','Unredeemed',NULL,NULL,'manual-retest',1,'','Failed','404 | 404','A1:FAIL(404) {\"status\":\"failed\",\"message\":\"Automation not found for the provided ID.\",\"data\":{\"automation_id\":\"69db595bad4f7\"}} || A2:FAIL(404) {\"status\":\"failed\",\"message\":\"Automation not found for the provided ID.\",\"data\":{\"automation_id\":\"69db595bad4f7',NULL),(65,'2026-04-12 09:43:59','Dummy Push Final','9756363107','Try Again Next Time','Unredeemed',NULL,NULL,'manual-final',1,'','Success','200','A1:OK(200) {\"status\":\"success\",\"message\":\"Automation executed successfully\",\"data\":{\"automation_id\":\"69db61c17e52a\"}}',NULL),(66,'2026-04-14 14:44:01','Proyal','9883022221','Mocktail on the House','Unredeemed',NULL,NULL,'menu-blocker-web',1,'NK-MOCK-00010-2221','Success','200','A1:OK(200) {\"status\":\"success\",\"message\":\"Automation executed successfully\",\"data\":{\"automation_id\":\"69db61c17e52a\"}}',NULL),(67,'2026-04-14 15:13:51','MADAN HIRA','7001253153','Try Again','Unredeemed',NULL,NULL,'menu-blocker-web',1,'','Success','200','A1:OK(200) {\"status\":\"success\",\"message\":\"Automation executed successfully\",\"data\":{\"automation_id\":\"69db61c17e52a\"}}',NULL),(68,'2026-04-14 15:17:02','Parin Daulat','9330033000','Try Again','Unredeemed',NULL,NULL,'menu-blocker-web',1,'','Success','200','A1:OK(200) {\"status\":\"success\",\"message\":\"Automation executed successfully\",\"data\":{\"automation_id\":\"69db61c17e52a\"}}',NULL);
/*!40000 ALTER TABLE `leads` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sheet_type` enum('food','bar') NOT NULL,
  `category` varchar(100) NOT NULL DEFAULT '',
  `sub_category` varchar(100) NOT NULL DEFAULT '',
  `item_name` varchar(200) NOT NULL,
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `base_price` decimal(10,2) DEFAULT NULL,
  `price_columns` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`price_columns`)),
  `food_category` enum('Veg','NonVeg','Jain','') NOT NULL DEFAULT '',
  `sort_order` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sheet_type` (`sheet_type`),
  KEY `idx_category` (`sheet_type`,`category`),
  KEY `idx_available` (`is_available`)
) ENGINE=InnoDB AUTO_INCREMENT=3715 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_items`
--

LOCK TABLES `menu_items` WRITE;
/*!40000 ALTER TABLE `menu_items` DISABLE KEYS */;
INSERT INTO `menu_items` VALUES (3096,'food','Sandwiches','','Mumbai Toast',0,NULL,'[]','',1,'2026-04-16 14:16:32',NULL),(3097,'food','Sandwiches','','Veg Delight',0,NULL,'[]','',2,'2026-04-16 14:16:32',NULL),(3098,'food','Sandwiches','','Cheese Chilly Tst',0,NULL,'[]','',3,'2026-04-16 14:16:32',NULL),(3099,'food','Sandwiches','','Corn Chilly',0,NULL,'[]','',4,'2026-04-16 14:16:32',NULL),(3100,'food','Sandwiches','','Corn Spinach',0,NULL,'[]','',5,'2026-04-16 14:16:32',NULL),(3101,'food','Sandwiches','','Classic Veg',0,NULL,'[]','',6,'2026-04-16 14:16:32',NULL),(3102,'food','Sandwiches','','Cheese Brust',0,NULL,'[]','',7,'2026-04-16 14:16:32',NULL),(3103,'food','Sandwiches','','Veg Clun Sandwich',0,NULL,'[]','',8,'2026-04-16 14:16:32',NULL),(3104,'food','Sandwiches','','Paneer Tikka',0,NULL,'[]','',9,'2026-04-16 14:16:32',NULL),(3105,'food','Sandwiches','','Peri Peri Paneer Sandwich',0,NULL,'[]','',10,'2026-04-16 14:16:32',NULL),(3106,'food','Sandwiches','','Tandoori Paneer',0,NULL,'[]','',11,'2026-04-16 14:16:32',NULL),(3107,'food','Sandwiches','','Boiled Eggs Sandwichs',0,329.00,'{\"Egg\":329}','',12,'2026-04-16 14:16:32',NULL),(3108,'food','Sandwiches','','Chciken Delight Sandwich',0,339.00,'{\"Chicken\":339}','',13,'2026-04-16 14:16:32',NULL),(3109,'food','Sandwiches','','Chicken Peri Per',0,379.00,'{\"Chicken\":379}','',14,'2026-04-16 14:16:32',NULL),(3110,'food','Sandwiches','','Chicken Tikka',0,379.00,'{\"Chicken\":379}','',15,'2026-04-16 14:16:32',NULL),(3111,'food','Sandwiches','','Chicken Club Sandwich',0,399.00,'{\"Chicken\":399}','',16,'2026-04-16 14:16:32',NULL),(3112,'food','Sandwiches','','Bbq Chicken Sandwich',0,379.00,'{\"Chicken\":379}','',17,'2026-04-16 14:16:32',NULL),(3113,'food','Sancks','','Mix Veg Pakoda',0,NULL,'[]','',18,'2026-04-16 14:16:32',NULL),(3114,'food','Sancks','','Onion Ring Pakoda',0,NULL,'[]','',19,'2026-04-16 14:16:32',NULL),(3115,'food','Sancks','','Potato Pakoda',0,NULL,'[]','',20,'2026-04-16 14:16:32',NULL),(3116,'food','Sancks','','Paneer Pakoda',0,NULL,'[]','',21,'2026-04-16 14:16:32',NULL),(3117,'food','Sancks','','Panner Kantaki',0,NULL,'[]','',22,'2026-04-16 14:16:32',NULL),(3118,'food','Sancks','','Aloo Papdi Chaat',0,NULL,'[]','',23,'2026-04-16 14:16:32',NULL),(3119,'food','Sancks','','Masala Peanut',0,NULL,'[]','',24,'2026-04-16 14:16:32',NULL),(3120,'food','Sancks','','Plain Peanut',0,NULL,'[]','',25,'2026-04-16 14:16:32',NULL),(3121,'food','Sancks','','Hara Bhara Kabab',0,NULL,'[]','',26,'2026-04-16 14:16:32',NULL),(3122,'food','Sancks','','Garlic& Cheese Crostini',0,NULL,'[]','',27,'2026-04-16 14:16:32',NULL),(3123,'food','Pehels Kadam','','Roomali Khakra  Masala Cheese &Garlic',0,NULL,'[]','',28,'2026-04-16 14:16:32',NULL),(3124,'food','Pehels Kadam','','Roomali Khakra  Masala',0,NULL,'[]','',29,'2026-04-16 14:16:32',NULL),(3125,'food','Pehels Kadam','','Roomali Khakra  Masala Cheese',0,NULL,'[]','',30,'2026-04-16 14:16:32',NULL),(3126,'food','Pehels Kadam','','Double Fried Mushroom With Cheddar & Fine Herbs',0,NULL,'[]','',31,'2026-04-16 14:16:32',NULL),(3127,'food','Pehels Kadam','','French Fries',0,NULL,'[]','',32,'2026-04-16 14:16:32',NULL),(3128,'food','Pehels Kadam','','Cheese Cherry Pineapple',0,NULL,'[]','',33,'2026-04-16 14:16:32',NULL),(3129,'food','Pehels Kadam','','Dahi Ke Sholey',0,NULL,'[]','',34,'2026-04-16 14:16:32',NULL),(3130,'food','Pehels Kadam','','Kathi Rolls',0,NULL,'[]','',35,'2026-04-16 14:16:32',NULL),(3131,'food','Pehels Kadam','','Egg Pakoda',0,289.00,'{\"Egg\":289}','',36,'2026-04-16 14:16:32',NULL),(3132,'food','Pehels Kadam','','Chicken Koliwada',0,389.00,'{\"Chicken\":389}','',37,'2026-04-16 14:16:32',NULL),(3133,'food','Pehels Kadam','','Chicken Nuggets',0,429.00,'{\"Chicken\":429}','',38,'2026-04-16 14:16:32',NULL),(3134,'food','Pehels Kadam','','Fish Koliwada',0,479.00,'{\"Basa\":479}','',39,'2026-04-16 14:16:32',NULL),(3135,'food','Pehels Kadam','','Fish & Chipes',0,439.00,'{\"Basa\":439}','',40,'2026-04-16 14:16:32',NULL),(3136,'food','Pehels Kadam','','Fish Finger',0,439.00,'{\"Basa\":439}','',41,'2026-04-16 14:16:32',NULL),(3137,'food','Pehels Kadam','','Fish Tava Fry  Pomfret',0,749.00,'{\"Pomfret\":749}','',42,'2026-04-16 14:16:32',NULL),(3138,'food','Pehels Kadam','','Fish Tava Fry Surmai',0,649.00,'{\"Surmai\":649}','',43,'2026-04-16 14:16:32',NULL),(3139,'food','Pehels Kadam','','Fish Tava Fry Basa',0,549.00,'{\"Basa\":549}','',44,'2026-04-16 14:16:32',NULL),(3140,'food','Salads','','Greek Salad',0,329.00,'{\"Chicken\":329}','',45,'2026-04-16 14:16:32',NULL),(3141,'food','Salads','','Tazza Salad',0,NULL,'[]','',46,'2026-04-16 14:16:32',NULL),(3142,'food','Salads','','Sprout Salad',0,NULL,'[]','',47,'2026-04-16 14:16:32',NULL),(3143,'food','Salads','','Chicken Hawaiian Salad',0,339.00,'{\"Chicken\":339}','',48,'2026-04-16 14:16:32',NULL),(3144,'food','Salads','','Waldorf Salad',0,399.00,'{\"Chicken\":399}','',49,'2026-04-16 14:16:32',NULL),(3145,'food','Salads','','Pasta Salad',0,349.00,'{\"Chicken\":349}','',50,'2026-04-16 14:16:32',NULL),(3146,'food','Salads','','Prussian Salad',0,379.00,'{\"Chicken\":379}','',51,'2026-04-16 14:16:32',NULL),(3147,'food','Salads','','Sauted Broccoli Chicken Salad',0,399.00,'{\"Chicken\":399}','',52,'2026-04-16 14:16:32',NULL),(3148,'food','Salads','','Chicken Tikka Salad',0,399.00,'{\"Chicken\":399}','',53,'2026-04-16 14:16:32',NULL),(3149,'food','Salads','','Crispy Salad',0,319.00,'{\"Chicken\":319}','',54,'2026-04-16 14:16:32',NULL),(3150,'food','Crackers & Dahi','','Crackers',0,NULL,'[]','',55,'2026-04-16 14:16:32',NULL),(3151,'food','Crackers & Dahi','','Urad Papad',0,NULL,'[]','',56,'2026-04-16 14:16:32',NULL),(3152,'food','Crackers & Dahi','','Nagli Papad',0,NULL,'[]','',57,'2026-04-16 14:16:32',NULL),(3153,'food','Crackers & Dahi','','Masla Papad',0,NULL,'[]','',58,'2026-04-16 14:16:32',NULL),(3154,'food','Crackers & Dahi','','Sindhi Papad',0,NULL,'[]','',59,'2026-04-16 14:16:32',NULL),(3155,'food','Crackers & Dahi','','Pineapple Raita',0,NULL,'[]','',60,'2026-04-16 14:16:32',NULL),(3156,'food','Crackers & Dahi','','Boondi Raita',0,NULL,'[]','',61,'2026-04-16 14:16:32',NULL),(3157,'food','Crackers & Dahi','','Mix Fruit Raita',0,NULL,'[]','',62,'2026-04-16 14:16:32',NULL),(3158,'food','Crackers & Dahi','','Plain Dahi',0,NULL,'[]','',63,'2026-04-16 14:16:32',NULL),(3159,'food','Soup','','Badam Aur Elaichi Ka Shorba',0,NULL,'[]','',64,'2026-04-16 14:16:32',NULL),(3160,'food','Soup','','Dal Shorba',0,NULL,'[]','',65,'2026-04-16 14:16:32',NULL),(3161,'food','Soup','','Hariyali Shorba',0,NULL,'[]','',66,'2026-04-16 14:16:32',NULL),(3162,'food','Soup','','Tomato Dhania',0,NULL,'[]','',67,'2026-04-16 14:16:32',NULL),(3163,'food','Soup','','Choice Of Cream Soup',0,NULL,'[]','',68,'2026-04-16 14:16:32',NULL),(3164,'food','Soup','','Murg Shorba',0,239.00,'{\"Chicken\":239}','',69,'2026-04-16 14:16:32',NULL),(3165,'food','Soup','','Cream Of Chicken Soup',0,259.00,'{\"Chicken\":259}','',70,'2026-04-16 14:16:32',NULL),(3166,'food','Soup','','Clear Broth',0,229.00,'{\"Chicken\":229,\"Prawns\":279}','',71,'2026-04-16 14:16:32',NULL),(3167,'food','Soup','','Lemon Corinder',0,229.00,'{\"Chicken\":229,\"Prawns\":279}','',72,'2026-04-16 14:16:32',NULL),(3168,'food','Soup','','Sweet Corn',0,229.00,'{\"Chicken\":229,\"Prawns\":279}','',73,'2026-04-16 14:16:32',NULL),(3169,'food','Soup','','Sour N Pepper',0,229.00,'{\"Chicken\":229,\"Prawns\":279}','',74,'2026-04-16 14:16:32',NULL),(3170,'food','Soup','','Asian Wok & Grill',0,259.00,'{\"Chicken\":259,\"Prawns\":279}','',75,'2026-04-16 14:16:32',NULL),(3171,'food','Soup','','Hakka',0,259.00,'{\"Chicken\":259,\"Prawns\":279}','',76,'2026-04-16 14:16:32',NULL),(3172,'food','Soup','','Burmese Traditinal Soup',0,249.00,'{\"Chicken\":249,\"Prawns\":299}','',77,'2026-04-16 14:16:32',NULL),(3173,'food','Soup','','Tom Kha',0,329.00,'{\"Chicken\":329,\"Prawns\":319}','',78,'2026-04-16 14:16:32',NULL),(3174,'food','Soup','','Crispy Wontan Soup',0,249.00,'{\"Chicken\":249,\"Prawns\":299}','',79,'2026-04-16 14:16:32',NULL),(3175,'food','Soup','','Bakso Ayam Soup',0,279.00,'{\"Chicken\":279}','',80,'2026-04-16 14:16:32',NULL),(3176,'food','Soup','','Spicy Dumpling Soup',0,279.00,'{\"Chicken\":279,\"Prawns\":289}','',81,'2026-04-16 14:16:32',NULL),(3177,'food','Koyle Ka Kamal','','Methi Mirchi Ka Paneer Tikka',0,NULL,'[]','',82,'2026-04-16 14:16:32',NULL),(3178,'food','Koyle Ka Kamal','','Paneer Dohara Tikka',0,NULL,'[]','',83,'2026-04-16 14:16:32',NULL),(3179,'food','Koyle Ka Kamal','','Classic Paneer',0,NULL,'[]','',84,'2026-04-16 14:16:32',NULL),(3180,'food','Koyle Ka Kamal','','Paneer Tikka Achari',0,NULL,'[]','',85,'2026-04-16 14:16:32',NULL),(3181,'food','Koyle Ka Kamal','','Paneer Tikka Ajwain',0,NULL,'[]','',86,'2026-04-16 14:16:32',NULL),(3182,'food','Koyle Ka Kamal','','Paneer Tikka Lasooni',0,NULL,'[]','',87,'2026-04-16 14:16:32',NULL),(3183,'food','Koyle Ka Kamal','','Paneer Malai Kali Miri',0,NULL,'[]','',88,'2026-04-16 14:16:32',NULL),(3184,'food','Koyle Ka Kamal','','Cheese Makai Seekh Kebab',0,NULL,'[]','',89,'2026-04-16 14:16:32',NULL),(3185,'food','Koyle Ka Kamal','','Veg Seekh',0,NULL,'[]','',90,'2026-04-16 14:16:32',NULL),(3186,'food','Koyle Ka Kamal','','Chilli Milli Seekh',0,NULL,'[]','',91,'2026-04-16 14:16:32',NULL),(3187,'food','Koyle Ka Kamal','','Soya Chaap Tikka',0,NULL,'[]','',92,'2026-04-16 14:16:32',NULL),(3188,'food','Koyle Ka Kamal','','Ajwani Soya Chaap',0,NULL,'[]','',93,'2026-04-16 14:16:32',NULL),(3189,'food','Koyle Ka Kamal','','Malai Soya Chaap',0,NULL,'[]','',94,'2026-04-16 14:16:32',NULL),(3190,'food','Koyle Ka Kamal','','Pahadi Soya Chaap',0,NULL,'[]','',95,'2026-04-16 14:16:32',NULL),(3191,'food','Koyle Ka Kamal','','Subz Shikampuri Kebab',0,NULL,'[]','',96,'2026-04-16 14:16:32',NULL),(3192,'food','Koyle Ka Kamal','','Paneer Naram Dil Kebab',0,NULL,'[]','',97,'2026-04-16 14:16:32',NULL),(3193,'food','Koyle Ka Kamal','','Mushroom Ki Nazakat',0,NULL,'[]','',98,'2026-04-16 14:16:32',NULL),(3194,'food','Koyle Ka Kamal','','Mushroom & Babycorn Tikka',0,NULL,'[]','',99,'2026-04-16 14:16:32',NULL),(3195,'food','Koyle Ka Kamal','','Veg.Cheese Crispy Rolls',0,NULL,'[]','',100,'2026-04-16 14:16:32',NULL),(3196,'food','Koyle Ka Kamal','','Assorted Tandoori Platter',0,NULL,'[]','',101,'2026-04-16 14:16:32',NULL),(3197,'food','Koyle Ka Kamal','','Murg Nazakat',0,529.00,'{\"Chicken\":529}','',102,'2026-04-16 14:16:32',NULL),(3198,'food','Koyle Ka Kamal','','Murg Ke Sholey',0,499.00,'{\"Chicken\":499}','',103,'2026-04-16 14:16:32',NULL),(3199,'food','Koyle Ka Kamal','','Sharabi Kebab',0,529.00,'{\"Chicken\":529}','',104,'2026-04-16 14:16:32',NULL),(3200,'food','Koyle Ka Kamal','','Murg Alishan Kebab',0,459.00,'{\"Chicken\":459}','',105,'2026-04-16 14:16:32',NULL),(3201,'food','Koyle Ka Kamal','','Tandoori Murgh  Half',0,439.00,'{\"Half\":439}','',106,'2026-04-16 14:16:32',NULL),(3202,'food','Koyle Ka Kamal','','Tandoori Murgh Full',0,699.00,'{\"Full\":699}','',107,'2026-04-16 14:16:32',NULL),(3203,'food','Koyle Ka Kamal','','Murgh Malai Tikka',0,479.00,'{\"Chicken\":479}','',108,'2026-04-16 14:16:32',NULL),(3204,'food','Koyle Ka Kamal','','Kalmi Kebab',0,529.00,'{\"Chicken\":529}','',109,'2026-04-16 14:16:32',NULL),(3205,'food','Koyle Ka Kamal','','Classic Chiken Tikka',0,479.00,'{\"Chicken\":479}','',110,'2026-04-16 14:16:32',NULL),(3206,'food','Koyle Ka Kamal','','Classic Chiken Tikka Banjara',0,479.00,'{\"Chicken\":479}','',111,'2026-04-16 14:16:32',NULL),(3207,'food','Koyle Ka Kamal','','Classic Chiken Tikka Pahadi',0,479.00,'{\"Chicken\":479}','',112,'2026-04-16 14:16:32',NULL),(3208,'food','Koyle Ka Kamal','','Classic Chiken Tikka  Achri',0,479.00,'{\"Chicken\":479}','',113,'2026-04-16 14:16:32',NULL),(3209,'food','Koyle Ka Kamal','','Classic Chiken Tikka   Ajwain',0,479.00,'{\"Chicken\":479}','',114,'2026-04-16 14:16:32',NULL),(3210,'food','Koyle Ka Kamal','','Tandoori Chicken Platter',0,1199.00,'{\"Chicken\":1199}','',115,'2026-04-16 14:16:32',NULL),(3211,'food','Koyle Ka Kamal','','Methi Mirch Ka Chicken Tikka',0,459.00,'{\"Chicken\":459}','',116,'2026-04-16 14:16:32',NULL),(3212,'food','Koyle Ka Kamal','','Murgh Galafi Seekh',0,459.00,'{\"Chicken\":459}','',117,'2026-04-16 14:16:32',NULL),(3213,'food','Koyle Ka Kamal','','Chicken Rolly Kebab',0,489.00,'{\"Chicken\":489}','',118,'2026-04-16 14:16:32',NULL),(3214,'food','Koyle Ka Kamal','','Muttan Lahori Seekh Kebaba',0,599.00,'{\"Mutton\":599}','',119,'2026-04-16 14:16:32',NULL),(3215,'food','Koyle Ka Kamal','','Tunda Kebeb',0,609.00,'{\"Mutton\":609}','',120,'2026-04-16 14:16:32',NULL),(3216,'food','Koyle Ka Kamal','','Mutton Boti Kebeb',0,589.00,'{\"Mutton\":589}','',121,'2026-04-16 14:16:32',NULL),(3217,'food','Koyle Ka Kamal','','Hariyali Pomfret',0,789.00,'{\"Pomfret\":789}','',122,'2026-04-16 14:16:32',NULL),(3218,'food','Koyle Ka Kamal','','Hariyali Surmai',0,759.00,'{\"Surmai\":759}','',123,'2026-04-16 14:16:32',NULL),(3219,'food','Koyle Ka Kamal','','Samundar Ki Shaan',0,789.00,'{\"Pomfret\":789}','',124,'2026-04-16 14:16:32',NULL),(3220,'food','Koyle Ka Kamal','','Jheenga Dum Ajwain',0,789.00,'{\"Prawns\":789}','',125,'2026-04-16 14:16:32',NULL),(3221,'food','Koyle Ka Kamal','','Classic Fish Tikka',0,559.00,'{\"Basa\":559}','',126,'2026-04-16 14:16:32',NULL),(3222,'food','Koyle Ka Kamal','','Classic Fish Tikka Ajwain',0,559.00,'{\"Basa\":559}','',127,'2026-04-16 14:16:32',NULL),(3223,'food','Koyle Ka Kamal','','Mahi Curry Patta Surmai',0,759.00,'{\"Surmai\":759}','',128,'2026-04-16 14:16:32',NULL),(3224,'food','Koyle Ka Kamal','','Mahi Curry Patta Basa',0,559.00,'{\"Basa\":559}','',129,'2026-04-16 14:16:32',NULL),(3225,'food','Koyle Ka Kamal','','Crab Tikka',0,NULL,'[]','',130,'2026-04-16 14:16:32',NULL),(3226,'food','Koyle Ka Kamal','','Tandoori Platter Non Veg',0,1899.00,'{\"Chicken\":1899}','',131,'2026-04-16 14:16:32',NULL),(3227,'food','Indian Mains Bageeche Se','','Punjabi Style Paneer Tikka Masala',0,NULL,'[]','',132,'2026-04-16 14:16:32',NULL),(3228,'food','Indian Mains Bageeche Se','','Paneer Amiritsari',0,NULL,'[]','',133,'2026-04-16 14:16:32',NULL),(3229,'food','Indian Mains Bageeche Se','','Paneer Lahori',0,NULL,'[]','',134,'2026-04-16 14:16:32',NULL),(3230,'food','Indian Mains Bageeche Se','','Desi Paneer Bhurji',0,NULL,'[]','',135,'2026-04-16 14:16:32',NULL),(3231,'food','Indian Mains Bageeche Se','','Paneer Lababadar',0,NULL,'[]','',136,'2026-04-16 14:16:32',NULL),(3232,'food','Indian Mains Bageeche Se','','Paneer Makhani',0,NULL,'[]','',137,'2026-04-16 14:16:32',NULL),(3233,'food','Indian Mains Bageeche Se','','Paneer Mirch Masala',0,NULL,'[]','',138,'2026-04-16 14:16:32',NULL),(3234,'food','Indian Mains Bageeche Se','','Palak Paneer',0,NULL,'[]','',139,'2026-04-16 14:16:32',NULL),(3235,'food','Indian Mains Bageeche Se','','Paneer Kasturi',0,NULL,'[]','',140,'2026-04-16 14:16:32',NULL),(3236,'food','Indian Mains Bageeche Se','','Paneer Do-Pyaza',0,NULL,'[]','',141,'2026-04-16 14:16:32',NULL),(3237,'food','Indian Mains Bageeche Se','','Paneer Kadhi',0,NULL,'[]','',142,'2026-04-16 14:16:32',NULL),(3238,'food','Indian Mains Bageeche Se','','Paneer Hand',0,NULL,'[]','',143,'2026-04-16 14:16:32',NULL),(3239,'food','Indian Mains Bageeche Se','','Malai Kofta',0,NULL,'[]','',144,'2026-04-16 14:16:32',NULL),(3240,'food','Indian Mains Bageeche Se','','Paneer Angara',0,NULL,'[]','',145,'2026-04-16 14:16:32',NULL),(3241,'food','Indian Mains Bageeche Se','','Paneer Pasanda',0,NULL,'[]','',146,'2026-04-16 14:16:32',NULL),(3242,'food','Indian Mains Bageeche Se','','Shahi Paneer',0,NULL,'[]','',147,'2026-04-16 14:16:32',NULL),(3243,'food','Indian Mains Bageeche Se','','Banarasi Kofta Curry',0,NULL,'[]','',148,'2026-04-16 14:16:32',NULL),(3244,'food','Indian Mains Bageeche Se','','Methi Matar Malai',0,NULL,'[]','',149,'2026-04-16 14:16:32',NULL),(3245,'food','Indian Mains Bageeche Se','','Methi Matar Masala',0,NULL,'[]','',150,'2026-04-16 14:16:32',NULL),(3246,'food','Indian Mains Bageeche Se','','Tandoori Dhingri Masala',0,NULL,'[]','',151,'2026-04-16 14:16:32',NULL),(3247,'food','Indian Mains Bageeche Se','','Maratha Vegetable Kofta Curry',0,NULL,'[]','',152,'2026-04-16 14:16:32',NULL),(3248,'food','Indian Mains Bageeche Se','','Masala Wangi',0,NULL,'[]','',153,'2026-04-16 14:16:32',NULL),(3249,'food','Indian Mains Bageeche Se','','Sarso Ka Saag (Seasonal)',0,NULL,'[]','',154,'2026-04-16 14:16:32',NULL),(3250,'food','Indian Mains Bageeche Se','','Veg Khurchan',0,NULL,'[]','',155,'2026-04-16 14:16:32',NULL),(3251,'food','Indian Mains Bageeche Se','','Navratna Kprma',0,NULL,'[]','',156,'2026-04-16 14:16:32',NULL),(3252,'food','Indian Mains Bageeche Se','','Tawa Sabzi',0,NULL,'[]','',157,'2026-04-16 14:16:32',NULL),(3253,'food','Indian Mains Bageeche Se','','Aloo Gobi Matar',0,NULL,'[]','',158,'2026-04-16 14:16:32',NULL),(3254,'food','Indian Mains Bageeche Se','','Aloo Matar',0,NULL,'[]','',159,'2026-04-16 14:16:32',NULL),(3255,'food','Indian Mains Bageeche Se','','Sev Bhaji',0,NULL,'[]','',160,'2026-04-16 14:16:32',NULL),(3256,'food','Indian Mains Bageeche Se','','Jeera Aloo',0,NULL,'[]','',161,'2026-04-16 14:16:32',NULL),(3257,'food','Indian Mains Bageeche Se','','Bhindi Kurkuri',0,NULL,'[]','',162,'2026-04-16 14:16:32',NULL),(3258,'food','Indian Mains Bageeche Se','','Bhindi Masala',0,NULL,'[]','',163,'2026-04-16 14:16:32',NULL),(3259,'food','Indian Mains Bageeche Se','','Veg Kolhapuri',0,NULL,'[]','',164,'2026-04-16 14:16:32',NULL),(3260,'food','Indian Mains Bageeche Se','','Diwani Handi',0,NULL,'[]','',165,'2026-04-16 14:16:32',NULL),(3261,'food','Indian Mains Bageeche Se','','Namaste Kalyan Special Veg',0,NULL,'[]','',166,'2026-04-16 14:16:32',NULL),(3262,'food','Indian Mains Bageeche Se','','Veg Kadai',0,NULL,'[]','',167,'2026-04-16 14:16:32',NULL),(3263,'food','Indian Mains Bageeche Se','','Veg Patiala',0,NULL,'[]','',168,'2026-04-16 14:16:32',NULL),(3264,'food','Indian Mains Bageeche Se','','Lasooni Palak',0,NULL,'[]','',169,'2026-04-16 14:16:32',NULL),(3265,'food','Indian Mains Bageeche Se','','Lasooni Methi',0,NULL,'[]','',170,'2026-04-16 14:16:32',NULL),(3266,'food','Indian Mains Bageeche Se','','Veg Haryali',0,NULL,'[]','',171,'2026-04-16 14:16:32',NULL),(3267,'food','Indian Mains Bageeche Se','','Mushroom Tikka Masala',0,NULL,'[]','',172,'2026-04-16 14:16:32',NULL),(3268,'food','Indian Mains Bageeche Se','','Mix Veg',0,NULL,'[]','',173,'2026-04-16 14:16:32',NULL),(3269,'food','Dal Wali Gali','','Dal Tadka',0,NULL,'[]','',174,'2026-04-16 14:16:32',NULL),(3270,'food','Dal Wali Gali','','Dal Fry',0,NULL,'[]','',175,'2026-04-16 14:16:32',NULL),(3271,'food','Dal Wali Gali','','Dal Panchmel',0,NULL,'[]','',176,'2026-04-16 14:16:32',NULL),(3272,'food','Dal Wali Gali','','Dal Makhan Wali',0,NULL,'[]','',177,'2026-04-16 14:16:32',NULL),(3273,'food','Dal Wali Gali','','Punjabi Kadhi',0,NULL,'[]','',178,'2026-04-16 14:16:32',NULL),(3274,'food','Dal Wali Gali','','Dal Palak',0,NULL,'[]','',179,'2026-04-16 14:16:32',NULL),(3275,'food','Dal Wali Gali','','Dal Methi',0,NULL,'[]','',180,'2026-04-16 14:16:32',NULL),(3276,'food','Murg Curry Specialities','','Murgh Makhani(Butter Chicken)',0,489.00,'{\"Chicken\":489}','',181,'2026-04-16 14:16:32',NULL),(3277,'food','Murg Curry Specialities','','Kukul Mass',0,469.00,'{\"Chicken\":469}','',182,'2026-04-16 14:16:32',NULL),(3278,'food','Murg Curry Specialities','','Chicken Malvani',0,469.00,'{\"Chicken\":469}','',183,'2026-04-16 14:16:32',NULL),(3279,'food','Murg Curry Specialities','','Murgh Rara',0,499.00,'{\"Chicken\":499}','',184,'2026-04-16 14:16:32',NULL),(3280,'food','Murg Curry Specialities','','Murgh Kadhai Mirichi Wala',0,469.00,'{\"Chicken\":469}','',185,'2026-04-16 14:16:32',NULL),(3281,'food','Murg Curry Specialities','','Kolhapuri',0,469.00,'{\"Chicken\":469}','',186,'2026-04-16 14:16:32',NULL),(3282,'food','Murg Curry Specialities','','Do-Pyaza',0,469.00,'{\"Chicken\":469}','',187,'2026-04-16 14:16:32',NULL),(3283,'food','Murg Curry Specialities','','Murgh Tikka Masala',0,499.00,'{\"Chicken\":499}','',188,'2026-04-16 14:16:32',NULL),(3284,'food','Murg Curry Specialities','','Murgh Khurchan',0,499.00,'{\"Chicken\":499}','',189,'2026-04-16 14:16:32',NULL),(3285,'food','Murg Curry Specialities','','Chicken Kala Masala',0,479.00,'{\"Chicken\":479}','',190,'2026-04-16 14:16:32',NULL),(3286,'food','Murg Curry Specialities','','Chicken Kebab Masala',0,499.00,'{\"Chicken\":499}','',191,'2026-04-16 14:16:32',NULL),(3287,'food','Murg Curry Specialities','','Chicken Saagwala',0,489.00,'{\"Chicken\":489}','',192,'2026-04-16 14:16:32',NULL),(3288,'food','Murg Curry Specialities','','Murgh Khada Masala',0,499.00,'{\"Chicken\":499}','',193,'2026-04-16 14:16:32',NULL),(3289,'food','Murg Curry Specialities','','Tandoori Chicken Masala Half',0,499.00,'{\"Half\":499}','',194,'2026-04-16 14:16:32',NULL),(3290,'food','Murg Curry Specialities','','Tandoori Chicken Masala Full',0,899.00,'{\"Full\":899}','',195,'2026-04-16 14:16:32',NULL),(3291,'food','Murg Curry Specialities','','Chicken Handi Half',0,479.00,'{\"Half\":479}','',196,'2026-04-16 14:16:32',NULL),(3292,'food','Murg Curry Specialities','','Chicken Handi Full',0,799.00,'{\"Full\":799}','',197,'2026-04-16 14:16:32',NULL),(3293,'food','Murg Curry Specialities','','Egg Masala',0,379.00,'{\"Egg\":379}','',198,'2026-04-16 14:16:32',NULL),(3294,'food','Ghosht Curry Specialities','','Mutton Mataha',0,599.00,'{\"Mutton\":599}','',199,'2026-04-16 14:16:32',NULL),(3295,'food','Ghosht Curry Specialities','','Deo Gosht',0,609.00,'{\"Mutton\":609}','',200,'2026-04-16 14:16:32',NULL),(3296,'food','Ghosht Curry Specialities','','Kashmiri Rogan Josh',0,629.00,'{\"Mutton\":629}','',201,'2026-04-16 14:16:32',NULL),(3297,'food','Ghosht Curry Specialities','','Bhuna Mitton Chaap',0,659.00,'{\"Mutton\":659}','',202,'2026-04-16 14:16:32',NULL),(3298,'food','Ghosht Curry Specialities','','Gosht Khada Masala',0,609.00,'{\"Mutton\":609}','',203,'2026-04-16 14:16:32',NULL),(3299,'food','Ghosht Curry Specialities','','Keema Muttar',0,579.00,'{\"Mutton\":579}','',204,'2026-04-16 14:16:32',NULL),(3300,'food','Ghosht Curry Specialities','','Keema Muttar Masala',0,579.00,'{\"Mutton\":579}','',205,'2026-04-16 14:16:32',NULL),(3301,'food','Ghosht Curry Specialities','','Bhuna Mitton  Pepper Fry',0,629.00,'{\"Mutton\":629}','',206,'2026-04-16 14:16:32',NULL),(3302,'food','Fish Curry Specialities','','Pomfret Masala',0,799.00,'{\"Pomfret\":799}','',207,'2026-04-16 14:16:32',NULL),(3303,'food','Fish Curry Specialities','','Surmai Masala',0,799.00,'{\"Surmai\":799}','',208,'2026-04-16 14:16:32',NULL),(3304,'food','Fish Curry Specialities','','Fish Tikka Masala',0,629.00,'{\"Basa\":629}','',209,'2026-04-16 14:16:32',NULL),(3305,'food','Fish Curry Specialities','','Prawns Hara Dhaniya',0,749.00,'{\"Prawns\":749}','',210,'2026-04-16 14:16:32',NULL),(3306,'food','Fish Curry Specialities','','Lobster Curry',0,NULL,'[]','',211,'2026-04-16 14:16:32',NULL),(3307,'food','Fish Curry Specialities','','Crab Masala',0,NULL,'[]','',212,'2026-04-16 14:16:32',NULL),(3308,'food','Fish Curry Specialities','','Jhinga Masaleder',0,749.00,'{\"Prawns\":749}','',213,'2026-04-16 14:16:32',NULL),(3309,'food','Fish Curry Specialities','','Prawns Chettinad',0,749.00,'{\"Prawns\":749}','',214,'2026-04-16 14:16:32',NULL),(3310,'food','Breads','','Tandoori Roti',0,49.00,'{\"Plain\":49,\"Butter\":64}','',215,'2026-04-16 14:16:32',NULL),(3311,'food','Breads','','Naan',0,89.00,'{\"Plain\":89,\"Butter\":104}','',216,'2026-04-16 14:16:32',NULL),(3312,'food','Breads','','Roomali Roti',0,129.00,'{\"Butter\":129}','',217,'2026-04-16 14:16:32',NULL),(3313,'food','Breads','','Warqi Paratha',0,109.00,'{\"Butter\":109}','',218,'2026-04-16 14:16:32',NULL),(3314,'food','Breads','','Lachcha Paratha',0,99.00,'{\"Plain\":99,\"Butter\":114}','',219,'2026-04-16 14:16:32',NULL),(3315,'food','Breads','','Garlic Naan',0,149.00,'{\"Plain\":149,\"Butter\":164}','',220,'2026-04-16 14:16:32',NULL),(3316,'food','Breads','','Missi Roti',0,99.00,'{\"Plain\":99,\"Butter\":114}','',221,'2026-04-16 14:16:32',NULL),(3317,'food','Breads','','Kulcha',0,99.00,'{\"Plain\":99,\"Butter\":114}','',222,'2026-04-16 14:16:32',NULL),(3318,'food','Breads','','Stuffed Paratha(Aloo/Gobi/Mint/Methi/Ajwain)',0,189.00,'{\"Plain\":189,\"Butter\":204}','',223,'2026-04-16 14:16:32',NULL),(3319,'food','Breads','','Onion Garlic Kulcha',0,169.00,'{\"Plain\":169,\"Butter\":184}','',224,'2026-04-16 14:16:32',NULL),(3320,'food','Breads','','Afgani Naan',0,179.00,'{\"Plain\":179,\"Butter\":194}','',225,'2026-04-16 14:16:32',NULL),(3321,'food','Breads','','Malabar Paratha',0,99.00,'{\"Plain\":99,\"Butter\":114}','',226,'2026-04-16 14:16:32',NULL),(3322,'food','Breads','','Cheese Garlic Naan',0,179.00,'{\"Plain\":179,\"Butter\":194}','',227,'2026-04-16 14:16:32',NULL),(3323,'food','Breads','','Har Mirch Ka Paratha',0,129.00,'{\"Plain\":129,\"Butter\":144}','',228,'2026-04-16 14:16:32',NULL),(3324,'food','Breads','','Lal Mirch Ka Paratha',0,129.00,'{\"Plain\":129,\"Butter\":144}','',229,'2026-04-16 14:16:32',NULL),(3325,'food','Breads','','Olive Naan',0,179.00,'{\"Plain\":179,\"Butter\":194}','',230,'2026-04-16 14:16:32',NULL),(3326,'food','Breads','','Olive Paratha',0,179.00,'{\"Plain\":179,\"Butter\":194}','',231,'2026-04-16 14:16:32',NULL),(3327,'food','Breads','','Makkai Ki Roti',0,109.00,'{\"Plain\":109,\"Butter\":124}','',232,'2026-04-16 14:16:32',NULL),(3328,'food','Breads','','Assorted Bread Basket',0,479.00,'{\"Butter\":479}','',233,'2026-04-16 14:16:32',NULL),(3329,'food','Rice','','Sada Basmati Chawal',0,NULL,'[]','',234,'2026-04-16 14:16:32',NULL),(3330,'food','Rice','','Jeera Rice',0,189.00,'{\"Half\":189,\"Full\":279}','',235,'2026-04-16 14:16:32',NULL),(3331,'food','Rice','','Peas Pulao',0,NULL,'[]','',236,'2026-04-16 14:16:32',NULL),(3332,'food','Rice','','Veg Pulao',0,NULL,'[]','',237,'2026-04-16 14:16:32',NULL),(3333,'food','Rice','','Curd Rice',0,NULL,'[]','',238,'2026-04-16 14:16:32',NULL),(3334,'food','Rice','','Dal Khichdi',0,NULL,'[]','',239,'2026-04-16 14:16:32',NULL),(3335,'food','Rice','','Kadhi Khichdi',0,NULL,'[]','',240,'2026-04-16 14:16:32',NULL),(3336,'food','Rice','','Palak Khichdi',0,NULL,'[]','',241,'2026-04-16 14:16:32',NULL),(3337,'food','Rice','','Paneer Pulao',0,NULL,'[]','',242,'2026-04-16 14:16:32',NULL),(3338,'food','Rice','','Kasmiri Pulao',0,NULL,'[]','',243,'2026-04-16 14:16:32',NULL),(3339,'food','Rice','','Subz Dum',0,NULL,'[]','',244,'2026-04-16 14:16:32',NULL),(3340,'food','Rice','','Veg Hydrabadi Biryani',0,NULL,'[]','',245,'2026-04-16 14:16:32',NULL),(3341,'food','Rice','','Avadhi Murgh Biryani',0,499.00,'{\"Chicken\":499}','',246,'2026-04-16 14:16:32',NULL),(3342,'food','Rice','','Egg Biryani',0,369.00,'{\"Egg\":369}','',247,'2026-04-16 14:16:32',NULL),(3343,'food','Rice','','Kachcha Ghost Biryani',0,629.00,'{\"Mutton\":629}','',248,'2026-04-16 14:16:32',NULL),(3344,'food','Rice','','Hyderabadi Muttan Biryani',0,629.00,'{\"Mutton\":629}','',249,'2026-04-16 14:16:32',NULL),(3345,'food','Rice','','Prawns Dum Biryani',0,629.00,'{\"Prawns\":629}','',250,'2026-04-16 14:16:32',NULL),(3346,'food','Dimsum','','Asian Wok & Grill (6 Pcs)',0,NULL,'[]','',251,'2026-04-16 14:16:32',NULL),(3347,'food','Dimsum','','Cottage Cheese & Broccoli',0,NULL,'[]','',252,'2026-04-16 14:16:32',NULL),(3348,'food','Dimsum','','Mushroom & Waterchestnut',0,NULL,'[]','',253,'2026-04-16 14:16:32',NULL),(3349,'food','Dimsum','','Hot Basil',0,399.00,'{\"Chicken\":399}','',254,'2026-04-16 14:16:32',NULL),(3350,'food','Dimsum','','Poached Beijing',0,369.00,'{\"Chicken\":369}','',255,'2026-04-16 14:16:32',NULL),(3351,'food','Dimsum','','Suimai Dimsum',0,429.00,'{\"Chicken\":429}','',256,'2026-04-16 14:16:32',NULL),(3352,'food','Dimsum','','Har Gao Dim Sum',0,509.00,'{\"Basa\":509}','',257,'2026-04-16 14:16:32',NULL),(3353,'food','Dimsum','','Sambal Dimsum',0,509.00,'{\"Basa\":509}','',258,'2026-04-16 14:16:32',NULL),(3354,'food','Bao','','Fire Cracker Bao',0,NULL,'[]','',259,'2026-04-16 14:16:32',NULL),(3355,'food','Bao','','Tempura',0,NULL,'[]','',260,'2026-04-16 14:16:32',NULL),(3356,'food','Bao','','Chilli Bao',0,429.00,'{\"Chicken\":429}','',261,'2026-04-16 14:16:32',NULL),(3357,'food','Bao','','Sriracha Prawn Bao',0,479.00,'{\"Prawns\":479}','',262,'2026-04-16 14:16:32',NULL),(3358,'food','Bao','','Stuffed Bao',0,429.00,'{\"Chicken\":429}','',263,'2026-04-16 14:16:32',NULL),(3359,'food','Bao','','Curried Chicken',0,429.00,'{\"Chicken\":429}','',264,'2026-04-16 14:16:32',NULL),(3360,'food','Bao','','Butter Chicken Bao',0,429.00,'{\"Chicken\":429}','',265,'2026-04-16 14:16:32',NULL),(3361,'food','Chicken Wings','','Drums Of Heaven Fry',0,449.00,'{\"Chicken\":449}','',266,'2026-04-16 14:16:32',NULL),(3362,'food','Chicken Wings','','Drums Of Heaven Toasted',0,449.00,'{\"Chicken\":449}','',267,'2026-04-16 14:16:32',NULL),(3363,'food','Chicken Wings','','Barbeqe',0,449.00,'{\"Chicken\":449}','',268,'2026-04-16 14:16:32',NULL),(3364,'food','Chicken Wings','','Hong Kong Style',0,449.00,'{\"Chicken\":449}','',269,'2026-04-16 14:16:32',NULL),(3365,'food','Chicken Wings','','Korean',0,449.00,'{\"Chicken\":449}','',270,'2026-04-16 14:16:32',NULL),(3366,'food','Rolls','','Awg Classic',0,399.00,'{\"Chicken\":399,\"Prawns\":439}','',271,'2026-04-16 14:16:32',NULL),(3367,'food','Rolls','','Cigar',0,NULL,'[]','',272,'2026-04-16 14:16:32',NULL),(3368,'food','Rolls','','Popia Tod',0,399.00,'{\"Chicken\":399,\"Prawns\":479}','',273,'2026-04-16 14:16:32',NULL),(3369,'food','Lettuce Cups','','Asian Vegetables',0,NULL,'[]','',274,'2026-04-16 14:16:32',NULL),(3370,'food','Lettuce Cups','','Thai Chicken',0,459.00,'{\"Chicken\":459}','',275,'2026-04-16 14:16:32',NULL),(3371,'food','Lettuce Cups','','Barbeque Chicken',0,479.00,'{\"Chicken\":479}','',276,'2026-04-16 14:16:32',NULL),(3372,'food','Deep Fry','','Corn Cheese Balls',0,NULL,'[]','',277,'2026-04-16 14:16:32',NULL),(3373,'food','Deep Fry','','Japanese Korokke',0,479.00,'{\"Chicken\":479}','',278,'2026-04-16 14:16:32',NULL),(3374,'food','Deep Fry','','Tempura',0,609.00,'{\"Prawns\":609}','',279,'2026-04-16 14:16:32',NULL),(3375,'food','Deep Fry','','Chow;S Finger Chips',0,NULL,'[]','',280,'2026-04-16 14:16:32',NULL),(3376,'food','Deep Fry','','Cheese Finger Chips',0,NULL,'[]','',281,'2026-04-16 14:16:32',NULL),(3377,'food','Deep Fry','','Peri Peri Chips',0,NULL,'[]','',282,'2026-04-16 14:16:32',NULL),(3378,'food','Deep Fry','','Chinese Fried',0,489.00,'{\"Chicken\":489}','',283,'2026-04-16 14:16:32',NULL),(3379,'food','From The Grill','','Corn Cheese & Water Chestnut',0,NULL,'[]','',284,'2026-04-16 14:16:32',NULL),(3380,'food','From The Grill','','Assorted Mushroom',0,NULL,'[]','',285,'2026-04-16 14:16:32',NULL),(3381,'food','From The Grill','','Asian Barbeque',0,499.00,'{\"Chicken\":499}','',286,'2026-04-16 14:16:32',NULL),(3382,'food','From The Grill','','Lemongrass',0,429.00,'{\"Chicken\":429}','',287,'2026-04-16 14:16:32',NULL),(3383,'food','From The Grill','','Sriracha',0,NULL,'[]','',288,'2026-04-16 14:16:32',NULL),(3384,'food','From The Grill','','Chilli Basil',0,499.00,'{\"Chicken\":499,\"Prawns\":539}','',289,'2026-04-16 14:16:32',NULL),(3385,'food','From The Grill','','Lemongrass Pomfret',0,799.00,'{\"Surmai\":799,\"Pomfret\":799}','',290,'2026-04-16 14:16:32',NULL),(3386,'food','From The Grill','','Pan Fried Chilli',0,439.00,'{\"Basa\":439,\"Surmai\":749,\"Pomfret\":799}','',291,'2026-04-16 14:16:32',NULL),(3387,'food','Skewers','','Hot Basil',0,NULL,'[]','',292,'2026-04-16 14:16:32',NULL),(3388,'food','Skewers','','Satay Gai',0,579.00,'{\"Chicken\":579}','',293,'2026-04-16 14:16:32',NULL),(3389,'food','Skewers','','Fresh Sambal',0,699.00,'{\"Prawns\":699}','',294,'2026-04-16 14:16:32',NULL),(3390,'food','Skewers','','Sesame Lime',0,699.00,'{\"Prawns\":699}','',295,'2026-04-16 14:16:32',NULL),(3391,'food','Sizzling Plate','','Hunan',0,NULL,'[]','',296,'2026-04-16 14:16:32',NULL),(3392,'food','Sizzling Plate','','Barbeque Onions Chicken',0,549.00,'{\"Chicken\":549}','',297,'2026-04-16 14:16:32',NULL),(3393,'food','Sizzling Plate','','Taipei Chicken',0,549.00,'{\"Chicken\":549}','',298,'2026-04-16 14:16:32',NULL),(3394,'food','Sizzling Plate','','Smoked Prawns',0,629.00,'{\"Prawns\":629}','',299,'2026-04-16 14:16:32',NULL),(3395,'food','Sizzling Plate','','Butter Garlic Prawns',0,629.00,'{\"Prawns\":629}','',300,'2026-04-16 14:16:32',NULL),(3396,'food','Wok Tossed','','Crispy Corn & Waterchestut',0,NULL,'[]','',301,'2026-04-16 14:16:32',NULL),(3397,'food','Wok Tossed','','Spicy Broccoli',0,NULL,'[]','',302,'2026-04-16 14:16:32',NULL),(3398,'food','Wok Tossed','','Kung Pao',0,489.00,'{\"Chicken\":489}','',303,'2026-04-16 14:16:32',NULL),(3399,'food','Wok Tossed','','Five Spice',0,NULL,'[]','',304,'2026-04-16 14:16:32',NULL),(3400,'food','Wok Tossed','','Chilli Classiccottage Cheese',0,479.00,'{\"Chicken\":479}','',305,'2026-04-16 14:16:32',NULL),(3401,'food','Wok Tossed','','Chilli Classic Mushroom',0,NULL,'[]','',306,'2026-04-16 14:16:32',NULL),(3402,'food','Wok Tossed','','Hunan Sesame Style Tofu/ Cottage Cheese',0,479.00,'{\"Chicken\":479,\"Mutton\":649,\"Basa\":539}','',307,'2026-04-16 14:16:32',NULL),(3403,'food','Wok Tossed','','Classic Manchurian Dry',0,479.00,'{\"Chicken\":479}','',308,'2026-04-16 14:16:32',NULL),(3404,'food','Wok Tossed','','Fresh Red Pepper',0,499.00,'{\"Chicken\":499}','',309,'2026-04-16 14:16:32',NULL),(3405,'food','Wok Tossed','','Asian Wok & Grill Cottage Cheese / Chicken/Fish /Prawns',0,499.00,'{\"Chicken\":499,\"Basa\":559,\"Prawns\":629}','',310,'2026-04-16 14:16:32',NULL),(3406,'food','Wok Tossed','','Hot Basil',0,459.00,'{\"Chicken\":459,\"Basa\":629}','',311,'2026-04-16 14:16:32',NULL),(3407,'food','Wok Tossed','','Asian Crispy Sesame',0,459.00,'{\"Chicken\":459}','',312,'2026-04-16 14:16:32',NULL),(3408,'food','Wok Tossed','','Butter Garlic',0,459.00,'{\"Chicken\":459,\"Basa\":559,\"Prawns\":629}','',313,'2026-04-16 14:16:32',NULL),(3409,'food','Wok Tossed','','Thai Spice',0,559.00,'{\"Basa\":559,\"Prawns\":629}','',314,'2026-04-16 14:16:32',NULL),(3410,'food','Wok Tossed','','Sichuan',0,459.00,'{\"Chicken\":459,\"Prawns\":629}','',315,'2026-04-16 14:16:32',NULL),(3411,'food','Sizzlers','','Awg Classic',0,799.00,'{\"Chicken\":799,\"Prawns\":899}','',316,'2026-04-16 14:16:32',NULL),(3412,'food','Sizzlers','','Fussion Mexican',0,799.00,'{\"Chicken\":799,\"Prawns\":899}','',317,'2026-04-16 14:16:32',NULL),(3413,'food','Sizzlers','','Classic Manchurian',0,799.00,'{\"Chicken\":799,\"Prawns\":899}','',318,'2026-04-16 14:16:32',NULL),(3414,'food','Sizzlers','','Smoking Cheese Balls Sambal',0,799.00,'{\"Chicken\":799,\"Prawns\":899}','',319,'2026-04-16 14:16:32',NULL),(3415,'food','Curries','','Kari Kapitan',0,549.00,'{\"Chicken\":549,\"Basa\":599,\"Prawns\":699}','',320,'2026-04-16 14:16:32',NULL),(3416,'food','Curries','','Kaeng Phet',0,549.00,'{\"Chicken\":549,\"Basa\":599,\"Prawns\":699}','',321,'2026-04-16 14:16:32',NULL),(3417,'food','Curries','','Gaeng Kiew Wan',0,549.00,'{\"Chicken\":549,\"Basa\":599,\"Prawns\":699}','',322,'2026-04-16 14:16:32',NULL),(3418,'food','Curries','','Shrimp& Mushroom Thai Red Curry',0,599.00,'{\"Prawns\":599}','',323,'2026-04-16 14:16:32',NULL),(3419,'food','Main Course','','Asian Veggies Red Pepper Sauce',0,479.00,'{\"Chicken\":479,\"Basa\":559,\"Prawns\":699}','',324,'2026-04-16 14:16:32',NULL),(3420,'food','Main Course','','Stir Fried Asian Green With Chilli Basil',0,NULL,'[]','',325,'2026-04-16 14:16:32',NULL),(3421,'food','Main Course','','Shanghi Style',0,479.00,'{\"Chicken\":479,\"Basa\":549,\"Prawns\":699}','',326,'2026-04-16 14:16:32',NULL),(3422,'food','Main Course','','Classic Manchurian Gravy',0,449.00,'{\"Chicken\":449}','',327,'2026-04-16 14:16:32',NULL),(3423,'food','Main Course','','Sichuan',0,479.00,'{\"Chicken\":479,\"Basa\":549,\"Prawns\":699}','',328,'2026-04-16 14:16:32',NULL),(3424,'food','Main Course','','Classic Chilli Oyster',0,479.00,'{\"Chicken\":479,\"Basa\":549,\"Prawns\":649}','',329,'2026-04-16 14:16:32',NULL),(3425,'food','Main Course','','Burma Delight',0,NULL,'[]','',330,'2026-04-16 14:16:32',NULL),(3426,'food','Main Course','','Pepper & Garlic',0,479.00,'{\"Chicken\":479,\"Basa\":549,\"Prawns\":699}','',331,'2026-04-16 14:16:32',NULL),(3427,'food','Main Course','','Dak Galbi',0,549.00,'{\"Chicken\":549}','',332,'2026-04-16 14:16:32',NULL),(3428,'food','Main Course','','Ca Oup Xa',0,549.00,'{\"Chicken\":549,\"Basa\":549}','',333,'2026-04-16 14:16:32',NULL),(3429,'food','Main Course','','Brunt Garlic Fish',0,599.00,'{\"Basa\":599,\"Prawns\":699}','',334,'2026-04-16 14:16:32',NULL),(3430,'food','Main Course','','Prawns With Seasonal Greens',0,699.00,'{\"Prawns\":699}','',335,'2026-04-16 14:16:32',NULL),(3431,'food','Bowls','','Thukpa',0,559.00,'{\"Chicken\":559,\"Basa\":649,\"Prawns\":609}','',336,'2026-04-16 14:16:32',NULL),(3432,'food','Bowls','','Laksa',0,559.00,'{\"Chicken\":559,\"Basa\":649,\"Prawns\":609}','',337,'2026-04-16 14:16:32',NULL),(3433,'food','Bowls','','Khow Suey',0,649.00,'{\"Chicken\":649,\"Basa\":699,\"Prawns\":699}','',338,'2026-04-16 14:16:32',NULL),(3434,'food','Bowls','','Asian Noodles',0,559.00,'{\"Chicken\":559}','',339,'2026-04-16 14:16:32',NULL),(3435,'food','Bowls','','Ramen Bowl',0,549.00,'{\"Chicken\":549,\"Basa\":669}','',340,'2026-04-16 14:16:32',NULL),(3436,'food','Rice','','Brunt Garlic Rice',0,429.00,'{\"Chicken\":429,\"Prawns\":529}','',341,'2026-04-16 14:16:32',NULL),(3437,'food','Rice','','Steam Rice',0,NULL,'[]','',342,'2026-04-16 14:16:32',NULL),(3438,'food','Rice','','Steam Jasmin Rice',0,NULL,'[]','',343,'2026-04-16 14:16:32',NULL),(3439,'food','Rice','','Mongolian Pot Rice',0,499.00,'{\"Chicken\":499,\"Prawns\":579}','',344,'2026-04-16 14:16:32',NULL),(3440,'food','Rice','','Korean',0,429.00,'{\"Chicken\":429,\"Prawns\":479}','',345,'2026-04-16 14:16:32',NULL),(3441,'food','Rice','','Chilli Garlic',0,389.00,'{\"Chicken\":389,\"Prawns\":479}','',346,'2026-04-16 14:16:32',NULL),(3442,'food','Rice','','Sichuan',0,389.00,'{\"Chicken\":389,\"Prawns\":479}','',347,'2026-04-16 14:16:32',NULL),(3443,'food','Rice','','Awg Classic',0,429.00,'{\"Chicken\":429,\"Prawns\":469}','',348,'2026-04-16 14:16:32',NULL),(3444,'food','Rice','','Classic Fried Rice',0,389.00,'{\"Chicken\":389,\"Prawns\":479}','',349,'2026-04-16 14:16:32',NULL),(3445,'food','Rice','','Jasmine Brunt Garlic Fried Rice',0,449.00,'{\"Chicken\":449,\"Prawns\":529}','',350,'2026-04-16 14:16:32',NULL),(3446,'food','Rice','','Khao Pad Prik',0,409.00,'{\"Chicken\":409}','',351,'2026-04-16 14:16:32',NULL),(3447,'food','Rice','','Nasi Goreng',0,559.00,'{\"Chicken\":559,\"Prawns\":629}','',352,'2026-04-16 14:16:32',NULL),(3448,'food','Rice','','Egg Fried Rice',0,309.00,'{\"Egg\":309}','',353,'2026-04-16 14:16:32',NULL),(3449,'food','Noodles','','Pan Fried White Sauce',0,529.00,'{\"Chicken\":529,\"Prawns\":569}','',354,'2026-04-16 14:16:32',NULL),(3450,'food','Noodles','','Pan Fried  Hunan',0,529.00,'{\"Chicken\":529,\"Prawns\":569}','',355,'2026-04-16 14:16:32',NULL),(3451,'food','Noodles','','Pan Fried  Sichuan',0,529.00,'{\"Chicken\":529,\"Prawns\":569}','',356,'2026-04-16 14:16:32',NULL),(3452,'food','Noodles','','Hot Pepper Garlic',0,479.00,'{\"Chicken\":479,\"Prawns\":499}','',357,'2026-04-16 14:16:32',NULL),(3453,'food','Noodles','','Hakka',0,399.00,'{\"Chicken\":399,\"Prawns\":409}','',358,'2026-04-16 14:16:32',NULL),(3454,'food','Noodles','','Sichuan',0,389.00,'{\"Chicken\":389,\"Prawns\":479}','',359,'2026-04-16 14:16:32',NULL),(3455,'food','Noodles','','American Chop Suey',0,509.00,'{\"Chicken\":509}','',360,'2026-04-16 14:16:32',NULL),(3456,'food','Noodles','','Korean',0,479.00,'{\"Chicken\":479,\"Prawns\":499}','',361,'2026-04-16 14:16:32',NULL),(3457,'food','Noodles','','All Time Favourite Chow Mein',0,479.00,'{\"Chicken\":479,\"Prawns\":499}','',362,'2026-04-16 14:16:32',NULL),(3458,'food','Noodles','','Cantonses',0,479.00,'{\"Chicken\":479,\"Prawns\":499}','',363,'2026-04-16 14:16:32',NULL),(3459,'food','Noodles','','Pad Thai',0,459.00,'{\"Chicken\":459,\"Prawns\":509}','',364,'2026-04-16 14:16:32',NULL),(3460,'food','Western Specialities','','Cheese Nachos With Salsa',0,NULL,'[]','',365,'2026-04-16 14:16:32',NULL),(3461,'food','Western Specialities','','Corn And Spinach Au Gratin',0,NULL,'[]','',366,'2026-04-16 14:16:32',NULL),(3462,'food','Pasta','','Penne With Tomato Basil Sauce',0,499.00,'{\"Chicken\":499}','',367,'2026-04-16 14:16:32',NULL),(3463,'food','Pasta','','Mac N Cheese',0,499.00,'{\"Chicken\":499}','',368,'2026-04-16 14:16:32',NULL),(3464,'food','Pasta','','Macaroni With Exotic Veg With Creamy Sauce',0,499.00,'{\"Chicken\":499}','',369,'2026-04-16 14:16:32',NULL),(3465,'food','Western Specialities','','Roasted Chicken',0,499.00,'{\"Chicken\":499}','',370,'2026-04-16 14:16:32',NULL),(3466,'food','Western Specialities','','Grilled Chicken',0,499.00,'{\"Chicken\":499}','',371,'2026-04-16 14:16:32',NULL),(3467,'food','Pasta','','Spaghettti Aglio E Olio With Chicken Sausages',0,529.00,'{\"Chicken\":529}','',372,'2026-04-16 14:16:32',NULL),(3468,'food','Pasta','','Fusilli With Grilled Chicken,Cherry Tomato In Cream Sauce',0,529.00,'{\"Chicken\":529}','',373,'2026-04-16 14:16:32',NULL),(3469,'food','Pasta','','Makr Your Own Pasta-Penne/Spaghetti/Fusilli/Macaroni',0,499.00,'{\"Chicken\":499}','',374,'2026-04-16 14:16:32',NULL),(3470,'food','Pasta','','Homemaade Baked Lasagna',0,599.00,'{\"Chicken\":599}','',375,'2026-04-16 14:16:32',NULL),(3471,'food','Pizza','','Tomato Basil Pizza',0,269.00,'{\"Medium\":269,\"Large\":369}','',376,'2026-04-16 14:16:32',NULL),(3472,'food','Pizza','','Margarita Pizza',0,269.00,'{\"Medium\":269,\"Large\":369}','',377,'2026-04-16 14:16:32',NULL),(3473,'food','Pizza','','Veg  Delight Pizza',0,289.00,'{\"Medium\":289,\"Large\":389}','',378,'2026-04-16 14:16:32',NULL),(3474,'food','Pizza','','Tandoori Paneer',0,329.00,'{\"Medium\":329,\"Large\":409}','',379,'2026-04-16 14:16:32',NULL),(3475,'food','Pizza','','Mushroom Veg Pizza',0,289.00,'{\"Medium\":289,\"Large\":389}','',380,'2026-04-16 14:16:32',NULL),(3476,'food','Pizza','','Bbq Chicken Pizza',0,399.00,'{\"Medium\":399,\"Large\":479}','',381,'2026-04-16 14:16:32',NULL),(3477,'food','Pizza','','Chicken Pizza',0,359.00,'{\"Medium\":359,\"Large\":479}','',382,'2026-04-16 14:16:32',NULL),(3478,'food','Pizza','','Chiken Peri-Peri Pizza',0,399.00,'{\"Medium\":399,\"Large\":479}','',383,'2026-04-16 14:16:32',NULL),(3479,'food','Dessert','','Fried Ice Cream',0,NULL,'[]','',384,'2026-04-16 14:16:32',NULL),(3480,'food','Dessert','','Gulab Jamun  With Ice Cream',0,NULL,'[]','',385,'2026-04-16 14:16:32',NULL),(3481,'food','Dessert','','Crispy Honey Noodels With Ice Cream',0,NULL,'[]','',386,'2026-04-16 14:16:32',NULL),(3482,'food','Dessert','','Gajar Ka Halwa',0,NULL,'[]','',387,'2026-04-16 14:16:32',NULL),(3483,'food','Dessert','','Chocolate Nutty Brownie Sizzler',0,NULL,'[]','',388,'2026-04-16 14:16:32',NULL),(3484,'food','Dessert','','Blue Berry Cheese Cake',0,379.00,'{\"Egg\":379}','',389,'2026-04-16 14:16:32',NULL),(3485,'food','Dessert','','Fruit Platter',0,NULL,'[]','',390,'2026-04-16 14:16:32',NULL),(3486,'food','Dessert','','Choice If Ice Cream ( V/C/S/M/Bs)',0,NULL,'[]','',391,'2026-04-16 14:16:32',NULL),(3487,'food','Dessert','','Kulfi',0,NULL,'[]','',392,'2026-04-16 14:16:32',NULL),(3488,'food','Dessert','','Desseri Of The Day',0,NULL,'[]','',393,'2026-04-16 14:16:32',NULL),(3489,'food','Mocktails','','Lost Island',0,NULL,'[]','',394,'2026-04-16 14:16:32',NULL),(3490,'food','Mocktails','','Watermelon & Kaffer Lime Lemonade',0,NULL,'[]','',395,'2026-04-16 14:16:32',NULL),(3491,'food','Mocktails','','Guava Mary',0,NULL,'[]','',396,'2026-04-16 14:16:32',NULL),(3492,'food','Mocktails','','Asian Itch',0,NULL,'[]','',397,'2026-04-16 14:16:32',NULL),(3493,'food','Mocktails','','Pineapple Yuzu Jalapeno Sour On The Rock',0,NULL,'[]','',398,'2026-04-16 14:16:32',NULL),(3494,'food','Mocktails','','Red Ginger Fizz',0,NULL,'[]','',399,'2026-04-16 14:16:32',NULL),(3495,'food','Mocktails','','Vitamin C',0,NULL,'[]','',400,'2026-04-16 14:16:32',NULL),(3496,'food','Mocktails','','Awg Virgin Sangria',0,NULL,'[]','',401,'2026-04-16 14:16:32',NULL),(3497,'food','Mocktails','','Masala Virgin Mojito',0,NULL,'[]','',402,'2026-04-16 14:16:32',NULL),(3498,'food','Mocktails','','Paan Gulkand Mojito',0,NULL,'[]','',403,'2026-04-16 14:16:32',NULL),(3499,'food','Mocktails','','Namaste Kalyan Spical',0,NULL,'[]','',404,'2026-04-16 14:16:32',NULL),(3500,'food','Mocktails','','Coco Berry',0,NULL,'[]','',405,'2026-04-16 14:16:32',NULL),(3501,'food','Mocktails','','Mixed Fantasy',0,NULL,'[]','',406,'2026-04-16 14:16:32',NULL),(3502,'food','Mocktails','','Wake Me Up',0,NULL,'[]','',407,'2026-04-16 14:16:32',NULL),(3503,'food','Icy Virgin Margaritas','','Strwberry & Mango Slushie',0,NULL,'[]','',408,'2026-04-16 14:16:32',NULL),(3504,'food','Icy Virgin Margaritas','','Peach & Mint Slushie',0,NULL,'[]','',409,'2026-04-16 14:16:32',NULL),(3505,'food','Icy Virgin Margaritas','','Spicy Blackcurrent Sluhie',0,NULL,'[]','',410,'2026-04-16 14:16:32',NULL),(3506,'food','Icy Virgin Margaritas','','Litchi & Dragoan Fruit Slushie',0,NULL,'[]','',411,'2026-04-16 14:16:32',NULL),(3507,'food','Icy Virgin Margaritas','','Rose Gulkand Slushie',0,NULL,'[]','',412,'2026-04-16 14:16:32',NULL),(3508,'food','Shakes& Smoothies','','Peanut Butter Banana Smoothie',0,NULL,'[]','',413,'2026-04-16 14:16:32',NULL),(3509,'food','Shakes& Smoothies','','Starwberry & Bourbon Shake',0,NULL,'[]','',414,'2026-04-16 14:16:32',NULL),(3510,'food','Shakes& Smoothies','','Chocolate& Coconut Shake',0,NULL,'[]','',415,'2026-04-16 14:16:32',NULL),(3511,'food','Shakes& Smoothies','','Coffee, Cookie &Toffee Shake',0,NULL,'[]','',416,'2026-04-16 14:16:32',NULL),(3512,'food','Shakes& Smoothies','','Salted Caramel, Alpenliebe & Popcorn  Shake',0,NULL,'[]','',417,'2026-04-16 14:16:32',NULL),(3513,'food','Shakes& Smoothies','','Blueberry Cheese Cake Smoothi',0,NULL,'[]','',418,'2026-04-16 14:16:32',NULL),(3514,'food','Shakes& Smoothies','','Mango Mint Smoothie',0,NULL,'[]','',419,'2026-04-16 14:16:32',NULL),(3515,'food','Shakes& Smoothies','','Kiwi Mint Smoothie',0,NULL,'[]','',420,'2026-04-16 14:16:32',NULL),(3516,'food','Shakes& Smoothies','','Mango Moon',0,NULL,'[]','',421,'2026-04-16 14:16:32',NULL),(3517,'food','All Time Favourite','','Bull Basil Mojito',0,NULL,'[]','',422,'2026-04-16 14:16:32',NULL),(3518,'food','All Time Favourite','','Fruit Punch',0,NULL,'[]','',423,'2026-04-16 14:16:32',NULL),(3519,'food','All Time Favourite','','V.Pinacolada',0,NULL,'[]','',424,'2026-04-16 14:16:32',NULL),(3520,'food','All Time Favourite','','V.Mojito',0,NULL,'[]','',425,'2026-04-16 14:16:32',NULL),(3521,'food','All Time Favourite','','Blue Lagoon',0,NULL,'[]','',426,'2026-04-16 14:16:32',NULL),(3522,'food','All Time Favourite','','Shirley Temple',0,NULL,'[]','',427,'2026-04-16 14:16:32',NULL),(3523,'food','All Time Favourite','','Cold Coffee',0,NULL,'[]','',428,'2026-04-16 14:16:32',NULL),(3524,'food','Iced Tea','','Classic',0,NULL,'[]','',429,'2026-04-16 14:16:32',NULL),(3525,'food','Iced Tea','','Black Current',0,NULL,'[]','',430,'2026-04-16 14:16:32',NULL),(3526,'food','Iced Tea','','Strawberry & Peach',0,NULL,'[]','',431,'2026-04-16 14:16:32',NULL),(3527,'food','Iced Tea','','Orange &Peach Apricot',0,NULL,'[]','',432,'2026-04-16 14:16:32',NULL),(3528,'food','Iced Tea','','Yuzu & Mint',0,NULL,'[]','',433,'2026-04-16 14:16:32',NULL),(3529,'food','Lemonades','','Blue Litchi & Coconut',0,NULL,'[]','',434,'2026-04-16 14:16:32',NULL),(3530,'food','Lemonades','','Blueberry & Basil',0,NULL,'[]','',435,'2026-04-16 14:16:32',NULL),(3531,'food','Lemonades','','Passion , Rosemary &Chilli',0,NULL,'[]','',436,'2026-04-16 14:16:32',NULL),(3532,'food','Lemonades','','Cucumber & Strawberry',0,NULL,'[]','',437,'2026-04-16 14:16:32',NULL),(3533,'food','Lemonades','','Cucumber & Curry Leaf',0,NULL,'[]','',438,'2026-04-16 14:16:32',NULL),(3534,'food','Lemonades','','Cucumber Lime Refresher',0,NULL,'[]','',439,'2026-04-16 14:16:32',NULL),(3535,'food','Cold Ones','','Aerated Float',0,NULL,'[]','',440,'2026-04-16 14:16:32',NULL),(3536,'food','Cold Ones','','Red Bull',0,NULL,'[]','',441,'2026-04-16 14:16:32',NULL),(3537,'food','Cold Ones','','Bull Float',0,NULL,'[]','',442,'2026-04-16 14:16:32',NULL),(3538,'food','Cold Ones','','Aerated Drinks (600 Ml)',0,NULL,'[]','',443,'2026-04-16 14:16:32',NULL),(3539,'food','Cold Ones','','Aerated Drinks Can',0,NULL,'[]','',444,'2026-04-16 14:16:32',NULL),(3540,'food','Cold Ones','','Fresh Lime Water',0,NULL,'[]','',445,'2026-04-16 14:16:32',NULL),(3541,'food','Cold Ones','','Masala Cola',0,NULL,'[]','',446,'2026-04-16 14:16:32',NULL),(3542,'food','Cold Ones','','Fresh Lime Soda',0,NULL,'[]','',447,'2026-04-16 14:16:32',NULL),(3543,'food','Cold Ones','','Jaljeera Soda',0,NULL,'[]','',448,'2026-04-16 14:16:32',NULL),(3544,'food','Cold Ones','','Jaljeera Water',0,NULL,'[]','',449,'2026-04-16 14:16:32',NULL),(3545,'food','Cold Ones','','Canned Juice(300 Ml)',0,NULL,'[]','',450,'2026-04-16 14:16:32',NULL),(3546,'food','Cold Ones','','Soda(750 Ml)',0,NULL,'[]','',451,'2026-04-16 14:16:32',NULL),(3547,'food','Cold Ones','','Ginger Ale',0,NULL,'[]','',452,'2026-04-16 14:16:32',NULL),(3548,'food','Cold Ones','','Tonic Water',0,NULL,'[]','',453,'2026-04-16 14:16:32',NULL),(3549,'food','Cold Ones','','Lassi',0,NULL,'[]','',454,'2026-04-16 14:16:32',NULL),(3550,'food','Cold Ones','','Butter Milk  Masala',0,NULL,'[]','',455,'2026-04-16 14:16:32',NULL),(3551,'food','Cold Ones','','Butter Milk Plain',0,NULL,'[]','',456,'2026-04-16 14:16:32',NULL),(3552,'food','Cold Ones','','Bottled Water',0,NULL,'[]','',457,'2026-04-16 14:16:32',NULL),(3553,'food','General','','New Item',1,NULL,'[]','',458,'2026-04-16 14:16:32',NULL),(3554,'bar','Awg & Classic Botanical Mixology','','Breezer Twist',1,529.00,'{\"Glass\":529}','',1,'2026-04-16 14:16:32',NULL),(3555,'bar','Awg & Classic Botanical Mixology','','Mai Tai',1,399.00,'{\"Glass\":399}','',2,'2026-04-16 14:16:32',NULL),(3556,'bar','Awg & Classic Botanical Mixology','','Old Monk Punch',1,379.00,'{\"Glass\":379}','',3,'2026-04-16 14:16:32',NULL),(3557,'bar','Awg & Classic Botanical Mixology','','Classic Mojito',1,349.00,'{\"Glass\":349}','',4,'2026-04-16 14:16:32',NULL),(3558,'bar','Awg & Classic Botanical Mixology','','Classic Pinacolada',1,349.00,'{\"Glass\":349}','',5,'2026-04-16 14:16:32',NULL),(3559,'bar','Awg & Classic Botanical Mixology','','Tom & Jerry',1,379.00,'{\"Glass\":379}','',6,'2026-04-16 14:16:32',NULL),(3560,'bar','Awg & Classic Botanical Mixology','','Sky Blue Colada',1,379.00,'{\"Glass\":379}','',7,'2026-04-16 14:16:32',NULL),(3561,'bar','Awg & Classic Botanical Mixology','','Daiquiri',1,349.00,'{\"Glass\":349}','',8,'2026-04-16 14:16:32',NULL),(3562,'bar','Awg & Classic Botanical Mixology','','Pain Killer',1,349.00,'{\"Glass\":349}','',9,'2026-04-16 14:16:32',NULL),(3563,'bar','Awg & Classic Botanical Mixology','','Cuba Libra',1,329.00,'{\"Glass\":329}','',10,'2026-04-16 14:16:32',NULL),(3564,'bar','Awg & Classic Botanical Mixology','','Spicy Chilli Beer',1,529.00,'{\"Glass\":529}','',11,'2026-04-16 14:16:32',NULL),(3565,'bar','Awg & Classic Botanical Mixology','','Sex On The Beach',1,379.00,'{\"Glass\":379}','',12,'2026-04-16 14:16:32',NULL),(3566,'bar','Awg & Classic Botanical Mixology','','Classic Guava Marry',1,379.00,'{\"Glass\":379}','',13,'2026-04-16 14:16:32',NULL),(3567,'bar','Awg & Classic Botanical Mixology','','Chocotini',1,379.00,'{\"Glass\":379}','',14,'2026-04-16 14:16:32',NULL),(3568,'bar','Awg & Classic Botanical Mixology','','Passion Star Martini',1,349.00,'{\"Glass\":349}','',15,'2026-04-16 14:16:32',NULL),(3569,'bar','Awg & Classic Botanical Mixology','','Poison Love',1,349.00,'{\"Glass\":349}','',16,'2026-04-16 14:16:32',NULL),(3570,'bar','Awg & Classic Botanical Mixology','','Cosmopolitan',1,329.00,'{\"Glass\":329}','',17,'2026-04-16 14:16:32',NULL),(3571,'bar','Awg & Classic Botanical Mixology','','Classic Blue Lagoon',1,329.00,'{\"Glass\":329}','',18,'2026-04-16 14:16:32',NULL),(3572,'bar','Awg & Classic Botanical Mixology','','New York Sour',1,529.00,'{\"Glass\":529}','',19,'2026-04-16 14:16:32',NULL),(3573,'bar','Awg & Classic Botanical Mixology','','Whiskey Sour',1,479.00,'{\"Glass\":479}','',20,'2026-04-16 14:16:32',NULL),(3574,'bar','Awg & Classic Botanical Mixology','','Old Fashioned',1,499.00,'{\"Glass\":499}','',21,'2026-04-16 14:16:32',NULL),(3575,'bar','Awg & Classic Botanical Mixology','','Mint Julep',1,449.00,'{\"Glass\":449}','',22,'2026-04-16 14:16:32',NULL),(3576,'bar','Awg & Classic Botanical Mixology','','Hot Toddy',1,399.00,'{\"Glass\":399}','',23,'2026-04-16 14:16:32',NULL),(3577,'bar','Awg & Classic Botanical Mixology','','Orange Julep',1,399.00,'{\"Glass\":399}','',24,'2026-04-16 14:16:32',NULL),(3578,'bar','Awg & Classic Botanical Mixology','','Let\'S Detox',1,449.00,'{\"Glass\":449}','',25,'2026-04-16 14:16:32',NULL),(3579,'bar','Awg & Classic Botanical Mixology','','Awgtini  (Martini)',1,469.00,'{\"Glass\":469}','',26,'2026-04-16 14:16:32',NULL),(3580,'bar','Awg & Classic Botanical Mixology','','Chilli Basil Martini',1,399.00,'{\"Glass\":399}','',27,'2026-04-16 14:16:32',NULL),(3581,'bar','Awg & Classic Botanical Mixology','','White',1,399.00,'{\"Glass\":399}','',28,'2026-04-16 14:16:32',NULL),(3582,'bar','Awg & Classic Botanical Mixology','','Pink Lady',1,399.00,'{\"Glass\":399}','',29,'2026-04-16 14:16:32',NULL),(3583,'bar','Awg & Classic Botanical Mixology','','Gimlet',1,349.00,'{\"Glass\":349}','',30,'2026-04-16 14:16:32',NULL),(3584,'bar','Awg & Classic Botanical Mixology','','Green Maxican',1,599.00,'{\"Glass\":599}','',31,'2026-04-16 14:16:32',NULL),(3585,'bar','Awg & Classic Botanical Mixology','','Tequila Sunrise',1,499.00,'{\"Glass\":499}','',32,'2026-04-16 14:16:32',NULL),(3586,'bar','Awg & Classic Botanical Mixology','','Margarita',1,499.00,'{\"Glass\":499}','',33,'2026-04-16 14:16:32',NULL),(3587,'bar','Bull Base Cocktail','','Bull Frog',1,699.00,'{\"Glass\":699}','',34,'2026-04-16 14:16:32',NULL),(3588,'bar','Bull Base Cocktail','','Flying Bull',1,529.00,'{\"Glass\":529}','',35,'2026-04-16 14:16:32',NULL),(3589,'bar','Bull Base Cocktail','','Bull Exotica',1,529.00,'{\"Glass\":529}','',36,'2026-04-16 14:16:32',NULL),(3590,'bar','Special Modern Mixology','','Drunk Maghai Paan',1,499.00,'{\"Glass\":499}','',37,'2026-04-16 14:16:32',NULL),(3591,'bar','Special Modern Mixology','','Sunset Melon',1,499.00,'{\"Glass\":499}','',38,'2026-04-16 14:16:32',NULL),(3592,'bar','Special Modern Mixology','','The Awg Punch',1,499.00,'{\"Glass\":499}','',39,'2026-04-16 14:16:32',NULL),(3593,'bar','Special Modern Mixology','','Cranberry Kiss',1,499.00,'{\"Glass\":499}','',40,'2026-04-16 14:16:32',NULL),(3594,'bar','Special Modern Mixology','','Lost In Litchi',1,499.00,'{\"Glass\":499}','',41,'2026-04-16 14:16:32',NULL),(3595,'bar','Special Modern Mixology','','Trust In Me',1,499.00,'{\"Glass\":499}','',42,'2026-04-16 14:16:32',NULL),(3596,'bar','Shots','','Jager Bomb',1,579.00,'{\"Glass\":579}','',43,'2026-04-16 14:16:32',NULL),(3597,'bar','Shots','','Bacadi  Mango Chilli Shot',1,299.00,'{\"Glass\":299}','',44,'2026-04-16 14:16:32',NULL),(3598,'bar','Shots','','B52',1,579.00,'{\"Glass\":579}','',45,'2026-04-16 14:16:32',NULL),(3599,'bar','Shots','','Bmw',1,579.00,'{\"Glass\":579}','',46,'2026-04-16 14:16:32',NULL),(3600,'bar','Shots','','Jack Sparrow',1,549.00,'{\"Glass\":549}','',47,'2026-04-16 14:16:32',NULL),(3601,'bar','Shots','','Jack Sparrow',1,549.00,'{\"Glass\":549}','',48,'2026-04-16 14:16:32',NULL),(3602,'bar','Shots','','Irish Frog',1,549.00,'{\"Glass\":549}','',49,'2026-04-16 14:16:32',NULL),(3603,'bar','Shots','','Drunk Melon',1,529.00,'{\"Glass\":529}','',50,'2026-04-16 14:16:32',NULL),(3604,'bar','Shots','','Passion Blondy',1,529.00,'{\"Glass\":529}','',51,'2026-04-16 14:16:32',NULL),(3605,'bar','Shots','','Fighting Bull',1,429.00,'{\"Glass\":429}','',52,'2026-04-16 14:16:32',NULL),(3606,'bar','Shots','','Kamikazi',1,429.00,'{\"Glass\":429}','',53,'2026-04-16 14:16:32',NULL),(3607,'bar','Sangria','','White Wine Sangria',1,429.00,'{\"Glass\":429,\"Pitcher(1.5 Lit)\":2999}','',54,'2026-04-16 14:16:32',NULL),(3608,'bar','Sangria','','Red Wine Sangria',1,429.00,'{\"Glass\":429,\"Pitcher(1.5 Lit)\":2999}','',55,'2026-04-16 14:16:32',NULL),(3609,'bar','Liit Long Glass','','Awg Liit',1,729.00,'{\"Glass\":729}','',56,'2026-04-16 14:16:32',NULL),(3610,'bar','Liit Long Glass','','Beer Liit',1,699.00,'{\"Glass\":699}','',57,'2026-04-16 14:16:32',NULL),(3611,'bar','Liit Long Glass','','Boom Boom Liit',1,699.00,'{\"Glass\":699}','',58,'2026-04-16 14:16:32',NULL),(3612,'bar','Liit Long Glass','','Cranberry Liit',1,649.00,'{\"Glass\":649}','',59,'2026-04-16 14:16:32',NULL),(3613,'bar','Liit Long Glass','','Classic Liit',1,649.00,'{\"Glass\":649}','',60,'2026-04-16 14:16:32',NULL),(3614,'bar','Scotch Whisky','','Dewar\'S 15 Yo',1,549.00,'{\"30Ml\":549}','',61,'2026-04-16 14:16:32',NULL),(3615,'bar','Scotch Whisky','','Dewar\'S 12 Yo',1,459.00,'{\"30Ml\":459}','',62,'2026-04-16 14:16:32',NULL),(3616,'bar','Scotch Whisky','','Dewar\'S Portuguese Smooth',1,359.00,'{\"30Ml\":359}','',63,'2026-04-16 14:16:32',NULL),(3617,'bar','Scotch Whisky','','Dewar\'S White Label',1,299.00,'{\"30Ml\":299}','',64,'2026-04-16 14:16:32',NULL),(3618,'bar','Scotch Whisky','','Johnnie Walker Blue Label',1,2299.00,'{\"30Ml\":2299}','',65,'2026-04-16 14:16:32',NULL),(3619,'bar','Scotch Whisky','','Royal Salute 21Yrs',1,2299.00,'{\"30Ml\":2299}','',66,'2026-04-16 14:16:32',NULL),(3620,'bar','Scotch Whisky','','Johnnie Walker Double Black',1,549.00,'{\"30Ml\":549}','',67,'2026-04-16 14:16:32',NULL),(3621,'bar','Scotch Whisky','','Monkey Shoulder',1,549.00,'{\"30Ml\":549}','',68,'2026-04-16 14:16:32',NULL),(3622,'bar','Scotch Whisky','','Chivas Regal 12 Y.O.',1,459.00,'{\"30Ml\":459}','',69,'2026-04-16 14:16:32',NULL),(3623,'bar','Scotch Whisky','','Johnnie Walker Black Label 12 Yrs.',1,459.00,'{\"30Ml\":459}','',70,'2026-04-16 14:16:32',NULL),(3624,'bar','Scotch Whisky','','J & B',1,329.00,'{\"30Ml\":329}','',71,'2026-04-16 14:16:32',NULL),(3625,'bar','Scotch Whisky','','Johnnie Walker Red Label',1,299.00,'{\"30Ml\":299}','',72,'2026-04-16 14:16:32',NULL),(3626,'bar','Single Malt','','Laphroaig 10 Y.O.',1,949.00,'{\"30Ml\":949}','',73,'2026-04-16 14:16:32',NULL),(3627,'bar','Single Malt','','Glenfiddich 12 Yrs.',1,729.00,'{\"30Ml\":729}','',74,'2026-04-16 14:16:32',NULL),(3628,'bar','Single Malt','','Talisker 10 Yrs.',1,729.00,'{\"30Ml\":729}','',75,'2026-04-16 14:16:32',NULL),(3629,'bar','Single Malt','','Glenlivet 12 Yrs.',1,729.00,'{\"30Ml\":729}','',76,'2026-04-16 14:16:32',NULL),(3630,'bar','Single Malt','','Glenmorangie. 10 Yrs.',1,729.00,'{\"30Ml\":729}','',77,'2026-04-16 14:16:32',NULL),(3631,'bar','Single Malt','','Aberfeldy 12Yo',1,800.00,'{\"30Ml\":800}','',78,'2026-04-16 14:16:32',NULL),(3632,'bar','Single Malt','','Amrut',1,529.00,'{\"30Ml\":529}','',79,'2026-04-16 14:16:32',NULL),(3633,'bar','Bourbon / Irish Whisky','','Jack Daniel\'S Old No.07',1,429.00,'{\"30Ml\":429}','',80,'2026-04-16 14:16:32',NULL),(3634,'bar','Bourbon / Irish Whisky','','Jack Daniel\'S Old No.07  Honey',1,429.00,'{\"30Ml\":429}','',81,'2026-04-16 14:16:32',NULL),(3635,'bar','Bourbon / Irish Whisky','','Jack Daniel\'S Old No.07  Fire',1,429.00,'{\"30Ml\":429}','',82,'2026-04-16 14:16:32',NULL),(3636,'bar','Bourbon / Irish Whisky','','Jim Beam',1,359.00,'{\"30Ml\":359}','',83,'2026-04-16 14:16:32',NULL),(3637,'bar','Bourbon / Irish Whisky','','Jameson',1,359.00,'{\"30Ml\":359}','',84,'2026-04-16 14:16:32',NULL),(3638,'bar','Premium Whisky','','Teachers 50',1,379.00,'{\"30Ml\":379}','',85,'2026-04-16 14:16:32',NULL),(3639,'bar','Premium Whisky','','Black Dog Triple Gold',1,369.00,'{\"30Ml\":369}','',86,'2026-04-16 14:16:32',NULL),(3640,'bar','Premium Whisky','','Teachers Highland Cream',1,309.00,'{\"30Ml\":309}','',87,'2026-04-16 14:16:32',NULL),(3641,'bar','Premium Whisky','','Black & White',1,299.00,'{\"30Ml\":299}','',88,'2026-04-16 14:16:32',NULL),(3642,'bar','Premium Whisky','','100 Pipers',1,299.00,'{\"30Ml\":299}','',89,'2026-04-16 14:16:32',NULL),(3643,'bar','Premium Whisky','','Vat 69',1,299.00,'{\"30Ml\":299}','',90,'2026-04-16 14:16:32',NULL),(3644,'bar','Premium Whisky','','Ballentine’S Finest',1,289.00,'{\"30Ml\":289}','',91,'2026-04-16 14:16:32',NULL),(3645,'bar','Premium Whisky','','Willam Lawson\'S',1,249.00,'{\"30Ml\":249}','',92,'2026-04-16 14:16:32',NULL),(3646,'bar','Premium Whisky','','The Glenwalk',1,249.00,'{\"30Ml\":249}','',93,'2026-04-16 14:16:32',NULL),(3647,'bar','Premium Whisky','','Blenders Pride Reserved',1,209.00,'{\"30Ml\":209}','',94,'2026-04-16 14:16:32',NULL),(3648,'bar','Premium Whisky','','Antiquity Blue',1,209.00,'{\"30Ml\":209}','',95,'2026-04-16 14:16:32',NULL),(3649,'bar','Premium Whisky','','Signature',1,199.00,'{\"30Ml\":199}','',96,'2026-04-16 14:16:32',NULL),(3650,'bar','Premium Whisky','','Blenders Pride',1,199.00,'{\"30Ml\":199}','',97,'2026-04-16 14:16:32',NULL),(3651,'bar','Premium Whisky','','Oaksmith Gold',1,199.00,'{\"30Ml\":199}','',98,'2026-04-16 14:16:32',NULL),(3652,'bar','Premium Whisky','','Oaksmith Silver',1,199.00,'{\"30Ml\":199}','',99,'2026-04-16 14:16:32',NULL),(3653,'bar','Premium Whisky','','Okan Glow',1,159.00,'{\"30Ml\":159}','',100,'2026-04-16 14:16:32',NULL),(3654,'bar','Imported Vodka','','Grey Goose',1,599.00,'{\"30Ml\":599}','',101,'2026-04-16 14:16:32',NULL),(3655,'bar','Imported Vodka','','Cîroc',1,579.00,'{\"30Ml\":579}','',102,'2026-04-16 14:16:32',NULL),(3656,'bar','Imported Vodka','','Absolut',1,329.00,'{\"30Ml\":329}','',103,'2026-04-16 14:16:32',NULL),(3657,'bar','Imported Vodka','','Absolut Flavor',1,329.00,'{\"30Ml\":329}','',104,'2026-04-16 14:16:32',NULL),(3658,'bar','Domestic Vodka','','Smirnoff',1,209.00,'{\"30Ml\":209}','',105,'2026-04-16 14:16:32',NULL),(3659,'bar','Domestic Vodka','','Smirnoff Flavor',1,209.00,'{\"30Ml\":209}','',106,'2026-04-16 14:16:32',NULL),(3660,'bar','Domestic Vodka','','Magic Moment',1,149.00,'{\"30Ml\":149}','',107,'2026-04-16 14:16:32',NULL),(3661,'bar','Gin','','Jaisalmer',1,399.00,'{\"30Ml\":399}','',108,'2026-04-16 14:16:32',NULL),(3662,'bar','Gin','','Bombay Sapphire',1,329.00,'{\"30Ml\":329}','',109,'2026-04-16 14:16:32',NULL),(3663,'bar','Gin','','Beefeater',1,299.00,'{\"30Ml\":299}','',110,'2026-04-16 14:16:32',NULL),(3664,'bar','Gin','','Blue Riband',1,149.00,'{\"30Ml\":149}','',111,'2026-04-16 14:16:32',NULL),(3665,'bar','Liquer','','Malibu',1,399.00,'{\"30Ml\":399}','',112,'2026-04-16 14:16:32',NULL),(3666,'bar','Domestic Rum','','Bacardi White Rum',1,159.00,'{\"30Ml\":159}','',113,'2026-04-16 14:16:32',NULL),(3667,'bar','Domestic Rum','','Bacardi  Black Rum',1,159.00,'{\"30Ml\":159}','',114,'2026-04-16 14:16:32',NULL),(3668,'bar','Domestic Rum','','Old Monk Coffee',1,209.00,'{\"30Ml\":209}','',115,'2026-04-16 14:16:32',NULL),(3669,'bar','Domestic Rum','','Captain Morgan',1,159.00,'{\"30Ml\":159}','',116,'2026-04-16 14:16:32',NULL),(3670,'bar','Domestic Rum','','Old Monk White',1,129.00,'{\"30Ml\":129}','',117,'2026-04-16 14:16:32',NULL),(3671,'bar','Domestic Rum','','Old Monk Regular',1,129.00,'{\"30Ml\":129}','',118,'2026-04-16 14:16:32',NULL),(3672,'bar','Brandy','','Hennessy V.S.',1,599.00,'{\"30Ml\":599}','',119,'2026-04-16 14:16:32',NULL),(3673,'bar','Tequila','','Patron',1,949.00,'{\"Silver\":949,\"Repesado\":1099,\"Anezo\":949}','',120,'2026-04-16 14:16:32',NULL),(3674,'bar','Tequila','','Tequila Camino Gold',1,409.00,'{\"30Ml\":409}','',121,'2026-04-16 14:16:32',NULL),(3675,'bar','Tequila','','Tequila Camino Silver',1,409.00,'{\"30Ml\":409}','',122,'2026-04-16 14:16:32',NULL),(3676,'bar','Tequila','','Don Angel',1,379.00,'{\"30Ml\":379}','',123,'2026-04-16 14:16:32',NULL),(3677,'bar','Liqueurs','','Triple Sec',1,499.00,'{\"30Ml\":499}','',124,'2026-04-16 14:16:32',NULL),(3678,'bar','Liqueurs','','Cointreau',1,499.00,'{\"30Ml\":499}','',125,'2026-04-16 14:16:32',NULL),(3679,'bar','Liqueurs','','Jagermeister',1,499.00,'{\"30Ml\":499}','',126,'2026-04-16 14:16:32',NULL),(3680,'bar','Liqueurs','','Baileys',1,399.00,'{\"30Ml\":399}','',127,'2026-04-16 14:16:32',NULL),(3681,'bar','Liqueurs','','Sambuca',1,379.00,'{\"30Ml\":379}','',128,'2026-04-16 14:16:32',NULL),(3682,'bar','Liqueurs','','Kahlua',1,379.00,'{\"30Ml\":379}','',129,'2026-04-16 14:16:32',NULL),(3683,'bar','Liqueurs','','Midori',1,379.00,'{\"30Ml\":379}','',130,'2026-04-16 14:16:32',NULL),(3684,'bar','Liqueurs','','Martini',1,379.00,'{\"30Ml\":379}','',131,'2026-04-16 14:16:32',NULL),(3685,'bar','Beer\'S','','Hoegaarden',1,409.00,'{\"330Ml\":409}','',132,'2026-04-16 14:16:32',NULL),(3686,'bar','Beer\'S','','Corona',1,409.00,'{\"330Ml\":409}','',133,'2026-04-16 14:16:32',NULL),(3687,'bar','Beer\'S','','Budweiser Magnum',1,349.00,'{\"330Ml\":349}','',134,'2026-04-16 14:16:32',NULL),(3688,'bar','Beer\'S','','Budweiser Premium',1,329.00,'{\"330Ml\":329}','',135,'2026-04-16 14:16:32',NULL),(3689,'bar','Beer\'S','','Kingfisher Ultra',1,329.00,'{\"330Ml\":329}','',136,'2026-04-16 14:16:32',NULL),(3690,'bar','Beer\'S','','Heineken',1,329.00,'{\"330Ml\":329}','',137,'2026-04-16 14:16:32',NULL),(3691,'bar','Beer\'S','','Heineken  Silver',1,329.00,'{\"330Ml\":329}','',138,'2026-04-16 14:16:32',NULL),(3692,'bar','Beer\'S','','Carlsberg Elephent',1,329.00,'{\"330Ml\":329}','',139,'2026-04-16 14:16:32',NULL),(3693,'bar','Beer\'S','','Carlsberg Smooth',1,289.00,'{\"330Ml\":289}','',140,'2026-04-16 14:16:32',NULL),(3694,'bar','Beer\'S','','Tuborg Strong',1,279.00,'{\"330Ml\":279}','',141,'2026-04-16 14:16:32',NULL),(3695,'bar','Beer\'S','','Kingfisher Premium',1,269.00,'{\"330Ml\":269}','',142,'2026-04-16 14:16:32',NULL),(3696,'bar','Breezers','','Cranberry',1,299.00,'{\"275Ml\":299}','',143,'2026-04-16 14:16:32',NULL),(3697,'bar','Breezers','','Jamaican Passion',1,299.00,'{\"275Ml\":299}','',144,'2026-04-16 14:16:32',NULL),(3698,'bar','Sparkling Wine','','Sula Brut',1,2599.00,'{\"Btl\":2599}','',145,'2026-04-16 14:16:32',NULL),(3699,'bar','Sparkling Wine','','Sula Seco',1,1699.00,'{\"Btl\":1699}','',146,'2026-04-16 14:16:32',NULL),(3700,'bar','Red Wine','','Jacob Creak Merlot',1,2699.00,'{\"Btl\":2699}','',147,'2026-04-16 14:16:32',NULL),(3701,'bar','Red Wine','','Shiraz',1,2699.00,'{\"Btl\":2699}','',148,'2026-04-16 14:16:32',NULL),(3702,'bar','Red Wine','','Sula Dindori',1,479.00,'{\"Glass\":479,\"Btl\":1999}','',149,'2026-04-16 14:16:32',NULL),(3703,'bar','Red Wine','','Sula Cabernet Shiraz',1,399.00,'{\"Glass\":399,\"Btl\":1899}','',150,'2026-04-16 14:16:32',NULL),(3704,'bar','Red Wine','','Sula  Satroi',1,399.00,'{\"Glass\":399,\"Btl\":1899}','',151,'2026-04-16 14:16:32',NULL),(3705,'bar','Red Wine','','Fratelli Classic Merlot',1,349.00,'{\"Glass\":349,\"Btl\":1599}','',152,'2026-04-16 14:16:32',NULL),(3706,'bar','Red Wine','','Fratell Cabernet Shiraz',1,349.00,'{\"Glass\":349,\"Btl\":1599}','',153,'2026-04-16 14:16:32',NULL),(3707,'bar','White Wine','','Jacob Creak Chordonnay',1,2599.00,'{\"Btl\":2599}','',154,'2026-04-16 14:16:32',NULL),(3708,'bar','White Wine','','Sula Chenin Blanc',1,349.00,'{\"Glass\":349,\"Btl\":1599}','',155,'2026-04-16 14:16:32',NULL),(3709,'bar','White Wine','','Sula  Sauvignon Blanc',1,349.00,'{\"Glass\":349,\"Btl\":1599}','',156,'2026-04-16 14:16:32',NULL),(3710,'bar','White Wine','','Fratelli Chenin Blane',1,349.00,'{\"Glass\":349,\"Btl\":1599}','',157,'2026-04-16 14:16:32',NULL),(3711,'bar','White Wine','','Fratelli Chardonnay',1,349.00,'{\"Glass\":349,\"Btl\":1599}','',158,'2026-04-16 14:16:32',NULL),(3712,'bar','Rose Wine','','Sula Zinfandel Rose',1,349.00,'{\"Glass\":349,\"Btl\":1599}','',159,'2026-04-16 14:16:32',NULL),(3713,'bar','Rose Wine','','Rose',1,349.00,'{\"Glass\":349,\"Btl\":1499}','',160,'2026-04-16 14:16:32',NULL),(3714,'bar','Dessert  Wine','','Sula Late Harvest',1,439.00,'{\"Glass\":439,\"Btl\":2199}','',161,'2026-04-16 14:16:32',NULL);
/*!40000 ALTER TABLE `menu_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menu_schema`
--

DROP TABLE IF EXISTS `menu_schema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `menu_schema` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sheet_type` enum('food','bar') NOT NULL,
  `headers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`headers`)),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sheet_type` (`sheet_type`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menu_schema`
--

LOCK TABLES `menu_schema` WRITE;
/*!40000 ALTER TABLE `menu_schema` DISABLE KEYS */;
INSERT INTO `menu_schema` VALUES (1,'food','[\"Category\",\"Item Name\",\"Description\",\"Veg\",\"Jain\",\"Chicken\",\"Mutton\",\"Basa\",\"Prawns\",\"Surmai\",\"Pomfret\",\"Crab\",\"Egg\",\"Spice Level\",\"Chef Special\",\"Half\",\"Full\",\"Plain\",\"Butter\",\"Medium\",\"Large\",\"Image URL\",\"Availability\"]','2026-04-16 07:56:39'),(2,'bar','[\"Category\",\"Item Name\",\"Description\",\"Mocktail\",\"30Ml\",\"330Ml\",\"275Ml\",\"Glass\",\"Silver\",\"Repesado\",\"Anezo\",\"Btl\",\"Pitcher(1.5 Lit)\",\"Bar Man Special\"]','2026-04-16 07:56:42');
/*!40000 ALTER TABLE `menu_schema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(120) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_filename` (`filename`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'001_create_users.sql','2026-04-14 12:06:48'),(2,'002_create_auth_audit.sql','2026-04-14 12:06:48'),(3,'003_create_revoked_tokens.sql','2026-04-14 12:06:49'),(4,'004_create_leads.sql','2026-04-14 12:06:49'),(5,'005_create_events.sql','2026-04-14 12:06:50'),(6,'006_create_event_transactions.sql','2026-04-14 12:06:50'),(7,'007_create_admin_cash_ledger.sql','2026-04-14 12:06:50'),(8,'008_create_superadmin_cash_ledger.sql','2026-04-14 12:06:51'),(9,'009_create_qr_scans.sql','2026-04-14 12:06:51'),(10,'010_create_menu_items.sql','2026-04-14 12:06:52'),(11,'011_create_api_settings.sql','2026-04-14 12:06:52');
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qr_scans`
--

DROP TABLE IF EXISTS `qr_scans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qr_scans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scanned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `user_agent` varchar(500) NOT NULL DEFAULT '',
  `referer` varchar(500) NOT NULL DEFAULT '',
  `ip_address` varchar(64) NOT NULL DEFAULT '',
  `scan_number` int(10) unsigned NOT NULL DEFAULT 0,
  `city` varchar(100) NOT NULL DEFAULT '',
  `region` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(50) NOT NULL DEFAULT '',
  `device` varchar(100) NOT NULL DEFAULT '',
  `browser` varchar(100) NOT NULL DEFAULT '',
  `os` varchar(100) NOT NULL DEFAULT '',
  `language` varchar(20) NOT NULL DEFAULT '',
  `screen` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_scanned_at` (`scanned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qr_scans`
--

LOCK TABLES `qr_scans` WRITE;
/*!40000 ALTER TABLE `qr_scans` DISABLE KEYS */;
/*!40000 ALTER TABLE `qr_scans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `revoked_tokens`
--

DROP TABLE IF EXISTS `revoked_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `revoked_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `revoked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `username` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `revoked_tokens`
--

LOCK TABLES `revoked_tokens` WRITE;
/*!40000 ALTER TABLE `revoked_tokens` DISABLE KEYS */;
INSERT INTO `revoked_tokens` VALUES (1,'4b549a5db264d0143a7242c86ef602132353b7b576ab4476082355ad5adaa951','2026-04-16 12:06:30','2026-04-16 20:06:23','9371519999'),(2,'db83d9b7d5a5b40c576a1833f190445ee09ca36c85c98f75fd9e2348057d68bf','2026-04-16 12:13:09','2026-04-16 20:13:02','9371519999'),(3,'165c5e5053ab7b104780dbe8f259104735d8f0ec681b468bf7e669cf74a25a1c','2026-04-16 12:28:50','2026-04-16 20:20:10','9371519999'),(4,'78c652f67ce2742afc9225cbbe1d363d1c716d3b042c5dbcb5ba6810655faa1e','2026-04-16 13:44:12','2026-04-16 21:28:42','9371519999'),(5,'5fe4fc2577b387ef58dabbe538ded5992d1a3045990e0d94b9420955e57cf221','2026-04-16 13:50:07','2026-04-16 21:44:16','9371519999');
/*!40000 ALTER TABLE `revoked_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `superadmin_cash_ledger`
--

DROP TABLE IF EXISTS `superadmin_cash_ledger`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `superadmin_cash_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_key` varchar(60) NOT NULL,
  `ledger_date` date NOT NULL,
  `admin_username` varchar(50) NOT NULL,
  `admin_display_name` varchar(100) NOT NULL DEFAULT '',
  `total_transactions` int(10) unsigned NOT NULL DEFAULT 0,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `requested_by` varchar(50) NOT NULL DEFAULT '',
  `approved_at` datetime DEFAULT NULL,
  `approved_by` varchar(50) NOT NULL DEFAULT '',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `notes` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_key` (`batch_key`),
  KEY `idx_ledger_date` (`ledger_date`),
  KEY `idx_admin_username` (`admin_username`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `superadmin_cash_ledger`
--

LOCK TABLES `superadmin_cash_ledger` WRITE;
/*!40000 ALTER TABLE `superadmin_cash_ledger` DISABLE KEYS */;
/*!40000 ALTER TABLE `superadmin_cash_ledger` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL DEFAULT '',
  `role` enum('admin','superadmin') NOT NULL DEFAULT 'admin',
  `password_hash` varchar(130) NOT NULL,
  `password_salt` varchar(64) NOT NULL,
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  `failed_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `lockout_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(50) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (10,'9371519999','Super Admin','superadmin','$2y$10$1l6WL91gt12eQ7YVm9zIjunz2oS7Vt2MmC9.8XjOQrHsyKljlwcLa','','active',1,0,NULL,'2026-04-16 15:00:01','2401:4900:1c44:a555:bc12:4d9a:3d7:c016','2026-04-13 14:37:54','bootstrap','2026-04-16 15:00:01',NULL,'[]'),(11,'9241839981','Smoke Test','admin','8cfa0da579e03b9977983e9b688b6f96e2d838018e54b2b2f8e53755ef642f54','13f43fbf2d9e4562ac32e889a9b91e04','active',1,0,NULL,NULL,'','2026-04-16 09:45:27','9371519999','2026-04-16 09:45:27','9371519999','[\"dashboard\"]'),(12,'9728433372','Smoke Test','admin','ff34d4b3a0f6155276bf304ee027bbef55c4f59e11829189b6f5d5e26d1437cd','56479c79067a4e219e26e86f21e9ef5b','active',1,0,NULL,NULL,'','2026-04-16 09:46:28','9371519999','2026-04-16 09:46:28','9371519999','[\"dashboard\"]'),(13,'9653815572','Smoke User','admin','e6d08c576800ef01e3d546e645def7f4a7cf438e7aaa070a2a95b41406d73165','13ab41f96e3f4d2c950cd3d00869d9b6','active',1,0,NULL,NULL,'','2026-04-16 11:14:32','9371519999','2026-04-16 11:14:32','9371519999','[\"dashboard\"]'),(14,'9567422124','Smoke User','admin','e0d0c4dfda2366a276b98c38c451c089294e9bafabe9c1b8a3f6141b4dcf1dca','c9a854196d8145d29422886104211ab9','active',1,0,NULL,NULL,'','2026-04-16 11:16:31','9371519999','2026-04-16 11:16:31','9371519999','[\"dashboard\"]'),(15,'9723648867','Smoke User','admin','318ed06f7a28a581d16afc5f711a47e9169819115de1deda36aa5cf7c3c1a76e','6c0b4ddca40947159ff5dccd8e54688a','active',1,0,NULL,NULL,'','2026-04-16 11:18:10','9371519999','2026-04-16 11:18:10','9371519999','[\"dashboard\"]'),(16,'9000000001','Test Admin','admin','$2y$10$e0svhl7ZfsY4BYUp41EeouY4XloiDyC.IaAxCWyhk7Y0V8X5LnZlO','','active',1,0,NULL,'2026-04-16 13:52:18','2401:4900:1c42:29b9:3423:7f3f:cbbb:5d79','2026-04-16 12:49:19','9371519999','2026-04-16 13:52:18',NULL,'{\"dashboard\":false,\"cashier\":false,\"verification\":false,\"eventGuests\":false,\"eventScanner\":false,\"eventManagement\":false,\"menuEditor\":false,\"cashApprovals\":false,\"userManagement\":false}');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-16  9:47:52
