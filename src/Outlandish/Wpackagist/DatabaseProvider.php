<?php

namespace Outlandish\Wpackagist;

class DatabaseProvider
{
    public function __construct()
    {
        $file = dirname(dirname(dirname(__DIR__))) . '/data/packages.sqlite';
        $this->db = new \PDO('sqlite:' . $file);
    }

    public function getDb()
    {
        $this->migrate();

        return $this->db;
    }

    protected function migrate()
    {
        $updated_to = $current = $this->getSchemaVersion();
        $version = $current + 1;
        $method = "migrateTo$version";

        while (method_exists($this, $method)) {
            $this->$method();
            $updated_to = $version;
            $method = "migrateTo" . ++$version;
        }

        if ($updated_to > $current) {
            $this->setSchemaVersion($updated_to);
        }
    }

    protected function getSchemaVersion()
    {
        if ($this->db->query("SELECT tbl_name FROM sqlite_master WHERE tbl_name = 'schema_version'")->fetchColumn()) {
            return $this->db->query('SELECT version FROM schema_version')->fetchColumn();
        } else {
            return 0;
        }
    }

    protected function setSchemaVersion($version)
    {
        $version = (int) $version;

        $this->db->exec("DELETE FROM schema_version");
        $this->db->exec("INSERT INTO schema_version (version) VALUES ($version)");
    }

    protected function migrateTo1()
    {
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
            CREATE INDEX IF NOT EXISTS last_fetched_idx ON packages(last_fetched);

            CREATE TABLE IF NOT EXISTS schema_version (
                version INT,

                PRIMARY KEY (version)
            );
        ');
    }

    protected function migrateTo2()
    {
        $this->db->exec('ALTER TABLE packages ADD COLUMN is_active INT DEFAULT 1');

        // Versions format has changed, redownload everything
        $this->db->exec('UPDATE packages SET last_fetched = NULL');
    }

    protected function migrateTo3()
    {
        // Versions detection has changed, redownload everyting
        $this->db->exec('UPDATE packages SET last_fetched = NULL');
    }
}
