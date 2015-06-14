alter table cms_global_content_blocks add column block_type varchar(10);
update cms_global_content_blocks set block_type = 'html';