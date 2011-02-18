<?php

define('ACTIVE_RECORD_BASE_PATH', dirname(__FILE__));

require_once ACTIVE_RECORD_BASE_PATH . '/active_record_exceptions.php';
require_once ACTIVE_RECORD_BASE_PATH . '/database_connector.php';
require_once ACTIVE_RECORD_BASE_PATH . '/sql_builder.php';
require_once ACTIVE_RECORD_BASE_PATH . '/class_static_proxy.php';

require_once ACTIVE_RECORD_BASE_PATH . '/active_record/01_active_record_base_public_instance_methods.php';

require_once ACTIVE_RECORD_BASE_PATH . '/associations/association_proxy.php';
require_once ACTIVE_RECORD_BASE_PATH . '/associations/association_collection.php';
require_once ACTIVE_RECORD_BASE_PATH . '/associations/belongs_to_association.php';
require_once ACTIVE_RECORD_BASE_PATH . '/associations/has_many_association.php';
require_once ACTIVE_RECORD_BASE_PATH . '/associations/has_and_belongs_to_many_association.php';

if (class_exists('YARR')) {
	YARR::add_default_config('active_record', array());
} else {
	require_once 'inflector.php';
}

abstract class ActiveRecord extends ActiveRecordBasePublicInstanceMethods
{
	private static $table_columns = array();
	private static $associations = array();
	private static $extensions = array();
	
	private $attributes;
	private $errors = array();
	private $dirty_attributes = array();
	private $association_proxies = array();
	
	private $frozen = false;
	private $read_only = false;
	private $new_record = true;
	
	function __construct($attributes = array())
	{
		$this->set_attributes($attributes);
	}
	
	function __set($name, $value)
	{
		if ('id' == $name) return $this->set_id($value);
		
		if (method_exists($this, "__set_$name")) {
			return $this->{"__set_$name"}($value);
		}
		
		if ($this->column_for_attribute($name)) {
			return $this->write_attribute($name, $value);
		}
		
		if ($this->has_association($name)) {
			return $this->association_proxy($name)->replace($value);
		}
		
		$match;
		
		if (preg_match('/(\w+)_ids$/', $name, $match) && ($this->has_association($association_name = Inflector::pluralize($match[1])))) {
			$association = $this->association_proxy($association_name);
			
			if ($association->is_collection()) {
				return $association->set_ids($value);
			} else {
				return null;
			}
		}
	}
	
	function __get($name)
	{
		if ('id' == $name) return $this->get_id();
		
		if (method_exists($this, "__get_$name")) {
			return $this->{"__get_$name"}();
		}
		
		if ($this->has_attribute($name)) {
			return $this->read_attribute($name);
		}
		
		if ($this->has_association($name)) {
			return $this->association_proxy($name)->get();
		}
		
		//throw new Exception ...
	}
	
	function __isset($name)
	{
		// TODO: check this for correctness!
		return $this->has_attribute($name) || $this->has_association($name);
	}
	
	function __call($name, $arguments)
	{
		if (preg_match('/(build|create)_(\w+)/', $name, $match) && $this->has_association($association_name = Inflector::pluralize($match[2]))) {
			$association = $this->association_proxy($association_name);
			
			if (!$association->is_collection() && ($match[1] == 'build' || $match[1] == 'create')) {
				call_user_func_array(array($association, $match[1]), $arguments);
			}
		}
		
		throw new Exception('Undefined method ' . $name . ' in class ' . get_class($this));
	}
	
	function is_same_as($against)
	{
		return (get_class($against) == get_class($this)) && !$this->is_new_record() && ($this->get_id() == $against->get_id());
	}
	
	function write_attribute($attribute, $value, $force_dirty = false)
	{
		if ($this->is_frozen()) return false; // throw an exception?
		
		if ($column = $this->column_for_attribute($attribute)) {
			// Handle dates passed as an assoc array of month, day and year
			if (is_array($value) && $column->is_date()) {
				if (isset($value['d']) && isset($value['m']) && isset($value['y'])) {
					$value = strtotime("$value[m]/$value[d]/$value[y]");
				}
			}
			
			// Check if the attribute actually changed
			if ($force_dirty || !($column->type_cast(@$this->attributes[$attribute]) === $column->type_cast($value))) {
				$this->mark_attribute_as_dirty($attribute);
				$this->attributes[$attribute] = $value;
			}
		}
		
		return $value;
	}
	
