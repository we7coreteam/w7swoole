<?php

/**
 * This file is part of Rangine
 *
 * (c) We7Team 2019 <https://www.rangine.com/>
 *
 * document http://s.w7.cc/index.php?c=wiki&do=view&id=317&list=2284
 *
 * visited https://www.rangine.com/ for more details
 */

namespace W7\Console\Command\Server;

use Symfony\Component\Console\Input\InputOption;
use W7\Console\Command\CommandAbstract;
use W7\Core\Server\ServerInterface;

abstract class ServerCommandAbstract extends CommandAbstract {
	private $servers;
	private $curServer;

	protected function configure() {
		$this->addOption('--config-app-setting-server', '-s', InputOption::VALUE_REQUIRED, 'server type');
	}

	private function registerProcessServer() {
		//如果启动的server中含有http,tcp,ws的时候,对用户自定义服务的类型个数不做限制
		//如果启动的server中不包含http,tcp,ws的时候,只能启动一个用户自定义服务
		$alone = true;
		$allServer = iconfig()->getAllServer();
		foreach ($this->servers as $key => $item) {
			if (!empty($allServer[$item])) {
				$alone = false;
				unset($this->servers[$key]);
			}
		}

		if ($alone && $this->servers) {
			$this->servers = [$this->servers[0]];
		}

//		$process = [];
//		foreach ($this->servers as $item) {
//
//		}
		//server的name的问题
	}

	private function getServer() : ServerInterface {
		$this->servers = trim(iconfig()->getUserAppConfig('setting')['server']);
		if ((ENV & DEBUG) === DEBUG) {
			$this->servers = $this->servers . '|reload';
		}
		$this->servers = explode('|', $this->servers);
		$this->registerProcessServer();

		foreach (iconfig()->getAllServer() as $key => $class) {
			if (in_array($this->servers, $key)) {
				unset($this->servers[$key]);
				return new $class();
			}
		}

		throw new \Exception('server type error');
	}

	private function addSubServer($server) {
		$lines = [];
		$this->servers = $this->servers ^ $this->curServer;
		foreach (iconfig()->getAllServer() as $key => $class) {
			if (($this->servers & $key) === $key) {
				$subServer = new $class();
				$subServer->listener($server->getServer());

				$statusInfo = '';
				foreach ($subServer->getStatus() as $key => $value) {
					$statusInfo .= " $key: $value, ";
				}
				$lines[] = "* {$subServer->getType()}  | " . rtrim($statusInfo, ', ');
			}
		}

		return $lines;
	}

	protected function start() {
		$server = $this->getServer();
		$status = $server->getStatus();

		if ($server->isRun()) {
			$this->output->writeln("The server have been running!(PID: {$status['masterPid']})", true);
			return $this->restart();
		}

		$statusInfo = '';
		foreach ($status as $key => $value) {
			$statusInfo .= " $key: $value, ";
		}

		// 信息面板
		$lines = [
			'			 Server Information					  ',
			'********************************************************************',
			"* {$server->getType()} | " . rtrim($statusInfo, ', '),
		];

		$lines = array_merge($lines, $this->addSubServer($server));

		$lines[] = '********************************************************************';
		// 启动服务器
		$this->output->writeln(implode("\n", $lines));
		$server->start();
	}

	protected function stop() {
		$server = $this->getServer();
		// 是否已启动
		if (!$server->isRun()) {
			$this->output->writeln('The server is not running!', true, true);
		}
		$this->output->writeln(sprintf('Server %s is stopping ...', $server->getType()));
		$result = $server->stop();
		if (!$result) {
			$this->output->writeln(sprintf('Server %s stop fail', $server->getType()), true, true);
		} else {
			$this->output->writeln(sprintf('Server %s stop success!', $server->getType()));
		}
	}

	protected function restart() {
		$server = $this->getServer();
		if ($server->isRun()) {
			$this->stop();
		}
		$this->start();
	}
}
