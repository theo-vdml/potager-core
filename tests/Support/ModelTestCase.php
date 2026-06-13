<?php

namespace Tests\Support;

use PDO;
use Tests\TestCase;
use Potager\Limpid\Database;
use Potager\Limpid\Model;

/**
 * Base test case for testing Limpid Models in isolation.
 * This class automatically sets up an in-memory SQLite database and binds it
 * to the Model architecture, entirely skipping the full application boot process
 * for maximum performance.
 */
abstract class ModelTestCase extends TestCase
{
    /**
     * @var PDO|null Holds the PDO instance to allow raw queries in tests (e.g., CREATE TABLE)
     */
    protected ?PDO $memoryPdo = null;

    /**
     * Sets up the isolated database environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->bootDatabaseInMemory();
    }

    /**
     * Cleans up the database resolver after each test.
     */
    protected function tearDown(): void
    {
        Model::setDatabaseResolver(null);

        $this->memoryPdo = null;

        parent::tearDown();
    }

    /**
     * Creates an in-memory SQLite connection and injects it into the ORM.
     */
    protected function bootDatabaseInMemory(): void
    {
        $config = [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ];

        $db = new Database($config);
        $this->memoryPdo = $db->getPdo();

        Model::setDatabaseResolver($db);
    }

    /**
     * Helper to retrieve the active PDO instance.
     * Useful for running schema migrations inside your test files.
     */
    public function pdo(): PDO
    {
        return $this->memoryPdo;
    }
}
