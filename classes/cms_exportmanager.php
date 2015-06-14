<?

	/**
	 * Provides CMS theme export and import functions.
	 * @documentable
	 * @package cms.classes
	 * @author LemonStand eCommerce Inc.
	 */
	class Cms_ExportManager
	{
		/** 
		 * @var integer Specifies the number of new pages created by the last {@link Cms_ExportManager::import() import()} method call.
		 * @documentable
		 */
		public $import_new_pages = 0;
		
		/** 
		 * @var integer Specifies the number of pages updated by the last {@link Cms_ExportManager::import() import()} method call.
		 * @documentable
		 */
		public $import_updated_pages = 0;
		
		/** 
		 * @var integer Specifies the number of new partials created by the last {@link Cms_ExportManager::import() import()} method call.
		 * @documentable
		 */
		public $import_new_partials = 0;

		/** 
		 * @var integer Specifies the number of partials updated by the last {@link Cms_ExportManager::import() import()} method call.
		 * @documentable
		 */
		public $import_updated_partials = 0;

		/** 
		 * @var integer Specifies the number of new layouts created by the last {@link Cms_ExportManager::import() import()} method call.
		 * @documentable
		 */
		public $import_new_templates = 0;

		/** 
		 * @var integer Specifies the number of layouts updated by the last {@link Cms_ExportManager::import() import()} method call.
		 * @documentable
		 */
		public $import_updated_templates = 0;

		/** 
		 * @var integer Specifies the number of files imported by the last {@link Cms_ExportManager::import() import()} method call.
		 * @documentable
		 */
		public $import_files = 0;

		/** 
		 * @var integer Specifies the number of global content blocks imported by the last {@link Cms_ExportManager::import() import()} method call.
		 * @documentable
		 */
		public $import_new_global_content_blocks = 0;
		
		protected $customer_groups = null;
		
		public static function create()
		{
			return new self();
		}
		
		/*
		 * Export operation
		 */
		
		/**
		 * Exports a CMS theme into a file.
		 * @documentable
		 * @param array $object_types Specifies CMS object types to export. 
		 * Supported values: <em>pages</em>, <em>partials</em>, <em>templates</em>, <em>global_content_blocks</em>, <em>resources</em>.
		 * @param integer $theme_id Specifies a {@link Cms_Theme theme} identifier.
		 * @param boolean $ignore_payment_paritals Determines whether payment partials should be ignored.
		 * @param boolean $ignore_theme_description Determines whether the theme description should be ignored.
		 * @return string Returns a name of the exported file. The file is saved to the <em>temp</em> subdirectory of LemonStand root directory.
		 */ 
		public function export($object_types, $theme_id = null, $ignore_payment_paritals = false, $ignore_theme_description = false)
		{
			$tmpDirPath = null;
			$archivePath = null;
			@set_time_limit(3600);
			
			Backend::$events->fireEvent('cms:onBeforeDataExport');

			try
			{
				$theme = null;
				if ($theme_id)
				{
					$theme = Cms_Theme::create()->find($theme_id);
					if (!$theme)
						throw new Phpr_ApplicationException('Theme not found.');
				}

				if (!is_writable(PATH_APP.'/temp/'))
					throw new Phpr_SystemException('Insufficient writing permissions to  '.PATH_APP.'/temp.');

				$tmpDirPath = PATH_APP.'/temp/'.uniqid('lca');
				if (!@mkdir($tmpDirPath))
					throw new Phpr_SystemException('Unable to create directory '.$tmpDirPath);

				$archiveName = uniqid('lca');
				$archivePath = PATH_APP.'/temp/'.$archiveName;

				/*
				 * Create manifest file
				 */
				$manifest_data = '<?xml version="1.0"?><versions>';
				$versions = Db_UpdateManager::getVersions();
				foreach ($versions as $moduleId=>$version)
					$manifest_data .= '<module id="'.h($moduleId).'"><version>'.$version.'</version></module>';
				$manifest_data .= '</versions>';
				file_put_contents($tmpDirPath.'/manifest.xml', $manifest_data);

				/*
				 * Create cms data file
				 */
				$export_pages = array_key_exists('pages', $object_types) && $object_types['pages'];
				$export_partials = array_key_exists('partials', $object_types) && $object_types['partials'];
				$export_templates = array_key_exists('templates', $object_types) && $object_types['templates'];
				$export_global_content_blocks = array_key_exists('global_content_blocks', $object_types) && $object_types['global_content_blocks'];

				if ($export_global_content_blocks || $export_pages || $export_partials || $export_templates || $theme)
				{
					$data_path = $tmpDirPath.'/cms_data.xml';
					$document = new Core_SimpleXMLExtended('<cms_data></cms_data>');
					
					if ($theme)
						$this->export_theme_data($document, $theme, $ignore_theme_description);
					
					if ($export_pages)
					{
						$pages = $this->export_pages_data($document, $theme_id);
						$this->export_content_blocks_data($document, $pages);
					}

					if ($export_partials)
						$this->export_partials_data($document, $theme_id, $ignore_payment_paritals);

					if ($export_templates)
						$this->export_templates_data($document, $theme_id);
						
					if ($export_global_content_blocks)
						$this->export_global_content_blocks($document);

					$document->asXML($data_path);
				}
				
				/*
				 * Export resources
				 */
				$export_resources = array_key_exists('resources', $object_types) && $object_types['resources'];
				if ($export_resources)
				{
					$options = array('ignore'=>array('.svn', '.DS_Store'));
					
					if (!$theme)
						$src_path = PATH_APP.'/'.Cms_SettingsManager::get()->resources_dir_path;
					else
						$src_path = PATH_APP.'/'.$theme->get_resources_path();

					Phpr_Files::copyDir($src_path, $tmpDirPath.'/resources', $options);
				}

				/*
				 * Create archive
				 */
				Core_ZipHelper::zipDirectory($tmpDirPath, $archivePath);
				Phpr_Files::removeDirRecursive($tmpDirPath);

				return $archiveName;
			}
			catch (Exception $ex)
			{
				if (strlen($tmpDirPath) && @file_exists($tmpDirPath))
					Phpr_Files::removeDirRecursive($tmpDirPath);

				if (strlen($archivePath) && @file_exists($archivePath))
					@unlink($archivePath);

				throw $ex;
			}
		}
		
		protected function export_pages_data($document, $theme_id)
		{
			$pages_root = $document->addChild('pages');
			$pages = Cms_Page::create();
			
			if ($theme_id)
				$pages->where('theme_id=?', $theme_id);
			
			$pages = $pages->find_all(null, array(), 'export');
			
			foreach ($pages as $page)
			{
				$page_element = $pages_root->addChild('page');
				$page_element->addChild('id', $page->id);
				$page_element->addChild('title')->addCData($page->title);
				$page_element->addChild('url', $page->url);
				$page_element->addChild('description')->addCData($page->description);
				$page_element->addChild('keywords')->addCData($page->keywords);
				$page_element->addChild('content')->addCData(trim($page->get_content_code()));
				$page_element->addChild('template_id')->addCData($page->template_id);
				$page_element->addChild('action_reference')->addCData($page->action_reference);
				$page_element->addChild('action_code')->addCData($page->get_post_action_code(true));
				$page_element->addChild('pre_action_code')->addCData($page->get_pre_action_code(true));
				$page_element->addChild('ajax_handlers_code')->addCData($page->get_ajax_handlers_code(true));
				$page_element->addChild('security_mode_id', $page->security_mode_id);
				$page_element->addChild('security_redirect_page_id', $page->security_redirect_page_id);
				$page_element->addChild('protocol', h($page->protocol));
				$page_element->addChild('parent_id', $page->parent_id);
				$page_element->addChild('navigation_visible', $page->navigation_visible);
				$page_element->addChild('navigation_label')->addCData($page->navigation_label);
				$page_element->addChild('navigation_sort_order', $page->navigation_sort_order);
				$page_element->addChild('disable_ga', $page->disable_ga);
				$page_element->addChild('is_published', $page->is_published);
				$page_element->addChild('label')->addCData($page->label);

				$page_element->addChild('head')->addCData($page->get_head_code());
				
				$blocks = $page->list_blocks();
				$index = 1;
				foreach ($blocks as $block_name=>$block_content)
				{
					$name_field = 'page_block_name_'.$index;
					$content_field = 'page_block_content_'.$index;
					
					$page_element->addChild($name_field, h($block_name));
					$page_element->addChild($content_field)->addCData($block_content);
					$index++;
				}
				
				for ($reset_index = $index; $reset_index <= Cms_Page::max_block_num; $reset_index++)
				{
					$name_field = 'page_block_name_'.$index;
					$content_field = 'page_block_content_'.$index;

					$page_element->addChild($name_field, '');
					$page_element->addChild($content_field)->addCData('');
				}
				
				$page_element->addChild('enable_page_customer_group_filter', $page->enable_page_customer_group_filter ? 1 : 0);

				$customer_groups = $page->customer_groups;
				$customer_groups_str = array();
				foreach ($customer_groups as $customer_group)
					$customer_groups_str[] = $customer_group->name;
				$customer_groups_str = implode('||', $customer_groups_str);
				$page_element->addChild('customer_groups', $customer_groups_str);
			}
			
			return $pages;
		}
		
		protected function export_partials_data($document, $theme_id, $ignore_payment_paritals)
		{
			Cms_Partial::auto_create_from_files();
			
			$partials_root = $document->addChild('partials');
			$partials = Cms_Partial::create();
			
			if ($theme_id)
				$partials->where('theme_id=?', $theme_id);
			
			$partials = $partials->find_all();
			
			foreach ($partials as $partial)
			{
				if ($ignore_payment_paritals)
				{
					if (substr($partial->name, 0, 7) == 'payment' || substr($partial->name, 0, 16) == 'payment_profile:')
						continue;
				}
				
				$partial_element = $partials_root->addChild('partial');
				$partial_element->addChild('id', $partial->id);
				$partial_element->addChild('name')->addCData($partial->name);
				$partial_element->addChild('description')->addCData($partial->description);
				$partial_element->addChild('html_code')->addCData($partial->get_content_code());
			}
		}

		protected function export_templates_data($document, $theme_id)
		{
			Cms_Template::auto_create_from_files();

			$templates_root = $document->addChild('templates');
			$templates = Cms_Template::create();
			
			if ($theme_id)
				$templates->where('theme_id=?', $theme_id);
			
			$templates = $templates->find_all();
			
			foreach ($templates as $template)
			{
				$template_element = $templates_root->addChild('template');
				$template_element->addChild('id', $template->id);
				$template_element->addChild('name')->addCData($template->name);
				$template_element->addChild('description')->addCData($template->description);
				$template_element->addChild('html_code')->addCData($template->get_content());
			}
		}
		
		protected function export_global_content_blocks($document)
		{
			$content_root = $document->addChild('global_content_blocks');
			$blocks = Cms_GlobalContentBlock::create()->find_all();
			
			foreach ($blocks as $block)
			{
				$block_element = $content_root->addChild('block');
				$block_element->addChild('name')->addCData($block->name);
				$block_element->addChild('code')->addCData($block->code);
				$block_element->addChild('content')->addCData($block->content);
				$block_element->addChild('type')->addCData($block->block_type);
			}
		}

		protected function export_content_blocks_data($document, $pages)
		{
			$contentblocks_root = $document->addChild('content_blocks');
			
			foreach ($pages as $page)
			{
				$content_blocks = $page->list_content_blocks($page->get_content_code());

				foreach ($content_blocks as $block)
				{
					$block_element = $contentblocks_root->addChild('contentblock');
					$block_element->addChild('code')->addCData($block->code);
					$block_element->addChild('type')->addCData($block->type);
					$block_element->addChild('page_id')->addCData($page->id);
					$block_element->addChild('content')->addCData($page->get_content_block_content($block->code));
				}
			}
		}
		
		protected function export_theme_data($document, $theme, $ignore_theme_description)
		{
			$theme_node = $document->addChild('theme');
			$theme_node->addChild('code')->addCData($theme->code);
			$theme_node->addChild('name')->addCData($theme->name);
			$theme_node->addChild('description')->addCData($ignore_theme_description ? '' : $theme->description);
			$theme_node->addChild('author_name')->addCData($theme->author_name);
			$theme_node->addChild('author_website')->addCData($theme->author_website);
			$theme_node->addChild('agent_detection_mode')->addCData($theme->agent_detection_mode);
			$theme_node->addChild('templating_engine')->addCData($theme->templating_engine);
			
			$agent_list = $theme->agent_list;
			if (!is_array($agent_list))
				$agent_list = array();

			$theme_node->addChild('agent_list')->addCData(serialize($agent_list));
			$theme_node->addChild('agent_detection_code')->addCData($theme->agent_detection_code);
		}

		/*
		 * Import operation
		 */
		
		/**
		 * Imports a CMS theme from a file.
		 * The method throws {@link Phpr_SystemException} in case of error.
		 * @documentable
		 * @param mixed $file_info Specifies either path to the CMS archive file or an array containing information about an 
		 * {@link http://www.php.net/manual/en/features.file-upload.post-method.php uploaded file}. 
		 * @param integer $theme_id Specifies a {@link Cms_Theme theme} identifier. If the parameter value is -1, a new theme will be created.
		 * The value -2 is reserved for backward compatibility.
		 */
		public function import($fileInfo, $theme_id = -2)
		{
			$filePath = null;
			
			Backend::$events->fireEvent('cms:onBeforeDataImport', $fileInfo);

			try
			{
				if (!is_writable(PATH_APP.'/temp/'))
					throw new Phpr_SystemException('Insufficient writing permissions to '.PATH_APP.'/temp');
					
				if (is_array($fileInfo))
				{
					$filePath = PATH_APP.'/temp/'.uniqid('lca');
					if (!move_uploaded_file($fileInfo['tmp_name'], $filePath))
						throw new Phpr_SystemException('Unable to copy uploaded file to '.$filePath);
				} else
					$filePath = $fileInfo;
					
				Cms_Module::begin_content_version_update();
				$this->restore_data($filePath, $theme_id);
				Cms_Module::end_content_version_update();

				@unlink($filePath);
			}
			catch (Exception $ex)
			{
				Cms_Module::end_content_version_update();

				if ($theme_id == -2)
				{
					if (strlen($filePath) && @file_exists($filePath))
						@unlink($filePath);
				}

				throw $ex;
			}
		}

		protected function restore_data($archiveFile, $theme_id)
		{
			$tmpDirPath = null;
			$sqlPath = null;
			@set_time_limit(3600);
			
			$theme = null;
			$new_theme = false;

			try
			{
				if (!is_writable(PATH_APP.'/temp/'))
					throw new Phpr_SystemException('Insufficient writing permissions to '.PATH_APP.'/temp');
					
				$tmpDirPath = PATH_APP.'/temp/'.uniqid('lar');
				if (!@mkdir($tmpDirPath))
					throw new Phpr_SystemException('Unable to create directory '.$tmpDirPath);

				Core_ZipHelper::unzip($tmpDirPath, $archiveFile);
				
				/*
				 * Load manifest and check versions
				 */

				$manifestPath = $tmpDirPath.'/manifest.xml';
				if (!file_exists($manifestPath))
					throw new Phpr_ApplicationException('Archive file is corrupted - manifest file is not found.');

				$systemVersions = Db_UpdateManager::getVersions();

				$doc = new DOMDocument('1.0');
				$doc->loadXML(file_get_contents($manifestPath));
				$xPath = new DOMXPath($doc);
				
				$modules = $xPath->query('//versions/module');
				$notFoundModules = array();
				foreach ($modules as $module)
				{
					$version = $xPath->query('version', $module)->item(0)->nodeValue;
					if (!strlen($version))
						continue;
					
					$moduleId = $module->getAttribute('id');
					if (!strlen($moduleId))
						throw new Phpr_ApplicationException('Archive file is corrupted - module identifier is not found.');
						
					if (!array_key_exists($moduleId, $systemVersions))
						continue;

					if (!Db_UpdateManager::moduleVersionExists($moduleId, $version))
						$notFoundModules[] = $moduleId.': '.$version;
				}

				if ($notFoundModules) {
					throw new Phpr_ApplicationException(
						sprintf('Error restoring data. The following module versions are not present in this system: %s.', implode(', ', $notFoundModules))
					);
				}

				/*
				 * Create or load theme
				 */
				
				if ($theme_id != -2)
				{
					if ($theme_id > 0)
					{
						$theme = Cms_Theme::create()->find($theme_id);
						if (!$theme)
							throw new Phpr_ApplicationException('Theme not found.');
					} elseif ($theme_id == -1)
					{
						$new_theme = true;
						
						/*
						 * Try to load theme info from the CSV file
						 */

						$theme_code = 'imported_theme';
						$theme_name = 'Imported theme';
						$templating_engine = 'php';
						$theme_description = null;
						$theme_author = null;
						$theme_website = null;
						$agent_detection_mode = Cms_Theme::agent_detection_disabled;
						$agent_list = null;
						$agent_detection_code = null;

						$dataPath = $tmpDirPath.'/cms_data.xml';
						if (file_exists($dataPath))
						{
							$doc = new DOMDocument('1.0');
							$doc->load($dataPath);
							$xPath = new DOMXPath($doc);

							$theme_nodes = $xPath->query('//cms_data/theme');
							if ($theme_nodes->length)
							{
								$theme_node = $theme_nodes->item(0);
								
								$theme_code = $xPath->query('code', $theme_node)->item(0)->nodeValue;
								$theme_name = $xPath->query('name', $theme_node)->item(0)->nodeValue;
								$theme_description = $xPath->query('description', $theme_node)->item(0)->nodeValue;
								$theme_author = $xPath->query('author_name', $theme_node)->item(0)->nodeValue;
								$theme_website = $xPath->query('author_website', $theme_node)->item(0)->nodeValue;
								$agent_detection_mode = $xPath->query('agent_detection_mode', $theme_node)->item(0)->nodeValue;
								$agent_list = $xPath->query('agent_list', $theme_node)->item(0)->nodeValue;
								$agent_detection_code = $xPath->query('agent_detection_code', $theme_node)->item(0)->nodeValue;
								$templating_engine = $this->eval_node_value($xPath, $theme_node, 'templating_engine', 'php');
							}
						}

						$theme = Cms_Theme::create_safe(
							$theme_code, 
							$theme_name, 
							$theme_description, 
							$theme_author, 
							$theme_website,
							$agent_detection_mode,
							$agent_list,
							$agent_detection_code,
							$templating_engine
						);
					}
				}

				/*
				 * Copy directories
				 */

				if (!$theme)
					$resources_directory = PATH_APP.'/'.Cms_SettingsManager::get()->resources_dir_path;
				else
					$resources_directory = PATH_APP.'/'.$theme->get_resources_path();
				
				$dirPath = $tmpDirPath.'/resources';
				if (file_exists($dirPath))
				{
					Phpr_Files::$dir_copy_file_num = 0;
					Phpr_Files::copyDir($dirPath, $resources_directory);
					$this->import_files = Phpr_Files::$dir_copy_file_num;
				}

				/*
				 * Restore pages, templates and partials
				 */

				$dataPath = $tmpDirPath.'/cms_data.xml';
				if (file_exists($dataPath))
				{
					$doc = new DOMDocument('1.0');
					$doc->load($dataPath);
					$xPath = new DOMXPath($doc);

					$this->import_partials($xPath, $theme);
					$template_ids = $this->import_templates($xPath, $theme);
					$this->import_pages($xPath, $template_ids, $theme);
					$this->import_global_content_blocks($xPath);
				}

				Phpr_Files::removeDirRecursive($tmpDirPath);
			}
			catch (Exception $ex)
			{
				if (strlen($tmpDirPath) && @file_exists($tmpDirPath))
					Phpr_Files::removeDirRecursive($tmpDirPath);
					
				if ($new_theme && $theme)
					$theme->delete();

				throw $ex;
			}
		}
		
		protected function eval_node_value($xPath, $object, $name, $default = false)
		{
			$node = $xPath->query($name, $object);
			if ($node->length)
				return $node->item(0)->nodeValue;
				
			return $default;
		}
		
		protected function import_partials($xPath, $theme)
		{
			$partials = $xPath->query('//cms_data/partials/partial');

			if ($partials->length)
			{
				$db_partials = Cms_Partial::create();
				if ($theme)
					$db_partials->where('theme_id=?', $theme->id);
				
				$db_partials = $db_partials->find_all()->as_array(null, 'name');
				foreach ($partials as $xml_partial)
				{
					$name = $this->eval_node_value($xPath, $xml_partial, 'name');

					$existing_object = false;
					if ($existing_object = array_key_exists($name, $db_partials))
						$db_partial = $db_partials[$name];
					else
						$db_partial = Cms_Partial::create();
					
					$db_partial->name = $name;
					$db_partial->description = $this->eval_node_value($xPath, $xml_partial, 'description');
					$db_partial->html_code = $this->eval_node_value($xPath, $xml_partial, 'html_code');
					if ($theme)
						$db_partial->theme_id = $theme->id;
					$db_partial->save();
					
					if ($existing_object)
						$this->import_updated_partials++;
					else
						$this->import_new_partials++;
				}
			}
		}

		protected function import_templates($xPath, $theme)
		{
			$id_correlations = array();
			
			$templates = $xPath->query('//cms_data/templates/template');
			if ($templates->length)
			{
				$db_templates = Cms_Template::create();
				if ($theme)
					$db_templates->where('theme_id=?', $theme->id);
				
				$db_templates = $db_templates->find_all()->as_array(null, 'name');
				foreach ($templates as $xml_template)
				{
					$name = $this->eval_node_value($xPath, $xml_template, 'name');

					$existing_object = false;
					if ($existing_object = array_key_exists($name, $db_templates))
						$db_template = $db_templates[$name];
					else
						$db_template = Cms_Template::create();
					
					$db_template->name = $name;
					$db_template->description = $this->eval_node_value($xPath, $xml_template, 'description');
					$db_template->html_code = $this->eval_node_value($xPath, $xml_template, 'html_code');
					if ($theme)
						$db_template->theme_id = $theme->id;
					$db_template->save();
					
					if ($existing_object)
						$this->import_updated_templates++;
					else
						$this->import_new_templates++;

					$id = $this->eval_node_value($xPath, $xml_template, 'id');
					$id_correlations[$id] = $db_template->id;
				}
			}
			
			return $id_correlations;
		}
		
		protected function import_global_content_blocks($xPath)
		{
			$blocks = $xPath->query('//cms_data/global_content_blocks/block');
			if ($blocks->length)
			{
				foreach ($blocks as $xml_block)
				{
					$name = $this->eval_node_value($xPath, $xml_block, 'name');
					$code = $this->eval_node_value($xPath, $xml_block, 'code');
					
					/*
					 * Do not overwrite global content blocks
					 */

					if (Cms_GlobalContentBlock::get_by_code($code))
						continue;

					$db_block = Cms_GlobalContentBlock::create();
					
					$db_block->name = $name;
					$db_block->code = $code;
					$db_block->block_type = $this->eval_node_value($xPath, $xml_block, 'type', 'html');
					$db_block->content = $this->eval_node_value($xPath, $xml_block, 'content');
					$db_block->save();
					
					$this->import_new_global_content_blocks++;
				}
			}
		}
		
		protected function set_page_customer_groups($page_id, $customer_groups)
		{
			Db_DbHelper::query('delete from page_customer_groups where page_id=:page_id', array('page_id'=>$page_id));
			
			if ($this->customer_groups === null)
			{
				$group_objects = Db_DbHelper::objectArray('select * from shop_customer_groups');
				$this->customer_groups = array();
				foreach ($group_objects as $group)
					$this->customer_groups[mb_strtoupper($group->name)] = $group->id;
			}

			$customer_groups = explode('||', $customer_groups);

			foreach ($customer_groups as $customer_group)
			{
				if (!strlen($customer_group))
					continue;
					
				$customer_group = mb_strtoupper($customer_group);
				if (array_key_exists($customer_group, $this->customer_groups))
				{
					Db_DbHelper::query('insert into page_customer_groups(page_id, customer_group_id) values (:page_id, :customer_group_id)', array(
						'page_id'=>$page_id,
						'customer_group_id'=>$this->customer_groups[$customer_group]
					));
				}
			}
		}
		
		protected function import_pages($xPath, $template_ids, $theme)
		{
			$id_correlations = array();

			$pages = $xPath->query('//cms_data/pages/page');
			if ($pages->length)
			{
				$db_pages = Cms_Page::create();
				if ($theme)
					$db_pages->where('theme_id=?', $theme->id);
				
				$db_pages = $db_pages->find_all(null, array(), 'export')->as_array(null, 'url');
				$saved_pages = array();
				foreach ($pages as $xml_page)
				{
					$url = $this->eval_node_value($xPath, $xml_page, 'url');

					$existing_object = false;
					if ($existing_object = array_key_exists($url, $db_pages))
						$db_page = $db_pages[$url];
					else
						$db_page = Cms_Page::create();

					$template_id = $this->eval_node_value($xPath, $xml_page, 'template_id');
					if (strlen($template_id))
					{
						if (array_key_exists($template_id, $template_ids))
							$template_id = $template_ids[$template_id];
					} else
						$template_id = null;

					$db_page->url = $url;
					$db_page->description = $this->eval_node_value($xPath, $xml_page, 'description');
					$db_page->title = $this->eval_node_value($xPath, $xml_page, 'title');

					$db_page->keywords = $this->eval_node_value($xPath, $xml_page, 'keywords');
					$db_page->content = $this->eval_node_value($xPath, $xml_page, 'content');
					$db_page->template_id = $template_id;
					$db_page->action_reference = $this->eval_node_value($xPath, $xml_page, 'action_reference');
					$db_page->action_code = $this->eval_node_value($xPath, $xml_page, 'action_code');
					$db_page->ajax_handlers_code = $this->eval_node_value($xPath, $xml_page, 'ajax_handlers_code');
					$db_page->security_mode_id = $this->eval_node_value($xPath, $xml_page, 'security_mode_id');
					$db_page->security_redirect_page_id = $this->eval_node_value($xPath, $xml_page, 'security_redirect_page_id');
					$db_page->protocol = $this->eval_node_value($xPath, $xml_page, 'protocol');
					$db_page->pre_action = $this->eval_node_value($xPath, $xml_page, 'pre_action_code');
					$db_page->navigation_visible = $this->eval_node_value($xPath, $xml_page, 'navigation_visible');
					$db_page->navigation_label = $this->eval_node_value($xPath, $xml_page, 'navigation_label');
					$db_page->navigation_sort_order = $this->eval_node_value($xPath, $xml_page, 'navigation_sort_order');
					$db_page->parent_id = $this->eval_node_value($xPath, $xml_page, 'parent_id');
					$db_page->label = $this->eval_node_value($xPath, $xml_page, 'label');
					$db_page->disable_ga = $this->eval_node_value($xPath, $xml_page, 'disable_ga');

					$db_page->is_published = $this->eval_node_value($xPath, $xml_page, 'is_published', true);
					
					$db_page->enable_page_customer_group_filter = $this->eval_node_value($xPath, $xml_page, 'enable_page_customer_group_filter');

					$db_page->head = $this->eval_node_value($xPath, $xml_page, 'head');

					for ($index = 1; $index <= Cms_Page::max_block_num; $index++)
					{
						$name_field = 'page_block_name_'.$index;
						$content_field = 'page_block_content_'.$index;
						
						$db_page->$name_field = $this->eval_node_value($xPath, $xml_page, $name_field);
						$db_page->$content_field = $this->eval_node_value($xPath, $xml_page, $content_field);
					}

					if ($theme)
						$db_page->theme_id = $theme->id;

					$db_page->save();
					$this->set_page_customer_groups($db_page->id, $this->eval_node_value($xPath, $xml_page, 'customer_groups'));
					
					$id = $this->eval_node_value($xPath, $xml_page, 'id');

					$id_correlations[$id] = $db_page->id;
					$saved_pages[] = $db_page;
					
					if ($existing_object)
						$this->import_updated_pages++;
					else
						$this->import_new_pages++;
				}

				/*
				 * Import content blocks
				 */

				$content_blocks = $xPath->query('//cms_data/content_blocks/contentblock');
				if ($content_blocks->length)
				{
					foreach ($content_blocks as $xml_block)
					{
						$page_id = $this->eval_node_value($xPath, $xml_block, 'page_id');
						if (!array_key_exists($page_id, $id_correlations))
							continue;
							
						$page_id = $id_correlations[$page_id];
						$code = $this->eval_node_value($xPath, $xml_block, 'code');
						$db_block = Cms_ContentBlock::get_by_page_and_code($page_id, $code);
						if (!$db_block)
						{
							$db_block = Cms_ContentBlock::create();
							$db_block->code = $code;
							$db_block->page_id = $page_id;
						}
						
						$db_block->type = $this->eval_node_value($xPath, $xml_block, 'type', 'html');
						$db_block->content = $this->eval_node_value($xPath, $xml_block, 'content');
						$db_block->save();
					}
				}

				foreach ($saved_pages as $db_page)
				{
					$db_page->save();
				}

				/*
				 * Update page relations
				 */
				
				foreach ($saved_pages as $db_page)
				{
					$redirect_id = $db_page->security_redirect_page_id;
					if (array_key_exists($redirect_id, $id_correlations))
					{
						$db_page->redirect_id = $id_correlations[$redirect_id];

						Db_DbHelper::query('update pages set security_redirect_page_id=:redirect_id where id=:id', array(
							'redirect_id'=>$id_correlations[$redirect_id],
							'id'=>$db_page->id
						));
					}

					$parent_id = $db_page->parent_id;
					if (strlen($parent_id) && array_key_exists($parent_id, $id_correlations))
					{
						$db_page->parent_id = $id_correlations[$parent_id];

						Db_DbHelper::query('update pages set parent_id=:parent_id where id=:id', array(
							'parent_id'=>$id_correlations[$parent_id],
							'id'=>$db_page->id
						));
					}
				}
			}
		}
		
		/*
		 * Event descriptions
		 */

		/**
		 * Triggered before a CMS theme is exported.
		 * In the event handler you can throw an exception if the operation should be cancelled.
		 * @event cms:onBeforeDataExport
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 */
		private function event_onBeforeDataExport() {}

		/**
		 * Triggered before a CMS theme is imported.
		 * In the event handler you can throw an exception if the operation should be cancelled.
		 * @event cms:onBeforeDataImport
		 * @package cms.events
		 * @author LemonStand eCommerce Inc.
		 * @param mixed $file_info Specifies either path to the CMS archive file or an array containing information about an 
		 * {@link http://www.php.net/manual/en/features.file-upload.post-method.php uploaded file}. 
		 		 */
		private function event_onBeforeDataImport($file_info) {}
	}

?>