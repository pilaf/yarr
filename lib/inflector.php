<?php

class Inflector
{
	static private $pluralize_rules = array();
	static private $pluralize_cache = array();
	
	static private $singularize_rules = array();
	static private $singularize_cache = array();
	
	static private $humanize_rules = array();
	static private $humanize_cache = array();
	
	static private $camelize_cache = array();
	static private $uncamelize_cache = array();
	
	static function set_pluralize_rules($rules)
	{
		self::$pluralize_rules = $rules;
	}
	
	static function set_singularize_rules($rules)
	{
		self::$singularize_rules = $rules;
	}
	
	static function set_humanize_rules($rules)
	{
		self::$humanize_rules = $rules;
	}
	
	static function add_pluralize_rule($match, $replace, $top = true)
	{
		self::add_rules('pluralize_rules', $match, $replace, $top);
	}
	
	static function add_singularize_rule($match, $replace, $top = true)
	{
		self::add_rules('singularize_rules', $match, $replace, $top);
	}
	
	static function add_humanize_rule($match, $replace, $top = true)
	{
		self::add_rules('humanize_rules', $match, $replace, $top);
	}
	
	static function pluralize($word)
	{
		return self::apply_ruleset('pluralize', $word);
	}
	
	static function singularize($word)
	{
		return self::apply_ruleset('singularize', $word);
	}
	
	static function humanize($string, $from_camelcase = false)
	{
		if ($from_camelcase) $string = self::uncamelize($string);
		if (isset($humanize_cache[$word])) return $humanize_cache[$word];
		$words = explode('_', $string);
		$length = count($words);
		for ($i = 0; $i < $length; $i++) {
			$words[$i] = ucfirst(self::apply_ruleset('humanize', $words[$i], false));
		}
		return join($words, ' ');
	}
	
	static function camelize($string)
	{
		if (isset(self::$camelize_cache[$string])) return self::$camelize_cache[$string];
		$words = explode('_', $string);
		$r = '';
		foreach ($words as $s) $r .= ucfirst($s);
		return self::$camelize_cache[$string] = $r;
	}
	
	static function uncamelize($string)
	{
		if (isset(self::$uncamelize_cache[$string])) return self::$uncamelize_cache[$string];
		return self::$uncamelize_cache[$string] = strtolower(preg_replace('/(\w)([A-Z])/', '$1_$2', $string));
	}
	
	static function tableize($string)
	{
		return self::pluralize(self::uncamelize($string));
	}
	
	static function classify($string)
	{
		return self::camelize(self::singularize($string));
	}
	
	static function normalize($string)
	{
		return iconv('UTF-8', 'ASCII//TRANSLIT', $string);
	}
	
	static function parameterize($string)
	{
		return trim(preg_replace(array('/\s+/', '/[^\w-]/', ), array('-', ''), strtolower(self::normalize($string))), '-');
	}
	
	static private function add_rules($ruleset, $match, $replace, $top = true)
	{
		if ($top) {
			array_unshift(self::${"{$ruleset}_rules"}, array($match, $replace));
		} else {
			self::${"{$ruleset}_rules"}[] = array($match, $replace);
		}
	}
	
	static private function apply_ruleset($ruleset, $word, $use_cache = true)
	{
		$cache = &self::${"{$ruleset}_cache"};
		if ($use_cache && isset($cache[$word])) return $cache[$word];
		$rules = &self::${"{$ruleset}_rules"};
		$length = count($rules);
		for ($i = 0; $i < $length; $i++) {
			$inflected_word = preg_replace("/{$rules[$i][0]}$/", $rules[$i][1], $word, 1, $c);
			if ($c) return $use_cache ? ($cache[$word] = $inflected_word) : $inflected_word;
		}
		return $use_cache ? ($cache[$word] = $word) : $word;
	}
}

Inflector::set_pluralize_rules(array(
	array('sheep', 'sheep'),
	array('person', 'people'),
	array('news', 'news'),
	array('money', 'money'),
	array('mouse', 'mice'),
	array('leaf', 'leaves'),
	array('foot', 'feet'),
	array('tooth', 'teeth'),
	array('virus', 'viruses'),
	array('matrix', 'matrices'),
	array('man', 'men'),
	array('child', 'children'),
	array('fe', 'ves'),            // knife, wife
	array('(a|i|u|e|o)y', '$1ys'), // boy, toy, day
	array('y', 'ies'),             // category, inventory
	array('us', 'i'),              // cactus, nucleus
	array('(x|sh|ch|ss)', '$1es'), // box, dish, church, glass
	array('(\w+)', '$1s')
));

Inflector::set_singularize_rules(array(
	array('people', 'person'),
	array('news', 'news'),
	array('money', 'money'),
	array('mice', 'mouse'),
	array('leaves', 'leaf'),
	array('feet', 'foot'),
	array('teth', 'tooth'),
	array('viruses', 'virus'),
	array('matrices', 'matrix'),
	array('men', 'man'),
	array('children', 'child'),
	array('ves', 'fe'),
	array('ies', 'y'),
	array('i', 'us'),
	array('(x|sh|ch|ss)es', '$1'),
	array('(\w+)s', '$1')
));

Inflector::set_humanize_rules(array(
	array('email', 'E-mail'),
	array('url', 'URL'),
	array('id', 'ID')
));