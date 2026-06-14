<?php

namespace Potager\Contracts\Configuration;

interface RepositoryInterface
{
    /**
     * Retrieve a config value using dot notation.
     *
     * @param string $key      e.g. 'database.driver'
     * @param mixed  $default  Returned when the key does not exist.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a config value at runtime using dot notation.
     * Useful for overriding values in tests or service providers.
     *
     * @param string $key   e.g. 'mail.default'
     * @param mixed  $value
     */
    public function set(string $key, mixed $value): void;

    /**
     * Determine whether a config key exists.
     *
     * @param string $key e.g. 'database.host'
     */
    public function has(string $key): bool;

    /**
     * Return the entire merged configuration array.
     */
    public function all(): array;
}
