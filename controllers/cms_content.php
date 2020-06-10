<?

	class Cms_Content extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Backend_FileBrowser';
		public $list_model_class = 'Cms_GlobalContentBlock';
		public $list_record_url = null;
		
		public $form_preview_title = 'Global Content Block';
		public $form_create_title = 'New Global Content Block';
		public $form_edit_title = 'Edit Global Content Block';
		public $form_model_class = 'Cms_GlobalContentBlock';
		public $form_not_found_message = 'Global content block not found';
		public $form_redirect = null;
		public $form_create_save_redirect = null;
		public $form_flash_id = 'form_flash';
		
		public $form_edit_save_flash = 'The content block has been successfully saved';
		public $form_create_save_flash = 'The content block has been successfully added';
		public $form_edit_delete_flash = 'The content block has been successfully deleted';
		public $form_edit_save_auto_timestamp = true;
		
		public $list_search_enabled = true;
		public $list_search_fields = array('name', 'code', 'content');
		public $list_search_prompt = 'find blocks by name, code or content';

		public $filebrowser_onFileClick = null;
		public $file_browser_file_list_class = 'ui-layout-anchor-window-bottom offset-24';
		public $enable_concurrency_locking = true;
		
		public $list_custom_body_cells = null;
		public $list_custom_head_cells = null;

		protected $globalHandlers = array('onSave');

		public $filebrowser_dirs = array();

		protected $required_permissions = array('cms:manage_content');

		public function __construct()
		{
			$resources_path = Cms_SettingsManager::get()->resources_dir_path;
			$this->filebrowser_dirs[$resources_path] = array('path'=>'/'.$resources_path, 'root_upload'=>true, 'title'=>'Website resources directory');

			parent::__construct();
			$this->app_tab = 'cms';
			$this->app_module_name = 'CMS';

			$this->list_record_url = url('/cms/content/edit/');
			$this->form_redirect = url('/cms/content');
			$this->form_create_save_redirect = url('/cms/content/edit/%s/'.uniqid());
			$this->app_page = 'content';
			
			if ($this->currentUser && $this->currentUser->isAdministrator())
			{
				$this->list_custom_body_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_body_cb.htm';
				$this->list_custom_head_cells = PATH_APP.'/phproad/modules/db/behaviors/db_listbehavior/partials/_list_head_cb.htm';
			}
		}
		
		public function index()
		{
			$this->app_page_title = 'Content';
		}

		protected function onSave($id)
		{
			Phpr::$router->action == 'create' ? $this->create_onSave() : $this->edit_onSave($id);
		}
		
		public function create_formBeforeRender($model)
		{

			if (Phpr::$router->param('param1') == 'text')
				$model->block_type = 'text';
			else
				$model->block_type = 'html';
		}
		
		public function formBeforeCreateSave($model, $session_key)
		{
			if (Phpr::$router->param('param1') == 'text')
				$model->block_type = 'text';
			else
				$model->block_type = 'html';
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
					$item = Cms_GlobalContentBlock::create()->find($item_id);
					if (!$item)
						throw new Phpr_ApplicationException('Block with identifier '.$item_id.' not found.');

					$item->delete();
					$items_deleted++;
					$items_processed++;
				}
				catch (Exception $ex)
				{
					if (!$item)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting global content block "'.$item->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($items_processed)
			{
				$message = null;
				
				if ($items_deleted)
					$message = 'Blocks deleted: '.$items_deleted;

				Phpr::$session->flash['success'] = $message;
			}

			$this->renderPartial('content_page_content');
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
				$this->form_create_save_redirect = url('/cms/content').'?'.uniqid();
			}
		}
	}

?>