<?php
class Cms_ResourceCombine {

	protected $URL_BASE64_TO = null;
	protected $URL_BASE64_FROM = null;
	protected $enable_gzip = true;


	protected function can_gzip(){
		if(!$this->enable_gzip){
			return false;
		}
		if(function_exists('gzcompress')){
			return true;
		}
		return false;
	}

	protected function url_base64_encode($data) {
		$encoded = base64_encode($data);
		if ($encoded === false) {
			return false;
		}
		return str_replace('=', '', strtr($encoded, $this->URL_BASE64_FROM, $this->URL_BASE64_TO));
	}

	protected function url_base64_decode($data) {
		$len = strlen($data);
		if (is_null($len)) {
			return false;
		}
		$padded = str_pad($data, 4 - $len % 4, '=', STR_PAD_RIGHT);
		return base64_decode(strtr($padded, $this->URL_BASE64_TO, $this->URL_BASE64_FROM));
	}

	protected function url_param_embed($data) {
		$arr = implode(',',$data);
		$data = $arr ? $arr : $data;
		if($this->can_gzip()){
			$data = @gzcompress($data);
		}
		return $this->url_base64_encode($data);
	}

	protected function url_param_decode($data) {
		$data = $this->url_base64_decode($data);
		if($this->can_gzip()){
			$data = @gzuncompress($data);
		}
		$arr = explode(',',$data);
		return $arr ? $arr : $data;
	}

	public static function encode_param($data){
		if(!$data){
			return null;
		}
		$obj = new self();
		$string = $obj->url_param_embed($data);
		return urlencode($string);
	}

	public static function decode_param($string, $url_encoded = true){
		if(!$string){
			return null;
		}
		if($url_encoded) {
			$string = urldecode( $string );
		}
		$obj = new self();
		return $obj->url_param_decode($string);
	}

	public static function is_remote_resource($path){
		if ( filter_var( $path, FILTER_VALIDATE_URL ) ) {
			return true;
		}
		return false;
	}

}