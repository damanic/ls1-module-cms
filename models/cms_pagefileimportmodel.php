<?

	class Cms_PageFileImportModel extends Db_ActiveRecord
	{
		public $table_name = 'pages';

		public $custom_columns = array('page_parameters'=>db_text);
		
		public $page_parameters = array();
		
		public function define_columns($context = null)
		{
			$this->define_column('page_parameters', 'Page parameters')->invisible()->validation()->required();
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('page_parameters')->renderAs(frm_grid)->gridColumns(array(
				'directory'=>array('title'=>'Directory', 'align'=>'left', 'read_only'=>true, 'autowidth'=>true), 
				'title'=>array('title'=>'Page Title', 'align'=>'left', 'width'=>'200'),
				'url'=>array('title'=>'Page URL', 'align'=>'left', 'width'=>'200')))->noLabel()->gridSettings(array(
				'no_toolbar'=>true, 'allow_adding_rows'=>false, 'allow_deleting_rows'=>false, 'no_sorting'=>true, 'data_index_is_key'=>true));
		}

		public static function init()
		{
			$obj = new self();

			$obj->page_parameters = array();
			$directories = Cms_Page::list_orphan_directories();
			
			$new_separator = Phpr::$config->get('URL_SEPARATOR', '_');
			$old_separator = $new_separator == '-' ? '_' : '-';

			foreach ($directories as $directory)
			{
				$obj->page_parameters[$directory] = array(
					'directory'=>$directory,
					'title'=>Phpr_Inflector::humanize($directory, 'all'),
					'url'=>'/'.str_replace($old_separator, $new_separator, $directory)
				);
			}
			
			$obj->define_form_fields();
			
			return $obj;
		}
		
		public static function import($data)
		{
			$obj = new self();
			$obj->define_form_fields();
			return $obj->import_pages($data['page_parameters']);
		}
		
		public function import_pages($page_parameters)
		{
			$processed_data = array();

			/*
			 * Validate
			 */
			
			$current_theme_id = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$current_theme_id = $theme->id;

			if($current_theme_id)
				$existing_urls = Db_DbHelper::scalarArray('select url from pages where theme_id=:id', array('id'=>$current_theme_id));
			else
				$existing_urls = Db_DbHelper::scalarArray('select url from pages');

			foreach ($page_parameters as $directory=>$row)
			{
				$title = trim($row['title']);
				$url = mb_strtolower(trim($row['url']));
				
				if (!strlen($title) && !strlen($url))
					continue;
					
				if (!strlen($title))
					$this->set_error('Please specify page title for the '.$directory.' directory', $directory, 'title');

				if (!strlen($url))
					$this->set_error('Please specify the page URL for the '.$directory.' directory', $directory, 'url');

				if (!preg_match(',^[/a-z0-9_-]*$,i', $url))
					$this->set_error('Page url can contain only latin characters, numbers and signs _, -, /', $directory, 'url');

				if (!preg_match(',^/,i', $url))
					$this->set_error('The first character in the url must be the forward slash (/)', $directory, 'url');
				
				if (preg_match(',//,i', $url))
					$this->set_error('Double slashes in page URLs are not allowed', $directory, 'url');

				if ($url != '/' && substr($url, -1) == '/')
					$url = substr($url, 0, -1);

				if (in_array($url, $existing_urls))
					$this->set_error('URL '.$url.' is already in use. Please specify another page URL', $directory, 'url');
					
				$processed_data[$directory] = array($url, $title);
			}
			
			/*
			 * Create pages
			 */
			
			if (!$processed_data)
				throw new Phpr_ApplicationException('Please specify page parameters, or click Cancel if you do not want to import any pages.');
	
			$counter = 0;

			foreach ($processed_data as $directory=>$info)
			{
				$page = Cms_Page::create();
				$page->url = $info[0];
				$page->title = $info[1];
				$page->directory_name = $directory;
				$page->no_file_copy = true;
				$page->theme_id = $current_theme_id;
				$page->action_reference = Cms_Page::action_custom;
				$page->load_directory_content();

				Backend::$events->fireEvent('cms:onCmsPageImport', $page);

				$page->save();
				
				$counter++;
			}
			
			return $counter;
		}
		
		protected function set_error($message, $grid_row, $grid_column)
		{
			if ($grid_row != null)
			{
				$rule = $this->validation->getRule('page_parameters');
				if ($rule)
					$rule->focusId('page_parameters_'.$grid_row.'_'.$grid_column);
			}
			
			$this->validation->setError($message, 'page_parameters', true);
		}
	}

?>