alter table pages add column navigation_sort_order int;
update pages set navigation_sort_order=id;