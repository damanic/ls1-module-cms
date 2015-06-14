CREATE TABLE `cms_global_content_blocks` (
  `id` int(11) NOT NULL auto_increment,
  `content` text,
  `created_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `updated_user_id` int(11) default NULL,
  `updated_at` datetime default NULL,
  `code` varchar(255) default NULL,
  `name` varchar(255) default NULL,
  PRIMARY KEY  (`id`),
  KEY `code` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;