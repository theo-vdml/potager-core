<?php

use Potager\App;
use Potager\Contracts\Configuration\RepositoryInterface;

if (!function_exists('config')) {
    /**
     * Get a configuration value using dot notation.
     *
     * Delegates to the ConfigRepository bound in the service container.
     * Accessible from anywhere in the framework and in user application code.
     *
     * @param  string $key     Dot-notated config key, e.g. 'database.driver'.
     * @param  mixed  $default Returned when the key does not exist.
     * @return mixed
     *
     * @example
     *   $driver = config('database.driver', 'sqlite');
     *   $host   = config('mail.drivers.smtp.host', 'localhost');
     */
    function config(string $key, mixed $default = null): mixed
    {
        /** @var RepositoryInterface $repository */
        $repository = App::make(RepositoryInterface::class);

        return $repository->get($key, $default);
    }
}