	function has_attribute($attribute)
	{
		return isset($this->attributes[$attribute]);
	}
	
	function read_attribute($attribute)
	{
		if ($this->has_attribute($attribute)) {
			if ($column = $this->column_for_attribute($attribute)) {
				return $column->type_cast($this->attributes[$attribute]);
			} else {
				return $this->attributes[$attribute];
			}
		} else {
			return null;
		}
	}
	
	function set_attributes($attributes)
	{
		if ($this->is_frozen()) return false;
		
		$attributes_protected = $this->attributes_protected();
		
		foreach ($attributes as $attribute => $value) {
			if (isset($attributes_protected[$attribute]) && $attributes_protected[$attribute]) continue;
			$this->__set($attribute, $value);
		}
	}
	
	function get_id()
	{
		return $this->attributes[$this->primary_key()];
	}
	
	function get_quoted_id()
	{
		return $this->get_quoted_attribute($this->primary_key());
	}
	
	function set_id($value)
	{
		return $this->write_attribute($this->primary_key(), $value);
	}
	
	function update_attribute($attribute, $value)
	{
		$this->__set($attribute, $value);
		return $this->save();
	}
	
	function update_attributes($attributes)
	{
		$this->set_attributes($attributes);
		return $this->save();
	}
	
	function save()
	{
		if ($this->run_callback('before_save')) {
			$result = $this->create_or_update();
		}
		
		if ($result) {
			$this->run_callback('after_save');
			$this->clean();
			$this->run_callback('after_save_clean');
		}
		
		return $result;
	}
	
	function save_without_callbacks()
	{
		if ($this->create_or_update()) {
			$this->clean();
			return true;
		} else {
			return false;
		}
	}
	
	function destroy()
	{
		if (!$this->run_callback('before_destroy')) return false;
		
		if (!$this->is_new_record()) {
			self::$connection->delete(
				'DELETE FROM ' . $this->get_quoted_table_name() . ' ' .
				'WHERE ' . self::$connection->quote_column_name($this->primary_key()) . ' = ' .
				$this->get_quoted_id()
			);
		}
		
		$this->freeze();
		
		$this->run_callback('after_destroy');
		
		return $this;
	}
	
	function reload()
	{
		$reloaded = self::find_one(get_class($this), $this->get_id());
		$this->attributes = $reloaded->attributes;
		$this->clean();
		return $this;
	}
	
	function freeze()
	{
		$this->frozen = true;
		
		return $this;
	}
	
	function is_frozen()
	{
		return $this->frozen;
	}
	
	function mark_as_read_only()
	{
		$this->read_only = true;
	}
	
	function is_read_only()
	{
		$this->read_only;
	}
	
	function is_new_record()
	{
		return $this->new_record;
	}
	
	function is_dirty()
	{
		return (bool)count($this->dirty_attributes);
	}
	
	function attribute_is_dirty($attribute)
	{
		return array_key_exists($attribute, $this->dirty_attributes);
	}
	
	function attribute_was($attribute)
	{
		return $this->dirty_attributes[$attribute];
	}
	
	function table_name()
	{
		return $this->get_table_name(get_class($this));
	}
	
	function to_param()
	{
		return $this->get_id();
	}
	
	function has_association($name)
	{
		$associations = $this->associations();
		return isset($associations[$name]);
	}
	
	/*
	 * Must be reimplemented in subclasses as:
	 *
	 *  public static function find($ids, $options = array())
	 *  {
	 * 		return parent::find(__CLASS__, $ids, $options);
	 *  }
	 */
	public static function find($class_name, $ids, $options = array())
	{
		return self::find_from_ids($class_name, $ids, $options);
	}
	
	/*
	 * Must be reimplemented in subclasses as:
	 *
	 *  public static function find_all($options = array())
	 *  {
	 * 		return parent::find_all(__CLASS__, $options);
	 *  }
	 */
	public static function find_all($class_name, $options = array())
	{
		return self::find_by_sql($class_name, self::construct_finder_sql($class_name, $options));
	}
	
