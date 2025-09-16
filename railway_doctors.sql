-- MySQL dump 10.13  Distrib 8.0.38, for Win64 (x86_64)
--
-- Host: switchback.proxy.rlwy.net    Database: railway
-- ------------------------------------------------------
-- Server version	9.4.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `doctors`
--

DROP TABLE IF EXISTS `doctors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialization_id` bigint unsigned NOT NULL,
  `str_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `practice_location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_picture` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `doctors_str_id_unique` (`str_id`),
  KEY `doctors_specialization_id_foreign` (`specialization_id`),
  CONSTRAINT `doctors_specialization_id_foreign` FOREIGN KEY (`specialization_id`) REFERENCES `specializations` (`specialization_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctors`
--

LOCK TABLES `doctors` WRITE;
/*!40000 ALTER TABLE `doctors` DISABLE KEYS */;
INSERT INTO `doctors` VALUES (1,'2025-08-15 13:10:53','2025-08-21 17:49:15','Imam Azhari',1,'141414221414','RS Muhammadiyah Bantul','-',NULL),(2,'2025-08-21 17:08:38','2025-08-21 17:08:38','dr. Sisca Wulandari, Sp.PD',2,'3525235342','RS Muhammadiyah Bantul','-',NULL),(3,'2025-08-21 17:10:22','2025-08-21 17:49:21','dr. Harik Firman Thahadian, Ph.D., Sp.PD',2,'32532423423','RS Muhammadiyah Bantul','-',NULL),(4,'2025-08-21 17:10:39','2025-08-21 17:49:29',' dr. Zainul Arifin, Sp.PD',2,'23534234','RS Muhammadiyah Bantul','-',NULL),(5,'2025-08-21 17:11:35','2025-08-21 17:49:40','dr. Novi Wijayanti Sukirto, M.Sc., Sp.PD',2,'124212424141','RS Muhammadiyah Bantul','-',NULL),(6,'2025-08-21 17:12:18','2025-08-21 17:49:51','dr. H. Barkah Djaka P., Sp.PD-KGH FINASIM',2,'35252353423','RS Muhammadiyah Bantul','-',NULL),(7,'2025-08-21 17:13:38','2025-08-21 17:49:59',' Dr. dr. Neneng Ratnasari, Sp.PD-KGEH FINASIM',2,'342423243242343','RS Muhammadiyah Bantul','-',NULL),(8,'2025-08-21 17:13:51','2025-08-21 17:51:03','dr. H. Sumardi, Sp.PD-KP FINASIM',2,'32432432432','RS Muhammadiyah Bantul','-',NULL);
/*!40000 ALTER TABLE `doctors` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-22 18:30:43
