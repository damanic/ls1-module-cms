alter table pages add column has_contentblocks tinyint;

CREATE TABLE `content_blocks` (
  `id` int(11) NOT NULL auto_increment,
  `code` varchar(100) default NULL,
  `page_id` int(11) default NULL,
  `content` text,
  PRIMARY KEY  (`id`),
  KEY `page_and_code` (`code`,`page_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;