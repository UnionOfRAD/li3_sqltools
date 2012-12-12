<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 *
 */

namespace li3_sqltools\extensions\adapter\data\source\database;

use li3_sqltools\extensions\adapter\data\source\DatabaseSchema;

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
		'schema' => "CREATE TABLE {:source} (\n{:columns}{:constraints}){:table};{:comment}",
		'drop'   => "DROP TABLE {:exists}{:source};"
	);

	/**
	 * Sqlite3 column type definitions.
	 *
	 * @var array
	 */
	protected $_columns = array(
		'id' => array('use' => 'integer'),
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
	 * Column specific metas used on table creating
	 * By default `'quote'` is false and 'join' is `' '`
	 *
	 * @var array
	 */
	protected $_metas = array(
		'column' => array(
			'collate' => array('keyword' => 'COLLATE', 'escape' => true)
		)
	);
	/**
	 * Column contraints
	 *
	 * @var array
	 */
	protected $_constraints = array(
		'primary' => array('template' => 'PRIMARY KEY ({:column})'),
		'foreign_key' => array(
			'template' => 'FOREIGN KEY ({:column}) REFERENCES {:to} ({:toColumn}) {:on}'
		),
		'unique' => array(
			'template' => 'UNIQUE {:index} ({:column})'
		),
		'check' => array('template' => 'CHECK ({:expr})')
	);

	/**
	 * Helper for `DatabaseSchema::_column()`
	 *
	 * @param array $field A field array
	 * @return string SQL column string
	 */
	protected function _buildColumn($field) {
		extract($field);
		if ($type === 'float' && $precision) {
			$use = 'numeric';
		}

		$out = $this->name($name) . ' ' . $use;

		$allowPrecision = preg_match('/^(integer|real|numeric)$/',$use);
		$precision = ($precision && $allowPrecision) ? ",{$precision}" : '';

		if ($length && ($allowPrecision || $use === 'text')) {
			$out .= "({$length}{$precision})";
		}

		$out .= $this->_buildMetas('column', $field, array('collate'));

		if ($type !== 'id') {
			$out .= is_bool($null) ? ($null ? ' NULL' : ' NOT NULL') : '' ;
			$out .= $default ? ' DEFAULT ' . $this->value($default, $field) : '';
		}

		return $out;
	}
}

?>