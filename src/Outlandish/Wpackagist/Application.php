<?php

namespace Outlandish\Wpackagist;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication {

	protected $db;

	public function __construct() {
		parent::__construct('Wpackagist');
		$this->db = new \PDO('sqlite:data/packages.sqlite');
		$this->db->exec('
			CREATE TABLE IF NOT EXISTS packages (
				class_name TEXT,
				name TEXT,
				last_committed DATETIME,
				last_fetched DATETIME,
				versions TEXT,

				PRIMARY KEY (class_name, name)
			);
			CREATE INDEX IF NOT EXISTS last_committed_idx ON packages(last_committed);
			CREATE INDEX IF NOT EXISTS last_fetched_idx ON packages(last_fetched);');

	}

	public function doRun(InputInterface $input, OutputInterface $output) {
		$this->registerCommands();
		return parent::doRun($input, $output);
	}

	protected function registerCommands() {
		$this->add(new RefreshCommand());
		$this->add(new UpdateCommand());
		$this->add(new BuildCommand());
	}

	public function getDb() {
		return $this->db;
	}
}