alter table cms_stats_settings add column enable_builtin_statistics tinyint(4);
update cms_stats_settings set enable_builtin_statistics=1;