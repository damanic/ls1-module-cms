<?

	class Cms_GoogleAnalytics
	{
		const report_visitors_overview = 'VisitorsOverviewReport';
		const report_content = 'ContentReport';
		const report_dashboard = 'DashboardReport';

		public $keyfile_path;
		public $siteId;

		protected $auth_url = 'https://www.googleapis.com/auth/analytics.readonly';

		protected $isLoggedIn = false;
		protected $client;
		protected $private_key_id;
		protected $private_key;
		protected $client_email;
		protected $client_id;



		public function __construct(){
			require_once PATH_APP.'/modules/cms/thirdpart/google-api/autoload.php';
			$settings = Cms_Stats_Settings::get();
			$this->siteId = $settings->ga_siteid;

			$this->key_file_path = is_object( $settings->ga_json_key[0] ) ? $settings->ga_json_key[0]->getFileSavePath( $settings->ga_json_key[0]->disk_name ) : false;
			if($this->key_file_path){
				$this->load_keyfile($this->key_file_path);
			}

		}

		public function load_keyfile($file_path){
			if(!file_exists($file_path)){
				throw new Phpr_ApplicationException('Could not load Google Analytics key file');
			}

			$key_data = file_get_contents($file_path);
			$key_data_array = json_decode($key_data, true);
			if(!is_array($key_data_array) || !isset($key_data_array['client_email']) || $key_data_array['type'] !== 'service_account'){
				throw new Phpr_ApplicationException( 'The Google authentication key file is not valid' );
			}

			$this->private_key_id = $key_data_array['private_key_id'];
			$this->private_key = $key_data_array['private_key'];
			$this->client_email = $key_data_array['client_email'];
			$this->client_id = $key_data_array['client_id'];

		}

		public function login()
		{
			if ($this->isLoggedIn)
				return;

			try {

				$credentials = new Google_Auth_AssertionCredentials(
					$this->client_email,
					array($this->auth_url),
					$this->private_key
				);

				$client = new Google_Client();
				$client->setClassConfig('Google_Cache_File', array('directory' => PATH_APP.'/temp/Google_Client/'));

				if (isset($CONFIG['TRACE_LOG']['GOOGLE'])){
					$client->setLoggerClass('Google_Logger_File');
					$client->setClassConfig('Google_Logger_File', array('file' => $CONFIG['TRACE_LOG']['GOOGLE']));
				}

				$client->setApplicationName("Lemonstand_V1");
				$client->setAssertionCredentials($credentials);
				if ($client->getAuth()->isAccessTokenExpired()) {
					$client->getAuth()->refreshTokenWithAssertion();
				}

				// Get this from the Google Console, API Access page
				$client->setClientId( $this->client_id );
				$client->setAccessType( 'offline_access' );
				$this->client = $client;

				$this->isLoggedIn = true;

			} catch (Exception $e){
				throw new Phpr_SystemException('Error connecting to Google Analytics. Google error: '.$e->getMessage());
			}
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

			$params = array('dimensions' => implode(',', $dimensions));
			if ($sort)
				$params['sort'] = $sort;


			$service = new Google_Service_Analytics($this->client);

			$data = $service->data_ga->get('ga:'.$this->siteId, $start->format('%Y-%m-%d'), $end->format('%Y-%m-%d'), implode(',', $metrics), $params );

			return $data;
		}
	}

?>