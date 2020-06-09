<?php

$_cms_current_page_object = null;

/**
 * CMS controller class is responsible for pages, partials and templates rendering
 * It provides methods required for building a website, acting as a glue for website elements.
 * The instance of the class is created automatically by LemonStand and available in
 * the code of any page, template and partial as <em>$this</em> variable.
 *
 * @package cms.classes
 * @author LemonStand eCommerce Inc.
 * @documentable
 */
class Cms_Controller
{
	const cms_type_page = 'CMS page';
	const cms_type_template = 'CMS template';
	const cms_type_partial = 'CMS partial';
	const cms_type_block = 'CMS page block';
	const cms_type_head = 'CMS page head';

	/**
	 * @var array A filed for passing variables from actions to pages and partials.
	 * See the {@link http://lemonstand.com/docs/actions Actions page} for details.
	 * The contents of this field is automatically converted to PHP variables when
	 * the page is rendered.
	 * @documentable
	 * @see http://lemonstand.com/docs/actions Actions
	 */
	public $data = array();

	/**
	 * @var Cms_Page A reference to the current page.
	 * You can access the current object in any CMS template with the following code:
	 * <pre>$page = $this->page;</pre>
	 * @documentable
	 */
	public $page;
	public $tracking_code = array();
	public $ajax_mode = false;

	protected static $_instance = null;

	/**
	 * @var array Keeps the page request parameters.
	 * Use {@link Cms_Controller::request_param() request_param()} method to access page parameters by index.
	 * @documentable
	 */
	public $request_params;

	/**
	 * @var Shop_Customer A reference to the current customer.
	 * This variable is NULL if there is no customer logged in.
	 * @documentable
	 */
	public $customer = null;

	protected $_page_content;
	protected $_page_block_content = array();
	protected $_is_admin_authorized;
	protected $_twig_parser;
	public static $_ajax_handlers_loaded = false;

	protected $_special_query_flags = array();

	protected $_cms_call_stack = array();

	public static function create()
	{
		return self::$_instance = new self();
	}

	/**
	 * Returns the current CMS Controller object.
	 * This method allows to get access to the controller object outside of the
	 * CMS templates. The method returns NULL if there is no CMS Controller
	 * instance available. This means that the script works out of the
	 * front-end page rendering context.
	 * @return Cms_Controller Returns the controller object or NULL.
	 * @documentable
	 */
	public static function get_instance()
	{
		return self::$_instance;
	}

	public function __construct($authorize = true)
	{
		if (Phpr::$config->get('OPTIMIZE_FRONTEND_QUERIES'))
			Db_ActiveRecord::$execution_context = 'front-end';

		if ($authorize)
			$this->customer = Phpr::$frontend_security->authorize_user();
	}

	/**
	 * Returns an identifier of a customer group the current customer belongs to.
	 * If the customer is not logged-in, the method returns an identifier of the <em>Guest</em> group.
	 * The result of this method can be altered by the {@link cms:onGetCustomerGroupId} event handler.
	 * @documentable
	 * @return integer Returns an identifier of the customer group.
	 */
	public static function get_customer_group_id()
	{
		$controller = Cms_Controller::get_instance();

		$group_ids = Backend::$events->fireEvent('cms:onGetCustomerGroupId', $controller);
		foreach ($group_ids as $group_id)
		{
			if (strlen($group_id))
			{
				return $group_id;
			}
		}

		if ($controller && $controller->customer)
			return $controller->customer->customer_group_id;
		else
			return Shop_CustomerGroup::get_guest_group()->id;
	}

	/**
	 * Returns a customer group the current customer belongs to.
	 * If the customer is not logged in, returns the <em>Guest</em> group object.
	 * @documentable
	 * @return Shop_CustomerGroup Returns a customer group object.
	 */
	public static function get_customer_group()
	{
		$controller = Cms_Controller::get_instance();

		if ($controller && $controller->customer)
			return $controller->customer->group;
		else
			return Shop_CustomerGroup::get_guest_group();
	}

	/**
	 * Returns the current customer object.
	 * If a customer is not logged in, or the script is working out of the context
	 * of a front-end page request, returns NULL.
	 * @documentable
	 * @return Shop_Customer Returns the customer object or NULL.
	 */
	public static function get_customer()
	{
		$controller = Cms_Controller::get_instance();

		if ($controller && $controller->customer)
			return $controller->customer;

		return null;
	}

	/**
	 * Outputs a specified page
	 */
	public function open($page, &$params)
	{
		global $_cms_current_page_object;
		global $activerecord_no_columns_info;
		$this->process_special_requests();
		$_cms_current_page_object = $page;

		try
		{
			/*
			 * Apply security mode
			 */

			$this->apply_security($page, $params);

			/*
			 * Add Google Analytics tracker code
			 */

			$gaSettings = Cms_Stats_Settings::getLazy();
			if ($gaSettings->ga_enabled && !$page->disable_ga)
				$this->add_tracking_code($gaSettings->get_ga_tracking_code());

			$result_onBeforeDisplay = Backend::$events->fireEvent('cms:onBeforeDisplay', $page, $params);

			if(is_a($result_onBeforeDisplay,'Cms_Page')){
				$page = $result_onBeforeDisplay;
			}

			/*
			 * Output the page
			 */

			$activerecord_no_columns_info = true;
			$template = $page->template;
			$activerecord_no_columns_info = false;

			$this->page = $page;
			$this->request_params = $params;
			$this->logVisit($page);

			$this->data['cms_fatal_error_message'] = null;
			$this->data['cms_error_message'] = null;
			$this->eval_page_content();


			$template_content = $template ? $template->get_content() : $this->_page_content;

			if (in_array('show_page_structure', $this->_special_query_flags))
			{
				$bootstrap = '<link rel="stylesheet" type="text/css" href="'.root_url('/modules/cms/resources/css/frontend.css').'" />';
				$template_content = preg_replace(',\</head\>,i', $bootstrap.'</head>', $template_content, 1);
			}

			ob_start();

			if ($template)
				$this->evalWithException('?>'.$template_content, Cms_Controller::cms_type_template, $template->name);
			else
				echo $template_content;

			$page_content = ob_get_clean();

			/*
			 * Integrate Google Analytics tracker code
			 */

			if ($this->tracking_code)
			{
				$this->add_tracking_code($gaSettings->get_ga_tracker_close_declaration());
				$ga_code = implode("\n", $this->tracking_code);
				$page_content = preg_replace(',\</head\>,i', $ga_code."</head>", $page_content, 1);
			}
			echo $page_content;

			Backend::$events->fireEvent('cms:onAfterDisplay', $page);
		}
		catch (Exception $ex)
		{
			$this->clean_buffer();

			throw $ex;
		}
	}

