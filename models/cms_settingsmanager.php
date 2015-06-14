<?php

	class Cms_SettingsManager extends Backend_SettingsRecord
	{
		public $table_name = 'cms_settings';
		public static $obj = null;
		
		public $enable_filebased_templates = false;

		public static function get($className = null, $init_columns = true)
		{
			if (self::$obj !== null)
				return self::$obj;
			
			return self::$obj = parent::get('Cms_SettingsManager');
		}

		public function define_columns($context = null)
		{
			$this->validation->setFormId('settings_form');
			
			if (!Cms_Theme::is_theming_enabled())
				$this->define_column('default_templating_engine', 'Templating engine')->validation()->method('validate_default_templating_engine');
			
			$this->define_column('enable_filebased_templates', 'Enable file-based templates');
			$this->define_column('templates_dir_path', 'Path to the templates directory')->validation()->fn('trim')->method('validate_templates_path');
			$this->define_column('content_file_extension', 'Content file extension')->validation()->fn('trim');
			$this->define_column('resources_dir_path', 'Path to the website resources directory')->validation()->fn('trim')->required('Please specify the resources directory path')->method('validate_resources_directory');
		}
		
		public function define_form_fields($context = null)
		{
			if (!Cms_Theme::is_theming_enabled())
				$this->add_form_field('default_templating_engine')->tab('Template Engine')->renderAs(frm_dropdown);

			$extraFieldClass = $this->enable_filebased_templates ? 'separatedField' : null;
			$this->add_form_field('enable_filebased_templates')->renderAs(frm_onoffswitcher)->comment('Enable file-based templates if you want LemonStand to store pages, partials and templates in files instead of the database.', 'above')->cssClassName($extraFieldClass)->tab('Template Engine');
			
			$extraFieldClass = 'filebased_field';
			if (!$this->enable_filebased_templates)
				$extraFieldClass .= ' hidden';
				
			$field = $this->add_form_field('templates_dir_path')->cssClassName($extraFieldClass)->tab('Template Engine')->comment('Please specify an absolute path to the directory on the server, where you want to store LemonStand templates. The directory should exist and be writable for PHP.', 'above', true);
			
			$field->titlePartial('path_hint');
			
			$this->add_form_field('content_file_extension')->cssClassName($extraFieldClass)->tab('Template Engine')->comment('Please choose a file extension to use for partial, template and page content files. PHP-specific page files (pre- and post- action code, AJAX declarations, etc.) always have .php extension.', 'above')->renderAs(frm_dropdown);
			
			$field = $this->add_form_field('resources_dir_path')->tab('Resources directory')->comment('Please specify path to the website resources directory relative to your LemonStand installation root. The directory should exist and be writable for PHP.', 'above', true);
		}
		
		public function get_default_templating_engine_options($key_value = -1)
		{
			$engines = array(
				'php'=>'PHP',
				'twig'=>'Twig'
			);
			
			if (!Cms_Controller::is_php_allowed())
				$engines['php'] .= ' (not allowed)';
				
			return $engines;
		}
		
		public function validate_default_templating_engine($name, $value)
		{
			if ($value == 'php' && !Cms_Controller::is_php_allowed())
				$this->validation->setError('The application configuration doesn\'t allow PHP in CMS templates.', $name, true);
			
			return true;
		}
		
		public function get_templates_dir_path($theme) 
		{
			$file_path = Phpr::$config->get('TEMPLATE_PATH', '');

			if($file_path)
				$result = $this->normalize_templates_path($file_path);
			else
				$result = $this->normalize_templates_path($this->templates_dir_path);

			if (Cms_Theme::is_theming_enabled() && $theme)
				$result .= '/'.$theme->code;

			return $result;
		}
		
		public function get_content_file_extension_options($key_value=-1)
		{
			return array(
				'htm'=>".htm",
				'php'=>".php"
			);
		}
		
		public function validate_templates_path($name, $value)
		{
			if ($this->enable_filebased_templates)
				$this->validate_templates_directory($value);
			
			return true;
		}
		
		public function validate_resources_directory($name, $value)
		{
			if (substr($value, 0, 1) == '/')
				$value = substr($value, 1);
				
			if (!strlen($value))
				$this->validation->setError('Please specify a valid resources directory name.', 'resources_dir_path', true);
				
			$path = PATH_APP.'/'.$value;
			
			if (!file_exists($value))
				$this->validation->setError('Directory not found: '.$value, 'resources_dir_path', true);
				
			if (!is_writable($value))
				$this->validation->setError('Directory is not writable: '.$value, 'resources_dir_path', true);
				
			return $value;
		}
		
		protected function validate_templates_directory($value)
		{
			if (!strlen($value))
				$this->validation->setError('Please specify path to the templates directory.', 'templates_dir_path', true);
			
			$value = $this->normalize_templates_path($value);
			
			if (!file_exists($value))
				$this->validation->setError('Directory not found: '.$value, 'templates_dir_path', true);
				
			if (!is_writable($value))
				$this->validation->setError('Directory is not writable: '.$value, 'templates_dir_path', true);
		}
		
		protected function normalize_templates_path($path)
		{
			return str_replace('{APP}', PATH_APP, $path);
		}
		
		public function before_save($deferred_session_key = null) 
		{
			$prev_filebased_status = isset($this->fetched['enable_filebased_templates']) && $this->fetched['enable_filebased_templates'];
			$prev_extension = isset($this->fetched['content_file_extension']) ? $this->fetched['content_file_extension'] : null;
			
			if ($prev_filebased_status != $this->enable_filebased_templates)
			{
				if (!$prev_filebased_status && $this->enable_filebased_templates)
					$this->copy_templates_to_files();
				else
				{
					if ($prev_extension)
						$this->content_file_extension = $prev_extension;

					$this->copy_templates_to_db();
				}
			}

			if ($this->enable_filebased_templates)
			{
				if ($prev_extension && $prev_extension != $this->content_file_extension)
				{
					$themes = Cms_Theme::list_themes();
					foreach ($themes as $theme)
					{
						Cms_Partial::update_content_file_extension($this->get_templates_dir_path($theme), $prev_extension, $this->content_file_extension);
						Cms_Template::update_content_file_extension($this->get_templates_dir_path($theme), $prev_extension, $this->content_file_extension);
						Cms_Page::update_content_file_extension($this->get_templates_dir_path($theme), $prev_extension, $this->content_file_extension);
					}
					
					Cms_Partial::update_content_file_extension($this->get_templates_dir_path(null), $prev_extension, $this->content_file_extension);
					Cms_Template::update_content_file_extension($this->get_templates_dir_path(null), $prev_extension, $this->content_file_extension);
					Cms_Page::update_content_file_extension($this->get_templates_dir_path(null), $prev_extension, $this->content_file_extension);
				}
			}
			
			Phpr::$session->set('cms_cur_resource_folder', null);
		}
		
		/**
		 * Creates templates directory and copies CMS objects into it
		 */
		public function copy_templates_to_files()
		{
			$templates_directory = $this->get_templates_dir_path(null);
			if (substr($templates_directory, -1) == '/')
				$templates_directory = substr($templates_directory, 0, -1);

			$this->validate_templates_directory($templates_directory);
			
			/*
			 * Load default file and folder permissions
			 */
			
			$file_permissions = Phpr_Files::getFilePermissions();

			/*
			 * Create theme directory
			 */
			
			if (Cms_Theme::is_theming_enabled())
			{
				$themes = Cms_Theme::list_themes();
				foreach ($themes as $theme)
				{
					$this->create_templates_directory($templates_directory, $theme->code, 'The existing theme templates directory is not writable', 'Error creating the theme templates directory', null, false);
					$this->create_templates_directory($templates_directory, $theme->code.'/pages', 'The existing pages directory is not writable', 'Error creating the pages directory');
					$this->create_templates_directory($templates_directory, $theme->code.'/layouts', 'The existing templates directory is not writable', 'Error creating the templates directory', $theme->code.'/templates');
					$this->create_templates_directory($templates_directory, $theme->code.'/partials', 'The existing partials directory is not writable', 'Error creating the partials directory');
				}
			}
			
			/*
			 * Create the templates directory internal directories
			 */

			if (!Cms_Theme::is_theming_enabled())
			{
				$this->create_templates_directory($templates_directory, 'pages', 'The existing pages directory is not writable', 'Error creating the pages directory');
				$this->create_templates_directory($templates_directory, 'layouts', 'The existing templates directory is not writable', 'Error creating the templates directory', 'templates');
				$this->create_templates_directory($templates_directory, 'partials', 'The existing partials directory is not writable', 'Error creating the partials directory');
			}
			
			/*
			 * Transfer partials
			 */
			
			$partials = Cms_Partial::create()->find_all();
			foreach ($partials as $partial)
				$partial->copy_to_file($templates_directory);

			/*
			 * Transfer templates
			 */
			
			$templates = Cms_Template::create()->find_all();
			foreach ($templates as $template)
				$template->copy_to_file($templates_directory);

			/*
			 * Transfer pages
			 */
			
			$pages = Cms_Page::create()->find_all();
			foreach ($pages as $page)
				$page->copy_to_file($templates_directory);
		}
		
		public function create_templates_directory($path, $directory_name, $non_writable_message, $mkdir_message, $alias=null, $protect=true)
		{
			$folder_permissions = Phpr_Files::getFolderPermissions();

			if ($alias)
			{
				$dest_path = $path.'/'.$alias;

				if (file_exists($dest_path))
				{
					if (!is_writable($dest_path))
						throw new Phpr_ApplicationException($non_writable_message.': '.$dest_path);
						
					return;
				}
			}

			$dest_path = $path.'/'.$directory_name;
			if (file_exists($dest_path))
			{
				if (!is_writable($dest_path))
					throw new Phpr_ApplicationException($non_writable_message.': '.$dest_path);
			} else
			{
				if (!@mkdir($dest_path))
					throw new Phpr_ApplicationException($mkdir_message.': '.$dest_path);

				@chmod($dest_path, $folder_permissions);
				
				if ($protect)
				{
					$file_permissions = Phpr_Files::getFilePermissions();
					$htacces_path = $dest_path.'/.htaccess';
					$data = "order deny,allow\ndeny from all";
					if (!@file_put_contents($htacces_path, $data))
						throw new Phpr_ApplicationException('Error creating file: '.$htacces_path);

					@chmod($htacces_path, $file_permissions);
				}
			}
		}
		
		/**
		 * Returns TRUE if the templates directory is writable for PHP
		 */
		public function templates_directory_is_writable()
		{
			return is_writable($this->get_templates_dir_path(null));
		}
		
		/**
		 * Copies all templates from files to the database
		 */
		public function copy_templates_to_db()
		{
			/*
			 * Transfer partials
			 */
			
			$partials = Cms_Partial::create()->find_all();
			foreach ($partials as $partial)
				$partial->copy_from_file();
				
			/*
			 * Transfer templates
			 */
			
			$templates = Cms_Template::create()->find_all();
			foreach ($templates as $template)
				$template->copy_from_file();

			/*
			 * Transfer pages
			 */
			
			$pages = Cms_Page::create()->find_all();
			foreach ($pages as $page)
				$page->set_from_directory();
		}
	}

?>