	/*
	 * Must be reimplemented in subclasses as:
	 *
	 *  public static function find_first($options = array())
	 *  {
	 * 		return parent::find_first(__CLASS__, $options);
	 *  }
	 */
	public static function find_first($class_name, $options = array())
	{
		$options['limit'] = 1;
		$result = self::find_all($class_name, $options);
		return isset($result[0]) ? $result[0] : null;
	}
	
	/*
	 * Must be reimplemented in subclasses as:
	 *
	 *  public static function find_last($options = array())
	 *  {
	 * 		return parent::find_last(__CLASS__, $options);
	 *  }
	 */
	public static function find_last($class_name, $options = array())
	{
		if ($options['order']) {
			$options['order'] = SqlBuilder::reverse_sql_order($options['order']);
		} else {
			$options['order'] = '`' . self::get_table_name($class_name) . '`.`' . call_user_func(array($class_name, 'primary_key')) . '` DESC';
		}
		
		return self::find_first($class_name, $options);
	}
	
	/*
	 * Must be reimplemented in subclasses as:
	 *
	 *  public static function find_by_sql($sql)
	 *  {
	 * 		return parent::find_by_sql(__CLASS__, $sql);
	 *  }
	 */
	public static function find_by_sql($class_name, $sql)
	{
		$raw_records = self::$connection->select($sql);
		
		$records = array();
		
		foreach ($raw_records as $raw_record) {
			$records[] = self::instantiate($class_name, $raw_record);
		}
		
		return $records;
	}
	
	public static function calculate($class_name, $operation, $column_name, $options = array())
	{
		if (isset($options['select']) && $options['select']) {
			$column_name = $options['select'];
		}
		
		if ($operation != 'count') {
			$columns = self::$connection->columns(self::get_table_name($class_name));
			$field_name = end(explode('.', $column_name));
			$column = isset($columns[$field_name]) ? $columns[$field_name] : null;
		} else {
			$column = null;
		}
		
		if (isset($options['group'])) {
			return self::execute_grouped_calculation($class_name, $operation, $column_name, $column, $options);
		} else {
			return self::execute_simple_calculation($class_name, $operation, $column_name, $column, $options);
		}
	}
	
	/*
	 * Must be reimplemented in subclasses as:
	 */
	public static function count($class_name, $options = array())
	{
		return self::calculate($class_name, 'count', '*', $options);
	}
	
	public static function update_all($class_name, $update, $conditions = null, $options = array())
	{
		$sql = 'UPDATE ' . self::get_table_name($class_name) . ' SET ' . SqlBuilder::sanitize_sql_for_assignment($update);
		SqlBuilder::add_conditions($sql, $conditions);
		// TODO: do something with options!
		self::$connection->update($sql);
	}
	
	public static function delete_all($class_name, $conditions = null)
	{
		$sql = 'DELETE FROM ' . self::get_table_name($class_name);
		SqlBuilder::add_conditions($sql, $conditions);
		self::$connection->delete($sql);
	}
	
	public static function destroy_all($class_name, $conditions = null)
	{
		$records = self::find_all($class_name, array('conditions' => $conditions));
		
		foreach ($records as &$record) {
			$record->destroy();
		}
	}
	
	/*
	 * Must be overwritten in subclasses if the table name can't be inferred from
	 * the class name:
	 *
	 * public static function get_table_name() { return 'my_table'; }
	 */
	public static function get_table_name()
	{
		if (func_num_args()) {
			return Inflector::tableize(func_get_arg(0));
		} else {
			throw new Exception('Expected a $class_name parameter for ActiveRecord::get_table_name()');
		}
	}
	
	/*
	 * Must be overwritten in subclasses if the table's primary key isn't 'id'
	 */
	public static function primary_key()
	{
		return 'id';
	}
	
	protected function mark_attribute_as_dirty($attribute)
	{
		$this->dirty_attributes[$attribute] = $this->read_attribute($attribute);
	}
	
	private function clean()
	{
		$this->dirty_attributes = array();
		$this->errors = array();
	}
	
	/*
	 * Set the list of attributes
	 */
	private function touch_date_attributes()
	{
		$columns = $this->get_columns();
		$time = time();
		foreach (func_get_args() as $attribute) {
			if (isset($columns[$attribute]) && $columns[$attribute]->is_date()) {
				$this->write_attribute($attribute, $time);
			}
		}
	}
	
