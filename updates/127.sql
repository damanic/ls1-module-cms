alter table pages add column directory_name varchar(255);
alter table partials add column file_name varchar(255);
alter table templates add column file_name varchar(255);

CREATE TABLE `cms_settings` (
  `id` int(11) NOT NULL auto_increment,
  `enable_filebased_templates` tinyint(4) default NULL,
  `templates_dir_path` varchar(255) default NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;