	/**
	 * Executes a handler for a specific page
	 */
	public function handle_ajax_request($page, $handlerName, $updateElements, &$params)
	{

		$this->apply_security($page, $params);

		try
		{
			$this->page = $page;
			$this->request_params = $params;

			$this->data['cms_fatal_error_message'] = null;
			$this->data['cms_error_message'] = null;

			/*
			 * Determine whether the hanlder is a local function
			 * or a module-provided method and run the handler
			 */

			$this->ajax_mode = true;

			Backend::$events->fireEvent('cms:onBeforeHandleAjax', $page);

			$handlerNameParts = explode(':', $handlerName);
			if (count($handlerNameParts) == 1)
			{
				/*
				 * Run on_action handler
				 */

				if ($handlerName == 'on_action')
				{
					$this->action();
				}
				else
				{
					/*
					 * Run the local function
					 */

					$php_is_allowed = self::is_php_allowed();

					if ($php_is_allowed) // Ignore custom AJAX handlers field if PHP is not allowed
					{
						try
						{
							if (!self::$_ajax_handlers_loaded)
							{
								self::$_ajax_handlers_loaded = true;
								$this->evalWithException($this->page->get_ajax_handlers_code(), 'Page AJAX handlers', $this->page->label ? $this->page->label : $this->page->title, array(), true);
							}
						}
						catch (Exception $ex)
						{
							$this->handleEvalException('Error executing page AJAX handlers code: ', $ex);
						}
					}

					if (!function_exists($handlerName))
						throw new Phpr_ApplicationException('AJAX handler not found: '.$handlerName);

					call_user_func($handlerName, $this, $this->page, $this->request_params);
				}
			}
			else
			{
				Cms_ActionManager::execAjaxHandler($handlerName, $this);
			}

			/*
			 * Update page elements
			 */
			ob_start();
			foreach ($updateElements as $element=>$partial)
			{
				if(!$element)
					continue;

				echo ">>$element<<";
				$this->render_partial($partial);
			}
			ob_end_flush();

			Backend::$events->fireEvent('cms:onAfterHandleAjax', $page);

		}
		catch (Exception $ex)
		{
			$this->clean_buffer();
			Phpr::$response->ajaxReportException($ex, true, true);
		}
	}

	/**
	 * Copies execution context from another controller instance
	 */
	public function copy_context_from($controller)
	{
		$this->page = $controller->page;
		$this->request_params = $controller->request_params;
		$this->data = $controller->data;
		$this->customer = $controller->customer;
		$this->tracking_code = $controller->tracking_code;
		$this->ajax_mode = $controller->ajax_mode;
	}

	protected function evalWithException($code, $call_stack_object_type, $call_stack_object_name, $params = array(), $eval_as_handler = false)
	{
		$eval_result = null;

		try
		{
			$call_stack_obj = new Cms_CallStackItem($call_stack_object_name, $call_stack_object_type, $code);
			array_push($this->_cms_call_stack, $call_stack_obj);

			extract($this->data);
			extract($params);

			ob_start();
			$display_errors = ini_set('display_errors', 1);

			$eval_code_result = Backend::$events->fire_event(array('name' => 'cms:onEvaluateCode', 'type' => 'filter'), array(
				'page' => $this->page,
				'controller' => $this,
				'params' => array_merge($this->data, $params),
				'code' => substr($code, 2), // remove the php tag prepended to beginning of code
				'object_type' => $call_stack_object_type,
				'object_name' => $call_stack_object_name,
				'content' => null
			));

			if($eval_code_result['content'] !== null)
				$eval_result = $eval_code_result['content'];
			else
			{
				if ($eval_as_handler)
					$engine_code = 'php'; // Always eval handlers in PHP unless PHP features is disabled.
				else
					$engine_code = self::get_templating_engine_code($call_stack_object_type);

				if ($engine_code == 'php')
				{
					if (self::is_php_allowed())
						$eval_result = eval($code);
					else
						throw new Phpr_ApplicationException('PHP is not allowed in CMS templates.');
				} elseif ($engine_code == 'twig')
				{
					$object_name = $call_stack_object_type.' - '.$call_stack_object_name;
					echo $this->get_twig_parser()->parse(substr($code, 2), array_merge($this->data, $params, array('this'=>$this)), $object_name);
				}
				else
					throw new Phpr_ApplicationException('Unknown templating engine: '.$engine_code);
			}

			ini_set('display_errors', $display_errors);

			$result = ob_get_clean();

			$matches = array();

			$error_types = array('Warning', 'Parse error', 'Fatal error');
			$error = false;

			foreach ($error_types as $type)
			{
				if ($error = preg_match(',^\<br\s*/\>\s*\<b\>'.$type.'\</b\>:(.*),m', $result, $matches))
					break;
			}

			if ($error)
			{
				$errorMessage = $matches[1];
				$errorMessageText = null;
				$errorLine = null;
				$pos = strpos($errorMessage, 'in <b>');

				if ($pos !== false)
				{
					$lineFound = preg_match(',on\s*line\s*\<b\>([0-9]*)\</b\>,', $errorMessage, $matches);
					$errorMessageText = substr($errorMessage, 0, $pos);
					if ($lineFound)
						$errorLine = $matches[1];

					throw new Cms_ExecutionException($errorMessageText, $this->_cms_call_stack, $errorLine);
				} else
					throw new Cms_ExecutionException($errorMessage, $this->_cms_call_stack, null);
			}

			array_pop($this->_cms_call_stack);

			$html_output_filter = Backend::$events->fire_event(array('name' => 'cms:beforeOutputEvaluatedCode', 'type' => 'filter'), array(
				'page' => $this->page,
				'controller' => $this,
				'params' => array_merge($this->data, $params),
				'code' => $result,
				'object_type' => $call_stack_object_type,
				'object_name' => $call_stack_object_name,
				'content' => null
			));

			if($html_output_filter['content']){
				$result = $html_output_filter['content'];
			}

			echo $result;
		}
		catch (Exception $ex)
		{
			$forward_exception_classes = array(
				'Cms_ExecutionException',
				'Phpr_ValidationException',
				'Phpr_ApplicationException',
				'Cms_Exception'
			);

			if (in_array(get_class($ex), $forward_exception_classes))
				throw $ex;

			if ($ex instanceof Twig_Error)
			{
				if (!$ex->getPrevious())
					throw new Cms_ExecutionException($ex->getMessage(), $this->_cms_call_stack, $ex->getTemplateLine());
				else
					throw $ex->getPrevious();
			}

			if ($this->_cms_call_stack && strpos($ex->getFile(), "eval()") !== false)
				throw new Cms_ExecutionException($ex->getMessage(), $this->_cms_call_stack, $ex->getLine());

			throw $ex;
		}

		return $eval_result;
	}

	protected function evalHandler($code, $call_stack_object_type, $call_stack_object_name, $params = array())
	{
		try
		{
			return $this->evalWithException($code, $call_stack_object_type, $call_stack_object_name, $params, true);
		}
		catch (Phpr_ValidationException $ex)
		{
			Phpr::$session->flash['error'] = $ex->getMessage();
			return -1;
		}
		catch (Phpr_ApplicationException $ex)
		{
			// if ($ex instanceof Cms_FatalException)
			// 	throw $ex;

			Phpr::$session->flash['error'] = $ex->getMessage();
			return -1;
		}
		catch (Cms_Exception $ex)
		{
			Phpr::$session->flash['error'] = $ex->getMessage();
			return -1;
		}
	}

	protected function handleEvalException($message, $ex)
	{
		$exception_text = $message.Core_String::finalize($ex->getMessage());

		if ($ex instanceof Phpr_PhpException)
			$exception_text .= ' Line '.$ex->getLine().'.';

		throw new Exception($exception_text);
	}

	protected function logVisit($page)
	{
		Cms_Analytics::logVisit($page, Phpr::$request->getCurrentUri());
		Core_Metrics::log_pageview();
	}

