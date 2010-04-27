CREATE TABLE `tbl_sections_sync` (
  `section` varchar(32) NOT NULL DEFAULT '',
  `xml` text,
  PRIMARY KEY (`section`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;