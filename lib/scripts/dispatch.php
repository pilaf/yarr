<?php

require dirname(__FILE__) . '/boot.php';

ActionController::dispatch($_SERVER['REQUEST_URI']);