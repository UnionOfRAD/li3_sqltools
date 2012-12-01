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
		'schema' => "CREATE TABLE {:source} (\n{:columns}{:constraints}){:table};{:comment}",
		'drop'   => "DROP TABLE {:exists}{:source};"
	);

	/**
	 * MySQL column type definitions.
	 *
	 * @var array
	 */
	protected $_columns = array(
		'primary' => array('use' => 'int', 'length' => 11, 'increment' => true),
		'string' => array('use' => 'varchar', 'length' => 255),
		'text' => array('use' => 'text'),
		'integer' => array('use' => 'int', 'length' => 11, 'formatter' => 'intval'),
		'float' => array('use' => 'float', 'formatter' => 'floatval'),
		'datetime' => array('use' => 'timestamp', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
		'time' => array('use' => 'time', 'format' => 'H:i:s', 'formatter' => 'date'),
		'date' => array('use' => 'date', 'format' => 'Y-m-d', 'formatter' => 'date'),
		'binary' => array('use' => 'blob'),
		'boolean' => array('use' => 'tinyint', 'length' => 1)
	);
	/**
	 * Meta atrribute syntax
	 * By default `'escape'` is false and 'join' is `' '`
	 *
	 * @var array
	 */
	protected $_metas = array(
		'column' => array(
			'charset' => array('keyword' => 'CHARACTER SET'),
			'collate' => array('keyword' => 'COLLATE'),
			'comment' => array('keyword' => 'COMMENT', 'escape' => true)
		),
		'table' => array(
			'charset' => array('keyword' => 'DEFAULT CHARSET'),
			'collate' => array('keyword' => 'COLLATE'),
			'engine' => array('keyword' => 'ENGINE'),
			'tablespace' => array('keyword' => 'TABLESPACE')
		),
		'constraint' => array(
			'primary' => array('template' => 'PRIMARY KEY ({:column})'),
			'foreign_key' => array(
				'template' => 'FOREIGN KEY ({:column}) REFERENCES {:to} ({:toColumn}) {:on}'
			),
			'index' => array('template' => 'INDEX ({:column})'),
			'unique' => array(
				'template' => 'UNIQUE {:index} ({:column})',
				'key' => 'KEY',
				'index' => 'INDEX'
			),
			'check' => array('template' => 'CHECK ({:expr})')
		)
	);

	/**
	 * Helper for `DatabaseSchema::buildColumn()`
	 *
	 * @param array $field A field array
	 * @return string The SQL column string
	 */
	protected function _buildColumn($field) {
		extract($field);
		if ($type === 'float' && $precision) {
			$use = 'decimal';
		}

		$out = $this->name($name) . ' ' . $use;

		$allowPrecision = preg_match('/^(decimal|float|double|real|numeric)$/',$use);
		$precision = ($precision && $allowPrecision) ? ",{$precision}" : '';

		if ($length && ($allowPrecision || preg_match('/(char|binary|int|year|timestamp)/',$use))) {
			$out .= "({$length}{$precision})";
		}

		$out .= $this->_buildMetas('column', $field, array('charset', 'collate'));

		if ($key === 'primary' && $use === 'int' && $increment) {
			$out .= ' NOT NULL AUTO_INCREMENT';
		} else {
			$out .= is_bool($null) ? ($null ? ' NULL' : ' NOT NULL') : '' ;
			$out .= $default ? ' DEFAULT ' . $this->value($default, $field) : '';
		}

		return $out . $this->_buildMetas('column', $field, array('comment'));
	}
}

?>