<?php

use Dijix\QueryMaker\Adapters\PdoAdapter;
use Dijix\QueryMaker\Query;
use PHPUnit\Framework\TestCase;


final class QueryMakerTest extends TestCase
{
	public function getQueryInstance()
	{
		$conn = new PdoAdapter([
			'driver' => 'mysql',
			'name' => 'mail',
			'host' => '192.168.1.210',
			'port' => 3306,
			'user' => 'mono',
			'pass' => 'password',
		]);
		
		return new Query($conn);

	}
	
	public function testConnection()
	{
		$query = $this->getQueryInstance();
		$query->db->connect();
	}
	
	public function testSelect()
	{
		$query = $this->getQueryInstance();

		$result = $query->table("accounts")->get();
		
		var_dump($result);

	}
	
	public function testWhere()
	{
		$query = $this->getQueryInstance();

		$result = $query->table("accounts")->where('id', 3)->get();
		
		var_dump($result);

	}
	
	public function testAndWhere()
	{
		$query = $this->getQueryInstance();

		$result = $query->table("accounts")->where('active', 1)->where('`limit`', '>', 500)->get();
		
		var_dump($result);

	}

	public function testOrWhere()
	{
		$query = $this->getQueryInstance();

		$result = $query->table("accounts")->where('id', 1)->orWhere('id', 2)->get();
		
		var_dump($result);

	}

}