<?php
 
class DatabaseColumn
{
	protected $name;
	protected $sql_type;
	protected $type;
	protected $limit;
	protected $default;
	protected $null;
	
	function __construct($name, $default, $sql_type = nil, $null = true)
	{
		$this->name     = $name;
		$this->sql_type = $sql_type;
		$this->null     = $null;
		
		$this->limit    = $this->extract_limit($sql_type);
		$this->type     = $this->simplified_type($sql_type);
		
		$this->default  = $this->type_cast($default);
	}
	
	/*
	 ************************************************************
	 * Overloading
	 ************************************************************
	 */
	
	function __get($attribute)
	{
		if (isset($this->$attribute)) return $this->$attribute;
		/*
		if ($attribute == 'name' || $attribute == 'sql_type' || $attribute == 'default' || $attribute == 'null' || $attribute == 'limit' || $attribute == 'type') {
			return $this->$attribute;
		}
		*/
	}
	
	/*
	 ************************************************************
	 * Public instance methods
	 ************************************************************
	 */
	
	function type_cast($value)
	{
		if ($value === null) return null;
		
		switch ($this->type) {
			case 'string':
			case 'text':
				return $value;
			case 'integer':
				return (int)$value;
			case 'float':
				return (float)$value;
			case 'datetime':
			case 'timestamp':
			case 'time':
			case 'date':
				return is_string($value) ? strtotime($value) : (int)$value;
			case 'boolean':
				return (bool)$value;
		}
		
		return $value;
		
		/*
		when :string    then value
		when :text      then value
		when :integer   then value.to_i rescue value ? 1 : 0
		when :float     then value.to_f
		when :decimal   then self.class.value_to_decimal(value)
		when :datetime  then self.class.string_to_time(value)
		when :timestamp then self.class.string_to_time(value)
		when :time      then self.class.string_to_dummy_time(value)
		when :date      then self.class.string_to_date(value)
		when :binary    then self.class.binary_to_string(value)
		when :boolean   then self.class.value_to_boolean(value)
		else value
		*/
	}
	
	function is_number()
	{
		return $this->type == 'integer' || $this->type == 'float' || $this->type == 'decimal';
	}
	
	function is_text()
	{
		return $this->type == 'string' || $this->type == 'text';
	}
	
	function is_date()
	{
		return $this->type == 'datetime' || $this->type == 'date' || $this->type == 'timestamp' || $this->type == 'time';
	}
	
	function human_name()
	{
		return Inflector::humanize($this->name);
	}

	/*
	 ************************************************************
	 * Protected instance methods
	 ************************************************************
	 */
	
	protected function extract_limit($sql_type)
	{
		$match = null;
		if (preg_match('/\((.*)\)/', $sql_type, $match)) {
			return (int)$match[1];
		}
	}
	
	protected function extract_sql_type($sql_type)
	{
		return rtrim(strtolower($sql_type), '0123456789()');
	}
	
	protected function simplified_type($sql_type)
	{
		$types_map = array(
			'int'                    => 'integer',
			'float|double'           => 'float',
			'decimal|numeric|number' => 'integer', //extract_scale(field_type) == 0 ? :integer : :decimal
			'datetime'               => 'datetime',
			'timestamp'              => 'timestamp',
			'time'                   => 'time',
			'date'                   => 'date',
			'clob|text'              => 'text',
			'blob|binary'            => 'binary',
			'char|string'            => 'string',
			'boolean'                => 'boolean'
		);
		
		foreach ($types_map as $regex => $type) {
			if (preg_match("/$regex/i", $sql_type)) {
				return $type;
			}
		}
	}
}