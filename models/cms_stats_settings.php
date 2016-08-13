<?php

class Cms_Stats_Settings extends Backend_SettingsRecord
{
	public $table_name = 'cms_stats_settings';
	public static $obj = null;

	public $keep_pageviews = 1000000;
	public $custom_columns = array('captcha_value'=>db_text, 'captcha_token'=>db_text);

	public $has_many = array(
		'ga_json_key'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Cms_Stats_Settings'", 'order'=>'id', 'delete'=>true),
	);

	public static function get($className = null, $init_columns = true)
	{
		if (self::$obj !== null)
			return self::$obj;

		return self::$obj = parent::get('Cms_Stats_Settings');
	}

	public function define_columns($context = null)
	{
		if (!strlen($this->ga_site_speed_sample_rate))
			$this->ga_site_speed_sample_rate = 1;

		$this->validation->setFormId('settings_form');

		$this->define_column('ga_service_enabled', 'Enable Google Analytics integration');
		$this->define_column('ga_enabled', 'Enable Google Analytics tracking');
		$this->define_column('ga_siteid', 'View ID (profile)')->validation()->fn('trim');
		$this->define_column('ga_property_id', 'Web Property ID')->validation()->fn('trim');

		$this->define_multi_relation_column('ga_json_key','ga_json_key', 'Google Analytics key file','@name')->invisible();


		$this->define_column('ga_username', 'Email')->validation()->fn('trim')->Email(true);
		$this->define_column('ga_password', 'Password')->validation()->fn('trim');
		$this->define_column('ga_site_speed_sample_rate', 'Site Speed sample rate, %')->validation()->fn('trim')->numeric();
		$this->define_column('ga_domain_name', 'Domain name')->validation()->fn('trim');
		$this->define_column('ga_enable_doubleclick_remarketing', 'Enable DoubleClick Remarketing ')->validation()->fn('trim');

		$this->define_column('enable_builtin_statistics', 'Enable built-in statistics');
		$this->define_column('keep_pageviews', 'Number of pageviews to keep')->validation()->fn('trim')->required();
		$this->define_column('ip_filters', 'IP Filters')->validation()->fn('trim');

		$this->define_column('dashboard_paid_only', 'Display only paid orders in the main chart');
		$this->define_column('dashboard_display_today', 'Display a current date in reports and charts');
	}

