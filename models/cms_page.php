<?php

	/**
	 * Represents a CMS page.
	 * Cms_Page class represents a front-end website page.
	 * @property integer $id Specifies the page identifier in the database.
	 * @see cms:onBeforeDisplay
	 * @see cms:onAfterDisplay
	 * @documentable
	 * @author LemonStand eCommerce Inc.
	 * @package cms.models
	 */
	class Cms_Page extends Cms_Object
	{
		const action_custom = 'Custom';
		const status_pending = 2;
		const max_block_num = 5;
		const default_static_page_name = 'Static Page';
		const default_static_page_url = '/static_page';
		
		public $table_name = 'pages';
		
		/**
		 * @var string Specifies the page URL relative to the application root.
		 * @documentable
		 */
		public $url = '/';
		
		/**
		 * @var string Specifies the page title.
		 * @documentable
		 */
		public $title;
		
		/**
		 * @var string Determines whether the page is visible in the site maps and generated menus. 
		 * @see http://lemonstand.com/docs/creating_site_maps_dynamic_menus_and_breadcrumbs/ Creating site maps, dynamic menus and breadcrumbs
		 * @documentable
		 */
		public $navigation_visible = 1;
		
		/**
		 * @var string Specifies the page navigation label for site maps and generated menus.
		 * @see http://lemonstand.com/docs/creating_site_maps_dynamic_menus_and_breadcrumbs/ Creating site maps, dynamic menus and breadcrumbs
		 * @documentable
		 */
		public $navigation_label;
		
		/**
		 * @var boolean Determines whether the page is published.
		 * Unpublished pages are not visible on the front-end website.
		 * @documentable
		 */
		public $is_published = 1;

		/**
		 * @var string Specifies a protocol the page can be accessed by.
		 * By default all pages can be accessed by any protocol. The propety
		 * accepts the following values:
		 * <ul>
		 *   <li><em>any</em> - HTTP or HTTPS protocol</li>
		 *   <li><em>http</em> - HTTP only</li>
		 *   <li><em>https</em> - HTTPS only</li>
		 *   <li><em>none</em> - None - requests are always redirected to the page specified in the <em>Redirect</em> field.</li>
		 * </ul>
		 * @documentable
		 */
		public $protocol = 'any';
		
		/**
		 * @var string Contains the page description.
		 * @documentable
		 */
		public $description;

		/**
		 * @var string Contains the page keywords.
		 * @documentable
		 */
		public $keywords;
		
		/**
		 * @var string Contains the page content source code.
		 * <span class="note">Use {@link Cms_Page::get_content_code() get_content_code()} method instead of accessing 
		 * this property directly. The property reflects the database value, which can be not actual if the 
		 * {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode}
		 * is enabled.</span>
		 * @documentable
		 */
		public $content;
		
		public $implement = 'Db_AutoFootprints,  Db_Act_As_Tree';
		public $act_as_tree_name_field = 'title';
		public $auto_footprints_visible = true;
		
		public $calculated_columns = array(
			'protocol_name'=>array('sql'=>"if(pages.protocol='any', 'Any', if(pages.protocol='none', 'None (redirect)', if(pages.protocol='https', 'HTTPS only', 'HTTP only')))", 'type'=>db_text)
		);
		
		protected static $page_cache = null;
		protected static $dir_existence_cache = null;
		protected $api_added_columns = array();
		protected $form_context = null;
		protected $block_cache = null;
		public $no_file_copy = false;
		public $act_as_tree_sql_filter = null;
		private static $_cache = array();
		
		public $has_and_belongs_to_many = array(
			'customer_groups'=>array('class_name'=>'Shop_CustomerGroup', 'join_table'=>'page_customer_groups', 'order'=>'name', 'foreign_key'=>'customer_group_id', 'primary_key'=>'page_id')
		);

		public $belongs_to = array(
			'template'=>array('class_name'=>'Cms_Template'),
			'security_mode'=>array('class_name'=>'Cms_SecurityMode', 'foreign_key'=>'security_mode_id'),
			'security_redirect'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'security_redirect_page_id'),
			'parent'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'parent_id')
		);
		
		/*
		 * Navigation cache
		 */
		
		public static $navigation_parent_cache = null;
		protected static $navigation_id_cache = null;
		protected static $navigation_full_parent_cache = null;
		
		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$php_allowed = Core_Configuration::is_php_allowed();
			
			$this->define_column('title', 'Title')->order('asc')->validation()->fn('trim')->required('Please specify the page title.');
			$this->define_column('is_published', 'Published');
			
			$this->define_column('url', 'Page URL')->validation()->fn('trim')->fn('mb_strtolower')->
				required('Please provide the page URL.')->unique('Url "%s" already in use.', array($this, 'configure_unique_validator'))->
				regexp(',^[/a-z0-9_\.-]*$,i', "Page url can contain only latin characters, numbers and signs _, -, /, and .")->
				regexp(',^/,i', "The first character in the url must be the forward slash.")->method('validate_url');

			$this->define_column('label', 'Label')->validation()->fn('trim');
			$this->define_column('disable_ga', 'Disable Google Analytics tracking')->listTitle('Disable GA')->defaultInvisible();

			$this->define_column('description', 'Description')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('keywords', 'Keywords')->defaultInvisible()->validation()->fn('trim');

			$this->define_column('head', 'Head Declarations')->invisible()->validation()->fn('trim');
			for ($i=1; $i <= self::max_block_num; $i++)
			{
				$this->define_column('page_block_name_'.$i, 'Block Code')->invisible()->validation()->fn('trim')->fn('mb_strtolower')->regexp(',^[a-z0-9_-]*$,i', "Block codes can contain only latin characters, numbers and signs _, -");
				$this->define_column('page_block_content_'.$i, 'Block Content')->invisible()->validation()->fn('trim');
			}
			
			$this->define_column('content', 'Content')->invisible()->validation()->required();
			$this->define_relation_column('template', 'template', 'Layout', db_varchar, '@name')->validation();
			$this->define_column('action_reference', 'Action')->defaultInvisible();
			
			if ($php_allowed)
			{
				$this->define_column('action_code', 'Post Action Code')->invisible();
				$this->define_column('pre_action', 'Pre Action Code')->invisible();
				$this->define_column('ajax_handlers_code', 'AJAX Handlers')->invisible();
			}
			
			$this->define_relation_column('security_mode', 'security_mode', 'Access', db_varchar, '@name')->defaultInvisible();
			$this->define_relation_column('security_redirect', 'security_redirect', 'Redirect', db_varchar, '@title')->defaultInvisible()->validation()->method('validate_redirect');
			$this->define_column('protocol', 'Allowed Protocol')->invisible();
			$this->define_column('protocol_name', 'Allowed Protocol')->defaultInvisible();
			$this->define_relation_column('parent', 'parent', 'Parent Page', db_varchar, 'if(@label is not null and length(@label) > 0, @label, @title)')->defaultInvisible()->listTitle('Navigation Parent');
			$this->define_column('navigation_visible', 'Visible')->defaultInvisible()->listTitle('Navigation Visible');
			$this->define_column('navigation_label', 'Menu Label')->defaultInvisible()->listTitle('Navigation Label')->validation()->fn('trim');
			$this->define_column('navigation_sort_order', 'Sort Order');
			
			$this->define_multi_relation_column('customer_groups', 'customer_groups', 'Customer Groups', '@name')->defaultInvisible();
			$this->define_column('enable_page_customer_group_filter', 'Enable customer group filter')->defaultInvisible();
			
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
				$this->define_column('directory_name', 'Directory Name')->defaultInvisible()->validation()->fn('trim')->required("Please specify the directory name.")->
					regexp('/^[a-z_0-9-]*$/i', 'Directory name can only contain latin characters, numbers, dashes and underscores.')->
					fn('strtolower')->unique('Directory name "%s" already used by another page. Please use another directory name.', array($this, 'configure_unique_validator'));

			$this->defined_column_list = array();
			Backend::$events->fireEvent('cms:onExtendPageModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}
		
		public function define_form_fields($context = null)
		{
			$php_allowed = Core_Configuration::is_php_allowed();
			
			$this->form_context = $context;
			if ($context != 'content')
			{
				$this->add_form_field('is_published')->tab('Page')->collapsable();
				$this->add_form_field('template')->tab('Page')->emptyOption('<please select a layout>')->collapsable();
				$this->add_form_field('title', 'left')->tab('Page')->collapsable();
				$this->add_form_field('url', 'right')->tab('Page')->collapsable();
				
				$settings_manager = Cms_SettingsManager::get();
				$label_align = 'left';
				$ga_align = 'right';
				if ($settings_manager->enable_filebased_templates)
				{
					$this->add_form_field('directory_name', 'left')->tab('Page')->comment('Name of the directory to store the page files', 'above')->collapsable();
					$label_align = 'right';
					$ga_align = 'full';
				}
				
				$this->add_form_field('label', $label_align)->tab('Page')->comment('Label is only used to distinguish pages in the list', 'above')->collapsable();
				$this->add_form_field('disable_ga', $ga_align)->tab('Page')->comment('Use this checkbox to disable the Google Analytics tracking for this specific page. You can configure Google Analytics tracking on the System/Settings/Statistics and Dashboard page.', 'above')->collapsable();

				$contentField = $this->add_form_field('content')->tab('Page')->size('giant')->cssClasses('code')->language('php')->renderAs(frm_code_editor)->saveCallback('save_code');

				$this->add_form_field('description')->tab('Meta');
				$this->add_form_field('keywords')->tab('Meta');
				
				$this->add_form_field('head')->tab('Head & Blocks')->size('small')->cssClasses('code')->renderAs(frm_code_editor)->saveCallback('save_code')->comment('In the field below you can define code to be rendered in the HEAD element of the page layout - JavaSript or CSS resource references, etc. The code can contain PHP tags (in PHP themes) or Twig tags (in Twig themes). In the page layout you can output the page head content with the $this->render_head() call in PHP themes or with render_head() call in Twig themes.', 'above');

				$this->add_form_section('You can use page blocks for injecting page-specific blocks of HTML code (like sidebars or custom headers) into the page layouts. Use the $this->render_block(\'block_name\') in PHP themes, or render_block(\'block_name\') in Twig themes, call in the page layout to output a specific page block.', 'Page Blocks')->tab('Head & Blocks');
				
				$visible_blocks = $this->number_of_blocks_visible();
				for ($i=1; $i <= self::max_block_num; $i++)
				{
					$css_class = $i <= $visible_blocks ? null : 'hidden';
					
					$this->add_form_field('page_block_name_'.$i)->tab('Head & Blocks')->cssClassName($css_class)->cssClasses('lowercase');
					$this->add_form_field('page_block_content_'.$i)->tab('Head & Blocks')->size('large')->cssClasses('code')->renderAs(frm_code_editor)->saveCallback('save_code')->noLabel()->cssClassName($css_class);
				}
				
				if ($visible_blocks < self::max_block_num)
					$this->add_form_custom_area('add_page_block')->tab('Head & Blocks');
			
				$this->add_form_field('action_reference')->tab('Action')->renderAs(frm_dropdown)->comment('Select an action provided by a module.', 'above');

				if ($php_allowed)
				{
					$this->add_form_field('pre_action')->tab('Action')->size('large')->cssClasses('code')->comment('PHP code to execute before the page display. If you selected some action in the drop-down menu above, the code from this field will be executed <strong>before the selected action</strong>.', 'above', true)->renderAs(frm_code_editor)->language('php')->saveCallback('save_code');
					$this->add_form_field('action_code')->tab('Action')->size('large')->cssClasses('code')->comment('PHP code to execute before the page display. If you selected some action in the drop-down menu above, the selected action will be executed <strong>before the code in this field</strong>.', 'above', true)->renderAs(frm_code_editor)->language('php')->saveCallback('save_code');
					$this->add_form_field('ajax_handlers_code')->tab('AJAX')->size('giant')->cssClasses('code')->comment('If you need, you may define custom AJAX handler functions here.', 'above')->renderAs(frm_code_editor)->language('php')->saveCallback('save_code');
				}
			
				$this->add_form_field('protocol')->comment('Please select a protocol visitors can access this page by.', 'above')->tab('Security')->renderAs(frm_dropdown);
				$this->add_form_field('security_mode', 'left')->referenceDescriptionField('@description')->comment('Please select security mode to apply to this page.', 'above')->tab('Security')->renderAs(frm_radio);
				$this->add_form_field('security_redirect', 'right')->referenceSort('title')->comment("Select a page to redirect to when the 'None' protocol is selected or when the visitor has no rights to access this page.", 'above')->emptyOption('<select>')->tab('Security');
				
				$this->add_form_field('enable_page_customer_group_filter')->tab('Visibility');
				$this->add_form_field('customer_groups')->tab('Visibility')->comment('Please select customer groups the page should be visible for.', 'above');

			} else {
				$user = Phpr::$security->getUser();
				$can_edit_pages = $user->get_permission('cms', 'manage_pages');
				$can_manage_static_pages = $user->get_permission('cms', 'manage_static_pages');
				
				if ($can_edit_pages || $can_manage_static_pages)
				{
					$this->add_form_field('is_published')->tab('Content')->collapsable();
					$this->add_form_field('template')->tab('Content')->emptyOption('<please select a layout>')->collapsable();
					$this->add_form_field('title', 'left')->tab('Content')->collapsable();
					$this->add_form_field('url', 'right')->tab('Content')->collapsable();
				}
				
				$blocks = $this->list_content_blocks();
				$editor_config = System_HtmlEditorConfig::get('cms', 'cms_page_content');

				foreach ($blocks as $block)
				{
					$this->add_form_section(null, $block->name)->tab('Content');
					$column_name = 'content_block_'.$block->code;
					$this->custom_columns[$column_name] = db_text;
					$this->_columns_def = null;
					$this->define_column($column_name, $block->name)->validation();
					$field = $this->add_form_field($column_name);
					
					if ($block->type == 'html')
					{
						$field->renderAs(frm_html)->saveCallback('save_code');
						$editor_config->apply_to_form_field($field);
						$field->htmlPlugins .= ',save,fullscreen,inlinepopups';
						$field->htmlButtons1 = 'save,separator,'.$field->htmlButtons1.',separator,fullscreen';
						$field->htmlFullWidth = true;
					}
					
					$field->tab('Content')->noLabel();
					
					$this->$column_name = $this->get_content_block_content($block->code);
				}
				
				$this->add_form_field('description')->tab('Meta');
				$this->add_form_field('keywords')->tab('Meta');
			}
			
			$this->add_form_field('parent')->tab('Navigation')->emptyOption('<none>')->optionsHtmlEncode(false)->comment('Please specify a parent page for this page. The parent page information will be used for the navigation menus generating only.', 'above');
			$this->add_form_field('navigation_visible')->tab('Navigation')->comment('Display this page in automatically generated navigation menus.');
			$this->add_form_field('navigation_label')->tab('Navigation')->comment('A label to represent this page in automatically generated navigation menus.', 'above');

			Backend::$events->fireEvent('cms:onExtendPageForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_template_options($key_value = -1)
		{
			$templates = Cms_Template::create();
			$templates->order('name');
			
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$templates->where('theme_id=?', $theme->id);
			
			return $templates->find_all()->as_array('name', 'id');
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('cms:onGetPageFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		protected function number_of_blocks_visible()
		{
			$result = 0;
			
			for ($i=1; $i <= self::max_block_num; $i++)
			{
				$name_field = 'page_block_name_'.$i;
				$content_field = 'page_block_content_'.$i;
				
				if (strlen($this->$name_field) || strlen($this->$content_field))
					$result ++;
			}
			
			return $result;
		}
		
		public function get_page_tree_options($key_value, $max_level = 100, $theme_id = null)
		{
			$result = array();
			$obj = new self();

			if ($key_value == -1)
			{
				if (!$theme_id)
				{
					if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
						$obj->act_as_tree_sql_filter = 'theme_id='.$theme->id;
				} else
					$obj->act_as_tree_sql_filter = 'theme_id='.$theme_id;

				$this->listParentIdOptions($obj->list_root_children('pages.navigation_sort_order'), $result, 0, $this->id, $max_level);
			}
			else 
			{
				if ($key_value == null)
					return $result;
				
				$obj = Cms_Page::create();
				$obj = $obj->find($key_value);

				if ($obj)
					return h($obj->title);
			}
			
			return $result;
		}
		
		public function get_parent_options($key_value = -1, $max_level = 100)
		{
			return $this->get_page_tree_options($key_value, $max_level);
		}

		private function listParentIdOptions($items, &$result, $level, $ignore, $maxLevel, $urlKey = false)
		{
			if ($maxLevel !== null && $level > $maxLevel)
				return;
			
			foreach ($items as $item)
			{
				if ($ignore !== null && $item->id == $ignore)
					continue;
					
				$key = $urlKey ? $item->url_title : $item->id;
				
				$result[$key] = str_repeat("&nbsp;", $level*3).h($item->title).' ['.h($item->url).']';
				$this->listParentIdOptions($item->list_children('pages.navigation_sort_order'), $result, $level+1, $ignore, $maxLevel, $urlKey);
			}
		}
		
		public function get_action_reference_options($keyValue=-1)
		{
			$result = array();
			$result['Custom'] = self::action_custom;
			
			$actions = Cms_ActionManager::listActions();
			foreach ($actions as $action)
				$result[$action] = $action;

			return $result;
		}
		
		public function get_protocol_options($keyValue=-1)
		{
			return array(
				'any'=>'HTTP or HTTPS',
				'http'=>'HTTP only',
				'https'=>'HTTPS only',
				'none' => 'None (redirect)'
			);
		}
		
		public function after_validation($deferred_session_key = null) 
		{
			$this->url = strtolower($this->url);
			if ($this->url != '/' && substr($this->url, -1) == '/')
				$this->url = substr($this->url, 0, -1);
		}
		
		public function validate_url($name, $value)
		{
			if (preg_match(',//,i', $value))
				$this->validation->setError('Invalid URL - forward slashes sequence.', $name, true);
				
			return true;
		}

		public function validate_redirect($name, $value)
		{
			if ($this->security_mode && $this->security_mode->id != Cms_SecurityMode::everyone && !$value)
				$this->validation->setError('Please select security redirect page.', $name, true);
				
			return true;
		}

		/**
		 * Returns a page by its URL.
		 * Resolves an URL and returns the page object corresponding that URL.
		 * Please note that the method finds pages in context of the currently active {@link http://lemonstand.com/docs/themes/ theme}.
		 *
		 * The following code example tries to find a page with URL <em>/categories/computers</em>.
		 * If there was a page with URL <em>/categories</em> it would be returned and the $params
		 * array would contain a single element <em>computers</em>:
		 * <pre>
		 * $params = array();
		 * $page = Cms_Page::findByUrl('/categories/computers', $params);
		 * </pre>
		 * @documentable
		 * @param string $url Specifies the URL.
		 * @param array $params A list of URL parameters.
		 * Represent parameters extracted from the URL. Pass an empty array to this parameter.
		 * @return mixed Returns {@link Cms_Page} object if a page corresponding the URL was found. Otherwise returns NULL.
		 */
		public static function findByUrl($url, &$params)
		{
			if (self::$page_cache == null)
			{
				self::$page_cache = array();
				
				if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
					$pages = Db_DbHelper::objectArray("select id, url from pages where theme_id=:theme_id", array('theme_id'=>$theme->id));
				else
					$pages = Db_DbHelper::objectArray("select id, url from pages");
					
				foreach ($pages as $page)
					self::$page_cache[$page->url] = $page;

				uasort(self::$page_cache, array('Cms_Router', 'sort_objects'));
			}
			
			if ($page = Cms_Router::find_object_by_url($url, self::$page_cache, $params))
			{
				$page_obj = new Cms_Page(null, array('no_column_init'=>true));
				$page_obj = $page_obj->where('id=?', $page->id)->find();

				return $page_obj;
			}

			return null;
		}
		
		/**
		 * Finds a page by its identifier.
		 * This method uses internal caching, and it is recommendable
		 * to use it instead of direct ActiveRecord calls/
		 * @documentable
		 * @param integer $id Specifies the page identifier.
		 * @return Cms_Page Returns the page object. Returns NULL if the page was not found.
		 */
		public static function find_by_id($id)
		{
			if (!strlen($id))
				return null;
			
			if (array_key_exists($id, self::$_cache))
				return self::$_cache[$id];
			
			return self::$_cache[$id] = self::create()->find($id);
		}
		
		/**
		 * Finds a page by action name.
		 * This method allows to find a page which uses a specific {@link http://lemonstand.com/docs/actions/ action}.
		 * Please note that the method finds pages in context of the currently active {@link http://lemonstand.com/docs/themes/ theme}.
		 * The following example finds the product page:
		 * <pre>$page = Cms_Page::find_by_action_reference('shop:product');</pre>
		 * @documentable
		 * @param string $action Specifies the action name.
		 * @return Cms_Page Returns the page object. Returns NULL if the page was not found.
		 */
		public function find_by_action_reference($action)
		{
			$obj = self::create()->where('action_reference=?', $action);
			
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
				$obj->where('theme_id=?', $theme->id);
				
			return $obj->find();
		}
		
		/**
		 * Returns an evaluated page content. 
		 * Executes the page source code and returns the generated content. Note that the returned value 
		 * does not contain the page layout markup. It contains only the page content, including values 
		 * of any content blocks defined on the page. If the page was not found, the returns string 
		 * <em>ERROR: page [url] not found.</em>
		 * 
		 * Example:
		 * <pre>echo Cms_Page::content_by_url('/sidebar');</pre>
		 * @param string $url Specifies the page URL.
		 * @return string Returns the page content
		 */
		public static function content_by_url($url)
		{
			global $_cms_current_page_object;
			
			$prev_page = $_cms_current_page_object;

			$params = array();
			$page = self::findByUrl($url, $params);
			if (!$page)
				return "ERROR: page ".$url." not found.";

			ob_start();
			$page_content = null;
			try
			{
				$_cms_current_page_object = $page;

				$page_content = ob_get_clean();
				eval('?>'.$page->content);

				$_cms_current_page_object = $prev_page;
			}
			catch (exception $ex)
			{
				$_cms_current_page_object = $prev_page;
				@ob_end_clean();
			}
			
			return $page_content;
		}
	
		public function before_delete($id=null)
		{
			$isInUse = Db_DbHelper::scalar(
				'select count(*) from pages where security_redirect_page_id=:id', 
				array('id'=>$this->id)
			);
			
			if ($isInUse)
				throw new Phpr_ApplicationException("Unable to delete the page: it is used as a security redirect page for other page.");
				
			$isInUse = Db_DbHelper::scalar(
				'select count(*) from pages where parent_id=:id', 
				array('id'=>$this->id)
			);
			
			if ($isInUse)
				throw new Phpr_ApplicationException("Unable to delete the page because it has subpages.");

			Backend::$events->fireEvent('cms:onDeletePage', $this);
			Backend::$events->fireEvent('onDeletePage', $this); // deprecated
		}
		
		public function get_security_redirect_options()
		{
			$pages = self::create()->order('title');
			if ($this->id)
				$pages->where('id <> ?', $this->id);
				
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$pages->where('theme_id=?', $theme->id);
				
			$pages = $pages->find_all();

			$result = array();
			foreach ($pages as $page)
				$result[$page->id] = $page->title.' ['.$page->url.']';
				
			return $result;
		}

		public function before_save($deferred_session_key = null) 
		{
			$content_blocks = $this->list_content_blocks();
			$this->has_contentblocks = count($content_blocks);
			
			if ($this->form_context == 'content')
			{
				foreach ($content_blocks as $content_block)
				{
					$block = Cms_ContentBlock::get_by_page_and_code($this->id, $content_block->code);
					if (!$block)
					{
						$block = Cms_ContentBlock::create();
						$block->page_id = $this->id;
						$block->code = $content_block->code;
					}

					$column_name = 'content_block_'.$content_block->code;
					$block->content = $this->$column_name;

					$block->save();
				}
			}

			$block_contents = array();
			for ($i=1; $i <= self::max_block_num; $i++)
			{
				$name_field = 'page_block_name_'.$i;
				$content_field = 'page_block_content_'.$i;
				
				if (strlen($this->$name_field) || strlen($this->$content_field))
					$block_contents[] = array($this->$name_field, $this->$content_field);
			}
			
			$content_block_num = count($block_contents);
			foreach ($block_contents as $index=>$block_data)
			{
				$name_field = 'page_block_name_'.($index+1);
				$content_field = 'page_block_content_'.($index+1);
				$this->$name_field = $block_data[0];
				$this->$content_field = $block_data[1];
			}
			
			for ($i=$content_block_num+1; $i <= self::max_block_num; $i++)
			{
				$name_field = 'page_block_name_'.$i;
				$content_field = 'page_block_content_'.$i;
				$this->$name_field = null;
				$this->$content_field = null;
			}
			
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
			{
				if (isset($this->fetched['directory_name']) && $this->fetched['directory_name'] != $this->directory_name)
				{
					$new_dir_path = $this->get_file_path($this->directory_name);
					if (file_exists($new_dir_path) && is_dir($new_dir_path))
					{
						throw new Phpr_ApplicationException('Directory '.$this->directory_name.' already exists.');
					}
					
					if (!@rename(
						$this->get_file_path($this->fetched['directory_name']),
						$new_dir_path
					))
						throw new Phpr_ApplicationException('Error renaming the page directory.');
				}
			}
		}

		public function list_content_blocks($content = null)
		{
			if ($content === null)
				$content = $this->content;

			$result = array();
			
			$matches = array();
			preg_match_all('/content_block\s*\([\'"]([-_a-z0-9]*)[\'"]\s*,\s*[\'"]([^)]*)[\'"]\)/i', $content, $matches);
			
			foreach ($matches[0] as $index=>$block)
			{
				$code = $matches[1][$index];
				$obj = array('code'=>$code, 'name'=>$matches[2][$index], 'type'=>'html');
				$result[$code] = (object)$obj;
			}

			$matches = array();
			preg_match_all('/text_content_block\s*\([\'"]([-_a-z0-9]*)[\'"]\s*,\s*[\'"]([^)]*)[\'"]\)/i', $content, $matches);
			
			foreach ($matches[0] as $index=>$block)
			{
				$code = $matches[1][$index];
				$obj = array('code'=>$code, 'name'=>$matches[2][$index], 'type'=>'text');
				$result[$code] = (object)$obj;
			}

			return $result;
		}
		
		public function after_delete()
		{
			$blocks = Cms_ContentBlock::create()->find_all_by_page_id($this->id);
			foreach ($blocks as $block)
				$block->delete();

			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates && $settings_manager->templates_directory_is_writable() && $this->directory_name)
				$this->delete_page_dir();
		}

		public function after_create() 
		{
			Db_DbHelper::query('update pages set navigation_sort_order=:navigation_sort_order where id=:id', array(
				'navigation_sort_order'=>$this->id,
				'id'=>$this->id
			));

			$this->navigation_sort_order = $this->id;
		}
		
		public function find_available_url($base)
		{
			$theme_filter = null;
			$theme_id = null;
			
			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_edit_theme();
				if ($theme)
				{
					$theme_filter = 'and theme_id=:theme_id';
					$theme_id = $theme->id;
				}
			}
			
			$counter = 1;
			$url = $base;
			while (Db_DbHelper::scalar("select count(*) from pages where url=:url $theme_filter", array('url'=>$url, 'theme_id'=>$theme_id)))
			{
				$url = $base.'_'.$counter;
				$counter++;
			}
			
			return $url;
		}
		
		public static function eval_page_statistics()
		{
			$theme_filter = null;
			$theme_id = null;
			
			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_edit_theme();
				if ($theme)
				{
					$theme_filter = 'and theme_id=:theme_id';
					$theme_id = $theme->id;
				}
			}
			
			return Db_DbHelper::object(
				"select
					(select count(*) from pages where id=id $theme_filter) as page_num,
					(select count(*) from pages where security_mode_id='customers' $theme_filter) as protected_page_num
				"
			, array('theme_id'=>$theme_id));
		}
		
		/**
		 * Returns a list of page blocks
		 */
		public function list_blocks($force_files = false)
		{
			if ($this->block_cache === null)
			{
				$this->block_cache = array();

				if (!Cms_SettingsManager::get()->enable_filebased_templates && !$force_files)
				{
					for ($index = 1; $index <= Cms_Page::max_block_num; $index++)
					{
						$name_field = 'page_block_name_'.$index;
						$content_field = 'page_block_content_'.$index;
				
						if (strlen($this->$name_field))
							$this->block_cache[$this->$name_field] = $this->$content_field;
					}
				} else
				{
					$path = $this->get_file_path($this->directory_name);
					$files = @scandir($path);
					if($files) {
						foreach ( $files as $file ) {
							if ( substr( $file, 0, 6 ) == 'block_' && substr( $file, - 4 ) == '.' . self::get_content_extension() ) {
								$this->block_cache[substr( $file, 6, - 4 )] = $this->get_page_file_content( $file, false );
							}
						}
					}
				}
			}
			
			return $this->block_cache;
		}

		/*
		 * Automatic menu generation features
		 */
		
		/**
		 * Returns a list of navigation root pages.
		 * @documentable
		 * @see http://lemonstand.com/docs/creating_site_maps_dynamic_menus_and_breadcrumbs/ Creating site maps, dynamic menus and breadcrumbs
		 * @return array Returns an array of the {@link Cms_PageNavigationNode} objects
		 */
		public static function navigation_root_pages()
		{
			self::init_navigation_cache();
			
			$result = array();
			if (!array_key_exists(-1, self::$navigation_parent_cache))
				return $result;

			foreach (self::$navigation_parent_cache[-1] as $reference)
				$result[] = $reference;
				
			return $result;
		}
		
		/**
		 * Returns the navigation menu label.
		 * If the navigation menu label was not specified for this page, the function returns the page title.
		 * @documentable
		 * @see http://lemonstand.com/docs/creating_site_maps_dynamic_menus_and_breadcrumbs/ Creating site maps, dynamic menus and breadcrumbs
		 * @return string Returns the page navigation menu label or page title.
		 */
		public function navigation_label()
		{
			self::init_navigation_cache();

			$reference = $this->find_this_reference();
			if (!$reference)
				return null;
				
			return $reference->navigation_label();
		}
		
		/**
		 * Returns a list of pages grouped under this page.
		 * @documentable
		 * @see http://lemonstand.com/docs/creating_site_maps_dynamic_menus_and_breadcrumbs/ Creating site maps, dynamic menus and breadcrumbs
		 * @return array Returns an array of the {@link Cms_PageNavigationNode} objects
		 */
		public function navigation_subpages()
		{
			self::init_navigation_cache();

			$reference = $this->find_this_reference();
			if (!$reference)
				return array();

			return $reference->navigation_subpages();
		}
		
		/**
		 * Returns a list of the page navigation parents.
		 * You can use this method for generating bread crumb navigation. 
		 * @documentable
		 * @see http://lemonstand.com/docs/creating_site_maps_dynamic_menus_and_breadcrumbs/ Creating site maps, dynamic menus and breadcrumbs
		 * @param boolean $include_this Determines whether the current page should be included to the result.
		 * @return array Returns an array of the {@link Cms_PageNavigationNode} objects.
		 */
		public function navigation_parents($include_this = true)
		{
			$this->init_navigation_cache();

			$reference = $this->find_this_reference();
			if (!$reference)
				return array();
				
			$result = array();
			if ($include_this)
				$result[] = $reference;
			
			$parent_key = $reference->parent_id;

			if (!array_key_exists($parent_key, self::$navigation_id_cache))
				return $result;

			$parents = array();
			while (array_key_exists($parent_key, self::$navigation_id_cache))
			{
				$parents[] = self::$navigation_id_cache[$parent_key];
				$parent_key = self::$navigation_id_cache[$parent_key]->parent_id;
			}

			$parents = array_reverse($parents);

			if ($include_this)
				$parents[] = $reference;

			return $parents;
		}

		protected function find_this_reference()
		{
			if (array_key_exists($this->id, self::$navigation_id_cache))
				return self::$navigation_id_cache[$this->id];
				
			return null;
		}
		
		protected static function init_navigation_cache()
		{
			if (self::$navigation_parent_cache === null)
			{
				self::$navigation_parent_cache = array();
				self::$navigation_id_cache = array();
				self::$navigation_full_parent_cache = array();
				
				$current_theme = null;
				if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
					$current_theme = $theme;
					
				$theme_filter = $current_theme ? ' where theme_id='.$current_theme->id : null; 
				
				$controller = Cms_Controller::get_instance();
				$customer_group_id = Cms_Controller::get_customer_group_id();

				$pages = Db_DbHelper::objectArray("select 
					id,
					title, 
					parent_id, 
					url,
					navigation_visible,
					is_published,
					navigation_label, 
					security_mode_id,
					if (enable_page_customer_group_filter is null or enable_page_customer_group_filter = 0, 1, (select count(*) from page_customer_groups where page_id=pages.id and customer_group_id='$customer_group_id')) as visible_for_group
				from pages $theme_filter order by navigation_sort_order");
				
				$full_reference_list = array();
				$id_cache = array();
				foreach ($pages as $page)
				{
					if (!$controller || $page->security_mode_id != 'everyone')
					{
						if ($page->security_mode_id == 'guests' && $controller->customer)
							continue;

						if ($page->security_mode_id == 'customers' && !$controller->customer)
							continue;
					}
					
					$result = Backend::$events->fireEvent('cms:onGetPageNavigationVisibility', $page);
					foreach ($result as $visibility_flag)
					{
						if (!$visibility_flag)
							continue 2;
					}
					
					$reference = new Cms_PageNavigationNode($page);
					$full_reference_list[] = $reference;
					$id_cache[$reference->id] = $reference;

					$parent_key = $page->parent_id ? $page->parent_id : -1;
					self::$navigation_full_parent_cache[$parent_key][] = $reference;
				}

				foreach ($full_reference_list as $reference)
				{
					if (!$reference->navigation_visible || !$reference->visible_for_group || !$reference->is_published)
						continue;

					if (!strlen($reference->parent_id))
						$reference->parent_id = -1;

					while (array_key_exists($reference->parent_id, $id_cache) && (!$id_cache[$reference->parent_id]->navigation_visible || !$id_cache[$reference->parent_id]->visible_for_group))
					{
						if (array_key_exists($reference->parent_id, $id_cache))
							$reference->parent_id = $id_cache[$reference->parent_id]->parent_id;
					}

					$parent_key = $reference->parent_id ? $reference->parent_id : -1;
					self::$navigation_parent_cache[$parent_key][] = $reference;
					$reference->parent_key_index = count(self::$navigation_parent_cache[$parent_key])-1;

					self::$navigation_id_cache[$reference->id] = $reference;
				}
			}
		}
		
		/**
		 * Determines whether the page is visible for a specific {@link Shop_CustomerGroup customer group}.
		 * @documentable
		 * @param integer $group_id Specifies the customer group identifier.
		 * @return boolean Returns TRUE if the page is visible for the customer group. Returns FALSE otherwise.
		 */
		public function visible_for_customer_group($group_id)
		{
			if (!$this->enable_page_customer_group_filter)
				return true;

			return Db_DbHelper::scalar('select count(*) from page_customer_groups where page_id=:page_id and customer_group_id=:group_id', array(
				'page_id'=>$this->id,
				'group_id'=>$group_id
			));
		}
		
		public static function set_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				Db_DbHelper::query('update pages set navigation_sort_order=:navigation_sort_order where id=:id', array(
					'navigation_sort_order'=>$order,
					'id'=>$id
				));
			}
		}

		public function after_save() 
		{
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
				$this->copy_to_file();
		}
				
		/*
		 * File-based templates support
		 */
		
		/**
		 * Copies the page to a file
		 */
		public function copy_to_file($templates_dir = null)
		{
			if ($this->no_file_copy)
			{
				if ($this->directory_name)
					$this->save_dir_name_to_db($this->directory_name);

				return;
			}
			
			$file_name = $this->directory_name ? $this->directory_name : $this->create_file_name();

			try
			{
				$this->save_to_files($this->get_file_path($file_name));
			} catch (exception $ex)
			{
				throw new Phpr_ApplicationException('Error saving page '.$this->name.' to file. '.$ex->getMessage());
			}
			
			if (!$this->directory_name)
				$this->save_dir_name_to_db($file_name);
				
			$this->directory_name = $file_name;
		}

		/**
		 * Saves object data to a file
		 */
		protected function save_to_files($dest_path)
		{
			if (file_exists($dest_path) && !is_writable($dest_path))
				throw new Phpr_ApplicationException('Directory is not writable: '.$dest_path);

			if (!file_exists($dest_path))
			{
				if (!@mkdir($dest_path))
					throw new Phpr_ApplicationException('Error creating page directory: '.$dest_path);

				$folder_permissions = Phpr_Files::getFolderPermissions();
				@chmod($dest_path, $folder_permissions);
			}

			/*
			 * Save regular fields
			 */

			$this->save_to_file($this->content, $dest_path.'/'.$this->get_content_file_name($dest_path));
			$this->save_to_file($this->add_php_tags($this->action_code), $dest_path.'/post_action.php');
			$this->save_to_file($this->add_php_tags($this->pre_action), $dest_path.'/pre_action.php');
			$this->save_to_file($this->head, $dest_path.'/head_declarations.'.self::get_content_extension());
			$this->save_to_file($this->add_php_tags($this->ajax_handlers_code), $dest_path.'/ajax_handlers.php');

			/*
			 * Save page blocks
			 */
			
			$used_page_block_files = array();
			
			for ($index = 1; $index <= Cms_Page::max_block_num; $index++)
			{
				$name_field = 'page_block_name_'.$index;
				$content_field = 'page_block_content_'.$index;
		
				if (strlen($this->$name_field))
				{
					$file_name = 'block_'.$this->$name_field.'.'.self::get_content_extension();
					$used_page_block_files[] = $file_name;
					$this->save_to_file($this->$content_field, $dest_path.'/'.$file_name);
				}
			}
			
			/*
			 * Save content blocks
			 */

			Cms_ContentBlock::clear_cache();
			$content_blocks = $this->list_content_blocks();
			foreach ($content_blocks as $block_info)
			{
				$block = Cms_ContentBlock::get_by_page_and_code($this->id, $block_info->code);
				if ($block)
				{
					$file_name = 'content_'.$block_info->code.'.'.self::get_content_extension();
					$this->save_to_file($block->content, $dest_path.'/'.$file_name);
				}
			}
			
			/*
			 * Delete renamed block files
			 */
			
			$files = @scandir($dest_path);
			if($files) {
				foreach ( $files as $file ) {
					if ( substr( $file, - 4 ) != '.' . self::get_content_extension() ) {
						continue;
					}

					if ( substr( $file, 0, 6 ) == 'block_' ) {
						if ( !in_array( $file, $used_page_block_files ) ) {
							@unlink( $dest_path . '/' . $file );
						}
					}
				}
			}
		}
		
		protected function get_content_file_name($path)
		{
			if (!file_exists($path))
				return false;
			
			$files = @scandir($path);
			if($files) {
				foreach ( $files as $file ) {
					if ( substr( $file, - 4 ) != '.' . self::get_content_extension() ) {
						continue;
					}

					if ( substr( $file, 0, 5 ) != 'page_' ) {
						continue;
					}

					return $file;
				}
			}
			
			return 'page_'.self::db_name_to_file_name($this->url).'.'.self::get_content_extension();
		}
		
		protected function add_php_tags($string)
		{
			if (!strlen($string))
				$string = "\n\n";
			
			return "<?\n".$string."\n?>";
		}
		
		protected function save_dir_name_to_db($file_name)
		{
			$file_name = pathinfo($file_name, PATHINFO_FILENAME);
			Db_DbHelper::query('update pages set directory_name=:file_name where id=:id', array('file_name'=>$file_name, 'id'=>$this->id));
		}
		
		protected function get_page_file_path($file_name)
		{
			return $this->get_file_path($this->directory_name).'/'.$file_name;
		}
		
		protected function get_page_file_content($file_name, $close_php_tag = true)
		{
			$path = $this->get_page_file_path($file_name);

			if (file_exists($path))
			{
				if (!$close_php_tag)
					return file_get_contents($path);
				else
					return '?>'.file_get_contents($path);
			}

			return null;
		}
		
		protected function load_file_content($file_name, $remove_php_wrap)
		{
			if (!$file_name)
				return false;
			
			$path = $this->get_page_file_path($file_name);

			if (!file_exists($path))
				return false;
				
			$content = file_get_contents($path);
			if ($remove_php_wrap)
			{
				$content = preg_replace('/^\s*\<\?\s*/', '', $content);
				$content = preg_replace('/^\s*\<\?php\s*/', '', $content);
				$content = preg_replace('/\?\>\s*$/', '', $content);
			}
			
			return trim($content);
		}
		
		/**
		 * Returns an absolute path to the object file
		 */
		public function get_file_path($dir_name)
		{
			if (!$dir_name)
				return null;
				
			$settings_manager = Cms_SettingsManager::get();
			return $settings_manager->get_templates_dir_path($this->get_theme()).'/pages/'.$dir_name;
		}
		
		public function create_file_name()
		{
			$templates_dir = Cms_SettingsManager::get()->get_templates_dir_path($this->get_theme());
			return $this->generate_unique_file_name(self::db_name_to_file_name($this->url), $templates_dir.'/pages/');
		}

		/**
		 * Converts page DB name to a directory name
		 */
		protected static function db_name_to_file_name($name)
		{
			if ($name == '/')
				return 'home';
			
			$name = mb_strtolower($name);
			$name = preg_replace('/[^a-z_0-9]/i', '_', $name);
			$name = preg_replace('/_+/i', '_', $name);
			$name = preg_replace('/^_/i', '', $name);
			$name = preg_replace('/_$/i', '', $name);
			
			return $name;
		}
		
		/**
		 * Returns the PRE action code string.
		 * This method loads the code string from a file if the 
		 * {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode}
		 * is enabled. If it is disabled, it returns the database field content.
		 * @documentable
		 * @param boolean $remove_php_wrap Determines if PHP open and close tags should be removed from result.
		 * @return string Returns the PRE action code string.
		 */
		public function get_pre_action_code($remove_php_wrap = false)
		{
			if (Cms_SettingsManager::get()->enable_filebased_templates)
			{
				if (!$remove_php_wrap)
					return $this->get_page_file_content('pre_action.php');
				else
					return $this->load_file_content('pre_action.php', true);
			}
				
			return $this->pre_action;
		}

		/**
		 * Returns the POST action code string.
		 * This method loads the code string from a file if the 
		 * {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode}
		 * is enabled. If it is disabled, it returns the database field content.
		 * @documentable
		 * @param boolean $remove_php_wrap Determines if PHP open and close tags should be removed from result.
		 * @return string Returns the POST action code string.
		 */
		public function get_post_action_code($remove_php_wrap = false)
		{
			if (Cms_SettingsManager::get()->enable_filebased_templates)
			{
				if (!$remove_php_wrap)
					return $this->get_page_file_content('post_action.php');
				else
					return $this->load_file_content('post_action.php', true);
			}
				
			return $this->action_code;
		}
		
		/**
		 * Returns the page content code string
		 * This method loads the code string from a file if the 
		 * {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode}
		 * is enabled. If it is disabled, it returns the database field content.
		 * The result of this method can be affected by {@link cms:onGetPageContent} event.
		 * @documentable
		 * @return string Returns the page content string.
		 */
		public function get_content_code()
		{
			$content = $this->content;
			$settings_manager = Cms_SettingsManager::get();
			$path = $this->get_file_path($this->directory_name);
			
			if(Cms_SettingsManager::get()->enable_filebased_templates)
				$content = $this->get_page_file_content($this->get_content_file_name($path), false);
				
			$result = Backend::$events->fire_event(array('name' => 'cms:onGetPageContent', 'type' => 'filter'), array(
				'url' => $this->url, 
				'content' => $content,
				'path' => $path, 
				'file_based' => $settings_manager->enable_filebased_templates
			));
			
			return $result['content'];
		}
		
		/**
		 * Returns the page AJAX handlers code string
		 * This method loads the code string from a file if the 
		 * {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode}
		 * is enabled. If it is disabled, it returns the database field content.
		 * @documentable
		 * @param boolean $remove_php_wrap Determines if PHP open and close tags should be removed from result.
		 * @return string Returns the page AJAX handlers code string.
		 */
		public function get_ajax_handlers_code($remove_php_wrap = false)
		{
			if (Cms_SettingsManager::get()->enable_filebased_templates)
			{
				if (!$remove_php_wrap)
					return $this->get_page_file_content('ajax_handlers.php');
				else
					return $this->load_file_content('ajax_handlers.php', true);
			}
				
			return $this->ajax_handlers_code;
		}
		
		/**
		 * Returns the page head declarations code string
		 * This method loads the code string from a file if the 
		 * {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode}
		 * is enabled. If it is disabled, it returns the database field content.
		 * @documentable
		 * @return string Returns the page head declarations code string.
		 */
		public function get_head_code()
		{
			if (Cms_SettingsManager::get()->enable_filebased_templates)
				return $this->get_page_file_content('head_declarations.'.self::get_content_extension(), false);
				
			return $this->head;
		}
		
		/**
		 * Returns content of a {@link http://lemonstand.com/docs/creating_editable_blocks/ content block} content by its code.
		 * Behavior of this method can be affected by {@link cms:onGetPageBlockContent} event handler.
		 * @documentable
		 * @param string $code Specifies the content block code.
		 * @return string Returns the content block content string.
		 */
		public function get_content_block_content($code)
		{
			$content = '';
			$settings_manager = Cms_SettingsManager::get();
		
			$block = Cms_ContentBlock::get_by_page_and_code($this->id, $code);
			if($block)
				$content = $block->content;
			
			if(Cms_SettingsManager::get()->enable_filebased_templates)
				$content = $this->get_page_file_content('content_'.$code.'.'.self::get_content_extension(), false);

			$path = $this->get_file_path($this->directory_name);
			
			$result = Backend::$events->fire_event(array('name' => 'cms:onGetPageBlockContent', 'type' => 'filter'), array(
				'url' => $this->url, 
				'content' => $content,
				'path' => $path, 
				'file_based' => $settings_manager->enable_filebased_templates,
				'code'=>$code, 
				'page_id'=>$this->id
			));
			
			return $result['content'];
		}
		
		/**
		 * Loads page content form the page directory into the model
		 */
		public function load_directory_content()
		{
			$settings_manager = Cms_SettingsManager::get();
			if (!$settings_manager->enable_filebased_templates)
				return;

			$path = $this->get_file_path($this->directory_name);
			if (!file_exists($path))
				return;

			/*
			 * Load regular fields
			 */
			
			$content = $this->load_file_content($this->get_content_file_name($path), false);
			if ($content !== false)
				$this->content = $content;

			$content = $this->load_file_content('pre_action.php', true);
			if ($content !== false)
				$this->pre_action = $content;

			$content = $this->load_file_content('post_action.php', true);
			if ($content !== false)
				$this->action_code = $content;

			$content = $this->load_file_content('ajax_handlers.php', true);
			if ($content !== false)
				$this->ajax_handlers_code = $content;
				
			$content = $this->load_file_content('head_declarations.'.self::get_content_extension(), false);
			if ($content !== false)
				$this->head = $content;
			
			/*
			 * Load page blocks
			 */

			for ($index = 1; $index <= Cms_Page::max_block_num; $index++)
			{
				$name_field = 'page_block_name_'.$index;
				$content_field = 'page_block_content_'.$index;
		
				$this->$name_field = null;
				$this->$content_field = null;
			}
			
			$blocks = $this->list_blocks();
			$index = 1;
			foreach ($blocks as $code=>$content)
			{
				$name_field = 'page_block_name_'.$index;
				$content_field = 'page_block_content_'.$index;

				$this->$name_field = $code;

				$this->$content_field = $content;
				$index++;
			}
		}
		
		/**
		 * Copies the page content from directory to the database
		 */
		public function set_from_directory()
		{
			$page_fields = array();
			
			/*
			 * Set regular fields
			 */
			
			$path = $this->get_file_path($this->directory_name);

			$content = $this->load_file_content($this->get_content_file_name($path), false);
			if ($content)
			{
				$page_fields['content'] = $content;
				$page_content_blocks = $this->list_content_blocks($content);
				$page_fields['has_contentblocks'] = count($page_content_blocks);
			}
			else
			{
				$page_fields['has_contentblocks'] = 0;
			}
				
			$content = $this->load_file_content('pre_action.php', true);
			if ($content)
				$page_fields['pre_action'] = $content;

			$content = $this->load_file_content('post_action.php', true);
			if ($content)
				$page_fields['action_code'] = $content;
				
			$content = $this->load_file_content('ajax_handlers.php', true);
			if ($content)
				$page_fields['ajax_handlers_code'] = $content;
				
			$content = $this->load_file_content('head_declarations.'.self::get_content_extension(), false);
			if ($content)
				$page_fields['head'] = $content;
			
			/*
			 * Set page blocks
			 */
			
			for ($index = 1; $index <= Cms_Page::max_block_num; $index++)
			{
				$page_fields['page_block_name_'.$index] = null;
				$page_fields['page_block_content_'.$index] = null;
			}
			
			$index = 1;
			$blocks = $this->list_blocks(true);
			foreach ($blocks as $code=>$content)
			{
				$page_fields['page_block_name_'.$index] = $code;
				$page_fields['page_block_content_'.$index] = $content;
				$index++;
			}

			$this->sql_update('pages', $page_fields, 'id='.$this->id);
			
			/*
			 * Set content blocks
			 */
			
			$files = @scandir($path);
			if($files) {
				foreach ( $files as $file ) {
					if ( substr( $file, 0, 8 ) == 'content_' && substr( $file, - 4 ) == '.' . self::get_content_extension() ) {
						$code  = substr( $file, 8, - 4 );
						$block = Cms_ContentBlock::get_by_page_and_code( $this->id, $code );
						if ( !$block ) {
							$block          = Cms_ContentBlock::create();
							$block->page_id = $this->id;
							$block->code    = $code;
						}

						$block->content = $this->get_page_file_content( $file, false );
						$block->save();
					}
				}
			}
		}

		/**
		 * Deletes the page directory from disk
		 */
		protected function delete_page_dir()
		{
			if (!strlen($this->directory_name))
				return;

			$path = $this->get_file_path($this->directory_name);
			
			if (!file_exists($path) || !is_dir($path))
				return;
			
			$files = @scandir($path);
			if($files) {
				foreach ( $files as $file ) {
					if ( !is_dir( $path . '/' . $file ) ) {
						@unlink( $path . '/' . $file );
					}
				}
			}
				
			@rmdir($path);
		}
		
		/**
		 * Returns a list of page directories which are not used by any page.
		 */
		public static function list_orphan_directories()
		{
			$settings_manager = Cms_SettingsManager::get();

			$current_theme = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$current_theme = $theme;
			
			$path = $settings_manager->get_templates_dir_path($current_theme).'/pages';
			$result = array();

			$files = @scandir($path);


			if($files) {
				$theme_filter = $current_theme ? ' where theme_id='.$current_theme->id : null;
				$existing_files = Db_DbHelper::scalarArray('select directory_name from pages'.$theme_filter);

				foreach ( $files as $file ) {
					$file_path = $path . '/' . $file;
					if ( !is_dir( $file_path ) || substr( $file, 0, 1 ) == '.' || !preg_match( '/^[a-z_0-9-]*$/', $file ) ) {
						continue;
					}

					if ( !in_array( $file, $existing_files ) ) {
						$result[] = $file;
					}
				}
			}
			
			return $result;
		}
		
		/**
		 * Returns TRUE if the page directory cannot be found
		 */
		public function directory_is_missing()
		{
			$settings_manager = Cms_SettingsManager::get();
			if (!$settings_manager->enable_filebased_templates)
				return false;

			self::init_existing_directory_cache();

			return !array_key_exists($this->id, self::$dir_existence_cache) || !self::$dir_existence_cache[$this->id];
		}
		
		protected static function init_existing_directory_cache()
		{
			$settings_manager = Cms_SettingsManager::get();

			if (self::$dir_existence_cache !== null)
				return;

			self::$dir_existence_cache = array();

			$current_theme = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$current_theme = $theme;

			$dir = $settings_manager->get_templates_dir_path($current_theme).'/pages';
			$theme_filter = $current_theme ? ' where theme_id='.$current_theme->id : null;
			$pages = Db_DbHelper::objectArray('select id, directory_name from pages'.$theme_filter);
			if (file_exists($dir) && is_dir($dir))
			{
				$directories = @scandir($dir);

				foreach ($pages as $page)
				{
					$file_exists = in_array($page->directory_name, $directories);
					$is_dir = is_dir($dir.'/'.$page->directory_name);

					self::$dir_existence_cache[$page->id] = $file_exists && $is_dir;
				}
			}
		}
		
		/**
		 * Assigns directory name to an existing page
		 */
		public function assign_directory_name($directory_name)
		{
			$directory_name = trim($directory_name);
			if  (!strlen($directory_name))
				throw new Phpr_ApplicationException('Please enter the directory name');
			
			if  (!preg_match('/^[a-z_0-9-]*$/i', $directory_name))
				throw new Phpr_ApplicationException('Directory name can only contain latin characters, numbers, dashes and underscores.');
				
			$current_theme = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$current_theme = $theme;

			$theme_filter = $current_theme ? ' and theme_id='.$current_theme->id : null;
			
			$in_use = Db_DbHelper::scalar(
				'select count(*) from pages where id <> :id and lower(directory_name)=:directory_name and ifnull(theme_id, 0)=ifnull(:theme_id, 0)'.$theme_filter, 
				array('id'=>$this->id, 'directory_name'=>$directory_name, 'theme_id'=>$this->theme_id));
				
			if ($in_use)
				throw new Phpr_ApplicationException('The directory name is already in use.');
				
			$this->directory_name = $directory_name;
			$this->copy_to_file();
			$this->save_dir_name_to_db($directory_name);
		}
		
		/**
		 * Binds page to an existing directory
		 */
		public function bind_to_directory($directory_name)
		{
			$directory_name = trim($directory_name);
			if (!strlen($directory_name))
				throw new Phpr_ApplicationException('Please select the page directory');
			
			$this->save_dir_name_to_db($directory_name);
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Cms_Module::update_cms_content_version();
		}
		
		public static function update_content_file_extension($templates_dir, $old, $new)
		{
			$pages_dir = $templates_dir.'/pages';
			if (!file_exists($pages_dir) || !is_dir($pages_dir))
				return;
				
			$directories = @scandir($pages_dir);
			if($directories) {
				foreach ( $directories as $dir ) {
					$dir = $pages_dir . '/' . $dir;
					if ( !is_dir( $dir ) ) {
						continue;
					}

					$files = @scandir( $dir );
					if($files) {
						foreach ( $files as $file_name ) {
							$info = pathinfo( $file_name );

							if ( !preg_match( '/^[a-z_0-9-;]*$/i', $info['filename'] ) ) {
								continue;
							}

							if ( !isset( $info['extension'] ) || mb_strtolower( $info['extension'] ) != $old ) {
								continue;
							}

							if (
								$info['filename'] != 'head_declarations' &&
								substr( $info['filename'], 0, 6 ) != 'block_' &&
								substr( $info['filename'], 0, 8 ) != 'content_' &&
								substr( $info['filename'], 0, 5 ) != 'page_'
							) {
								continue;
							}

							$old_path = $dir . '/' . $file_name;
							$new_path = $dir . '/' . $info['filename'] . '.' . $new;
							if ( !@rename( $old_path, $new_path ) ) {
								throw new Phpr_SystemException( 'Error renaming file: ' . $old_path . ' to ' . $new_path );
							}
						}
					}
				}
			}
		}
		
		public function duplicate()
		{
			$result = parent::duplicate();
			
			return $result;
		}
		
		public function save_duplicated($original)
		{
			$this->save();
			
			/*
			 * Duplicate content blocks
			 */
			
			$content_blocks = Cms_ContentBlock::create()->where('page_id=?', $original->id)->find_all();
			foreach ($content_blocks as $content_block)
			{
				$new_block = $content_block->duplicate();
				$new_block->page_id = $this->id;
				$new_block->save();
			}
			
			/*
			 * Update page templates directory
			 */

			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
				$this->copy_to_file();

			Backend::$events->fireEvent('cms:onAfterPageDuplicate', $original, $this);
		}
		
		/*
		 * Event descriptions
		 */
		
		/**
		 * Allows to define new columns in the page model.
		 * The event handler should accept two parameters - the page object and the form execution context string. 
		 * To add new columns to the page model, call the  {@link Db_ActiveRecord::define_column() define_column()} method of
		 * the page object. 
		 * Before you add new columns to the model, you should add them to the database (the <em>pages</em> table). 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onExtendPageModel', $this, 'extend_page_model');
		 *   Backend::$events->addEvent('cms:onExtendPageForm', $this, 'extend_page_form');
		 * }
		 * 
		 * public function extend_page_model($page, $context)
		 * {
		 *   $page->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_page_form($page, $context)
		 * {
		 *   if ($context != 'content')  
		 *     $page->add_form_field('x_extra_description')->tab('Description');
		 * }
		 * </pre>
		 * @event cms:onExtendPageModel
		 * @package cms.events
		 * @see cms:onExtendPageForm
		 * @see cms:onGetPageFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @author LemonStand eCommerce Inc.
		 * @param Cms_Page $page Specifies the page object to extend.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendPageModel($page, $context) {}

		/**
		 * Allows to add new fields to the Create/Edit Page form.
		 * Usually this event is used together with the {@link cms:onExtendPageModel} event.
		 * The event handler should accept two parameters - the page object and the form execution context string. 
		 * To add new fields to the page form, call the {@link Db_ActiveRecord::add_form_field() add_form_field()} method of
		 * the page object. 
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onExtendPageModel', $this, 'extend_page_model');
		 *   Backend::$events->addEvent('cms:onExtendPageForm', $this, 'extend_page_form');
		 * }
		 * 
		 * public function extend_page_model($page, $context)
		 * {
		 *   $page->define_column('x_extra_description', 'Extra description');
		 * }
		 * 
		 * public function extend_page_form($page, $context)
		 * {
		 *   if ($context != 'content')  
		 *     $page->add_form_field('x_extra_description')->tab('Description');
		 * }
		 * </pre>
		 * @event cms:onExtendPageForm
		 * @package cms.events
		 * @see cms:onExtendPageModel
		 * @see cms:onGetPageFieldOptions
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @author LemonStand eCommerce Inc.
		 * @param Cms_Page $page Specifies the page object to extend.
		 * @param string $context Specifies the execution context.
		 */
		private function event_onExtendPageForm($page, $context) {}
		
		/**
		 * Allows to populate drop-down, radio- or checkbox list fields, which have been added with {@link cms:onExtendPageForm} event.
		 * Usually you do not need to use this event for fields which represent 
		 * {@link http://lemonstand.com/docs/extending_models_with_related_columns data relations}. But if you want a standard 
		 * field (corresponding an integer-typed database column, for example), to be rendered as a drop-down list, you should 
		 * handle this event.
		 *
		 * The event handler should accept 2 parameters - the field name and a current field value. If the current
		 * field value is -1, the handler should return an array containing a list of options. If the current 
		 * field value is not -1, the handler should return a string (label), corresponding the value.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onExtendPageModel', $this, 'extend_page_model');
		 *   Backend::$events->addEvent('cms:onExtendPageForm', $this, 'extend_page_form');
		 *   Backend::$events->addEvent('cms:onGetPageFieldOptions', $this, 'get_page_field_options');
		 * }
		 *
		 * public function extend_page_model($page, $context)
		 * {
		 *   $page->define_column('x_color', 'Color');
		 * }
		 *
		 * public function extend_page_form($page, $context)
		 * {
		 *   if ($context != 'content')  
		 *     $page->add_form_field('x_color')->renderAs(frm_dropdown);
		 * }
		 *
		 * public function get_page_field_options($field_name, $current_key_value)
		 * {
		 *   if ($field_name == 'x_color')
		 *   {
		 *     $options = array(
		 *       0 => 'Red',
		 *       1 => 'Green',
		 *       2 => 'Blue'
		 *     );
		 *
		 *     if ($current_key_value == -1)
		 *       return $options;
		 *
		 *     if (array_key_exists($current_key_value, $options))
		 *       return $options[$current_key_value];
		 *   }
		 * }
		 * </pre>
		 * @event cms:onGetPageFieldOptions
		 * @package cms.events
		 * @see cms:onExtendPageModel
		 * @see cms:onExtendPageForm
		 * @see http://lemonstand.com/docs/extending_existing_models Extending existing models
		 * @see http://lemonstand.com/docs/creating_and_updating_database_tables Creating and updating database tables
		 * @author LemonStand eCommerce Inc.
		 * @param string $db_name Specifies the field name.
		 * @param string $field_value Specifies the field value.
		 * @return mixed Returns a list of options or a specific option label.
		 */
		private function event_onGetPageFieldOptions($db_name, $field_value) {}
			
		/**
		 * Triggered before a page is deleted.
		 * The event handler can throw an exception to prevent deletion of a specific page.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onDeletePage', $this, 'page_deletion_check');
		 * }
		 *
		 * public function page_deletion_check($page)
		 * {
		 *   $in_use = Db_DbHelper::scalar(
		 *     'select count(*) from shop_categories where page_id=:id', 
		 *     array('id'=>$page->id)
		 *   );
		 *
		 *   if ($in_use)
		 *     throw new Phpr_ApplicationException("Unable to delete page: it is used as a category landing page.");
		 * }
		 * </pre>
		 * @event cms:onDeletePage
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Cms_Page $page Specifies the page object to be deleted.
		 */
		private function event_onDeletePage($page) {}

		/**
		 * Allows to hide a page from {@link http://lemonstand.com/docs/creating_site_maps_dynamic_menus_and_breadcrumbs automatically generated menus and site maps}.
		 * The handler function should return FALSE if the page should not be visible and TRUE 
		 * if it should be visible. If the event is handled by different modules, a page would 
		 * not be visible if any handler returned FALSE.
		 * The event handler should accept a single argument - the page object (stdClass). The object has the following properties:
		 * <ul>
		 *   <li><em>id</em> - specifies the page identifier</li>
		 *   <li><em>title</em> - specifies the page title</li>
		 *   <li><em>parent_id</em> - specifies the page parent identifier</li> 
		 *   <li><em>url</em> - specifies the page URL</li>
		 *   <li><em>navigation_label</em> - specifies the page navigation label</li> 
		 * </ul>
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onGetPageNavigationVisibility', $this, 'eval_page_visibility');
		 * }
		 *
		 * public function eval_page_visibility($page)
		 * {
		 *   return $page->url != '/hidden_page';
		 * }
		 * </pre>
		 * @event cms:onGetPageNavigationVisibility
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param mixed $page Specifies the CMS page object (stdClass).
		 * @return boolean Returns FALSE if the page should not be visible and TRUE if the page should be visible.
		 */
		private function event_onGetPageNavigationVisibility($page) {}
			
		/**
		 * Triggered after a page has been duplicated with Duplicate {@link http://lemonstand.com/docs/themes/ Theme} feature.
		 * @event cms:onAfterPageDuplicate
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Cms_Page $original Specifies the original CMS page object.
		 * @param Cms_Page $new Specifies the duplicated CMS page object.
		 */
		private function event_onAfterPageDuplicate($original, $new) {}
		
		/**
		 * Allows to programmatically modify a page content.
		 * The event handler should accepts an array of parameters with the following keys:
		 * <ul>
		 * <li><em>url</em> - the page URL.</li>
		 * <li><em>content</em> - the page content string.</li>
		 * <li><em>path</em> - path to the page file (if {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode} is enabled).</li>
		 * <li><em>file_based</em> - boolean, determines whether {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode} is enabled.</li>
		 * </ul>
		 * The event handler should return an array with at least a single element <em>content</em> containing the updated content.
		 * <pre>
		 * public function subscribeEvents() 
		 * {
		 *   Backend::$events->addEvent('cms:onGetPageContent', $this, 'get_page_content');
		 * }
		 *
		 * public function get_page_content($data) 
		 * {
		 *   // Replace parts of the page content
		 *   $data['content'] = str_replace('NAME', 'OUR STORE NAME', $data['content']);
		 *
		 *   // Add to the page content
		 *   if($data['file_based'])
		 *     $data['content'] .= '<br>CMS page file: '.$data['path'];
		 * 
		 *   $data['content'] .= '<br>CMS page URL: '.$data['url'];
		 *
		 *   return $data;
		 * }
		 * </pre>
		 * @event cms:onGetPageContent
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $data Specifies a list of input parameters.
		 * @return array Returns an array containing the <em>content</em> element.
		 */
		private function event_onGetPageContent($data) {}

		/**
		 * Allows to programmatically modify a content block content.
		 * The event handler should accepts an array of parameters with the following keys:
		 * <ul>
		 * <li><em>url</em> - the page URL.</li>
		 * <li><em>content</em> - the page content string.</li>
		 * <li><em>path</em> - path to the page file (if {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode} is enabled).</li>
		 * <li><em>file_based</em> - boolean, determines whether {@link http://lemonstand.com/docs/using_file_based_templates/ file-based templates mode} is enabled.</li>
		 * <li><em>code</em> - the content block code.</li>
		 * <li><em>page_id</em> - the page identifier.</li>
		 * </ul>
		 * The event handler should return an array with at least a single element <em>content</em> containing the updated content.
		 * @event cms:onGetPageBlockContent
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param array $data Specifies a list of input parameters.
		 * @return array Returns an array containing the <em>content</em> element.
		 */
		private function event_onGetPageBlockContent($data) {}

		/**
		 * Allows to alter the list of pages on the CMS/Pages page in the Administration Area.
		 * The event handler accepts the back-end controller object and should return
		 * a configured {@link Cms_Page} object. The following example repeats the default functionality,
		 * and effectively does not change the page list behavior.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onPreparePageListData', $this, 'prepare_page_list');
		 * }
		 * 
		 * public function prepare_page_list($controller) 
		 * {
		 *   $obj = Cms_Page::create();
		 * 
		 *   if (!$controller->currentUser->get_permission('cms', 'manage_pages'))
		 *     $obj->where('pages.has_contentblocks is not null and pages.has_contentblocks > 0');
		 *   
		 *   if (Cms_Theme::is_theming_enabled())
		 *   {
		 *     $theme = Cms_Theme::get_edit_theme();
		 *     if ($theme)
		 *       $obj->where('theme_id=?', $theme->id);
		 *   }
		 * 
		 *   return $obj;
		 * }
		 * </pre>
		 * @event cms:onPreparePageListData
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Backend_Controller $controller The back-end controller object.
		 * @return Cms_Page configured CMS Page object.
		 */
		private function event_onPreparePageListData($controller) {}

		/**
		 * Allows to add new buttons to the toolbar above the page list (CMS/Pages page) in the Administration Area.
		 * The event handler should accept the back-end controller object and use its renderPartial() method
		 * to render new toolbar elements.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onExtendPagesToolbar', $this, 'extend_toolbar');
		 * }
		 *
		 * public function extend_toolbar($controller)
		 * {
		 *   $controller->renderPartial(PATH_APP.'/modules/cmsext/partials/_toolbar.htm');
		 * }
		 *
		 * // Example of the _toolbar.htm partial
		 *
		 * <div class="separator">&nbsp;</div>
		 * <?= backend_ctr_button('Some button', 'new_document', '#') ?>
		 * </pre>
		 * @triggered /modules/cms/controllers/cms_pages/_pages_control_pabel.htm
		 * @event cms:onExtendPagesToolbar
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Backend_Controller $controller The back-end controller object.
		 */
		private function event_onExtendPagesToolbar($controller) {}

		/**
		 * Allows to configure the Administration Area CMS Pages pages before they are displayed.
		 * In the event handler you can update the back-end controller properties.
		 * @event cms:onConfigurePagesPage
		 * @triggered /modules/cms/controllers/cms_pages.php
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Cms_Pages $controller Specifies the controller object.
		 */
		private function event_onConfigurePagesPage($controller) {}
			
		/**
		 * Allows to load extra CSS or JavaScript files on the Created/Edit Page page. 
		 * The event handler should accept a single parameter - the controller object reference. 
		 * Call addJavaScript() and addCss() methods of the controller object to add references to JavaScript and CSS files. 
		 * Use paths relative to LemonStand installation URL for your resource files. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onDisplayPageForm', $this, 'load_page_resources');
		 * }
		 *  
		 * public function load_page_resources($controller)
		 * {
		 *   $controller->addJavaScript('/modules/mymodule/resources/javascript/my.js');
		 *   $controller->addCss('/modules/mymodule/resources/css/my.css');  
		 * }
		 * </pre>
		 * @event cms:onDisplayPageForm
		 * @triggered /modules/cms/controllers/cms_pages.php
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Cms_Pages $controller Specifies the controller object.
		 */
		private function event_onDisplayPageForm($controller) {}

		/**
		 * Allows to add tabs to the Create/Edit Page page sidebar in the Administration Area. 
		 * The handler should return an associative array of tab titles and corresponding tab partials.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('shop:onListPageEditorSidebarTabs', $this, 'add_page_editor_tabs');
		 * }
		 *  
		 * public function add_page_editor_tabs()
		 * {
		 *   return array('Special information'=>PATH_APP.'/mymodule/partials/special.htm');
		 * }
		 * </pre>
		 * @event cms:onListPageEditorSidebarTabs
		 * @triggered /modules/cms/controllers/cms_pages/_action_doc.htm
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @return array Returns an array of tab names and tab partial paths.
		 */
		private function event_onListPageEditorSidebarTabs() {}

		/**
		 * Triggered before a CMS page imported from a file is saved.
		 * Use the event handler to assign custom values to the page properties.
		 * <pre>
		 * public function subscribeEvents() {
		 *   Backend::$events->addEvent('cms:onCmsPageImport', $this, 'on_page_import');
		 * }
		 * public function on_page_import($page)
		 * {
		 *   //assign a template to the page
		 *   $template = Cms_Template::create()->find_by_id(1);
		 *   if($template)
		 *     $page->template_id = $template->id;
		 * }
		 * </pre>
		 * @event cms:onCmsPageImport
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Cms_Page $page Specifies the order object.
		 */
		private function event_onCmsPageImport($page) {}
			
		/**
		 * Allows to add new buttons to the toolbar above the Edit Content form (CMS/Pages/Edit page content) in the Administration Area.
		 * The event handler should accept two parameters - the back-end controller object and the page object. Use the controller's 
		 * renderPartial() method to render new toolbar elements.
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onExtendPageContentToolbar', $this, 'extend_toolbar');
		 * }
		 *
		 * public function extend_toolbar($controller, $page)
		 * {
		 *   $controller->renderPartial(PATH_APP.'/modules/cmsext/partials/_toolbar.htm', array('page'=>$page));
		 * }
		 *
		 * // Example of the _toolbar.htm partial
		 *
		 * <div class="separator">&nbsp;</div>
		 * <?= backend_button('Some button', array('href'=>'#')) ?>
		 * <?= backend_ajax_button('AJAX button', 'onSomeHandler') ?>
		 * </pre>
		 * @triggered /modules/cms/controllers/cms_pages/_pages_control_pabel.htm
		 * @event cms:onExtendPageContentToolbar
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Backend_Controller $controller The back-end controller object.
		 * @param Cms_Page $page Specifies the CMS page object.
		 */
		private function event_onExtendPageContentToolbar($controller, $page) {}
			
		/**
		 * Allows to add new items to the list context menu on the CMS/Pages page in the Administration Area.
		 * The event handler should accept two parameters - the back-end controller object and the page object. Use the controller's 
		 * renderPartial() method to render menu item elements. Each item should be presented with a LI and A elements. Example:
		 * <pre>
		 * public function subscribeEvents()
		 * {
		 *   Backend::$events->addEvent('cms:onExtendPagesContextMenu', $this, 'extend_pages_menu');
		 * }
		 *
		 * public function extend_pages_menu($controller, $page)
		 * {
		 *   $controller->renderPartial(PATH_APP.'/modules/testmodule/partials/_menu.htm', array('page'=>$page));
		 * }
		 *
		 * // Example of the _menu.htm partial
		 *
		 * <li>
		 *   <a href="#" target="_blank">New item</a>
		 * </li>
		 * </pre>
		 * @triggered /modules/cms/controllers/cms_pages/_pages_control_pabel.htm
		 * @event cms:onExtendPagesContextMenu
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param Backend_Controller $controller The back-end controller object.
		 * @param Cms_Page $page Specifies the CMS page object.
		 */
		private function event_onExtendPagesContextMenu($controller, $page) {}
	}

?>