	/*
	 * Persists this record in the database by performing an SQL CREATE
	 * and updates the primary key
	 *
	 * Callbacks: before_create, after_create
	 *
	 * Auto-updated attributes: created_at, created_on, updated_at, updated_on (if present as dates)
	 */
	private function create_record()
	{
		if ($this->is_frozen() || $this->is_read_only()) {
			return false;
		}
		
		if (!$this->run_callback('before_create')) {
			return false;
		}
		
		$this->touch_date_attributes('created_at', 'created_on', 'updated_at', 'updated_on');
		
		$quoted_attributes = $this->get_attributes_with_quotes();
		
		if (!empty($quoted_attributes)) {
			$statement =
			'INSERT INTO ' . $this->get_quoted_table_name() . ' ' .
			'(' . join(', ', $this->get_quoted_column_names($quoted_attributes)) . ') ' .
			'VALUES (' . join(', ', $quoted_attributes) . ')';
		}
		
		if ($result = self::$connection->insert($statement)) {
			$this->set_id($result);
		
			$this->new_record = false;
			
			$this->run_callback('after_create');
			
			return true;
		} else {
			return false;
		}
	}
	
	/*
	 * Persists this record in the database by performing an SQL UPDATE
	 *
	 * Callbacks: before_update, after_update
	 *
	 * Auto-updated attributes: updated_at, updated_on (if present as dates)
	 */
	private function update_record()
	{
		if ($this->is_frozen() || $this->is_read_only()) {
			return false;
		}
		
		if (!$this->is_dirty()) {
			return true;
		}
		
		if (!$this->run_callback('before_update')) {
			return false;
		}
		
		$this->touch_date_attributes('updated_at', 'updated_on');
		
		$quoted_attributes = $this->get_attributes_with_quotes();
		
		if (!empty($quoted_attributes)) {
			$result = self::$connection->update(
				'UPDATE ' . $this->get_quoted_table_name() . ' ' .
				'SET ' . SqlBuilder::quoted_comma_pair_list($quoted_attributes) . ' ' .
				'WHERE ' .
					self::$connection->quote_column_name($this->primary_key()) .
					' = ' .
					$this->get_quoted_attribute($this->primary_key())
			);
		}
		
		$this->run_callback('after_update');
		
		return $result;
	}
	
	private function create_or_update()
	{
		if ($this->is_new_record()) {
			return $this->create_record();
		} else {
			return $this->update_record();
		}
		
		//$this->clean();
	}
	
	private function get_attributes_with_quotes($include_primary_key = true, $include_readonly_attributes = true, $attribute_names = null)
	{
		if (!isset($attribute_names)) {
			$attribute_names = array_keys($this->attributes);
		}
		
		$columns = $this->get_columns();
		
		$quoted_attributes = array();
		
		foreach ($attribute_names as $name) {
			if (isset($columns[$name]) && ($include_primary_key || $name == $this->primary_key())) {
				$quoted_attributes[$name] = $this->get_quoted_attribute($name);
			}
		}
		
		return $quoted_attributes;
	}
	
	protected function get_quoted_attribute($name)
	{
		$value = $this->read_attribute($name);
		return self::$connection->quote($value, $this->column_for_attribute($name));
	}
	
	private function get_quoted_table_name()
	{
		return $this->quoted_table_name(get_class($this));
	}
	
	private function get_quoted_column_names($attributes = null)
	{
		if (!isset($attributes)) $attributes = $this->get_attributes_with_quotes();
		return array_map(
			array(self::$connection, 'quote_column_name'),
			//array_keys($this->get_columns())
			array_keys($attributes)
		);
	}
	
	protected function run_callback($name)
	{
		if (method_exists($this, $name)) {
			return !($this->{$name}() === false);
		} else {
			return true;
		}
	}
	
	/*
	 * Get a list of columns belonging to this record's table
	 *
	 * It returns an array by reference, so be polite and don't touch it!
	 */
	protected function &get_columns()
	{
		return self::$connection->columns($this->table_name());
	}
	
	/*
	 * Get the column for a given attribute in this record's table
	 */
	protected function column_for_attribute($attribute)
	{
		$columns = $this->get_columns();
		return $columns[$attribute];
	}
	