	protected function reset_cache_request()
	{
		$caching_params = Phpr::$config->get('CACHING', array());
		$reset_cache_key = array_key_exists('RESET_PAGE_CACHE_KEY', $caching_params) ? $caching_params['RESET_PAGE_CACHE_KEY'] : null;
		if (!$reset_cache_key)
			return false;

		return Phpr::$request->getField($reset_cache_key);
	}

	protected function eval_page_content()
	{
		$php_is_allowed = self::is_php_allowed();

		ob_start();

		if ($php_is_allowed)
			$this->evalHandler($this->page->get_pre_action_code(!$php_is_allowed), 'CMS page PRE action code', $this->page->label ? $this->page->label : $this->page->title);

		/*
		 * We always execute the page action code even if the PRE Action code returned -1 (CMS, Validation or Application exception).
		 * In case of a fatal exception the execution stop.
		 */

		$loaded_from_cache = false;
		$loaded_from_api = false;
		$cache_result = false;

		$api_result = Backend::$events->fire_event(array('name' => 'cms:onBeforeEvalPageContent', 'type' => 'filter'), array(
			'page'=>$this->page,
			'page_content'=>false
		));

		if (isset($api_result['page_content']) && $api_result['page_content'] !== false)
		{
			echo $api_result['page_content'];
			$loaded_from_api = true;
		}

		if (!$loaded_from_api && function_exists('get_page_caching_params'))
		{
			$vary_by = array();
			$versions = array();
			$ttl = null;
			$cache = get_page_caching_params($vary_by, $versions, $ttl);
			$vary_by[] = 'url';

			$key_prefix= 'page_'.str_replace('/', '', $this->page->url);
			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_active_theme();
				if ($theme)
					$key_prefix = $theme->code.'-'.$key_prefix;
			}

			$recache = false;
			$cache_key = Core_CacheBase::create_key($key_prefix, $recache, $vary_by, $versions);
			if ($this->reset_cache_request())
				$recache = true;
			$cache = Core_CacheBase::create();
			$page_contents = !$recache ? $cache->get($cache_key) : false;

			if ($page_contents !== false)
			{
				$loaded_from_cache = true;
				echo $page_contents;
			} else
				$cache_result = true;
		}

		if (!$loaded_from_cache && !$loaded_from_api)
		{
			$action_result = true;
			if (strlen($this->page->action_reference) && $this->page->action_reference != Cms_Page::action_custom)
			{
				try
				{
					Cms_ActionManager::execAction($this->page->action_reference, $this);
				}
				catch (Phpr_ValidationException $ex)
				{
					$action_result = false;
					Phpr::$session->flash['error'] = $ex->getMessage();
				}
				catch (Phpr_ApplicationException $ex)
				{
					$action_result = false;

					// if ($ex instanceof Cms_FatalException)
					// 	throw $ex;

					Phpr::$session->flash['error'] = $ex->getMessage();
				}
				catch (Cms_Exception $ex)
				{
					$action_result = false;
					Phpr::$session->flash['error'] = $ex->getMessage();
				}
			}

			ob_start();

			if ($action_result && $php_is_allowed)
				$this->evalHandler($this->page->get_post_action_code(!$php_is_allowed), 'CMS page POST action code', $this->page->label ? $this->page->label : $this->page->title);

			$this->evalWithException('?>'.$this->page->get_content_code(), Cms_Controller::cms_type_page, $this->page->label ? $this->page->label : $this->page->title);

			$page_contents = ob_get_contents();
			ob_end_clean();

			if ($cache_result)
				$cache->set($cache_key, $page_contents, $ttl);

			echo $page_contents;
		}

		if (!$loaded_from_api)
			Backend::$events->fire_event('cms:onAfterEvalPageContent', $this->page, $page_contents);

