<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_sqltools\tests\mocks\data\source\database\adapter;

class MockSqlite3 extends \li3_sqltools\extensions\data\source\database\adapter\Sqlite3 {

	public function __construct(array $config = array()) {
		$this->connection = $this;
	}

	protected function _execute($sql) {
		return $sql;
	}

	public function quote($value) {
		return "'{$value}'";
	}

}

?>