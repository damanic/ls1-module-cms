<?php

	class CMS_Module extends Core_ModuleBase
	{
		private static $cms_content_version_update = false;
		
		/**
		 * Creates the module information object
		 * @return Core_ModuleInfo
		 */
		protected function createModuleInfo()
		{
			return new Core_ModuleInfo(
				"CMS",
				"LemonStand CMS features",
				"LemonStand eCommerce Inc." );
		}

		/**
		 * Returns a list of the module back-end GUI tabs.
		 * @param Backend_TabCollection $tabCollection A tab collection object to populate.
		 * @return mixed
		 */
		public function listTabs($tabCollection)
		{
			$user = Phpr::$security->getUser();
			$tabs = array(
				'pages'=>array('pages', 'Pages', array('manage_pages', 'manage_page_content', 'manage_static_pages', 'manage_page_order')),
				'templates'=>array('templates', 'Layouts', 'manage_pages'),
				'partials'=>array('partials', 'Partials', 'manage_pages'),
				'content'=>array('content', 'Content', 'manage_content'),
				'resources'=>array('resources', 'Resources', 'manage_resources'),
				'backup'=>array('backup', 'Export or Import', 'manage_pages')
			);
			
			if (Cms_Theme::is_theming_enabled())
			{
				unset($tabs['backup']);
				$tabs['themes'] = array('themes', 'Themes', 'manage_pages');
			}

			$first_tab = null;
			foreach ($tabs as $tab_id=>$tab_info)
			{
				if (($tabs[$tab_id][3] = $user->get_permission('cms', $tab_info[2])) && !$first_tab)
					$first_tab = $tab_info[0];
			}

			if ($first_tab)
			{
				$tab = $tabCollection->tab('cms', 'CMS', $first_tab, 20);
				foreach ($tabs as $tab_id=>$tab_info)
				{
					if ($tab_info[3])
						$tab->addSecondLevel($tab_id, $tab_info[1], $tab_info[0]);
				}
			}
		}
		
		public function subscribeEvents()
		{
			Backend::$events->addEvent('onLogin', $this, 'onUserLogin');
			Backend::$events->addEvent('cms:onAfterThemeActivated', $this, 'on_theme_activated');
		}
		
		public function onUserLogin()
		{
			Cms_Analytics::deleteStalePageviews();
		}
		
		public function listSettingsItems()
		{
			$maintenance_config = Cms_MaintenanceParams::create();
			
			$result = array(
				array(
					'icon'=>'/modules/cms/resources/images/stats_settings.png', 
					'title'=>'Statistics and Dashboard', 
					'url'=>'/cms/settings/stats',
					'description'=>'Configure Google Analytics integration or built-in statistics feature and manage the Dashboard.',
					'sort_id'=>10,
					'section'=>'CMS'
					),
				array(
					'icon'=>'/modules/cms/resources/images/maintenance.png', 
					'title'=>'Maintenance Session'.($maintenance_config->enabled ? ': ENABLED': ': disabled'), 
					'url'=>'/cms/maintenance',
					'description'=>'Configure the maintenance notification page and enable the maintenance mode.',
					'sort_id'=>15,
					'section'=>'CMS'
					),
				array(
					'icon'=>'/modules/cms/resources/images/cms_settings.png', 
					'title'=>'CMS Settings', 
					'url'=>'/cms/settings/config',
					'description'=>'Enable or disable the file-based CMS templates and manage other CMS parameters.',
					'sort_id'=>11,
					'section'=>'CMS'
					)
			);
			
			if (!Cms_Theme::is_theming_enabled())
			{
				$result[] = array(
					'icon'=>'/modules/cms/resources/images/artwork.png', 
					'title'=>'Enable Theming', 
					'url'=>'/cms/themes/enable',
					'description'=>'Enable Theming feature and transfer pages, partials, layouts and file resources to a new theme.',
					'sort_id'=>12,
					'section'=>'CMS'
				);
			}

			return $result;
		}
		
		/**
		 * Returns a list of HTML Editor configurations used by the module
		 * The method must return an array of configuration codes and descriptions:
		 * array('blog_post_content'=>'Blog post')
		 * @return array
		 */
		public function listHtmlEditorConfigs()
		{
			return array(
				'cms_page_content'=>'Page content block',
				'cms_global_content_block'=>'Global content block',
			);
		}
		
		/**
		 * Builds user permissions interface
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have "Access Level" drop-down:
		 * public function get_access_level_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param $host_obj ActiveRecord object to add fields to
		 */
		public function buildPermissionsUi($host_obj)
		{
			$host_obj->add_field($this, 'manage_pages', 'Manage pages', 'left')->renderAs(frm_checkbox)->comment('Create, modify or delete pages, partials, and templates.', 'above');
			$host_obj->add_field($this, 'manage_page_content', 'Manage pages content', 'right')->renderAs(frm_checkbox)->comment('Edit static content of pages previously created by other users.', 'above');
			$host_obj->add_field($this, 'manage_resources', 'Manage resources', 'left')->renderAs(frm_checkbox)->comment('Create, modify or delete website resource files - CSS, JavaScript, Images, etc.', 'above');
			$host_obj->add_field($this, 'manage_maintenance_mode', 'Manage maintenance mode', 'right')->renderAs(frm_checkbox)->comment('Enable or disable the Maintenance Mode from the CMS/Pages page.', 'above');
			$host_obj->add_field($this, 'manage_content', 'Manage global content blocks', 'left')->renderAs(frm_checkbox)->comment('Create and Edit existing global static content blocks.', 'above');
			$host_obj->add_field($this, 'manage_static_pages', 'Manage static pages', 'right')->renderAs(frm_checkbox)->comment('Create new and edit existing static pages.', 'above');
			$host_obj->add_field($this, 'manage_page_order', 'Manage page order')->renderAs(frm_checkbox)->comment('Manage the order of pages in the navigation menus.', 'above');
			
		}
		
		/**
		 * Returns a list of dashboard indicators in format
		 * array('indicator_code'=>array('partial'=>'partial_name.htm', 'name'=>'Indicator Name')).
		 * Partials must be placed to the module dashboard directory:
		 * /modules/cms/dashboard
		 */
		public function listDashboardIndicators()
		{
			return array(
				'visits'=>array('partial'=>'visits_indicator.htm', 'name'=>'Visits'),
				'pageviews'=>array('partial'=>'pageviews_indicator.htm', 'name'=>'Pageviews'),
				'newvisits'=>array('partial'=>'newvisits_indicator.htm', 'name'=>'New Visits (Google Analytics only)'),
				'pagespervisit'=>array('partial'=>'pagespervisit_indicator.htm', 'name'=>'Pages per Visit'),
				'timeonsite'=>array('partial'=>'timeonsite_indicator.htm', 'name'=>'Time on Site (Google Analytics only)'),
				'bouncerate'=>array('partial'=>'bouncerate_indicator.htm', 'name'=>'Bounce Rate (Google Analytics only)'),
			);
		}
		
		/**
		 * Returns a list of dashboard reports in format
		 * array('report_code'=>array('partial'=>'partial_name.htm', 'name'=>'Report Name')).
		 * Partials must be placed to the module dashboard directory:
		 * /modules/cms/dashboard
		 */
		public function listDashboardReports()
		{
			return array(
				'top_pages'=>array('partial'=>'toppages_report.htm', 'name'=>'Top Pages')
			);
		}
		
		/**
		 * CMS cache version management
		 */
		
		public static function get_cms_content_version()
		{
			return Db_ModuleParameters::get( 'cms', 'content_version', 0 );
		}
		
		public static function update_cms_content_version()
		{
			if (self::$cms_content_version_update)
				return;
				
			Db_ModuleParameters::set( 'cms', 'content_version', time() );
		}
		
		public static function begin_content_version_update()
		{
			self::$cms_content_version_update = true;
		}

		public static function end_content_version_update()
		{
			self::$cms_content_version_update = false;
			self::update_cms_content_version();
		}
		
		public function on_theme_activated($theme)
		{
			Cms_MaintenanceParams::handle_theme_activation($theme);
		}
	}
?>