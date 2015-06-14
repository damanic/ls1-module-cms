<?

	class Cms_GaCaptchaException extends Phpr_ApplicationException
	{
		public $captcha_url;
		public $captcha_token;
		
		public function __construct( $message, $url, $token )
		{
			$this->captcha_url = $url;
			$this->captcha_token = $token;

			parent::__construct($message);
		}
	}

?>