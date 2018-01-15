<?php 

namespace Dijix\QueryMaker\Adapters;

interface AdapterInterface {
	
	/**
	 * Assign DSN connection details and any other config through constructor
	 *
	 * @param Array $config 
	 */
	public function __construct($config=[]);

	/**
	 * Make a connection to the database
	 *
	 * @return boolean
	 */
	public function connect();
	
	/**
	 * Executes a query and returns the result
	 *
	 * @param string $query 
	 * @param array $values 
	 * @param boolean $return_collection 
	 * @return mixed
	 */
	public function execute($query, $values, $return_collection=false);
	
	/**
	 * Returns the id of the last inserted row
	 *
	 * @return integer
	 */
	public function lastInsertId();

	/**
	 * Returns the number of rows affected by the previous statement
	 *
	 * @return integer
	 */
	public function affectedRows();

}