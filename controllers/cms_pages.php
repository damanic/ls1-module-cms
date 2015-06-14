<?

	class Cms_Pages extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Backend_FileBrowser, Cms_ThemeSelector';
		public $list_model_class = 'Cms_Page';
		public $list_record_url = null;
		public $list_render_as_sliding_list = true;
		public $list_root_level_label = 'Pages';
		public $list_handle_row_click = false;
		public $list_no_sorting = false;
		public $list_no_pagination = false;
		public $list_no_setup_link = false;
		public $list_custom_body_cells = null;
		public $list_custom_head_cells = null;
		
		public $form_preview_title = 'Page';
		public $form_create_title = 'New Page';
		public $form_edit_title = 'Edit Page';
		public $form_model_class = 'Cms_Page';
		public $form_not_found_message = 'Page not found';
		public $form_redirect = null;
		public $form_create_save_redirect = null;
		public $form_flash_id = 'form_flash';
		
		public $form_edit_save_flash = 'The page has been successfully saved';
		public $form_create_save_flash = 'The page has been successfully added';
		public $form_edit_delete_flash = 'The page has been successfully deleted';
		public $form_edit_save_auto_timestamp = true;

		public $list_search_enabled = true;
		public $list_search_fields = array('@title', '@url', '@content');
		public $list_search_prompt = 'find pages by title, URL or content';

		public $enable_concurrency_locking = true;

		public $filebrowser_dirs = array();
		public $filebrowser_default_dirs = true;
		public $filebrowser_absoluteUrls = false;
		public $filebrowser_onFileClick = "return onFileBrowserFileClick('%s');";
		public $file_browser_file_list_class = 'ui-layout-anchor-window-bottom offset-24';

		protected $globalHandlers = array('edit_onDelete', 'onSave', 'onShowActionDocument', 'onActionChanged', 'onCustomEvent');

		protected $required_permissions = array('cms:manage_pages', 'cms:manage_page_content', 'cms:manage_static_pages', 'cms:manage_page_order');
		
		public function __construct()
		{
			Backend::$events->fireEvent('cms:onConfigurePagesPage', $this);
			
			parent::__construct();

			$this->app_tab = 'cms';
			$this->app_module_name = 'CMS';

			if ($this->currentUser)
			{
				if ($this->currentUser->get_permission('cms', array('manage_pages')))
					$this->list_record_url = url('/cms/pages/edit/');
				else
					$this->list_record_url = url('/cms/pages/content/');
					
				if (Phpr::$router->action == 'edit' || Phpr::$router->action == 'create' || Phpr::$router->action == 'content')
					Backend::$events->fireEvent('cms:onDisplayPageForm', $this);
			}

			$this->form_redirect = url('/cms/pages');
			$this->form_create_save_redirect = url('/cms/pages/edit/%s/'.uniqid());
			$this->app_page = 'pages';
			
			if (Phpr::$router->action == 'content')
				$this->filebrowser_onFileClick = null;

			if (Phpr::$router->action == 'reorder_pages')
				$this->setup_reorder_pages_list();

			if ($this->currentUser)
			{
				$this->viewData['can_edit_content'] = $this->currentUser->get_permission('cms', 'manage_page_content');
				$this->viewData['can_edit_pages'] = $this->currentUser->get_permission('cms', 'manage_pages');
				$this->viewData['can_manage_maintenance'] = $this->currentUser->get_permission('cms', 'manage_maintenance_mode');
				$this->viewData['can_manage_static_pages'] = $this->currentUser->get_permission('cms', 'manage_static_pages');
				$this->viewData['can_manage_page_order'] = $this->currentUser->get_permission('cms', 'manage_page_order');
			}
		}

		public function listPrepareData()
		{
			$updated_data = Backend::$events->fireEvent('cms:onPreparePageListData', $this);
			foreach ($updated_data as $updated)
			{
				if ($updated)
					return $updated;
			}
			
			$obj = Cms_Page::create();
			
			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_edit_theme();
				if ($theme)
					$obj->where('theme_id=?', $theme->id);
			}

			return $obj;
		}

		public function listOverrideSortingColumn($sorting_column)
		{
			if (Phpr::$router->action == 'reorder_pages')
			{
				$result = array('field'=>'navigation_sort_order', 'direction'=>'asc');
				return (object)$result;
			}

			return $sorting_column;
		}

		public function index()
		{
			$this->app_page_title = 'Pages';
		}
		
		public function listGetRowClass($model)
		{
			$classes = $model->is_published ? null : 'disabled';
			
			if(!$this->viewData['can_edit_pages'] && !$model->has_contentblocks)
				$classes .= ' disabled';
			
			if ($model->directory_is_missing())
				$classes .= ' error';

			return $classes;
		}
		
		protected function index_onResetFilters()
		{
			$this->listCancelSearch();
			Phpr::$response->redirect(url('cms/pages'));
		}
		
		protected function eval_page_statistics()
		{
			return Cms_Page::eval_page_statistics();
		}
		
		public function formCreateModelObject()
		{
			$obj = new Cms_Page();
			$obj->init_columns_info();
			$obj->define_form_fields();

			return $obj;
		}
		
		public function index_onCreateStaticPage()
		{
			try
			{
				$obj = Cms_Page::create();
				$obj->url = $obj->find_available_url(Cms_Page::default_static_page_url);
				$obj->title = Cms_Page::default_static_page_name;
				$obj->protocol = 'any';
				$content = '<? content_block("content", "Page Content") ?>';
				
				if (Cms_Theme::is_theming_enabled())
				{
					$theme = Cms_Theme::get_edit_theme();
					if ($theme)
					{
						$obj->theme_id = $theme->id;
						
						if ($theme->templating_engine == 'twig')
							$content = '{{content_block("content", "Page Content")}}';
					}
				}

				$obj->content = $content;

				Backend::$events->fireEvent('core:onBeforeFormRecordCreate', $this, $obj);
				$obj->save();
				Backend::$events->fireEvent('core:onAfterFormRecordCreate', $this, $obj);
				
				Phpr::$response->redirect(url('/cms/pages/content/'.$obj->id));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onDisableMaintenanceMode()
		{
			try
			{
				Cms_MaintenanceParams::set_status(false);
				Phpr::$session->flash['success'] = 'Maintenance mode has been successfully disabled';
				Phpr::$response->redirect(url('/cms/pages/'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function index_onEnableMaintenanceMode()
		{
			try
			{
				Cms_MaintenanceParams::set_status(true);
				Phpr::$session->flash['success'] = 'Maintenance mode has been successfully enabled';
				Phpr::$response->redirect(url('/cms/pages/'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onRefresh()
		{
			$this->renderPartial('page_list_content');
		}
		
		protected function onSave($id)
		{
			Phpr::$router->action == 'create' ? $this->create_onSave() : $this->edit_onSave($id);
		}
		
		public function formAfterEditSave($model, $session_key)
		{
			$model = $this->viewData['form_model'] = Cms_Page::create()->find($model->id);
			$model->updated_user_name = $this->currentUser->name;
			
			$this->renderMultiple(array(
				'form_flash'=>flash(),
				'page-summary'=>'@_page_summary'
			));
			
			return true;
		}
		
		protected function onShowActionDocument()
		{
			try
			{
				$module = post('module');
				$name = post('name');
				
				if (!preg_match('/^[a-z0-9_]+$/', $module) || !preg_match('/^[a-z0-9_]+$/', $name) || !strlen($module))
					throw new Phpr_ApplicationException('File not found.');

				$path = PATH_APP.'/modules/'.$module.'/docs/actions/'.$name.'.htm';

				if (!file_exists($path))
					throw new Phpr_ApplicationException('File not found.');
					
				$this->viewData['contents'] = file_get_contents($path);
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
			
			$this->renderPartial('action_document');
		}
		
		protected function onActionChanged()
		{
			$data = post('Cms_Page', array());

			$this->viewData['action'] = $data['action_reference'];
			$this->renderMultiple(array(
				'action_info_description'=>'@_action_description'
			));
		}

		public function content($page_id)
		{
			if (!$this->currentUser->get_permission('cms', array('manage_page_content', 'manage_static_pages')))
				Phpr::$response->redirect(url('/'));
			$this->form_edit_title = 'Edit Page Content';
			$this->edit($page_id, 'content');
			
			$this->filebrowser_onFileClick = null;
		}
		
		public function edit_formBeforeRender($model)
		{
			if (Phpr::$router->action == 'edit' && !$this->currentUser->get_permission('cms', array('manage_pages')))
				Phpr::$response->redirect(url('/'));
				
			if (Phpr::$router->action == 'content' && !$this->currentUser->get_permission('cms', array('manage_page_content', 'manage_static_pages')))
				Phpr::$response->redirect(url('/'));

			Cms_Template::auto_create_from_files();
		}
		
		protected function edit_onFixPageDirectory($id)
		{
			try
			{
				$obj = $this->formFindModelObject($id);
				
				$action = post('action');
				
				if ($action == 'create_new')
				{
					$obj->assign_directory_name(post('new_file_name'));
					Phpr::$response->redirect(url('cms/pages/edit/'.$obj->id));
				} elseif ($action == 'delete')
				{
					$obj->delete();
					Phpr::$session->flash['success'] = $this->form_edit_delete_flash;
					Phpr::$response->redirect(url('cms/pages/'));
				} elseif ($action == 'use_another_dir')
				{
					$obj->bind_to_directory(post('another_directory_name'));
					Phpr::$response->redirect(url('cms/pages/edit/'.$obj->id));
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function formFindModelObject($recordId)
		{
			if (!strlen($recordId))
				throw new Phpr_ApplicationException('Record not found');

			$obj = Cms_Page::create()->find($recordId);
			if (!$obj || !$obj->count())
				throw new Phpr_ApplicationException('Record not found');

			$obj->load_directory_content();

			$obj->define_form_fields($this->formGetContext());

			return $obj;
		}
		
		public function create_formBeforeRender()
		{
			if (!$this->currentUser->get_permission('cms', array('manage_pages', 'manage_static_pages')))
				Phpr::$response->redirect(url('/'));
				
			Cms_Template::auto_create_from_files();
		}

		public function reorder_pages()
		{
			$this->app_page_title = 'Manage Page Order';
			$this->setup_reorder_pages_list();
		}
		
		protected function setup_reorder_pages_list()
		{
			$this->list_record_url = null;
			$this->list_no_sorting = true;
			$this->list_no_pagination = true;
			$this->list_no_setup_link = true;
			$this->list_search_enabled = false;
			$this->list_custom_head_cells = PATH_APP.'/modules/cms/controllers/cms_pages/_pages_handle_head_col.htm';
			$this->list_custom_body_cells = PATH_APP.'/modules/cms/controllers/cms_pages/_pages_handle_body_col.htm';
		}
		
		protected function reorder_pages_onSetOrders()
		{
			try
			{
				Cms_Page::set_orders(post('item_ids'), post('sort_orders'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function formAfterCreateSave($page, $session_key)
		{
			if (post('create_close'))
			{
				$this->form_create_save_redirect = url('/cms/pages').'?'.uniqid();
			}
		}
		
		public function formBeforeCreateSave($model, $session_key)
		{
			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_edit_theme();
				if ($theme)
					$model->theme_id = $theme->id;
			}
		}
		
		public function create_from_files()
		{
			try
			{
				$this->app_page_title = 'Create Pages from Files';
				$this->viewData['directories'] = Cms_Page::list_orphan_directories();
				$this->viewData['model'] = Cms_PageFileImportModel::init();
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function create_from_files_onCreate()
		{
			try
			{
				$imported = Cms_PageFileImportModel::import(post('Cms_PageFileImportModel', array()));
				if ($imported > 1)
					Phpr::$session->flash['success'] = $imported.' pages have been successfully created.';
				else
					Phpr::$session->flash['success'] = 'The page has been successfully created';

				Phpr::$response->redirect(url('/cms/pages/'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		protected function index_onReloadPagesFromFiles()
		{
			try
			{
				Cms_SettingsManager::get()->copy_templates_to_db();
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		private function getPageObj($id)
		{
			return strlen($id) ? $this->formFindModelObject($id) : $this->formCreateModelObject();
		}

		/*
		 * Custom events
		 */
		
		protected function onCustomEvent($id=null)
		{
			$page = null;
			
			if (Phpr::$router->action == 'edit' || Phpr::$router->action == 'create')
				$page = $this->getPageObj($id);

			Backend::$events->fireEvent(post('custom_event_handler'), $this, $page);
		}
	}

?>