-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for glowupveritabanı
CREATE DATABASE IF NOT EXISTS `glowupveritabanı` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `glowupveritabanı`;

-- Dumping structure for table glowupveritabanı.chat
CREATE TABLE IF NOT EXISTS `chat` (
  `chat_ID` int NOT NULL AUTO_INCREMENT,
  `ticket_ID` int NOT NULL,
  `user_ID` int NOT NULL,
  `chat_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `chat_created_at` timestamp NOT NULL,
  `chat_attachment_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`chat_ID`) USING BTREE,
  KEY `ticket_ID_key` (`ticket_ID`),
  KEY `user_ID_key` (`user_ID`),
  CONSTRAINT `ticket_ID_key` FOREIGN KEY (`ticket_ID`) REFERENCES `tickets` (`ticket_ID`),
  CONSTRAINT `user_ID_key` FOREIGN KEY (`user_ID`) REFERENCES `users` (`user_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glowupveritabanı.chat: ~0 rows (approximately)
INSERT INTO `chat` (`chat_ID`, `ticket_ID`, `user_ID`, `chat_message`, `chat_created_at`, `chat_attachment_file`) VALUES
	(9, 1001, 4, 'Merhaba, şifre sıfırlama mailini bir türlü alamıyorum. Spam kutusuna da baktım.', '2026-08-14 18:23:37', NULL),
	(10, 1001, 2, 'Merhaba Can Bey, sistemlerimizi kontrol ediyorum, mail sunucusunda küçük bir gecikme yaşanıyor. Şifrenizi manuel olarak sıfırlayıp size iletiyorum.', '2026-08-14 18:23:37', NULL),
	(11, 1002, 5, 'İyi günler, faturamdaki bu 50 TL ek ücretin detayı nedir?', '2026-08-14 18:23:37', NULL),
	(12, 1002, 3, 'Elif Hanım merhaba, incelememize göre geçtiğimiz ayki aşım ücretiniz bu faturaya yansımış. Detaylı dökümü ekte gönderiyorum.', '2026-08-14 18:23:37', NULL),
	(13, 1004, 4, 'Üyeliğimin hemen iptal edilmesini rica ediyorum.', '2026-08-14 18:23:37', NULL);

-- Dumping structure for table glowupveritabanı.departments
CREATE TABLE IF NOT EXISTS `departments` (
  `department_ID` int NOT NULL,
  `department_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  PRIMARY KEY (`department_ID`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glowupveritabanı.departments: ~0 rows (approximately)
INSERT INTO `departments` (`department_ID`, `department_name`) VALUES
	(1, 'Teknik Destek'),
	(2, 'Muhasebe ve Faturalandırma'),
	(3, 'Satış ve Pazarlama'),
	(4, 'Müşteri İlişkileri'),
	(5, 'İnsan Kaynakları');

-- Dumping structure for table glowupveritabanı.notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `notification_ID` int NOT NULL AUTO_INCREMENT,
  `user_ID` int NOT NULL,
  `ticket_ID` int DEFAULT NULL,
  `notification_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT (now()),
  `notification_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`notification_ID`) USING BTREE,
  KEY `FK__users` (`user_ID`) USING BTREE,
  KEY `FK__tickets` (`ticket_ID`) USING BTREE,
  CONSTRAINT `FK__tickets` FOREIGN KEY (`ticket_ID`) REFERENCES `tickets` (`ticket_ID`),
  CONSTRAINT `FK__users` FOREIGN KEY (`user_ID`) REFERENCES `users` (`user_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glowupveritabanı.notifications: ~0 rows (approximately)
INSERT INTO `notifications` (`notification_ID`, `user_ID`, `ticket_ID`, `notification_message`, `is_read`, `created_at`, `notification_type`) VALUES
	(24, 4, 1001, 'Destek talebinize Ali Veli tarafından yanıt verildi.', 0, '2026-08-14 21:23:37', 'ticket_update'),
	(25, 2, 1001, 'Can Öztürk tarafından yeni bir destek talebi oluşturuldu.', 1, '2026-08-14 21:23:37', 'new_ticket'),
	(26, 5, 1002, 'Fatura talebinizin durumu "In Progress" (İşlemde) olarak güncellendi.', 0, '2026-08-14 21:23:37', 'status_change'),
	(27, 3, 1002, 'Size yeni bir fatura destek talebi atandı.', 1, '2026-08-14 21:23:37', 'ticket_assigned'),
	(28, 6, 1003, 'Uygulama çökmesi ile ilgili talebiniz başarıyla alındı.', 0, '2026-08-14 21:23:37', 'ticket_created');

-- Dumping structure for table glowupveritabanı.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `role_ID` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  PRIMARY KEY (`role_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glowupveritabanı.roles: ~3 rows (approximately)
INSERT INTO `roles` (`role_ID`, `role_name`) VALUES
	(1, 'Admin'),
	(2, 'Employee'),
	(3, 'Customer');

-- Dumping structure for table glowupveritabanı.tickets
CREATE TABLE IF NOT EXISTS `tickets` (
  `ticket_ID` int NOT NULL AUTO_INCREMENT,
  `ticket_title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `ticket_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `ticket_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `ticket_priority` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `customer_ID` int NOT NULL,
  `employee_ID` int DEFAULT NULL,
  `department_ID` int DEFAULT NULL,
  `ticket_created_at` timestamp NOT NULL,
  `ticket_attachment_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`ticket_ID`) USING BTREE,
  KEY `department_ID_key` (`department_ID`),
  KEY `customer_ID_fk` (`customer_ID`),
  KEY `employee_ID_fk` (`employee_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=350336 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glowupveritabanı.tickets: ~2 rows (approximately)
INSERT INTO `tickets` (`ticket_ID`, `ticket_title`, `ticket_description`, `ticket_status`, `ticket_priority`, `customer_ID`, `employee_ID`, `department_ID`, `ticket_created_at`, `ticket_attachment_file`) VALUES
	(1001, 'Sisteme giriş yapamıyorum', 'Şifremi sıfırlamaya çalıştığımda mail gelmiyor.', 'Open', 'High', 4, 2, 1, '2026-08-14 18:23:37', NULL),
	(1002, 'Faturam yanlış hesaplanmış', 'Ağustos ayı faturamda fazladan 50 TL ücret yansıtılmış.', 'In Progress', 'Medium', 5, 3, 2, '2026-08-14 18:23:37', NULL),
	(1003, 'Uygulama çöküyor', 'Mobil uygulamaya giriş yaparken uygulama aniden kapanıyor.', 'Open', 'High', 6, 2, 1, '2026-08-14 18:23:37', NULL),
	(1004, 'Premium üyelik iptali', 'Premium üyeliğimi iptal etmek istiyorum, yardımcı olur musunuz?', 'Resolved', 'Low', 4, 3, 2, '2026-08-14 18:23:37', NULL),
	(1005, 'Sunucu bağlantı hatası', 'API sunucularınıza bağlanırken timeout hatası alıyorum.', 'Open', 'High', 5, NULL, 1, '2026-08-14 18:23:37', NULL);

-- Dumping structure for table glowupveritabanı.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_ID` int NOT NULL,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `phone_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `role_ID` int NOT NULL DEFAULT '3',
  `department_ID` int DEFAULT NULL,
  `join_date` timestamp NULL DEFAULT NULL,
  `is_verified` tinyint NOT NULL DEFAULT (0),
  `verify_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `emp_status` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `token_expires_at` timestamp NULL DEFAULT NULL,
  `last_mail_sent` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`user_ID`) USING BTREE,
  UNIQUE KEY `unique_email` (`email`),
  KEY `role_ID_key` (`role_ID`),
  KEY `department_ID_fk` (`department_ID`),
  CONSTRAINT `department_ID_fk` FOREIGN KEY (`department_ID`) REFERENCES `departments` (`department_ID`),
  CONSTRAINT `role_ID_key` FOREIGN KEY (`role_ID`) REFERENCES `roles` (`role_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table glowupveritabanı.users: ~3 rows (approximately)
INSERT INTO `users` (`user_ID`, `username`, `first_name`, `last_name`, `email`, `password`, `phone_number`, `role_ID`, `department_ID`, `join_date`, `is_verified`, `verify_token`, `emp_status`, `profile_image`, `token_expires_at`, `last_mail_sent`) VALUES
	(1, 'superadmin', 'Ahmet', 'Yılmaz', 'admin@glowup.com', 'hashed_password_123', '05320000001', 1, NULL, '2026-08-14 18:23:37', 1, NULL, 'active', NULL, NULL, NULL),
	(2, 'tech_ali', 'Ali', 'Veli', 'ali.tech@glowup.com', 'hashed_password_123', '05320000002', 2, 1, '2026-08-14 18:23:37', 1, NULL, 'active', NULL, NULL, NULL),
	(3, 'bill_ayse', 'Ayşe', 'Demir', 'ayse.bill@glowup.com', 'hashed_password_123', '05320000003', 2, 2, '2026-08-14 18:23:37', 1, NULL, 'active', NULL, NULL, NULL),
	(4, 'customer_can', 'Can', 'Öztürk', 'can.ozturk@gmail.com', 'hashed_password_123', '05320000004', 3, NULL, '2026-08-14 18:23:37', 1, NULL, NULL, NULL, NULL, NULL),
	(5, 'customer_elif', 'Elif', 'Şahin', 'elif.sahin@hotmail.com', 'hashed_password_123', '05320000005', 3, NULL, '2026-08-14 18:23:37', 1, NULL, NULL, NULL, NULL, NULL),
	(6, 'customer_burak', 'Burak', 'Çelik', 'burak.celik@yahoo.com', 'hashed_password_123', '05320000006', 3, NULL, '2026-08-14 18:23:37', 1, NULL, NULL, NULL, NULL, NULL),
	(889426, 'glowup', 'Glow', 'Up', 'service.glowupcorp@gmail.com', '$2y$12$81VJlmBD1JA3NZbvcTigrOwciETCuBfTzw/6eE4UOu0NF4ejIpveq', '0532 458 98 78', 1, NULL, '2026-08-14 18:27:21', 1, NULL, NULL, NULL, NULL, '2026-08-14 18:28:55');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
