<?php

class HasManyAssociation extends AssociationCollection
{
	protected function prepare_conditions(&$options)
	{
		parent::prepare_conditions($options);
		
		if (!empty($options['conditions'])) {
			$options['conditions'] .= ' AND ';
		} else {
			$options['conditions'] = '';
		}
		
		$options['conditions'] .= $this->quoted_table_name() . '.' . $this->quoted_foreign_key() . ' = ' . $this->owner->get_quoted_id();
		
		if ($this->is_polymorphic()) {
			$options['conditions'] .= ' AND ' . $this->quoted_table_name() . '.' . $this->quoted_foreign_type() . ' = ' . ActiveRecord::$connection->quote_string(get_class($this->owner));
		}
	}
	
	function build($attributes = array())
	{
		$new_record = new $this->association_options['class_name']($attributes);
		
		if (!$this->owner->is_new_record()) {
			$new_record->{$this->foreign_key()} = $this->owner->get_id();
			
			if ($this->is_polymorphic()) {
				$new_record->{$this->foreign_type()} = get_class($this->owner);
			}
		}
		
		return $new_record;
	}
	
	private function is_polymorphic()
	{
		return isset($this->association_options['as']);
	}
	
	private function quoted_foreign_type()
	{
		return ActiveRecord::$connection->quote_column_name($this->foreign_type());
	}
	
	private function foreign_type()
	{
		return $this->association_options['foreign_type'];
	}
	
	static function initialize_association_options($class_name, $plural_name, &$options)
	{
		/*
		 * Handle polymorphic associations
		 */
		if (isset($options['as'])) {
			if (!isset($options['foreign_key'])) {
				$options['foreign_key'] = $options['as'] . '_id';
			}
			
			if (!isset($options['foreign_type'])) {
				$options['foreign_type'] = $options['as'] . '_type';
			}
		}
		
		parent::initialize_association_options($class_name, $plural_name, $options);
	}
}