<?php

namespace Tests\Support;

use Potager\App;
use Tests\TestCase;

/**
 * Base test case for testing Limpid Models in isolation.
 * This class automatically sets up an in-memory SQLite database and binds it
 * to the Model architecture, entirely skipping the full application boot process
 * for maximum performance.
 */
abstract class FrameworkIntegrationTestCase extends TestCase
{
    protected ?App $app = null;

    protected function createApp(): App
    {
        $basePath = realpath(__DIR__ . '/../Fixtures');

        return App::create($basePath);
    }
}
