<?php

namespace Potager\Testing;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Potager\App;

/**
 * Base test case class for the Potager framework.
 *
 * Provides automatic application bootstrapping capabilities. If a child class
 * declares an `$app` property, this class will automatically initialize the
 * application during the setup phase.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Prepares the test environment before each test runs.
     *
     * If the extending test class defines an `$app` property, it automatically
     * boots the application instance and assigns it to that property.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (property_exists($this, 'app')) {
            $this->app = $this->autoBootApp();
        }
    }

    /**
     * Cleans up the test environment after each test runs.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (property_exists($this, 'app')) {
            $this->app = null;

            restore_error_handler();
            restore_exception_handler();
        }

        parent::tearDown();
    }

    /**
     * Automatically boots the application instance.
     *
     * It first checks if the child class implements a custom `createApp()` method
     * to allow custom bootstrapping. If not, it falls back to the default
     * `App::create()` initialization.
     *
     * @return App The initialized application instance.
     */
    protected function autoBootApp(): App
    {
        if (method_exists($this, 'createApp')) {
            return $this->createApp();
        }

        return App::create();
    }
}
