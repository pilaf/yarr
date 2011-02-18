<?php

define('ACTION_CONTROLLER_BASE_PATH', dirname(__FILE__));

require_once ACTION_CONTROLLER_BASE_PATH . '/action_controller_exceptions.php';
require_once ACTION_CONTROLLER_BASE_PATH . '/routing.php';
require_once ACTION_CONTROLLER_BASE_PATH . '/template.php';

if (class_exists('YARR')) {
	
	YARR::add_default_config('action_controller', array(
		'controllers_path' => YARR_APP_PATH . '/controllers'
	));
	
	YARR::require_lib('inflector');
	
} else {
	
	require_once 'inflector.php';
	
}

/*
 * ActionController class
 *
 * TODO: Add documentation!
 *
 *
 *** Actions ***
 *
 * Actions are public non-static methods defined in the controller class being instantiated (i.e.
 * the end of the inheritance chain).
 *
 * To avoid common keyword collitions (such as new), action methods can take an trailing underscore
 * (e.g. new_).
 *
 *
 *** Filters ***
 *
 * There are two non-exclusive ways of defining filters:
 *
 * 1) Create a static <prefix>_filters (plural) method returning a list of filters to run, e.g.:
 *
 * 		public static function before_filters()
 * 		{
 * 			return 'require_login';
 * 		}
 *
 * 		public static function before_filters()
 * 		{
 * 			return array('require_login', 'do_something_else');
 * 		}
 *
 * 		public static function before_filters()
 * 		{
 * 			return array('require_login', 'do_something_else' => array('only' => 'index'));
 * 		}
 *
 * 2) Define a <prefix>_filter (singular):
 *
 * 		protected function before_filter()
 * 		{
 * 			if (!$this->logged_in()) $this->redirect_to(somewhere);
 * 		}
 *
 */
abstract class ActionController
{
	const HTTP_OK = 200;
	const HTTP_BAD_REQUEST = 500;
	
	public $request;
	public $params;
	public $headers;
	public $flash = array();
	
	private $properties = array();
	
	private $performed = false;
	private $performance_action;
	
	public $debug_buffer;
	
	private $inheritance_chain;
	
	public static function layout()
	{
		return 'application';
	}
	
	/*
	 ************************************************************
	 * Public instance methods
	 ************************************************************
	 */
	
	function __construct($request, $params)
	{
		$this->request = $request;
		
		$this->initialize_params($params);
		$this->initialize_headers();
		//$this->params = $params;
	}
	
	function __set($name, $value)
	{
		return $this->properties[$name] = $value;
	}
	
	function __get($name)
	{
		return @$this->properties[$name];
	}
	
	function __isset($name)
	{
		return isset($this->properties[$name]);
	}
	
	function invoke_action($action)
	{
		if (class_exists('Logger')) {
			Logger::info('Processing ' . get_class($this) . '#' . $action . ' - parameters: ' . print_r($this->params, true));
		}
		
		if ($this->valid_action($action)) {
			
			$method = $action;
		
		// Allow naming action methods with a trailing underscore to prevent keyword collitions (e.g. new -> new_)
		} else if ($this->valid_action($action . '_')) {
			
			$method = $action . '_';
			
		} else {
			
			throw new UnknownActionException('Undefined action: ' . $action . ' in ' . get_class($this));
			
			/*
			if (method_exists(array($this, 'rescue_action')) && !$this->valid_action('rescue_action')) {
				
				//$this->rescue_action($exception);
				$method = 'rescue_action';
				
			} else {
				throw $exception;
			}
			*/
			
		}
		
		$this->run_action($method);
	}
	
	function valid_action($action_name)
	{
		if (!method_exists($this, $action_name)) {
			return false;
		}
		
		$class_name = get_class($this);
		
		$method = new ReflectionMethod($class_name, $action_name);
		
		return $method->isPublic() && !$method->isStatic() && ($method->getDeclaringClass()->getName() == $class_name);
	}
	
	function is_xhr()
	{
		// TODO: find more abstract way around this
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	}
	
	/*
	 ************************************************************
	 * Protected instance methods
	 ************************************************************
	 */
	
	protected function render($options)
	{
		$this->set_performance_action('do_render', array($options));
	}
	
	protected function redirect_to($url)
	{
		$this->set_performance_action('do_redirect', array($url));
	}
	
	protected function head($status)
	{
		$this->set_performance_action('do_head', array($status));
	}
	
	protected function before_filter()
	{
	}
	
	/*
	 ************************************************************
	 * Private instance methods
	 ************************************************************
	 */
	
