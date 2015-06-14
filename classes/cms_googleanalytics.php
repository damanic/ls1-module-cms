<?

	class Cms_GoogleAnalytics
	{
		const report_visitors_overview = 'VisitorsOverviewReport';
		const report_content = 'ContentReport';
		const report_dashboard = 'DashboardReport';

		public $username;
		public $password;
		public $siteId;
		
		public $captcha_value;
		public $captcha_token;

		protected $auth_url = 'https://www.google.com/accounts/ClientLogin';
		protected $feed_url = 'https://www.google.com/analytics/feeds/data';

		protected $isLoggedIn = false;
		protected $auth_ticket = null;

		public function login()
		{
			if ($this->isLoggedIn)
				return;

			$data = array(
			    'accountType' => 'GOOGLE',
			    'Email' => $this->username,
			    'Passwd' => $this->password,
			    'service' => 'analytics',
			    'source' => ''
			);
			
			if (strlen($this->captcha_token))
			{
				$data['logintoken'] = $this->captcha_token;
				$data['logincaptcha'] = trim($this->captcha_value);
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->auth_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
			$output = curl_exec($ch);
			$info = curl_getinfo($ch);
			curl_close($ch);

			$this->auth_ticket = null;
			
			if($info['http_code'] == 200) 
			{
				$matches = array();
				preg_match('/Auth=(.*)/', $output, $matches);
				if(isset($matches[1]))
					$this->auth_ticket = $matches[1];
			} else 
			{
				$matches = array();
				if (preg_match('/CaptchaToken=(.*)/', $output, $matches))
				{
					if (isset($matches[1]))
					{
						$captcha_token = $matches[1];
						preg_match('/CaptchaUrl=(.*)/', $output, $matches);
						$catpcha_url = $matches[1];
						
						throw new Cms_GaCaptchaException('Error logging into Google Analytics account. Please update Google Analytics configuration.', 'http://www.google.com/accounts/'.$catpcha_url, $captcha_token);
					}
				}
				
				throw new Phpr_SystemException('Error connecting to Google Analytics. Google error: '.$output);
			}
				
			if (!$this->auth_ticket)
				throw new Phpr_SystemException('Error connecting to Google Analytics. Authentication ticket not found in Google API response.');

			$this->isLoggedIn = true;
		}
		
		protected function url_encode_array(&$fields)
		{
			$result = array();
			foreach ($fields as $name=>$value)
				$result[] = $name.'='.urlencode($value);
				
			return implode('&', $result);
		}
		
		public function downloadReport($dimensions, $metrics, $start, $end, $sort = null)
		{
			$this->login();

			$get_fields = array(
				'ids'=>'ga:'.$this->siteId,
				'dimensions'=>implode(',', $dimensions),
				'metrics'=>implode(',', $metrics),
				'start-date'=>$start->format('%Y-%m-%d'),
				'end-date'=>$end->format('%Y-%m-%d')
			);

			if ($sort)
				$get_fields['sort'] = $sort;
			
			$url = $this->feed_url.'?'.$this->url_encode_array($get_fields);
			$headers = array('Authorization: GoogleLogin auth='.$this->auth_ticket);
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
			
			$response = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

			curl_close($ch);

			if ($code != 200)
				throw new Phpr_ApplicationException('Error downloading Google Analytics report. Invalid response from Google Analytics API. Response code: '.$code);

			if (!preg_match(',\</feed\>\s*$,', $response))
				throw new Phpr_ApplicationException('Error downloading Google Analytics report. Response text is not an XML document.');
				
			return $response;
		}
	}

?>