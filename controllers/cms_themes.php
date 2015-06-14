<?

	class Cms_Themes extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior, Cms_ThemeSelector';
		public $list_model_class = 'Cms_Theme';
		public $list_no_data_message = 'No themes found.';
		public $list_record_url = null;
		
		public $form_preview_title = 'Theme';
		public $form_create_title = 'New Theme';
		public $form_edit_title = 'Edit Theme';
		public $form_model_class = 'Cms_Theme';
		public $form_not_found_message = 'Theme not found';
		public $form_redirect = null;
		public $form_edit_save_auto_timestamp = true;

		public $form_edit_save_flash = 'The theme has been successfully saved';
		public $form_create_save_flash = 'The theme has been successfully added';
		public $form_edit_delete_flash = 'The theme has been successfully deleted';

		protected $required_permissions = array('cms:manage_pages');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'cms';
			$this->app_module_name = 'CMS';

			$this->list_record_url = url('/cms/themes/edit/');
			$this->form_redirect = url('/cms/themes');
			$this->form_create_save_redirect = url('/cms/themes/edit/%s/'.uniqid());
			$this->app_page = 'themes';
		}
		
		public function index()
		{
			$this->app_page_title = 'Themes';
		}
		
		protected function index_onRefresh()
		{
			$this->renderPartial('themes_page_content');
		}
		
		public function listGetRowClass($model)
		{
			if ($model->is_default)
				return 'important';
				
			if (!$model->is_enabled)
				return 'deleted';
		}
		
		/*
		 * Activating
		 */
		
		protected function index_onshow_set_default_theme_form()
		{
			try
			{
				$ids = post('list_ids', array());
				$this->viewData['theme_id'] = count($ids) ? $ids[0] : null;

				$this->viewData['themes'] = Cms_Theme::create()->where('(is_default is null or is_default = 0)')->order('name')->find_all();
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('set_default_theme_form');
		}
		
		protected function index_onset_default_theme()
		{
			try
			{
				$theme_id = post('theme_id');
				
				if (!$theme_id)
					throw new Phpr_ApplicationException("Please select a default theme.");
					
				$theme = Cms_Theme::create()->find($theme_id);
				if (!$theme)
					throw new Phpr_ApplicationException("Theme not found.");

				$theme->make_default();
				Phpr::$session->flash['success'] = sprintf('Theme "%s" is now the default theme.', h($theme->name));
				$this->renderPartial('themes_page_content');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Deleting
		 */
		
		protected function index_ondelete_selected()
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
					$item = Cms_Theme::create()->find($item_id);
					if (!$item)
						throw new Phpr_ApplicationException('Theme with identifier '.$item_id.' not found.');

					$item->delete();
					$items_deleted++;
					$items_processed++;
				}
				catch (Exception $ex)
				{
					if (!$item)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error deleting theme "'.$item->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($items_processed)
			{
				$message = null;
				
				if ($items_deleted)
					$message = 'Themes deleted: '.$items_deleted;

				Phpr::$session->flash['success'] = $message;
			}

			$this->renderPartial('themes_page_content');
		}
		
		/*
		 * Enabling/disabling
		 */
		
		protected function index_onenable_selected()
		{
			$items_processed = 0;
			$items_enabled = 0;

			$item_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $item_ids;

			foreach ($item_ids as $item_id)
			{
				$item = null;
				try
				{
					$item = Cms_Theme::create()->find($item_id);
					if (!$item)
						throw new Phpr_ApplicationException('Theme with identifier '.$item_id.' not found.');

					$item->enable_theme();
					$items_enabled++;
					$items_processed++;
				}
				catch (Exception $ex)
				{
					if (!$item)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error enabling theme "'.$item->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($items_processed)
			{
				$message = null;
				
				if ($items_enabled)
					$message = 'Themes enabled: '.$items_enabled;

				Phpr::$session->flash['success'] = $message;
			}

			$this->renderPartial('themes_page_content');
		}
		
		protected function index_ondisable_selected()
		{
			$items_processed = 0;
			$items_disabled = 0;

			$item_ids = post('list_ids', array());
			$this->viewData['list_checked_records'] = $item_ids;

			foreach ($item_ids as $item_id)
			{
				$item = null;
				try
				{
					$item = Cms_Theme::create()->find($item_id);
					if (!$item)
						throw new Phpr_ApplicationException('Theme with identifier '.$item_id.' not found.');

					$item->disable_theme();
					$items_disabled++;
					$items_processed++;
				}
				catch (Exception $ex)
				{
					if (!$item)
						Phpr::$session->flash['error'] = $ex->getMessage();
					else
						Phpr::$session->flash['error'] = 'Error disabling theme "'.$item->name.'": '.$ex->getMessage();

					break;
				}
			}

			if ($items_processed)
			{
				$message = null;
				
				if ($items_disabled)
					$message = 'Themes disabled: '.$items_disabled;

				Phpr::$session->flash['success'] = $message;
			}

			$this->renderPartial('themes_page_content');
		}
		
		/*
		 * Duplicating
		 */
		
		protected function index_onshow_duplicate_theme_form()
		{
			try
			{
				$ids = post('list_ids', array());
				if (count($ids) != 1)
					throw new Phpr_ApplicationException('Please select a theme to duplicate.');

				$existing_theme = $this->viewData['existing_theme'] = Cms_Theme::create()->where('id=?', $ids[0])->find();
				if (!$existing_theme)
					throw new Phpr_ApplicationException('Theme not found.');
					
				$theme = $this->viewData['theme'] = Cms_Theme::create();
				$existing_theme->init_copy($theme);
				$theme->define_form_fields('duplicate');
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('duplicate_theme_form');
		}
		
		protected function index_onduplicate_theme()
		{
			try
			{
				$theme_id = post('theme_id');
				
				if (!$theme_id)
					throw new Phpr_ApplicationException("Original theme not found.");
					
				$theme = Cms_Theme::create()->find($theme_id);
				if (!$theme)
					throw new Phpr_ApplicationException("Original theme not found.");

				$theme->duplicate_theme(post('Cms_Theme', array()));

				Phpr::$session->flash['success'] = 'Theme has been successfully duplicated.';
				$this->renderPartial('themes_page_content');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Export
		 */
		
		protected function index_onshow_export_theme_form()
		{
			try
			{
				$ids = post('list_ids', array());

				$model = new Cms_ThemeExportModel();
				
				$model->theme_id = count($ids) ? $ids[0] : null;
				$model->define_form_fields();
				$this->viewData['model'] = $model;
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('export_theme_form');
		}

		protected function index_onexport_theme()
		{
			try
			{
				$model = new Cms_ThemeExportModel();
				$file = $model->export(post('Cms_ThemeExportModel', array()));
				
				$theme = Cms_Theme::create()->find($model->theme_id);
				if (!$theme)
					throw new Phpr_ApplicationException("Theme not found.");
				
				Phpr::$response->redirect(url('/cms/backup/get/'.$file.'/'.$theme->code.'.lca'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Import
		 */
		
		protected function index_onshow_import_theme_form()
		{
			try
			{
				$model = new Cms_ThemeImportModel();
				
				$model->define_form_fields();
				$this->viewData['model'] = $model;
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('import_theme_form');
		}
		
		protected function index_onimport_theme()
		{
			try
			{
				$model = new Cms_ThemeImportModel();
				$import_manager = $model->import(post('Cms_ThemeImportModel', array()), $this->formGetEditSessionKey());

				$message = 'Theme has been successfully imported. Pages: new - %s, updated - %s. Partials: new - %s, updated - %s. Layouts: new - %s, updated - %s. Global content blocks: new - %s.';
				$message = sprintf($message, 
					$import_manager->import_new_pages, 
					$import_manager->import_updated_pages,
					$import_manager->import_new_partials, 
					$import_manager->import_updated_partials, 
					$import_manager->import_new_templates, 
					$import_manager->import_updated_templates,
					$import_manager->import_new_global_content_blocks
				);
				
				Phpr::$session->flash['success'] = $message;
				
				$this->renderPartial('themes_page_content');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Enable Theming feature
		 */
		
		public function enable()
		{
			$this->app_module = 'system';
			$this->app_tab = 'system';
			$this->app_module_name = 'System';
			$this->app_page = 'settings';
			$this->app_page_title = 'Enable Theming';

			$theme = Cms_Theme::create();
			$theme->define_form_fields('enable');
			$theme->name = "Default";
			$theme->code = "default";
			$this->viewData['theme'] = $theme;
		}
		
		public function enable_onApply()
		{
			try
			{
				$theme = Cms_Theme::create()->enable_theming(post('Cms_Theme', array()));
				$this->viewData['theme'] = $theme;
				$this->renderPartial('enable_step_2');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>