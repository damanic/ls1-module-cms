<?php

	if (Db_DbHelper::scalar('select count(*) from cms_settings'))
		Db_DbHelper::query('update cms_settings set resources_dir_path=\'resources\'');
	else
		Db_DbHelper::query('insert into cms_settings(resources_dir_path) values(\'resources\')');

?>
