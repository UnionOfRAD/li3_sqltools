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

