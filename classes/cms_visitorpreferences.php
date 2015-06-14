<?

	/**
	 * Allows to save visitor preferences into the server session.
	 * The Cms_VisitorPreferences is a multi-purpose class which allows to save visitor 
	 * preferences into the server session storage and load them when it is needed. 
	 * Please remember that the values saved using this class are stored in the session object,
	 * and not bound to a specific customer. The server distinguishes visitors using cookies, 
	 * so visitor preferences should be considered as temporary. This data storing method 
	 * is reliable during a visitor browsing session, but you should not rely on it 
	 * for storing important or sensitive data. Use this class for storing non critical 
	 * data, for example preferred product sorting modes.
	 * @documentable
	 * @author LemonStand eCommerce Inc.
	 * @package cms.classes
	 */
	class Cms_VisitorPreferences
	{
		/**
		 * Saves a value. Example:
		 * <pre>Cms_VisitorPreferences::set('color', 'blue');</pre>
		 * @documentable
		 * @param string $name Specifies the name (identifier) of the value.
		 * @param mixed $value Specifies a value to save.
		 * The value can be scalar, array or object.
		 */
		public static function set($name, $value)
		{
			$params = Phpr::$session->get('cms_visitor_preferences', array());
			$params[$name] = serialize($value);
			
			Phpr::$session->set('cms_visitor_preferences', $params);

			return;
		}

		/**
		 * Returns a value, previously saved to the with the {@link Cms_VisitorPreferences::set() set()} method. Example:
		 * <pre>$color = Cms_VisitorPreferences::get('color', 'red');</pre>
		 * @documentable
		 * @param string $name Specifies the name (identifier) of the saved value.
		 * @param mixed $default Specifies a default value.
		 * This value will be used in case if the value with the specified name doesn't
		 * exist in the session.
		 * @return mixed Returns the loaded value or the default value.
		 */
		public static function get( $name, $default = null)
		{
			$params = Phpr::$session->get('cms_visitor_preferences', array());
			if (array_key_exists($name, $params))
			{
				$value = $params[$name];
				if (strlen($value))
				try
				{
					return @unserialize($value);
				} catch (Exception $ex){}
				
				return $value;
			}
			
			return $default;
		}
	}

?>