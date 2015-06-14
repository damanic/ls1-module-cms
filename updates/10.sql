CREATE TABLE `cms_stats_settings` (
  `id` int(11) NOT NULL auto_increment,
  `keep_pageviews` int(11) default NULL,
  `ip_filters` text,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;