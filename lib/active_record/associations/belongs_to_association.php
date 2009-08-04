<?php

class BelongsToAssociation extends AssociationProxy
{
	private $record;
	
	function get()
	{
		if (!isset($this->record)) {
			$this->find_record();
		}
		
		return $this->record;
	}
	
	function replace($record)
	{
		$this->record = $record;
		$this->owner->{$this->foreign_key()} = $record->id;
		
		if ($this->is_polymorphic()) {
			$this->owner->{$this->foreign_type()} = get_class($record);
		}
	}
	
	private function find_record()
	{
		if ($id = $this->owner->{$this->foreign_key()}) {
			
			$primary_key = call_user_func(array($this->class_name(), 'primary_key'));
			
			$this->record = call_user_func(
				array('ActiveRecord', 'find_first'),
				$this->class_name(),
				array('conditions' => array($primary_key => $id))
			);
		}
	}
	
	private function is_polymorphic()
	{
		return isset($this->association_options['polymorphic']) && $this->association_options['polymorphic'];
	}
	
	private function foreign_type()
	{
		return $this->association_options['foreign_type'];
	}
	
	protected function class_name()
	{
		if ($this->is_polymorphic()) {
			return $this->owner->{$this->foreign_type()};
		} else {
			return parent::class_name();
		}
	}
	
	static function initialize_association_options($class_name, $singular_name, &$options)
	{
		if (!isset($options['class_name'])) {
			$options['class_name'] = Inflector::camelize($singular_name);
		}
		
		if (!isset($options['foreign_key'])) {
			$options['foreign_key'] = Inflector::uncamelize($options['class_name']) . '_id';
		}
		
		if (isset($options['polymorphic']) && $options['polymorphic']) {
			if (!isset($options['foreign_type'])) {
				$options['foreign_type'] = Inflector::uncamelize($options['class_name']) . '_type';
			}
		}
		
		if (!isset($options['validate'])) {
			$options['validate'] = true;
		}
	}
}