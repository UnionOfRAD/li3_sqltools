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
 * The `DatabaseSchema` trait provides the base-level for schema management
 */
trait DatabaseSchema {

	/**
	 * Build field metas
	 *
	 * @param array $data The array of column data.
	 * @param string $position The position type to use. 'before' or 'after' are common
	 * @return string a built column parameters.
	 */
	protected function _fieldMetas($data, $position) {
		$result = '';
		foreach ($this->_fieldMetas as $key => $value) {
			if (isset($data[$key]) && $value['position'] == $position) {
				if (isset($value['options']) && !in_array($data[$key], $value['options'])) {
					continue;
				}
				$val = $data[$key];
				if ($value['quote']) {
					$val = $this->value($val, array('type' => 'string'));
				}
				$result .= ' ' . $value['value'] . $value['join'] . $val;
			}
		}
		return $result;
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
				$metas = $this->_tableMetas[$name];
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

	/**
	 * Generate a database-native column schema string
	 *
	 * @param array $column A field array structured like the following:
	 *        `array('name' => 'value', 'type' => 'value' [, options])`, where options can
	 *        be `'default'`, `'null'`, `'length'`, `'key'` or `'precision'`.
	 * @return string SQL string
	 */
	public function buildColumn($field) {
		if (!isset($field['name']) || !isset($field['type'])) {
			throw new InvalidArgumentException("Column name or type not defined in schema.");
		}

		if (!isset($this->_columns[$field['type']])) {
			throw new UnexpectedValueException("Column type `{$field['type']}` does not exist.");
		}

		$field += $this->_columns[$field['type']] + array(
			'name' => null,
			'key' => null,
			'type' => null,
			'length' => null,
			'precision' => null,
			'default' => null,
			'null' => null
		);

		$isNumeric = preg_match('/^(integer|float|boolean)$/', $field['type']);
		if ($isNumeric && $field['default'] === '') {
			$field['default'] = null;
		}
		$field['use'] = strtolower($field['use']);
		return $this->_buildColumn($field);
	}
}

?>