-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: nursing_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admitted_patient_documents`
--

DROP TABLE IF EXISTS `admitted_patient_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admitted_patient_documents` (
  `document_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `document_type` enum('lab','chart','prescription','other') NOT NULL DEFAULT 'other',
  `title` varchar(150) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`document_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `idx_admitted_docs_patient` (`patient_id`),
  CONSTRAINT `admitted_patient_documents_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  CONSTRAINT `admitted_patient_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admitted_patient_documents`
--

LOCK TABLES `admitted_patient_documents` WRITE;
/*!40000 ALTER TABLE `admitted_patient_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `admitted_patient_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admitted_patient_records`
--

DROP TABLE IF EXISTS `admitted_patient_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admitted_patient_records` (
  `record_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `lab_results` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `patient_chart` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  KEY `updated_by` (`updated_by`),
  CONSTRAINT `admitted_patient_records_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  CONSTRAINT `admitted_patient_records_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admitted_patient_records`
--

LOCK TABLES `admitted_patient_records` WRITE;
/*!40000 ALTER TABLE `admitted_patient_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `admitted_patient_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alarms`
--

DROP TABLE IF EXISTS `alarms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alarms` (
  `alarm_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `alarm_type` varchar(50) NOT NULL,
  `severity` enum('high','medium','low') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`alarm_id`),
  KEY `patient_id` (`patient_id`),
  KEY `resolved_by` (`resolved_by`),
  KEY `idx_alarm_active` (`is_active`),
  CONSTRAINT `alarms_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  CONSTRAINT `alarms_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alarms`
--

LOCK TABLES `alarms` WRITE;
/*!40000 ALTER TABLE `alarms` DISABLE KEYS */;
/*!40000 ALTER TABLE `alarms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `barangays`
--

DROP TABLE IF EXISTS `barangays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `barangays` (
  `barangay_id` int(11) NOT NULL AUTO_INCREMENT,
  `municipality_id` int(11) NOT NULL,
  `barangay_name` varchar(100) NOT NULL,
  PRIMARY KEY (`barangay_id`),
  UNIQUE KEY `unique_barangay` (`municipality_id`,`barangay_name`),
  CONSTRAINT `barangays_ibfk_1` FOREIGN KEY (`municipality_id`) REFERENCES `municipalities` (`municipality_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=226 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barangays`
--

LOCK TABLES `barangays` WRITE;
/*!40000 ALTER TABLE `barangays` DISABLE KEYS */;
INSERT INTO `barangays` VALUES (1,1,'Barile'),(2,1,'Borol'),(3,1,'Calaanan'),(4,1,'Coloong'),(5,1,'Galing'),(6,1,'Langka'),(7,1,'Laya'),(8,1,'Lipat'),(9,1,'Listerio'),(10,1,'Malinta'),(11,1,'Niog'),(12,1,'Pangarap'),(13,1,'Pinagbuhatan'),(14,1,'Poblacion'),(15,1,'Sikat'),(16,2,'Arkong-Bato'),(17,2,'Bagbaguin'),(18,2,'Bisig'),(19,2,'Canumay'),(20,2,'Coloong'),(21,2,'Daan-Lungsod'),(22,2,'Galing'),(23,2,'Hooker'),(24,2,'Kalayaan'),(25,2,'Karuhatan'),(26,2,'Lampak'),(27,2,'Langka'),(28,2,'Mabolo'),(29,2,'Malanday'),(30,2,'Malinta'),(31,3,'Bahay'),(32,3,'Bambang'),(33,3,'Binunga'),(34,3,'Coloong'),(35,3,'Cupang'),(36,3,'Galing'),(37,3,'Langka'),(38,3,'Laya'),(39,3,'Lipat'),(40,3,'Mahabang-Dahilan'),(41,3,'Mahimay'),(42,3,'Maligaya'),(43,3,'Mataas'),(44,3,'Melawin'),(45,3,'Minuyan'),(46,4,'Abangan'),(47,4,'Burol'),(48,4,'Catanghalan'),(49,4,'Chalan'),(50,4,'Cuyab'),(51,4,'Naglingao'),(52,4,'Pangarap'),(53,4,'Patubig'),(54,4,'Piyong'),(55,4,'Tabing-Karagatan'),(56,5,'Biglang-Laya'),(57,5,'Bigte'),(58,5,'Kalikasan'),(59,5,'Kinabuchi'),(60,5,'Loma'),(61,5,'Pag-Asa'),(62,5,'Panginabangan'),(63,5,'Pariahan'),(64,5,'Parulan'),(65,5,'Peñaranda'),(66,6,'Balintawak'),(67,6,'Bangolo'),(68,6,'Binondo'),(69,6,'Gagalangin'),(70,6,'Intramuros'),(71,6,'Ipil'),(72,6,'Julo'),(73,6,'Kaligayahan'),(74,6,'Kinabukasan'),(75,6,'Laon-Laan'),(76,6,'Lavezares'),(77,6,'Letre'),(78,6,'Libis'),(79,6,'Longos'),(80,6,'Luneta'),(81,6,'Magsaysay'),(82,6,'Maisahan'),(83,6,'Malate'),(84,6,'Malibay'),(85,6,'Malinaw'),(86,7,'Agora'),(87,7,'Almanza'),(88,7,'Apelo Cruz'),(89,7,'Bagay-Bagay'),(90,7,'Bagbag'),(91,7,'Bagumbayan'),(92,7,'Balikatan'),(93,7,'Balintawak'),(94,7,'Balite'),(95,7,'Balubad'),(96,7,'Balyani'),(97,7,'Bambang'),(98,7,'Banaba'),(99,7,'Banawa'),(100,7,'Banawe'),(101,8,'Bangkal'),(102,8,'Barrio Magsaysay'),(103,8,'Barrio Olimpo'),(104,8,'Bel-Air'),(105,8,'Benedikto Reyes'),(106,8,'Binakayan'),(107,8,'Bingwisan'),(108,8,'Burangis'),(109,8,'Carmona'),(110,8,'Cembo'),(111,8,'Comembo'),(112,8,'Dasmariñas'),(113,8,'Dato'),(114,8,'De La Costa'),(115,8,'Dela Paz'),(116,9,'Baclaran'),(117,9,'Bangad'),(118,9,'Barnachea'),(119,9,'Bgy. 1'),(128,9,'Bgy. 10'),(129,9,'Bgy. 11'),(130,9,'Bgy. 12'),(120,9,'Bgy. 2'),(121,9,'Bgy. 3'),(122,9,'Bgy. 4'),(123,9,'Bgy. 5'),(124,9,'Bgy. 6'),(125,9,'Bgy. 7'),(126,9,'Bgy. 8'),(127,9,'Bgy. 9'),(131,10,'Aguinaldo'),(132,10,'Balangkas'),(133,10,'Balsahan'),(134,10,'Banay'),(135,10,'Bandera'),(136,10,'Bangkal'),(137,10,'Banilad'),(138,10,'Bañong-Parang'),(139,10,'Banquilao'),(140,10,'Banrisya'),(141,11,'Alapan'),(148,11,'Anunas'),(142,11,'Bucal'),(143,11,'Butsukan'),(144,11,'Habagat'),(145,11,'Kanluran'),(146,11,'Kaybiga'),(147,11,'Panapaan'),(149,11,'Tagupo'),(150,11,'Talaba'),(151,12,'Aguho'),(152,12,'Alapan I-A'),(153,12,'Alapan I-B'),(154,12,'Alapan I-C'),(155,12,'Bancal'),(156,12,'Banusil'),(157,12,'Banyong'),(158,12,'Capul'),(159,12,'Dao'),(160,12,'Dasmariñas'),(161,13,'Aplaya'),(162,13,'Balabago'),(165,13,'Baliti'),(163,13,'Banay'),(164,13,'Banlic'),(166,13,'Biñan'),(167,13,'Butas'),(168,13,'Cabuyao'),(169,13,'Casuarina'),(170,13,'Catindig'),(171,14,'Bagong-Tahanan'),(172,14,'Bagumbayan'),(173,14,'Banaba'),(174,14,'Barangka'),(175,14,'Bayuyungan'),(176,14,'Bubuyan'),(177,14,'Burol'),(178,14,'Caloocan'),(179,14,'Canumay'),(180,14,'Casimiro'),(181,15,'Ampid'),(182,15,'Asilo'),(183,15,'Bagay-Bagay'),(184,15,'Bagumbayan'),(185,15,'Baintong'),(186,15,'Balanoy'),(187,15,'Balantakay'),(188,15,'Balao'),(189,15,'Balas'),(190,15,'Balasubas'),(191,16,'Bagong-Silang'),(192,16,'Bagumbayan'),(193,16,'Bailang'),(194,16,'Batingan'),(195,16,'Bicutan'),(196,16,'Blumentritt'),(197,16,'Bocobo'),(198,16,'Boni'),(199,16,'Bunot'),(200,16,'Caruncho'),(201,16,'Casile'),(202,16,'Cavil'),(203,16,'Cayuman'),(204,16,'Concepcion'),(205,16,'Dalig'),(206,17,'Bagong-Nayon'),(207,17,'Bagumbayan'),(208,17,'Bagumbayan-Itaas'),(209,17,'Batuhan'),(210,17,'Binangonan'),(211,17,'Binwangan'),(212,17,'Biyaya'),(213,17,'Bukal'),(214,17,'Bulacan'),(215,17,'Calangyuan'),(216,18,'Barangka'),(218,18,'Barangka-Ibaba'),(217,18,'Barangka-Ilaya'),(219,18,'Baruti'),(220,18,'Concepcion'),(222,18,'Marikina Heights'),(225,18,'Nangka'),(223,18,'San Roque'),(224,18,'Tipas'),(221,18,'Tumana');
/*!40000 ALTER TABLE `barangays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beds`
--

DROP TABLE IF EXISTS `beds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beds` (
  `bed_id` int(11) NOT NULL AUTO_INCREMENT,
  `bed_number` varchar(20) NOT NULL,
  `room_number` varchar(20) NOT NULL,
  `bed_type` enum('ICU','General','Emergency','Recovery') NOT NULL,
  `status` enum('available','occupied','maintenance') DEFAULT 'available',
  `patient_id` int(11) DEFAULT NULL,
  `assigned_nurse` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`bed_id`),
  UNIQUE KEY `bed_number` (`bed_number`),
  KEY `assigned_nurse` (`assigned_nurse`),
  KEY `idx_bed_status` (`status`),
  CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`assigned_nurse`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2241 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beds`
--

LOCK TABLES `beds` WRITE;
/*!40000 ALTER TABLE `beds` DISABLE KEYS */;
INSERT INTO `beds` VALUES (1,'ER-01-B1','ER-01','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 09:13:04'),(2,'ER-01-B2','ER-01','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 03:37:02'),(3,'ER-02-B1','ER-02','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(4,'ER-02-B2','ER-02','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 04:13:26'),(5,'ER-03-B1','ER-03','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 09:13:08'),(6,'ER-03-B2','ER-03','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(7,'ER-04-B1','ER-04','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(8,'ER-04-B2','ER-04','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(9,'ER-05-B1','ER-05','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(10,'ER-05-B2','ER-05','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(11,'ER-06-B1','ER-06','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(12,'ER-06-B2','ER-06','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(13,'ER-07-B1','ER-07','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(14,'ER-07-B2','ER-07','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(15,'ER-08-B1','ER-08','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(16,'ER-08-B2','ER-08','Emergency','available',NULL,NULL,'2026-05-13 00:10:55','2026-05-13 00:10:55');
/*!40000 ALTER TABLE `beds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `municipalities`
--

DROP TABLE IF EXISTS `municipalities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `municipalities` (
  `municipality_id` int(11) NOT NULL AUTO_INCREMENT,
  `province_id` int(11) NOT NULL,
  `municipality_name` varchar(100) NOT NULL,
  PRIMARY KEY (`municipality_id`),
  UNIQUE KEY `unique_municipality` (`province_id`,`municipality_name`),
  CONSTRAINT `municipalities_ibfk_1` FOREIGN KEY (`province_id`) REFERENCES `provinces` (`province_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `municipalities`
--

LOCK TABLES `municipalities` WRITE;
/*!40000 ALTER TABLE `municipalities` DISABLE KEYS */;
INSERT INTO `municipalities` VALUES (3,1,'Bocaue'),(4,1,'Marilao'),(1,1,'Meycauayan'),(5,1,'Obando'),(2,1,'Valenzuela'),(8,2,'Makati'),(6,2,'Manila'),(9,2,'Pasay City'),(7,2,'Quezon City'),(12,3,'Bacoor'),(11,3,'Imus'),(10,3,'Kawit'),(15,4,'Biñan'),(14,4,'San Pedro'),(13,4,'Santa Rosa'),(16,5,'Antipolo'),(18,5,'Marikina'),(17,5,'Taytay');
/*!40000 ALTER TABLE `municipalities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reset_id`),
  KEY `idx_password_reset_token` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_tracking`
--

DROP TABLE IF EXISTS `patient_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_tracking` (
  `tracking_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `from_status` varchar(50) DEFAULT NULL,
  `to_status` varchar(50) DEFAULT NULL,
  `status_started_at` datetime DEFAULT NULL,
  `status_ended_at` datetime DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `wait_time_minutes` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tracking_id`),
  KEY `patient_id` (`patient_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `patient_tracking_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  CONSTRAINT `patient_tracking_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_tracking`
--

LOCK TABLES `patient_tracking` WRITE;
/*!40000 ALTER TABLE `patient_tracking` DISABLE KEYS */;
INSERT INTO `patient_tracking` VALUES (32,7,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 17 min','triage','waiting_for_consultation','2026-05-13 13:31:00','2026-05-13 13:48:04',1,17,'2026-05-13 05:48:04'),(33,7,'Status Updated','Patient status changed from Waiting for Consultation to Ready for Disposition after 0 min','waiting_for_consultation','ready_for_disposition','2026-05-13 13:48:04','2026-05-13 13:48:20',1,0,'2026-05-13 05:48:20'),(34,7,'Status Updated','Patient status changed from Ready for Disposition to Discharged after 0 min','ready_for_disposition','discharge','2026-05-13 13:48:20','2026-05-13 13:48:28',1,0,'2026-05-13 05:48:28'),(35,8,'Old Patient Returned','New emergency room encounter created from old patient record',NULL,NULL,NULL,NULL,1,0,'2026-05-13 05:48:50'),(36,8,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 1 min','triage','waiting_for_consultation','2026-05-13 13:48:00','2026-05-13 13:49:22',1,1,'2026-05-13 05:49:22'),(37,8,'Status Updated','Patient status changed from Waiting for Consultation to Ready for Disposition after 0 min','waiting_for_consultation','ready_for_disposition','2026-05-13 13:49:22','2026-05-13 13:49:39',1,0,'2026-05-13 05:49:39'),(38,8,'Status Updated','Patient status changed from Ready for Disposition to Discharged after 0 min','ready_for_disposition','discharge','2026-05-13 13:49:39','2026-05-13 13:49:45',1,0,'2026-05-13 05:49:45'),(39,9,'Old Patient Returned','New emergency room encounter created from old patient record',NULL,NULL,NULL,NULL,1,0,'2026-05-13 06:39:20'),(40,9,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 0 min','triage','waiting_for_consultation','2026-05-13 14:39:00','2026-05-13 14:39:29',1,0,'2026-05-13 06:39:29'),(41,9,'Status Updated','Patient status changed from Waiting for Consultation to Ready for Disposition after 0 min','waiting_for_consultation','ready_for_disposition','2026-05-13 14:39:29','2026-05-13 14:39:34',1,0,'2026-05-13 06:39:34'),(42,9,'Status Updated','Patient status changed from Ready for Disposition to Discharged after 0 min','ready_for_disposition','discharge','2026-05-13 14:39:34','2026-05-13 14:39:51',1,0,'2026-05-13 06:39:51'),(49,12,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 1 min','triage','waiting_for_consultation','2026-05-13 15:53:00','2026-05-13 15:54:13',1,1,'2026-05-13 07:54:13'),(50,12,'Status Updated','Patient status changed from Waiting for Consultation to Ready for Disposition after 0 min','waiting_for_consultation','ready_for_disposition','2026-05-13 15:54:13','2026-05-13 15:54:27',1,0,'2026-05-13 07:54:27'),(51,12,'Status Updated','Patient status changed from Ready for Disposition to Discharged after 0 min','ready_for_disposition','discharge','2026-05-13 15:54:27','2026-05-13 15:54:35',1,0,'2026-05-13 07:54:35'),(55,14,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 1 min','triage','waiting_for_consultation','2026-05-13 16:13:00','2026-05-13 16:14:44',1,1,'2026-05-13 08:14:44'),(56,15,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 2 min','triage','waiting_for_consultation','2026-05-13 16:15:00','2026-05-13 16:17:15',1,2,'2026-05-13 08:17:15'),(57,16,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 1 min','triage','waiting_for_consultation','2026-05-13 16:16:00','2026-05-13 16:17:22',1,1,'2026-05-13 08:17:22'),(58,17,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 31 min','triage','waiting_for_consultation','2026-05-13 15:50:00','2026-05-13 16:21:58',1,31,'2026-05-13 08:21:58'),(59,17,'Status Updated','Patient status changed from Waiting for Consultation to Ready for Disposition after 13 min','waiting_for_consultation','ready_for_disposition','2026-05-13 16:21:58','2026-05-13 16:35:33',1,13,'2026-05-13 08:35:33'),(60,17,'Status Updated','Patient status changed from Ready for Disposition to Discharged after 0 min','ready_for_disposition','discharge','2026-05-13 16:35:33','2026-05-13 16:35:39',1,0,'2026-05-13 08:35:39'),(61,18,'Status Updated','Patient status changed from Triage to Waiting for Consultation after 1 min','triage','waiting_for_consultation','2026-05-13 16:35:00','2026-05-13 16:36:39',1,1,'2026-05-13 08:36:39'),(62,16,'Status Updated','Patient status changed from Waiting for Consultation to Ready for Disposition after 21 min','waiting_for_consultation','ready_for_disposition','2026-05-13 16:17:22','2026-05-13 16:38:40',1,21,'2026-05-13 08:38:40'),(63,16,'Status Updated','Patient status changed from Ready for Disposition to For Admission after 0 min','ready_for_disposition','for_admission','2026-05-13 16:38:40','2026-05-13 16:39:16',1,0,'2026-05-13 08:39:16'),(64,18,'Status Updated','Patient status changed from Waiting for Consultation to Consultation after 24 min','waiting_for_consultation','consultation','2026-05-13 16:36:39','2026-05-13 17:00:48',1,24,'2026-05-13 09:00:48'),(65,18,'Status Updated','Patient status changed from Consultation to Under Treatment after 0 min','consultation','treatment','2026-05-13 17:00:48','2026-05-13 17:00:50',1,0,'2026-05-13 09:00:50'),(66,18,'Status Updated','Patient status changed from Under Treatment to Waiting Results after 0 min','treatment','waiting_results','2026-05-13 17:00:50','2026-05-13 17:00:53',1,0,'2026-05-13 09:00:53'),(67,18,'Status Updated','Patient status changed from Waiting Results to Ready for Disposition after 0 min','waiting_results','ready_for_disposition','2026-05-13 17:00:53','2026-05-13 17:00:57',1,0,'2026-05-13 09:00:57'),(68,18,'Status Updated','Patient status changed from Ready for Disposition to For Admission after 0 min','ready_for_disposition','for_admission','2026-05-13 17:00:57','2026-05-13 17:01:05',1,0,'2026-05-13 09:01:05'),(69,15,'Status Updated','Patient status changed from Waiting for Consultation to Ready for Disposition after 43 min','waiting_for_consultation','ready_for_disposition','2026-05-13 16:17:15','2026-05-13 17:01:09',1,43,'2026-05-13 09:01:09'),(70,15,'Status Updated','Patient status changed from Ready for Disposition to Discharged after 0 min','ready_for_disposition','discharge','2026-05-13 17:01:09','2026-05-13 17:01:16',1,0,'2026-05-13 09:01:16'),(71,14,'Status Updated','Patient status changed from Waiting for Consultation to Ready for Disposition after 46 min','waiting_for_consultation','ready_for_disposition','2026-05-13 16:14:44','2026-05-13 17:01:19',1,46,'2026-05-13 09:01:19'),(72,14,'Status Updated','Patient status changed from Ready for Disposition to ER Death after 0 min','ready_for_disposition','er_death','2026-05-13 17:01:19','2026-05-13 17:01:31',1,0,'2026-05-13 09:01:31'),(73,18,'Bed Assigned','Assigned admission patient to bed ER-03-B1',NULL,NULL,NULL,NULL,1,0,'2026-05-13 09:03:48'),(74,16,'Bed Assigned','Assigned admission patient to bed ER-01-B1',NULL,NULL,NULL,NULL,1,0,'2026-05-13 09:03:54'),(75,16,'Status Updated','Patient status changed from Admission to Discharged after 33 min','admit_inpatient','discharge','2026-05-13 16:39:16','2026-05-13 17:13:04',1,33,'2026-05-13 09:13:04'),(76,18,'Status Updated','Patient status changed from Admission to Discharged after 12 min','admit_inpatient','discharge','2026-05-13 17:01:05','2026-05-13 17:13:08',1,12,'2026-05-13 09:13:08');
/*!40000 ALTER TABLE `patient_tracking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL AUTO_INCREMENT,
  `permanent_patient_id` varchar(30) DEFAULT NULL,
  `patient_code` varchar(30) NOT NULL,
  `queue_date` date DEFAULT NULL,
  `queue_number` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `house_no_street` varchar(100) DEFAULT NULL,
  `barangay_name` varchar(100) DEFAULT NULL,
  `municipality_name` varchar(100) DEFAULT NULL,
  `province_name` varchar(100) DEFAULT NULL,
  `barangay_id` varchar(20) DEFAULT NULL,
  `municipality_id` varchar(20) DEFAULT NULL,
  `province_id` varchar(20) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `final_diagnosis` varchar(150) DEFAULT NULL,
  `specific_diagnosis` text DEFAULT NULL,
  `current_bed` int(11) DEFAULT NULL,
  `ward_department` varchar(30) NOT NULL DEFAULT 'MEDICAL',
  `current_doctor` int(11) DEFAULT NULL,
  `assigned_nurse` int(11) DEFAULT NULL,
  `admission_date` datetime NOT NULL,
  `triage_date` date DEFAULT NULL,
  `triage_time` time DEFAULT NULL,
  `arrival_time` datetime NOT NULL,
  `status` enum('triage','waiting_for_consultation','consultation','treatment','waiting_results','ready_for_disposition','for_admission','discharge','admit_inpatient','referred','ama','thoc','absconded','dama','doa','er_death','expired','arrival','waiting_area','initial_assessment','diagnostics','treatment_management','reassessment','disposition_decision','refer_other_facility','registration','for_laboratory','for_consult','under_treatment','for_discharge','discharged','admission','trans_in','trans_out','hama','death') NOT NULL DEFAULT 'triage',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `patient_code` (`patient_code`),
  KEY `current_bed` (`current_bed`),
  KEY `current_doctor` (`current_doctor`),
  KEY `assigned_nurse` (`assigned_nurse`),
  KEY `idx_patient_location` (`province_id`,`municipality_id`,`barangay_id`),
  KEY `idx_patient_status` (`status`),
  KEY `idx_patient_admission` (`admission_date`),
  KEY `idx_patient_code` (`patient_code`),
  KEY `idx_permanent_patient_id` (`permanent_patient_id`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`current_bed`) REFERENCES `beds` (`bed_id`) ON DELETE SET NULL,
  CONSTRAINT `patients_ibfk_2` FOREIGN KEY (`current_doctor`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `patients_ibfk_3` FOREIGN KEY (`assigned_nurse`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (7,'PT-000001','JVGH-2026-05-13-0000','2026-05-13',1,'cssr','jvgh','','2000-10-10',25,'Male','CATHOLIC','231312','12','Binuangan','Obando','Bulacan','031414001','031414000','031400000',NULL,'ACUTE ANGLE GLAUCOMA','',NULL,'MEDICAL',NULL,NULL,'2026-05-13 13:45:43','2026-05-13','13:31:00','2026-05-13 13:31:00','discharge','2026-05-13 05:45:43','2026-05-13 05:59:59'),(8,'PT-000001','JVGH-2026-05-13-0001','2026-05-13',2,'cssr','jvgh','','2000-10-10',25,'Male','CATHOLIC','231312','12','Binuangan','Obando','Bulacan','031414001','031414000','031400000',NULL,'ACUTE ANGLE GLAUCOMA','',NULL,'MEDICAL',NULL,NULL,'2026-05-13 13:48:00','2026-05-13','13:48:00','2026-05-13 13:48:00','discharge','2026-05-13 05:48:50','2026-05-13 05:59:59'),(9,'PT-000001','JVGH-2026-05-13-0002','2026-05-13',3,'cssr','jvgh','','2000-10-10',25,'Male','CATHOLIC','231312','12','Binuangan','Obando','Bulacan','031414001','031414000','031400000',NULL,'ANEURYSM','SDF',NULL,'MEDICAL',NULL,NULL,'2026-05-13 14:39:00','2026-05-13','14:39:00','2026-05-13 14:39:00','discharge','2026-05-13 06:39:20','2026-05-13 06:40:07'),(12,'PT-000002','JVGH-2026-05-13-0003','2026-05-13',4,'JAUN','JUAN','J','0000-00-00',24,'Male','CATHOLIC','3213201','2','Liputan','City of Meycauayan','Bulacan','031412013','031412000','031400000',NULL,'ACUTE CORONARY SYNDROME','',NULL,'MEDICAL',NULL,NULL,'2026-05-13 15:53:50','2026-05-13','15:53:00','2026-05-13 15:53:00','discharge','2026-05-13 07:53:50','2026-05-13 07:54:35'),(14,'PT-000003','JVGH-2026-05-13-0004','2026-05-13',5,'TEST','TEST','TEST','2020-02-02',6,'Male','CATHOLIC','321321','23','Poblacion','Pandi','Bulacan','031415016','031415000','031400000',NULL,'ACUTE RESPIRATORY FAILURE','',NULL,'MEDICAL',NULL,NULL,'2026-05-13 16:14:01','2026-05-13','16:13:00','2026-05-13 16:13:00','er_death','2026-05-13 08:14:01','2026-05-13 09:01:31'),(15,'PT-000004','JVGH-2026-05-13-0005','2026-05-13',6,'Q','Q','Q','2009-10-10',16,'Female','CATHOLIC','05465469','12','Santo Niño','Paombong','Bulacan','031416015','031416000','031400000',NULL,'ACUTE CORONARY SYNDROME','',NULL,'MEDICAL',NULL,NULL,'2026-05-13 16:16:12','2026-05-13','16:15:00','2026-05-13 16:15:00','discharge','2026-05-13 08:16:12','2026-05-13 09:01:16'),(16,'PT-000005','JVGH-2026-05-13-0006','2026-05-13',7,'E','E','E','2004-05-22',21,'Male','CATHOLIC','123','2','Catanghalan','Obando','Bulacan','031414002','031414000','031400000',NULL,'ACUTE ABDOMEN','',NULL,'PEDIA',NULL,NULL,'2026-05-13 16:17:05','2026-05-13','16:16:00','2026-05-13 16:16:00','discharge','2026-05-13 08:17:05','2026-05-13 09:13:04'),(17,'PT-000006','JVGH-2026-05-13-0007','2026-05-13',8,'AW','QQWEA','Z','2015-03-06',11,'Male','CATHOLIC','2323','12','','','Bulacan',NULL,NULL,'031400000',NULL,'ACS STEMI','',NULL,'MEDICAL',NULL,NULL,'2026-05-13 16:21:50','2026-05-13','15:50:00','2026-05-13 15:50:00','discharge','2026-05-13 08:21:50','2026-05-13 08:35:39'),(18,'PT-000007','JVGH-2026-05-13-0008','2026-05-13',9,'WE','WE','W','2010-02-02',16,'Male','CATHOLIC','213','2','Poblacion','Pandi','Bulacan','031415016','031415000','031400000',NULL,'ACUTE PANIC ATTACK','',NULL,'MEDICAL',NULL,NULL,'2026-05-13 16:36:21','2026-05-13','16:35:00','2026-05-13 16:35:00','discharge','2026-05-13 08:36:21','2026-05-13 09:13:08');
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `provinces`
--

DROP TABLE IF EXISTS `provinces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `provinces` (
  `province_id` int(11) NOT NULL AUTO_INCREMENT,
  `province_name` varchar(100) NOT NULL,
  PRIMARY KEY (`province_id`),
  UNIQUE KEY `province_name` (`province_name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `provinces`
--

LOCK TABLES `provinces` WRITE;
/*!40000 ALTER TABLE `provinces` DISABLE KEYS */;
INSERT INTO `provinces` VALUES (1,'Bulacan'),(3,'Cavite'),(4,'Laguna'),(2,'Metro Manila'),(5,'Rizal');
/*!40000 ALTER TABLE `provinces` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `nurse_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `assigned_beds` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assigned_beds`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`schedule_id`),
  KEY `nurse_id` (`nurse_id`),
  CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`nurse_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `triage`
--

DROP TABLE IF EXISTS `triage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `triage` (
  `triage_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `triage_level` enum('red','yellow','green','black','emergency','urgent','non-urgent') NOT NULL,
  `chief_complaint` text NOT NULL,
  `assessment` text DEFAULT NULL,
  `vital_signs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`vital_signs`)),
  `assigned_by` int(11) NOT NULL,
  `triage_time` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`triage_id`),
  UNIQUE KEY `patient_id` (`patient_id`),
  KEY `assigned_by` (`assigned_by`),
  KEY `idx_triage_level` (`triage_level`),
  CONSTRAINT `triage_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  CONSTRAINT `triage_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `triage`
--

LOCK TABLES `triage` WRITE;
/*!40000 ALTER TABLE `triage` DISABLE KEYS */;
INSERT INTO `triage` VALUES (7,7,'yellow','3','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 13:48:04','2026-05-13 05:48:04'),(8,8,'yellow','23','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 13:49:22','2026-05-13 05:49:22'),(9,9,'yellow','23','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 14:39:29','2026-05-13 06:39:29'),(12,12,'yellow','123','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 15:54:13','2026-05-13 07:54:13'),(14,14,'yellow','EE','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 16:14:44','2026-05-13 08:14:44'),(15,15,'yellow','ZSDA','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 16:17:15','2026-05-13 08:17:15'),(16,16,'yellow','ASDASD','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 16:17:22','2026-05-13 08:17:22'),(17,17,'yellow','23','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 16:21:58','2026-05-13 08:21:58'),(18,18,'yellow','SD','','{\"temperature\":\"\",\"blood_pressure\":\"\",\"heart_rate\":\"\",\"respiratory_rate\":\"\"}',1,'2026-05-13 16:36:39','2026-05-13 08:36:39');
/*!40000 ALTER TABLE `triage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('chief_nurse','nurse_supervisor','head_nurse','staff_nurse','nursing_attendant','nurse','doctor','admission_staff','admin') NOT NULL,
  `department` enum('emergency_room','out_patient_department') DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_user_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$fKA1InaXzGybmv/wrBpe3uT5Si/JgPb.RnJgqLVd3ykUYqTAF0HEi','admin@nursing-system.local','System','Admin','admin','emergency_room',NULL,1,'2026-05-13 00:10:55','2026-05-13 00:10:55'),(2,'doctor','$2y$10$yPgzvV79Z.MRx.Mu8gRo6e.VEmyGROy/EsKg9S1Pei4o6/QqYz2R6','doctor@gmail.com','doctor','doctor','doctor','emergency_room',NULL,1,'2026-05-13 00:21:40','2026-05-13 00:21:40'),(3,'admitting','$2y$10$mPVASEeU7M.WyGF2KMyML./KvNOCoIW81sZ9PqDG3s85/ZwZPDSJS','admitting@gmail.com','admitting','admitting','admission_staff','emergency_room',NULL,1,'2026-05-13 03:36:19','2026-05-13 03:36:19'),(4,'nurse','$2y$10$RXpgkSSQo3PNjMYVYSY9Ue5NR/j5nUyN2vSnuoavwI86v4ogBtwHm','nurse@gmail.com','nurse','nurse','staff_nurse','emergency_room',NULL,1,'2026-05-13 03:44:07','2026-05-13 03:44:07');
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

-- Dump completed on 2026-05-13 17:34:20
