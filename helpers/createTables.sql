DROP DATABASE IF EXISTS `cache`;

CREATE DATABASE `cache` /*!40100 DEFAULT CHARACTER SET utf8mb4 */;

CREATE TABLE `cache`.`dataStore` (
  `key` varchar(512) NOT NULL,
  `html` mediumtext,
  `header` text,
  `expiry` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`key`),
  UNIQUE KEY `key_UNIQUE` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cache`.`queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payload` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cache`.`info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `origin` varchar(512) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;

