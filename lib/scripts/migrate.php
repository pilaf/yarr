<?php

require dirname(__FILE__) . '/boot.php';

YARR::require_lib('active_record/migrations');

$runner = new MigrationsRunner(YARR_DB_PATH . '/migrate');

if (isset($argv) && isset($argv[1]) && is_numeric($argv[1])) {
	$runner->migrate((int)$argv[1]);
} else {
	$runner->migrate();
}
