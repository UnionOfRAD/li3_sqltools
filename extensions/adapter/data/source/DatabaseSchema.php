<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 */

namespace li3_sqltools\extensions\adapter\data\source;

use lithium\util\String;
use lithium\data\model\Query;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * The `DatabaseSchema` trait provides the base-level for schema management
 */
trait DatabaseSchema {

	/**
	 * Build a SQL column/table meta
	 *
	 * @param string $type The type of the meta to build (possible values: 'table' or 'column')
	 * @param string $name The name of the meta to build
	 * @param mixed $value The value used for building the meta
	 * @return string The SQL meta string
	 */
	protected function _meta($type, $name, $value) {
		$meta = isset($this->_metas[$type][$name]) ? $this->_metas[$type][$name] : null;
		if (!$meta || (isset($meta['options']) && !in_array($value, $meta['options']))) {
			return;
		}
		$meta += array('keyword' => '', 'escape' => false, 'join' => ' ');
		extract($meta);
		if ($escape === true) {
			$value = $this->value($value, array('type' => 'string'));
		}
		$result = $keyword . $join . $value;
		return $result !== ' ' ? $result : '';
	}

	/**
	 * Build a SQL column constraint
	 *
	 * @param string $name The name of the meta to build
	 * @param mixed $value The value used for building the meta
	 * @param object $schema A `Schema` instance.
	 * @return string The SQL meta string
	 */
	protected function _constraint($name, $value, $schema = null) {
		$value += array('options' => array());
		$meta = isset($this->_constraints[$name]) ? $this->_constraints[$name] : null;
		$template = isset($meta['template']) ? $meta['template'] : null;
		if (!$template) {
			return;
		}

		$data = array();
		foreach($value as $name => $value) {
			switch($name){
				case 'key':
				case 'index':
					if (isset($meta[$name])) {
						$data['index'] = $meta[$name];
					}
					break;
				case 'to':
					$data[$name] = $this->name($value);
					break;
				case 'on':
					$data[$name] = "ON {$value}";
					break;
				case 'expr':
					if (is_array($value)) {
						$result = array();
						$context = new Query(array('type' => 'none'));
						foreach ($value as $key => $val) {
							$return = $this->_processConditions($key, $val, $context, $schema);
							if ($return) {
								$result[] = $return;
							}
						}
						$data[$name] = join(" AND ", $result);
					} else {
						$data[$name] = $value;
					}
					break;
				case 'toColumn':
				case 'column';
					$data[$name] = join(', ', array_map(array($this, 'name'), (array) $value));
					break;
			}
		}

		return trim(String::insert($template, $data, array('clean' => array('method' => 'text'))));
	}

	/**
	 * Create a database-native schema
	 *
	 * @param string $name A table name.
	 * @param object $schema A `Schema` instance.
	 * @return boolean `true` on success, `true` otherwise
	 */
	public function createSchema($source, $schema) {

		if (!$schema instanceof $this->_classes['schema']) {
			throw new InvalidArgumentException("Passed schema is not a valid `{$class}` instance.");
		}

		$columns = array();
		$primary = null;

		$source = $this->name($source);

		foreach ($schema->fields() as $name => $field) {
			$field['name'] = $name;
			if (isset($field['key']) && $field['key'] === 'primary') {
				$primary = $name;
			}
			$columns[] = $this->column($field);
		}
		$columns = join(",\n", array_filter($columns));

		$metas = $schema->meta() + array('table' => array(), 'constraints' => array());

		$constraints = $this->_buildConstraints($metas['constraints'], $schema, ",\n", $primary);
		$table = $this->_buildMetas('table', $metas['table']);

		$params = compact('source', 'columns', 'constraints', 'table');
		return $this->_execute($this->renderCommand('schema', $params));
	}

	/**
	 * Helper for building columns metas
	 *
	 * @see DatabaseSchema::createSchema()
	 * @see DatabaseSchema::_column()
	 *
	 * @param array $metas The array of column metas.
	 * @param array $names If `$names` is not `null` only build meta present in `$names`
	 * @param type $joiner The join character
	 * @return string The SQL constraints
	 */
	protected function _buildMetas($type, array $metas, $names = null, $joiner = ' ') {
		$result = '';
		$names = $names ? (array) $names : array_keys($metas);
		foreach ($names as $name) {
			$value = isset($metas[$name]) ? $metas[$name] : null;
			if ($value && $meta = $this->_meta($type, $name, $value)) {
				$result .= $joiner . $meta;
			}
		}
		return $result;
	}

	/**
	 * Helper for building columns constraints
	 *
	 * @see DatabaseSchema::createSchema()
	 *
	 * @param array $constraints The array of constraints
	 * @param type $schema The schema of the table
	 * @param type $joiner The join character
	 * @return string The SQL constraints
	 */
	protected function _buildconstraints(array $constraints, $schema = null, $joiner = ' ', $primary = false) {
		$result = '';
		foreach($constraints as $constraint) {
			if (isset($constraint['type'])) {
				$name = $constraint['type'];
				if ($meta = $this->_constraint($name, $constraint, $schema)) {
					$result .= $joiner . $meta;
				}
				if ($name == 'primary') {
					$primary = false;
				}
			}
		}
		if ($primary) {
			$result .= $joiner . $this->_constraint('primary', array('column' => $primary));
		}
		return $result;
	}

	/**
	 * Drop a table
	 *
	 * @param string $name The table name to drop.
	 * @param boolean $soft With "soft dropping", the function will retrun `true` even if the
	 *                table doesn't exists.
	 * @return boolean `true` on success, `false` otherwise
	 */
	public function dropSchema($source, $soft = true) {
		if ($source) {
			$source = $this->name($source);
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
	public function column($field) {
		if (!isset($field['type'])) {
			$field['type'] = 'string';
		}

		if (!isset($field['name'])) {
			throw new InvalidArgumentException("Column name not defined.");
		}

		if (!isset($this->_columns[$field['type']])) {
			throw new UnexpectedValueException("Column type `{$field['type']}` does not exist.");
		}

		$field += $this->_columns[$field['type']];

		if (isset($field['key'])) {
			$field += $this->_columns[$field['key']];
		}

		$field += array(
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