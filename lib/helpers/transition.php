<?php

/*
 * This file contains all depracated functions that should be replaced
 */

function set_content_for($section)
{
	global $CONTENT_SECTIONS;
	if (!is_array($CONTENT_SECTIONS)) {
		$CONTENT_SECTIONS = array($section => ob_get_clean());
	} else {
		$CONTENT_SECTIONS[$section] = ob_get_clean();
	}
}

function content_for($section)
{
	return @$GLOBALS['CONTENT_SECTIONS'][$section];
}
