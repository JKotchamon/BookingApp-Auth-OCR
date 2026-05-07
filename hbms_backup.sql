-- MySQL dump 10.13  Distrib 8.0.36, for Linux (x86_64)
--
-- Host: localhost    Database: hbmsdb
-- ------------------------------------------------------
-- Server version	8.0.36

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
-- Table structure for table `tbladmin`
--

DROP TABLE IF EXISTS `tbladmin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbladmin` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `AdminName` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `UserName` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MobileNumber` bigint DEFAULT NULL,
  `Email` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Password` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `AdminRegdate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbladmin`
--

LOCK TABLES `tbladmin` WRITE;
/*!40000 ALTER TABLE `tbladmin` DISABLE KEYS */;
INSERT INTO `tbladmin` VALUES (1,'Admin','admin',5689784592,'admin@gmail.com','f925916e2754e5e03f75dd58a5733251','2023-02-01 07:25:30');
/*!40000 ALTER TABLE `tbladmin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblbooking`
--

DROP TABLE IF EXISTS `tblbooking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblbooking` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `RoomId` int DEFAULT NULL,
  `BookingNumber` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `UserID` int NOT NULL,
  `IDType` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Gender` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Address` mediumtext COLLATE utf8mb4_general_ci,
  `CheckinDate` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CheckoutDate` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `BookingDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `Remark` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblbooking`
--

LOCK TABLES `tblbooking` WRITE;
/*!40000 ALTER TABLE `tblbooking` DISABLE KEYS */;
INSERT INTO `tblbooking` VALUES (1,1,'390343987',3,'Voter Card','Female','A 123 Gaur Apartment Ghaziabad UP 201017','2023-05-10','2023-05-15','2023-05-02 07:12:29','Booking accepted','Approved','2023-05-02 07:15:51'),(2,2,'545403040',4,'Voter Card','Male','A 12232 ABC Apartment Mayur Vihar New Delhi','2023-05-20','2023-05-25','2023-05-05 02:50:41','Booking Accepted','Approved','2023-05-05 02:51:35'),(3,1,'121281639',4,'Passport','Female','xxxxxx','2026-03-25','2026-03-25','2026-03-10 05:28:30',NULL,NULL,NULL),(4,2,'756567740',4,'Passport','Female','gggggggggggggggg','2026-03-10','2026-04-03','2026-03-10 05:32:27',NULL,NULL,NULL),(5,3,'463661995',4,'Passport','Female','ffff','2026-03-11','2026-03-13','2026-03-10 05:35:09',NULL,NULL,NULL);
/*!40000 ALTER TABLE `tblbooking` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblcategory`
--

DROP TABLE IF EXISTS `tblcategory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblcategory` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `CategoryName` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Description` mediumtext COLLATE utf8mb4_general_ci,
  `Price` int NOT NULL,
  `Date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `ID` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblcategory`
--

LOCK TABLES `tblcategory` WRITE;
/*!40000 ALTER TABLE `tblcategory` DISABLE KEYS */;
INSERT INTO `tblcategory` VALUES (1,'Single Room','Only for one person',800,'2023-02-23 06:43:55'),(2,'Double Room','For Two Person',1100,'2023-03-23 06:44:55'),(3,'Triple Room','A room assigned to three people. May have two or more beds.',1200,'2023-04-01 06:45:27'),(4,'Quad Room','A room assigned to four people. May have two or more beds.',1800,'2020-02-28 06:45:56'),(5,'Queen Room','A room with a queen-sized bed. May be occupied by one or more people',2000,'2023-05-01 06:46:30');
/*!40000 ALTER TABLE `tblcategory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblcontact`
--

DROP TABLE IF EXISTS `tblcontact`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblcontact` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `Name` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MobileNumber` bigint DEFAULT NULL,
  `Email` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Message` mediumtext COLLATE utf8mb4_general_ci,
  `EnquiryDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `IsRead` int DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblcontact`
--

LOCK TABLES `tblcontact` WRITE;
/*!40000 ALTER TABLE `tblcontact` DISABLE KEYS */;
INSERT INTO `tblcontact` VALUES (1,'Joh Doe',1425365412,'johnd@test.com','I want o stay in hotel','2023-05-05 02:53:52',1);
/*!40000 ALTER TABLE `tblcontact` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblfacility`
--

DROP TABLE IF EXISTS `tblfacility`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblfacility` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `FacilityTitle` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Description` mediumtext COLLATE utf8mb4_general_ci,
  `Image` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblfacility`
--

LOCK TABLES `tblfacility` WRITE;
/*!40000 ALTER TABLE `tblfacility` DISABLE KEYS */;
INSERT INTO `tblfacility` VALUES (1,'24-Hour room service','24-Hour room service available','b9fb9d37bdf15a699bc071ce49baea531582890659.jpg','2023-04-29 11:54:05'),(2,'Free wireless internet access','Free wireless internet access available in room restro area','7fdc1a630c238af0815181f9faa190f51582890845.jpg','2023-04-29 11:54:05'),(3,'Laundry service','Free Laundry service available for a customer who book queen and king size room','3c7baecb174a0cbcc64507e9c3308c4b1582890987.jpg','2023-04-29 11:54:05'),(4,'Tour & excursions','vehicle are available for tour and travels','1e6ae4ada992769567b71815f124fac51582891174.jpg','2023-04-29 11:54:05'),(5,'Airport transfers','Airport transfers facility available on demand','c9e82378a39eec108727a123b09056651582891272.jpg','2023-04-29 11:54:05'),(6,'Babysitting on request','Babysitting on request','c26be60cfd1ba40772b5ac48b95ab19b1582891331.png','2023-04-29 11:54:05'),(7,'24-Hour doctor on call','24-Hour doctor on call','55ccf27d26d7b23839986b6ae2e447ab1582891425.jpg','2023-04-29 11:54:05'),(8,'Meeting facilities','Meeting facilities available for company person','efc1a80c391be252d7d777a437f868701582891713.jpg','2023-04-29 11:54:05');
/*!40000 ALTER TABLE `tblfacility` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblpage`
--

DROP TABLE IF EXISTS `tblpage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblpage` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `PageType` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `PageTitle` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `PageDescription` mediumtext COLLATE utf8mb4_general_ci,
  `Email` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MobileNumber` bigint DEFAULT NULL,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblpage`
--

LOCK TABLES `tblpage` WRITE;
/*!40000 ALTER TABLE `tblpage` DISABLE KEYS */;
INSERT INTO `tblpage` VALUES (1,'aboutus','About Us','We have 72 comfortably equipped rooms, including two suites: The President Suite and the Ambassador Suite, with over one hundred metres of surface area, which are sure to awe even the most demanding Guests. We offer 7 rooms, where we have been preparing family and business meetings already for 15 years (This is a test project. Do not book.)',NULL,NULL,NULL),(2,'contactus','Contact Us','325, Avenida Santos Dumont, Campo de Marte, São Paulo, Brazil','info@gmail.com',8529631236,'2026-03-10 05:25:28');
/*!40000 ALTER TABLE `tblpage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tblroom`
--

DROP TABLE IF EXISTS `tblroom`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tblroom` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `RoomType` int DEFAULT NULL,
  `RoomName` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MaxAdult` int DEFAULT NULL,
  `MaxChild` int DEFAULT NULL,
  `RoomDesc` mediumtext COLLATE utf8mb4_general_ci,
  `NoofBed` int DEFAULT NULL,
  `Image` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `RoomFacility` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `RoomType` (`RoomType`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tblroom`
--

LOCK TABLES `tblroom` WRITE;
/*!40000 ALTER TABLE `tblroom` DISABLE KEYS */;
INSERT INTO `tblroom` VALUES (1,1,'Single Room for one person',1,2,'A single room is for one person and contains a single bed, and will usually be quite small',1,'2870b3543f2550c16a4551f03a0b84ac1582975994.jpg','24-Hour room service,Free wireless internet acces','2023-04-29 11:33:14'),(2,2,'Double Room',2,2,'A double room is a room intended for two people, usually a couple, to stay in. One person occupying a double room has to pay a supplement.',2,'74375080377499ab76dad37484ee7f151582982180.jpg','24-Hour room service,Free wireless internet acces','2023-04-29 11:33:14'),(3,3,'triple room',4,2,'A triple room is a hotel room that is made to comfortably accommodate three people. The triple room , simply called a triple, at times, may be configured with different bed sizes to ensure three hotel guests can be accommodated comfortably.',3,'5ebc75f329d3b6f84d44c2c2e9764d4f1582976638.jpg','24-Hour room service,Free wireless internet access,Laundry service,Babysitting on request,24-Hour doctor on call,Meeting facilities','2023-04-29 11:33:14'),(4,4,'Quad Room',6,3,'A quad, when referring to hotel rooms, is a room that can accommodate four people. The quad room may be configured with different bed sizes to ensure four hotel guests can be accommodated comfortably:',4,'0cdcf50ea65522a6e15d4e0ac383a30e1582976749.jpg','24-Hour room service,Free wireless internet access,Laundry service,Tour & excursions,Airport transfers,Babysitting on request,24-Hour doctor on call,Meeting facilities','2023-04-29 11:33:14'),(5,5,'Queen Room',2,1,'A room with a queen-size bed. It may be occupied by one or more people (Size: 153 x 203 cm). King:',1,'7edd3d2f392c4a07d107f07cbe764fa51582977081.jpg','24-Hour room service,Free wireless internet access,Laundry service,Tour & excursions,Airport transfers,Babysitting on request,24-Hour doctor on call,Meeting facilities','2023-04-29 11:33:14'),(6,1,'Single Room with Balcony',1,2,'Each room is equipped with satellite TV, minibar and a tea/coffee maker. Ironing facilities are provided in all rooms.\r\n\r\nTreebo Select Royal Garden offers a well-equipped business centre. Guests can make travel arrangements at the tour desk.\r\n\r\nCheckers Restaurant serves a variety of Indian, Chinese and Continental dishes.',1,'ca3de1cf40a0af9351083d4b0e95736c1583047692.jpg','24-Hour doctor on call','2023-04-29 11:33:14');
/*!40000 ALTER TABLE `tblroom` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tbluser`
--

DROP TABLE IF EXISTS `tbluser`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbluser` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `FullName` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `MobileNumber` bigint DEFAULT NULL,
  `Email` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `Password` varchar(120) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `RegDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `ID` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tbluser`
--

LOCK TABLES `tbluser` WRITE;
/*!40000 ALTER TABLE `tbluser` DISABLE KEYS */;
INSERT INTO `tbluser` VALUES (1,'Test',7897897899,'test@gmail.com','202cb962ac59075b964b07152d234b70','2023-03-24 17:07:28'),(2,'Sample',4644654646,'sample@gmail.com','202cb962ac59075b964b07152d234b70','2023-04-30 12:51:42'),(3,'Anuj Kumar',1234569871,'Test@test.com','f925916e2754e5e03f75dd58a5733251','2023-05-01 14:53:36'),(4,'John Doe',4125365412,'johndeo@test.com','f925916e2754e5e03f75dd58a5733251','2023-05-05 02:49:44');
/*!40000 ALTER TABLE `tbluser` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-13 22:14:33
