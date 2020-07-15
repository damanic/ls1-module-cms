<?

	/**
	 * Base class for CMS objects
	 */
	class Cms_Object extends Db_ActiveRecord
	{
		/**
		 * Returns a list of object attribute 
		 * names and values describing the object
		 * in file.
		 * @return array
		 */
		protected function list_file_attributes()
		{
			return array();
		}

		/**
		 * Returns a string containing a file header with a 
		 * a list of attributes describing object properties in
		 * a file.
		 */
		protected function format_file_attributes()
		{
			$attributes = $this->list_file_attributes();
			
			if (!$attributes)
				return null;
			
			$result = array();
			foreach ($attributes as $name=>$value)
				$result[] = $name.': '.$value;
				
			return "<?\n #".implode("\n #", $result)."\n?>\n";
		}
		
		/**
		 * Returns an unique file/directory name for saving the object
		 */
		protected function generate_unique_file_name($base_name, $directory, $file_extension = null)
		{
			$base_name = mb_strtolower($base_name);
			
			if (substr($directory, -1) == '/')
				$directory = substr($directory, 0, -1);
			
			$file_extension = strlen($file_extension) ? '.'.$file_extension : null;
			$file_name = $base_name.$file_extension;
			
			$dest_path = $directory.'/'.$file_name;
			$counter = 1;

			while ( file_exists($dest_path) )
			{
				$file_name = $base_name.'_'.$counter.$file_extension;
				$dest_path = $directory.'/'.$file_name;
				$counter++;
			}
			
			return $file_name;
		}
		
		/**
		 * Saves object data to a file
		 */
		protected function save_to_file($data, $dest_path)
		{
			$file_exists = file_exists($dest_path);
			
			if ($file_exists && !is_writable($dest_path))
				throw new Phpr_ApplicationException('File is not writable: '.$dest_path);
			
			if (strlen($data) || $file_exists)
			{
				if (@file_put_contents($dest_path, $data) === false)
					throw new Phpr_ApplicationException('Error writing file: '.$dest_path);
			} else
			{
				if (!@touch($dest_path))
					throw new Phpr_ApplicationException('Error creating file: '.$dest_path);
			}
			
			try
			{
				@chmod($dest_path, Phpr_Files::getFilePermissions());
			} catch (exception $ex) {}
		}
		
		/**
		 * Deletes old object file
		 */
		protected function delete_file($file_name)
		{
			$path = $this->get_file_path($file_name);
			if (file_exists($path))
				@unlink($path);
		}
		
		/**
		 * Returns CMS object file content and variables. This method is reserved for future use.
		 */
		public static function parse_object_file($file_path, &$file_content, &$variables)
		{
			$content = file_get_contents($file_path);
			$variables = array();
			$file_content = preg_replace('/^\s*\<\?\n*\s*#ls_[^\?\>]*\s*\n*\?\>\s*\n*/m', '', $content);
			
			$matches = array();
			$has_header = preg_match_all('/#(ls_[\w]+):[ \t]*([^\n]*)/i', $content, $matches);
			if ($has_header && $matches && isset($matches[1]) && isset($matches[2]))
			{
				foreach ($matches[1] as $index=>$var_name)
					$variables[$var_name] = $matches[2][$index];
			}
		}

		/**
		 * Returns an absolute path to the object file
		 */
		public function get_file_path($file_name)
		{
			return null;
		}
		
		/**
		 * Returns TRUE if the specified file name is a valid CMS object file name
		 */
		protected static function is_valid_file_name($file_name)
		{
			if (substr($file_name, 0, 1) == '.')
				return false;

			$info = pathinfo($file_name);
			if (!preg_match('/^[a-z_0-9-;]*$/i', $info['filename']))
				return false;

			if (!isset($info['extension']) || mb_strtolower($info['extension']) != self::get_content_extension())
				return false;
				
			return true;
		}
		
		/**
		 * Validates a file name before assigning it to a CMS object
		 */
		protected function validate_file_name($file_name)
		{
			$file_name = trim(mb_strtolower($file_name));
			if (!strlen($file_name))
				throw new Phpr_ApplicationException('Please enter the file name');

			if (!preg_match('/^[a-z_0-9-;]*$/i', $file_name))
				throw new Phpr_ApplicationException('File name can only contain latin characters, numbers, dashes and underscores.');
				
			return $file_name;
		}

		/**
		 * Saves a specific file name to CMS object database record
		 */
		protected function save_file_name_to_db($file_name)
		{
		}
		
		/**
		 * Copies the CMS object to a file
		 */
		public function copy_to_file($templates_dir = null)
		{
		}
		
		/**
		 * Assigns file name to an existing CMS object
		 */
		public function assign_file_name($file_name)
		{
			$this->file_name = $file_name;
			$this->copy_to_file();
			$this->save_file_name_to_db($file_name);
		}
		
		/**
		 * Returns extension of content files
		 * @return string
		 */
		public static function get_content_extension()
		{
			return Cms_SettingsManager::get()->content_file_extension;

		}

		/**
		 * Updates file extensions in a directory
		 */
		public static function change_dir_file_extensions($dir, $old, $new)
		{
			if (!file_exists($dir) || !is_dir($dir))
				return;

			$files = @scandir($dir);
			if($files) {
				foreach ( $files as $file_name ) {
					$info = pathinfo( $file_name );
					if ( !preg_match( '/^[a-z_0-9-;]*$/i', $info['filename'] ) ) {
						continue;
					}

					if ( !isset( $info['extension'] ) || mb_strtolower( $info['extension'] ) != $old ) {
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
		
		/**
		 * Returns a CMS theme the object belongs to.
		 * @return mixed Returns Cms_Theme if theming is enabled. Otherwise returns null.
		 */
		public function get_theme()
		{
			if (!Cms_Theme::is_theming_enabled())
				return null;

			return Cms_Theme::get_theme_by_id($this->theme_id);
		}
		
		/**
		 * Configures the unique validator for CMS objects.
		 */
		public function configure_unique_validator($checker, $page, $deferred_session_key)
		{
			/*
			 * Exclude pages from other themes
			 */
			
			if  (strlen($page->theme_id))
				$checker->where('theme_id=?', $page->theme_id);
		}
	}

?>