	private function run_action($action, $action_method = null)
	{
		if (!isset($action_method)) $action_method = $action;
		
		$this->load_flash();
		
		ob_start();
		
		// Run before filters
		$this->run_filters('before', $action);
		
		// Call the actual action method (only if no filters performed first)
		if (!$this->performed) {
			$this->$action_method();
		}
		
		// TODO: do something useful with this, maybe a debug pane?
		$this->debug_buffer = ob_get_clean();
		
		$this->persist_flash();
		
		if (!$this->performed) {
			$this->render_default();
		}
		
		$this->perform();
	}
	
	private function run_filters($prefix, $action)
	{
		$filters = $this->get_filters($prefix);
		
		foreach ($filters as $method => $options) {
			if (is_array($options)) {
				if (isset($options['only'])) {
					if (!in_array($action, (array)$options['only'])) {
						continue;
					}
				}
				
				if (isset($options['except'])) {
					if (in_array($action, (array)$options['except'])) {
						continue;
					}
				}
			}
			
			$this->$method();
			
			if ($this->performed) break;
		}
	}
	
	private function &get_filters($prefix)
	{
		$filters = array($prefix . '_filter' => true);
		
		foreach ($this->get_inheritance_chain() as $class) {
			if (method_exists($class, $prefix . '_filters')) {
				$filters += $this->normalize_filters_array((array)call_user_func(array($class, $prefix . '_filters')));
			}
		}
		
		return $filters;
	}
	
	private function normalize_filters_array($filters)
	{
		$normalized_filters = array();
		
		foreach ($filters as $key => $value) {
			if (is_numeric($key) && is_string($value)) {
				$normalized_filters[$value] = true;
				unset($filters[$key]);
			}
		}
		
		return $normalized_filters + $filters;
	}
	
	private function &get_inheritance_chain()
	{
		if (!isset($this->inheritance_chain)) {
			$class = get_class($this);
			
			$this->inheritance_chain = array($class);
			
			while (($class = get_parent_class($class)) && $class != __CLASS__) {
				array_unshift($this->inheritance_chain, $class);
			}
		}
		
		return $this->inheritance_chain;
	}
	
	private function load_flash()
	{
		if (!empty($_SESSION['flash'])) {
			$this->flash = $_SESSION['flash'];
			$this->clear_flash();
		}
	}
	
	private function persist_flash()
	{
		if (!empty($this->flash)) {
			$_SESSION['flash'] = $this->flash;
		}
	}
	
	private function clear_flash()
	{
		unset($_SESSION['flash']);
	}
	
	private function render_default()
	{
		$this->set_performance_action('do_render');
	}
	
	private function check_not_performed()
	{
		if ($this->performed) {
			// TODO: make a nicer exception for this
			throw new ControllerException("Double render!");
		}
	}
	
	private function set_performance_action($action, $params = array())
	{
		$this->check_not_performed();
		$this->performance_action = array($action, $params);
		$this->performed = true;
	}
	
	private function perform()
	{
		call_user_func_array(array($this, $this->performance_action[0]), $this->performance_action[1]);
	}
	
	private function echo_friendly_error($title, $message)
	{
		echo '<!DOCTYPE html><html><head><title>';
		echo $title;
		echo '</title></head><body style="font-family:sans-serif;"><h1 style="color:#333333;">';
		echo $title;
		echo '</h1><div>';
		echo $message;
		echo '</div></body></html>';
	}
	
	private function do_render($options = array())
	{
		$template = new Template($this, $options, $this->properties);
		
		try { echo $template->process(); } catch (TemplateMissingException $e) {
			
			$this->echo_friendly_error('Template Missing', 'Template <tt>\'' . htmlentities($e->getMessage()) . '\'</tt> not found.');
			
			return false;
			
		}
		
		$this->clear_flash();
	}
	
	private function do_head($status)
	{
		header("HTTP/1.0 $status");
	}
	
	private function do_redirect($url)
	{
		if (is_array($url)) {
			$url = $this->url_for($url);
		}
		
		header("Location: $url");
	}
	
	public function url_for($params, $options = array())
	{
		if (!isset($params['controller'])) {
			$params['controller'] = $this->params['controller'];
		}
		
		return Routes::find_path($params);
	}
	
	private function initialize_headers()
	{
		if (function_exists('apache_request_headers')) {
			$this->headers = apache_request_headers();
		}
	}
	
	private function initialize_params(&$params)
	{
		$this->params = $params;
		
		foreach ($_GET as $key => &$value) {
			$this->params[$key] = &$value;
		}
		
		if (isset($_POST)) {
			foreach ($_POST as $key => &$value) {
				$this->params[$key] = &$value;
			}
		}
		
		$this->initialize_file_uploads();
	}
	
	private function initialize_file_uploads()
	{
		if (isset($_FILES) && !empty($_FILES)) {
			require_once ACTION_CONTROLLER_BASE_PATH . '/file_upload.php';
			
			foreach ($_FILES as $key => &$value) {
				if (is_array($value['name'])) {
					$path = array();
					$this->initialize_file_uploads_recursive($key, $value, $value['name'], $path);
				} else {
					$this->params[$key] = new FileUpload($value);
				}
			}
		}
	}
	
