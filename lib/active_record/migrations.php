<?php

if (class_exists('YARR')) {
	YARR::require_lib('inflector');
} else {
	require_once 'inflector.php';
}

require dirname(__FILE__) . '/migrations/migrations_runner.php';
require dirname(__FILE__) . '/migrations/migration.php';

/*
require_once 'mysql.php';

define('string', 'string');
define('text', 'text');
define('binary', 'binary');
define('integer', 'integer');
define('boolean', 'boolean');
define('float', 'float');
define('decimal', 'decimal');
define('date', 'date');
define('datetime', 'datetime');
define('time', 'time');
define('timestamp', 'timestamp');
*/