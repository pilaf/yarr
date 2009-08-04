<?php

class Routes
{
	static private $routes = array();
	static private $root;
	
	/*
	static private $routes_args = array();
	static private $current_pointer = 0;
	*/
	
	function __construct()
	{
		throw new Exception("Routes class must not be instantiated. Maybe you wanted `Route'?");
	}
	
	static function root($defaults)
	{
		//self::map('', $defaults);
		self::$root = new Route('', $defaults);
	}
	
	static function map($mask, $defaults = null, $requirements = null)
	{
		self::$routes[] = new Route($mask, $defaults, $requirements);
		
		// TODO: finish this!
		// Avoid instantiating routes right away if we might never use them in this request
		//self::$routes_args[] array($mask, $defaults, $requirements);
	}
	
	static function find_route($path, &$matching_route)
	{
		array_unshift(self::$routes, self::$root);
		
		foreach (self::$routes as $route) {
			if (($match = $route->match_path($path)) !== false) {
				$matching_route = $route;
				array_shift(self::$routes);
				return $match;
			}
		}
		
		array_shift(self::$routes);
		return false;
	}
	
	static function find_path($params)
	{
		array_unshift(self::$routes, self::$root);
		
		foreach (self::$routes as $route) {
			if (($url = $route->match_params($params)) !== false) {
				array_shift(self::$routes);
				return $url;
			}
		}
		
		array_shift(self::$routes);
		return false;
	}
	
	/*
	static private function reset_routes_pointer()
	{
		reset($routes_args);
		reset($routes);
		$current_pointer = 0;
	}
	
	static private function next_route()
	{
		if ($route = next($routes)) {
			next($routes_args);
			return $route;
		} else if (
		}
	}
	*/
}