	/*
	 * Must be overwritten in subclasses to specify protected attributes.
	 *
	 * Should return an associative array with attribute names in keys and true in values.
	 *
	 * Make sure to include the primary key.
	 */
	protected function attributes_protected()
	{
		return array($this->primary_key() => true);
	}
	
	protected static function primary_key_for($class_name)
	{
		return call_user_func(array($class_name, 'primary_key'));
	}
	
	protected static function find_some($class_name, $ids, $options)
	{
		$conditions = '';
		
		if ($options['conditions']) {
			$conditions = ' AND (' . SqlBuilder::sanitize_sql_for_conditions($options['conditions']) . ')';
		}
		
		$primary_key = self::primary_key_for($class_name);
		$table_columns = self::$connection->columns(self::get_table_name($class_name));
		$ids_list = array();
		
		foreach ($ids as $id) {
			$ids_list[] = self::$connection->quote($id, $table_columns[$primary_key]);
		}
		$ids_list = join(', ', $ids_list);
		
		$options['conditions'] = self::quoted_table_name($class_name) . '.' .
			self::$connection->quote_column_name($primary_key) .
			' IN (' . $ids_list . ')' . $conditions;
		
		$result = self::find_all($class_name, $options);
		
		if (count($result) < count($ids)) {
			throw new RecordNotFound("Couldn't find all $class_name with IDs (" . join(',', $ids) . ') (found ' . count($result) . ' results, but was looking for ' . count($ids) . ')');
		}
		
		return $result;
	}
	
	protected static function find_one($class_name, $id, $options)
	{
		$conditions = '';
		
		if (isset($options['conditions'])) {
			$conditions = ' AND (' . SqlBuilder::sanitize_sql_for_conditions($options['conditions']) . ')';
		}
		
		$primary_key = self::primary_key_for($class_name);
		$table_columns = self::$connection->columns(self::get_table_name($class_name));
		
		$options['conditions'] = self::quoted_table_name($class_name) . '.' .
			self::$connection->quote_column_name($primary_key) .
			' = ' .
			self::$connection->quote($id, $table_columns[$primary_key]) .
			$conditions;
		
		if ($result = self::find_all($class_name, $options)) {
			return $result[0];
		} else {
			throw new RecordNotFound("Couldn't find $class_name with ID=$id$conditions");
		}
	}
	
	protected static function find_from_ids($class_name, $ids, $options)
	{
		if (empty($ids)) throw new ActiveRecordException("Can't find $class_name without an ID");
		
		if (is_array($ids)) {
			$ids = array_unique($ids);
			
			if (count($ids) > 1) {
				return self::find_some($class_name, $ids, $options);
			} else {
				return array(self::find_one($class_name, $ids[0], $options));
			}
		} else {
			return self::find_one($class_name, $ids, $options);
		}
	}
	
	protected static function instantiate($class_name, $raw_record)
	{
		$object = new $class_name;
		
		// This overrides the actual object's $attributes property (i.e. bypasses __set())
		$object->attributes = $raw_record;
		
		$object->new_record = false;
		
		return $object;
	}
	
	public static function quoted_table_name($class_name)
	{
		return self::$connection->quote_table_name(self::get_table_name($class_name));
	}
	
	protected static function default_select($class_name, $qualified)
	{
		if ($qualified) {
			return self::quoted_table_name($class_name) . '.*';
		} else {
			return '*';
		}
	}
	
	/*
	 * Build the SELECT statement to use in a find
	 */
	protected static function construct_finder_sql($class_name, &$options)
	{
		$sql = 'SELECT ' . (isset($options['select']) ? $options['select'] : self::default_select($class_name, @$options['joins'])) . ' ' .
		       'FROM '   . (isset($options['from'])   ? $options['from']   : self::quoted_table_name($class_name));
		
		SqlBuilder::add_sql_trail($sql, $options);
		
		return $sql;
	}
	
	protected static function construct_calculation_sql($class_name, $column_name, $operation, &$options)
	{
		$columns = self::$connection->columns(self::get_table_name($class_name));
		
		if (isset($columns[$column_name])) {
			$column_name = self::quoted_table_name($class_name) . '.' . $column_name;
		}
		
		$sql = 'SELECT ' . $operation . '(' . (isset($options['distinct']) ? 'DISTINCT ' : '') . $column_name . ') ' .
		       'FROM '   . (isset($options['from']) ? $options['from'] : self::quoted_table_name($class_name));
		
		SqlBuilder::add_sql_trail($sql, $options);
		
		return $sql;
	}
	
