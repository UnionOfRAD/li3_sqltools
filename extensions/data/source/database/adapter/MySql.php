<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_sqltools\extensions\data\source\database\adapter;

use li3_sqltools\extensions\data\source\DatabaseSchema;

/**
 * Extends the `Database` class to implement the necessary SQL-formatting and resultset-fetching
 * features for working with MySQL databases.
 *
 * For more information on configuring the database connection, see the `__construct()` method.
 *
 * @see lithium\data\source\database\adapter\MySql::__construct()
 */
class MySql extends \lithium\data\source\database\adapter\MySql {

	use DatabaseSchema;

	/**
	 * Strings used to render the given statement
	 *
	 * @see lithium\data\source\Database::renderCommand()
	 * @var array
	 */
	protected $_strings = array(
		'create' => "INSERT INTO {:source} ({:fields}) VALUES ({:values});{:comment}",
		'update' => "UPDATE {:source} SET {:fields} {:conditions};{:comment}",
		'delete' => "DELETE {:flags} FROM {:source} {:conditions};{:comment}",
		'join' => "{:type} JOIN {:source} {:alias} {:constraints}",
		'schema' => "CREATE TABLE {:source} (\n{:columns}\n{:indexes}){:tableMetas};{:comment}",
		'drop'   => "DROP TABLE {:exists}{:source};"
	);

	/**
	 * MySQL column type definitions.
	 *
	 * @var array
	 */
	protected $_columns = array(
		'primary_key' => array('use' => 'NOT NULL AUTO_INCREMENT'),
		'string' => array('use' => 'varchar', 'length' => 255),
		'text' => array('use' => 'text'),
		'integer' => array('use' => 'int', 'length' => 11, 'formatter' => 'intval'),
		'float' => array('use' => 'float', 'formatter' => 'floatval'),
		'datetime' => array('use' => 'datetime', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
		'time' => array('use' => 'time', 'format' => 'H:i:s', 'formatter' => 'date'),
		'date' => array('use' => 'date', 'format' => 'Y-m-d', 'formatter' => 'date'),
		'binary' => array('use' => 'blob'),
		'boolean' => array('use' => 'tinyint', 'length' => 1)
	);
	/**
	 * Column specific metas used on table creating
	 *
	 * @var array
	 */
	protected $_columnMetas = array(
		'charset' => array(
			'keyword' => 'CHARACTER SET',
			'quote' => false,
			'join' => ' ',
			'position' => 'before'
		),
		'collate' => array(
			'keyword' => 'COLLATE',
			'quote' => false,
			'join' => ' ',
			'position' => 'before'
		),
		'comment' => array(
			'keyword' => 'COMMENT',
			'quote' => true,
			'join' => ' ',
			'position' => 'after'
		)
	);
	/**
	 * Table specific metas used on table creating
	 *
	 * @var array
	 */
	protected $_tableMetas = array(
		'charset' => array(
			'keyword' => 'DEFAULT CHARSET',
			'quote' => false,
			'join' => '='
		),
		'collate' => array(
			'keyword' => 'COLLATE',
			'quote' => false,
			'join' => '='
		),
		'engine' => array(
			'keyword' => 'ENGINE',
			'quote' => false,
			'join' => '='
		)
	);

	/**
	 * Build indexes for create table
	 *
	 * @param array $indexes Indexes
	 * @param string $table Table name
	 * @return string
	 */
	protected function _buildIndex($indexes, $table = null) {
		$join = array();
		foreach ($indexes as $name => $value) {
			$out = '';
			if ($name === 'PRIMARY') {
				$out .= $name;
				$name = '';
			} else {
				if (!empty($value['unique'])) {
					$out .= 'UNIQUE';
				}
				$name = $this->_quotes[0] . $name . $this->_quotes[1] . ' ';
			}
			if (is_array($value['column'])) {
				$column = array_map(array($this, 'name'), $value['column']);
				$out .= ' KEY ' . $name . '(' . implode(', ', $column) . ')';
			} else {
				$out .= ' KEY ' . $name . '(' . $this->name($value['column']) . ')';
			}
			$join[] = $out;
		}
		return $join;
	}

	/**
	 * Helper for `DatabaseSchema::buildColumn()`
	 * @param array $field A field array
	 * @return string SQL column string
	 */
	protected function _buildColumn($field) {
		extract($field);
		if ($type === 'float' && $precision) {
			$use = 'decimal';
		}

		if ($precision) {
			$precision = preg_match('/decimal|float|double/',$use) ? ",{$precision}" : '';
		}

		$out = $this->name($name) . ' ' . $use;

		if ($length && preg_match('/char|decimal|int|float|double|year|timestamp/',$use)) {
			$out .= "({$length}{$precision})";
		}

		$out .= $this->_columnMetas($field, 'before');

		if ($key === 'primary' && $use === 'int') {
			$out .= ' ' . $this->_columns['primary_key']['use'];
		} elseif ($key === 'primary') {
			$out .= ' NOT NULL';
		} elseif (isset($default) && $null === false) {
			$out .= ' NOT NULL DEFAULT ' . $this->value($default, $field);
		} elseif (isset($default)) {
			$out .= ' DEFAULT ' . $this->value($default, $field);
		} elseif ($use !== 'datetime' && $null) {
			$out .= ' DEFAULT NULL';
		} elseif ($use === 'datetime' && $null) {
			$out .= ' NULL';
		} elseif ($null === false) {
			$out .= ' NOT NULL';
		}

		return $out . $this->_columnMetas($field, 'after');
	}
}

?>