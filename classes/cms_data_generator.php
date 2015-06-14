<?

	class Cms_Data_Generator
	{
		// Cms_Data_Generator::generate_page_visits_file(
		// 	PATH_APP.'/logs/visits.sql', 
		// 	new Phpr_DateTime('2008-01-01 00:00:00'), 
		// 	500, 
		// 	365);
		public static function generate_page_visits_file($sql_file_path, $start_date, $visits_per_day, $days)
		{
			if (file_exists($sql_file_path))
				unlink($sql_file_path);
			
			set_time_limit(3600);
			
			if ( !($fp = @fopen( $sql_file_path, 'a' )) )
				throw new Phpr_ApplicationException('Error creating file');

			@fwrite( $fp, "truncate table cms_page_visits; \n");
			@fwrite( $fp, "insert into cms_page_visits(url, visit_date, ip, page_id) values \n");

			$page_id_urls = array();
			$pages = Cms_Page::create()->find_all();
			foreach ($pages as $page)
			{
				$page_id_urls[$page->id] = $page->url;
			}

			$page_count = count($pages);
			$page_ids = array_keys($page_id_urls);

			$ips = array();

			$current_date = $start_date;
			$interval = new Phpr_DateTimeInterval(1);
			for ($day_index = 1; $day_index <= $days; $day_index++)
			{
				$day_visits = rand($visits_per_day - round($visits_per_day/3), $visits_per_day + round($visits_per_day/3));
				$day_visits = sin(deg2rad(($day_index % 7)*25.7))*$day_visits + rand($day_visits/5, $day_visits/5);
				$date = $current_date->toSqlDate();

				for ($visit_index = 1; $visit_index <= $day_visits; $visit_index++)
				{
					$page_index = rand(0, $page_count-1);
					$page_id = $page_ids[$page_index];
					$url = $page_id_urls[$page_id];
					
					if (rand(1, 10) < 2 || !count($ips))
						$ips[] = $ip = self::genIp();
					else
						$ip = $ips[rand(0, count($ips)-1)];

					$str = "('$url', '$date', '$ip', $page_id)";
					if (!($day_index == $days && $visit_index = $day_visits))
						$str .= ",\n";

					@fwrite($fp, $str);
				}

				$current_date = $current_date->addInterval($interval);
			}

			fclose( $fp );
			return true;
		}
		
		protected static function genIp()
		{
			return rand(1,255).'.'.rand(1,255).'.'.rand(1,255).'.'.rand(1,255);
		}
		
		public static function generate_totals_chart_data($visit_data, $start_date)
		{
			$data = 	'a:31:{i:0;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-23";s:12:"series_value";s:10:"2009-03-23";s:12:"record_value";i:5634;}i:1;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-24";s:12:"series_value";s:10:"2009-03-24";s:12:"record_value";i:1386;}i:2;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-25";s:12:"series_value";s:10:"2009-03-25";s:12:"record_value";i:4878;}i:3;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-26";s:12:"series_value";s:10:"2009-03-26";s:12:"record_value";i:5307;}i:4;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-27";s:12:"series_value";s:10:"2009-03-27";s:12:"record_value";i:2560;}i:5;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-28";s:12:"series_value";s:10:"2009-03-28";s:12:"record_value";i:1687;}i:6;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-29";s:12:"series_value";s:10:"2009-03-29";s:12:"record_value";i:1732;}i:7;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-30";s:12:"series_value";s:10:"2009-03-30";s:12:"record_value";i:3260;}i:8;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-03-31";s:12:"series_value";s:10:"2009-03-31";s:12:"record_value";i:3134;}i:9;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-01";s:12:"series_value";s:10:"2009-04-01";s:12:"record_value";i:4106;}i:10;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-02";s:12:"series_value";s:10:"2009-04-02";s:12:"record_value";i:3468;}i:11;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-03";s:12:"series_value";s:10:"2009-04-03";s:12:"record_value";i:2401;}i:12;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-04";s:12:"series_value";s:10:"2009-04-04";s:12:"record_value";i:1818;}i:13;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-05";s:12:"series_value";s:10:"2009-04-05";s:12:"record_value";i:649;}i:14;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-06";s:12:"series_value";s:10:"2009-04-06";s:12:"record_value";i:3878;}i:15;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-07";s:12:"series_value";s:10:"2009-04-07";s:12:"record_value";i:6180;}i:16;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-08";s:12:"series_value";s:10:"2009-04-08";s:12:"record_value";i:4271;}i:17;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-09";s:12:"series_value";s:10:"2009-04-09";s:12:"record_value";i:1168;}i:18;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-10";s:12:"series_value";s:10:"2009-04-10";s:12:"record_value";i:5855;}i:19;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-11";s:12:"series_value";s:10:"2009-04-11";s:12:"record_value";i:2209;}i:20;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-12";s:12:"series_value";s:10:"2009-04-12";s:12:"record_value";i:2438;}i:21;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-13";s:12:"series_value";s:10:"2009-04-13";s:12:"record_value";i:1308;}i:22;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-14";s:12:"series_value";s:10:"2009-04-14";s:12:"record_value";i:3339;}i:23;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-15";s:12:"series_value";s:10:"2009-04-15";s:12:"record_value";i:3892;}i:24;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-16";s:12:"series_value";s:10:"2009-04-16";s:12:"record_value";i:2409;}i:25;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-17";s:12:"series_value";s:10:"2009-04-17";s:12:"record_value";i:2576;}i:26;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-18";s:12:"series_value";s:10:"2009-04-18";s:12:"record_value";i:2617;}i:27;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-19";s:12:"series_value";s:10:"2009-04-19";s:12:"record_value";i:538;}i:28;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-20";s:12:"series_value";s:10:"2009-04-20";s:12:"record_value";i:2421;}i:29;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-21";s:12:"series_value";s:10:"2009-04-21";s:12:"record_value";i:3890;}i:30;O:8:"stdClass":5:{s:10:"graph_code";s:6:"amount";s:10:"graph_name";s:6:"amount";s:9:"series_id";s:10:"2009-04-22";s:12:"series_value";s:10:"2009-04-22";s:12:"record_value";i:2087;}}';
			
			return unserialize($data);
			
			$visitors_per_date = array();
			foreach ($visit_data as &$obj)
			{
				$obj->record_value *= 10;
				$visitors_per_date[$obj->series_id] = $obj->record_value;
			}

			$sales_data = array();
			$current_date = $start_date;
			$interval = new Phpr_DateTimeInterval(1);
			$days = 31;
			for ($day_index = 1; $day_index <= $days; $day_index++)
			{

				$date_str = $current_date->format(Phpr_DateTime::universalDateFormat);
				$value = $visitors_per_date[$date_str]*2*rand(1,5) + rand(1000,2000)-1000;
				
				$entry = array(
					'graph_code'=>'amount',
					'graph_name'=>'amount',
					'series_id'=>$date_str,
					'series_value'=>$date_str,
					'record_value'=>$value
				);
				
				$sales_data[] = (object)$entry;
				$current_date = $current_date->addInterval($interval);
			}
			
			return $sales_data;
		}
	}
?>