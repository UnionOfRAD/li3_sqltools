<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright	  Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license		  http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_sqltools\extensions\data\source\database\adapter;

use li3_sqltools\extensions\data\source\DatabaseSchema;

/**
 * Extends the `Database` class to implement the necessary SQL-formatting and resultset-fetching
 * features for working with PostgreSQL databases.
 *
 * For more information on configuring the database connection, see the `__construct()` method.
 *
 * @see lithium\data\source\database\adapter\PostgreSql::__construct()
 */
class PostgreSql extends \lithium\data\source\database\adapter\PostgreSql {

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
	 * Table specific metas used on table creating
	 *
	 * @var array
	 */
	protected $_tableMetas = array(
		'tablespace' => array(
			'keyword' => 'TABLESPACE',
			'quote' => false,
			'join' => ' '
		)
	);

	/**
	 * PostgreSQL column type definitions.
	 *
	 * @var array
	 */
	protected $_columns = array(
		'primary_key' => array('use' => 'serial NOT NULL'),
		'string' => array('use' => 'varchar', 'length' => 255),
		'text' => array('use' => 'text'),
		'integer' => array('use' => 'integer', 'formatter' => 'intval'),
		'float' => array('use' => 'float', 'formatter' => 'floatval'),
		'datetime' => array('use' => 'timestamp', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
		'time' => array('use' => 'time', 'format' => 'H:i:s', 'formatter' => 'date'),
		'date' => array('use' => 'date', 'format' => 'Y-m-d', 'formatter' => 'date'),
		'binary' => array('use' => 'bytea'),
		'boolean' => array('use' => 'boolean'),
		'number' => array('use' => 'numeric'),
		'inet' => array('use' => 'inet')
	);

	/**
	 * Build indexes for create table
	 *
	 * @param array $indexes Indexes
	 * @param string $table Table name
	 * @return string
	 */
	protected function _buildIndex(array $indexes, $table = null) {
		$join = array();
		foreach ($indexes as $name => $value) {
			if ($name == 'PRIMARY') {
				$out = 'PRIMARY KEY (' . $this->name($value['column']) . ')';
			} else {
				$out = 'CREATE ';
				if (!empty($value['unique'])) {
					$out .= 'UNIQUE ';
				}
				if (is_array($value['column'])) {
					$column = array_map(array(&$this, 'name'), $value['column']);
					$value['column'] = implode(', ', $column);
				} else {
					$value['column'] = $this->name($value['column']);
				}
				$out .= "INDEX {$name} ON {$table} ({$value['column']});";
			}
			$join[] = $out;
		}
		return $join;
	}

	protected function schemaConstraint($source, $name, array $value) {
		$out = 'CREATE ';
		if (!empty($value['unique'])) {
			$out .= 'UNIQUE ';
		}
		if (is_array($value['column'])) {
			$column = array_map(array($this, 'name'), $value['column']);
			$value['column'] = implode(', ', $column);
		} else {
			$value['column'] = $this->name($value['column']);
		}
		$out .= "INDEX {$name} ON {$source} ({$value['column']});";
	}

	/**
	 * Helper for `DatabaseSchema::buildColumn()`
	 * @param array $field A field array
	 * @return string SQL column string
	 */
	protected function _buildColumn($field) {
		extract($field);
		if ($type === 'float' && $precision) {
			$use = 'numeric';
		}

		if ($precision) {
			$precision = $use === 'numeric' ? ",{$precision}" : '';
		}

		if ($key === 'primary' && $type === 'integer') {
			$out = $this->name($name) . ' ' . $this->_columns['primary_key']['use'];
		} else {
			$out = $this->name($name) . ' ' . $use;

			if ($length && preg_match('/char|numeric|interval|bit|time/',$use)) {
				$out .= "({$length}{$precision})";
			}

			if (isset($default) && $null === false) {
				$out .= ' NOT NULL DEFAULT ' . $this->value($default, $field);
			} elseif (isset($default)) {
				$out .= ' DEFAULT ' . $this->value($default, $field);
			} elseif ($use !== 'timestamp' && $null) {
				$out .= ' DEFAULT NULL';
			} elseif ($use === 'timestamp' && $null) {
				$out .= ' NULL';
			} elseif ($null === false) {
				$out .= ' NOT NULL';
			}
		}

		return $out;
	}
}

?>