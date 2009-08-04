<?php

/*
 * Proxy class to call static methods for a class where the class name of the called method
 * is needed as a first parameter (this is obsolote in PHP 5.3 with the new static keyword).
 *
 * E.g.:
 *
 * For ActiveRecord's finder methods (all of which take $class_name as their first parameter),
 * we could use:
 *
 * 		$people_finder = new ClassStaticProxy('Person');
 * 		$people_finder->find('foo');
 *
 * (that is assuming we don't override the finder methods in the Person class)
 */
class ClassStaticProxy
{
	private $class_name;
	private $call_class_name;
	
	function __construct($class_name, $call_class_name = null)
	{
		$this->class_name = $class_name;
		$this->call_class_name = $call_class_name ? $call_class_name : $class_name;
	}
	
	function __call($name, $args)
	{
		if (is_callable(array($this->class_name, $name))) {
			array_unshift($args, $this->call_class_name);
			return call_user_func_array(array($this->class_name, $name), $args);
		} else {
			throw new Exception("Method {$this->class_name}::$name is uncallable.");
		}
	}
}