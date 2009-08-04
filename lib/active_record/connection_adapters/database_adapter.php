<?php

/*
 * TODO: Convert this into an abstract class and implement common methods here
 */
abstract class DatabaseAdapter
{
	private static $columns_cache = array();
	
	abstract function __construct($options);
	
	abstract function execute($sql);
	
	abstract function select($sql);
	abstract function select_one($sql);
	abstract function select_rows($sql);
	abstract function select_value($sql);
	abstract function select_values($sql);
	
	abstract function insert($sql);
	
	abstract function rename_table($old_name, $new_name);
	abstract function rename_column($table_name, $column_name, $new_column_name);
	
	abstract function begin_db_transaction();
	abstract function commit_db_transaction();
	
	abstract function quote($value, $column = null);
	abstract function quote_string($value);
	abstract function quote_column_name($column_name);
	abstract function quote_table_name($table_name);
	
	abstract function add_limit_offset(&$sql, $limit = null, $offset = null);
	
	abstract function add_column($table, $name, $type, $options = null);
	
	abstract protected function &fetch_columns($table_name);
	
	function &columns($table_name, $clear_cache = false)
	{
		if (!$clear_cache && !isset(self::$columns_cache[$table_name])) {
			self::$columns_cache[$table_name] = $this->fetch_columns($table_name);
		}
		
		return self::$columns_cache[$table_name];
	}
	
	function update($update_sql)
	{
		return $this->execute($update_sql);
	}
	
	function delete($delete_sql)
	{
		return $this->execute($delete_sql);
	}
	
	function drop_table($name)
	{
		return $this->execute("DROP TABLE `$name`");
	}
	
	function remove_column($table, $column)
	{
		return $this->execute('ALTER TABLE ' . $this->quote_table_name($table) . ' DROP ' . $this->quote_column_name($column));
	}
	
	function add_index($table_name, $column_names, $options = array()) {
		if (!is_array($column_names)) {
			$column_names = array($column_names);
		}
		
		$index_name = $this->quote_column_name(isset($options['name']) ? $options['name'] : $this->index_name($table_name, $column_names));
		
		$index_type = isset($options['unique']) && $options['unique'] ? 'UNIQUE ' : '';
		
		$quoted_column_names = join(', ', array_map(array($this, 'quote_column_name'), $column_names));
		
		return $this->execute('CREATE ' . $index_type . 'INDEX ' . $index_name . ' ON ' . $this->quote_table_name($table_name) . ' (' . $quoted_column_names . ')');
	}
	
	function remove_index($table_name, $options)
	{
		if (is_array($options)) {
			if (isset($options['name'])) {
				$index_name = $options['name'];
			} else if (isset($options[0])) {
				$column_names = $options;
			} else if (isset($options['columns'])) {
				$column_names = is_array($options['columns']) ? $options['columns'] : array($options['columns']);
			} else {
				throw new Exception('Columns missing for remove_index.');
			}
		} else {
			$column_names = array($options);
		}
		
		if (!isset($index_name)) {
			$index_name = $this->index_name($table_name, $column_names);
		}
		
		$this->execute('DROP INDEX ' . $this->quote_column_name($index_name) . ' ON ' . $this->quote_table_name($table_name));
	}
	
	protected function index_name(&$table_name, &$column_names)
	{
		return 'index_' . $table_name . '_on_' . join('_and_', $column_names);
	}
}