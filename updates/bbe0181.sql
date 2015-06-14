alter table cms_themes
	add column agent_detection_mode char(10),
	add column agent_list text,
	add column agent_detection_code text;