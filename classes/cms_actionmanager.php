<?php

	class Cms_ActionManager
	{
		protected static $action_list = null;
		
		public static function listActions()
		{
			if (self::$action_list !== null)
				return self::$action_list;

			self::$action_list = array();

			$modulesPath = PATH_APP."/modules";
			$iterator = new DirectoryIterator( $modulesPath );
			foreach ( $iterator as $dir )
			{
				if ( $dir->isDir() && !$dir->isDot() )
				{
					$dirPath = $modulesPath."/".$dir->getFilename();
					$moduleId = $dir->getFilename();

					$modulePath = $dirPath."/classes/".$moduleId."_actions.php";

					if (!file_exists($modulePath))
						continue;

					if ( Phpr::$classLoader->load($className = $moduleId."_Actions") )
						self::loadScopeActions($moduleId, $className);
				}
			}
			
			sort(self::$action_list);
			return self::$action_list;
		}

		protected static function loadScopeActions($moduleId, $className)
		{
			$classInfo = new ReflectionClass($className);
			$methods = $classInfo->getMethods();
			foreach ($methods as $method)
			{
				$methodName = $method->getName();
				$declaringClass = $method->getDeclaringClass();
				
				if(strtolower($declaringClass->name) !== strtolower($className))
					continue; // method declared by a subclass, so let's continue
					
				$isHidden = substr($methodName, 0, 1) == '_';
				$isEventHandler = preg_match('/^on_/', $methodName);

				if ($method->isPublic() && $declaringClass->name != 'Cms_Controller' && !$isHidden && !$isEventHandler)
					self::$action_list[] = $moduleId.':'.$methodName;
			}
		}
		
		public static function execAction($name, $controller)
		{
			$parts = explode(':', $name);
			if (count($parts) != 2)
				throw new Phpr_ApplicationException("Invalid action identifier: $name");
				
			$className = ucfirst($parts[0]).'_Actions';
			if (!Phpr::$classLoader->load($className))
				throw new Phpr_ApplicationException("Actions scope class is not found: $className");

			$obj = new $className(false);
			$obj->copy_context_from($controller);
			$method = $parts[1];
			try
			{
				$obj->$method();
				$controller->copy_context_from($obj);
			} catch (Exception $ex)
			{
				$controller->copy_context_from($obj);
				throw $ex;
			}
		}
		
		public static function execAjaxHandler($name, $controller)
		{
			$parts = explode(':', $name);
			if (count($parts) != 2)
				throw new Phpr_ApplicationException("Invalid event handler identifier: $name");
				
			$className = ucfirst($parts[0]).'_Actions';
			if (!Phpr::$classLoader->load($className))
				throw new Phpr_ApplicationException("Actions scope class is not found: $className");
				
			$method = $parts[1];
			$isEventHandler = preg_match('/^on_/', $method);
			if (!$isEventHandler)
				throw new Phpr_ApplicationException("Specified method is not AJAX event handler: $method");

			$obj = new $className();
			if (!method_exists($obj, $method))
				throw new Phpr_ApplicationException("AJAX handler not found: $name");
			
			$obj->copy_context_from($controller);
			try
			{
				$result = $obj->$method();
				$controller->copy_context_from($obj);
				return $result;
			}
			catch (Exception $ex)
			{
				$controller->copy_context_from($obj);
				throw $ex;
			}
		}
		
		public static function getActionInfo($name)
		{
			$result = array('info'=>null, 'examples'=>null);
			$result = (object)$result;
			
			$action_parts = explode(':', $name);
			if (count($action_parts) != 2)
				return $result;
				
			$doc_path = PATH_APP.'/modules/'.$action_parts[0].'/docs/actions/'.$action_parts[1].'.htm';
			if (!file_exists($doc_path))
				return $result;
				
			$file_content = file_get_contents($doc_path);
			if (($examples_pos = strpos($file_content, '[EXAMPLES]')) === false)
			{
				$result->info = $file_content;
			} else
			{
				$result->info = substr($file_content, 0, $examples_pos);
				$result->examples = trim(substr($file_content, $examples_pos+10));
			}
			
			$result->examples = self::processActionInfoStr($action_parts[0], $action_parts[1], $result->examples);
			$result->info = self::processActionInfoStr($action_parts[0], $action_parts[1], $result->info);
			
			return $result;
		}
		
		protected static function processActionInfoStr($module, $action, $str)
		{
			return preg_replace('/<a\s*href="@([a-z0-9_]+)"\s*>/', '<a href="#" onclick="new PopupForm(\'onShowActionDocument\', {ajaxFields: {\'module\': \''.$module.'\', \'name\': \'$1\'}}); return false;">', $str);
		}
	}

?>