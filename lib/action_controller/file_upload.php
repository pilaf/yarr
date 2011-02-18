<?php

class FileUpload
{
	private $name;
	private $type;
	private $tmp_name;
	private $error;
	private $size;
	private $mime_type;
	
	function __construct($args)
	{
		$this->name = $args['name'];
		$this->type = $args['type'];
		$this->tmp_name = $args['tmp_name'];
		$this->error = $args['error'];
		$this->size = $args['size'];
	}
	
	function get_name()
	{
		return $this->name;
	}
	
	function get_type()
	{
		if (!isset($this->mime_type)) {
			// Try various techniques to obtain MIME type
			if (function_exists('mime_content_type')) {
				$this->mime_type = mime_content_type($this->tmp_name);
			} else if (function_exists('finfo_open')) {
				if ($finfo = finfo_open(FILEINFO_MIME)) {
					$mime = finfo_info($finfo, $this->tmp_name);
					finfo_close($finfo);
					$this->mime_type = $mime;
				}
			} else if ($file_cmd_output = exec('file -bi ' . escapeshellarg($this->tmp_name))) {
				$this->mime_type = trim($file_cmd_output);
			} else {
				$this->mime_type = $this->type;
			}
		}
		
		return $this->mime_type;
	}
	
	function get_size()
	{
		return (int)$this->size;
	}
	
	function get_error_message()
	{
		switch ($this->error) {
			case UPLOAD_ERR_OK:         return '';
			case UPLOAD_ERR_INI_SIZE:   return 'The uploaded file exceeds the maximum allowed.';
			case UPLOAD_ERR_FORM_SIZE:  return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form (and PHP is stupid).';
			case UPLOAD_ERR_PARTIAL:    return 'The uploaded file was only partially uploaded.';
			case UPLOAD_ERR_NO_FILE:    return 'No file was uploaded.';
			case UPLOAD_ERR_NO_TMP_DIR: return 'Missing a temporary folder.';
			case UPLOAD_ERR_CANT_WRITE: return 'Failed to write file to disk.';
			case 8 /*UPLOAD_ERR_EXTENSION (PHP 5.2)*/: return 'File upload stopped by extension.';
		}
	}
	
	function is_valid()
	{
		return $this->error == UPLOAD_ERR_OK;
	}
	
	function is_image($accepted_types = 'jpeg|gif|png')
	{
		return $this->is_valid() && preg_match('/\Aimage\/(?:' . $accepted_types . ')\Z/', $this->get_type());
	}
	
	function move($dest)
	{
		return move_uploaded_file($this->tmp_name, $dest);
	}
	
	static function get_upload_max_size()
	{
		return self::shorthand_to_i(ini_get('upload_max_filesize'));
	}
	
	private static function shorthand_to_i($shorthand_value)
	{
		$shorthand_value = trim($shorthand_value);
		
		switch(strtolower($shorthand_value[strlen($shorthand_value) - 1])) {
			case 'g':
				$shorthand_value *= 1024;
			case 'm':
				$shorthand_value *= 1024;
			case 'k':
				$shorthand_value *= 1024;
		}
		
		return $shorthand_value;
	}
}
