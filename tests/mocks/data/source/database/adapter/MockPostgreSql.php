<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD,http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_sqltools\tests\mocks\data\source\database\adapter;

class MockPostgreSQL extends \li3_sqltools\extensions\data\source\database\adapter\PostgreSQL {

	protected function _execute($sql) {
		return $sql;
	}

}

?>
