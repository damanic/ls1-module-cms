CREATE TABLE `page_security_modes` (
  `id` varchar(15) NOT NULL,
  `name` varchar(255) default NULL,
  `description` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into page_security_modes(id, name, description) values('everyone', 'All', 'Guests and customers will be able to access this page.');
insert into page_security_modes(id, name, description) values('customers', 'Customers only', 'Only logged in customers will be able to access this page.');
insert into page_security_modes(id, name, description) values('guests', 'Guests only', 'Only guest users will be able to access this page.');
	
alter table pages add column security_mode_id varchar(15) default 'everyone';