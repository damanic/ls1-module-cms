<?

	class Cms_Analytics
	{
		private static $visitors_stats = null;
		
		public static function evalVisiorsStatistics($start, $end)
		{
			if (self::isGoogleAnalyticsEnabled())
				return self::ga_evalVisiorsStatistics($start, $end);

			return self::int_evalVisitorsStatistics($start, $end);
		}
		
		public static function getVisitorsChartData($start, $end)
		{
			if (self::isGoogleAnalyticsEnabled())
			{
				$active_days = 7;
				$start = $end->substractInterval(new Phpr_DateTimeInterval($active_days-1));
				return self::ga_evalVisitorsChartData($start, $end);
			}

			return self::int_getVisitorsChartData($start, $end);
		}
		
		public static function evalTopPages($start, $end)
		{
			if (self::isGoogleAnalyticsEnabled())
				return self::ga_evalTopPages($start, $end);

			return self::int_evalTopPages($start, $end);
		}

		public static function deleteStalePageviews($numberToKeep = null)
		{
			if  ($numberToKeep === null)
				$numberToKeep = Cms_Stats_Settings::get()->keep_pageviews;

			$cnt = Db_DbHelper::scalar("select count(*) from cms_page_visits");
			$offset = $cnt - $numberToKeep;

			if ($offset <= 0)
				return;
			
			Db_DbHelper::query("delete from cms_page_visits order by id limit $offset");
		}
		
		public static function logVisit($page, $url)
		{
			if (self::isGoogleAnalyticsEnabled())
				return;
				
			if (!Cms_Stats_Settings::get()->enable_builtin_statistics)
				return;

			$ip = Phpr::$request->getUserIp();
			if (Cms_Stats_Settings::ipIsFiltered($ip))
				return;

			$bind = array();
			$bind['ip'] = $ip;
			$bind['page_id'] = $page->id;
			$bind['visit_date'] = Phpr_Date::userDate(Phpr_DateTime::now())->getDate();
			$bind['url'] = $url;
			
			Db_DbHelper::query("insert into cms_page_visits(url, visit_date, ip, page_id) values (:url, :visit_date, :ip, :page_id)", $bind);
		}

		public static function isGoogleAnalyticsEnabled()
		{
			return Cms_Stats_Settings::get()->ga_service_enabled;
		}
		
		/*
		 * Google analytics
		 */
		
		private static function ga_evalVisiorsStatistics($start, $end)
		{
			$cache = self::initGaCache($start, $end);
			return $cache['visitors_statistics_data'];
		}
		
		private static function ga_evalVisitorsChartData($start, $end)
		{
			$cache = self::initGaCache($start, $end);
			return $cache['visitors_chart_data'];
		}
		
		private static function ga_evalTopPages($start, $end)
		{
			$cache = self::initGaCache($start, $end);
			return $cache['top_pages'];
		}
		
		public static function clearGaCache()
		{
			Db_ModuleParameters::set('cms', 'analytics', null);
		}
		
		private static function initGaCache($start, $end)
		{

			$prevCache = $cache = Db_ModuleParameters::get('cms', 'analytics');

			try
			{
				$startFormatted = $start->format(Phpr_DateTime::universalDateFormat);
				$endFormatted = $end->format(Phpr_DateTime::universalDateFormat);

				if ($cache && $cache['start'] == $startFormatted && $cache['end'] == $endFormatted)
					return $cache;

				$cache = array();
				$cache['start'] = $startFormatted;
				$cache['end'] = $endFormatted;

				$ga = new Cms_GoogleAnalytics();

				/*
				 * Fetch visitors chart data
				 */
				$prevStart = $prevEnd  = null;
				Backend_Dashboard::evalPrevPeriod($start, $end, $prevStart, $prevEnd);

				$chart_start = $end->substractInterval(new Phpr_DateTimeInterval(30));
				$data = $ga->downloadReport(array('ga:date'), array('ga:visits'), $chart_start, $end);


				$chart_data = array();
				foreach ($data->rows as $row_id => $row_info)
				{
					$label = self::parseDate($row_info[0]);
					$value =  $row_info[1];

					$chart_data[] = (object)array('record_value'=>self::strToInt($value), 'series_id'=>$label);
				}
			
				$cache['visitors_chart_data'] = $chart_data;

				/*
				 * Fetch statistics data
				 */

				$current_data = $ga->downloadReport(array('ga:date'), array('ga:visits','ga:bounces','ga:newVisits','ga:avgSessionDuration','ga:pageviews','ga:visitors'), $start, $end);
				$previous_data = $ga->downloadReport(array('ga:date'), array('ga:visits','ga:bounces','ga:newVisits','ga:avgSessionDuration','ga:pageviews','ga:visitors'), $prevStart, $prevEnd);

				$pageviews_current = $current_data->totalsForAllResults['ga:pageviews'];
				$pageviews_prev = $previous_data->totalsForAllResults['ga:pageviews'];
				
				$visits_current = $current_data->totalsForAllResults['ga:visits'];
				$visits_prev = $previous_data->totalsForAllResults['ga:visits'];
				
				$time_current =  $current_data->totalsForAllResults['ga:avgSessionDuration'];
				$time_prev = $previous_data->totalsForAllResults['ga:avgSessionDuration'];

				$bounces_current = $current_data->totalsForAllResults['ga:bounces'];
				$bounces_prev = $previous_data->totalsForAllResults['ga:bounces'];
				
				$new_visits_current = $current_data->totalsForAllResults['ga:newVisits'];
				$new_visits_prev = $previous_data->totalsForAllResults['ga:newVisits'];
			
				$cache['visitors_statistics_data'] = (object)array(
				 	'unique_visitors_current'=>$visits_current,
				 	'unique_visitors_previous'=>$visits_prev,

					'pages_per_visit_current'=>($visits_current > 0 ? $pageviews_current/$visits_current : 0),
					'pages_per_visit_previous'=>($visits_prev > 0 ? $pageviews_prev/$visits_prev : 0),
				
					'pageviews_current'=>$pageviews_current,
					'pageviews_previous'=>$pageviews_prev,

					'time_on_site_current'=>round($time_current),
					'time_on_site_previous'=>round($time_prev),

					'bounce_rate_current'=>($visits_current > 0 ? $bounces_current/$visits_current : 0),
					'bounce_rate_previous'=>($visits_prev > 0 ? $bounces_prev/$visits_prev : 0),

					'new_visits_current'=>($visits_current > 0 ? $new_visits_current/$visits_current : 0),
					'new_visits_previous'=>($visits_prev > 0 ? $new_visits_prev/$visits_prev : 0),
				);

				/*
				 * Fetch content data
				 */

				$data = $ga->downloadReport( array('ga:pagePath'), array('ga:pageviews'), $start, $end, '-ga:pageViews');
			

				$top_pages = array();
				foreach ($data->rows as $row_id => $row_data)
				{
					$url = $row_data[0];
					$visits =  $row_data[1];

					$top_pages[] = (object)array(
						'cnt'=>$visits,
						'url'=>$url
					);
				}
							
				$cache['top_pages'] = $top_pages;

				Db_ModuleParameters::set('cms', 'analytics', $cache);
				Db_ModuleParameters::set('cms', 'analytics_error', null);
			} catch (Exception $ex)
			{
				if (!$prevCache)
				{
					$prevCache = array(
						'visitors_chart_data'=>array(),
						'top_pages'=>array(),
						'visitors_statistics_data'=>(object)array(
							'unique_visitors_current'=>0,
							'unique_visitors_previous'=>0,

							'pageviews_current'=>0,
							'pageviews_previous'=>0,

							'time_on_site_current'=>0,
							'time_on_site_previous'=>0,

							'bounce_rate_current'=>0,
							'bounce_rate_previous'=>0,

							'new_visits_current'=>0,
							'new_visits_previous'=>0,
						)
					);
				}

				Db_ModuleParameters::set('cms', 'analytics_error', 'Error loading Google Analytics report. Cached data used. '.$ex->getMessage());

				return $prevCache;
			}

			return $cache;
		}
		
		private static function parseDate($date)
		{
			return substr($date, 0, 4).'-'.substr($date, 4, 2).'-'.substr($date, 6, 2);
		}
		
		private static function strToInt($value)
		{
			return str_replace(',', '', str_replace(' ', '', $value));
		}

		/*
		 * Integrated analytics
		 */

		private static function int_evalVisitorsStatistics($start, $end)
		{
			$startFormatted = $start->toSqlDate();
			$endFormatted = $end->toSqlDate();
			
			if (self::$visitors_stats !== null 
				&& self::$visitors_stats[0] == $startFormatted 
				&& self::$visitors_stats[1] == $endFormatted)
				return self::$visitors_stats[2];

			$prevEnd = $prevStart = null;
			Backend_Dashboard::evalPrevPeriod($start, $end, $prevStart, $prevEnd);

			$data = Db_DbHelper::object('select
				(select count(distinct ip) from cms_page_visits where visit_date >= :current_start and visit_date <= :current_end) as unique_visitors_current,
				(select count(distinct ip) from cms_page_visits where visit_date >= :prev_start and visit_date <= :prev_end) as unique_visitors_previous,
				
				(select count(*) from cms_page_visits where visit_date >= :current_start and visit_date <= :current_end) as pageviews_current,
				(select count(*) from cms_page_visits where visit_date >= :prev_start and visit_date <= :prev_end) as pageviews_previous
			', array(
				'current_start'=>$start->toSqlDate(),
				'current_end'=>$end->toSqlDate(),
				'prev_start'=>$prevStart->toSqlDate(),
				'prev_end'=>$prevEnd->toSqlDate()
			));
			
			self::$visitors_stats = array($startFormatted, $endFormatted, $data);
			
			return $data;
		}

		private static function int_getVisitorsChartData($start, $end)
		{
			$query = "select 
					count(distinct ip) as record_value,
					report_date as series_id
				from 
					report_dates
				left join 
					cms_page_visits on visit_date=report_date
				where report_date >= :start and report_date <= :end
				group by report_date
				order by report_date";

			return Db_DbHelper::objectArray($query, array('start'=>$start, 'end'=>$end));
		}

		private static function int_evalTopPages($start, $end)
		{
			$count = 5;
			
			return Db_DbHelper::objectArray("select url, count(*) as cnt from cms_page_visits
				where visit_date >= :start and visit_date <= :end
				group by url
				order by 2 desc
				limit 0, $count",
				array(
					'start'=>$start->toSqlDate(),
					'end'=>$end->toSqlDate()
				)
			);
		}

	}

?>