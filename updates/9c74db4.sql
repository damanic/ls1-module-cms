CREATE TABLE `cms_page_references` (
  `object_class_name` varchar(100) NOT NULL,
  `object_id` int(11) NOT NULL default '0',
  `reference_name` varchar(100) NOT NULL default '',
  `page_id` int(11) default NULL,
  `id` int(11) NOT NULL auto_increment,
  PRIMARY KEY  (`id`),
  KEY `object_class_name` (`object_class_name`,`object_id`,`reference_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;