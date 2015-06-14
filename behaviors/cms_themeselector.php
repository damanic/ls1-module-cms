<?php

	class Cms_ThemeSelector extends Phpr_ControllerBehavior
	{
		public function __construct($controller)
		{
			parent::__construct($controller);
			$this->_controller->addJavaScript('/modules/cms/behaviors/cms_themeselector/resources/javascript/cms_themeselector.js?'.module_build('cms'));

			$this->addEventHandler('on_cms_theme_selector_load_popup');
			$this->addEventHandler('on_cms_theme_selector_apply');
		}
		
		public function on_cms_theme_selector_load_popup()
		{
			$themes = Cms_Theme::create()->order('name')->find_all();
			$edit_theme = Cms_Theme::get_edit_theme();
			$current_theme_id = $edit_theme ? $edit_theme->id : null;
			
			$this->renderPartial('theme_selector_form', array('themes'=>$themes, 'current_theme_id'=>$current_theme_id));
		}
		
		public function on_cms_theme_selector_apply()
		{
			$edit_theme = Cms_Theme::set_edit_theme(post('theme_id'));
		}
	}
?>