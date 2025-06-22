<?php

namespace Potager\Limpid;

use Potager\App;
use Pixie\Connection;
use Pixie\QueryBuilder\QueryBuilderHandler;

/**
 * Class Database
 * Wrapper around the Pixie Query Builder and PDO connection.
 * Handles database connection initialization and provides static query interface.
 */
class Database
{
    protected \PDO $pdo;

    protected Connection $connection;


    /**
     * Database constructor.
     * Initializes the Pixie database connection.
     */
    public function __construct(array $config)
    {
        $this->initializeConnection($config);
    }



    /**
     * Get the singleton instance of the Database.
     *
     * @return Database
     */
    public static function getInstance(): Database
    {
        return App::useDatabase();
    }

    /**
     * Initializes the Pixie Connection with configuration values from the App.
     *
     * @throws \Exception If connection fails.
     */
    private function initializeConnection(array $config): void
    {
        // Build the config array for Pixie
        $pixieConfig = $this->buildPixieConfig($config);

        // Attempt to connect using config 
        try {
            $this->connection = new Connection($pixieConfig['driver'], $pixieConfig);
            $this->pdo = $this->connection->getPdoInstance();
        } catch (\Exception $e) {
            throw new \RuntimeException("Database connection failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Builds the Pixie-compatible configuration array from App config.
     *
     * @param mixed $config
     * @return array
     */
    private function buildPixieConfig(array $config): array
    {
        return [
            'driver' => $config['driver'] ?? 'mysql',
            'host' => $config['host'] ?? 'localhost',
            'database' => $config['database'] ?? null,
            'username' => $config['username'] ?? null,
            'password' => $config['password'] ?? '',
            'charset' => $config['charset'] ?? 'utf8',
            'collation' => $config['collation'] ?? 'utf8_unicode_ci',
            'prefix' => $config['prefix'] ?? '',
            'options' => [\PDO::ATTR_TIMEOUT => 5, \PDO::ATTR_EMULATE_PREPARES => false],
        ];
    }

    /**
     * Returns the raw PDO instance.
     *
     * @return \PDO
     */
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Allows direct access to the Pixie Connection instance.
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Returns the raw PDO instance.
     *
     * @return \PDO
     */
    public static function pdo(): \PDO
    {
        return static::getInstance()->getPdo();
    }

    /**
     * Allows direct access to the Pixie Connection instance.
     *
     * @return Connection
     */
    public static function connection(): Connection
    {
        return static::getInstance()->getConnection();
    }

    /**
     * Returns a query builder instance, optionally pre-set with a table.
     *
     * @return QueryBuilderHandler
     */
    public static function query(): QueryBuilderHandler
    {
        $queryBuilder = static::connection()->getQueryBuilder();
        return $queryBuilder;
    }

    /**
     * Shortcut to create a query builder with a specific table.
     *
     * @param string $table
     * @return QueryBuilderHandler
     */
    public static function table(string $table): QueryBuilderHandler
    {
        return static::query()->table($table);
    }
}
