<?php

// TODO: Document this class

class Route
{
	static $default_defaults = array('action' => 'index');
	
	private $mask;
	private $defaults;
	private $requirements;
	private $segments;
	private $named_segments = array();
	private $params = array();
	
	// this keeps the maximum number of segments of a route that
	// are actually needed for the route to be matched.
	// this is used to build optimal (compact) urls.
	private $first_named_segment_position = 0;
	private $has_catchall;
	
	/*
	 ************************************************************
	 * Public instance methods
	 ************************************************************
	 */
	
	function __construct($mask, $defaults = null, $requirements = null)
	{
		$this->mask = $mask;
		
		$this->requirements = $requirements;
		
		$this->initialize_defaults($defaults);
		
		$this->initialize_segments();
		
		$this->initialize_named_segments();
		
		$this->merge_default_params();
		
		$this->initialize_has_catchall();
	}
	
	/*
	 * Returns the matching params if the route matches this path, and false otherwise.
	 */
	function match_path($path)
	{
		$path = $this->remove_bounding_slashes($path);
		
		if (empty($this->mask)) {
			return ($path == '') ? $this->defaults : false;
		}
		
		$path_segments = $this->split_path($path);
		$match_params = array();
		
		foreach ($this->segments as $segment) {
			if ($this->segment_is_named($segment)) {
				
				$this->match_named_segment($segment, $path_segments, $match_params);
				
			} else if ($this->segment_is_catchall($segment)) {
				
				$match_params[$this->segment_name($segment)] = $path_segments;
				
				break;
				
			} else if ($segment != array_shift($path_segments)) {
				
				return false;
				
			}
		}
		
		return $this->merge_with_defaults($match_params);
	}
	
	/*
	 * Returns the path corresponding to the passed params if they match this route, and false otherwise.
	 */
	function match_params($params)
	{
		foreach ($params as $name => $value) {
			//if ($value !== null) {
				$this->normalize_param($value);
				
				$belongs = $this->named_segment_exists($name) || $this->matches_default_value($name, $value);
				
				if (!$belongs || !$this->check_requirement($name, $value)) {
					return false;
				}
			//}
		}
		
		return $this->build_path($params);
	}
	
	/*
	 * Builds a minimum path with the given params.
	 */
	function build_path($params)
	{
		$path = '';
		
		$limit = $this->find_last_non_default_position_for($params);
		
		foreach ($this->segments as $index => $segment) {
			if ($this->segment_is_named($segment)) {
				
				if ($index <= $limit) {
					if ($value = $this->param_or_default($params, $this->named_segments[$index])) { 
						$this->add_path_segment($path, $value);
					} else {
						throw new RoutingException("Not enough parameters to build path. Missing `{$this->named_segments[$index]}'.");
					}
				} else {
					break;
				}
				
			} else if ($this->segment_is_catchall($segment)) {
				
				if (isset($params[$this->named_segments[$index]])) {
					$this->add_path_segment($path, join('/', $params[$this->named_segments[$index]]));
				}
				
				break;
				
			} else {
				$this->add_path_segment($path, $segment);
			}
		}
		
		return empty($path) ? '/' : $path;
	}
	
	/*
	 ************************************************************
	 * Private instance methods
	 ************************************************************
	 */
	
	private function normalize_param(&$param)
	{
		if (is_object($param)) {
			if (method_exists($param, 'to_param')) {
				$param = $param->to_param();
			} else {
				$param = (string)$param;
			}
		}
	}
	
	private function match_named_segment($segment, &$path_segments, &$match_params)
	{
		$segment_name = $this->segment_name($segment);
		
		if ($segment_name == 'controller') {
			
			$this->match_controller_segment($path_segments, $match_params);
			
		} else if ($candidate = array_shift($path_segments)) {
			
			if (!$this->check_requirement($segment_name, $candidate)) {
				return false;
			}
			
			$match_params[$segment_name] = $candidate;
			
		}
	}
	
	private function match_controller_segment(&$path_segments, &$match_params)
	{
		$tmp_segments = $path_segments;
		
		do {
			$controller = join('/', $tmp_segments);
			
			if (ActionController::controller_exists($controller)) {
				$match_params['controller'] = $controller;
				$path_segments = array_slice($path_segments, count($tmp_segments));
				return true;
			}
		} while (array_pop($tmp_segments));
		
		throw new ControllerNotMatchedException('No existing controller found matching the given route.');
	}
	
	private function add_path_segment(&$path, $segment)
	{
		$path .= '/' . $segment;
	}
	
	private function param_or_default(&$params, $name)
	{
		if ($value = isset($params[$name]) ? $params[$name] : $this->defaults[$name]) {
			$this->normalize_param($value);
			return $this->remove_bounding_slashes($value);
		} else {
			return false;
		}
	}
	
	private function remove_bounding_slashes($string)
	{
		return trim($string, '/');
	}
	
	private function find_last_non_default_position_for(&$params)
	{
		$pos = $this->first_named_segment_position;
		
		$segments_count = count($this->segments);
		
		for ($i = $pos; $i < $segments_count; ++$i) {
			$named_segment = $this->named_segments[$i];
			
			if (isset($params[$named_segment]) && !$this->matches_default_value($named_segment, $params[$named_segment])) {
				$pos = $i;
			}
		}
		
		return $pos;
	}
	
	private function matches_default_value($name, $value)
	{
		return isset($this->defaults[$name]) && $this->defaults[$name] == $value;
	}
	
	private function named_segment_exists($name)
	{
		return in_array(":$name", $this->segments) || end($this->segments) == "*$name";
	}
	
	private function merge_with_defaults($params)
	{
		return array_merge($this->defaults, $params);
	}
	
	private function check_requirement($name, $value)
	{
		if (isset($this->requirements[$name])) {
			return preg_match($this->requirements[$name], $value);
		} else {
			return true;
		}
	}
	
	private function segment_name(&$segment)
	{
		return substr($segment, 1);
	}
	
	private function segment_is_named(&$segment)
	{
		return $segment[0] == ':';
	}
	
	private function segment_is_catchall(&$segment)
	{
		return $segment[0] == '*';
	}
	
	private function merge_default_params()
	{
		foreach (array_keys($this->defaults) as $name) {
			if (!in_array($name, $this->params)) $this->params[] = $name;
		}
	}
	
	private function initialize_has_catchall()
	{
		$this->has_catchall = $this->segments[count($this->segments)-1][0] == '*';
	}
	
	private function initialize_named_segments()
	{
		foreach ($this->segments as $index => $value) {
			if ($value[0] != '*' && $value[0] != ':') {
				$this->named_segments[] = null;
				$this->first_named_segment_position = $index + 1;
			} else {
				$this->named_segments[] = $param = substr($value, 1);
				if (!in_array($param, $this->params)) $this->params[] = $param;
			}
		}
	}
	
	private function initialize_segments()
	{
		// split the mask
		$this->segments = $this->split_path($this->mask);
	}
	
	private function initialize_defaults($defaults)
	{
		$this->defaults = $defaults ? array_merge(self::$default_defaults, $defaults) : self::$default_defaults;
	}
	
	/*
	 ************************************************************
	 * Public static methods
	 ************************************************************
	 */
	
	static function split_path($path)
	{
		return ($path != '') ? explode('/', $path) : array();
	}
}