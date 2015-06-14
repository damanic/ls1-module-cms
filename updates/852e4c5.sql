alter table cms_themes add column templating_engine varchar(50);
update cms_themes set templating_engine='php';
	
alter table cms_settings add column default_templating_engine varchar(50);
update cms_settings set default_templating_engine='php';