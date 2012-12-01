# Database schema managment

## Requirement

PHP 5.4

## Installation

Checkout the code to either of your library directories:

	cd libraries
	git clone git@github.com:UnionOfRAD/li3_sqltools.git

Include the library in in your `/app/config/bootstrap/libraries.php`

	Libraries::add('li3_sqltools');

## Presentation

This plugin override the default database adapter to allow schema managment.

## API

Example of creating a schema:

	$schema = new Schema(array(
		'fields' => array(
			'id' => array('type' => 'integer', 'key' => 'primary'),
			'name' => array('type' => 'string','length' => 128,'null' => true),
			'price' => array('type' => 'integer'),
			'table_id' => array('type' => 'integer'),
			'created' => array('type' => 'datetime')
		),
		'meta' => array(
			'constraints' => array(
				array(
					'type' => 'foreign_key',
					'column' => 'table_id',
					'toColumn' => 'id',
					'to' => 'other_table',
					'on' => 'DELETE NO ACTION'
				),
				array(
					'type' => 'unique',
					'column' => 'name',
					'index' => true
				),
				array(
					'type' => 'check',
					'expr' => array(
						'price' => array('<' => 10)
					)
				)
			),
			'table' => array(
				'charset' => 'utf8',
				'collate' => 'utf8_unicode_ci',
				'engine' => 'InnoDB'
			)
		)
	));

	$db = Connections::get('default');
	$db->createSchema('mytable', $schema);

Example of droping a schema:

	$db = Connections::get('default');
	$db->dropSchema('mytable');

## API (core function)

Example of building a schema column:

	$data = array(
		'name' => 'created',
		'type' => 'datetime',
		'default' => (object) 'CURRENT_TIMESTAMP',
		'null' => false
	);

	$db = Connections::get('default');
	$result = $db->buildColumn($data); //'`created` timestamp DEFAULT CURRENT_TIMESTAMP' (MySQL)

Example of building a column meta:

	$db->buildMeta('column', 'collate', 'NOCASE'); //"'COLLATE 'NOCASE'" (Sqlite3)

Example of building a table meta :

	$db->buildMeta('column', 'engine', 'InnoDB'); //"ENGINE InnoDB" (MySql)

Example of building a constraint meta:

	$data = array('expr' => array('price' => array('<' => 10)))
	$db->buildMeta('constraint', 'check', $data); //"CHECK ((`integer` < 10))" (PostgreSQL/Sqlite3)

	$data = array('column' => 'name', 'index' => true);
	$db->buildMeta('constraint', 'unique', $data); //"UNIQUE INDEX (`name`)" (MySQL)

	$data = array('column' => 'name');
	$db->buildMeta('constraint', 'unique', $data); //"UNIQUE (`name`)" (PostgreSQL/Sqlite3)

Example of building a foreign key reference:

	$data = array('column' => 'table_id',
		'to' => 'other_table',
		'toColumn' => 'id',
		'on' => 'DELETE NO ACTION'
	);
	$db->buildMeta('constraint', 'foreign_key', $data);
	//Build the following string using MySql, PostgreSQL or Sqlite3:
	//"FOREIGN KEY (`table_id`) REFERENCES `other_table` (`id`) ON DELETE NO ACTION"
