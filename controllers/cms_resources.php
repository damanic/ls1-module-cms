<?

	/**
	 * CMS resource manager controller.
	 * @has_documentable_methods
	 */
	class Cms_Resources extends Backend_Controller
	{
		public $implement = 'Cms_ThemeSelector';
		public $app_tab = 'cms';
		public $app_page = 'resources';
		public $app_module_name = 'CMS';
		
		protected $currentFolder = null;
		protected $public_actions = array('file_upload');

		protected $required_permissions = array('cms:manage_resources');
		protected static $ignore_files = array('.DS_Store', '.gitignore');
		
		const resources_root_folder_code  = 'resources-root-folder';
		const theme_resources_root_folder_code = 'theme-resources-root-folder';
		
		public function __construct()
		{
			parent::__construct();
		}
		
		public function index()
		{
			$this->app_page_title = 'Resources';
			$this->viewData['body_class'] = 'resource_manager';
			try
			{
				$dir = $this->getResourcesRootDir(false);
				
				if (!file_exists($dir) || is_file($dir))
					throw new Phpr_ApplicationException(sprintf('Directory "%s" does not exist.', $dir));
			} 
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function index_onRefresh()
		{
			$this->renderPartial('resources_page_content');
		}
		
		/*
		 * Navigation
		 */
		
		protected function index_onNavigate($dir = null)
		{
			$dir = $dir ? $dir : post('dir');
			
			if (!strlen($dir))
				$dir = Phpr::$session->get('cms_cur_resource_folder');
			else
				$dir = PATH_APP.$dir;

			Phpr::$session->set('cms_cur_resource_folder', $this->currentFolder = $dir);

			$this->renderMultiple(array(
				'folder_list'=>'@_folder_list',
				'file_list'=>'@_file_list'
			));
		}
		
		protected function index_onToggleFolder()
		{
			$dir = post('dir');
			$expanded_folders = Db_UserParameters::get('resource-manager-expanded-folders', null, array());
			if (post('status'))
			{
				if (array_key_exists($dir, $expanded_folders))
					unset($expanded_folders[$dir]);
			} else
				$expanded_folders[$dir] = 1;

			Db_UserParameters::set('resource-manager-expanded-folders', $expanded_folders, null);
			
			$this->renderMultiple(array(
				'folder_list'=>'@_folder_list'
			));
		}

		/*
		 * Deleting files
		 */

		protected function index_onDeleteFile()
		{
			try
			{
				if (@unlink(PATH_APP.post('file')))
					Phpr::$session->flash['success'] = 'File '.post('file').' has been successfully deleted.';
					
				$this->deleteThumbs(post('file'));
			} catch (Exception $ex)
			{
				Phpr::$session->flash['error'] = 'Error deleting file: '.$ex->getMessage();
			}
			
			$dir = dirname(post('file'));
			$this->index_onNavigate($dir);
		}
		
		protected function index_onDeleteSelected()
		{
			try
			{
				$file = null;
				$deletedNum = 0;
				$files = post('file_names', array());
				Phpr::$session->flash['system'] = $files;
				
				foreach ($files as $file)
				{
					$filePath = PATH_APP.$file;
					@unlink($filePath);
					$this->deleteThumbs($file);
					$deletedNum++;
				}

				Phpr::$session->flash['success'] = "$deletedNum file(s) have been successfully deleted.";
			}
			catch (Exception $ex)
			{
				Phpr::$session->flash['error'] = 'Error deleting file: '.$ex->getMessage();
			}

			$dir = dirname($file);
				$this->index_onNavigate($dir);
		}

		/*
		 * Uploading
		 */

		protected function index_onShowUploadForm()
		{
			$this->viewData['file_path'] = post('dir');
			$this->renderPartial('upload_form');
		}
		
		public function file_upload($ticket, $dir = null)
		{
			$this->suppressView();

			$result = array();
			try
			{
				$dir = base64_decode($dir);

				if (!Phpr::$security->validateTicket($ticket, true))
					throw new Phpr_ApplicationException('Authorization error.');

				if (!array_key_exists('file', $_FILES))
					throw new Phpr_ApplicationException('File was not uploaded.');
					
				$destPath = PATH_APP.$dir.'/'.$_FILES['file']['name'];
				if (file_exists($destPath) && !post('override_files'))
					throw new Phpr_ApplicationException('File already exists.');

				if ( !@move_uploaded_file($_FILES['file']['tmp_name'], $destPath) )
					throw new Phpr_SystemException( "Error copying file to $destPath." );

				@chmod($destPath, Phpr_Files::getFilePermissions());
				
				Backend::$events->fireEvent('cms:onAfterResourceFileUpload', $destPath);
				$result['result'] = 'success';
			}
			catch (Exception $ex)
			{
				$result['result'] = 'failed';
				$result['error'] = $ex->getMessage();
			}
			
			header('Content-type: application/json');
			echo json_encode($result);
		}

		/*
		 * Renaming
		 */

		protected function index_onShowRenameForm()
		{
			try
			{
				$file = post('file');
				if (!file_exists(PATH_APP.$file))
					throw new Phpr_ApplicationException("File $file not found");

				$this->viewData['file_name'] = basename(post('file'));
				$this->viewData['file_path'] = post('file');
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('rename_form');
		}
		
		protected function index_onRenameFile()
		{
			try
			{
				$this->validation->add('file_name', 'File Name')->fn('trim')
					->required("Please specify the file name")
					->regexp('/^[0-9a-z\.\-_]*$/i', "File name contains invalid character. Allowed characters are Latin letters, numbers and following characters: '.', '-', '_'.");
				if (!$this->validation->validate($_POST))
					$this->validation->throwException();

				$file_path = post('file_path');
				if (!file_exists(PATH_APP.post('file_path')))
					throw new Phpr_ApplicationException("File {$file_path} not found.");

				$newFileName = $this->validation->fieldValues['file_name'];
				$dir = dirname(post('file_path'));
				$newFilePath = $dir.'/'.$newFileName;
				if (file_exists(PATH_APP.$newFilePath))
					$this->validation->setError( "File $newFilePath already exists.", 'file_name', 'true' );

				$newFilePath = PATH_APP.$newFilePath;
				if (is_dir($newFilePath))
					$this->validation->setError( "$newFilePath is a directory.", 'file_name', 'true' );

				try
				{
					@rename(PATH_APP.post('file_path'), $newFilePath);
				} catch (Exception $ex)
				{
					throw  new Phpr_ApplicationException('Error renaming file: '.$ex->getMessage());
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onFileRenamed()
		{
			Phpr::$session->flash['success'] = 'File '.post('file').' has been successfully renamed.';
			$this->index_onNavigate(dirname(post('file')));
		}

		/*
		 * Editing
		 */

		public function edit($file_path)
		{
			$this->app_page_title = 'Edit File';
			try
			{
				$file_path = base64_decode($file_path);
				$full_path = PATH_APP.$file_path;
				if (!file_exists($full_path) || !is_file($full_path))
					throw new Phpr_ApplicationException('File not found.');

				$this->viewData['file_path'] = $file_path;
				$this->viewData['file_contents'] = file_get_contents($full_path);

				$pathInfo = pathinfo($file_path);
				$ext = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : null;
				
				if ($ext == 'htm')
					$ext = 'html';
					
				if ($ext == 'js')
					$ext = 'javascript';

				if ($ext == 'md')
					$ext = 'markdown';

				if ($ext == 'rb')
					$ext = 'ruby';
					
				$known_extensions = array('css', 'html', 'javascript', 'json', 'markdown', 'php', 'ruby', 'xml');
				if (!in_array($ext, $known_extensions))
					$ext = null;
			
				$this->viewData['ext'] = $ext;
			} 
				catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function edit_onSave($file_path)
		{
			try
			{
				$file_path_str = base64_decode($file_path);
				
				if ($lock = Db_RecordLock::lock_exists(md5($file_path_str)))
					throw new Phpr_ApplicationException(sprintf('User %s is editing this file. The edit session started %s. You cannot save changes.', $lock->created_user_name, $lock->get_age_str()));

				@file_put_contents(PATH_APP.$file_path_str, post('file_content'));
				Phpr::$session->flash['success'] = 'File has been successfully saved';
				
				if (post('redirect', 1))
				{
					$dir = PATH_APP.dirname($file_path_str);
					Phpr::$session->set('cms_cur_resource_folder', $this->currentFolder = $dir);
					
					if (!Db_RecordLock::lock_exists(md5($file_path_str)))
						Db_RecordLock::unlock_record(md5($file_path_str));

					Phpr::$response->redirect(url('cms/resources'));
				}
				{
					Phpr::$session->flash['success'] .= ' at '.Phpr_Date::display(Phpr_DateTime::now(), '%X');
					$this->renderPartial('flash');
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function edit_onCancel($file_path)
		{
			$file_path_str = base64_decode($file_path);

			if (!Db_RecordLock::lock_exists(md5($file_path_str)))
				Db_RecordLock::unlock_record(md5($file_path_str));

			Phpr::$response->redirect(url('cms/resources'));
		}

		/*
		 * Creating files
		 */

		protected function index_onShowNewFileForm()
		{
			$this->viewData['dir'] = post('dir');
			$this->renderPartial('new_file_form');
		}
		
		protected function index_onNewFile()
		{
			try
			{
				$this->validation->add('file_name', 'File Name')->fn('trim')
					->required("Please specify the file name")
					->regexp('/^[0-9a-z\.\-_]*$/i', "File name contains invalid character. Allowed characters are Latin letters, numbers and following characters: '.', '-', '_'.");
				if (!$this->validation->validate($_POST))
					$this->validation->throwException();

				$newFileName = $this->validation->fieldValues['file_name'];
				if (!$this->isEditable($newFileName))
					throw new Phpr_ApplicationException("You may create only editable file types: ".implode(', ', $this->getEditableTypes()).'.');

				$file_path = post('file_path');
				if (!file_exists(PATH_APP.post('file_path')))
					throw new Phpr_ApplicationException("Directory {$file_path} not found.");

				$newFilePath = $file_path.'/'.$newFileName;
				if (file_exists(PATH_APP.$newFilePath))
					$this->validation->setError( "File $newFilePath already exists.", 'file_name', 'true' );

				try
				{
					@file_put_contents(PATH_APP.$newFilePath, '');
					@chmod(PATH_APP.$newFilePath, Phpr_Files::getFilePermissions());
				} catch (Exception $ex)
				{
					throw  new Phpr_ApplicationException('Error creating file: '.$ex->getMessage());
				}
				
				$url = url('cms/resources/edit/'.base64_encode($newFilePath));
				Phpr::$response->redirect($url);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/*
		 * Moving files
		 */

		protected function index_onShowMoveForm()
		{
			try
			{
				$this->viewData['dir'] = post('dir');

				$files = post('file_names', array());
				if (!count($files))
					throw new Phpr_ApplicationException('Please select file(s) to move.');

				$dir = dirname($files[0]);

				$this->viewData['files'] = $files;
				$this->viewData['dir'] = $dir;
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('move_file_form');
		}

		protected function index_onMoveFile()
		{
			try
			{
				$destDir = post('dest_dir');
				$srcDir = post('dir');
				
				$resources_root_dir = '/'.$this->getResourcesRootDir();
				
				$theming_enabled = Cms_Theme::is_theming_enabled();
				if (strpos($destDir, $resources_root_dir) !== 0)
				{
					if ($theming_enabled)
					{
						$theme_root_dir = '/'.$this->getThemeResourcesRootDir();
						if (strpos($destDir, $theme_root_dir) !== 0)
							throw new Phpr_ApplicationException("Invalid destination directory.");
					} else
						throw new Phpr_ApplicationException("Invalid destination directory.");
				}
				
				if ($destDir == $srcDir)
					throw new Phpr_ApplicationException("Destination directory matches the source directory. Please select another directory.");

				$files = explode(',', post('files'));
				foreach ($files as $file)
				{
					$srcPath = PATH_APP.$file;
					$destPath = PATH_APP.$destDir.'/'.basename($file);

					if (file_exists($destPath))
						throw new Phpr_ApplicationException("File $file already exists.");

					try
					{
						@rename($srcPath, $destPath);
						@chmod($destPath, Phpr_Files::getFilePermissions());
					} catch (Exception $ex)
					{
						throw  new Phpr_ApplicationException('Error moving file: '.$ex->getMessage());
					}
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onFilesMoved()
		{
			$files = explode(',', post('files'));
			
			Phpr::$session->flash['success'] = count($files).' file(s) have been successfully moved to '.post('dir');
			$this->index_onNavigate(post('src_dir'));
		}
		
		protected function index_onCancelMove()
		{
			$files = explode(',', post('files'));
			Phpr::$session->flash['system'] = $files;

			$this->index_onNavigate(post('dir'));
		}

		/*
		 * View settings
		 */

		protected function index_onSetViewSettings()
		{
			try
			{
				$settings = Db_UserParameters::get('cms_folders_view_settings', null, array());
				$settings[post('dir')] = post('view_type');
				Db_UserParameters::set('cms_folders_view_settings', $settings);
				
				$this->index_onNavigate(post('dir'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/*
		 * Folders
		 */
		
		protected function index_onShowNewFoldeForm()
		{
			$this->viewData['dir'] = post('dir');
			$this->renderPartial('new_folder_form');
		}
		
		protected function index_onNewFolder()
		{
			try
			{
				$dir = post('dir');

				$this->validation->add('folder_name', 'Folder Name')->fn('trim')
					->required("Please specify a folder name")
					->regexp('/^[0-9a-z\.\-_]*$/i', "Folder name contains invalid character. Allowed characters are Latin letters, numbers and following characters: '.', '-', '_'.");
				if (!$this->validation->validate($_POST))
					$this->validation->throwException();

				$newFolderName = $this->validation->fieldValues['folder_name'];

				$file_path = post('dir');
				if (!file_exists(PATH_APP.$file_path))
					throw new Phpr_ApplicationException("Directory {$file_path} not found.");

				$newFolderPath = $file_path.'/'.$newFolderName;
				if (file_exists(PATH_APP.$newFolderPath))
					$this->validation->setError( "Folder $newFolderPath already exists.", 'folder_name', 'true' );

				try
				{
					@mkdir(PATH_APP.$newFolderPath);
					@chmod(PATH_APP.$newFolderPath, Phpr_Files::getFolderPermissions());
				} catch (Exception $ex)
				{
					throw  new Phpr_ApplicationException('Error creating folder: '.$ex->getMessage());
				}
				
				Phpr::$session->set('cms_cur_resource_folder', $this->currentFolder = PATH_APP.$newFolderPath);
				
				$expanded_folders = Db_UserParameters::get('resource-manager-expanded-folders', null, array());
				$expand_folder = dirname($newFolderPath);

				$resources_root_dir = '/'.$this->getResourcesRootDir();
				
				$theming_enabled = Cms_Theme::is_theming_enabled();
				if ($theming_enabled)
					$theme_resources_root_dir = '/'.$this->getThemeResourcesRootDir();

				if ($expand_folder == $resources_root_dir)
					$expanded_folders[Cms_Resources::resources_root_folder_code] = 1;
				elseif ($theming_enabled && $expand_folder == $theme_resources_root_dir)
					$expanded_folders[Cms_Resources::theme_resources_root_folder_code] = 1;
				else
					$expanded_folders[$expand_folder] = 1;

				Db_UserParameters::set('resource-manager-expanded-folders', $expanded_folders, null);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onShowRenameFolderForm()
		{
			try
			{
				$dir = post('dir');
				
				if ($dir == '/'.$this->getResourcesRootDir(true))
					throw new Phpr_ApplicationException('We are sorry, the root resources directory cannot be renamed or deleted.');

				if (Cms_Theme::is_theming_enabled())
				{
					if ($dir == '/'.$this->getThemeResourcesRootDir(true))
						throw new Phpr_ApplicationException('We are sorry, the root theme resources directory cannot be renamed or deleted.');
				}
				
				$this->viewData['dir'] = $dir;
				$this->viewData['folder_name'] = basename($dir);
				
				
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('rename_folder_form');
		}
		
		protected function index_onShowRenameFoldeForm() { // deprecated
			return $this->index_onShowRenameFolderForm();
		}
		
		protected function index_onRenameFolder()
		{
			try
			{
				$dir = post('dir');

				$this->validation->add('folder_name', 'Folder Name')->fn('trim')
					->required("Please specify a folder name")
					->regexp('/^[0-9a-z\.\-_]*$/i', "Folder name contains invalid character. Allowed characters are Latin letters, numbers and following characters: '.', '-', '_'.");
				if (!$this->validation->validate($_POST))
					$this->validation->throwException();

				$newFolderName = $this->validation->fieldValues['folder_name'];
				
				if ($dir == '/'.$this->getResourcesRootDir(true))
					throw new Phpr_ApplicationException('We are sorry, root resources directory cannot be renamed or deleted.');
					
				if (Cms_Theme::is_theming_enabled())
				{
					if ($dir == '/'.$this->getThemeResourcesRootDir(true))
						throw new Phpr_ApplicationException('We are sorry, root theme resources directory cannot be renamed or deleted.');
				}

				$file_path = dirname(post('dir'));
				if (!file_exists(PATH_APP.$file_path))
					throw new Phpr_ApplicationException("Directory {$file_path} not found.");

				$newFolderPath = $file_path.'/'.$newFolderName;
				if (file_exists(PATH_APP.$newFolderPath))
				{
					throw new Phpr_ApplicationException("Folder $newFolderPath already exists.");
				}

				try
				{
					@rename(PATH_APP.post('dir'), PATH_APP.$newFolderPath);
				} catch (Exception $ex)
				{
					throw  new Phpr_ApplicationException('Error renaming folder: '.$ex->getMessage());
				}
				
				Phpr::$session->set('cms_cur_resource_folder', $this->currentFolder = PATH_APP.$newFolderPath);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function index_onShowDeleteFoldeForm()
		{
			try
			{
				$dir = post('dir');
				
				if ($dir == '/'.$this->getResourcesRootDir(true))
					throw new Phpr_ApplicationException('We are sorry, root resources directory cannot be renamed or deleted.');

				if (Cms_Theme::is_theming_enabled())
				{
					if ($dir == '/'.$this->getThemeResourcesRootDir(true))
						throw new Phpr_ApplicationException('We are sorry, root theme resources directory cannot be renamed or deleted.');
				}
					
				$files = glob(PATH_APP.$dir.'/*');
				if ($files)
					throw new Phpr_ApplicationException('Directory is not empty. Please delete all files and folders from the directory.');

				$this->viewData['dir'] = $dir;
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}

			$this->renderPartial('delete_folder_form');
		}
		
		protected function index_onDeleteFolder()
		{
			try
			{
				$dir = post('dir');
				if ($dir == '/'.$this->getResourcesRootDir(true))
					throw new Phpr_ApplicationException('We are sorry, root resources directory cannot be renamed or deleted.');

				if (Cms_Theme::is_theming_enabled())
				{
					if ($dir == '/'.$this->getThemeResourcesRootDir(true))
						throw new Phpr_ApplicationException('We are sorry, root theme resources directory cannot be renamed or deleted.');
				}

				$files = glob(PATH_APP.$dir.'/*');
				if (is_array($files) && count($files))
					throw new Phpr_ApplicationException('Directory is not empty. Please delete all files and folders from the directory.');

				try
				{
					@rmdir(PATH_APP.$dir);
				} catch (Exception $ex)
				{
					throw  new Phpr_ApplicationException('Error deleting folder: '.$ex->getMessage());
				}
				
				Phpr::$session->set('cms_cur_resource_folder', $this->currentFolder = PATH_APP.dirname($dir));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		/*
		 * Misc functions
		 */
		
		protected function getResourcesRootDir($relative = true)
		{
			$result = Cms_SettingsManager::get()->resources_dir_path;
			if  (!$relative)
				$result = PATH_APP.'/'.$result;
				
			return $result;
		}
		
		protected function getThemeResourcesRootDir($relative = true)
		{
			$theme = Cms_Theme::get_edit_theme();
			$result = 'themes/'.$theme->code.'/resources';
			
			if  (!$relative)
				$result = PATH_APP.'/'.$result;
				
			return $result;
		}
		
		protected function getCurrentFolder()
		{
			if (strlen($this->currentFolder) && file_exists($this->currentFolder) && is_dir($this->currentFolder))
				return $this->currentFolder;

			$resources_root_dir = PATH_APP.'/'.$this->getResourcesRootDir();
			
			$theming_enabled = Cms_Theme::is_theming_enabled();
			$theme_root_dir = $theming_enabled ? PATH_APP.'/'.$this->getThemeResourcesRootDir() : $resources_root_dir;

			$currentFolder = Phpr::$session->get('cms_cur_resource_folder');

			if (file_exists($currentFolder) && is_dir($currentFolder) && (strpos($currentFolder, $resources_root_dir) === 0 || strpos($currentFolder, $theme_root_dir) === 0))
				return $this->currentFolder = $currentFolder;
				
			$dir = $this->getResourcesRootDir(false);
			
			return $this->currentFolder = $dir;
		}
		
		protected function getAppRelativeFolder($folder)
		{
			if (substr($folder, 0, strlen(PATH_APP)) == PATH_APP)
				return '/'.substr($folder, strlen(PATH_APP)+1);
			
			return $folder;
		}

		protected function getViewType($path)
		{
			$settings = Db_UserParameters::get('cms_folders_view_settings', null, array());
			if (!array_key_exists($path, $settings))
				return 'table';
			
			return $settings[$path];
		}
		
		protected function getFolderContents()
		{
			$folder = $this->getCurrentFolder();
			if (!$folder)
				return array();

			$result = array();

			$dir = $folder;
			if ($dh = opendir($folder))
			{
				while ( ($file = readdir($dh)) !== false )
				{
					$file_path = $folder.'/'.$file;
					if (!is_file($file_path))
						continue;
						
					if (in_array($file, self::$ignore_files))
						continue;

					$result[] = (object)array('name'=>$file, 'size'=>filesize($file_path));
				}
				closedir($dh);
			}
			
			usort($result, array('Cms_Resources', 'sort_files'));
			
			return $result;
		}
		
		protected function folderHasSubfolders($folder)
		{
			$result = false;
			if ($dh = opendir($folder))
			{
				while ( ($file = readdir($dh)) !== false )
				{
					$file_path = $folder.'/'.$file;
					if (!is_file($file_path) && $file != '.' && $file != '..')
					{
						$result = true;
						break;
					}
				}
				closedir($dh);
			}
			
			return $result;
		}
		
		public static function sort_files($a, $b)
		{
			return strcmp($a->name, $b->name);
		}

		protected function listFolders(&$folders, $dir = null, $level = 0)
		{
			if ($dir == null)
				$dir = '/'.$this->getResourcesRootDir(true);
				
			if ($level == 0)
			{
				$folders[] = (object)array('name'=>substr($dir, 1), 'level'=>0, 'path'=>$dir);
				$level++;
			}

			if ($dh = opendir(PATH_APP.$dir))
			{
				while ( ($file = readdir($dh)) !== false )
				{
					$file_path = $dir.'/'.$file;
					if (is_file(PATH_APP.$file_path) || $file == '.' || $file == '..' || $file == '.svn')
						continue;

					$folders[] = (object)array('name'=>$file, 'level'=>$level, 'path'=>$file_path);
					$this->listFolders($folders, $file_path, $level+1);
				}
				closedir($dh);
			}
		}

		protected function getEditableTypes()
		{
			return Phpr::$config->get('EDITABLE_FILES', array('css', 'js'));
		}

		protected function isEditable($fileName)
		{
			$pathInfo = pathinfo($fileName);
			$ext = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : null;

			$editableFormats = $this->getEditableTypes();
			return in_array($ext, $editableFormats);
		}
		
		protected function getFileExt($path)
		{
			$pathInfo = pathinfo($path);
			return isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : null;
		}

		protected function getFileThumb($path, $width = 60, $height = 60)
		{
			$ext = $this->getFileExt($path);
			
			$isImage = in_array($ext, array('jpg', 'jpeg', 'gif', 'png'));

			if ($isImage)
			{
				$thumbName = Phpr_Image::createThumbnailName($path, $width, $height, 'keep_ratio');
			
				$fileUrl = '/uploaded/thumbnails/'.$thumbName;
				if (file_exists(PATH_APP.$fileUrl))
					return root_url($fileUrl);
					
				$errThumbName = Phpr_Image::createThumbnailName($path, $width, $height, 'keep_ratio_err');
				$errFileUrl = '/uploaded/thumbnails/'.$errThumbName;

				if (file_exists(PATH_APP.$errFileUrl))
					return root_url($errFileUrl);

				$this->deleteThumbs($thumbName);

				try
				{
					Phpr_Image::makeThumbnail(
						PATH_APP.$path, 
						PATH_APP.'/uploaded/thumbnails/'.$thumbName, $width, $height, false, 'keep_ratio');
				} catch (Exception $ex)
				{
					@copy(
						PATH_APP.'/modules/cms/resources/images/error_thumb.jpg', 
						PATH_APP.'/uploaded/thumbnails/'.$errThumbName);

					return root_url($errFileUrl);
				}
			} else
				return null;
			
			return root_url($fileUrl);
		}
		
		protected function deleteThumbs($path)
		{
			Phpr_Image::deleteImageThumbnails($path);
		}
		
		protected function isFolderExpanded($path)
		{
			$path = $this->getAppRelativeFolder($path);

			$expanded_folders = Db_UserParameters::get('resource-manager-expanded-folders', null, array());
			return array_key_exists($path, $expanded_folders);
		}
		
		/**
		 * Triggered after a file is uploaded in the CMS resource manager. 
		 * @event cms:onAfterResourceFileUpload
		 * @see backend:onAfterFileBrowserUpload
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param string $dest_path Specifies the file path on the server
		 */
		private function event_onAfterResourceFileUpload($dest_path) {}
	}

?>