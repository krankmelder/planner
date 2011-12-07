-- Table structure for table `planner`
CREATE TABLE `planner` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `starred` int(1) NOT NULL DEFAULT '0',
  `datetime` datetime DEFAULT NULL,
  `text` text NOT NULL,
  `done` int(1) NOT NULL DEFAULT '0',
  `deleted` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  CONSTRAINT `user_id_fk_events` FOREIGN KEY (`user_id`)
  REFERENCES `users`(`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) /*!40000 ENGINE=INNODB */ /*!40101 CHARACTER SET utf8 COLLATE utf8_general_ci */;