	/*
	 * This is needed to fix the convluted mess of PHP's $_FILES superglobal when we're dealing with
	 * nested attribute names (e.g.: <input type="file" name="article[thumbnail]">)
	 */
	private function initialize_file_uploads_recursive(&$prefix, &$root, &$current_name_level, &$path)
	{
		/*
		 * We iterate through each key in the current recursion tree level
		 */
		foreach ($current_name_level as $key => &$value) {
			/*
			 * If we're on a subtree node, then recurse
			 */
			if (is_array($value)) {
				
				$path[] = $key;
				
				$this->initialize_file_uploads_recursive($prefix, $root, $value, $path);
				
			/*
			 * Otherwise we're on a leaf and ready to initialize the FileUpload instance
			 */
			} else {
				
				/*
				 * First construct the args array
				 */
				$args = array('name' => $value);
				
				foreach (array('type', 'size', 'tmp_name', 'error') as $arg) {
					$current_arg_level = &$root[$arg];
					
					foreach ($path as &$level) {
						$current_arg_level = &$current_arg_level[$level];
					}
					
					$args[$arg] = $current_arg_level[$key];
				}
				
				/*
				 * Now find the right place to put it and instantiate the FileUpload
				 */
				$current_params_level = &$this->params[$prefix];
				
				foreach ($path as &$level) {
					if (!is_array($current_params_level[$level])) {
						$current_params_level[$level] = array();
					}
					
					$current_params_level = &$current_params_level[$level];
				}
				
				$current_params_level[$key] = new FileUpload($args);
				
				/*
				 * Since the upper level recursion will go on, we need to clean up after ourselves
				 */
				array_pop($path);
			}
		}
	}
	
	/*
	 ************************************************************
	 * Public class methods
	 ************************************************************
	 */
	
	/*
	 * Find a matching route for the given path, initialize the
	 * corresponding controller and invoke its action
	 */
	static function dispatch($path)
	{
		// Strip query string from path:
		$path = preg_replace('/\?.+\Z/', '', $path);
		
		try {
			
			$params = Routes::find_route($path, $route);
			
			self::load_application_controller();
			
			self::load_module_controllers($params['controller']);
			
			$class_name = self::load_controller($params['controller']);
			
			$controller = new $class_name($path, $params);
			
			$controller->invoke_action($params['action']);
			
		} catch (Exception $exception) {
			
			// TODO; this is a horrible solution, need to come up with something cleaner/more flexible
			if ($exception instanceof UnknownActionException || $exception instanceof ControllerNotMatchedException) {
				
				header('HTTP/1.0 404 Not Found');
				
				if (file_exists(YARR_PUBLIC_PATH . '/404.html')) {
					echo file_get_contents(YARR_PUBLIC_PATH . '/404.html');
				}
				
			} else {
				
				throw $exception;
				
			}
			
			/*
			if ($exception instanceof ControllerNotMatchedException) {
				
				
				
			} else if (isset($controller)) {
				
			}
			*/
			
		}
	}
	
	static function controller_exists($controller_name)
	{
		return file_exists(self::controller_file_name($controller_name));
	}
	
	static function controller_file_name($controller_name)
	{
		return YARR::read_config('action_controller/controllers_path') . '/' . $controller_name . '_controller.php';
	}
	
	static function controller_class_name($controller_name)
	{
		return implode('_', array_map(array('Inflector', 'camelize'), split('/', $controller_name))) . 'Controller';
	}
	
	/*
	 * Load a controller class and return its class name
	 */
	static function load_controller($controller_name)
	{
		if (self::controller_exists($controller_name)) {
			$file_name = self::controller_file_name($controller_name);
			
			require_once $file_name;
			
			$class_name = self::controller_class_name($controller_name);
			
			if (class_exists($class_name) && is_subclass_of($class_name, 'ActionController')) {
				//return new $class_name($request, $params);
				return $class_name;
			} else {
				throw new ControllerException("Expected file `$file_name' to define class `$class_name'.");
			}
		} else {
			throw new ControllerException("Couldn't find controller `$controller_name'.");
		}
	}
	
	/*
	 ************************************************************
	 * Private class methods
	 ************************************************************
	 */
	
	/*
	 * Load the ApplicationController class
	 */
	static private function load_application_controller()
	{
		self::load_controller('application');
	}
	
	/*
	 * Load module controllers for namespaced controllers
	 */
	static private function load_module_controllers($controller_name)
	{
		if (!strstr($controller_name, '/')) return;
		
		$segments = split('/', $controller_name);
		
		array_pop($segments);
		
		$module_controllers = array();
		
		while ($module_controllers[] = array_shift($segments)) {
			self::load_controller(join('/', $module_controllers) . '/' . end($module_controllers));
		}
	}
}
