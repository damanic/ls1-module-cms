<?

	/**
	 * Proxy object for storing page navigation information.
	 * This class is a placeholder (proxy) object which holds information about a specific page. 
	 * It is used during the dynamic menus generation instead of the {@link Cms_Page} class due to performance reasons.
	 * This class has methods and fields sufficient for building menus. In most cases, during the menu generation 
	 * process, you can work with objects of this class as if you were working with {@link Cms_Page} objects. 
	 * @documentable
	 * @see http://lemonstand.com/docs/creating_site_maps_dynamic_menus_and_breadcrumbs/ Creating site maps, dynamic menus and breadcrumbs
	 * @author LemonStand eCommerce Inc.
	 * @package cms.classes
	 */
	class Cms_PageNavigationNode
	{
		/**
		 * @var integer Specifies the page identifier.
		 * @documentable
		 */ 
		public $id;

		/**
		 * @var string Specifies the page title.
		 * @documentable
		 */ 
		public $title;

		/**
		 * @var string Specifies the page URL, relative to LemonStand application root.
		 * @documentable
		 */ 
		public $url;

		/**
		 * @var integer Specifies the identifier of a parent page.
		 * @documentable
		 */ 
		public $parent_id;

		public $parent_key_index;
		
		/**
		 * @var boolean Indicates whether the page should be visible in the site maps or menus. 
		 * @documentable
		 */
		public $navigation_visible;

		/**
		 * @var boolean Indicates whether the page is published.
		 * @documentable
		 */
		public $is_published;

		/**
		 * @var string Specifies the page navigation label.
		 * @documentable
		 */
		public $navigation_label;

		/**
		 * @var string Indicates whether the page is visible for the current customer group.
		 * @documentable
		 */
		public $visible_for_group;
		
		public function __construct($db_record)
		{
			$this->title = $db_record->title;
			$this->id = $db_record->id;
			$this->url = $db_record->url;
			$this->parent_id = $db_record->parent_id;
			$this->navigation_visible = $db_record->navigation_visible;
			$this->navigation_label = $db_record->navigation_label;
			$this->visible_for_group = $db_record->visible_for_group;
			$this->is_published = $db_record->is_published;
		}
		
		/**
		 * Returns the navigation menu label.
		 * If the navigation menu label was not specified for this page, the function returns the page title.
		 * @documentable
		 * @return string Returns the page navigation menu label or page title.
		 */
		public function navigation_label()
		{
			if (strlen($this->navigation_label))
				return $this->navigation_label;
				
			return $this->title;
		}
		
		/**
		 * Returns a list of pages grouped under this page.
		 * @documentable
		 * @return array Returns an array of the {@link Cms_PageNavigationNode} objects
		 */
		public function navigation_subpages()
		{
			if (array_key_exists($this->id, Cms_Page::$navigation_parent_cache))
				return Cms_Page::$navigation_parent_cache[$this->id];

			return array();
		}
		
		public function is_current() {
			return $this->url == Phpr::$request->getCurrentUri();
		}
	}