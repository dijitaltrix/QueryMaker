<?php 

namespace Dijix\QueryMaker;

use PDO;

class DB {
	
	# database connection
	protected $config;
	protected $pdo;
	protected $stm;
	protected $last_insert_id;
	protected $affected_rows;

	# sql query string
	protected $query;
	protected $verb;
	
	# logger class
	public $log;


	public function __construct($config)
	{
		$this->config = $config;
	}

	public function connect()
	{
		try {

			$dsn = $this->config['driver'];
			$dsn.= ':dbname='.$this->config['name'];
			$dsn.= ';host='.$this->config['host'];
			$this->pdo = new PDO($dsn, $this->config['user'], $this->config['password']);
			
		} catch (Exception $e) {

			throw $e;

		}
		
	}
	
	public function execute($query, $values, $return_collection=false)
	{
		if ( ! isset($this->pdo)) {
			$this->connect();
		}

		# clear out attributes
		$this->last_insert_id = null;
		$this->affected_rows = null;
		
		# prepare and execute query
		$this->stm = $this->pdo->prepare($query);
		if ($this->stm->execute($values) === false)
		{
			$error = implode(" ", $this->stm->errorInfo());
			throw new Exception($error);
			
		}
		
		# get verb
		$verb = explode(' ', $query, 2);
		$verb = strtoupper(trim($verb[0]));
		# determine response
		switch ($verb)
		{
			case 'SELECT':
			$results = $this->stm->fetchAll(PDO::FETCH_ASSOC);
			if (count($results)) {
				# return all results as array
				return $results;
			
			} else {
				# return empty array so we don't break foreach loops
				return array();
			}
			break;

			default:
			# always set affected rows
			$this->affected_rows = $this->stm->rowCount();
			
			# set lastInsert ID only if there was a single row affected
			if ($verb=='INSERT' && $this->affected_rows == 1) {
				$this->last_insert_id = $this->pdo->lastInsertId();
			}
			
			# always return boolean, rows and last_id can be found using methods
			return true;
			break;
		}
		
	}
	
	public function lastInsertId()
	{
		return $this->last_insert_id;
	}

	public function affectedRows()
	{
		return $this->affected_rows;
	}
	

}