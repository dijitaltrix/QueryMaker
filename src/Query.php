<?php 

namespace Dijix\QueryMaker;

use Exception;
use Dijix\QueryMaker\DbInterface;

class Query {
	
	# sql query string
	protected $sql;
	# values for passing to PDO
	protected $values;
	# database table for model
	protected $table;
	# select array containg columns
	protected $select;
	# from table clause
	protected $from;
	# join clauses array
	protected $join;
	# where clauses array
	protected $where;
	# order by clause
	protected $order_by;
	# group by clause
	protected $group_by;
	# having clause
	protected $having;
	# limit clause
	protected $limit;
	# parameter index for bound queries
	private $parameter_index = 0;
	

	/**
	 * Inject DB Adapter
	 *
	 * @param AdapterInterface $db 
	 */
	public function __construct(Adapters\AdapterInterface $db)
	{
		$this->db = $db;
	}

	#
	#	query builder methods
	#
	public function from($str)
	{
		$this->from[] = $str;

		return $this;
	}
	public function join($table, $col_1, $operator, $col_2, $type=null)
	{
		if ( ! $type) {
			$type = 'JOIN';
		} else {
			$type = "$type JOIN";
		}
		
		$this->join[] = array(
			'type' => $type,
			'table' => $table,
			'col_1' => $col_1,
			'col_2' => $col_2,
			'operator' => $operator,
		);

		return $this;
	}
	public function limit($from, $to=null)
	{
		if ($to) {
			$this->limit = "$from, $to";
		} else {
			$this->limit = $from;
		}

		return $this;

	}
	public function orderBy($str, $dir=null)
	{
		$dir = strtoupper($dir);
		if ( ! in_array($dir, array('ASC', 'DESC'))) {
			$dir = 'ASC';
		}
		
		$this->order_by[$str] = $dir;

		return $this;
	}
	public function select($fields)
	{
		if ( ! is_array($fields))
		{
			$fields = explode(',', $fields);
		}
		
		foreach ($fields as $field)
		{
			$this->select[] = trim($field);
		}
		
		return $this;
		
	}
	public function table($str)
	{
		$this->table = $str;
		return $this;
	}

	# where clauses
	public function where($field, $operator=null, $value=null, $boolean='AND', $logic=null)
	{
		# handle nested where clauses
		if (is_callable($field) && ! $operator) 
		{
			$this->where[] = array(
				'boolean' => $boolean, 
				'open' => '(',
			);
			call_user_func_array($field, array('q'=>$this));
			$this->where[] = array(
				'close' => ')'
			);
			return $this;
		}
		
		# set default operator to 'equals' when using short syntax
		# eg where(this, that);
		if (empty($value))
		{
			$value = $operator;
			$operator = '=';
		}
		
		$this->where[] = array(
			'boolean' => $boolean,
			'logic' => $logic,
			'field' => $field,
			'operator' => $operator,
			'value' => $this->setValue($field, $value)
		);
		
		return $this;
		
	}
	public function whereNot($field, $operator, $value=null, $boolean='AND')
	{
		return $this->where($field, $operator, $value, $boolean, $logic='NOT');
		
	}
	public function orWhere($field, $operator=null, $value=null)
	{
		return $this->where($field, $operator, $value, 'OR');
		
	}
	public function orWhereNot($field, $operator=null, $value=null)
	{
		return $this->where($field, $operator, $value, 'XOR');
		
	}	

	# where between clauses
	public function whereBetween($field, $min, $max=null, $boolean='AND', $logic=null)
	{
		if (is_array($min) && $max == null) {
			$max = $min[1];
			$min = $min[0];
		}
		
		$this->where(function($q) use($field, $min, $max) {
			$q->where($field, '>=', $min);
			$q->where($field, '<=', $max);
		});
		
		return $this;
		
	}
	public function whereNotBetween($field, $min, $max=null)
	{
		if (is_array($min) && $max == null) {
			$max = $min[1];
			$min = $min[0];
		}
		
		$this->whereNot(function($q) use($field, $min, $max) {
			$q->where($field, '>=', $min);
			$q->where($field, '<=', $max);
		});
		
		return $this;
		
	}
	public function orWhereBetween($field, $min, $max=null)
	{
		return $this->whereBetween($field, $min, $max, $boolean='OR');
	}
	public function orWhereNotBetween($field, $min, $max=null)
	{
		return $this->whereBetween($field, $min, $max, $boolean='OR', $logic='NOT');
	}

	# where null clauses
	public function whereNull($field, $boolean='AND', $negate=false)
	{
		$operator = 'IS NULL';
		if ($negate) {
			$operator = 'IS NOT NULL';
		}
		
		$this->where[] = array(
			'boolean' => $boolean,
			'field' => $field,
			'operator' => $operator,
			'value' => null
		);
		
		return $this;
		
	}
	public function whereNotNull($field, $boolean='AND')
	{
		return $this->whereNull($field, $boolean, $negate=true);
		
	}
	public function orWhereNull($field)
	{
		return $this->whereNull($field, 'OR');
		
	}
	public function orWhereNotNull($field)
	{
		return $this->whereNull($field, 'OR', $negate=true);
		
	}
	
