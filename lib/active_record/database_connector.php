<?php

require_once ACTIVE_RECORD_BASE_PATH . '/connection_adapters/database_adapter.php';
require_once ACTIVE_RECORD_BASE_PATH . '/connection_adapters/database_column.php';

abstract class DatabaseConnector
{
	public static $connection;
	
	/*
	 * $options must be an assoc array containing an 'adapter' key (e.g. 'mysqli') and
	 * the options to pass to that adapter's connect() method.
	 *
	 * If the specified adapter can't be found an exception will be thrown.
	 */
	static function establish_connection($options)
	{
		if ($options['adapter']) {
			$options['adapter'] = strtolower($options['adapter']);
			
			$adapter_file_name = ACTIVE_RECORD_BASE_PATH . "/connection_adapters/adapters/$options[adapter]_adapter.php";
			
			if (file_exists($adapter_file_name)) {
				require_once($adapter_file_name);
			} else {
				throw new Exception("Couldn't find an appropiate adapter for the database connection.");
			}
			
			// Look for a class named <Adapter>Adapter (i.e. MysqliAdapter)
			$adapter_class_name = ucfirst($options['adapter']) . 'Adapter';
			
			if (!class_exists($adapter_class_name)) {
				throw new Exception("Expected $adapter_file_name to define class $adapter_class_name.");
			}
			
			if (self::$connection = new $adapter_class_name($options)) {
				return true;
			}
		}
	}
}