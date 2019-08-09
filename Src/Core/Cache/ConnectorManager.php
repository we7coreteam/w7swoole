<?php
/**
 * 缓存连接管理
 * @author donknap
 * @date 18-12-30 上午11:59
 */

namespace W7\Core\Cache;


use Psr\SimpleCache\CacheInterface;
use W7\Core\Cache\Connection\ConnectionAbstract;
use W7\Core\Cache\Pool\Pool;

class ConnectorManager {
	private $config;
	private $pool;
	
	public function __construct() {
		$this->pool = [];
		$this->config['connection'] = \iconfig()->getUserAppConfig('cache') ?? [];
		$this->config['pool'] = \iconfig()->getUserAppConfig('pool')['cache'] ?? [];
	}

	public function release($connection) {
		if (empty($connection->poolName)) {
			return true;
		}
		list($poolType, $poolName) = explode(':', $connection->poolName);
		/**
		 * @var Pool $pool
		 */
		$pool = iloader()->withClass(Pool::class)
			->withSingle()->withAlias($poolName)
			->withParams(['name' => $poolName])
			->get();

		$pool->releaseConnection($connection);
	}

	public function connect($name = 'default') : CacheInterface {
		$config = $this->config['connection'][$name] ?? [];
		$poolConfig = $this->config['pool'][$name] ?? [];

		if (empty($config)) {
			throw new \RuntimeException('Cache is not configured.');
		}

		$connectionClass = $this->checkDriverSupport($config['driver']);
		/**
		 * @var ConnectionAbstract $connection
		 */
		$connection = iloader()->singleton($connectionClass);

		if (empty($poolConfig) || empty($poolConfig['enable'])) {
			ilogger()->channel('cache')->debug('create connection without pool');
			return $connection->connect($config);
		}

		/**
		 * @var Pool $pool
		 */
		$pool = iloader()->withClass(Pool::class)
					->withSingle()->withAlias($name)
					->withParams(['name' => $name])
					->get();
		$pool->setConfig($config);
		$pool->setMaxCount($poolConfig['max']);
		$pool->setCreator($connection);

		return $pool->getConnection();
	}

	private function checkDriverSupport($driver) {
		$className = sprintf("\\W7\\Core\\Cache\\Connection\\%sConnection", ucfirst($driver));
		if (!class_exists($className)) {
			//处理自定义的handler
			$className = $driver;
		}
		if (!class_exists($className)) {
			throw new \RuntimeException('This cache driver is not supported');
		}

		return $className;
	}
}