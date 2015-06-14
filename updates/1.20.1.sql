alter table cms_stats_settings
add column ga_enable_tracking tinyint;
update cms_stats_settings set ga_enable_tracking = ga_enabled;