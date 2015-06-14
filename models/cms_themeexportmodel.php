<?

	class Cms_ThemeExportModel extends Db_ActiveRecord
	{
		public $table_name = 'core_configuration_records';

		public $custom_columns = array(
			'theme_id'=>db_number,
			'objects'=>db_text,
			'ignore_payment_partials'=>db_number,
			'ignore_theme_description'=>db_number
		);

		public function define_columns($context = null)
		{
			$this->define_column('theme_id', 'Theme')->validation()->fn('trim')->required('Please select theme to export.');
			$this->define_column('objects', 'Theme objects')->validation()->required('Please select at least one theme object type to export.');
			$this->define_column('ignore_payment_partials', 'Ignore payment and payment profile partials');
			$this->define_column('ignore_theme_description', 'Ignore the theme description text');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('theme_id')->renderAs(frm_dropdown)->tab('Theme objects');
			$this->add_form_field('objects')->renderAs(frm_checkboxlist)->comment('Please select types of theme objects you would like to export', 'above')->tab('Theme objects');
			$this->add_form_field('ignore_payment_partials')->renderAs(frm_checkbox)->comment('Use this option if you want to exclude payment and payment profile partials from the exported file.', 'below')->tab('Settings');
			$this->add_form_field('ignore_theme_description')->renderAs(frm_checkbox)->comment('Use this option to clear the theme description in the exported file.', 'below')->tab('Settings');
		}

		public function get_theme_id_options($key_value=-1)
		{
			$result = array();
			
			$themes = Cms_Theme::create()->order('name')->find_all();
			foreach ($themes as $theme)
				$result[$theme->id] = $theme->name.' ('.$theme->code.')';
				
			return $result;
		}
		
		public function get_objects_options($key_value=-1)
		{
			return array(
				'pages'=>'Pages',
				'templates'=>'Layouts',
				'partials'=>'Partials',
				'resources'=>'Resources',
				'global_content_blocks'=>'Global content blocks',
			);
		}
		
		public function get_objects_optionState($value)
		{
			return true;
		}
		
		public function export($data)
		{
			$this->define_form_fields();
			$this->validate_data($data);
			$this->set_data($data);
			
			$objects = array();
			foreach ($this->objects as $object)
				$objects[$object] = 1;

			return Cms_ExportManager::create()->export($objects, $this->theme_id, $this->ignore_payment_partials, $this->ignore_theme_description);
		}
	}
	
?>
