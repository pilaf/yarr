<?php

require dirname(__FILE__) . '/../framework.php';

require YARR_CONFIG_PATH . '/config.php';
require YARR_CONFIG_PATH . '/database.php';

// TODO: clean this up!
YARR::require_lib('helpers/assets');
YARR::require_lib('helpers/transition');

YARR::boot($CONFIG);