	# where in clauses
	public function whereIn($field, $array, $boolean='AND', $negate=false)
	{
		$operator = 'IN';
		if ($negate) {
			$operator = 'NOT IN';
		}
		
		$this->where[] = array(
			'boolean' => $boolean,
			'field' => $field,
			'operator' => $operator,
			'value' => $this->setValue($field, $array)
		);
		
		return $this;
		
	}
	public function whereNotIn($field, $array, $boolean='AND')
	{
		return $this->whereIn($field, $array, $boolean, $negate=true);
		
	}
	

	#
	#	Base query execute methods
	#
	public function delete($id=null)
	{
		# begin sql
		$this->sql = "DELETE FROM $this->table ";
		
		if (is_int($id)) {
			# delete by id
			$this->sql.= "WHERE id = $id";
			
		} else {
			$this->sql.= $this->getWhere();
		}

		return $this->execute();
		
	}
	public function insert($data=array())
	{
		if (empty($data)) {
			throw new Exception("No data received for insert");
			
		}
		if ( ! is_array($data)) {
			throw new Exception("Invalid data type received for insert: ".gettype($data));
			
		}
		
		# handle where clause
		if (is_array($data)) {

			# begin sql
			$this->sql = "INSERT INTO $this->table ";

			# check whether we're inserting one, or multiple rows
			if (isset($data[0]) && is_array($data[0])) 
			{
				# build fields from first dataset
				$fields = array();
				foreach ($data[0] as $key=>$value)
				{
					$fields[] = "$key";
				}
				$this->sql.= '('.implode(', ', $fields).')';
				
				# build multiple values statement, 
				# uses $i as unique field identifier
				$i = 1;
				$this->sql.= ' VALUES ';
				$fields = array();
				foreach ($data as $row) 
				{
					# reset parameter bind counter 
					# on each loop to keep it neat
					$this->parameter_index = 0;
					$values = array();
					foreach ($row as $key=>$value)
					{
						$values[] = $this->setValue($key, $value, $prefix=$i);
					}
					$this->sql.= '('.implode(', ', $values).'),';
					# incremement values unique id
					$i++;
				}
				$this->sql = rtrim($this->sql, ',');
			}
			else
			{
				# build single values statement
				$fields = array();
				$values = array();
				foreach ($data as $key=>$value)
				{
					// $marker = $this->setValue($key, $value);
					// $fields[] = "$key=$marker";

					$fields[] = $key;
					$values[] = $this->setValue($key, $value);
					
					// $fields[] = "$key";
					// $values[] = ":$key";
					// $this->values[":$key"] = $value;
				}
				$this->sql.= '('.implode(', ', $fields).')';
				$this->sql.= ' VALUES ';
				$this->sql.= '('.implode(', ', $values).')';
			}

			return $this->execute();
			
		}
	
		
	}
	public function update($data=array())
	{
		if (empty($data)) {
			throw new Exception("No data received for update");
			
		}
		if ( ! is_array($data)) {
			throw new Exception("Invalid data type received for update: ".gettype($data));
			
		}
		
		# make query, only supports single table syntax
		$this->sql = "UPDATE $this->table SET ";
		$fields = array();
		foreach ($data as $key=>$value)
		{
			$marker = $this->setValue($key, $value);
			$fields[] = "$key=$marker";
		}
		$this->sql.= implode(', ', $fields);
		$this->sql.= $this->getWhere();
		$this->sql.= $this->getOrderBy();
		$this->sql.= $this->getLimit();

		return $this->execute();

	}
	

	#
	#	Additional query execute methods
	#
	public function exists()
	{
		#TODO select 
	}
	public function get()
	{
		# build select query
		$this->sql = $this->getSelect();
		$this->sql.= " FROM $this->table";
		$this->sql.= $this->getJoin();
		$this->sql.= $this->getWhere();
		$this->sql.= $this->getGroupBy();
		$this->sql.= $this->getHaving();
		$this->sql.= $this->getOrderBy();
		$this->sql.= $this->getLimit();
		
		return $this->execute();

	}
	public function getOne()
	{
		$this->limit(1);
		$data = $this->get();
		
		# return one record, not collection
		if (is_array($data) && isset($data[0])) {
			return $data[0];
		}
		
		return $data;

	}
	public function insertGetId($data=array())
	{
		if ($this->insert($data)) {
			return $this->getInsertId();
		}
		
		return false;
		
	}

	
	#
	#	Additional query execute methods
	#
	public function hasOne($class, $relation_id)
	{
		$r = new $class();
		$this->table($r->table);
		$this->where($relation_id, '=', $this->id);
		
		return $this;
		
	}
	public function belongsTo($class, $parent_id)
	{
		return $this->where('id', '=', $this->$parent_id);
		
	}
	public function hasMany($class, $key_field)
	{
		return $this->where($key_field, '=', $this->id);
	
	}
	

