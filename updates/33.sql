alter table pages 
add column parent_id int,
add column navigation_visible tinyint;

update pages set navigation_visible = 1;