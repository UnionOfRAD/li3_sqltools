# Database schema managment

## Installation

Checkout the code to either of your library directories:

	cd libraries
	git clone git@github.com:UnionOfRAD/li3_sqltools.git

Include the library in in your `/app/config/bootstrap/libraries.php`

	Libraries::add('li3_sqltools');

## Presentation

This plugin override the default database adapter to allow schema managment.

## API

Example of creating a schema :

	$schema = new Schema(array(
		'fields' => array(
			'id' => array('type' => 'integer', 'key' => 'primary'),
			'name' => array('type' => 'string','length' => 128,'null' => true),
			'created' => array('type' => 'datetime')
		),
		'meta' => array(
			'indexes' => array('PRIMARY' => array('column' => 'id'))
	)));

	$db = Connections::get('default');
	$db->createSchema('mytable', $schema);

Example of droping a schema :

	$db = Connections::get('default');
	$db->dropSchema('mytable');

Example of building a schema column :

	$data = array(
		'name' => 'created',
		'type' => 'datetime',
		'default' => (object) 'CURRENT_TIMESTAMP',
		'null' => false
	);

	$db = Connections::get('default');
	$result = $db->buildColumn($data);
	//here $result will be : '`created` datetime DEFAULT CURRENT_TIMESTAMP' for a MySQL datasource


