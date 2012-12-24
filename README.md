# Database schema managment

## Requirement

PHP 5.4

## Installation

Checkout the code to either of your library directories:

```
cd libraries
git clone git@github.com:UnionOfRAD/li3_sqltools.git
```

Include the library in your `/app/config/bootstrap/libraries.php`

	Libraries::add('li3_sqltools');

## Presentation

This plugin override the default database adapters to allow schema managment (i.e. create and drop table).

## API

Simple schema creation:

```php
$db = Connections::get('default');
$db->createSchema('mytable', new Schema(array(
	'fields' => array(
		'id' => array('type' => 'id'),
		'name' => array('type' => 'string')
	)
)));
```
A more complexe example with constraints:

```php
$schema = new Schema(array(
	'fields' => array(
		'id' => array('type' => 'id'),
		'name' => array('type' => 'string', 'length' => 128, 'null' => true),
		'big' => array('type' => 'int', 'use' => 'bigint'),
		'price' => array('type' => 'float', 'length' => 10, 'precision' => 2),
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
```

Example of dropping a schema:

```php
$db = Connections::get('default');
$db->dropSchema('mytable');
```

## Build status
[![Build Status](https://secure.travis-ci.org/UnionOfRAD/li3_sqltools.png?branch=master)](http://travis-ci.org/UnionOfRAD/li3_sqltools)