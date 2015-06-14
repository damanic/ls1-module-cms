alter table pages add column is_published tinyint default 1;
update pages set is_published = 1;