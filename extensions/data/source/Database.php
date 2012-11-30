<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_sqltools\extensions\data\source;

use UnexpectedValueException;
/**
 * The `Database` class provides the base-level abstraction for SQL-oriented relational databases.
 * It handles all aspects of abstraction, including formatting for basic query types and SQL
 * fragments (i.e. for joins), converting `Query` objects to SQL, and various other functionality
 * which is shared across multiple relational databases.
 *
 * @see lithium\data\model\Query
 */
abstract class Database extends \lithium\data\source\Database {

	protected $_classes = array(
		'entity' => 'lithium\data\entity\Record',
		'set' => 'lithium\data\collection\RecordSet',
		'relationship' => 'lithium\data\model\Relationship',
		'result' => 'lithium\data\source\database\adapter\pdo\Result',
		'schema' => 'lithium\data\Schema'
	);

	/**
	 * Field specific metas used on table creating
	 *
	 * @var array
	 */
	protected $_fieldMetas = array();
	/**
	 * Table specific metas used on table creating
	 *
	 * @var array
	 */
	protected $_tableMetas = array();
	/**
	 * Strings used to render the given statement
	 *
	 * @see LF\Data\Source\Database::renderCommand()
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
	 * Generate a database-native column schema string
	 *
	 * @param array $column An array structured like the following:
	 *              `array('name' => 'value', 'type' => 'value' [, options])`, where options can
	 *              be `'default'`, `'length'`, or `'key'`.
	 * @return string
	 */
	public function buildColumn($column) {
		$name = $type = null;
		extract(array_merge(array('null' => true), $column));

		if (empty($name) || empty($type)) {
			throw new InvalidArgumentException("Column name or type not defined in schema.");
			return null;
		}

		if (!isset($this->_columns[$type])) {
			throw new UnexpectedValueException("Column type `{$type}` does not exist.");
			return null;
		}

		$precision = '';
		if ($column['type'] === 'float' && isset($column['precision'])) {
			$type = 'fixed';
			$precision = ",{$column['precision']}";
		}

		unset($column['name']);
		$column += $this->_columns[$type];
		$out = $this->name($name) . ' ' . $column['name'];

		if (isset($column['length'])) {
			$length = $column['length'];
			$out .= "({$length}{$precision})";
		}

		if (($column['type'] === 'integer' || $column['type'] === 'float') && isset($column['default']) && $column['default'] === '') {
			$column['default'] = null;
		}

		$out = $this->_fieldMetas($out, $column, 'before');

		if (isset($column['key']) && $column['key'] === 'primary' && $type === 'integer') {
			$out .= ' ' . $this->_columns['primary_key']['name'];
		} elseif (isset($column['key']) && $column['key'] === 'primary') {
			$out .= ' NOT NULL';
		} elseif (isset($column['default']) && isset($column['null']) && $column['null'] === false) {
			$out .= ' DEFAULT ' . $this->value($column['default'], $column) . ' NOT NULL';
		} elseif (isset($column['default'])) {
			$out .= ' DEFAULT ' . $this->value($column['default'], $column);
		} elseif ($type !== 'datetime' && !empty($column['null'])) {
			$out .= ' DEFAULT NULL';
		} elseif ($type === 'datetime' && !empty($column['null'])) {
			$out .= ' NULL';
		} elseif (isset($column['null']) && $column['null'] === false) {
			$out .= ' NOT NULL';
		}

		return $this->_fieldMetas($out, $column, 'after');
	}

	/**
	 * Build field metas
	 *
	 * @param string $column The partially built column string
	 * @param array $data The array of column data.
	 * @param string $position The position type to use. 'beforeDefault' or 'afterDefault' are common
	 * @return string a built column with the field parameters added.
	 */
	protected function _fieldMetas($column, $data, $position) {
		foreach ($this->_fieldMetas as $key => $value) {
			if (isset($data[$key]) && $value['position'] == $position) {
				if (isset($value['options']) && !in_array($data[$key], $value['options'])) {
					continue;
				}
				$val = $data[$key];
				if ($value['quote']) {
					$val = $this->value($val, array('type' => 'string'));
				}
				$column .= ' ' . $value['value'] . $value['join'] . $val;
			}
		}
		return $column;
	}

	/**
	 * Build table metas
	 *
	 * @param array $metas
	 * @return array
	 */
	protected function _tableMetas($metas) {
		$result = array();
		foreach ($metas as $name => $value) {
			if (isset($this->_tableMetas[$name])) {
				$metas = &$this->_tableMetas[$name];
				if ($metas['quote']) {
					$value = $this->value($value, array('type' => 'string'));
				}
				$result[] = $metas['value']. $metas['join'] . $value;
			}
		}
		return $result;
	}

	/**
	 * Create a database-native schema
	 *
	 * @param Model $schema An instance of a schema.
	 * @param string $source A table name.
	 * @return boolean `true` on success, `true` otherwise
	 */
	public function createSchema($source, $schema) {
		$class = $this->_classes['schema'];
		if (!$schema instanceof $class) {
			throw new InvalidArgumentException("Passed schema is not a valid `{$class}` instance.");
		}

		$columns = $indexes = $tableMetas = array();
		$primary = null;
		$source = $this->name($source);

		foreach ($schema->fields() as $name => $col) {
			if (is_string($col)) {
				$col = array('type' => $col);
			}
			if (isset($col['key']) && $col['key'] === 'primary') {
				$primary = $name;
			}
			$col['name'] = $name;
			if (!isset($col['type'])) {
				$col['type'] = 'string';
			}
			$columns[] = $this->buildColumn($col);
		}
		foreach ($schema->meta() as $name => $col) {
			if ($name === 'indexes') {
				$indexes = array_merge($indexes, $this->_buildIndex($col, $source));
			} elseif ($name === 'tableMetas') {
				$tableMetas = array_merge($tableMetas, $this->_tableMetas($col));
			}
		}
		if (empty($indexes) && !empty($primary)) {
			$col = array('PRIMARY' => array('column' => $primary, 'unique' => 1));
			$indexes = array_merge($indexes, $this->_buildIndex($col, $source));
		}

		foreach (array('columns', 'indexes', 'tableMetas') as $var) {
			${$var} = join(",\n", array_filter(${$var}));
		}

		if (trim($tableMetas) !== '') {
			$tableMetas = "\n" . $tableMetas;
		}

		if (trim($indexes) !== '') {
			$columns .= ',';
		}

		$params = compact('source', 'columns', 'indexes', 'tableMetas');
		return $this->_execute($this->renderCommand('schema', $params));
	}

	/**
	 * Drop a table
	 *
	 * @param string $source The table name to drop.
	 * @param boolean $soft With "soft dropping", the function will retrun `true` even if the
	 *                table doesn't exists.
	 * @return boolean `true` on success, `false` otherwise
	 */
	public function dropSchema($source, $soft = true) {
		if ($source = $this->name($source)) {
			$exists = $soft ? 'IF EXISTS ' : '';
			return $this->_execute($this->renderCommand('drop', compact('exists', 'source')));
		}
		return false;
	}
}

?>