<?php
/**
 * Class RedisSource
 *
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
 *
 *
 *
 * Configure ds
	public $redis = array(
		'datasource' => 'RedisSource',
		'server' => 'localhost',
	);
 *
 *
 * example usage
 *
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
 *
 *
 */
class RedisSource extends DataSource {

	public $description = 'Redis datasource';

	/**
	 * Redis instance
	 */
	protected $_Redis;

	/**
	 * The default configuration of a specific DataSource
	 *
	 * @var array
	 */
	protected $_baseConfig = array(
		'server' => 'localhost',
		'timeout' => 0,
		'persistent' => true,
		'port' => '6379',
		'password' => false
	);


	/**
	 * Create our HttpSocket and handle any config tweaks.
	 */
	public function __construct($config = array()) {
		parent::__construct($config);
		$this->_connect();
	}

	/**
	 * Connects to a Redis server
	 *
	 * @return boolean True if Redis server was connected
	 */
	protected function _connect() {
		if (!class_exists('Redis')) {
			return false;
		}
		$return = false;
		try {
			$this->_Redis = new Redis();
			if (empty($this->config['persistent'])) {
				$return = $this->_Redis->connect($this->config['server'], $this->config['port'], $this->config['timeout']);
			} else {
				$return = $this->_Redis->pconnect($this->config['server'], $this->config['port'], $this->config['timeout']);
			}
		} catch (RedisException $e) {
			return false;
		}
		if ($return && $this->config['password']) {
			$return = $this->_Redis->auth($this->config['password']);
		}
		return $return;
	}

	/**
	 * Close connect to Redis if "persistent" connection
	 */
	public function close() {
		if (!$this->config['persistent']) {
			$this->_Redis->close();
		}
	}

	/**
	 * Since datasources normally connect to a database there are a few things
	 * we must change to get them to work without a database.
	 */

	/**
	 * listSources() is for caching. You'll likely want to implement caching in
	 * your own way with a custom datasource. So just ``return null``.
	 */
	public function listSources($data = null) {
		return null;
	}

	/**
	 * describe() tells the model your schema for ``Model::save()``.
	 *
	 * You may want a different schema for each model but still use a single
	 * datasource. If this is your case then set a ``schema`` property on your
	 * models and simply return ``$model->schema`` here instead.
	 */
	public function describe($model) {
		if (!empty($model->useTable)) {
			return array();
		}
		return $model->schema(false);
	}

	/**
	 * calculate() is for determining how we will count the records and is
	 * required to get ``update()`` and ``delete()`` to work.
	 *
	 * We don't count the records here but return a string to be passed to
	 * ``read()`` which will do the actual counting. The easiest way is to just
	 * return the string 'COUNT' and check for it in ``read()`` where
	 * ``$data['fields'] === 'COUNT'``.
	 */
	public function calculate(Model $model, $func, $params = array()) {
		return 'COUNT';
	}

	/**
	 * Implement the R in CRUD. Calls to ``Model::find()`` arrive here.
	 */
	public function read(Model $model, $queryData = array(), $recursive = null) {
		if ($queryData['fields'] === 'COUNT') {
			return array(array(array('count' => 1)));
		}
		if (empty($queryData['conditions'][$model->primaryKey])) {
			if (empty($queryData['conditions'][$model->alias . '.' . $model->primaryKey])) {
				return array();
			}
			$id = $queryData['conditions'][$model->alias . '.' . $model->primaryKey];
		} else {
			$id = $queryData['conditions'][$model->primaryKey];
		}
		$key = $model->name . ':' . $id;
		$value = $this->_Redis->get($key);

		if (ctype_digit($value)) {
			$value = (int)$value;
		}
		if ($value !== false && is_string($value)) {
			$value = unserialize($value);
		}
		return array(
			array(
				$model->alias => $value
			)
		);
	}

	/**
	 * Implement the C in CRUD. Calls to ``Model::save()`` without $model->id
	 * set arrive here.
	 */
	public function create(Model $model, $fields = null, $values = null) {
		if (empty($fields) || empty($values)) {
			return false;
		}
		$value = serialize(array_combine($fields, $values));
		$key = $model->name . ':' . $model->{$model->primaryKey};
		if (empty($fields['duration']) || ($fields['duration'] === 0)) {
			return $this->_Redis->set($key, $value);
		}

		return $this->_Redis->setex($key, $fields['duration'], $value);
	}

	/**
	 * Implement the U in CRUD. Calls to ``Model::save()`` with $Model->id
	 * set arrive here. Depending on the remote source you can just call
	 * ``$this->create()``.
	 */
	public function update(Model $model, $fields = null, $values = null, $conditions = null) {
		return $this->create($model, $fields, $values);
	}

	/**
	 * Implement the D in CRUD. Calls to ``Model::delete()`` arrive here.
	 */
	public function delete(Model $model, $id = null) {
		$key = $model->name . ":" . $id[$model->alias . '.' . $model->primaryKey];
		return $this->_Redis->delete($key) > 0;
	}

	/**
	 * Queries the database with given Redis statement.
	 *
	 * ### Options
	 *
	 *
	 * @param string $query  redis query statement
	 * @param array  $options
	 * @param array  $params values to be bound to the query
	 *
	 * @return mixed Resource or object representing the result set, or false on failure
	 */
	public function execute($query, $options = array(), $params = array()) {
		return false;
	}
}
