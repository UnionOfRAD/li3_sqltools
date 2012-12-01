<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_sqltools\extensions\data\source;

use InvalidArgumentException;
use UnexpectedValueException;

/**
 * The `DatabaseSchema` trait provides the base-level for schema management
 */
trait DatabaseSchema {

	/**
	 * Build a column meta
	 *
	 * @param array $name The name of a meta
	 * @param string $value The value of the meta
	 * @return string a built column parameters.
	 */
	public function columnMeta($name, $value) {
		$meta = isset($this->_columnMetas[$name]) ? $this->_columnMetas[$name] : null;
		if (!$meta || (isset($meta['options']) && !in_array($value, $meta['options']))) {
			return;
		}
		return $this->buildMeta($meta + compact('value'));
	}

	/**
	 * Build a column meta
	 *
	 * @param array $name The name of a meta
	 * @param string $value The value of the meta
	 * @return string a built column parameters.
	 */
	public function tableMeta($name, $value) {
		$meta = isset($this->_tableMetas[$name]) ? $this->_tableMetas[$name] : null;
		if (!$meta || (isset($meta['options']) && !in_array($value, $meta['options']))) {
			return;
		}
		return $this->buildMeta($meta + compact('value'));
	}

	/**
	 * Build a meta
	 * @param type $meta
	 * @return string
	 */
	public function buildMeta($meta) {
		extract($meta);
		if ($quote === true) {
			$value = $this->value($value, array('type' => 'string'));
		} elseif ($quote) {
			$value = $quote .$value . $quote;
		}
		return $keyword . $join . $value;
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

		foreach ($schema->fields() as $name => $field) {
			if (is_string($field)) {
				$field = array('type' => $field);
			}
			if (isset($field['key']) && $field['key'] === 'primary') {
				$primary = $name;
			}
			$field['name'] = $name;
			$columns[] = $this->buildColumn($field);
		}

		$metas = $schema->meta();

		if ($primary) {
			$meta = array('PRIMARY' => array('column' => $primary, 'unique' => true));
			$metas['indexes'] = isset($metas['indexes']) ? $metas['indexes'] + $meta : $meta;
		}

		foreach ($metas as $name => $col) {
			if ($name === 'indexes') {
				$indexes = $this->_buildIndex($col, $source);
			} elseif ($name === 'table') {
				foreach ($col as $key => $value) {
					if (isset($this->_tableMetas[$key])) {
						$tableMetas[] = $this->buildMeta($this->_tableMetas[$key] + compact('value'));
					}
				}
			}
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
	 * Build column metas
	 *
	 * @param array $metas The array of column metas.
	 * @param string $position The position type to use. 'before' or 'after' are common
	 * @return string a built column parameters.
	 */
	protected function _columnMetas(array $metas, $position = null) {
		$result = '';
		foreach ($metas as $key => $value) {
			$meta = isset($this->_columnMetas[$key]) ? $this->_columnMetas[$key] : null;
			if ($meta && ($meta['position'] == $position || !$position)) {
				$result .= ' ' . $this->columnMeta($key, $value);
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
	protected function _tableMetas(array $metas) {
		$result = array();
		foreach ($metas as $name => $value) {
			if (isset($this->_tableMetas[$name])) {
				$result[] = $this->buildMeta($this->_tableMetas[$name] + compact('value'));
			}
		}
		return $result;
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
		if (!isset($field['type'])) {
			$field['type'] = 'string';
		}

		if (!isset($field['name'])) {
			throw new InvalidArgumentException("Column name not defined.");
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