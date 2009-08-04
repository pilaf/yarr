<?php

/*
 * Runs migrations
 *
 * Requires an existing connection to a database.
 *
 * Usage:
 *
 * $migrations_runner = new MigrationsRunner('/path/to/migrations');
 * $migrations_runner->migrate(<migration_version>);
 *
 */
class MigrationsRunner
{
	private $schema_version;
	private $migrations_dir;
	
	function __construct($migrations_dir)
	{
		$this->schema_version = $this->get_schema_version();
		$this->migrations_dir = $migrations_dir;
	}
	
	/*
	 * Allow calling database adapter methods as own
	 */
	public function __call($name, $arguments)
	{
		if ($name != 'execute') {
			echo "Running $name with arguments ";
			print_r($arguments);
		}
		return call_user_func_array(array(ActiveRecord::$connection, $name), $arguments);
	}
	
	public function migrate($version = -1)
	{
		$start = $this->schema_version;
		$finish = $version;
		
		/*
		 * version == -1 means run all migrations starting at the bottom
		 */
		if ($version == -1 || $version > $this->schema_version) {
			$start++;
			$step = 1;
			$method = 'up';
		} else {
			$step = -1;
			$method = 'down';
		}
		
		echo "Going $method... starting at version $start.\n";
		
		$migrations = array();
		
		if ($this->migrations_dir != '' && substr($this->migrations_dir, -1) != '/') $this->migrations_dir .= '/';
		
		for ($i = $start; $i != $finish; $i += $step) {
			$migration_files = glob(sprintf($this->migrations_dir . '%03d_*.php', $i));
			
			if (count($migration_files) > 1) throw new Exception("Multiple migrations have version number $i.");
			
			if (count($migration_files) == 0) {
				if ($step == 1) break; else throw new Exception("Migration $i not found.");
			}
			
			$file = basename($migration_files[0]);
			
			if (preg_match('/^\d{3}_([a-z_]+)\.php$/', $file, $match)) {
				if (in_array($migration_files[0], $migrations)) throw new Exception("Multiple migrations have share the name $migration_files[0].");
				
				require $migration_files[0];
				
				$migrations[] = $match[1];
			} else throw new Exception("Invalid migration file name, '$file'.");
		}
		
		if (count($migrations) == 0) {
			echo "Already at latest version. Nothing to do, bye!\n";
			return;
		}
		
		foreach ($migrations as $m) {
			$class_name = ucfirst(Inflector::camelize($m));
			
			if (class_exists($class_name)) {
				
				$this->run_migration($class_name, $method);
				$this->schema_version += $step;
				
			} else throw new Exception("Migration class doesn't exist: '$class'.\n");
		}
		
		$this->update_schema_version();
		
		echo "All done. Schema is at version {$this->schema_version}.\n";
	}
	
	private function get_schema_version()
	{
		// This is MySQL-specific for the time being
		$this->execute('CREATE TABLE IF NOT EXISTS `schema_info` (`version` INTEGER NOT NULL)');
		
		if ($schema_version = $this->select_value('SELECT `version` FROM `schema_info` LIMIT 1')) {
			return (int)$schema_version;
		}
		
		return 0;
	}
	
	private function update_schema_version()
	{
		$this->execute('BEGIN');
		$this->execute('TRUNCATE `schema_info`');
		$this->execute("INSERT INTO `schema_info` VALUES ({$this->schema_version})");
		$this->execute('COMMIT');
	}
	
	private function run_migration($class_name, $method)
	{
		echo "\n================================================================================\n";
		echo "Running $class_name $method:\n\n";
		
		call_user_func(array($class_name, $method), $this);
		
		echo "\n================================================================================\n\n";
	}
}