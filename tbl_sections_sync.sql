CREATE TABLE `tbl_sections_sync` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guid` varchar(32) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `section` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;