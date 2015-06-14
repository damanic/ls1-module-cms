<?

	class Cms_ThemeImportModel extends Db_ActiveRecord
	{
		public $table_name = 'core_configuration_records';

		public $custom_columns = array(
			'theme_id'=>db_number
		);
		
		public $has_many = array(
			'file'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Cms_ThemeImportModel'", 'order'=>'id', 'delete'=>true)
		);

		public function define_columns($context = null)
		{
			$this->define_multi_relation_column('file', 'file', 'Theme file', '@name')->validation()->required('Please upload LCA file.');
			$this->define_column('theme_id', 'Theme')->validation()->fn('trim')->required('Please select theme to import the file to.');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('file')->renderAs(frm_file_attachments)->renderFilesAs('single_file')->addDocumentLabel('Upload file')->fileDownloadBaseUrl(url('ls_backend/files/get/'))->noAttachmentsLabel('')->comment('Please upload LCA archive.', 'above');
			$this->add_form_field('theme_id')->renderAs(frm_dropdown)->emptyOption('<please select>')->comment('Please select theme to import the archive to. Pages, partials, layouts and file resources in the selected theme could be replaced with archive content.', 'above');
		}

		public function get_theme_id_options($key_value=-1)
		{
			$result = array(
				-1 => '<create new theme>'
			);
			
			$themes = Cms_Theme::create()->order('name')->find_all();
			foreach ($themes as $theme)
				$result[$theme->id] = $theme->name.' ('.$theme->code.')';
				
			return $result;
		}
		
		public function import($data, $session_key)
		{
			$this->validate_data($data, $session_key);
			$this->set_data($data);
			
			$file = $this->list_related_records_deferred('file', $session_key)->first;

			$pathInfo = pathinfo($file->name);
			$ext = strtolower($pathInfo['extension']);
			if (!isset($pathInfo['extension']) || !($ext == 'lca' || $ext == 'zip'))
				$this->validation->setError( 'Uploaded file is not a valid LemonStand theme archive.', 'file', true );
				
			$export_manager = Cms_ExportManager::create();
			$export_manager->import(PATH_APP.$file->getPath(), $this->theme_id);

			return $export_manager;
		}
	}

?>