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

namespace W7\Core\Server;

use W7\App;
use W7\Core\Dispatcher\EventDispatcher;
use W7\Core\Listener\FinishListener;
use W7\Core\Listener\ManagerStartListener;
use W7\Core\Listener\PipeMessageListener;
use W7\Core\Listener\StartListener;
use W7\Core\Listener\TaskListener;
use W7\Core\Listener\WorkerErrorListener;
use W7\Core\Listener\WorkerStartListener;
use W7\Core\Listener\WorkerStopListener;
use W7\Http\Listener\RequestListener;
use W7\Tcp\Listener\CloseListener;
use W7\Tcp\Listener\ConnectListener;
use W7\Tcp\Listener\ReceiveListener;

class SwooleEvent {
	/**
	 * swoole 事件
	 */
	const ON_START = 'start';
	const ON_SHUTDOWN = 'shutdown';

	const ON_WORKER_START = 'workerStart';
	const ON_WORKER_STOP = 'workerStop';
	const ON_WORKER_EXIT = 'workerExit';
	const ON_WORKER_ERROR = 'workerError';

	const ON_MANAGER_START = 'managerStart';
	const ON_MANAGER_STOP = 'managerStop';

	const ON_CONNECT = 'connect';
	const ON_RECEIVE = 'receive';
	const ON_PACKET = 'packet';
	const ON_CLOSE = 'close';

	const ON_BUFFER_FULL = 'bufferFull';
	const ON_BUFFER_EMPTY = 'bufferEmpty';

	const ON_TASK = 'task';
	const ON_FINISH = 'finish';
	const ON_PIPE_MESSAGE = 'pipeMessage';

	const ON_REQUEST = 'request';

	/**
	 * 自定义事件
	 */
	const ON_USER_BEFORE_START = 'beforeStart';
	const ON_USER_AFTER_START = 'afterStart';
	const ON_USER_BEFORE_REQUEST = 'beforeRequest';
	const ON_USER_AFTER_REQUEST = 'afterRequest';
	const ON_USER_TASK_FINISH = 'afterTaskFinish';

	public function getDefaultEvent() {
		return [
			'task' => [
				self::ON_TASK => TaskListener::class,
				self::ON_FINISH => FinishListener::class,
			],
			'http' => [
				self::ON_REQUEST => RequestListener::class,
			],
			'tcp' => [
				self::ON_RECEIVE => ReceiveListener::class,
				self::ON_CONNECT => ConnectListener::class,
				self::ON_CLOSE => CloseListener::class,
			],
			'manage' => [
				self::ON_START => StartListener::class,
				self::ON_MANAGER_START => ManagerStartListener::class,
				self::ON_WORKER_START => WorkerStartListener::class,
				self::ON_WORKER_STOP => WorkerStopListener::class,
				self::ON_WORKER_ERROR => WorkerErrorListener::class,
				self::ON_PIPE_MESSAGE => PipeMessageListener::class,
			]
		];
	}

	public function getUserEvent() {
		return [
			self::ON_USER_BEFORE_START,
			self::ON_USER_BEFORE_REQUEST,
			self::ON_USER_AFTER_REQUEST,
			self::ON_USER_TASK_FINISH,
			self::ON_USER_AFTER_REQUEST
		];
	}

	private function registerSystemEvent() {
		$eventTypes = [App::$server->type, 'task', 'manage'];

		$swooleEvents = $this->getDefaultEvent();
		foreach ($eventTypes as $name) {
			$events = $swooleEvents[$name] ?? [];
			foreach ($events as $name => $event) {
				iloader()->singleton(EventDispatcher::class)->listen($name, $event);
			}
		}
	}

	private function registerUserEvent() {
		foreach ($this->getUserEvent() as $eventName) {
			$listener = sprintf('\\W7\\Core\\Listener\\%sListener', ucfirst($eventName));
			iloader()->singleton(EventDispatcher::class)->listen($eventName, $listener);

			$listener = sprintf('\\W7\\%s\\Listener\\%sListener', ucfirst(App::$server->type), ucfirst($eventName));
			iloader()->singleton(EventDispatcher::class)->listen($eventName, $listener);

			$listener = sprintf('\\W7\\App\\Listener\\%sListener', ucfirst($eventName));
			iloader()->singleton(EventDispatcher::class)->listen($eventName, $listener);
		}
	}

	public function register() {
		$this->registerSystemEvent();
		$this->registerUserEvent();
	}
}