	#
	#	Always reset state after every query
	#
	private function reset()
	{
		foreach (get_object_vars($this) as $key=>$value)
		{
			$this->$key = null;
		}
	
	}
	

	#
	#	Query clause builders
	#
	private function getGroupBy()
	{
		#TODO
		if ( ! empty($this->group_by)) {

		}

	}
	private function getHaving()
	{
		#TODO
		if ( ! empty($this->having)) {
			
		}

	}
	private function getJoin()
	{
		if (is_array($this->join))
		{
			$out = ' ';
			foreach ($this->join as $join)
			{
				$out.= $join['type'].' ';
				$out.= $join['table'].' ON ';
				$out.= $join['col_1'].' ';
				$out.= $join['operator'].' ';
				$out.= $join['col_2'].' ';
			}
			
			return $out;
		}
	}
	private function getLimit()
	{
		if ( ! empty($this->limit)) {
			return " LIMIT $this->limit";
		}

	}
	private function getOrderBy()
	{
		if ( ! empty($this->order_by)) {
			$out = array();
			foreach ($this->order_by as $field => $dir)
			{
				$out[] = "$field $dir";
			}
			$out = " ORDER BY ".implode(', ', $out);
			
			return $out;
		
		}

	}
	private function getSelect()
	{
		$out = 'SELECT ';
		if (is_array($this->select))
		{
			return $out.implode(', ', $this->select);
		}
		else
		{
			return "$out *";
		}
	}
	private function getWhere()
	{
		if (is_array($this->where))
		{
			$out = " WHERE ";
			$c = count($this->where);
			$i = 0;
			
			#TODO break down into single clause or multi clause handling
			foreach ($this->where as $where)
			{
				# set logic first
				if (isset($where['logic']) && ! empty($where['logic'])) {
					$out.= " ";
					$out.= $where['logic'];
				}
				
				if (count($where) == 1) {
					$out.= " ";
					$out.= $where['close'];
					$out.= " ";
				
				} elseif (count($where) == 2) {
					$out.= " ";
					if ($i > 0) {
						$out.= $where['boolean'];
					}
					$out.= " ";
					$out.= $where['open'];
					$i = 0;
					
				} else {
					$out.= " ";
					if ($i > 0) {
						# add WHERE clause booleans AND OR etc..
						$out.= $where['boolean'].' ';
					}
					$out.= $where['field'].' ';
					$out.= $where['operator'].' ';
					$out.= $where['value'];
					$i++;

				}
			}
			
			return $out;
			
		}
	}
	
	private function getMarker()
	{
		# create and return marker
		# set marker and value in keyed array
		$i = ++$this->parameter_index;
		return ":$i";
		
	}
	
	private function setValue($field, $value, $prefix=null)
	{
		$marker = $this->getMarker();
		if ($prefix) {
			$marker = str_replace(':', ':'.$prefix.'_', $marker);
		}
		
		if (is_array($value)) {
			# turn value to string
			$c = count($array);
			# add markers - get your glasses for this!
			$markers = rtrim(str_repeat('?,', $c),',');
			
			foreach ($value as $v) {
				$this->values[] = $v;
			}

			return "()";
			
		} else {
			$this->values[$marker] = $value;
			return $marker;
		}

	}
	
	
	#
	# 	DB Calls
	#
	public function getInsertId()
	{
		if (isset($this->db->last_insert_id)) {
			return $this->db->last_insert_id;
		}
		
		throw new Exception("Calling getInsertId when database is not instantiated");
		
	}
	public function getAffectedRows()
	{
		if (isset($this->db->affected_rows)) {
			return $this->db->affected_rows;
		}
		
		throw new Exception("Calling affactedRows when database is not instantiated");
		
	}
	private function execute()
	{
		# grab result data
		$result = $this->db->execute($this->sql, $this->values);
		# empty all query parameters
		$this->reset();
		
		return $result;
		
	}
	
	
	#
	#	magic methods
	#
	public function __call($method, $args)
	{
		$scope = 'scope'.ucfirst($method);
		if (method_exists($this, $scope))
		{
			switch (count($args))
			{
				case 1:
				return $this->$scope($args[0]);
				case 2:
				return $this->$scope($args[0], $args[1]);
				case 3:
				return $this->$scope($args[0], $args[1], $args[2]);
				case 4:
				return $this->$scope($args[0], $args[1], $args[2], $args[3]);

				default:
				return $this->$scope();
			}
		}
		
		throw new Exception("Method $method() does not exist");
		
	}
	
	
}