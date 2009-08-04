<?php

require dirname(__FILE__) . '/boot.php';

if (file_exists(YARR_DB_PATH . '/seed.php')) {
	echo "Running db/seed.php ...\n\n";
	
	require YARR_DB_PATH . '/seed.php';
	
	echo "\n\nDone.\n";
}