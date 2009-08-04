<?php

function stylesheet_link_tag()
{
	$buffer = '';
	foreach (func_get_args() as $stylesheet)  {
		if ($stylesheet[0] != '/') $stylesheet = '/stylesheets/' . $stylesheet;
		if (!preg_match('/\.css$/', $stylesheet)) $stylesheet .= '.css';
		$buffer .= '<link rel="stylesheet" type="text/css" media="screen" href="' . $stylesheet . '" />';
	}
	return $buffer;
}

function javascript_include_tag()
{
	$buffer = '';
	foreach (func_get_args() as $javascript) {
		if ($javascript[0] != '/') $javascript = '/javascripts/' . $javascript;
		if (!preg_match('/\.js$/', $javascript)) $javascript .= '.js';
		$buffer .= '<script type="text/javascript" src="' . $javascript . '"></script>';
	}
	return $buffer;
}
