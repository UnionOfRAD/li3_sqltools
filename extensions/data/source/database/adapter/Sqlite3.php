<?php
/**
 * Lithium: the most rad php framework
 *
 * @copyright     Copyright 2012, Union of RAD (http://union-of-rad.org)
 * @license       http://opensource.org/licenses/bsd-license.php The BSD License
 *
 */

namespace li3_sqltools\extensions\data\source\database\adapter;

use PDO;
use PDOException;

/**
 * Sqlite database driver
 *
 * @todo fix encoding methods to use class query methods instead of sqlite3 natives
 */
class Sqlite3 extends \li3_sqltools\extensions\data\source\Database {

	/**
	 * Sqlite3 column type definitions.
	 *
	 * @var array
	 */
	protected $_columns = array(
		'primary_key' => array('name' => 'primary key autoincrement'),
		'string' => array('name' => 'text', 'length' => 255),
		'text' => array('name' => 'text'),
		'integer' => array('name' => 'integer', 'formatter' => 'intval'),
		'float' => array('name' => 'real', 'formatter' => 'floatval'),
		'fixed' => array(
			'name' => 'numeric', 'formatter' => 'floatval', 'length' => 10, 'precision' => 2
		),
		'timestamp' => array(
			'name' => 'numeric', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'
		),
		'datetime' => array('name' => 'numeric', 'format' => 'Y-m-d H:i:s', 'formatter' => 'date'),
		'time' => array('name' => 'numeric', 'format' => 'H:i:s', 'formatter' => 'date'),
		'date' => array('name' => 'numeric', 'format' => 'Y-m-d', 'formatter' => 'date'),
		'binary' => array('name' => 'blob'),
		'boolean' => array('name' => 'numeric')
	);

	/**
	 * Field specific metas used on table creating
	 *
	 * @var array
	 */
	public $fieldMetas = array(
		'collate' => array(
			'value' => 'COLLATE',
			'quote' => false,
			'join' => ' ',
			'column' => 'Collate',
			'options' => array(
				'BINARY',
				'NOCASE',
				'RTRIM'
			),
			'position' => 'after'
		),
	);

	/**
	 * Pair of opening and closing quote characters used for quoting identifiers in queries.
	 *
	 * @link http://www.sqlite.org/lang_keywords.html
	 * @var array
	 */
	protected $_quotes = array('"', '"');


	/**
	 * Holds commonly regular expressions used in this class.
	 *
	 * @see lithium\data\source\database\adapter\Sqlite3::describe()
	 * @see lithium\data\source\database\adapter\Sqlite3::_column()
	 * @var array
	 */
	protected $_regex = array(
		'column' => '(?P<type>[^(]+)(?:\((?P<length>[^)]+)\))?'
	);

	/**
	 * Constructs the Sqlite adapter
	 *
	 * @see lithium\data\source\Database::__construct()
	 * @see lithium\data\Source::__construct()
	 * @see lithium\data\Connections::add()
	 * @param array $config Configuration options for this class. For additional configuration,
	 *        see `lithium\data\source\Database` and `lithium\data\Source`. Available options
	 *        defined by this class:
	 *        - `'database'` _string_: database name. Defaults to none
	 *        - `'flags'` _integer_: Optional flags used to determine how to open the SQLite
	 *          database. By default, open uses SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE.
	 *        - `'key'` _string_: An optional encryption key used when encrypting and decrypting
	 *          an SQLite database.
	 *
	 * Typically, these parameters are set in `Connections::add()`, when adding the adapter to the
	 * list of active connections.
	 */
	public function __construct(array $config = array()) {
		$defaults = array('database' => ':memory:', 'encoding' => null);
		parent::__construct($config + $defaults);
	}

	/**
	 * Check for required PHP extension, or supported database feature.
	 *
	 * @param string $feature Test for support for a specific feature, i.e. `'transactions'`.
	 * @return boolean Returns `true` if the particular feature (or if Sqlite) support is enabled,
	 *         otherwise `false`.
	 */
	public static function enabled($feature = null) {
		if (!$feature) {
			return extension_loaded('pdo_sqlite');
		}
		$features = array(
			'arrays' => false,
			'transactions' => false,
			'booleans' => true,
			'relationships' => true
		);
		return isset($features[$feature]) ? $features[$feature] : null;
	}

	/**
	 * Connects to the database using options provided to the class constructor.
	 *
	 * @return boolean True if the database could be connected, else false
	 */
	public function connect() {
		if (!$this->_config['database']) {
			throw new ConfigException('No Database configured');
		}

		if (empty($this->_config['dsn'])) {
			$this->_config['dsn'] = sprintf("sqlite:%s", $this->_config['database']);
		}

		return parent::connect();
	}

	/**
	 * Disconnects the adapter from the database.
	 *
	 * @return boolean True on success, else false.
	 */
	public function disconnect() {
		if ($this->_isConnected) {
			unset($this->connection);
			$this->_isConnected = false;
		}
		return true;
	}

