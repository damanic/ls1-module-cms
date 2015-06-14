<?

	class Cms_CallStackItem
	{
		public $name;
		public $type;
		public $code;
		
		public function __construct($name, $type, $code)
		{
			$this->name = $name;
			$this->type = $type;
			$this->code = $code;
			
			if (mb_substr($this->code, 0, 2) == '?>')
				$this->code = mb_substr($this->code, 2);
		}
	}

?>