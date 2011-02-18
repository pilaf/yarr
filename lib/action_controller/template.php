<?php

class TemplateMissingException extends Exception {};
class TemplateException extends Exception {};

class Template
{
	private $options;
	private $controller;
	private $params;
	private $properties;
	private $flash;
	
	function __construct($controller, $options, &$properties)
	{
		$this->controller = $controller;
		$this->options = $options;
		$this->properties = &$properties;
		
		$this->params = &$controller->params;
		$this->flash = &$controller->flash;
	}
	
	function __get($name)
	{
		return $this->properties[$name];
	}
	
	function __isset($name)
	{
		return isset($this->properties[$name]);
	}
	
	function url_for($params, $options = array())
	{
		return $this->controller->url_for($params, $options);
	}
	
	function render($options)
	{
		$template = new Template($this->controller, $options, $this->properties);
		return $template->process();
	}
	
	function process()
	{
		if (is_array($this->options)) {
			if (isset($this->options['partial'])) {
				
				return $this->render_partial();
				
			} else if (isset($this->options['inline'])) {
				
				return eval('?>' . $this->options['inline']);
				
			} else if (isset($this->options['text'])) {
				
				return $this->options['text'];
				
			} else {
				
				return $this->render_with_layout();
				
			}
		} else {
			
			return $this->render_partial(array('partial' => $this->options/*, 'locals' => */));
			
		}
	}
	
	private function render_partial($__options = null)
	{
		if (!isset($__options)) {
			$__options = &$this->options;
		}
		
		list($__partial_name, $__partial_path) = $this->partial_name_and_path($__options['partial']);
		
		if (!file_exists($__partial_path)) {
			throw new TemplateMissingException($__partial_path);
		}
		
		if (isset($__options['locals'])) {
			extract($__options['locals'], EXTR_SKIP | EXTR_REFS);
		}
		
		ob_start();
		
		if (isset($__options['collection'])) {
			$__partial_raw_template = file_get_contents($__partial_path);
			eval('foreach($__options[\'collection\'] as $' . $__partial_name . '){?>' . $__partial_raw_template . '<?php }');
		} else {
			if (isset($__options['object'])) {
				$$__partial_name = &$__options['object'];
			}
			require $__partial_path;
		}
		
		return ob_get_clean();
	}
	
	private function render_with_layout()
	{
		$template_filename = $this->get_template_filename();
		
		$content = $this->render_file($template_filename);
		
		$layout_filename = $this->get_layout_filename();
		
		ob_start();
		
		if ($layout_filename && file_exists($layout_filename)) {
			require $layout_filename;
		} else {
			echo $content;
		}
		
		return ob_get_clean();
	}
	
	private function render_file($filename)
	{
		ob_start();
		
		if (file_exists($filename)) {
			require $filename;
		} else {
			ob_end_clean();
			throw new TemplateMissingException($filename);
		}
		
		return ob_get_clean();
	}
	
	private function partial_name_and_path($partial)
	{
		$partial_segments = explode('/', $partial);
		
		$partial_name = array_pop($partial_segments);
		
		if (count($partial_segments)) {
			$partial_path = $this->template_path(join('/', $partial_segments), '_' . $partial_name);
		} else {
			$partial_path = $this->template_path($this->params['controller'], '_' . $partial_name);
		}
		
		return array($partial_name, $partial_path);
	}
	
	private function layout_path($layout)
	{
		return YARR_APP_PATH . '/layouts/' . $layout . '.html.php';
	}
	
	private function template_path($controller, $action)
	{
		return YARR_APP_PATH . '/views/' . $controller . '/' . $action . '.html.php';
	}
	
	private function get_layout_filename()
	{
		if (isset($this->options['layout'])) {
			if (is_bool($this->options['layout'])) {
				return $this->options['layout'] ? $this->layout_path($this->controller->layout()) : false;
			} else {
				return $this->layout_path($this->options['layout']);
			}
		} else {
			return $this->layout_path($this->controller->layout());
		}
	}
	
	private function get_template_filename()
	{
		if (isset($this->options['action'])) {
			return $this->template_path($this->params['controller'], $this->options['action']);
		} else {
			return $this->template_path($this->params['controller'], $this->params['action']);
		}
	}
}