	public function define_form_fields($context = null)
	{
		$this->add_form_field('ga_service_enabled')->comment('Turning Google Analytics integration ON you allow the system to download reports from your Google Analytics account. If you disable this feature, the built-in statistics will be used.<br/><br/><strong>Important!</strong> In order to track E-Commerce transaction you need to create a service account in your google developers console. This will give you a client email address that you can add as a user in your analytics account and a JSON keyfile that you can upload below for authentication.', 'below', true)->tab('Google Analytics')->renderAs(frm_onoffswitcher);

		$extraFieldClass = $this->ga_service_enabled ? 'ga_service_enabled' : 'hidden ga_service_enabled';
		$this->add_form_field('ga_siteid')->tab('Google Analytics')->cssClassName($extraFieldClass)->comment('The profile/view ID can be found in your GA admin area:  admin -> view settings','above', true);

		$extraFieldClass = $this->ga_service_enabled ? 'ga_service_enabled separatedField' : 'hidden ga_service_enabled';
		$this->add_form_partial('ga_hint')->tab('Google Analytics');
		$this->add_form_field( 'ga_json_key' )->tab( 'Google Analytics' )->cssClassName($extraFieldClass)->renderAs( frm_file_attachments )->renderFilesAs( 'single_file' )->addDocumentLabel( 'Upload Authentication Keyfile (JSON)' )->noAttachmentsLabel( 'No Authentication keyfile uploaded' )->noLabel();


		$this->add_form_field('ga_enabled')->comment('Turning Google Analytics tracking ON will insert tracking code into your pages', 'below', true)->tab('Google Analytics')->renderAs(frm_onoffswitcher);


		$extraFieldClass = ($this->ga_enabled) ? 'ga_enabled' : 'hidden ga_enabled';

		$this->add_form_field('ga_property_id')->tab('Google Analytics')->cssClassName($extraFieldClass);
		$this->add_form_field('ga_domain_name')->tab('Google Analytics')->cssClassName($extraFieldClass)->comment('Specify the store domain name to use <a href="http://code.google.com/apis/analytics/docs/tracking/gaTrackingSite.html" target="_blank">Google Analytics cross-domain tracking feature</a>. Leave the field blank to disable the cross-domain tracking for this website.', 'above', true);
		$this->add_form_field('ga_site_speed_sample_rate')->tab('Google Analytics')->cssClassName($extraFieldClass)->comment('Defines a sample set size for Site Speed data collection. If you have a relatively small number of daily visitors to your site, such as 100,000 or fewer, you might want to adjust the sampling to a larger rate. This will provide increased granularity for page load time and other Site Speed metrics.', 'above');
		$this->add_form_field('ga_enable_doubleclick_remarketing')->tab('Google Analytics')->cssClassName($extraFieldClass)->comment('Enable this feature if you utilize DoubleClick Remarketing with Google Analytics.', 'above');

//			$this->add_form_field('ga_username', 'left')->tab('Google Analytics')->comment('Email you use to log into Google Analytics', 'above')->cssClassName($extraFieldClass);
//			$this->add_form_field('ga_password', 'right')->tab('Google Analytics')->renderAs(frm_password)->comment('Password you use to log into Google Analytics', 'above')->cssClassName($extraFieldClass);



//			$this->add_form_custom_area('ga_captcha')->tab('Google Analytics');

		$extraFieldClass = $this->enable_builtin_statistics ? 'separatedField' : null;
		$this->add_form_field('enable_builtin_statistics')->comment('Turn off the built-in statistics if you don\'t want LemonStand to store traffic information in the database.', 'below', true)->tab('Built-in Statistics')->renderAs(frm_onoffswitcher)->cssClassName($extraFieldClass);

		$extraFieldClass = $this->enable_builtin_statistics ? null : 'hidden';
		$this->add_form_field('keep_pageviews')->comment('How many pageviews to keep in the database. Large number of pageviews may slowdown the Dashboard page loading.', 'above')->tab('Built-in Statistics')->renderAs(frm_dropdown)->cssClassName($extraFieldClass);;
		$this->add_form_field('ip_filters')->tab('Built-in Statistics')->cssClassName($extraFieldClass);;

		$this->add_form_field('dashboard_paid_only')->tab('Dashboard')->comment('Use this checkbox if you want to see only paid order totals in the main dashboard chart.');
		$this->add_form_field('dashboard_display_today')->tab('Dashboard')->comment('Use this checkbox if you to include a current date to the dashboard reports and charts. If the Google Analytics integration enabled, the visitors statistic could be wrong for a current date.');
	}

	public function get_keep_pageviews_options($key_value = -1)
	{
		return array(
			50000=>50000,
			100000=>100000,
			500000=>500000,
			1000000=>1000000
		);
	}

	public function get_ga_tracking_code()
	{
		if (!strlen($this->ga_site_speed_sample_rate))
			$this->ga_site_speed_sample_rate = 1;

		$propertyId = $this->ga_property_id;

		$extra_params = '';
		if ($this->ga_domain_name)
			$extra_params .= ", cookieDomain: '".Core_String::js_encode($this->ga_domain_name)."', 'legacyCookieDomain': '".Core_String::js_encode($this->ga_domain_name)."'";

		$result = "\n\t<script type=\"text/javascript\">\n
						\t(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
						\t(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
						\tm=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
						\t})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
						
						\tga('create', '$propertyId', {'siteSpeedSampleRate': $this->ga_site_speed_sample_rate $extra_params});
						";

		return $result;
	}

	public function get_ga_tracker_close_declaration()
	{
		$close = '';
		if( $this->ga_enable_doubleclick_remarketing ){
			$close ="\tga('require', 'displayfeatures');\n";
		}
		$close .= "\tga('send', 'pageview');\n";
		$close.="</script>\n";
		return $close;
	}

