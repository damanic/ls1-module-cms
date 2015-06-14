<?php

	class Cms_SecurityMode extends Db_ActiveRecord
	{
		const everyone = 'everyone';
		const customers = 'customers';
		const guests = 'guests';
		
		public $table_name = 'page_security_modes';
	}

?>