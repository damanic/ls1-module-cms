<?php

	class Cms_Twig extends Core_Twig
	{
		private $controller_extension;
		private $controller;
		
		public function __construct($controller)
		{
			$this->controller = $controller;
			parent::__construct();
		}
		
		protected function configure_environment()
		{
			parent::configure_environment();
			
			$this->controller_extension = new Cms_TwigControllerExtension($this->controller);
			$this->environment->addExtension($this->controller_extension);

			/*
			 * Register CMS functions
			 */
			
			$html_safe = array('is_safe' => array('html'));

			$this->environment->addFunction('content_block', new Twig_Function_Function('content_block', $html_safe));
			$this->environment->addFunction('option_state', new Twig_Function_Function('option_state', $html_safe));
			$this->environment->addFunction('checkbox_state', new Twig_Function_Function('checkbox_state', $html_safe));
			$this->environment->addFunction('radio_state', new Twig_Function_Function('radio_state', $html_safe));
			$this->environment->addFunction('post', new Twig_Function_Function('post'));
			$this->environment->addFunction('flash', new Twig_Function_Function('flash'));
			$this->environment->addFunction('flash_message', new Twig_Function_Function('flash_message', $html_safe));
			$this->environment->addFunction('global_content_block', new Twig_Function_Function('global_content_block', $html_safe));
			$this->environment->addFunction('include_resources', new Twig_Function_Function('include_resources', $html_safe));
			$this->environment->addFunction('process_ls_tags', new Twig_Function_Function('process_ls_tags', $html_safe));
			$this->environment->addFunction('theme_resource_url', new Twig_Function_Function('theme_resource_url'));
			
			$functions = $this->controller_extension->getFunctions();
			foreach ($functions as $function)
				$this->environment->addFunction($function, new Twig_Function_Method($this->controller_extension, $function, array('is_safe' => array('html'))));
				
			/*
			 * Allow other modules to register Twig extensions
			 */
			
			Backend::$events->fireEvent('cms:onRegisterTwigExtension', $this->environment);
		}
		
		protected function get_cache_dir()
		{
			$cache_dir = parent::get_cache_dir();
			
			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_active_theme();
				if ($theme)
				{
					$cache_dir .= '/'.$theme->code;
					if (!file_exists($cache_dir) || !is_dir($cache_dir))
					{
						if (!@mkdir($cache_dir, Phpr_Files::getFolderPermissions()))
							throw new Phpr_ApplicationException('Error creating Twig cache directory (temp/twig_cache/'.$theme->code.')');
					}
				}
			}

			return $cache_dir;
		}
	}

?>