cakephp-redis-ds
================

Datasource for manipulation records in redis

 * example model
	class Example extends AppModel {
		public $useDbConfig = 'redis';
		public $useTable = false;
		public $displayField = 'test';
		public $_schema = array(
			'id' => array('type' => 'integer'),
			'test' => array('type' => 'string')
		);
	}
  
 
 * Configure ds
	public $redis = array(
		'datasource' => 'RedisSource',
		'server' => 'localhost',
	);
 
 
 * example usage
 	$Example = ClassRegistry::init('Example');
	$Example->create();
	$Example->save(array('id' => '1', 'test' => 'name'));
	$Example->create();
	$Example->save(array('id' => '2', 'test' => 'name2'));
	$result = $Example->find('list', array(
		'conditions' => array(
			'id' => '2'
		)
	));
	print_r($result);
	$Example->delete(2);
	$result = $Example->find('list', array(
		'conditions' => array(
			'id' => '2'
		)
	));
	print_r($result);
