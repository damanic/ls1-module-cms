CREATE TABLE `cms_themes` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) default NULL,
  `code` varchar(100) default NULL,
  `description` text,
  `author_name` varchar(255) default NULL,
  `author_website` varchar(255) default NULL,
  `is_default` tinyint(4) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;