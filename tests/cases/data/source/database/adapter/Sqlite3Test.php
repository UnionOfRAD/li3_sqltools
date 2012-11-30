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

class Sqlite3Test extends \lithium\test\Unit {

	protected $_classes	= array(
		'adapter' => 'li3_sqltools\extensions\data\source\database\adapter\Sqlite3',
		'mock' => 'li3_sqltools\tests\mocks\data\source\database\adapter\MockSqlite3'
	);

	protected $_dbConfig = array();

	public $db = null;

	public $dbmock = null;

	public function skip() {
		$adapter = $this->_classes['adapter'];
		$this->skipIf(!$adapter::enabled(), 'Sqlite3 Extension is not loaded');
		$this->_dbConfig = Connections::get('lithium_sqlite3_test', array('config' => true));
		$hasDb = (isset($this->_dbConfig['adapter']) && $this->_dbConfig['adapter'] == 'Sqlite3');
		$message = 'Test database is either unavailable, or not using a Sqlite3 adapter';
		$this->skipIf(!$hasDb, $message);

		$adapter = $this->_classes['adapter'];
		$this->db = new $adapter($this->_dbConfig);
		$mock = $this->_classes['mock'];
		$this->dbmock = new $mock($this->_dbConfig);
	}

	public function testBuildColumn() {
		$data = array(
			'name' => 'testName',
			'type' => 'string',
			'length' => 32,
			'null' => true
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"testName" text(32) DEFAULT NULL';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'testName',
			'type' => 'string',
			'length' => 32,
			'null' => false,
			'charset' => 'utf8',
			'collate' => 'utf8_unicode_ci'
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"testName" text(32) NOT NULL';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'testName',
			'type' => 'float',
			'length' => 10,
			'precision' => 2
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"testName" numeric(10,2)';
		$this->assertEqual($expected, $result);
	}

	public function testBuildColumnTime() {
		$data = array(
			'name' => 'created',
			'type' => 'datetime',
			'default' => (object) 'CURRENT_TIMESTAMP',
			'null' => false
 		);

		$result = $this->dbmock->buildColumn($data);
		$expected = '"created" numeric DEFAULT CURRENT_TIMESTAMP NOT NULL';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'created',
			'type' => 'datetime',
			'default' => (object) 'CURRENT_TIMESTAMP',
			'null' => true
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"created" numeric DEFAULT CURRENT_TIMESTAMP';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'modified',
			'type' => 'datetime',
			'null' => true
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"modified" numeric NULL';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'modified',
			'type' => 'datetime',
			'default' => null,
			'null' => true
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"modified" numeric NULL';
		$this->assertEqual($expected, $result);
	}

	public function testBuildColumnCast() {
		$data = array(
			'name' => 'testName',
			'type' => 'integer',
			'length' => 11,
			'default' => 1
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"testName" integer(11) DEFAULT 1';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'testName',
			'type' => 'integer',
			'length' => 11,
			'default' => '1'
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"testName" integer(11) DEFAULT 1';
		$this->assertEqual($expected, $result);
	}

	public function testBuildColumnBadType() {
		$data = array(
			'name' => 'testName',
			'type' => 'varchar(255)',
			'null' => true
		);
		$this->expectException('Column type `varchar(255)` does not exist.');
		$this->dbmock->buildColumn($data);
	}

	public function testBuildIndex() {
		$data = array(
			'PRIMARY' => array('column' => 'id')
		);
		$result = $this->dbmock->invokeMethod('_buildIndex', array($data));
		$expected = array();
		$this->assertEqual($expected, $result);

		$data = array(
			'id' => array('column' => 'id', 'unique' => true)
		);
		$result = $this->dbmock->invokeMethod('_buildIndex', array($data));
		$expected = array('CREATE UNIQUE INDEX "_id" ON ("id");');
		$this->assertEqual($expected, $result);

		$data = array(
			'myIndex' => array('column' => array('id', 'name'), 'unique' => true)
		);
		$result = $this->dbmock->invokeMethod('_buildIndex', array($data));
		$expected = array('CREATE UNIQUE INDEX "_myIndex" ON ("id", "name");');
		$this->assertEqual($expected, $result);
	}

	public function testCreateSchema() {
		$schema = new Schema(array(
			'fields' => array(
				'id' => array('type' => 'integer', 'key' => 'primary'),
				'stringy' => array(
					'type' => 'string',
					'length' => 128,
					'null' => true,
					'charset' => 'cp1250',
					'collate' => 'cp1250_general_ci',
				),
				'other_col' => array(
					'type' => 'string',
					'null' => false,
					'charset' => 'latin1',
					'comment' => 'Test Comment'
				)
			),
			'meta' => array(
				'indexes' => array('PRIMARY' => array('column' => 'id')),
				'tableMetas' => array(
					'charset' => 'utf8',
					'collate' => 'utf8_unicode_ci',
					'engine' => 'InnoDB'
				)
		)));

		$result = $this->dbmock->dropSchema('test_table');
		$this->assertTrue($result);

		$expected = 'CREATE TABLE "test_table" (' . "\n";
		$expected .= '"id" integer primary key autoincrement,' . "\n";
		$expected .= '"stringy" text(128) DEFAULT NULL,' . "\n";
		$expected .= '"other_col" text(255) NOT NULL);';

		$result = $this->dbmock->createSchema('test_table', $schema);
		$this->assertEqual($expected, $result);
		$result = $this->dbmock->dropSchema('test_table');
	}
}

?>