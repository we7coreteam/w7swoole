<?php

/**
 * WeEngine Api System
 *
 * (c) We7Team 2019 <https://www.w7.cc>
 *
 * This is not a free software
 * Using it under the license terms
 * visited https://www.w7.cc for more details
 */

namespace W7\Core\Crontab\Process;

use W7\Core\Crontab\Task\TaskManager;
use W7\Core\Process\ProcessAbstract;

class CrontabDispatcher extends ProcessAbstract {
	/**
	 * @var TaskManager
	 */
	private $taskManager;
	private static $tasks = [];

	protected function init() {
		$this->taskManager = new TaskManager(static::getTasks());
	}

	public static function getTasks() {
		if (!static::$tasks) {
			$tasks = \iconfig()->getUserConfig('crontab')['task'] ?? [];
			foreach ($tasks as $name => $task) {
				if (isset($task['enable']) && $task['enable'] === false) {
					continue;
				}
				static::$tasks[$name] = $task;
			}
		}
		return static::$tasks;
	}

	public function run() {
		if ((ENV & DEBUG) === DEBUG) {
			echo 'Crontab run at ' . date('Y-m-d H:i:s') . PHP_EOL;
		}

		$tasks = $this->taskManager->getRunTasks();
		foreach ($tasks as $name => $task) {
			ilogger()->info('push crontab task ' . $name . ' ' . $task);
			$this->sendMsg($task);
		}
	}
}
