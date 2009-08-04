<?php

class HasAndBelongsToManyAssociation extends AssociationCollection
{
	function build($attributes = array())
	{
	}
	
	protected function prepare_find_options(&$options)
	{
		parent::prepare_find_options($options);
		
		$this->prepare_joins($options);
	}
	
	protected function prepare_conditions(&$options)
	{
		parent::prepare_conditions($options);
		
		if (!empty($options['conditions'])) {
			$options['conditions'] .= ' AND ';
		} else {
			$options['conditions'] = '';
		}
		
		$options['conditions'] .= $this->quoted_join_table() . '.' . $this->quoted_foreign_key() . ' = ' . $this->owner->get_quoted_id();
	}
	
	protected function prepare_joins(&$options)
	{
		$options['joins'] = 'INNER JOIN ' . $this->quoted_join_table() . ' ON ' . $this->quoted_table_name() . '.' . $this->quoted_reflection_primary_key() . ' = ' . $this->quoted_join_table() . '.' . $this->quoted_association_foreign_key();
	}
	
	protected function add_records(&$new_records)
	{
		if (empty($new_records)) {
			return true;
		}
		
		$sql = 'INSERT INTO ' . $this->quoted_join_table() . ' (' . $this->quoted_foreign_key() . ', ' . $this->quoted_association_foreign_key() . ') VALUES ';
		
		$values = array();
		
		foreach ($new_records as &$record) {
			$values[] = '(' . $this->owner->get_quoted_id() . ', ' . $record->get_quoted_id() . ')';
		}
		
		$sql .= join(', ', $values);
		
		return ActiveRecord::$connection->insert($sql);
	}
	
	protected function remove_records(&$removed_records)
	{
		if (empty($removed_records)) {
			return true;
		}
		
		$sql = 'DELETE FROM ' . $this->quoted_join_table() . ' WHERE ' . $this->quoted_foreign_key() . ' = ' . $this->owner->get_quoted_id() . ' AND ' . $this->quoted_association_foreign_key();
		
		if (count($removed_records) == 1) {
			$sql .= ' = ' . $removed_records[0]->get_quoted_id();
		} else {
			$sql .= ' IN (';
			
			$values = array();
			
			foreach ($removed_records as &$record) {
				$values[] = $record->get_quoted_id();
			}
			
			$sql .= join(',', $values) . ')';
		}
		
		return ActiveRecord::$connection->delete($sql);
	}
	
	private function quoted_reflection_primary_key()
	{
		return ActiveRecord::$connection->quote_column_name(call_user_func(array($this->class_name(), 'primary_key')));
	}
	
	private function quoted_join_table()
	{
		return ActiveRecord::$connection->quote_table_name($this->association_options['join_table']);
	}
	
	private function quoted_association_foreign_key()
	{
		return ActiveRecord::$connection->quote_column_name($this->association_options['association_foreign_key']);
	}
	
	static function initialize_association_options($class_name, $plural_name, &$options)
	{
		parent::initialize_association_options($class_name, $plural_name, $options);
		
		if (!isset($options['join_table'])) {
			$tableized_class = Inflector::tableize($class_name);
			$tableized_foreign_class = Inflector::tableize($options['class_name']);
			$options['join_table'] = ($tableized_class < $tableized_foreign_class) ? $tableized_class . '_' . $tableized_foreign_class : $tableized_foreign_class . '_' . $tableized_class;
		}
		
		if (!isset($options['association_foreign_key'])) {
			$options['association_foreign_key'] = Inflector::uncamelize($options['class_name']) . '_id';
		}
	}
}