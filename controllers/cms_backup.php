<?

	class Cms_Backup extends Backend_Controller
	{
		protected $required_permissions = array('cms:manage_pages');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'cms';
			$this->app_module_name = 'CMS';
			$this->app_page = 'backup';
		}
		
		public function index()
		{
			$this->app_page_title = 'Import or Export CMS Objects';
			$this->app_page = 'backup';
		}
		
		/*
		 * Export
		 */
		
		public function export()
		{
			$this->app_page_title = 'Export CMS Objects';
		}
		
		protected function export_onExport()
		{
			try
			{
				$objects = post('objects', array());
				
				$checked_found = false;
				foreach ($objects as $value)
				{
					if ($value)
					{
						$checked_found = true;
						break;
					}
				}
				
				if (!$checked_found)
					throw new Phpr_ApplicationException('Please select at least one type of objects you want to export.');

				$file = Cms_ExportManager::create()->export($objects);
				Phpr::$response->redirect(url('/cms/backup/get/'.$file));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function get($name, $output_name = null)
		{
			try
			{
				$this->app_page_title = 'Download CMS Objects Archive';
				
				if (!preg_match('/^lca[0-9a-z]*$/i', $name))
					throw new Phpr_ApplicationException('File not found');

				$archivePath = PATH_APP.'/temp/'.$name;
				if (!file_exists($archivePath))
					throw new Phpr_ApplicationException('File not found');
					
				$output_name = $output_name ? $output_name : 'lemonstand_cms_objects.lca';

				header("Content-type: application/octet-stream");
				header('Content-Disposition: inline; filename="'.$output_name.'"');
				header('Cache-Control: no-store, no-cache, must-revalidate');
				header('Cache-Control: pre-check=0, post-check=0, max-age=0');
				header('Accept-Ranges: bytes');
				header('Content-Length: '.filesize($archivePath));
				header("Connection: close");

				Phpr_Files::readFile($archivePath);
				@unlink($archivePath);
			
				$this->suppressView();
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		/*
		 * Import
		 */
		
		public function import()
		{
			$this->app_page_title = 'Import CMS Objects';
			try
			{
				$this->viewData['complete'] = false;
				if (post('postback'))
				{
					try
					{
						Phpr_Files::validateUploadedFile($_FILES['file']);
						$fileInfo = $_FILES['file'];
						
						$pathInfo = pathinfo($fileInfo['name']);
						$ext = strtolower($pathInfo['extension']);
						if (!isset($pathInfo['extension']) || !($ext == 'lca' || $ext == 'zip'))
							throw new Phpr_ApplicationException('Uploaded file is not LemonStand CMS objects archive.');

						$exportMan = Cms_ExportManager::create();
						$exportMan->import($fileInfo);
						$this->viewData['complete'] = true;
						$this->viewData['exportMan'] = $exportMan;
					}
					catch (Exception $ex)
					{
						$this->viewData['form_error'] = $ex->getMessage();
					}
				}
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
	}

?>