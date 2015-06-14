CREATE TABLE `page_customer_groups` (
  `page_id` int(11) NOT NULL default '0',
  `customer_group_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`page_id`,`customer_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

alter table pages add column enable_page_customer_group_filter tinyint;