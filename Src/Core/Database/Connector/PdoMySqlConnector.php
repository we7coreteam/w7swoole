<?php
/**
 * @author donknap
 * @date 18-10-22 下午7:17
 */

namespace W7\Core\Database\Connector;


use Illuminate\Database\Connectors\MySqlConnector;
use Swoole\Coroutine\Channel;
use W7\Core\Database\Pool\Pool;

class PdoMySqlConnector {
	private $poolconfig;
	private $pool;
	private $mySqlConnector;
	private $defaultConnection;

	public function __construct() {
		$this->poolconfig = \iconfig()->getUserAppConfig('pool')['database'] ?? [];
		$this->mySqlConnector = new MySqlConnector();
	}

	/**
	 * ConnectionFactory 类调用，用于实例化数据库连接
	 * 此处根据传来的host，去创建相应的数据库连接池，然后返回连接
	 * @param array $config
	 */
	public function connect(array $config) {
		//未设置连接池时，直接返回数据连接对象
		if (!isset($this->poolconfig[$config['host']]) || $this->poolconfig[$config['host']]['enable'] == false) {
			ilogger()->info('return connection');
			return $this->getDefaultConnection($config);
		}

		$pool = $this->getPool($config['host'], $config);

		return $pool->getConnection();
	}

	/**
	 * @param $name
	 * @return Pool
	 */
	private function getPool($name, $option = []) {
		if (!empty($this->pool[$name])) {
			return $this->pool[$name];
		}

		$pool = new Pool();
		$pool->setConfig($option);
		$pool->setCreator($this->mySqlConnector);
		$pool->setMaxCount($this->poolconfig[$name]['max']);

		$this->pool[$name] = $pool;

		return $this->pool[$name];
	}

	private function getDefaultConnection($config) {
		if (!empty($this->defaultConnection)) {
			//return $this->defaultConnection;
		}
		$this->defaultConnection = $this->mySqlConnector->connect($config);
		return $this->defaultConnection;
	}
}