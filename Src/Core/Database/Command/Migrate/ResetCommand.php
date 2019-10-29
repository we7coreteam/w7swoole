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

namespace W7\Core\Database\Command\Migrate;

use Illuminate\Filesystem\Filesystem;
use W7\Core\Database\Migrate\DatabaseMigrationRepository;
use Symfony\Component\Console\Input\InputOption;
use W7\Console\Command\ConfirmTrait;
use W7\Core\Database\Migrate\Migrator;

class ResetCommand extends MigrateCommandAbstract {
	use ConfirmTrait;

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'migrate:reset';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Rollback all database migrations';

	/**
	 * The migrator instance.
	 *
	 * @var \Illuminate\Database\Migrations\Migrator
	 */
	protected $migrator;

	/**
	 * Create a new migration rollback command instance.
	 *
	 * @param  \Illuminate\Database\Migrations\Migrator  $migrator
	 * @return void
	 */
	public function __construct(string $name = null) {
		parent::__construct($name);
		$this->migrator = new Migrator(new DatabaseMigrationRepository(idb(), 'migration'), idb(), new Filesystem());
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	protected function handle($options) {
		if (! $this->confirmToProceed()) {
			return;
		}

		$this->migrator->setConnection($this->option('database'));

		// First, we'll make sure that the migration table actually exists before we
		// start trying to rollback and re-run all of the migrations. If it's not
		// present we'll just bail out with an info message for the developers.
		if (! $this->migrator->repositoryExists()) {
			return $this->output->comment('Migration table not found.');
		}

		$this->migrator->setOutput($this->output)->reset(
			$this->getMigrationPaths(),
			$this->option('pretend')
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions() {
		return [
			['database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use'],

			['force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production'],

			['path', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The path(s) to the migrations files to be executed'],

			['realpath', null, InputOption::VALUE_NONE, 'Indicate any provided migration file paths are pre-resolved absolute paths'],

			['pretend', null, InputOption::VALUE_NONE, 'Dump the SQL queries that would be run'],
		];
	}
}