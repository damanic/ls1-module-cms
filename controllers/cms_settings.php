<?

	class Cms_Settings extends Backend_SettingsController
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $form_model_class = '';
		protected $access_for_groups = array(Users_Groups::admin);

		public function stats()
		{
			$this->app_page_title = 'Statistics';
			$this->form_model_class = 'Cms_Stats_Settings';
			
			$settings = Cms_Stats_Settings::get();
			$settings->init_columns_info();
			$settings->define_form_fields();
			$this->viewData['settings'] = $settings;
		}

		protected function stats_onSave()
		{
			try
			{
				$settings = Cms_Stats_Settings::get();
				$settings->init_columns_info();
				$settings->define_form_fields();

				try
				{
					$settings->save(post('Cms_Stats_Settings'));
				} 
				catch (Cms_GaCaptchaException $ex)
				{
					$this->viewData['captcha_url'] = $ex->captcha_url;
					$this->viewData['captcha_token'] = $ex->captcha_token;
					
					$this->renderMultiple(array(
						'ga_captcha_fields'=>'@_ga_captcha_fields'
					));
					
					return;
				}
				
				Cms_Analytics::deleteStalePageviews($settings->keep_pageviews);
				Cms_Analytics::clearGaCache();

				Phpr::$session->flash['success'] = 'Statistics settings have been saved.';
				Phpr::$response->redirect(url('system/settings'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function stats_onLoadFilterForm()
		{
			$this->renderPartial('add_filter_form');
		}
		
		protected function stats_onAddFilter()
		{
			try
			{
				$this->validation->add('filter_ip', 'IP Address')->fn('trim')
					->required("Please specify an IP address")
					->regexp('/^[0-9\.\*]*$/i', "IP address can only contain numbers, dots and asterisk characters.");
					
				$this->validation->add('filter_name', 'Filter Name')->fn('trim')
					->required("Please specify a filter name");
					
				if (!$this->validation->validate($_POST))
					$this->validation->throwException();

				$ip = $this->validation->fieldValues['filter_ip'];
				$name = $this->validation->fieldValues['filter_name'];

				$form_data = post('Cms_Stats_Settings');
				$settings = Cms_Stats_Settings::get();
				$settings->addIpFilter($form_data['ip_filters'], $ip, $name);
				$this->viewData['form_model'] = $settings;
				
				$this->renderPartial('ip_filter_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		protected function stats_onDeleteIpFilter()
		{
			try
			{
				$form_data = post('Cms_Stats_Settings');
				$settings = Cms_Stats_Settings::get();
				$settings->deleteIpFilter($form_data['ip_filters'], post('ip'));
				$this->viewData['form_model'] = $settings;
				
				$this->renderPartial('ip_filter_list');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function config()
		{
			$this->app_page_title = 'CMS Settings';
			$this->form_model_class = 'Cms_SettingsManager';
			
			$settings = Cms_SettingsManager::get();
			$settings->init_columns_info();
			$settings->define_form_fields();
			$this->viewData['settings'] = $settings;
		}
		
		protected function config_onSave()
		{
			try
			{
				$settings = Cms_SettingsManager::get();
				$settings->init_columns_info();
				$settings->define_form_fields();

				$settings->save(post('Cms_SettingsManager'));

				Phpr::$session->flash['success'] = 'CMS settings have been successfully saved.';
				Phpr::$response->redirect(url('system/settings'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}
	
?>