	protected static function type_cast_calculated_value($value, $column, $operation = null)
	{
		switch ($operation) {
			case 'count': return (int)$value;
			// Ruby code to port:
			//when 'sum'   then type_cast_using_column(value || '0', column)
			//when 'avg'   then value && (value.is_a?(Fixnum) ? value.to_f : value).to_d
			default:      return $column ? $column->type_cast($value) : $value;
		}
	}
	
	protected static function execute_simple_calculation($class_name, $operation, $column_name, $column, &$options)
	{
		$value = self::$connection->select_value(self::construct_calculation_sql($class_name, $column_name, $operation, $options));
		
		return self::type_cast_calculated_value($value, $column, $operation);
	}
	
	/*
	 * Must be overwritten in subclasses to define extensions
	 */
	protected static function define_extensions()
	{
	}
	
	protected function extensions()
	{
		return self::extensions_for(get_class($this));
	}
	
	protected static function extensions_for($class_name)
	{
		if (!isset(self::$extensions[$class_name])) {
			self::$extensions[$class_name] = array();
			$static_proxy = new ClassStaticProxy($class_name);
			call_user_func(array($class_name, 'define_extensions'), $static_proxy);
		}
		
		return self::$extensions[$class_name];
	}
	
	public static function add_extension($class_name, $extension_class, $options)
	{
		self::$extensions[$class_name][] = array($extension_class, $options);
	}
	
	/*
	 * Must be overwritten in subclasses to define associations
	 *
	 * Using this method to define associations instead of defining the associations outside
	 * of the class has the benefit of not creating the associations in memory unless being
	 * absolutely necessary (i.e. this gets called on demand).
	 */
	protected static function define_associations()
	{
	}
	
	protected function &association_proxy($name)
	{
		if (!isset($this->association_proxies[$name])) {
			$associations = $this->associations();
			
			$association_class = Inflector::classify($associations[$name][0]) . 'Association';
			
			if (class_exists($association_class) && is_subclass_of($association_class, 'AssociationProxy')) {
				$this->association_proxies[$name] = new $association_class($this, $associations[$name][1]);
			}
		}
		
		return $this->association_proxies[$name];
	}
	
	protected function &associations()
	{
		return self::associations_for(get_class($this));
	}
	
	protected static function &associations_for($class_name)
	{
		if (!isset(self::$associations[$class_name])) {
			self::$associations[$class_name] = array();
			
			/*
			 * Create a static proxy to provide a nicer API, e.g.:
			 * 
			 * 		$company->has_many('employees');
			 * 
			 * instad of:
			 * 
			 * 		self::has_many(__CLASS__, 'employees');
			 */
			$static_proxy = new ClassStaticProxy($class_name);
			
			call_user_func(array($class_name, 'define_associations'), $static_proxy);
		}
		
		return self::$associations[$class_name];
	}
	
	private static function add_association($class_name, $name, $type, $options)
	{
		if (!isset(self::$associations[$class_name][$name])) {
			self::$associations[$class_name][$name] = array($type, $options);
		} else {
			throw new ActiveRecordException("Can't redefine association $name for class $class_name.");
		}
	}
	
	/*
	protected static function has_one($class_name, $singular_name, $options = array())
	{
		self::add_association($class_name, $singular_name, 'has_one', $options);
	}
	*/
	
	static function belongs_to($class_name, $singular_name, $options = array())
	{
		BelongsToAssociation::initialize_association_options($class_name, $singular_name, $options);
		self::add_association($class_name, $singular_name, 'belongs_to', $options);
	}
	
	static function has_many($class_name, $plural_name, $options = array())
	{
		HasManyAssociation::initialize_association_options($class_name, $plural_name, $options);
		self::add_association($class_name, $plural_name, 'has_many', $options);
	}
	
	static function has_and_belongs_to_many($class_name, $plural_name, $options = array())
	{
		HasAndBelongsToManyAssociation::initialize_association_options($class_name, $plural_name, $options);
		self::add_association($class_name, $plural_name, 'has_and_belongs_to_many', $options);
	}
}
