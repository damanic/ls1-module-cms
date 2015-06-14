alter table pages add column protocol varchar(10);
update pages set protocol = 'any';