<?

	class Cms_PageReference
	{
		private static $cache = null;
		
		/**
		 * Returns a list of page references assigned to a specific 
		 * field (reference name) of a specific Active Record object.
		 * @param Db_ActiveRecord $object Specifies an object.
		 * @param string $reference_name Specifies the reference name
		 * @return array Returns an array of page references in the following format:
		 * array(page_id=>object(page_id, title, url, theme_name, theme_id))
		 */
		public static function list_page_references($object, $reference_name)
		{
			self::init_cache();
			
			$object_class = get_class($object);
			$object_key = $object->get_primary_key_value();
			
			/*
			 * Try to load references from the reference table
			 */
			
			if (isset(self::$cache[$object_class][$object_key]))
			{
				$result = array();
				foreach (self::$cache[$object_class][$object_key] as $reference)
				{
					if ($reference->reference_name == $reference_name)
						$result[$reference->page_id] = $reference;
				}
				
				return $result;
			}
			
			/*
			 * Fallback to the old (pre-theming) page reference
			 */
			
			$result = array();
			if ($page_id = $object->$reference_name)
			{
				$page = Cms_Page::find_by_id($page_id);
				if ($page)
				{
					$theme = $page->get_theme();
					$item = array('page_id'=>$page_id, 'title'=>$page->title, 'url'=>$page->url, 'theme_id'=>$theme ? $theme->id : null, 'theme_name'=>$theme ? $theme->name : null);
					$result[$page_id] = (object)$item;
				}
			}

			return $result;
		}
		
		/**
		 * Returns a list of pages assigned to an object's specific field as string.
		 * @param Db_ActiveRecord $object Specifies an object.
		 * @param string $string Specifies the reference name
		 * @return string Returns Returns a list of pages as string.
		 */
		public static function pages_as_string($object, $reference_name)
		{
			$references = self::list_page_references($object, $reference_name);
			return self::references_as_string($references);
		}
		
		public static function references_as_string($references)
		{
			$result = array();

			foreach ($references as $reference)
			{
				if ($reference->theme_name)
					$result[] = $reference->theme_name.': '.$reference->title;
				else
					$result[] = $reference->title;
			}

			if ($result)
			 	return implode('. ', $result);
			
			return 'Page is not assigned';
		}
		
		/**
		 * Returns information a referenced page in a current theme.
		 * @param Db_ActiveRecord $object Specifies an object.
		 * @param string $reference_name Specifies the reference name
		 * @param mixed $default Default value to be returned if Theming feature is disabled or 
		 * if a current theme cannot be detected.
		 * @return mixed If Theming is enabled and page reference exists, returns the reference 
		 * data as object(page_id, title, url, theme_name, theme_id). If reference is not found, returns null.
		 * If Theming is disabled, returns $default value.
		 */
		public static function get_page_info($object, $reference_name, $default = null)
		{
			if (!Cms_Theme::is_theming_enabled())
				return $default;
				
			$references = self::list_page_references($object, $reference_name);
			$theme = Cms_Theme::get_active_theme();
			if (!$theme)
				return $default;

			foreach ($references as $reference)
			{
				if ($reference->theme_id == $theme->id)
					return $reference;
			}
			
			return null;
		}
		
		/**
		 * Returns URL of a referenced page in a current theme.
		 * @param Db_ActiveRecord $object Specifies an object.
		 * @param string $reference_name Specifies the reference name
		 * @param mixed $default Default value to be returned if Theming feature is disabled or 
		 * if a current theme cannot be detected.
		 * @return mixed If Theming is enabled and page reference exists, returns the page URL. 
		 * If reference is not found, returns null. If Theming is disabled, returns $default value.
		 */
		public static function get_page_url($object, $reference_name, $default = null)
		{
			$page_info = self::get_page_info($object, $reference_name, $default);
			if (is_object($page_info))
				return $page_info->url;

			return $page_info;
		}
		
		protected static function init_cache()
		{
			if (self::$cache !== null)
				return;

			self::$cache = array();
			$references = Db_DbHelper::objectArray('
				select 
					pages.id as page_id,
					cms_page_references.*, 
					pages.title, 
					pages.url, 
					cms_themes.id as theme_id, 
					cms_themes.name as theme_name 
				from 
					cms_page_references, 
					pages, 
					cms_themes 
				where 
					pages.id=cms_page_references.page_id
					and cms_themes.id=pages.theme_id
				order by cms_page_references.id');
			foreach ($references as $reference)
			{
				if (!array_key_exists($reference->object_class_name, self::$cache))
					self::$cache[$reference->object_class_name] = array();

				if (!array_key_exists($reference->object_id, self::$cache[$reference->object_class_name]))
					self::$cache[$reference->object_class_name][$reference->object_id] = array();
					
				self::$cache[$reference->object_class_name][$reference->object_id][] = $reference;
			}
		}
	}

?>