<?

	/**
	 * Providing methods for finding objects (pages, categories, etc.) by nested URLs.
	 */
	class Cms_Router
	{
		/**
		 * Returns object with URL matching the specified URL string.
		 * @param string $url Requested URL string.
		 * @param array $objects Array of objects. Each object should have url and id fields.
		 * The array should be sorted by object URL length.
		 * @param array $params A list of parameters loaded from the URL string.
		 * @return mixed Returns an element of the $object parameter which URL matched the requested URL. 
		 * Returns null if the URL match was not found.
		 */
		public static function find_object_by_url($url, &$objects, &$params)
		{
			$requested_url_segments = self::split_url($url);
			$requested_segment_num = count($requested_url_segments);

			foreach ($objects as $object)
			{
				$object_url_segments = self::split_url($object->url);
				$object_url_segment_num = count($object_url_segments);
				if ($object_url_segment_num > $requested_segment_num)
					continue;

				if (self::find_url_match($requested_url_segments, $object_url_segments, $object->url, $params))
				{
					$params = array_reverse($params);
					return $object;
				}
			}
			
			return null;
		}
		
		/**
		 * Removes URL segments corresponding to a current CMS page: store/category/men/jumpers/wool => men/jumpers/wool
		 * @param string $url URL to process.
		 * @return string Returns processed URL
		 */
		public static function remove_page_segments($url)
		{
			$controller = Cms_Controller::get_instance();
			if (!$controller)
				return $url;
				
			$page_segments = self::split_url($controller->page->url);
			
			if (substr($url, 0, 1) == '/')
				$url = substr($url, 1);
			
			$segments = self::split_url($url);
			foreach ($page_segments as $segment)
				array_shift($segments);

			return implode('/', $segments);
		}
		
		/**
		 * Comparison function (for uksort()) for sorting objects by URL length.
		 * @param mixed $a Object A
		 * @param mixed $b Object B
		 * @return integer Returns comparing result (-1, 0, 1).
		 */
		public static function sort_objects($a, $b)
		{
			$a_len = strlen($a->url);
			$b_len = strlen($b->url);
			
			if ($a_len > $b_len)
				return -1;
			elseif ($a_len < $b_len)
				return 1;
				
			return 0;
		}
		
		protected static function split_url($url)
		{
			$url_parts = explode('/', $url);
			$url_part_values = array();
			foreach ($url_parts as $part)
			{
				if (strlen($part))
					$url_part_values[] = $part;
			}
			
			return $url_part_values;
		}
		
		protected static function find_url_match($requested_url_segments, $object_url_segments, $object_url, &$params)
		{
			$params = array();
			
			 $object_url_segment_num = count($object_url_segments);
			 
			 if ($object_url_segment_num == 0 && count($requested_url_segments) > 0)
			 	return false;

			while (count($requested_url_segments) >= $object_url_segment_num)
			{
				$current_requested_url = '/'.implode('/', $requested_url_segments);
				if ($object_url == $current_requested_url)
					return true;
					
				$params[] = array_pop($requested_url_segments);
			}
			
			return false;
		}
	}

?>