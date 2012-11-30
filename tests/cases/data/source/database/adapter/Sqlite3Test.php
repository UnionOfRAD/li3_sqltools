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
			'name' => 'fieldname',
			'type' => 'string',
			'length' => 32,
			'null' => true
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" text(32) NULL';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'fieldname',
			'type' => 'string',
			'length' => 32,
			'null' => false,
			'collate' => 'NOCASE'
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" text(32) COLLATE \'NOCASE\' NOT NULL';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'fieldname',
			'type' => 'float',
			'length' => 10,
			'precision' => 2
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" numeric(10,2)';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'fieldname',
			'type' => 'text',
			'default' => 'value'
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" text DEFAULT \'value\'';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'fieldname',
			'type' => 'text',
			'default' => null
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" text';
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
		$expected = '"created" numeric NOT NULL DEFAULT CURRENT_TIMESTAMP';
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
			'name' => 'fieldname',
			'type' => 'integer',
			'length' => 11,
			'default' => 1
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" integer(11) DEFAULT 1';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'fieldname',
			'type' => 'integer',
			'length' => 11,
			'default' => '1'
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" integer(11) DEFAULT 1';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'fieldname',
			'type' => 'string',
			'length' => 64,
			'default' => 1
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" text(64) DEFAULT \'1\'';
		$this->assertEqual($expected, $result);

		$data = array(
			'name' => 'fieldname',
			'type' => 'text',
			'default' => 15
		);
		$result = $this->dbmock->buildColumn($data);
		$expected = '"fieldname" text DEFAULT \'15\'';
		$this->assertEqual($expected, $result);
	}

	public function testBuildColumnBadType() {
		$data = array(
			'name' => 'fieldname',
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
		$result = $this->dbmock->invokeMethod('_buildIndex', array($data, 'tablename'));
		$expected = array();
		$this->assertEqual($expected, $result);

		$data = array(
			'id' => array('column' => 'id', 'unique' => true)
		);
		$result = $this->dbmock->invokeMethod('_buildIndex', array($data, 'tablename'));
		$expected = array('CREATE UNIQUE INDEX "tablename_id" ON "tablename" ("id");');
		$this->assertEqual($expected, $result);

		$data = array(
			'myIndex' => array('column' => array('id', 'name'), 'unique' => true)
		);
		$result = $this->dbmock->invokeMethod('_buildIndex', array($data, 'tablename'));
		$expected = array('CREATE UNIQUE INDEX "tablename_myIndex" ON "tablename" ("id", "name");');
		$this->assertEqual($expected, $result);
	}

	public function testCreateSchema() {

		$schema = new Schema(array(
			'fields' => array(
				'id' => array('type' => 'integer', 'key' => 'primary'),
				'name' => array(
					'type' => 'string',
					'length' => 255,
					'null' => false,
					'comment' => 'comment'
				),
				'published' => array(
					'type' => 'datetime',
					'null' => false,
					'default' => (object) 'CURRENT_TIMESTAMP'
				),
				'decimal' => array(
					'type' => 'float',
					'length' => 10,
					'precision' => 2
				),
				'integer' => array(
					'type' => 'integer',
					'use' => 'numeric',
					'length' => 10,
					'precision' => 2
				),
				'date' => array(
					'type' => 'date',
					'null' => false,
				),
				'text' => array(
					'type' => 'text',
					'null' => false,
				)
			)
		));

		$result = $this->dbmock->dropSchema('test_table');
		$this->assertTrue($result);

		$expected = 'CREATE TABLE "test_table" (' . "\n";
		$expected .= '"id" integer PRIMARY KEY,' . "\n";
		$expected .= '"name" text(255) NOT NULL,' . "\n";
		$expected .= '"published" numeric NOT NULL DEFAULT CURRENT_TIMESTAMP,' . "\n";
		$expected .= '"decimal" numeric(10,2),' . "\n";
		$expected .= '"integer" numeric(10,2),' . "\n";
		$expected .= '"date" numeric NOT NULL,' . "\n";
		$expected .= '"text" text NOT NULL);';

		$result = $this->dbmock->createSchema('test_table', $schema);
		$this->assertEqual($expected, $result);

		$schema = new Schema(array(
			'fields' => array(
				'id' => array(
					'type' => 'integer', 'key' => 'primary'
				),
				'stringy' => array(
					'type' => 'string',
					'length' => 128,
					'null' => true,
					'collate' => 'BINARY',
				),
				'other_col' => array(
					'type' => 'string',
					'null' => false,
					'comment' => 'RTRIM'
				)
			),
			'meta' => array(
				'indexes' => array(
					'PRIMARY' => array('column' => 'id')
				)
			)
		));

		$result = $this->dbmock->dropSchema('test_table');
		$this->assertTrue($result);

		$expected = 'CREATE TABLE "test_table" (' . "\n";
		$expected .= '"id" integer PRIMARY KEY,' . "\n";
		$expected .= '"stringy" text(128) COLLATE \'BINARY\' NULL,' . "\n";
		$expected .= '"other_col" text(255) NOT NULL);';

		$result = $this->dbmock->createSchema('test_table', $schema);
		$this->assertEqual($expected, $result);
		$result = $this->dbmock->dropSchema('test_table');
	}
}

?>