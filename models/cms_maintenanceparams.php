<?

	class Cms_MaintenanceParams extends Core_Configuration_Model
	{
		public $record_code = 'maintenance_configuration';
		
		public static function create()
		{
			$configObj = new Cms_MaintenanceParams();
			return $configObj->load();
		}

		protected function build_form()
		{
			$this->add_field('enabled', 'Enable maintenance mode', 'full', db_number)->renderAs(frm_checkbox)->comment("During the maintenance session visitors will see the page which you select below.", "above");

			$this->add_field('maintenance_page', 'Maintenance notification page', 'full', db_text)->renderAs(frm_dropdown)->cssClassName('checkbox_align')->noLabel()->optionsHtmlEncode(false);
		}
		
		public function get_maintenance_page_options($key_value = -1)
		{
			return Cms_Page::create()->get_page_tree_options($key_value);
		}
		
		public static function set_status($enabled)
		{
			$obj = self::create();
			if (!$obj->maintenance_page)
				throw new Phpr_ApplicationException('The maintenance mode is not configured');
				
			$obj->enabled = $enabled ? 1 : 0;
			$obj->save();
		}
		
		public function is_configured()
		{
			return $this->maintenance_page ? true : false;
		}
		
		public static function handle_theme_activation($theme)
		{
			$obj = self::create();
			if ($obj->maintenance_page)
			{
				$original_page_url = Db_DbHelper::scalar('select url from pages where id=:id', array('id'=>$obj->maintenance_page));

				if ($original_page_url)
				{
					$new_page_id = Db_DbHelper::scalar('select id from pages where url=:url and theme_id=:theme_id', array('url'=>$original_page_url, 'theme_id'=>$theme->id));
					if ($new_page_id)
					{
						$obj->maintenance_page = $new_page_id;
						$obj->save();
					}
				}
			}
		}
		
		public function get_maintenance_page()
		{
			$page = $this->maintenance_page;
			$page_info = Cms_PageReference::get_page_info($this, 'maintenance_page', $page);
			if (is_object($page_info))
				$page = $page_info->page_id;

			if (!$page)
				return null;

			return Cms_Page::create()->find($page);
		}
	}

?>