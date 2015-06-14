alter table pages add column theme_id int;
alter table partials add column theme_id int;
alter table templates add column theme_id int;

create index theme_id on pages(theme_id);
create index theme_id on partials(theme_id);
create index theme_id on templates(theme_id);