<?php

function options_for_select_array($array, $selected = null)
{
	$html = '';
	
	foreach ($array as $v) {
		$html .= '<option value="' . $v . '"';
		if ($v === $selected) $html .= ' selected="selected"';
		$html .= '>' . $v . '</option>';
	}
	
	return $html;
}

function options_for_select_assoc($assoc_array, $selected = null)
{
	$html = '';
	
	foreach ($assoc_array as $k => $v) {
		$html .= '<option value="' . $v . '"';
		if ($v === $selected) $html .= ' selected="selected"';
		$html .= '>' . $k . '</option>';
	}
	
	return $html;
}

function options_from_collection_for_select($collection, $value_method, $text_method, $selected = null)
{
	$options = array();
	
	foreach ($collection as $item) {
		$options[$item->$text_method] = $item->$value_method;
	}
	
	return options_for_select_assoc($options, is_object($selected) ? $selected->$value_method : $selected);
}

function options_for_select($container, $selected = null)
{
	if (isset($container[0])) {
		return options_for_select_array($container, $selected);
	} else {
		return options_for_select_assoc($container, $selected);
	}
}

function date_select($name, $value = null, $options = array())
{
	$months = isset($options['months']) ? $options['months'] : array(
		'January'    => 1,
		'February'   => 2,
		'March'      => 3,
		'April'      => 4,
		'May'        => 5,
		'June'       => 6,
		'July'       => 7,
		'August'     => 8,
		'September'  => 9,
		'October'    => 10,
		'November'   => 11,
		'December'   => 12
	);
	
	if (!isset($options['start_year'])) {
		if (isset($value)) {
			$start_year = (int)date('Y', $value) - 10;
		} else {
			$start_year = (int)date('Y') - 10;
		}
	} else {
		$start_year = $options['start_year'];
	}
	
	$end_year = isset($options['end_year']) ? $options['end_year'] : (int)date('Y');
	
	$html = '<select name="' . $name . '[m]">';
	$html .= options_for_select($months, (int)(isset($value) ? date('m', $value) : date('m')));
	$html .= '</select> ';
	
	$html .= '<select name="' . $name . '[d]">';
	$html .= options_for_select(range(1, 31), (int)(isset($value) ? date('d', $value) : date('d')));
	$html .= '</select> ';
	
	$html .= '<select name="' . $name . '[y]">';
	$html .= options_for_select(range($end_year, $start_year), (int)(isset($value) ? date('Y', $value) : date('Y')));
	$html .= '</select>';
	
	return $html;
}