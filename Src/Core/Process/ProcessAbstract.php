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

namespace W7\Core\Process;

use Swoole\Event;
use Swoole\Process;
use Swoole\Timer;
use W7\Core\Exception\HandlerExceptions;
use W7\Core\Log\LogManager;

abstract class ProcessAbstract {
	protected $name = 'process';
	protected $num = 1;
	protected $mqKey;
	protected $serverType;
	/**
	 * @var Process
	 */
	protected $process;
	// event 模式下支持用户自定义pipe
	protected $pipe;

	//定时器模式下
	protected $runTimer;
	protected $interval = 1;
	private $exitTimer;
	private $complete;
	private $exitStatus;

	public function __construct($name, $num = 1, Process $process = null) {
		$this->name = $name;
		$this->num = $num;
		$this->process = $process;

		$this->init();
	}

	protected function init() {
	}

	public function getName() {
		return $this->name;
	}

	public function setProcess(Process $process) {
		$this->process = $process;
	}

	public function getProcess() {
		return $this->process;
	}

	public function setServerType($serverType) {
		$this->serverType = $serverType;
	}

	public function getServerType() {
		return $this->serverType;
	}

	private function getProcessName() {
		$name = 'w7-rangine ' . $this->name;
		if ($this->num > 1) {
			$name .= '-' . ($this->process->id % $this->num);
		}

		return $name;
	}

	/**
	 * process->push(msg) 有bug
	 * 默认的消息队列消费方式为争抢方式
	 * @param int $key
	 * @param int $mode
	 */
	public function setMq($key = 0, $mode = 2 | Process::IPC_NOWAIT) {
		$this->mqKey = $key;
		$this->process->useQueue($key, $mode);
	}

	abstract public function check();

	protected function beforeStart() {
	}

	public function onStart() {
		if (\stripos(PHP_OS, 'Darwin') === false) {
			$this->process->name($this->getProcessName());
		}

		/**
		 * 注册退出信号量,等本次业务执行完成后退出,在执行stop后需要等待sleep结束后再结束
		 */
		$this->exitStatus = 2;
		$this->complete = true;
		Process::signal(SIGTERM, function () {
			--$this->exitStatus;
		});

		$this->beforeStart();

		if (method_exists($this, 'read')) {
			$this->startByEvent();
		} else {
			$this->startByTimer();
		}

		$this->exitTimer = Timer::tick(1000, function () {
			/**
			 * 得到退出信号,但是任务定时器正在等待下一个时间点的时候,强制clear time,退出当前进程
			 */
			if ($this->exitStatus === 1 && $this->complete) {
				$this->stop();
			}
		});
	}

	private function startByTimer() {
		$this->runTimer = Timer::tick($this->interval * 1000, function () {
			$this->doRun(function () {
				$this->run();
			});
		});
	}

	private function startByEvent() {
		$pipe = $this->pipe ? $this->pipe : $this->process->pipe;
		Event::add($pipe, function () {
			$this->doRun(function () {
				$data = $this->pipe ? '' : $this->process->read();
				$this->read($data);
			});
		});
	}

	private function doRun(\Closure $callback) {
		$this->complete = false;

		try {
			$callback();
		} catch (\Throwable $throwable) {
			iloader()->get(HandlerExceptions::class)->handle($throwable, $this->serverType);
		}

		$this->complete = true;

		//如果在执行完成后就得到退出信息,则马上退出
		if ($this->exitStatus === 1) {
			$this->stop();
		}
	}

	abstract protected function run();

	public function stop() {
		--$this->exitStatus;
		if ($this->runTimer) {
			Timer::clear($this->runTimer);
			$this->runTimer = null;
		}
		if ($this->exitTimer) {
			Timer::clear($this->exitTimer);
			$this->exitTimer = null;
		}
		if (method_exists($this, 'read')) {
			Event::del($this->pipe ? $this->pipe : $this->process->pipe);
		}

		$this->process->kill($this->process->pid);
	}

	public function sendMsg($msg) {
		//swoole 版本不兼容, 不能用push
		return msg_send(msg_get_queue($this->mqKey), 1, $msg, false);
	}

	public function getMsg($size = null) {
		return $this->getProcess()->pop($size);
	}

	public function onStop() {
		ilogger()->debug('process ' . $this->getProcessName() . ' exit');
		iloader()->get(LogManager::class)->flushLog();
	}
}
