<?php

	/**
	 * This class converts some CMS controller methods to global Twig functions.
	 */
	class Cms_TwigControllerExtension extends Twig_Extension
	{
		private $controller;
		
		public function __construct($controller)
		{
			$this->controller = $controller;
		}
		
		public function getName()
		{
			return 'CMS Controller';
		}
		
		public function getFunctions()
		{
			return array(
				'render_partial',
				'js_combine',
				'css_combine',
				'render_page',
				'render_block',
				'render_head',
				'request_param',
				'redirect_url'
			);
		}
		
		public function redirect_url($url, $index = 0)
		{
			return $this->controller->redirect_url($url, $index);
		}
		
		public function render_partial($name, $params = array(), $options = array('return_output' => false))
		{
			return $this->controller->render_partial($name, $params, $options);
		}
		
		public function js_combine($files, $options = array(), $show_tag = true)
		{
			return $this->controller->js_combine($files, $options, $show_tag);
		}

		public function css_combine($files, $options = array(), $show_tag = true)
		{
			return $this->controller->css_combine($files, $options, $show_tag);
		}

		public function render_page()
		{
			return $this->controller->render_page();
		}
		
		public function render_block($block_code, $default = null)
		{
			return $this->controller->render_block($block_code, $default);
		}
		
		public function render_head()
		{
			return $this->controller->render_head();
		}
		
		public function request_param($index, $default = null)
		{
			return $this->controller->request_param($index, $default);
		}
	}

?>