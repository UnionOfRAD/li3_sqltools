<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_sqltools\tests\cases\data\source\database\adapter;

use lithium\data\Connections;
use lithium\data\Schema;

class PostgreSqlTest extends \lithium\test\Unit {

	protected $_classes	= array(
		'adapter' => 'li3_sqltools\extensions\data\source\database\adapter\PostgreSql',
		'mock' => 'li3_sqltools\tests\mocks\data\source\database\adapter\MockPostgreSql'
	);

	protected $_dbConfig = array();

	public $db = null;

	public $dbmock = null;

	public function skip() {
		$this->skipIf(true, 'Unavailable tests for the PostgreSQL adapter');

		$adapter = $this->_classes['adapter'];
		$this->skipIf(!$adapter::enabled(), 'PostgreSQL Extension is not loaded');
		$this->_dbConfig = Connections::get('lithium_postgresql_test', array('config' => true));
		$hasDb = (isset($this->_dbConfig['adapter']) && $this->_dbConfig['adapter'] == 'PostgreSql');
		$message = 'Test database is either unavailable, or not using a PostgreSQL adapter';
		$this->skipIf(!$hasDb, $message);

		$adapter = $this->_classes['adapter'];
		$this->db = new $adapter($this->_dbConfig);
		$mock = $this->_classes['mock'];
		$this->dbmock = new $mock($this->_dbConfig);
	}


}

?>