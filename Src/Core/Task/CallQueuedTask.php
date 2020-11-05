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

namespace W7\Core\Task;

use W7\App;
use W7\Contract\Task\TaskDispatcherInterface;
use W7\Core\Message\TaskMessage;

class CallQueuedTask {
	/**
	 * @var TaskMessage
	 */
	public $taskMessage;

	/**
	 * @param TaskMessage $message
	 */
	public function __construct(TaskMessage $message) {
		$this->taskMessage = $message;
	}

	public function handle() {
		$container = App::getApp()->getContainer();
		return $container->singleton(TaskDispatcherInterface::class)->dispatchNow($this->taskMessage, null, null, $container->get('worker_id'));
	}

	/**
	 * @param $e
	 */
	public function failed($e) {
		$handler = App::getApp()->getContainer()->singleton($this->taskMessage->task);

		if (method_exists($handler, 'failed')) {
			call_user_func_array([$handler, 'failed'], [$e]);
		}
	}

	public function displayName() {
		return $this->taskMessage->task;
	}
}
