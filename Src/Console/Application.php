<?php

namespace W7\Console;

use Symfony\Component\Console\Application as SymfontApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use W7\Console\Io\Output;
use W7\Core\Exception\CommandException;

class Application extends SymfontApplication {
	public function __construct() {
		$version = $this->version();
		$this->registerCommands();

		parent::__construct('w7swoole', $version);
	}

	public function run(InputInterface $input = null, OutputInterface $output = null) {
		$output = new Output();

		return parent::run($input, $output); // TODO: Change the autogenerated stub
	}

	public function doRun(InputInterface $input, OutputInterface $output) {
		if (true === $input->hasParameterOption(['--version', '-V', '-v'], true)) {
			$output->writeln($this->logo());
			$output->writeln($this->getLongVersion());
			return 0;
		}

		try{
			return parent::doRun($input, $output);
		} catch (\Throwable $e) {
			$this->renderException($e, $output);
			if ($e instanceof CommandException) {
				$input = new ArrayInput(['--help' => true]);
				$this->run($input);
			}
		}
	}

	private function registerCommands() {
		$commands = glob(__DIR__  . '/Command/*/' . '*Command.php');
		$systemCommands = [];
		foreach ($commands as $key => &$item) {
			$item = str_replace(__DIR__, '', $item);
			$item = str_replace('.php', '', $item);
			$item = str_replace('/', '\\', $item);

			$info = explode('\\', $item);
			$name = substr($info[3], 0, strlen($info[3]) - 7);
			$name = strtolower($info[2] . ':' . $name);

			$systemCommands[$name] = "\\W7\\Console" . $item;
		}
		$systemCommands = array_merge($systemCommands, iconfig()->getServerCommand());

		$userCommands = iconfig()->getUserConfig('command');
		$commands = array_merge($systemCommands, $userCommands);

		foreach ($commands as $name => $class) {
			$commandObj = new $class($name);
			$this->add($commandObj);
		}
	}

	private function logo() {
		return "
__      _______ _______                   _      
\ \    / /  ___  / ___|_      _____   ___ | | ___ 
 \ \ /\ / /   / /\___ \ \ /\ / / _ \ / _ \| |/ _ \
  \ V  V /   / /  ___) \ V  V / (_) | (_) | |  __/
   \_/\_/   /_/  |____/ \_/\_/ \___/ \___/|_|\___|
";
	}

	private function version() {
		$frameworkVersion = \iconfig()::VERSION;
		$phpVersion = PHP_VERSION;
		$swooleVersion = SWOOLE_VERSION;
		$version = "framework: $frameworkVersion, php: $phpVersion, swoole: $swooleVersion";

		return $version;
	}
}