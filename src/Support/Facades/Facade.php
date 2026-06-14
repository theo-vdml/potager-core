<?php

namespace Potager\Support\Facades;

use Potager\App;

/**
 * Base Facade class.
 *
 * Provides a static proxy interface to services registered in the application's dependency container.
 */
abstract class Facade
{
    /**
     * Get the registered name of the component in the container.
     * Child classes must implement this method to return the binding key or class name.
     *
     * @return string
     */
    abstract protected static function getFacadeAccessor(): string;

    /**
     * Resolve the underlying facade root instance from the container.
     *
     * @return mixed The resolved service instance.
     */
    protected static function getFacadeRoot(): mixed
    {
        $accessor = static::getFacadeAccessor();
        return App::make($accessor);
    }

    /**
     * Handle dynamic, static method calls and proxy them to the resolved instance.
     *
     * @param string $method The name of the method being called.
     * @param array $args The arguments passed to the method.
     * @return mixed The result of the forwarded method call.
     *
     * @throws \RuntimeException If the facade root cannot be resolved from the container.
     */
    public static function __callStatic(string $method, array $args)
    {
        $instance = static::getFacadeRoot();

        if (!$instance) {
            $accessor = static::getFacadeAccessor();
            throw new \RuntimeException(
                "Unable to resolve the facade root for [{$accessor}]. Ensure the service is correctly bound in the application container before calling " . static::class . "::{$method}()."
            );
        }

        return $instance->$method(...$args);
    }
}
