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
		'schema' => "CREATE TABLE {:source} (\n{:columns}{:constraints}){:table};{:comment}",
		'drop'   => "DROP TABLE {:exists}{:source};"
	);

	/**
	 * PostgreSQL column type definitions.
	 *
	 * @var array
	 */
	protected $_columns = array(
		'primary' => array('use' => 'integer', 'increment' => true),
		'string' => array('use' => 'varchar', 'length' => 255),
		'text' => array('use' => 'text'),
		'integer' => array('use' => 'integer', 'formatter' => 'intval'),
		'float' => array('use' => 'real', 'formatter' => 'floatval'),
		'datetime' => array('use' => 'timestamp', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
		'time' => array('use' => 'time', 'format' => 'H:i:s', 'formatter' => 'date'),
		'date' => array('use' => 'date', 'format' => 'Y-m-d', 'formatter' => 'date'),
		'binary' => array('use' => 'bytea'),
		'boolean' => array('use' => 'boolean'),
		'inet' => array('use' => 'inet')
	);

	/**
	 * Table specific metas used on table creating
	 * By default `'escape'` is false and 'join' is `' '`
	 *
	 * @var array
	 */
	protected $_metas = array(
		'table' => array(
			'tablespace' => array('keyword' => 'TABLESPACE')
		),
		'constraint' => array(
			'primary' => array('template' => 'PRIMARY KEY ({:column})'),
			'foreign_key' => array(
				'template' => 'FOREIGN KEY ({:column}) REFERENCES {:to} ({:toColumn}) {:on}'
			),
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

		$out = $this->name($name);

		if ($key === 'primary' && $type === 'integer' && $increment) {
			$out .= ' serial NOT NULL';
		} else {
			$out .= ' ' . $use;

			if ($length && preg_match('/char|numeric|interval|bit|time/',$use)) {
				$out .= "({$length}{$precision})";
			}

			$out .= is_bool($null) ? ($null ? ' NULL' : ' NOT NULL') : '' ;
			$out .= $default ? ' DEFAULT ' . $this->value($default, $field) : '';
		}

		return $out;
	}
}

?>