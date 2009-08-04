<?php

class Logger
{
	private static $file_handle;
	private static $muted = false;
	
	public static function mute()
	{
		self::$muted = true;
	}
	
	public static function unmute()
	{
		self::$muted = false;
	}
	
	public static function open($log_file)
	{
		if ($file_handle) self::close();
		
		self::$file_handle = fopen($log_file, 'a');
	}
	
	public static function close()
	{
		fclose(self::$file_handle);
	}
	
	public static function info($message)
	{
		self::log('[INFO] ' . $message);
	}
	
	private static function log($message)
	{
		if (self::$muted) return;
		
		if (self::$file_handle) {
			fputs(self::$file_handle, $message . "\n");
		} else {
			echo $message . "\n";
		}
	}
}