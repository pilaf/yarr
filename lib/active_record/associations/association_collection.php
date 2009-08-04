<?php

abstract class AssociationCollection extends AssociationProxy implements Iterator
{
	protected $collection;
	protected $collection_ids;
	
	function __call($name, $arguments)
	{
		if (method_exists($this->class_name(), $name)) {
			return call_user_func_array(array($this->class_name(), $name), $arguments);
		}
	}
	
	function is_collection()
	{
		return true;
	}
	
	function get()
	{
		return $this;
	}
	
	function find($ids, $options = array())
	{
		$this->prepare_find_options($options);
		
		return call_user_func(array('ActiveRecord', 'find'), $this->class_name(), $ids, $options);
	}
	
	function find_all($options = array())
	{
		$this->prepare_find_options($options);
		
		$result = call_user_func(array('ActiveRecord', 'find_all'), $this->class_name(), $options);
		
		return $result;
	}
	
	function find_first($options = array())
	{
		$this->prepare_find_options($options);
		
		return call_user_func(array('ActiveRecord', 'find_first'), $this->class_name(), $options);
	}
	
	function find_last($options = array())
	{
		$this->prepare_find_options($options);
		
		return call_user_func(array('ActiveRecord', 'find_last'), $this->class_name(), $options);
	}
	
	function all()
	{
		$this->initialize_collection();
		
		return $this->collection;
	}
	
	function first()
	{
		$this->initialize_collection();
		
		return $this->collection[0];
	}
	
	function last()
	{
		$this->initialize_collection();
		
		return $this->collection[count($this->collection) - 1];
	}
	
	function includes($record_or_id)
	{
		if (is_object($record_or_id)) {
			$this->initialize_collection();
			foreach ($this->collection as &$record) {
				if ($record->is_same_as($record_or_id)) {
					return true;
				}
			}
			return false;
		} else {
			return in_array($record_or_id, $this->ids());
		}
	}
	
	function ids()
	{
		if (!isset($this->collection_ids)) {
			if (isset($this->collection)) {
				$this->initialize_collection_ids_from_collection();
			} else if (!$this->owner->is_new_record()) {
				$this->initialize_collection_ids_from_db();
			} else {
				$this->collection_ids = array();
			}
		}
		
		return $this->collection_ids;
	}
	
	function set_ids($ids)
	{
		$records = ActiveRecord::find($this->class_name(), $ids);
		
		if ($this->owner->is_new_record()) {
			$this->collection = $records;
			$this->initialize_collection_ids_from_collection();
		} else {
			$diff;
			$this->calculate_collections_diff($records, $diff);
			$this->add_and_remove_records($diff['new'], $diff['removed']);
		}
	}
	
	function count($options = array())
	{
		if (isset($this->collection)) {
			
			return count($this->collection);
			
		} else if (isset($this->collection_ids)) {
			
			return count($this->collection_ids);
			
		} else {
			
			$this->prepare_find_options($options);
			return call_user_func(array('ActiveRecord', 'count'), $this->class_name(), $options);
			
		}
	}
	
	abstract function build($attributes = array());
	
	function create($attributes = array())
	{
		$new_record = $this->build($attributes);
		$new_record->save();
		return $new_record;
	}
	
	function push($records)
	{
	}
	
	function delete_all()
	{
	}
	
	function destroy_all()
	{
	}
	
	/*
	 * Begin iterator implementation
	 */
	function rewind()
	{
		$this->initialize_collection();
		reset($this->collection);
	}
	
	function current()
	{
		return current($this->collection);
	}
	
	function key()
	{
		return key($this->collection);
	}
	
	function next()
	{
		return next($this->collection);
	}
	
	function valid()
	{
		return current($this->collection);
	}
	/*
	 * End iterator implementation
	 */
	
	protected function add_records(&$new_records)
	{
	}
	
	protected function remove_records(&$removed_records)
	{
	}
	
	private function add_and_remove_records(&$add, &$remove)
	{
		if (empty($add) && empty($remove)) {
			return;
		}
		
		ActiveRecord::$connection->begin_db_transaction();
		$this->add_records($add);
		$this->remove_records($remove);
		ActiveRecord::$connection->commit_db_transaction();
	}
	
	private function calculate_collections_diff(&$records, &$diff)
	{
		$this->initialize_collection();
		
		$diff = array('new' => array(), 'removed' => array());
		
		foreach ($records as &$record) {
			foreach ($this->collection as &$record_in_collection) {
				if ($record->is_same_as($record_in_collection)) {
					continue 2;
				}
			}
			
			$diff['new'][] = $record;
		}
		
		foreach ($this->collection as &$record_in_collection) {
			foreach ($records as &$record) {
				if ($record->is_same_as($record_in_collection)) {
					continue 2;
				}
			}
			
			$diff['removed'][] = $record_in_collection;
		}
	}
	
	private function initialize_collection_ids_from_collection()
	{
		$this->collection_ids = array();
		
		foreach ($this->collection as &$record) {
			$this->collection_ids[] = $record->get_id();
		}
	}
	
	private function initialize_collection_ids_from_db()
	{
		$options;
		
		$this->prepare_find_options($options);
		
		$quoted_primary_key = ActiveRecord::$connection->quote_column_name(call_user_func(array($this->class_name(), 'primary_key')));
		
		$sql = 'SELECT ' . $this->quoted_table_name() . '.' . $quoted_primary_key . ' FROM ' . $this->quoted_table_name() . ' ' . $options['joins'] . ' WHERE ' . $options['conditions'];
		
		$this->collection_ids = ActiveRecord::$connection->select_values($sql);
	}
	
	private function initialize_collection()
	{
		if (!isset($this->collection)) {
			if ($this->owner->is_new_record()) {
				$this->collection = array();
			} else {
				$this->collection = $this->find_all();
			}
		}
	}
	
	protected function prepare_find_options(&$options)
	{
		$this->prepare_conditions($options);
		$this->prepare_order($options);
	}
	
	protected function prepare_conditions(&$options)
	{
		$conditions = '';
		
		if (isset($options['conditions'])) {
			$conditions .= '(' . SqlBuilder::sanitize_sql_for_conditions($options['conditions']) . ')';
		}
		
		if (isset($this->association_options['conditions'])) {
			if (!empty($conditions)) {
				$conditions .= ' AND ';
			}
			
			$conditions .= '(' . SqlBuilder::sanitize_sql_for_conditions($this->association_options['conditions']) . ')';
		}
		
		$options['conditions'] = $conditions;
	}
	
	protected function prepare_order(&$options)
	{
		if (isset($options['order']) && isset($this->association_options['order'])) {
			
			$options['order'] .= ', ' . $this->association_options['order'];
			
		} else if (isset($this->association_options['order'])) {
			
			$options['order'] = $this->association_options['order'];
			
		}
	}
	
	protected function quoted_table_name()
	{
		return ActiveRecord::quoted_table_name($this->class_name());
	}
	
	protected function quoted_foreign_key()
	{
		return ActiveRecord::$connection->quote_column_name($this->foreign_key());
	}
}