	public function get_ga_ec_tracking_code($order)
	{
		$order_tax = $order->shipping_tax + $order->goods_tax;

		$company_name = Shop_CompanyInformation::get()->name;
		if (!strlen($company_name))
			$company_name = 'LemonStand';

		$company_name = Core_String::js_encode($company_name);

		$result = "\tga('require', 'ecommerce');\n";
		$result .= "\tga('ecommerce:addTransaction', {
							  'id': '{$order->id}',              
							  'affiliation': '{$company_name}',   
							  'revenue': '{$order->total}',               
							  'shipping': '{$order->shipping_quote}',                 
							  'tax': '{$order_tax}'                  
							});\n";

		foreach ($order->items as $item)
		{
			$sku = Core_String::js_encode($item->product->sku);
			$name = Core_String::js_encode($item->product->name);
			$category = Core_String::js_encode($item->product->category_list[0]->name);
			$price = $item->eval_unit_total_price();
			$result .= "\tga('ecommerce:addItem', {
							  'id': '{$order->id}',
							  'name': '{$name}',
							  'sku': '{$sku}',
							  'category': '{$category}',
							  'price': '{$price}',
							  'quantity': '{$item->quantity}'
							});\n";

		}

		$result .= "\tga('ecommerce:send');\n";

		return $result;
	}

	/*
	 * Validation
	 */

	public function before_update($session_key = null)
	{
		if ($this->ga_service_enabled) {


			$key_file_path = is_object( $this->ga_json_key[0] ) ? $this->ga_json_key[0]->getFileSavePath( $this->ga_json_key[0]->disk_name ) : false;

			$keyfiles = $this->list_related_records_deferred( 'ga_json_key', post( 'edit_session_key' ) );
			if ( $keyfiles ) {
				foreach ( $keyfiles as $keyfile ) {
					$key_file_path = $keyfile->getFileSavePath( $keyfile->disk_name );
				}
			}

			if ( !$key_file_path ) {
				throw new Phpr_ApplicationException( 'You must upload a Google Authentication key file to enable Google Analytics integration.' );
			}

			try {
				//check authentication
				$ga = new Cms_GoogleAnalytics();
				$ga->load_keyfile($key_file_path);
				$ga->login();
			} catch ( Exception $ex ) {
				if ( $ex instanceof Cms_GaCaptchaException ) {
					throw $ex;
				}

				throw new Phpr_ApplicationException( 'Error logging into Google Analytics. ' . $ex->getMessage() );
			}
		}
	}

	/*
	 * IP filters
	 */

	public function getIpFilters($filters = null)
	{
		$filters = explode(',', $filters === null ? $this->ip_filters : $filters);

		$result = array();
		foreach ($filters as $filter)
		{
			if (!strlen($filter))
				continue;

			$filter = explode('<>', $filter);
			$filter = (object)array('ip'=>$filter[0], 'name'=>$filter[1]);
			$result[] = $filter;
		}

		return $result;
	}

	public function addIpFilter($filters, $ip, $name)
	{
		$filters = $this->getIpFilters($filters);

		foreach ($filters as $filter)
		{
			if ($filter->ip == $ip)
				throw new Phpr_ApplicationException("Filter $ip already exists");
		}

		$filters[] = (object)array('ip'=>$ip, 'name'=>$name);
		$this->implodeFilters($filters);
	}

	public function deleteIpFilter($filters, $ip)
	{
		$filters = $this->getIpFilters($filters);

		$new_filters = array();
		foreach ($filters as $filter)
		{
			if ($filter->ip != $ip)
				$new_filters[] = $filter;
		}

		$this->implodeFilters($new_filters);
	}

	protected function implodeFilters($filters)
	{
		$result = array();
		foreach ($filters as $filter)
			$result[] = $filter->ip.'<>'.$filter->name;

		$this->ip_filters = implode(',', $result);
	}

	public static function getLazy()
	{
		if (self::$obj)
			return self::$obj;
		else
		{
			$obj = new Cms_Stats_Settings(null, array('no_column_init'=>true, 'no_validation'=>true));
			self::$obj = $obj->find();
			if (!self::$obj)
				self::$obj = $obj;

			return self::$obj;
		}
	}

	public static function ipIsFiltered($ip)
	{
		$obj = self::getLazy();

		$filters = $obj->getIpFilters();
		foreach ($filters as $filter)
		{
			$filter_ip = str_replace('.', '\.', $filter->ip);
			$filter_ip = str_replace('*', '.*', $filter_ip);

			if (preg_match('/^'.$filter_ip.'$/', $ip))
				return true;
		}

		return false;
	}
}

?>