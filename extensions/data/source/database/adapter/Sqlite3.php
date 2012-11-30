<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 *
 */

namespace li3_sqltools\extensions\data\source\database\adapter;

use li3_sqltools\extensions\data\source\DatabaseSchema;

/**
 * Sqlite database driver
 *
 * @todo fix encoding methods to use class query methods instead of sqlite3 natives
 */
class Sqlite3 extends \lithium\data\source\database\adapter\Sqlite3 {

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
	 * Sqlite3 column type definitions.
	 *
	 * @var array
	 */
	protected $_columns = array(
		'primary_key' => array('use' => 'PRIMARY KEY'),
		'string' => array('use' => 'text', 'length' => 255),
		'text' => array('use' => 'text'),
		'integer' => array('use' => 'integer', 'formatter' => 'intval'),
		'float' => array('use' => 'real', 'formatter' => 'floatval'),
		'datetime' => array('use' => 'numeric', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
		'time' => array('use' => 'numeric', 'format' => 'H:i:s', 'formatter' => 'date'),
		'date' => array('use' => 'numeric', 'format' => 'Y-m-d', 'formatter' => 'date'),
		'binary' => array('use' => 'blob'),
		'boolean' => array('use' => 'numeric', 'length' => 1)
	);

	/**
	 * Field specific metas used on table creating
	 *
	 * @var array
	 */
	protected $_fieldMetas = array(
		'collate' => array(
			'value' => 'COLLATE',
			'quote' => "'",
			'join' => ' ',
			'column' => 'Collate',
			'position' => 'before'
		),
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

		$table = str_replace('"', '', $table);

		foreach ($indexes as $name => $value) {

			if ($name == 'PRIMARY') {
				continue;
			}
			$out = 'CREATE ';

			if (!empty($value['unique'])) {
				$out .= 'UNIQUE ';
			}
			if (is_array($value['column'])) {
				$value['column'] = join(', ', array_map(array(&$this, 'name'), $value['column']));
			} else {
				$value['column'] = $this->name($value['column']);
			}
			$t = trim($table, '"');
			$indexname = $this->name($t . '_' . $name);
			$table = $this->name($table);
			$out .= "INDEX {$indexname} ON {$table} ({$value['column']});";
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
			$use = 'numeric';
		}

		if ($precision) {
			$precision = preg_match('/integer|real|numeric/',$use) ? ",{$precision}" : '';
		}
		$out = $this->name($name) . ' ' . $use;

		if ($length && preg_match('/integer|real|numeric|text/',$use)) {
			$out .= "({$length}{$precision})";
		}

		$out .= $this->_fieldMetas($field, 'before');

		if ($key === 'primary' && $use === 'integer') {
			$out .= ' ' . $this->_columns['primary_key']['use'];
		} elseif ($key === 'primary') {
			$out .= ' NOT NULL';
		} elseif (isset($default) && $null === false) {
			$out .= ' NOT NULL DEFAULT ' . $this->value($default, $field);
		} elseif (isset($default)) {
			$out .= ' DEFAULT ' . $this->value($default, $field);
		} elseif ($null) {
			$out .= ' NULL';
		} elseif ($null === false) {
			$out .= ' NOT NULL';
		}

		return $out;
	}
}

?>