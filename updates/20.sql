alter table pages add column label varchar(255);
update pages set label = title;