		$this->_page_content = ob_get_clean();
	}

	/**
	 * Outputs the page head declarations block contents.
	 * You can define the header content on the <em>Head & Blocks</em> tab of the Create/Edit Page form.
	 * Please read {@link http://lemonstand.com/docs/using_the_head_field/ this article} for details.
	 * Example:
	 * <pre><? $this->render_head() ?></pre>
	 * @documentable
	 * @see http://lemonstand.com/docs/using_the_head_field/ Using the page Head Declarations field
	 */
	public function render_head()
	{
		ob_start();

		$this->evalWithException('?>'.$this->page->get_head_code(), Cms_Controller::cms_type_head, $this->page->label ? $this->page->label : $this->page->title);

		echo ob_get_clean();
	}

	/**
	 * Outputs a page block contents.
	 * Use this method for displaying code block defined on the <em>Head & Blocks</em> tab of the Create/Edit Page form.
	 * If the specified block does not exist no error will occur. Please read
	 * {@link http://lemonstand.com/docs/using_the_page_blocks_feature/ this article} for details. Example:
	 * <pre><? $this->render_block('sidebar') ?></pre>
	 * @documentable
	 * @param string $block_code Specifies a code of the block to output.
	 * @param string $default String to output if the block is not defined on the page.
	 * @see http://lemonstand.com/docs/using_the_page_blocks_feature/ Using the page blocks feature
	 */
	public function render_block($block_code, $default = null)
	{
		$block_code = mb_strtolower(trim($block_code));

		if (!array_key_exists($block_code, $this->_page_block_content))
		{
			$blocks = $this->page->list_blocks();
			if (array_key_exists($block_code, $blocks))
			{
				ob_start();
				$this->evalWithException('?>'.$blocks[$block_code], Cms_Controller::cms_type_block, $block_code);
				$this->_page_block_content[$block_code] = ob_get_clean();
			}
			else echo $default;
		}

		if (array_key_exists($block_code, $this->_page_block_content))
			echo $this->_page_block_content[$block_code];
	}

	/**
	 * Returns URL of a page which was originally requested before the redirection.
	 * If a not logged on visitor tries to access a page, which can only be accessed by registered customers,
	 * he is redirected to the Login page. This function returns the URL of the original page, which allows
	 * to redirect the customer back to that page after the successful log in.
	 * This method is useful for creating the {@link http://lemonstand.com/docs/customer_login_and_logout customer login} page.
	 * The following example creates a hidden field with name <em>redirect</em> which is supported
	 * by the {@link action@shop:login} action.
	 * <pre><input type="hidden" value="<?= $this->redirect_url('/') ?>" name="redirect"/></pre>
	 * There is the Twig function redirect_url() as well.
	 * @documentable
	 * @see Cms_Controller::create_redirect_url() create_redirect_url()
	 * @param string $default Specifies the default page URL.
	 * This value will be used if the Login page was opened directly.
	 * @param integer $index Specifies the page URL segment index to extract the original page URL.
	 * The built-in authentication mechanism passes the URL in the first segment, which zero-based index is 0.
	 * @return string Returns the URL of the originally requested page.
	 */
	public function redirect_url($default, $index = 0)
	{
		$url = $this->request_param($index);
		if (!$url)
			return $default;

		return root_url(str_replace("|", "/", urldecode($url)));
	}

	/**
	 * Creates a redirection URL string suitable for using with {@link Cms_Controller::redirect_url() redirect_url()} method.
	 * A result of this method can be attached to the Login page URL.
	 * <pre>Please <a href="<?= root_url('login').'/'.$this->create_redirect_url() ?>">login</a>.</pre>
	 * @documentable
	 * @see Cms_Controller::redirect_url() redirect_url()
	 * @return string Returns then encoded URL string.
	 */
	public function create_redirect_url($include_query_string = false)
	{
		$url = urlencode(str_replace('/', '|', strtolower(Phpr::$request->getCurrentUri())));
		if($include_query_string && method_exists(Phpr::$request,'get_query_string')){
			$query_string = Phpr::$request->get_query_string();
			if($query_string){
				$url = $url.'/?'.$query_string;
			}

		}
		return $url;
	}

	protected function process_special_requests()
	{
		$special_queries = array(
			'show_page_structure'
		);

		$special_query_found = false;
		foreach ($_REQUEST as $key=>$value)
		{
			if (in_array($key, $special_queries))
			{
				$this->_special_query_flags[] = $key;
				$special_query_found = true;
			}
		}

		if ($special_query_found)
			$this->http_admin_authorize();
	}

	protected function http_admin_authorize()
	{
		if (!isset($_SERVER['PHP_AUTH_USER']))
			$this->send_http_auth_headers();

		$user = new Users_User();
		$user = $user->findUser($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		if (!$user)
			$this->send_http_auth_headers();

		$this->_is_admin_authorized = true;
	}

	protected function send_http_auth_headers()
	{
		header('WWW-Authenticate: Basic realm="Website management"');
		header('HTTP/1.0 401 Unauthorized');

		die("You are not authorized to access this page.");
	}

	protected function clean_buffer()
	{
		$handlers = ob_list_handlers();
		foreach ($handlers as $handler)
		{
			try
			{
				if (strpos($handler, 'zlib') === false)
					@ob_end_clean();

			} catch (Exception $ex) {}
		}
	}

	protected function apply_security($page, $request_params)
	{
		if ($page->protocol != 'any' && $page->protocol != 'none')
		{
			$protocol = Phpr::$request->protocol();
			if ($page->protocol != $protocol)
			{
				$ticket_id = Phpr::$frontend_security->storeTicket();
				$session_id = session_id();

				Phpr::$session->store();

				session_write_close();

				$param_str = null;
				$url_params_str = null;

				if ($request_params)
					$param_str = '/'.implode('/', $request_params).'/';
				elseif ($page->url != '/')
					$param_str = '/';

				$url_params = Phpr::$request->get_fields;
				if (!$url_params)
					$url_params = array();

				$request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
				if (isset($url_params[$request_param_name]))
					unset($url_params[$request_param_name]);

				$session_id_param = Phpr::$config->get('SESSION_PARAM_NAME', 'ls_session_id');
				$frontend_ticket_param = Phpr::$config->get('TICKET_PARAM_NAME', 'ls_frontend_ticket');

				$url_params[$frontend_ticket_param] = $ticket_id;
				$url_params[$session_id_param] = $session_id;

				$url_params_encoded = array();
				foreach ($url_params as $param_name=>$param_value)
					$url_params_encoded[] = $param_name.'='.urlencode($param_value);

				$url_params_str = '?'.implode('&', $url_params_encoded);

				Phpr::$response->redirect(Phpr::$request->getRootUrl($page->protocol).
					root_url($page->url).
					$param_str.
					$url_params_str,
					true);
			}
		}

		if (($page->security_mode_id == Cms_SecurityMode::customers && $this->customer == null) ||
			($page->security_mode_id == Cms_SecurityMode::guests && $this->customer != null) ||
			$page->protocol == 'none')
		{
			$redir_page = $page->security_redirect;
			if ($redir_page) {
				$redirect_params = $this->create_redirect_url(true);
				if ($redir_page->url != '/'){
					Phpr::$response->redirect(root_url($redir_page->url, true).'/'.$redirect_params);
				}
				else {
					Phpr::$response->redirect(root_url($redir_page->url));
				}

			} else {
				echo "Sorry, specified page is not found.";
				die();
			}
		}

		if (Phpr::$request->getField(Phpr::$config->get('TICKET_PARAM_NAME', 'ls_frontend_ticket')) && Phpr::$config->get('SECURITY_AUTO_REDIRECT', true))
		{
			$url = $_SERVER['REQUEST_URI'];
			$pos = strpos($url, '?');
			Phpr::$response->redirect(substr($url, 0, $pos));
		}

		Backend::$events->fireEvent('cms:onApplyPageSecurity', $page, $request_params);

		if ($this->customer)
			Shop_CheckoutData::load_from_customer($this->customer);
	}

	/**
	 * Adds JavaScript string to output before the closing BODY tag
	 */
	protected function add_tracking_code($code)
	{
		$stop_tracking_code = false;
		$return = Backend::$events->fireEvent('cms:onBeforeTrackingCodeInclude', $code);
		foreach ($return as $value)
		{
			if ($value === false)
				$stop_tracking_code = true;
		}
		if(!$stop_tracking_code)
			$this->tracking_code[] = $code;
	}

	/*
	 * Service functions - use it in pages or layouts
	 */

	/**
	 * Extracts a page request parameter from the requested URL by the parameter index.
	 * When LemonStand parses an URL, it considers all URL segments following a page address as parameters.
	 * For example, if there was a page with address <em>/category</em> (the value, specified in the
	 * <em>Page URL</em> field of the Create/Edit Page form), and you opened the page using URL
	 * <em>/category/computers</em>, the word <em>computers</em> would be considered as a parameter with index 0.
	 * The second optional parameter specifies a default value for a parameter. It is used if
	 * a parameter with the specified index is not found.
	 *
	 * The $index parameter can take negative values. In this case the method returns index'th parameter
	 * from the end of the URL. For example, if the input URL was <em>/category/men/jumpers/2</em>, calling
	 * the method with <em>$index=-1</em> would return <em>2</em> (i.e. the first parameter from the end).
	 * @documentable
	 * @param integer $index Specifies the parameter index.
	 * @param mixed $default Specified a default value to return in case if the parameter with
	 * the specified index does not exist.
	 * @return string Returns the parameter value or the default value.
	 */
	public function request_param($index, $default = null)
	{
		if ($index < 0)
		{
			$length = count($this->request_params);
			$index = $length+$index;
		}

		if (array_key_exists($index, $this->request_params))
			return $this->request_params[$index];

		return $default;
	}

	/**
	 * Renders the page.
	 * Call this method inside {@link http://lemonstand.com/docs/adding_a_template/ layouts} to output a current page content.
	 * Example:
	 * <pre><? $this->render_page() ?></pre>
	 * @see http://lemonstand.com/docs/adding_a_template/ Adding a layout
	 * @documentable
	 */
	public function render_page()
	{
		echo $this->_page_content;
	}

	/**
	 * Outputs a {@link http://lemonstand.com/docs/creating_a_partial/ partial} with the specified name.
	 *
	 * The following code renders the <em>product_list</em> partial and passes the <em>products</em>
	 * variable to it.
	 * <pre>$this->render_partial('product_list', array('products'=>$category->products));</pre>
	 * The $options parameter supports the following elements:
	 * <ul>
	 *   <li><em>return_output</em> - determines whether the method should return the partial content instead of displaying it.</li>
	 *   <li><em>cache</em> - enables the {@link http://lemonstand.com/docs/caching_pages_and_partials/ caching}.</li>
	 *   <li><em>cache_vary_by</em> - array or string (for a single value parameter). Specifies parameters the cache
	 *        should depend on. For example, you can create different versions of a partial cache for different pages
	 *        (if you reuse a partial on different pages), or for different customers, customer groups, and other parameters. </li>
	 *   <li><em>cache_versions</em> - array or string (for a single value parameter). Allows to specify names of content types
	 *        which versions the partial cache should depend on. You can use the following values in this parameter:
	 *        <em>cms</em>, <em>catalog</em>, <em>blog</em>. If you specify the catalog content type, the partial will be automatically
	 *        recached if you update the catalog (add/update/delete products, apply catalog price rules, update categories, etc).
	 *        The <em>cms</em> and <em>blog</em> parameters work in the same way. but watch updates of the CMS and blog content.</li>
	 *   <li><em>cache_ttl</em> - cache item time-to-live, in seconds. Please note, that file-based caching does not support
	 *        per-item TTL setting and always use the global TTL value specified in the caching configuration parameters.</li>
	 * </ul>
	 * The following example renders a partial with caching:
	 * <pre>
	 * <?
	 *   $this->render_partial('my_partial',
	 *      array(), // No partial-specific parameters (to simplify the example)
	 *      array(
	 *         'cache'=>true,// Enable caching
	 *         'cache_vary_by'=>array('url'), // We are going to reuse this partial on different pages, but its content can be different for different pages so we want to create different partial cache versions for different pages
	 *         'cache_versions'=>array('catalog'), // The partial should be recached then the catalog updates
	 *         'cache_ttl'=>1800 // Recache each 30 minutes
	 *     )
	 *   )
	 * ?></pre>
	 * @documentable
	 * @see http://lemonstand.com/docs/creating_a_partial/ Creating a partial
	 * @see http://lemonstand.com/docs/caching_pages_and_partials/ Caching pages and partials
	 * @param string $name Specifies the partial name.
	 * @param array $params A list of parameters to pass to the partial.
	 * @param array $options A list of options.
	 * @return mixed Returns NULL by default. Returns the partial content if the $options parameters has
	 * <em>return_output</em> element with value TRUE.
	 */
	public function render_partial($name, $params = array(), $options = array('return_output' => false))
	{
		$result = null;
		$name = str_replace(';', ':', $name);

		$return_output = array_key_exists('return_output', $options) && $options['return_output'];
		if ($return_output)
			ob_start();

		if (in_array('show_page_structure', $this->_special_query_flags))
		{
			echo '<div class="cms_partial_wrapper" title="'.h($name).'">';
			echo '<span title="'.h($name).'" class="cms_partial_name">'.h($name).'</span>';
		}

		Backend::$events->fireEvent('cms:onBeforeRenderPartial', $name, $params, $options);

		$loaded_from_cache = false;
		$cache_result = false;

		if (array_key_exists('cache', $options))
		{
			$key_prefix= 'partial_'.str_replace(':', '-', $name);

			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_active_theme();
				if ($theme)
					$key_prefix = $theme->code.'-'.$key_prefix;
			}

			$vary_by = array_key_exists('cache_vary_by', $options) ? $options['cache_vary_by'] : array();
			$cache_versions = array_key_exists('cache_versions', $options) ? $options['cache_versions'] : array();

			$cache_ttl = array_key_exists('cache_ttl', $options) ? $options['cache_ttl'] : null;
			$recache = false;
			$cache_key = Core_CacheBase::create_key($key_prefix, $recache, $vary_by, $cache_versions);
			if ($this->reset_cache_request())
				$recache = true;

			$cache = Core_CacheBase::create();
			$partial_contents = !$recache ? $cache->get($cache_key) : false;

			if ($partial_contents !== false)
			{
				$loaded_from_cache = true;
				echo $partial_contents;
			} else
				$cache_result = true;
		}

		if (!$loaded_from_cache)
		{
			if ($cache_result)
				ob_start();

			$partial = Cms_Partial::find_by_name($name);
			if ($partial)
				$result = $this->evalWithException('?>'.Cms_Partial::get_content($name, $partial->html_code, $partial->file_name), Cms_Controller::cms_type_partial, $partial->name, $params);
			else if ($this->_cms_call_stack)
				throw new Cms_ExecutionException("Partial \"$name\" not found", $this->_cms_call_stack, null, true);
			else
				throw new Phpr_ApplicationException("Partial " . $name . " not found");

			if ($cache_result)
			{
				$partial_contents = ob_get_contents();
				ob_end_clean();

				$cache->set($cache_key, $partial_contents, $cache_ttl);
				echo $partial_contents;
			}
		}

		if (in_array('show_page_structure', $this->_special_query_flags))
			echo "</div>";

		Backend::$events->fireEvent('cms:onAfterRenderPartial',  $name, $params, $options);

		if ($return_output)
		{
			$result = ob_get_contents();
			ob_end_clean();
		}

		return $result;
	}

	/**
	 * Executes the page CMS action.
	 * Use this method in custom {@link http://lemonstand.com/docs/ajax/ AJAX} handlers to force the controller to execute the default page action.
	 * The following example demonstrates how to execute the page action in an AJAX handler.
	 * <pre>
	 * function my_handler($controller)
	 * {
	 *   $controller->action();
	 * }
	 * </pre>
	 * @documentable
	 * @see http://lemonstand.com/docs/ajax/ AJAX
	 */
	public function action()
	{
		if (strlen($this->page->pre_action) && self::is_php_allowed()){
			eval($this->page->pre_action);
		}

		if ($this->page->action_reference != Cms_Page::action_custom) {
			Cms_ActionManager::execAction( $this->page->action_reference, $this );
		}

		if (strlen($this->page->action_code) && self::is_php_allowed()){
			eval($this->page->action_code);
		}

	}

	/**
	 * Executes an AJAX handler directly.
	 * This method is extremely helpful for customizing default server handlers.
	 * It allows to wrap any code around a standard LemonStand handler. The following example
	 * demonstrates a custom AJAX event handler, which can be used for implementing a single-form
	 * checkout process. This code should be entered to the <em>AJAX Handlers</em> field of the <em>AJAX</em>
	 * tab on the <em>Create/Edit Page</em> page.
	 * <pre>
	 * function my_custom_checkout($controller, $page, $params)
	 * {
	 *   if (!Shop_Cart::list_active_items())
	 *     throw new Exception('Your cart is empty.');
	 *
	 *   $controller->exec_action_handler('shop:on_checkoutSetBillingInfo');
	 *
	 *   Shop_CheckoutData::copy_billing_to_shipping();
	 *   Shop_CheckoutData::set_payment_method(
	 *     Shop_PaymentMethod::find_by_api_code('no_payment')->id
	 *   );
	 *   Shop_CheckoutData::set_shipping_method(
	 *     Shop_ShippingOption::find_by_api_code('free_shipping')->id
	 *   );
	 *   Shop_CheckoutData::place_order($controller->customer);
	 *
	 *   Phpr::$response->redirect('/receipt');
	 * }
	 * </pre>
	 * @documentable
	 * @param string $handler Specifies a handler name, for example shop:on_addToCart
	 */
	public function exec_action_handler($handler)
	{
		return Cms_ActionManager::execAjaxHandler($handler, $this);
	}

	public static function is_php_allowed()
	{
		return Core_Configuration::is_php_allowed();
	}

	protected static function get_templating_engine_code($object_type)
	{
		$cms_object_types = array(
			Cms_Controller::cms_type_page,
			Cms_Controller::cms_type_template,
			Cms_Controller::cms_type_partial,
			Cms_Controller::cms_type_block,
			Cms_Controller::cms_type_head,
		);

		if (!in_array($object_type, $cms_object_types))
			return 'php';

		$engine = null;

		if (Cms_Theme::is_theming_enabled())
		{
			$theme = Cms_Theme::get_active_theme();
			if (!$theme)
				return 'php';

			$engine = $theme->templating_engine;
			if (!$engine)
				return 'php';
		} else
			$engine = Cms_SettingsManager::get()->default_templating_engine;

		if (!$engine)
			$engine = 'php';

		return $engine;
	}

	protected function get_twig_parser()
	{
		if ($this->_twig_parser)
			return $this->_twig_parser;

		return $this->_twig_parser = new Cms_Twig($this);
	}

	protected function resource_combine($type, $files, $options, $show_tag = true)
	{
		$results = Backend::$events->fire_event('cms:onBeforeResourceCombine', array(
			'type' => $type,
			'files' => $files,
			'options' => $options,
			'show_tag' => $show_tag
		));

		foreach($results as $result)
			if($result)
				return $result;

		$files = Phpr_Util::splat($files);

		$current_theme = null;
		if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
			$current_theme = $theme;

		$files_array = array();
		foreach ($files as $file)
		{
			$file = trim($file);

			if (substr($file, 0, 1) == '@')
			{
				$file = substr($file, 1);
				if (strpos($file, '/') !== 0)
					$file = '/'.$file;

				if ($current_theme)
					$file = $theme->get_resources_path().$file;
				else
					$file = '/'.Cms_SettingsManager::get()->resources_dir_path.$file;
			}

			$files_array[] = urlencode(trim($file));
		}

		$options_str = array();
		foreach ($options as $option=>$value)
		{
			if ($value)
				$options_str[] = $option.'=1';
		}

		$options_str = implode('&amp;', $options_str);
		if ($options_str)
			$options_str = '&amp;'.$options_str;

		$files_string = Cms_ResourceCombine::encode_param($files_array);

		if ($type == 'javascript') {
			$url = root_url('cms_js_combine/?f='.$files_string.$options_str);

			return $show_tag ? '<script type="text/javascript" src="'.$url.'"></script>'."\n" : $url;
		}
		else {
			$url = root_url('cms_css_combine/?f='.$files_string.$options_str);

			return $show_tag ? '<link rel="stylesheet" type="text/css" href="'.$url.'" />' : $url;
		}
	}

	/**
	 * Combines and minifies multiple JavaScript files.
	 * Please read {@link http://lemonstand.com/docs/combining_and_minifying_javascript_and_css_files/ Combining and minifying JavaScript and CSS files}
	 * documentation article for details about the method usage.
	 * The $files parameter should contain a list of JavaScript files to include. The files must be specified
	 * relative to the LemonStand root directory. Remote resources should be prefixed with http:// or https://
	 * protocol specifier. Theme resources should be prefixed with <em>@</em> symbol. Alongside with the
	 * file paths, the $files array accepts file aliases, which point to LemonStand built-in front-end JavaScript
	 * libraries:
	 * <ul>
	 *   <li><em>mootools</em> - included MooTools core script</li>
	 *   <li><em>ls_core_mootools</em> - includes LemonStand MooTools-specific front-end library/li>
	 *   <li><em>jquery</em> - includes jQuery core script</li>
	 *   <li><em>jquery_noconflict</em> - includes jQuery noConflict() call</li>
	 *   <li><em>ls_core_jquery</em>  - includes LemonStand jQuery-specific front-end library</li>
	 * </ul>
	 * The $options parameter accepts the following parameters:
	 * <ul>
	 *   <li><em>reset_cache</em> - reset the file cache. LemonStand caches combined files on the server.
	 *       By default it updates the cache as soon as any of the cached files is changed. However it cannot
	 *       track updates of remote files. You can use this parameter for forcing LemonStand to update the file cache.</li>
	 *   <li><em>skip_cache</em> - do not cache the combined files.</li>
	 *   <li><em>src_mode</em> - do not minify the combined files (leave the comments, and formatting as is).</li>
	 * </ul>
	 * The $show_tag parameter determines whether <em>script</em> HTML tag should be returned. The default value is TRUE,
	 * meaning that the tag is returned. If you want to create the tag manually pass FALSE to the parameter.
	 *
	 * The following code adds jQuery, LemonStand jQuery library and {@link http://fancybox.net/ FancyBox} plugin to the page
	 * (the example assumes that FancyBox files have been uploaded to the /resources directory).
	 * <pre>
	 * <head>
	 *   <?= $this->js_combine(array(
	 *     'jquery',
	 *     'ls_core_jquery',
	 *     '/resources/fancybox/jquery.fancybox-1.3.4.pack.js'
	 *   )) ?>
	 *   ...
	 * </pre>
	 * The returning value of this method can be affected by {@link cms:onBeforeResourceCombine} event.
	 * @documentable
	 * @see http://lemonstand.com/docs/combining_and_minifying_javascript_and_css_files/ Combining and minifying JavaScript and CSS files
	 * @see cms:onBeforeResourceCombine
	 * @param array $files A list of files or file aliases to include to the page.
	 * @param array $options Specifies a list of options.
	 * @param boolean $show_tag Determines whether HTML <em>script</em> tag should be generated.
	 * @return string Returns a string containing a reference to the resource files.
	 */
	public function js_combine($files, $options = array(), $show_tag = true)
	{
		return $this->resource_combine('javascript', $files, $options, $show_tag);
	}

	/**
	 * Combines and minifies multiple CSS files.
	 * Please read {@link http://lemonstand.com/docs/combining_and_minifying_javascript_and_css_files/ Combining and minifying JavaScript and CSS files}
	 * documentation article for details about the method usage.
	 * The $files parameter should contain a list of СЫЫ files to include. The files must be specified
	 * relative to the LemonStand root directory. Remote resources should be prefixed with http:// or https://
	 * protocol specifier. Theme resources should be prefixed with <em>@</em> symbol. Alongside with the
	 * file paths, the $files array accepts file aliases, which point to LemonStand built-in front-end CSS
	 * libraries:
	 * <ul>
	 *   <li><em>ls_styles</em> -  includes LemonStand front-end CSS styles file</li>
	 * </ul>
	 * The $options parameter accepts the following parameters:
	 * <ul>
	 *   <li><em>reset_cache</em> - reset the file cache. LemonStand caches combined files on the server.
	 *       By default it updates the cache as soon as any of the cached files is changed. However it cannot
	 *       track updates of remote files. You can use this parameter for forcing LemonStand to update the file cache.</li>
	 *   <li><em>skip_cache</em> - do not cache the combined files.</li>
	 *   <li><em>src_mode</em> - do not minify the combined files (leave the comments, and formatting as is).</li>
	 * </ul>
	 * LemonStand automatically processes relative URLs in the CSS files, so you can use CSS files from different locations and it will not break images.
	 * The $show_tag parameter determines whether <em>link</em> HTML tag should be returned. The default value is TRUE,
	 * meaning that the tag is returned. If you want to create the tag manually pass FALSE to the parameter.
	 *
	 * The following code adds LemonStand front-end CSS file, styles.css file and {@link http://fancybox.net/ FancyBox} CSS file to the page
	 * (the example assumes that FancyBox files have been uploaded to the /resources directory).
	 * <pre>
	 * <head>
	 * <?= $this->css_combine(array(
	 *   'ls_styles',
	 *   '/resources/css/styles.css',
	 *   '/resources/fancybox/jquery.fancybox-1.3.4.css'
	 * )) ?>
	 * ...
	 * </pre>
	 * The returning value of this method can be affected by {@link cms:onBeforeResourceCombine} event.
	 * @documentable
	 * @see http://lemonstand.com/docs/combining_and_minifying_javascript_and_css_files/ Combining and minifying JavaScript and CSS files
	 * @see cms:onBeforeResourceCombine
	 * @param array $files A list of files or file aliases to include to the page.
	 * @param array $options Specifies a list of options.
	 * @param boolean $show_tag Determines whether HTML <em>link</em> tag should be generated.
	 * @return string Returns a string containing a reference to the resource files.
	 */
	public function css_combine($files, $options = array(), $show_tag = true)
	{
		return $this->resource_combine('css', $files, $options, $show_tag);
	}

	/*
	 * Event descriptions
	 */

	/**
	 * Allows to override the default current customer group identifier in the front-end requests.
	 * The event allows to alter the {@link Cms_Controller::get_customer_group_id()} method result,
	 * effectively changing the current customer group on the front-end and thus possibly affecting
	 * product prices and pages visibility. The handler function should return a customer group identifier
	 * or nothing if changing the current customer group is not needed.
	 *
	 * Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onGetCustomerGroupId', $this, 'get_customer_group_id');
	 * }
	 *
	 * public function get_customer_group_id($controller)
	 * {
	 *   // Do not override the customer group for logged in customers
	 *   if ($controller && $controller->customer)
	 *     return $controller->customer->customer_group_id;
	 *
	 *   // Replace the "guest" customer group with some other group
	 *   return 10;
	 * }</pre>
	 *
	 * @event cms:onGetCustomerGroupId
	 * @package cms.events
	 * @author LemonStand eCommerce Inc.
	 * @param Cms_Controller $controller Specifies the CMS controller object.
	 * @return int Returns the current customer group identifier.
	 */
	private function event_onGetCustomerGroupId($controller) {}

	/**
	 * Triggered before a front-end page is displayed.
	 * The event handler result does not affect the page rendering process.
	 * Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onBeforeDisplay', $this, 'before_page_display');
	 * }
	 *
	 * public function before_page_display($page)
	 * {
	 *   // Do something
	 * }</pre>
	 *
	 * @event cms:onBeforeDisplay
	 * @package cms.events
	 * @see cms:onAfterDisplay
	 * @author LemonStand eCommerce Inc.
	 * @param Cms_Page $page Specifies the CMS page object.
	 */
	private function event_onBeforeDisplay($page) {}

	/**
	 * Triggered after a front-end page is displayed.
	 * The event handler result does not affect the page rendering process.
	 * Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onAfterDisplay', $this, 'after_page_display');
	 * }
	 *
	 * public function before_page_display($page)
	 * {
	 *   // Do something
	 * }</pre>
	 *
	 * @event cms:onAfterDisplay
	 * @package cms.events
	 * @see cms:onBeforeDisplay
	 * @author LemonStand eCommerce Inc.
	 * @param Cms_Page $page Specifies the CMS page object.
	 */
	private function event_onAfterDisplay($page) {}

	/**
	 * Triggered before an AJAX request is handled.
	 * The event handler result does not affect the request handling process unless
	 * an exception is thrown.
	 * Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onBeforeHandleAjax', $this, 'before_handle_ajax');
	 * }
	 *
	 * public function before_handle_ajax($page)
	 * {
	 *   // Do something
	 * }</pre>
	 *
	 * @event cms:onBeforeHandleAjax
	 * @package cms.events
	 * @see cms:onAfterHandleAjax
	 * @author LemonStand eCommerce Inc.
	 * @param Cms_Page $page Specifies the CMS page object.
	 */
	private function event_onBeforeHandleAjax() {}

	/**
	 * Triggered after an AJAX request is handled.
	 * The event handler result does not affect the request handling process.
	 * Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onAfterHandleAjax', $this, 'after_handle_ajax');
	 * }
	 *
	 * public function after_handle_ajax($page)
	 * {
	 *   // Do something
	 * }</pre>
	 *
	 * @event cms:onAfterHandleAjax
	 * @package cms.events
	 * @see cms:onBeforeHandleAjax
	 * @author LemonStand eCommerce Inc.
	 * @param Cms_Page $page Specifies the CMS page object.
	 */
	private function event_onAfterHandleAjax() {}

	/**
	 * Triggered before a page content is evaluated.
	 * The event handler can return a string which will be used instead of the
	 * standard page content. This event can be used for custom page
	 * caching implementations.
	 *
	 * If the default page content should be altered,
	 * the handler should return an array containing the <em>page_content</em> element
	 * with a value corresponding the updated page content.
	 * Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onBeforeEvalPageContent', $this, 'before_eval_page_content');
	 * }
	 *
	 * public function before_eval_page_content($params)
	 * {
	 *   $page = $params['page'];
	 *
	 *   if ($page->url !== 'my-special-page')
	 *     return false;
	 *
	 *   return array(
	 *     'page_content'=>'Updated page content'
	 *   );
	 * }</pre>
	 *
	 * @event cms:onBeforeEvalPageContent
	 * @package cms.events
	 * @see cms:onAfterEvalPageContent
	 * @author LemonStand eCommerce Inc.
	 * @param array $params An array with the <em>page</em> element representing a {@link Cms_Page page} object.
	 * @return mixed Returns an array containing the <em>page_content</em> element or FALSE.
	 */
	private function event_onBeforeEvalPageContent($params) {}

	/**
	 * Triggered after a page content is evaluated.
	 * The event handler accepts the page object and the page content string.
	 * This event can be used for custom page caching implementations.
	 *
	 * Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onAfterEvalPageContent', $this, 'after_eval_page_content');
	 * }
	 *
	 * public function after_eval_page_content($page, $content)
	 * {
	 *   if ($page->url !== 'my-special-page')
	 *     return;
	 *
	 *   // Save the $content variable to some cache
	 * }</pre>
	 *
	 * @event cms:onAfterEvalPageContent
	 * @package cms.events
	 * @see cms:onBeforeEvalPageContent
	 * @author LemonStand eCommerce Inc.
	 * @param Cms_Page $page Specifies the CMS page object.
	 * @param string $content Specifies the evaluated page content.
	 */
	private function event_onAfterEvalPageContent($page, $content) {}

	/**
	 * Allows to perform security checks before a page is displayed.
	 * In the event handler you can perform additional security checks
	 * and redirect the browser to another page if it is needed.
	 *
	 * The event handler accepts the page object and the request parameters array. Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onApplyPageSecurity', $this, 'apply_security');
	 * }
	 *
	 * public function apply_security($page, $params)
	 * {
	 *   if ($page->url !== 'my-secure-page')
	 *     return;
	 *
	 *   // Redirect to another page if the
	 *   // customer is not logged in, or if the
	 *   // customer name is not John
	 *   $customer = Cms_Controller::get_customer();
	 *   if (!$customer || $customer->first_name != 'John')
	 *     Phpr::$response->redirect('/login');
	 * }</pre>
	 *
	 * @event cms:onApplyPageSecurity
	 * @package cms.events
	 * @author LemonStand eCommerce Inc.
	 * @param Cms_Page $page Specifies the CMS page object.
	 * @param array $request_parameters A list of request parameters.
	 * @param string $content Specifies the evaluated page content.
	 */
	private function event_onApplyPageSecurity($page, $request_params) {}

	/**
	 * Allows to deny Google Analytics tracking code output on a page.
	 * The event handler should return FALSE to stop LemonStand from injecting
	 * Google Analytics tracking code to a page.
	 *
	 * Usage example:
	 * <pre>public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onBeforeTrackingCodeInclude', $this, 'before_tracking_code_include');
	 * }
	 *
	 * public function before_tracking_code_include($code)
	 * {
	 *   // Perform some checks
	 *   if ($some_condition)
	 *     return false;
	 *
	 *   return true;
	 * }</pre>
	 *
	 * @event cms:onBeforeTrackingCodeInclude
	 * @package cms.events
	 * @author LemonStand eCommerce Inc.
	 * @param string $code Specifies the tracking code string to be injected to the page.
	 * @return boolean Returns FALSE if the tracking code should not be displayed.
	 */
	private function event_onBeforeTrackingCodeInclude($code) {}

	/**
	 * Triggered before a partial is rendered.
	 * The event handler receives the same parameters as {@link Cms_Controller::render_partial()} method.
	 * A value returned by the handler does't affect the partial rendering process.
	 * @event cms:onBeforeRenderPartial
	 * @package cms.events
	 * @see cms:onAfterRenderPartial
	 * @author LemonStand eCommerce Inc.
	 * @param string $name Specifies the partial name.
	 * @param array $params A list of parameters to pass to the partial.
	 * @param array $options A list of options.
	 */
	private function event_onBeforeRenderPartial($name, $params, $options) {}

	/**
	 * Triggered before a partial is rendered.
	 * The event handler receives the same parameters as {@link Cms_Controller::render_partial()} method.
	 * A value returned by the handler does't affect the partial rendering process.
	 * @event cms:onAfterRenderPartial
	 * @package cms.events
	 * @see cms:onBeforeRenderPartial
	 * @author LemonStand eCommerce Inc.
	 * @param string $name Specifies the partial name.
	 * @param array $params A list of parameters to pass to the partial.
	 * @param array $options A list of options.
	 */
	private function event_onAfterRenderPartial($name, $params, $options) {}

	/**
	 * Allows to implement custom resource combining approaches.
	 * The event handler can return a string which is returned from {@link Cms_Controller::js_combine()}
	 * and {@link Cms_Controller::css_combine()} methods.
	 * The event handler receives an array of parameters with the following keys:
	 * <ul>
	 *   <li><em>type</em> - the resource type - css or javascript.</li>
	 *   <li><em>files</em> - a list of files passed to {@link Cms_Controller::js_combine()} or {@link Cms_Controller::css_combine()} method.</li>
	 *   <li><em>options</em> - a list of options passed to {@link Cms_Controller::js_combine()} or {@link Cms_Controller::css_combine()} method.</li>
	 *   <li><em>show_tag</em> - value of the $show_tag parameter passed to {@link Cms_Controller::js_combine()} or {@link Cms_Controller::css_combine()} method.</li>
	 * </ul>
	 * The handler should return a string which will be returned from js_combine() or css_combine() methods.
	 * @event cms:onBeforeResourceCombine
	 * @package cms.events
	 * @see Cms_Controller::js_combine()
	 * @see Cms_Controller::css_combine()
	 * @see http://lemonstand.com/docs/combining_and_minifying_javascript_and_css_files/ Combining and minifying JavaScript and CSS files
	 * @author LemonStand eCommerce Inc.
	 * @param array $params Specifies the partial name.
	 * @return string Returns the updated resources string if the default resource combiner result should be altered.
	 */
	private function event_onBeforeResourceCombine() {}

	/**
	 * Allows to override the default front-end page routing process.
	 * The event handler should parse the requested URL string and return the page object (Cms_Page)
	 * and a list of parameters which could be specified in the URL. The event handler should accept a
	 * single parameter - the URL string and return an array with two elements:
	 * <ul>
	 *   <li><em>page</em> - the CMS page object.</li>
	 *   <li><em>params</em> - the array of parameters extracted from the URL.</li>
	 * </ul>
	 * Even if there are no parameters required for the page, the <em>params</em> element should be
	 * presented in the result value as an empty array.
	 * The handler can return null or false to use the default LemonStand routing process.
	 * <pre>
	 * public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onBeforeRoute', $this, 'before_route');
	 * }
	 *
	 * public function before_route($url)
	 * {
	 *   // Always display the iPad product page, regardless of the actual URL
	 *   // Return the default product page with the first parameter containing the "ipad" string
	 *   $page = Cms_Page::create()->find_by_url('/product');
	 *
	 *   return array(
	 *     'page'=>$page,
	 *     'params'=>array('ipad')
	 *   );
	 * }
	 * </pre>
	 * @triggered /controllers/application.php
	 * @event cms:onBeforeRoute
	 * @package cms.events
	 * @author LemonStand eCommerce Inc.
	 * @param string $url Specifies the requested URL string.
	 * @return array Returns an array containing <em>page</em> and <em>params</em> elements.
	 */
	private function event_onBeforeRoute($url) {}

	/**
	 * Allows to override the default processing of not found front-end pages.
	 * By default, when the browser requests a page not found in LemonStand, the CMS page with the url
	 * name "/404" is displayed. Using this event, you can bypass the default behavior
	 * returning TRUE from the event handler.
	 * <pre>
	 * public function subscribeEvents()
	 * {
	 *   Backend::$events->addEvent('cms:onPageNotFound', $this, 'no_page_found');
	 * }
	 *
	 * public function no_page_found()
	 * {
	 *   // Do something
	 *   //...
	 *
	 *   // To avoid displaying the CMS page with "/404" url name, return TRUE
	 *   return true;
	 * }
	 * </pre>
	 * @triggered /controllers/application.php
	 * @event cms:onPageNotFound
	 * @package cms.events
	 * @author LemonStand eCommerce Inc.
	 * @return boolean Returns TRUE if the default behavior should be bypassed.
	 */
	private function event_onPageNotFound() {}

	/**
	 * Triggered before PHP code on a page, partial or layout code is evaluated.
	 * The event is triggered for all fields which support PHP, including page Pre and Post action codes, code blocks,
	 * AJAX event handlers, etc. The event handler should accept the array which contains the following elements:
	 * <ul>
	 *   <li><em>page</em> - specifies a page ({@link Cms_Page}) the code is being evaluated for.</li>
	 *   <li><em>controller</em> - specifies the controller ({@link Cms_Controller}) object.</li>
	 *   <li><em>params</em> - an array of PHP variables available in the code.</li>
	 *   <li><em>code</em> - specifies the PHP code to evaluate.</li>
	 *   <li><em>object_type</em> - specifies the CMS object type. Possible values are: <em>CMS page</em>, <em>CMS template</em>,
	 *     <em>CMS partial</em>, <em>CMS page block</em>, <em>CMS page head</em>.</li>
	 *   <li><em>object_name</em> - specifies the CMS object name.</li>
	 * </ul>
	 * The handler can return an array with the single element <em>content</em>. In this case LemonStand will use the returned value instead
	 * of evaluating the PHP code.
	 * @event cms:onEvaluateCode
	 * @package cms.events
	 * @author LemonStand eCommerce Inc.
	 * @param array $params An array of parameters.
	 * @return mixed Returns an array with <em>content</em> element or NULL.
	 */
	private function event_onEvaluateCode($params) {}
}

?>