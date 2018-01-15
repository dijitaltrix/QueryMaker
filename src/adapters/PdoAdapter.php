<?php 

namespace Dijix\QueryMaker\Adapters;

use Exception;
use PDO;
use PDOException;

class PdoAdapter implements AdapterInterface {
	
	# database connection
	protected $config;
	protected $conn;
	protected $stm;
	protected $last_insert_id;
	protected $affected_rows;

	# sql query string
	protected $query;
	protected $verb;
	
	# logger instance
	private $logger;


	public function __construct($config=[])
	{
		// merge supplied config with defaults
		$this->config = array_merge([
			'driver' => null,
			'name' => null,
			'host' => null,
			'user' => null,
			'pass' => null,
			'options' => null,
		], $config);

		// use supplied logger
		if (isset($config['log']) && $config['log'] instanceof \Psr\Log\LoggerInterface) {
			$this->logger = $config['log'];
		}

	}

	public function connect()
	{
		try {

			$dsn = sprintf("%s:dbname=%s;host=%s", 
				$this->config['driver'],
				$this->config['name'],
				$this->config['host']
			);
			
			$this->conn = new PDO($dsn, $this->config['user'], $this->config['pass'], $this->config['options']);
			
		} catch (PDOException $e) {

			throw $e;

		}
		
	}
	
	public function execute($query, $values, $return_collection=false)
	{
		if ( ! isset($this->conn)) {
			$this->connect();
		}

		# clear out attributes
		$this->last_insert_id = null;
		$this->affected_rows = null;
		
		# prepare and execute query
		$stm = $this->conn->prepare($query);
		if ($stm === false) {
			throw new Exception("Could not prepare query '$query'");
		}
		if ($stm->execute($values) === false)
		{
			$error = implode(" ", $stm->errorInfo());
			throw new Exception($error);
			
		}
		
		# get verb
		$verb = explode(' ', $query, 2);
		$verb = strtoupper(trim($verb[0]));
		# determine response
		switch ($verb)
		{
			case 'SELECT':
			$results = $stm->fetchAll(PDO::FETCH_ASSOC);
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
			$this->affected_rows = $stm->rowCount();
			
			# set lastInsert ID only if there was a single row affected
			if ($verb == 'INSERT' && $this->affected_rows == 1) {
				$this->last_insert_id = $this->conn->lastInsertId();
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

	private function log()
	{

		try {
			
			return $this->logger;
			
		} catch (Exception $e) {
			
		}

	}
}