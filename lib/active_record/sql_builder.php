<?php

require_once ACTIVE_RECORD_BASE_PATH . '/database_connector.php';

/*
 * TODO: Move all AR generic SQL building methods as public static methods here
 */
abstract class SqlBuilder extends DatabaseConnector
{
	static function comma_pair_list($array)
	{
		$pairs = array();
		foreach ($array as $key => $value) $pairs[] = "$key = $value";
		return join(', ', $pairs);
	}
	
	static function quoted_comma_pair_list($array)
	{
		return self::comma_pair_list(self::quote_columns($array));
	}
	
	static function quote_columns($array)
	{
		$quoted = array();
		foreach ($array as $key => $value) {
			$quoted[self::$connection->quote_column_name($key)] = $value;
		}
		return $quoted;
	}
	
	static function sanitize_sql_for_assignment($assignments)
	{
		if (is_array($assignments)) {
			if (isset($assignments[0])) {
				return self::sanitize_sql($assignments);
			} else {
				return self::quoted_comma_pair_list($assignments);
			}
		} else {
			return $assignments;
		}
	}
	
	/*
	 * Given an SQL order statement (e.g.'id DESC, created_at ASC'),
	 * reverse it (e.g. 'id ASC, created_at DESC')
	 */
	static function reverse_sql_order($order)
	{
		$order_rules = explode(',', $order);
		
		foreach ($order_rules as &$order_rule) {
			$order_rule = trim($order_rule);
			
			var_dump($order_rule);
			
			if (preg_match('/\sDESC$/i', $order_rule)) {
				$order_rule = preg_replace('/\sDESC$/i', ' ASC', $order_rule);
			} else if (preg_match('/\sASC$/i', $order_rule)) {
				$order_rule = preg_replace('/\sASC$/i', ' DESC', $order_rule);
			} else {
				$order_rule .= ' DESC';
			}
		}
		
		return join(', ', $order_rules);
	}
	
	/*
	 * Add a WHERE clause to an SQL statement given a conditions string, array or associative array
	 */
	static function add_conditions(&$sql, &$conditions)
	{
		if ($conditions) {
			$merged_conditions = self::sanitize_sql_for_conditions($conditions);
			
			if ($merged_conditions) {
				$sql .= ' WHERE ' . $merged_conditions;
			}
		}
	}
	
	static function add_group(&$sql, $group, $having)
	{
		if ($group) {
			$sql .= ' GROUP BY ' . $group;
			if ($having) $sql .= ' HAVING ' . self::sanitize_sql_for_conditions($having);
		}
	}
	
	static function add_order(&$sql, $order)
	{
		if ($order) {
			$sql .= ' ORDER BY ' . $order;
		}
	}
	
	static function add_limit(&$sql, $limit = null, $offset = null)
	{
		if ($limit) {
			self::$connection->add_limit_offset($sql, $limit, $offset);
		}
	}
	
	static function add_joins(&$sql, &$joins)
	{
		if ($joins) {
			if (is_array($joins)) {
				$sql .= ' ' . join(' ', $joins);
			} else {
				$sql .= ' ' . $joins;
			}
		}
	}
	
	static function add_sql_trail(&$sql, &$options)
	{
		self::add_joins($sql, $options['joins']);
		self::add_conditions($sql, $options['conditions']);
		self::add_group($sql, $options['group'], $options['having']);
		self::add_order($sql, $options['order']);
		self::add_limit($sql, $options['limit'], $options['offset']);
	}
	
	/*
	 * Given an array where the first argument is a string containing '?' characters,
	 * replace those characters with properly escaped values (arguments 1 ... n)
	 */
	static function sanitize_sql()
	{
		if (func_num_args() == 1) {
			return func_get_arg(0);
		}
		
		$sql = func_get_arg(0);
		$num_args = func_num_args();
		
		$sql_parts = explode('?', $sql, $num_args);
		$replaced_sql = '';
		$i = 0;
		
		while (true) {
			$replaced_sql .= array_shift($sql_parts);
			
			if (empty($sql_parts)) break;
			
			++$i;
			
			$value = func_get_arg($i);
			
			if (is_array($value)) {
				$replaced_sql .= join(', ', array_map(array(self::$connection, 'quote'), $value));
			} else {
				$replaced_sql .= self::$connection->quote($value);
			}
		}
		
		return $replaced_sql;
	}
	
	static function sanitize_sql_for_conditions($conditions)
	{
		if (is_array($conditions) && !empty($conditions)) {
			if (isset($conditions[0])) {
				return call_user_func_array(array(self, 'sanitize_sql'), $conditions);
			} else {
				return self::sanitize_sql_assoc($conditions);
			}
		} else { //if (is_string($conditions) && !empty($conditions)) {
			return $conditions;
		}
	}
	
	static function merge_sql_for_conditions()
	{
		$conditions = func_get_args();
		
		$sanitized_conditions = array_map(array('SqlBuilder', 'sanitize_sql_for_conditions'), $conditions);
		
		return join(' AND ', $sanitized_conditions);
	}
	
	protected static function attribute_condition($quoted_column_name, $value)
	{
		if ($value === null) {
			return $quoted_column_name . ' IS NULL';
		} else if (is_array($value)) {
			return SqlBuilder::sanitize_sql($quoted_column_name . ' IN (?)', $value);
		} else {
			return SqlBuilder::sanitize_sql($quoted_column_name . ' = ?', $value);
		}
	}
	
	protected static function sanitize_sql_assoc($assoc_array)
	{
		$conditions = array();
		
		foreach ($assoc_array as $key => $value) {
			$conditions[] = self::attribute_condition(self::$connection->quote_column_name($key), $value);
		}
		
		return join(' AND ', $conditions);
	}
}