	/**
	 * Returns the list of tables in the currently-connected database.
	 *
	 * @param string $model The fully-name-spaced class name of the model object making the request.
	 * @return array Returns an array of objects to which models can connect.
	 * @filter This method can be filtered.
	 */
	public function sources($model = null) {
		$config = $this->_config;

		return $this->_filter(__METHOD__, compact('model'), function($self, $params) use ($config) {
			$sql = "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;";
			$result = $self->invokeMethod('_execute', array($sql));
			$sources = array();

			while ($data = $result->next()) {
				$sources[] = reset($data);
			}
			return $sources;
		});
	}

	/**
	 * Gets the column schema for a given Sqlite3 table.
	 *
	 * A column type may not always be available, i.e. when during creation of
	 * the column no type was declared. Those columns are internally treated
	 * by SQLite3 as having a `NONE` affinity. The final schema will contain no
	 * information about type and length of such columns (both values will be
	 * `null`).
	 *
	 * @param mixed $entity Specifies the table name for which the schema should be returned, or
	 *        the class name of the model object requesting the schema, in which case the model
	 *        class will be queried for the correct table name.
	 * @param array $schema Any schema data pre-defined by the model.
	 * @param array $meta
	 * @return array Returns an associative array describing the given table's schema, where the
	 *         array keys are the available fields, and the values are arrays describing each
	 *         field, containing the following keys:
	 *         - `'type'`: The field type name
	 * @filter This method can be filtered.
	 */
	public function describe($entity, $schema = array(), array $meta = array()) {
		$params = compact('entity', 'meta');
		$regex = $this->_regex;
		return $this->_filter(__METHOD__, $params, function($self, $params) use ($regex) {
			$entity = $params['entity'];
			$meta = $params['meta'];

			$name = $self->invokeMethod('_entityName', array($entity, array('quoted' => true)));
			$columns = $self->read("PRAGMA table_info({$name})", array('return' => 'array'));
			$fields = array();
			print_r($columns);
			foreach ($columns as $column) {
				preg_match("/{$regex['column']}/", $column['type'], $matches);

				$fields[$column['name']] = array(
					'type' => isset($matches['type']) ? $matches['type'] : null,
					'length' => isset($matches['length']) ? $matches['length'] : null,
					'null' => $column['notnull'] == 1,
					'default' => $column['dflt_value']
				);
			}
			return $self->invokeMethod('_instance', array('schema', compact('fields')));
		});
	}

	/**
	 * Gets the last auto-generated ID from the query that inserted a new record.
	 *
	 * @param object $query The `Query` object associated with the query which generated
	 * @return mixed Returns the last inserted ID key for an auto-increment column or a column
	 *         bound to a sequence.
	 */
	protected function _insertId($query) {
		return $this->connection->lastInsertId();
	}

	/**
	 * Gets or sets the encoding for the connection.
	 *
	 * @param string $encoding If setting the encoding, this is the name of the encoding to set,
	 *               i.e. `'utf8'` or `'UTF-8'` (both formats are valid).
	 * @return mixed If setting the encoding; returns `true` on success, or `false` on
	 *         failure. When getting, returns the encoding as a string.
	 */
	public function encoding($encoding = null) {
		$encodingMap = array('UTF-8' => 'utf8');

		if (!$encoding) {
			$query = $this->connection->query('PRAGMA encoding');
			$encoding = $query->fetchColumn();
			return ($key = array_search($encoding, $encodingMap)) ? $key : $encoding;
		}
		$encoding = isset($encodingMap[$encoding]) ? $encodingMap[$encoding] : $encoding;

		try {
			$this->connection->exec("PRAGMA encoding = \"{$encoding}\"");
			return true;
		} catch (PDOException $e) {
			return false;
		}
	}

	/**
	 * In cases where the query is a raw string (as opposed to a `Query` object), to database must
	 * determine the correct column names from the result resource.
	 *
	 * @param mixed $query
	 * @param resource $resource
	 * @param object $context
	 * @return object
	 */
	public function schema($query, $resource = null, $context = null) {
		if (is_object($query)) {
			return parent::schema($query, $resource, $context);
		}

		$result = array();
		$count = $resource->resource()->columnCount();

		for ($i = 0; $i < $count; $i++) {
			$meta = $resource->resource()->getColumnMeta($i);
			$result[] = $meta['name'];
		}
		return $result;
	}

	/**
	 * Retrieves database error message and error code.
	 *
	 * @return array
	 */
	public function error() {
		if ($error = $this->connection->errorInfo()) {
			return array($error[1], $error[2]);
		}
	}

	/**
	 * Execute a given query.
	 *
	 * @see lithium\data\source\Database::renderCommand()
	 * @param string $sql The sql string to execute
	 * @param array $options No available options.
	 * @return resource
	 * @filter
	 */
	protected function _execute($sql, array $options = array()) {
		$conn = $this->connection;
		$params = compact('sql', 'options');
		return $this->_filter(__METHOD__, $params, function($self, $params) use ($conn) {
			$sql = $params['sql'];
			$options = $params['options'];
			try {
				$resource = $conn->query($sql);
			} catch(PDOException $e) {
				$self->invokeMethod('_error', array($sql));
			};
			return $self->invokeMethod('_instance', array('result', compact('resource')));
		});
	}

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
			$out .= "INDEX {$indexname} ON {$table}({$value['column']});";
			$join[] = $out;
		}
		return $join;
	}
}

?>