<?php

namespace Outlandish\Wpackagist\Service;

use Silex\Provider\DoctrineServiceProvider as BaseDoctrineServiceProvider;
use Silex\Application;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Event\ConnectionEventArgs;

class DoctrineServiceProvider extends BaseDoctrineServiceProvider
{
    public function register(Application $app)
    {
        parent::register($app);

        $provider = $this;

        $app['db.event_manager'] = $app->share($app->extend('db.event_manager', function ($manager, $app) use ($provider) {
            $manager->addEventListener('postConnect', $provider);

            return $manager;
        }));
    }

    public function postConnect(ConnectionEventArgs $args)
    {
        $this->migrate($args->getConnection());
    }

    protected function migrate(Connection $conn)
    {
        $updated_to = $current = $this->getSchemaVersion($conn);
        $version = $current + 1;
        $method = "migrateTo$version";

        while (method_exists($this, $method)) {
            $this->$method($conn);
            $updated_to = $version;
            $method = "migrateTo".++$version;
        }

        if ($updated_to > $current) {
            $this->setSchemaVersion($conn, $updated_to);
        }
    }

    protected function getSchemaVersion(Connection $conn)
    {
        if ($conn->query("SELECT tbl_name FROM sqlite_master WHERE tbl_name = 'schema_version'")->fetchColumn()) {
            return $conn->query('SELECT version FROM schema_version')->fetchColumn();
        } else {
            return 0;
        }
    }

    protected function setSchemaVersion(Connection $conn, $version)
    {
        $version = (int) $version;

        $conn->exec("DELETE FROM schema_version");
        $conn->exec("INSERT INTO schema_version (version) VALUES ($version)");
    }

    protected function migrateTo1(Connection $conn)
    {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS packages (
                class_name TEXT,
                name TEXT,
                last_committed DATETIME,
                last_fetched DATETIME,
                versions TEXT,

                PRIMARY KEY (class_name, name)
            );
            CREATE INDEX IF NOT EXISTS last_committed_idx ON packages(last_committed);
            CREATE INDEX IF NOT EXISTS last_fetched_idx ON packages(last_fetched);

            CREATE TABLE IF NOT EXISTS schema_version (
                version INT,

                PRIMARY KEY (version)
            );
        ');
    }

    protected function migrateTo2(Connection $conn)
    {
        $conn->exec('ALTER TABLE packages ADD COLUMN is_active INT DEFAULT 1');

        // Versions format has changed, redownload everything
        $conn->exec('UPDATE packages SET last_fetched = NULL');
    }

    protected function migrateTo3(Connection $conn)
    {
        // Versions detection has changed, redownload everyting
        $conn->exec('UPDATE packages SET last_fetched = NULL');
    }

    protected function migrateTo4(Connection $conn)
    {
        $conn->exec('ALTER TABLE packages ADD COLUMN display_name TEXT');

        // redownload everything to get display names
        $conn->exec('UPDATE packages SET last_fetched = NULL');
    }

    protected function migrateTo5(Connection $conn)
    {
        $conn->exec('
            CREATE TABLE IF NOT EXISTS requests (
                ip_address TEXT UNIQUE,
                last_request DATETIME,
                request_count INT
            );
            CREATE INDEX IF NOT EXISTS ip_address_idx ON requests(ip_address);
        ');
    }
}
