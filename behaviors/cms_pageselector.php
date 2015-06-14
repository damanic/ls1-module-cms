<?php

	class Cms_PageSelector extends Phpr_ControllerBehavior
	{
		public function __construct($controller)
		{
			parent::__construct($controller);

			$this->addEventHandler('on_cms_page_selector_show_popup');
			$this->addEventHandler('on_cms_page_selector_on_apply');
			Backend::$events->add_event('core:onAfterFormRecordUpdate', $this, 'cms_page_selector_save_model_data');
			Backend::$events->add_event('core:onAfterFormRecordCreate', $this, 'cms_page_selector_save_model_data');
		}
		
		public function cms_page_selector_render_field($model, $field)
		{
			if (!Cms_Theme::is_theming_enabled())
				$this->_controller->formRenderFieldElementPartial($model, $field);
			else
			{
				$this->renderPartial('page_selector', array('model'=>$model, 'field'=>$field));
			}
		}
		
		public function on_cms_page_selector_show_popup()
		{
			$data = post('reference_data');
			$selected_ids = array();
			if (strlen($data))
			{
				try {
					$selected_ids = unserialize(base64_decode($data));
				} catch (exception $ex) {}
			}

			$themes = Cms_Theme::create()->order('name')->find_all();
			
			$this->renderPartial('page_selector_form', array(
				'themes'=>$themes, 
				'selected_pages'=>$selected_ids, 
				'reference_name'=>post('reference_name'), 
				'container'=>post('container'),
				'model_class'=>post('model_class'),
				'label'=>post('label'),
				'field_name'=>post('field_name')
			));
		}
		
		public function cms_page_selector_hidden_container_id($model, $session_key, $db_name)
		{
			return 'page_selector_data_'.$db_name.get_class($model).'-'.str_replace('.', '-', $session_key);
		}
		
		public function cms_page_selector_hidden_label_id($model, $session_key, $db_name)
		{
			return 'page_selector_label_'.$db_name.get_class($model).'-'.str_replace('.', '-', $session_key);
		}
		
		public function cms_page_selector_render_data($model, $db_name)
		{
			$this->renderPartial('reference_data', array('model'=>$model, 'reference'=>$db_name));
		}
		
		public function cms_page_selector_render_partial($name, $data = array())
		{
			$this->renderPartial($name, $data);
		}
		
		public function on_cms_page_selector_on_apply()
		{
			/*
			 * Update the hidden field
			 */
			$this->_controller->preparePartialRender(post('container_id'));
			$theme_pages = post('page', array());
			$references = array();
			$reference_value = null;
			if (is_array($theme_pages))
			{
				foreach ($theme_pages as $theme_id=>$page_id)
				{
					if ($page_id)
					{
						$references[] = $page_id;
						if (!$reference_value)
							$reference_value = $page_id;
					}
				}
			}
			
			$this->renderPartial('reference_data', array(
				'update'=>true,
				'references'=>$references, 
				'field_name'=>post('field_name'), 
				'reference_value'=>$reference_value,
				'reference_name'=>post('reference_name'),
				'model_class'=>post('model_class')
			));
			
			/*
			 * Update the control text
			 */
			$this->_controller->preparePartialRender(post('label_id'));
			$reference_list = array();
			foreach ($references as $page_id)
			{
				$page = Cms_Page::find_by_id($page_id);
				if (!$page)
					continue;
					
				$theme = $page->get_theme();
				if (!$theme)
					continue;
				
				$page_data = array(
					'title'=>$page->title,
					'url'=>$page->url,
					'theme_name'=>$theme->name
				);

				$reference_list[$page_id] = (object)$page_data;
			}

			echo h(Cms_PageReference::references_as_string($reference_list));
		}
		
		public function cms_page_selector_save_model_data($controller, $model)
		{
			$posted_data = post(get_class($model), array());
			if (!$posted_data)
				return;

			if (!array_key_exists('cms_page_selector', $posted_data))
				return;
				
			$page_data = $posted_data['cms_page_selector'];
			foreach ($page_data as $reference_id=>$data)
			{
				/*
				 * Update reference data
				 */
				try
				{
					$bind = array(
						'object_class_name'=>get_class($model),
						'object_id'=>$model->get_primary_key_value(),
						'reference_name'=>$reference_id
					);
					Db_DbHelper::query('delete from cms_page_references where object_class_name=:object_class_name and object_id=:object_id and reference_name=:reference_name', $bind);
					
					$data = unserialize(base64_decode($data));

					foreach ($data as $page_id)
					{
						$bind['page_id'] = $page_id;
						Db_DbHelper::query('insert into cms_page_references(object_class_name, object_id, reference_name, page_id) values (:object_class_name, :object_id, :reference_name, :page_id)', $bind);
					}
				} catch (exception $ex) {}
				
			}
		}
	}
	
?>