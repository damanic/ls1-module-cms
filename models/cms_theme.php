<?php

	/**
	 * Represents a CMS theme.
	 * Cms_Page class represents a front-end website theme.
	 * @documentable
	 * @property string $name Specifies the theme name.
	 * @property string $code Specifies the theme code.
	 * @property string $description Specifies the theme description.
	 * @property string $author_name Specifies the theme author name.
	 * @property string $author_website Specifies an URL of the theme author's website.
	 * @property boolean $is_default Determines whether the theme is default.
	 * @property boolean $is_enabled Determines whether the theme is enabled.
	 * @property string $templating_engine Specifies a templating engine the theme uses.
	 * Supported options are <em>php</em>, <em>twig</em>.
	 * @property string $agent_detection_mode Specifies the user agent detection mode.
	 * Supported options are <em>disabled</em>, <em>built-in</em>, <em>custom</em>.
	 * @property string $agent_list Specifies a list of user agent for the <em>built-in</em> agent detection.
	 * @property string $agent_detection_code Specifies the user agent detection PHP code for the <em>custom</em> agent detection mode.
	 * @property integer $id Specifies the theme identifier in the database.
	 * @author LemonStand eCommerce Inc.
	 * @package cms.models
	 */
	class Cms_Theme extends Cms_Object
	{
		public $table_name = 'cms_themes';
		public $is_enabled = 1;
		public $agent_detection_mode = 'disabled';
		public $agent_detection_code = 'return false;';
		
		private static $_active_theme = false;
		private static $_default_theme = false;
		private static $_edit_theme = false;
		private static $_themes = array();
		
		const agent_detection_disabled = 'disabled';
		const agent_detection_built_in = 'built-in';
		const agent_detection_custom = 'custom';
		
		public static function create()
		{
			return new self();
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the theme name.");
			$this->define_column('code', 'Code')->validation()->fn('trim')->fn('strtolower')->required("Please specify the theme code.")->regexp(',^[a-z0-9_\.-]*$,i', "Theme code can contain only latin characters, numbers and signs _, -, /, and .")->unique('Theme with code %s already exists.')->method('validate_code');
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('author_name', 'Author')->validation()->fn('trim');
			$this->define_column('author_website', 'Author website')->validation()->fn('trim');
			$this->define_column('is_default', 'Default');
			$this->define_column('is_enabled', 'Enabled')->validation()->method('validate_enabled');
			$this->define_column('templating_engine', 'Templating engine')->validation()->method('validate_templating_engine');

			$this->define_column('agent_detection_mode', 'User agent detection mode')->defaultInvisible()->validation()->method('validate_agent_detection_mode');
			$this->define_column('agent_list', 'User agents')->invisible();
			$this->define_column('agent_detection_code', 'User agent detection code')->invisible()->validation()->fn('trim');
		}
		
		public function define_form_fields($context = null)
		{
			if ($context == 'enable')
				$this->add_form_partial('enable_theming_header');
			
			$field = $this->add_form_field('name');
			if ($context != 'enable')
				$field->tab('Theme');
			
			$field = $this->add_form_field('code')->comment('Theme code defines the theme directory name', 'above');
			if ($context != 'enable')
				$field->tab('Theme');

			if ($context != 'enable')
			{
				$this->add_form_field('is_enabled')->tab('Theme');
				$this->add_form_field('description')->size('small')->tab('Theme');
				$this->add_form_field('author_name', 'left')->tab('Theme');
				$this->add_form_field('author_website', 'right')->tab('Theme');
				$this->add_form_field('templating_engine')->tab('Theme')->renderAs(frm_dropdown);
				
				$this->add_form_field('agent_detection_mode')->renderAs(frm_dropdown)->tab('User agent/device detection')->comment('The theme can be automatically activated based on the user agent/device. Please select the detection mode if you want to use this option.', 'above');
				$this->add_form_field('agent_list')->renderAs(frm_checkboxlist)->tab('User agent/device detection')->comment('Select user agents the theme should be active for.', 'above');
				$this->add_form_field('agent_detection_code')->renderAs(frm_code_editor)->size('large')->language('php')->comment('Please specify PHP expression for detecting the user agent. The code should return Boolean value.', 'above')->tab('User agent/device detection');
			}
		}
		
		public function validate_code($name, $value)
		{
			if (in_array($value, array('pages', 'partials', 'templates', 'layouts')))
				$this->validation->setError('Theme code cannot be "pages", "partials", "templates" and "layouts".', $name, true);
				
			return true;
		}
		
		public function validate_enabled($name, $value)
		{
			if (!$value && $this->is_default)
				$this->validation->setError('This theme is default and cannot be disabled.', $name, true);
				
			return $value;
		}
		
		public function validate_agent_detection_mode($name, $value)
		{
			if (strlen($value) && $value != self::agent_detection_disabled && $this->is_default)
				$this->validation->setError('This theme is default. User agent detection is not applicable to a default theme.', $name, true);

			return $value;
		}
		
		public function get_agent_detection_mode_options($key_value = -1)
		{
			return array(
				self::agent_detection_disabled=>'Disabled',
				self::agent_detection_built_in=>'Built-in',
				self::agent_detection_custom=>'Custom'
			);
		}

		public function get_agent_list_options($key_value = -1)
		{
			$agents = self::get_agent_list();
			
			$result = array();
			foreach ($agents as $agent_id=>$agent_info)
				$result[$agent_id] = $agent_info['name'];
				
			return $result;
		}
		
		public function get_templating_engine_options($key_value = -1)
		{
			$engines = array(
				'php'=>'PHP',
				'twig'=>'Twig'
			);
			
			if (!Cms_Controller::is_php_allowed())
				$engines['php'] .= ' (not allowed)';
				
			return $engines;
		}
		
		public function validate_templating_engine($name, $value)
		{
			if ($value == 'php' && !Cms_Controller::is_php_allowed())
				$this->validation->setError('The application configuration doesn\'t allow PHP in CMS templates.', $name, true);
			
			return true;
		}
		
		public function get_agent_list_option_state($value)
		{
			return is_array($this->agent_list) && in_array($value, $this->agent_list);
		}
		
		public static function get_agent_list()
		{
			return array(
				'blackberry'=>array('name'=>'BlackBerry', 'signature'=>'BlackBerry'),
				'android'=>array('name'=>'Android', 'signature'=>'Android'),
				'ipad'=>array('name'=>'Apple iPad', 'signature'=>'iPad'),
				'iphone'=>array('name'=>'Apple iPhone', 'signature'=>'iPhone'),
				'ipod'=>array('name'=>'Apple iPod Touch', 'signature'=>'iPod'),
				'google'=>array('name'=>'Googlebot', 'signature'=>'Googlebot'),
				'msnbot'=>array('name'=>'Msnbot', 'signature'=>'msnbot'),
				'yahoo'=>array('name'=>'Yahoo! Slurp', 'signature'=>'Yahoo! Slurp'),
			);
		}

		public function before_save($deferred_session_key = null) 
		{
			if (!is_array($this->agent_list))
				$this->agent_list = array();
			
			$this->agent_list = serialize($this->agent_list);

			$themes_path = PATH_APP.'/themes';
			$theme_path = $themes_path.'/'.$this->code;
			
			$old_code = isset($this->fetched['code']) ? $this->fetched['code'] : null;

			$directory_exists = file_exists($theme_path) && is_dir($theme_path);

			if (($this->is_new_record() || $old_code != $this->code) && $directory_exists)
				throw new Phpr_ApplicationException('Theme directory already exists: '.$theme_path);
				
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
			{
				$theme_templates_path = $settings_manager->get_templates_dir_path(null).'/'.$this->code;
				$directory_exists = file_exists($theme_templates_path) && is_dir($theme_templates_path);
				if (($this->is_new_record() || $old_code != $this->code) && $directory_exists)
					throw new Phpr_ApplicationException('Theme templates directory already exists: '.$theme_templates_path);
			}
				
			if ($this->is_new_record())
				self::create_theme_directory($this->code);
			else if (strlen($old_code) && $old_code != $this->code)
			{
				if (!@rename($themes_path.'/'.$old_code, $themes_path.'/'.$this->code))
					throw new Phpr_ApplicationException('Error renaming the theme directory: '.$themes_path.'/'.$old_code);
					
				$settings_manager = Cms_SettingsManager::get();
				if ($settings_manager->enable_filebased_templates)
				{
					$theme_templates_path = $settings_manager->get_templates_dir_path(null);
					$old_path = $theme_templates_path.'/'.$old_code;
					$new_path = $theme_templates_path.'/'.$this->code;
					
					if ($old_path != $themes_path.'/'.$old_code)
					{
						if (!@rename($old_path, $new_path))
							throw new Phpr_ApplicationException('Error renaming the theme directory: '.$old_path);
					}
				}
			}
		}
		
		public function after_delete()
		{
			if (strlen($this->code))
			{
				/*
				 * Delete resources
				 */
				$theme_path = self::get_themes_path().$this->code;
				if (file_exists($theme_path))
					Phpr_Files::removeDirRecursive($theme_path);
					
				/*
				 * Delete templates
				 */
				$settings_manager = Cms_SettingsManager::get();
				if ($settings_manager->enable_filebased_templates && $this->code)
				{
					$theme_templates_path = $settings_manager->get_templates_dir_path(null).'/'.$this->code;
					if (file_exists($theme_templates_path))
						Phpr_Files::removeDirRecursive($theme_templates_path);
				}

				/*
				 * Delete pages, partials and layouts
				 */
				$bind = array('id'=>$this->id);
				Db_DbHelper::query('delete content_blocks from content_blocks, pages where content_blocks.page_id=pages.id and pages.theme_id=:id', $bind);
				Db_DbHelper::query('delete from pages where theme_id=:id', $bind);
				Db_DbHelper::query('delete from partials where theme_id=:id', $bind);
				Db_DbHelper::query('delete from templates where theme_id=:id', $bind);
			}
		}
		
		public function before_delete($id=null) 
		{
			if ($this->is_default)
				throw new Phpr_ApplicationException(sprintf('Theme "%s" is default. Please select another default theme before deleting this theme.', $this->name));
		}
		
		protected function after_fetch()
		{
			$this->agent_list = self::decode_agent_list($this->agent_list);
		}
		
		public static function decode_agent_list($agent_list)
		{
			if (strlen($agent_list))
			{
				try
				{
					return @unserialize($agent_list);
				}
				catch (exception $ex) {}
			}
			
			return array();
		}

		public static function get_themes_path($absolute = true)
		{
			$result = "/themes/";
			if (!$absolute)
				return $result;
				
			return PATH_APP.$result;
		}
		
		public static function create_theme_directory($theme_code = null)
		{
			$themes_path = self::get_themes_path();

			if (!file_exists($themes_path) || !is_dir($themes_path))
			{
				if (!@mkdir($themes_path, Phpr_Files::getFolderPermissions()))
					throw new Phpr_ApplicationException('Error creating directory: '.$themes_path);
			}
			
			if ($theme_code)
			{
				if (!is_writable($themes_path))
					throw new Phpr_ApplicationException('Themes directory is not writable: '.$themes_path);
				
				$theme_path = $themes_path.'/'.$theme_code;
				if (!file_exists($theme_path) || !is_dir($theme_path)) {
					if (!@mkdir($theme_path, Phpr_Files::getFolderPermissions()))
						throw new Phpr_ApplicationException('Error creating directory: '.$theme_path);
				
					$resources_path = $theme_path.'/resources';
					if (!@mkdir($resources_path, Phpr_Files::getFolderPermissions()))
						throw new Phpr_ApplicationException('Error creating directory: '.$resources_path);
				}
				
				$settings_manager = Cms_SettingsManager::get();
				if ($settings_manager->enable_filebased_templates)
				{
					$theme_templates_path = $settings_manager->get_templates_dir_path(null).'/'.$theme_code;
					if (!file_exists($theme_templates_path) || !is_dir($theme_templates_path)) {
						if (!@mkdir($theme_templates_path, Phpr_Files::getFolderPermissions()))
							throw new Phpr_ApplicationException('Error creating directory: '.$theme_templates_path);
						else {
							if (!is_writable($theme_templates_path))
								throw new Phpr_ApplicationException('Themes directory is not writable: '.$theme_templates_path);
						}
					}
					
					$settings_manager->create_templates_directory($theme_templates_path, 'pages', 'The existing pages directory is not writable', 'Error creating the pages directory');
					$settings_manager->create_templates_directory($theme_templates_path, 'layouts', 'The existing templates directory is not writable', 'Error creating the templates directory', 'templates');
					$settings_manager->create_templates_directory($theme_templates_path, 'partials', 'The existing partials directory is not writable', 'Error creating the partials directory');
				}

			}
		}
		
		public static function is_theming_enabled()
		{
			return Db_ModuleParameters::get('cms', 'enable_theming');
		}
		
		/**
		 * Returns the active theme based on the user agent and default theme.
		 */
		public static function get_active_theme()
		{
			if (self::$_active_theme !== false)
				return self::$_active_theme;
				
			$active_theme = Backend::$events->fire_event('cms:onGetActiveTheme');
			
			if(count($active_theme) > 0) 
			{
				foreach ($active_theme as $theme)
				{
					if (is_object($theme) && $theme instanceof Cms_Theme)
						return self::$_active_theme = $active_theme[0];
				}
				
			}
			
			$themes = Db_DbHelper::objectArray('select id, agent_detection_mode, agent_list, agent_detection_code, name, code from cms_themes where is_enabled is not null and is_enabled=1 order by name');
			
			/*
			 * Try to select a theme based on the user agent
			 */
			
			$agent = Phpr::$request->getUserAgent();
			$known_agents = self::get_agent_list();
			
			foreach ($themes as $theme)
			{
				if (!$theme->agent_detection_mode || $theme->agent_detection_mode == self::agent_detection_disabled)
					continue;
					
				if ($theme->agent_detection_mode == self::agent_detection_built_in)
				{
					$theme_agents = self::decode_agent_list($theme->agent_list);
					foreach ($theme_agents as $theme_agent_id)
					{
						foreach ($known_agents as $agent_id=>$agent_info)
						{
							if ($agent_id == $theme_agent_id && strpos($agent, $agent_info['signature']) !== false)
								return self::$_active_theme = self::create()->where('id=?', $theme->id)->find();
						}
					}
				}
				
				if (strlen($theme->agent_detection_code))
				{
					try
					{
						if (@eval($theme->agent_detection_code))
							return self::$_active_theme = self::create()->where('id=?', $theme->id)->find();
					} catch (exception $ex)
					{
						throw new Phpr_SystemException(
							sprintf('Error evaluating the user agent detection code for theme "%s (%s)". %s', 
								$theme->name,
								$theme->code, 
								Core_String::finalize($ex->getMessage())
							)
						);
					}
				}
			}
			
			/*
			 * Try to return a default theme
			 */
			
			$theme = self::get_default_theme();
			if ($theme)
				return self::$_active_theme = $theme;
				
			/*
			 * Return the first theme in the list
			 */
			
			if (count($themes))
				return self::$_active_theme = self::create()->where('id=?', $themes[0]->id)->find();
				
			return null;
		}
		
		/**
		 * Returns the default theme.
		 */
		public static function get_default_theme()
		{
			if (self::$_default_theme === false)
				self::$_default_theme = self::create()->where('is_default=1')->find();
				
			return self::$_default_theme;
		}
		
		/**
		 * Returns the theme which is being edited in CMS UI. 
		 * The result depends on the user currently logged into the Administration Area.
		 */
		public static function get_edit_theme()
		{
			if (self::$_edit_theme !== false)
				return self::$_edit_theme;
			
			if ($theme_id = Db_UserParameters::get('cms-edit-theme'))
			{
				$theme = self::get_theme_by_id($theme_id);
				if ($theme)
					return self::$_edit_theme = $theme;
			}
			
			$default_theme = self::get_default_theme();
			if ($default_theme)
				return $default_theme;
			
			return self::$_edit_theme = Cms_Theme::create()->order('name')->find();
		}
		
		public static function set_edit_theme($id)
		{
			if (!strlen($id))
				throw new Phpr_ApplicationException('Please select theme.');
				
			$theme = self::get_theme_by_id($id);
			if (!$theme)
				throw new Phpr_ApplicationException('Theme not found.');
				
			self::$_edit_theme = $theme;
			Db_UserParameters::set('cms-edit-theme', $id);
		}
		
		public static function get_theme_by_id($id)
		{
			if (!strlen($id))
				return null;
			
			if (array_key_exists($id, self::$_themes))
				return self::$_themes[$id];
			
			return self::$_themes[$id] = self::create()->find($id);
		}
		
		public static function list_themes()
		{
			return Cms_Theme::create()->order('name')->find_all();
		}
		
		/**
		 * Returns path to the theme resources directory 
		 * relative to the application root directory.
		 * @return string
		 */
		public function get_resources_path()
		{
			return self::get_themes_path(false).$this->code.'/resources';
		}
		
		/**
		 * Makes the theme default.
		 */
		public function make_default()
		{
			if (!$this->is_enabled)
				throw new Phpr_ApplicationException(sprintf('Theme "%s" is disabled and cannot be default.', $this->name));

			if (strlen($this->agent_detection_mode) && $this->agent_detection_mode != self::agent_detection_disabled)
				throw new Phpr_ApplicationException(sprintf('Theme "%s" depends on the user agent and cannot be default.', $this->name));

			if (!$this->is_default)
				Backend::$events->fire_event('cms:onSetDefaultTheme', self::get_default_theme(), $this);

			$bind = array('id'=>$this->id);
			Db_DbHelper::query('update cms_themes set is_default=1 where id=:id', $bind);
			Db_DbHelper::query('update cms_themes set is_default=0 where id<>:id', $bind);
		}
		
		public function enable_theme()
		{
			$this->is_enabled = true;
			Db_DbHelper::query('update cms_themes set is_enabled=1 where id=:id', array('id'=>$this->id));
		}
		
		public function disable_theme()
		{
			if ($this->is_default)
				throw new Phpr_ApplicationException(sprintf('Theme "%s" is default and cannot be disabled.', $this->name));
			
			$this->is_enabled = false;
			Db_DbHelper::query('update cms_themes set is_enabled=0 where id=:id', array('id'=>$this->id));
		}
		
		public function init_copy($obj)
		{
			$obj->name = $this->name;
			$obj->code = $this->code;
			$obj->description = $this->description;
			$obj->author_name = $this->author_name;
			$obj->author_website = $this->author_website;
			$obj->agent_detection_mode = $this->agent_detection_mode;
			$obj->agent_list = $this->agent_list;
			$obj->agent_detection_code = $this->agent_detection_code;
			$obj->templating_engine = $this->templating_engine;
		}
		
		/**
		 * Duplicates the theme.
		 * @param array $data Field values for the new theme.
		 * @return Cms_Theme Returns new theme
		 */
		public function duplicate_theme($data)
		{
			/*
			 * Copy templates to DB
			 */
			
			$sm = Cms_SettingsManager::get();
			if ($sm->enable_filebased_templates)
				$sm->copy_templates_to_db();
			
			/*
			 * Create and theme the theme record
			 */
			
			$new_theme = self::create();
			$new_theme->init_columns_info();
			$new_theme->define_form_fields();
			$new_theme->save($data);
			
			/*
			 * Copy CMS objects - pages, layouts and partials
			 */
			
			$pages = Cms_Page::create()->where('theme_id=?', $this->id)->find_all();
			$page_map = array();
			foreach ($pages as $page)
			{
				$new_page = $page->duplicate();
				$new_page->theme_id = $new_theme->id;
				$new_page->save_duplicated($page);
				$page_map[$page->id] = $new_page;
			}
			
			$templates = Cms_Template::create()->where('theme_id=?', $this->id)->find_all();
			$template_map = array();
			foreach ($templates as $template)
			{
				$new_template = $template->duplicate();
				$new_template->theme_id = $new_theme->id;
				$new_template->save();
				$template_map[$template->id] = $new_template;
			}

			$partials = Cms_Partial::create()->where('theme_id=?', $this->id)->find_all();
			foreach ($partials as $partial)
			{
				$new_partial = $partial->duplicate();
				$new_partial->theme_id = $new_theme->id;
				$new_partial->save();
			}
			
			/*
			 * Update page relations
			 */
			
			foreach ($page_map as $old_id=>$page)
			{
				$update_page = false;
				
				if ($page->security_redirect_page_id)
				{
					if (isset($page_map[$page->security_redirect_page_id]))
					{
						$page->security_redirect_page_id = $page_map[$page->security_redirect_page_id]->id;
						$update_page = true;
					}
				}
				
				if ($page->parent_id)
				{
					if (isset($page_map[$page->parent_id]))
					{
						$page->parent_id = $page_map[$page->parent_id]->id;
						$update_page = true;
					}
				}
				
				if ($page->template_id)
				{
					if (isset($template_map[$page->template_id]))
					{
						$page->template_id = $template_map[$page->template_id]->id;
						$update_page = true;
					}
				}
				
				if ($update_page)
					$page->save();
			}
			
			/*
			 * Copy resources
			 */
			
			$new_resources_path = PATH_APP.'/'.$new_theme->get_resources_path();
			$old_resources_path = PATH_APP.'/'.$this->get_resources_path();

			Phpr_Files::copyDir($old_resources_path, $new_resources_path);
			
			return $new_theme;
		}
		
		/**
		 * Creates a new theme basing on the provided data.
		 * If theme with the specified code already exists, 
		 * appends the "-N" suffix to the code value. If the 
		 * code or name is not provided, uses "new_theme" and "New theme" values.
		 * @param string $code Theme code.
		 * @param string $name Theme name.
		 * @param string $description Theme description.
		 * @param string $author_name Theme author name.
		 * @param string $author_website Theme author website.
		 * @param string $agent_detection_mode User agent detection mode.
		 * @param string $agent_list User agent list (for the built-in detection).
		 * @param string $agent_detection_code User agent detection code (for the custom detection).
		 * @param string $templating_engine Templating engine name (php or twig)
		 * @return Cms_Theme Returns the new theme.
		 */
		public static function create_safe($code, $name, $description, $author_name, $author_website, $agent_detection_mode, $agent_list, $agent_detection_code, $templating_engine)
		{
			$code = trim($code);
			$name = trim($name);
			
			if (!strlen($code))
				$code = 'new_theme';

			if (!strlen($name))
				$name = 'New theme';

			$code = mb_strtolower($code);
			$counter = 1;
			$original_code = $code;
			while (Db_DbHelper::scalar('select count(*) from cms_themes where code=:code', array('code'=>$code)))
			{
				$counter++;
				$code = $original_code .= '-'.$counter;
			}
			
			$theme = self::create();
			$theme->name = $name;
			$theme->code = $code;
			$theme->description = $description;
			$theme->author_name = $author_name;
			$theme->author_website = $author_website;
			$theme->agent_detection_mode = $agent_detection_mode;
			$theme->agent_list = self::decode_agent_list($agent_list);
			$theme->agent_detection_code = $agent_detection_code;
			$theme->templating_engine = $templating_engine;
			
			$theme->save();
			
			return $theme;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Cms_Module::update_cms_content_version();
		}
		
		public function enable_theming($data)
		{
			$theme = self::create();
			$theme->init_columns_info();
			$theme->save($data);
			$theme->make_default();
			
			$bind = array('id'=>$theme->id);
			
			$sm = Cms_SettingsManager::get();
			if ($sm->enable_filebased_templates)
				$sm->copy_templates_to_db();
			
			Db_DbHelper::query('update pages set theme_id=:id', $bind);
			Db_DbHelper::query('update partials set theme_id=:id', $bind);
			Db_DbHelper::query('update templates set theme_id=:id', $bind);
			
			Db_ModuleParameters::set('cms', 'enable_theming', true);

			if ($sm->enable_filebased_templates)
				$sm->copy_templates_to_files();

			return $theme;
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Triggered when LemonStand determines an active CMS theme. 
		 * Use this event to force a specific theme to be used, ignoring the currently default CMS theme.
		 * The handler should return a {@link Cms_Theme} object representing a theme which should be used.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onGetActiveTheme', $this, 'get_active_theme');
		 * }
		 * 
		 * public function get_active_theme()
		 * {
		 *   return Cms_Theme::create()->find_by_code('utility');
		 * }
		 * </pre>
		 * @event cms:onGetActiveTheme
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @return Cms_Theme Returns the theme object. Returns NULL if the default theme should be used.
		 */
		private function event_onGetActiveTheme() {}
			
		/**
		 * Triggered when a default theme is changed by a user. 
		 * The handler method should accept two parameters - the old and new default theme objects. You can
		 * throw an exception in the handler to stop the operation.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onSetDefaultTheme', $this, 'set_default_theme');
		 * }
		 * 
		 * public function set_default_theme($old, $new)
		 * {
		 *   // Do something
		 * }
		 * </pre>
		 * @event cms:onSetDefaultTheme
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Cms_Theme $old_theme The old default theme object.
		 * @param Cms_Theme $new_theme The new default theme object.
		 */
		private function event_onSetDefaultTheme($old_theme, $new_theme) {}
	}
	
?>