<?php

require_once 'basics.php';

function init_mysql()
{
	static $connected;
	if (!$connected) {
		global $CONFIG_DB_HOST, $CONFIG_DB_USER, $CONFIG_DB_PASS, $CONFIG_DB_NAME;
		mysql_connect($CONFIG_DB_HOST, $CONFIG_DB_USER, $CONFIG_DB_PASS) or die(mysql_error());
		mysql_select_db($CONFIG_DB_NAME) or die(mysql_error());
		$connected = true;
	}
}

function mysql_to_php($type)
{
	switch ($type) {
		case 'int':
		case 'tinyint':
		case 'smallint':
		case 'mediumint':
		case 'integer':
		case 'bigint':
		case 'timestamp':
			return 'int';
		case 'float':
		case 'double':
		case 'decimal':
		case 'numeric':
			return 'real';
		case 'date':
		case 'time':
		case 'datetime':
			return 'date';
		default:
			return 'string';
	}
}

function q($query)
{
	debug($query);
	init_mysql();
	$r = mysql_query($query);
	if (!$r) debug(mysql_error());
	return $r;
}

function &get_schema($table)
{
	static $schemas;
	isset($schemas) || $schemas = array();

	if (!isset($schemas[$table])) {
		$schemas[$table] = array();

		//echo "[[$table]]";

		$r = q("DESCRIBE $table");

		while ($row = mysql_fetch_assoc($r)) {

			$field = $row['Field'];

			$schemas[$table][$field] = new stdclass;

			preg_match('/^(\w+)(?:\((\d+)\))?$/', $row['Type'], $type);

			$schemas[$table][$field]->type = $type[1];

			isset($type[2]) && $schemas[$table][$field]->length = (int)$type[2];
		}
	}

	return $schemas[$table];
}
