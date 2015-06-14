<?

	class Cms_Partials extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Backend_FileBrowser, Cms_ThemeSelector';
		public $list_model_class = 'Cms_Partial';
		public $list_record_url = null;
		
		public $form_preview_title = 'Partial';
		public $form_create_title = 'New Partial';
		public $form_edit_title = 'Edit Partial';
		public $form_model_class = 'Cms_Partial';
		public $form_not_found_message = 'Partial not found';
		public $form_redirect = null;
		public $form_create_save_redirect = null;
		public $form_flash_id = 'form_flash';
		
		public $form_edit_save_flash = 'The partial has been successfully saved';
		public $form_create_save_flash = 'The partial has been successfully added';
		public $form_edit_delete_flash = 'The partial has been successfully deleted';
		public $form_edit_save_auto_timestamp = true;
		
		public $list_search_enabled = true;
		public $list_search_fields = array('name', 'html_code');
		public $list_search_prompt = 'find partials by name or content';

		public $filebrowser_onFileClick = "return onFileBrowserFileClick('%s');";
		public $enable_concurrency_locking = true;

		protected $globalHandlers = array('onSave');

		public $filebrowser_dirs = array();
		public $filebrowser_default_dirs = true;
		public $file_browser_file_list_class = 'ui-layout-anchor-window-bottom offset-24';

		protected $required_permissions = array('cms:manage_pages');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'cms';
			$this->app_module_name = 'CMS';

			$this->list_record_url = url('/cms/partials/edit/');
			$this->form_redirect = url('/cms/partials');
			$this->form_create_save_redirect = url('/cms/partials/edit/%s/'.uniqid());
			$this->app_page = 'partials';
		}
		
		public function index()
		{
			$this->app_page_title = 'Partials';
			
			Cms_Partial::auto_create_from_files();
			Shop_PaymentMethod::create_partials();
		}
		
		protected function index_onRefresh()
		{
			Cms_Partial::auto_create_from_files();
			Shop_PaymentMethod::create_partials();

			$this->renderPartial('partials_page_content');
		}
		
		protected function onSave($id)
		{
			Phpr::$router->action == 'create' ? $this->create_onSave() : $this->edit_onSave($id);
		}
		
		public function listGetRowClass($model)
		{
			if (!Cms_SettingsManager::get()->enable_filebased_templates || !($model instanceof Cms_Partial))
				return null;
				
			if ($model->file_is_missing())
				return 'error';
		}
		
		public function listPrepareData()
		{
			$updated_data = Backend::$events->fireEvent('cms:onPreparePartialListData', $this);
			foreach ($updated_data as $updated)
			{
				if ($updated)
					return $updated;
			}
			
			$obj = Cms_Partial::create();
			
			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_edit_theme();
				if ($theme)
					$obj->where('theme_id=?', $theme->id);
			}
			
			return $obj;
		}
		
		protected function index_onDeleteSelected()
		{
			$items_processed = 0;
			$items_deleted = 0;

			$item_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $item_ids;

			foreach ($item_ids as $item_id)
			{
				$item = null;
				try
				{
					$item = Cms_Partial::create()->find($item_id);
					if (!$item)
						throw new Phpr_ApplicationException('Partial with identifier '.$item_id.' not found.');

					$item->delete();
					$items_deleted++;
					$items_processed++;
				}
				catch (Exception $ex)
				{
					if (!$item)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting partial "'.$partial->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($items_processed)
			{
				$message = null;
				
				if ($items_deleted)
					$message = 'Partials deleted: '.$items_deleted;

				Phpr::$session->flash['success'] = $message;
			}

			$this->renderPartial('partials_page_content');
		}
		
		public function formAfterEditSave($model, $session_key)
		{
			$this->viewData['form_model'] = $model;
			$model->updated_user_name = $this->currentUser->name;
			
			$this->renderMultiple(array(
				'form_flash'=>flash(),
				'object-summary'=>'@_object_summary'
			));
			
			return true;
		}
		
		public function formAfterCreateSave($page, $session_key)
		{
			if (post('create_close'))
			{
				$this->form_create_save_redirect = url('/cms/partials').'?'.uniqid();
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
		
		public function edit_formBeforeRender($model)
		{
			$model->load_file_content();
		}
		
		protected function edit_onFixPartialFile($id)
		{
			try
			{
				$obj = $this->formFindModelObject($id);
				
				$action = post('action');
				
				if ($action == 'create_new')
				{
					$obj->assign_file_name(post('new_file_name'));
					Phpr::$response->redirect(url('cms/partials/edit/'.$obj->id));
				} elseif ($action == 'delete')
				{
					$obj->delete();
					Phpr::$session->flash['success'] = $this->form_edit_delete_flash;
					Phpr::$response->redirect(url('cms/partials/'));
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>