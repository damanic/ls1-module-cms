alter table cms_stats_settings
add column ga_enabled tinyint,
add column ga_username varchar(255),
add column ga_password varchar(255),
add column ga_siteid varchar(100);