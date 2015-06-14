<?

	class Cms_ExecutionException extends Phpr_SystemException
	{
		public $call_stack;
		public $code_line;
		
		public function __construct($message, $call_stack, $line, $load_line_from_stack = false)
		{
			$call_stack = array_reverse($call_stack);
			$this->call_stack = $call_stack;
			$this->code_line = $line;
			
			if ($load_line_from_stack)
			{
				$trace = $this->getTrace();
				$cnt = count($trace);
				if ($cnt)
				{
					$this->code_line = $trace[0]['line'];
				}
			}
			
			parent::__construct($message);
		}
		
		public function stack_top()
		{
			return $this->call_stack[0];
		}
		
		public function document_type()
		{
			return $this->stack_top()->type;
		}

		public function document_name()
		{
			return $this->stack_top()->name;
		}

		public function document_code()
		{
			return $this->stack_top()->code;
		}
	}

?>