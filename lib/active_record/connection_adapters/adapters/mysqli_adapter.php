<?php

class MysqliException extends Exception {}

class MysqliAdapter extends DatabaseAdapter
{
	const DATETIME_FORMATTING = '"Y-m-d H:i:s"';
	
	private $link;
	
	function __construct($options)
	{
		$this->link = new mysqli(
			$options['host'],
			$options['username'],
			$options['password'],
			$options['database'],
			@$options['port'],
			@$options['socket']
		);
	}
	
	/*
	 * Run an SQL query and return the resulting MySQL resource id
	 */
	function execute($sql, $flags = MYSQLI_USE_RESULT)
	{
		if (class_exists('Logger')) {
			Logger::info('Execute SQL: ' . $sql);
		}
		
		if ($result = $this->link->query($sql, $flags)) {
			return $result;
		} else {
			throw new MysqliException("MySQL error: {$this->link->error}");
		}
	}
	
	/*
	 * Run a SELECT and return all rows in assoc arrays
	 */
	function select($sql)
	{
		if ($result = $this->execute($sql)) {
			$rows = array();
			while ($rows[] = $result->fetch_assoc());
			array_pop($rows);
			$result->free();
			return $rows;
		} else {
			return false;
		}
	}
	
	/*
	 * Run a SELECT and return only the first row in an assoc array
	 */
	function select_one($sql)
	{
		if ($result = $this->execute($sql)) {
			$row = $result->fetch_assoc();
			$result->free();
			return $row;
		} else {
			return false;
		}
	}
	
	function select_rows($sql)
	{
		if ($result = $this->execute($sql)) {
			$rows = array();
			while ($rows[] = $result->fetch_row());
			$result->free();
			array_pop($rows);
			return $rows;
		} else {
			return false;
		}
	}
	
	function select_value($sql)
	{
		if ($result = $this->execute($sql)) {
			$value = $result->fetch_row();
			$value = $value[0];
			$result->free();
			return $value;
		} else {
			return false;
		}
	}
	
	function select_values($sql)
	{
		if ($result = $this->execute($sql)) {
			$rows = array();
			while ($row = $result->fetch_row()) $rows[] = $row[0];
			$result->free();
			return $rows;
		} else {
			return false;
		}
	}
	
	function insert($insert_sql)
	{
		if ($this->execute($insert_sql)) {
			return $this->link->insert_id;
		} else {
			return false;
		}
	}
	
	function begin_db_transaction()
	{
		$this->execute('BEGIN');
	}
	
	function commit_db_transaction()
	{
		$this->execute('COMMIT');
	}
	
	function add_column($table, $name, $type, $options = null)
	{
		return $this->execute("ALTER TABLE `$table` ADD " . $this->column_definition($name, $type, $options));
	}
	
	function create_table($name, $columns, $options = null)
	{
		$column_definitions = array();
		
		if (!isset($options['id']) || $options['id'] !== false) $column_definitions[] = '`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
		
		foreach ($columns as $column_name => $column_data) {
			$column_definitions[] = is_array($column_data) ? $this->column_definition($column_name, array_shift($column_data), $column_data) : $this->column_definition($column_name, $column_data);
		}
		
		return $this->execute("CREATE TABLE `$name` (" . implode(', ', $column_definitions) . (isset($options['options']) ? " $options[options]" : '') . ')');
	}
	
	function rename_table($old_name, $new_name)
	{
		return $this->execute("RENAME TABLE `$old_name` TO `$new_name`");
	}
	
	function rename_column($table_name, $column_name, $new_column_name)
	{
		throw new Exception('rename_column not yet implemented for MysqliAdapter.');
	}
	
	function column_definition($name, $type, $options = null)
	{
		$type = strtolower($type);
		
		switch ($type) {
			case 'string':
				$limit = isset($options['limit']) ? $options['limit'] : 255;
				$sql_type = "VARCHAR($limit)";
				break;
			case 'text':
				$sql_type = 'TEXT';
				if (isset($options['limit'])) $sql_type .= "($options[limit])";
				break;
			case 'binary':
				$sql_type = 'BLOB';
				if (isset($options['limit'])) $sql_type .= "($options[limit])";
				break;
			case 'boolean':
				$sql_type = 'INTEGER';
				break;
			case 'integer':
			case 'float':
			case 'decimal':
			case 'date':
			case 'datetime':
			case 'time':
			case 'timestamp':
				$sql_type = strtoupper($type);
				break;
			default:
				$sql_type = $type;
		}
		
		if ($type == 'string' || $type == 'text' || $type == 'binary') {
			$default = isset($options['default']) ? $this->quote_string($options['default']) : null;
		} else $default = $options['default'];
		
		$column = "$name $sql_type";
		
		if ($default !== null) $column .= ' DEFAULT ' . $default;
		
		if (isset($options['null']) && $options['null'] === false) $column .= ' NOT NULL';
		
		return $column;
	}
	
	private function modify($sql)
	{
		if ($this->execute($sql)) {
			//return mysql_affected_rows($this->link);
			return $this->link->affected_rows;
		} else {
			return false;
		}
	}
	
	/*
	 *
	 */
	function quote($value, $column = null)
	{
		if (is_string($value)) {
			
			if ($column && ($column->is_number())) {
				return (string)($column->type == 'integer' ? (int)$value : (float)$value);
			} else {
				return self::quote_string($value);
			}
			
		} else if ($value === null) {
			
			return "NULL";
			
		} else if (is_bool($value)) {
			/*if ($column && $column->type == 'integer') {
			}*/
			return (string)(int)$value;
			
		} else if (is_numeric($value)) {
			
			if (isset($column) && $column->is_date()) {
				return self::quote_date($value);
			} else {
				return (string)$value;
			}
			
		}
	}
	
	function quote_date($value)
	{
		return date(self::DATETIME_FORMATTING, $value);
	}
	
	function quote_string($value)
	{
		return "'" . $this->link->real_escape_string($value) . "'";
	}
	
	function quote_column_name($column_name)
	{
		return '`' . $column_name . '`';
	}
	
	function quote_table_name($table_name)
	{
		return $this->quote_column_name($table_name);
	}
	
	function add_limit_offset(&$sql, $limit = null, $offset = null)
	{
		if (isset($limit)) {
			if (isset($offset)) {
				$sql .= ' LIMIT ' . (int)$offset . ', ' . (int)$limit;
			} else {
				$sql .= ' LIMIT ' . (int)$limit;
			}
		}
	}
	
	protected function &fetch_columns($table_name)
	{
		$raw_columns = $this->select("SHOW FIELDS FROM `$table_name`");
		
		$columns = array();
		
		foreach ($raw_columns as $column) {
			$new_column = new MysqliColumn($column);
			$columns[$new_column->name] = $new_column;
		}
		
		return $columns;
	}
}

class MysqliColumn extends DatabaseColumn
{
	function __construct($column)
	{
		parent::__construct($column['Field'], $column['Default'], $column['Type'], $column['Null']);
	}
	
	protected function simplified_type($sql_type)
	{
		if (strtolower($sql_type) == 'tinyint(1)') {
			return 'boolean';
		}
		
		if (preg_match('/enum/i', $sql_type)) {
			return string;
		}
		
		return parent::simplified_type($sql_type);
	}
}
