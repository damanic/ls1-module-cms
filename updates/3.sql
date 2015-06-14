CREATE TABLE `partials` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(100) default NULL,
  `description` varchar(255) default NULL,
  `html_code` text,
  `created_user_id` int(11) default NULL,
  `updated_user_id` int(11) default NULL,
  `created_at` datetime default NULL,
  `updated_at` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;