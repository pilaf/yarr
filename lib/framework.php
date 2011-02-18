<?php

define('YARR_ROOT',        realpath(dirname(__FILE__) . '/../'));
define('YARR_APP_PATH',    YARR_ROOT . '/app');
define('YARR_LIB_PATH',    YARR_ROOT . '/lib');
define('YARR_LOG_PATH',    YARR_ROOT . '/log');
define('YARR_DB_PATH',     YARR_ROOT . '/db');
define('YARR_CONFIG_PATH', YARR_ROOT . '/config');
define('YARR_PUBLIC_PATH', YARR_ROOT . '/public');

define('DATE_SHORT', 'Y-m-d');
define('DATE_LONG', 'B e, Y');
define('DATE_DB', 'Y-m-d');
//define('DATE_DB', 'Ymd');

define('TIME_SHORT', 'H:i:s');

define('DATETIME_SHORT', DATE_SHORT . ' ' . TIME_SHORT);

/*
 * YARR - Yet Another Rails Ripoff - a PHP5 framework
 */
class YARR
{
	const VERSION_MAJOR = 0;
	const VERSION_MINOR = 1;
	const VERSION_REVISION = 0;
	
	static private $default_config = array(
		'load_modules' => array(
			'logger'            => true,
			'active_record'     => true,
			'action_controller' => true
		)
	);
	
	static private $config;
	
	/*
	 * Get framework version
	 */
	static function get_version()
	{
		return "{self::VERSION_MAJOR}.{self::VERSION_MINOR}.{self::VERSION_REVISION}";
	}
	
	/*
	 * Set everything up to use the framework
	 */
	static function boot($config = array())
	{
		/*
		 * Initialize configuration
		 */
		self::$config = $config;
		
		/*
		 * Perform some cleanup: mainly making sure that things work more or less
		 * the same across all PHP servers.
		 */
		self::initialize_php_environment();
		
		/*
		 * Next, load some modules.
		 *
		 * Initialize logger
		 */
		self::initialize_logger();
		
		/*
		 * Load ActiveRecord class and establish database connection
		 */
		self::initialize_active_record();
		
		/*
		 * Load ActionController class and load routes
		 */
		self::initialize_action_controller();
		
		/*
		 * Do some more initializations
		 */
		self::initialize_class_autoloader();
		self::initialize_error_handlers();
	}
	
	static function read_config($path)
	{
		$segments = split('/', $path);
		
		$value = null;
		
		if (self::find_in_config(self::$config, $segments, $value) || self::find_in_config(self::$default_config, $segments, $value)) {
			return $value;
		} else {
			throw new Exception("Couldn't find config value with key `$path'");
		}
	}
	
	static function require_lib($lib)
	{
		require_once(YARR_LIB_PATH . '/' . $lib . '.php');
	}
	
	/*
	 * Remove magic quotes from an array recursively
	 */
	static function stripslashes_deep(&$value)
	{
		$value = is_array($value) ? array_map(array(self, 'stripslashes_deep'), $value) : stripslashes($value);
		return $value;
	}
	
	/*
	 * Remove magic quotes from PHP superglobals
	 */
	static function clear_magic_quotes()
	{
		if ((function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc()) || (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase')) != "off")) ){
			self::stripslashes_deep($_GET);
			self::stripslashes_deep($_POST);
			self::stripslashes_deep($_COOKIE);
		}
	}
	
	static function add_default_config($key, $value)
	{
		if (!isset(self::$default_config[$key])) {
			self::$default_config[$key] = $value;
			return true;
		} else {
			return false;
		}
	}
	
	static private function find_in_config(&$array, &$segments, &$value)
	{
		$current_level = &$array;
		
		foreach ($segments as $key) {
			if (isset($current_level[$key])) {
				if (is_array($current_level[$key])) {
					$current_level = &$current_level[$key];
				} else {
					$value = $current_level[$key];
					return true;
				}
			} else {
				return false;
			}
		}
		
		$value = $current_level;
		
		return true;
	}
	
	static private function initialize_action_controller()
	{
		if (self::read_config('load_modules/action_controller')) {
			self::require_lib('action_controller/action_controller');
			require_once YARR_CONFIG_PATH . '/routes.php';
		}
	}
	
	static private function initialize_active_record()
	{
		if (self::read_config('load_modules/active_record')) {
			self::require_lib('active_record/active_record');
			ActiveRecord::establish_connection(self::read_config('database'));
		}
	}
	
	static private function initialize_logger()
	{
		if (self::read_config('load_modules/logger')) {
			self::require_lib('logger');
			Logger::open(YARR_LOG_PATH . '/application.log');
		}
	}
	
	static private function initialize_php_environment()
	{
    // Check if we're in command line mode and avoid doing
    // HTTP-specific maintenance
    if (php_sapi_name() != 'cli') {
      /*
       * Disable output buffering (this is handled manually by the dispatcher)
       */
      ob_end_clean();
      
      /*
       * Get rid of nasty magic quotes slashes
       */
      self::clear_magic_quotes();
      
      /*
       * Start browser session
       */
      session_start();
    }
	}
	
	/*
	 * Register a PHP class autoloader function to automatically load models
	 */
	static private function initialize_class_autoloader()
	{
		if (!function_exists('__autoload')) {
			
			function __autoload($class_name)
			{
				$file_name = YARR_APP_PATH . '/models/' . Inflector::uncamelize($class_name) . '.php';
				
				if (file_exists($file_name)) {
					require_once $file_name;
				}
			}
			
		}
	}
	
	static private function initialize_error_handlers()
	{
	}
}

/*
define('LIB_PATH', dirname(__FILE__));
define('APP_PATH', LIB_PATH . '/../app');
define('LOG_PATH', LIB_PATH . '/../log');
define('CONFIG_PATH', LIB_PATH . '/../config');
define('PUBLIC_PATH', LIB_PATH . '/../public');


function config($value)
{
	global $CONFIG;
	return $CONFIG[$value];
}

function lib($lib)
{
	return LIB_PATH . "/$lib.php";
}

function config_file($file)
{
	return CONFIG_PATH . "/$file.php";
}

function load_models()
{
	$models = glob(APP_PATH . '/models/*.php');
	foreach ($models as $model) {
		require $model;
	}
}

function parse_request_uri()
{
	$path = split('/', trim($_SERVER['REQUEST_URI'], '/'));
	$controller = array_shift($path);
	$action     = array_shift($path);
	return array(
		'controller' => $controller ? $controller : config('default_controller'),
		'action'     => $action ? $action : 'index',
		'params'     => $path
	);
}

function action_script_path($path) {
	return APP_PATH . "/controllers/$path[controller]/$path[action].php";
}

function view_path($path) {
	return APP_PATH . "/views/$path[controller]/$path[action].html.php";
}

function render_404()
{
	header('Status: 404');
	if (file_exists(PUBLIC_PATH . '/404.html')) {
		echo file_get_contents(PUBLIC_PATH . '/404.html');
	} else {
		echo '<html><head></head><body><h1>404</h1></body></html>';
	}
}

function set_content_for($section)
{
	global $CONTENT_SECTIONS;
	if (!is_array($CONTENT_SECTIONS)) {
		$CONTENT_SECTIONS = array($section => ob_get_clean());
	} else {
		$CONTENT_SECTIONS[$section] = ob_get_clean();
	}
}

function content_for($section)
{
	return $GLOBALS['CONTENT_SECTIONS'][$section];
}
*/
