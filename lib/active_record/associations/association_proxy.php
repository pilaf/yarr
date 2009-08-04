<?php

abstract class AssociationProxy
{
	protected $owner;
	protected $association_options;
	
	function __construct(&$owner, &$association_options)
	{
		$this->owner = &$owner;
		$this->association_options = $association_options;
	}
	
	function is_collection()
	{
		return false;
	}
	
	abstract function get();
	
	static function initialize_association_options($class_name, $plural_name, &$options)
	{
		if (!isset($options['class_name'])) {
			$options['class_name'] = Inflector::classify($plural_name);
		}
		
		if (!isset($options['foreign_key'])) {
			$options['foreign_key'] = Inflector::uncamelize($class_name) . '_id';
		}
		
		if (!isset($options['validate'])) {
			$options['validate'] = true;
		}
	}
	
	protected function class_name()
	{
		return $this->association_options['class_name'];
	}
	
	protected function foreign_key()
	{
		return $this->association_options['foreign_key'];
	}
}