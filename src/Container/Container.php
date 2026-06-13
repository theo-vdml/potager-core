<?php

namespace Potager\Container;

use Exception;
use Potager\Container\Exceptions\ContainerException;
use Potager\Container\Exceptions\NotFoundException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * Class Container
 *
 * A lightweight and flexible dependency injection container.
 *
 * Supports binding of services via closures, singleton registration, autowiring
 * via reflection, and PSR-11 compliance (get/has methods).
 *
 * Provides:
 * - Manual binding of services (singleton or not)
 * - Automatic resolution of dependencies through constructors and callable injection
 * - Method/function call with automatic dependency injection
 * - Service retrieval via PSR-11 `get()` and `has()`
 */
class Container implements ContainerInterface
{
    /**
     * Array of service bindings (non-instantiated factories).
     *
     * @var array<string, callable>
     */
    protected $bindings = [];

    /**
     * Array of singleton instances or placeholders (null before instantiation).
     *
     * @var array<string, mixed|null>
     */
    protected $instances = [];

    /**
     * Register a service binding (non-singleton).
     *
     * @param string $id Identifier for the service.
     * @param callable $closure A factory that returns the service instance.
     * @return void
     */
    public function bind(string $id, callable $closure)
    {
        $this->bindings[$id] = $closure;
    }

    /**
     * Register a singleton service binding (lazy-loaded).
     *
     * @param string $id Identifier for the service.
     * @param callable|null $closure A factory that returns the service instance.
     * @return void
     */
    public function singleton(string $id, callable|null $closure = null)
    {
        if ($closure !== null) {
            $this->bindings[$id] = $closure;
        }
        $this->instances[$id] = null;
    }

    /**
     * Register an already instantiated object as a singleton.
     *
     * @param string $id Identifier for the service.
     * @param mixed $instance The concrete instance to use.
     * @return void
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Register a non-singleton binding only if not already registered.
     *
     * @param string $id Identifier for the service.
     * @param callable $closure A factory for the service.
     * @return void
     */
    public function bindIfNotExists(string $id, callable $closure)
    {
        if (!$this->has($id)) {
            $this->bind($id, $closure);
        }
    }

    /**
     * Register a singleton binding only if not already registered.
     *
     * @param string $id Identifier for the service.
     * @param callable|null $closure A factory for the singleton service.
     * @return void
     */
    public function singletonIfNotExists(string $id, callable|null $closure = null): void
    {
        if (!$this->has($id)) {
            $this->singleton($id, $closure);
        }
    }

    /**
     * Register an instance only if not already registered.
     *
     * @param string $id Identifier for the service.
     * @param mixed $instance The service instance.
     * @return void
     */
    public function instanceIfNotExists(string $id, mixed $instance): void
    {
        if (!$this->has($id)) {
            $this->instances[$id] = $instance;
        }
    }



    /**
     * Resolve and instantiate a service or call its registered factory.
     *
     * - Checks singleton registry
     * - Calls binding factory if registered
     * - Autowires via constructor if class exists
     *
     * @param string $id Service identifier or class name.
     * @param array $parameters Optional named parameters to override autowiring.
     * @return mixed Instantiated service.
     *
     * @throws NotFoundException If the service is not found or class does not exist.
     * @throws ContainerException On resolution or instantiation failure.
     */
    public function make(string $id, array $parameters = []): mixed
    {
        try {
            if (array_key_exists($id, $this->instances) && $this->instances[$id] !== null) {
                return $this->instances[$id];
            }

            if (array_key_exists($id, $this->bindings)) {
                $object = $this->callFactory($this->bindings[$id], $parameters);

                if (array_key_exists($id, $this->instances)) {
                    $this->instances[$id] = $object;
                }

                return $object;
            }

            if (class_exists($id)) {
                $object = $this->resolve($id, $parameters);

                if (array_key_exists($id, $this->instances)) {
                    $this->instances[$id] = $object;
                }

                return $object;
            }

            throw new NotFoundException("Service '{$id}' was not found in the container.");
        } catch (NotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ContainerException("Failed to resolve service '{$id}'. Reason: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Retrieve a service instance (PSR-11 compliant).
     *
     * Alias to `make()`.
     *
     * @param string $id The identifier of the entry to retrieve.
     * @return mixed The resolved service instance.
     *
     * @throws NotFoundException If the service is not found.
     * @throws ContainerException If an error occurs while resolving the service.
     */
    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * Check if a service is registered in bindings or instances.
     *
     * @param string $id Service identifier.
     * @return bool True if the service is bound or already instantiated.
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings) || array_key_exists($id, $this->instances);
    }

    /**
     * Call a callable (closure, function, or method) with automatic dependency injection.
     *
     * Parameters are resolved by name, type-hint, or default values.
     *
     * @param callable $callable The function or method to call.
     * @param array $parameters Optional named arguments to override autowiring.
     * @return mixed The result of the callable.
     *
     * @throws ContainerException If parameter resolution fails or callable cannot be invoked.
     */
    public function call(callable $callable, array $parameters = []): mixed
    {

        if (is_array($callable) && count($callable) === 2) {
            // Méthode d'objet ou statique [object|string, method]
            $reflector = new ReflectionMethod($callable[0], $callable[1]);
        } elseif (is_string($callable) && strpos($callable, '::') !== false) {
            // Méthode statique sous forme "Class::method"
            $reflector = new ReflectionMethod($callable);
        } else {
            // Fonction globale ou closure 
            $reflector = new ReflectionFunction($callable);
        }

        $args = $this->resolveParameters($parameters, $reflector->getParameters());

        return call_user_func_array($callable, $args);
    }

    /**
     * Internally invokes a service factory with resolved parameters.
     *
     * Used for bindings and singleton creation.
     *
     * @param callable $factory Factory callable to invoke.
     * @param array $parameters Optional named arguments to override autowiring.
     * @return mixed The result of the factory.
     *
     * @throws ContainerException If the factory cannot be called.
     */
    protected function callFactory(callable $factory, array $parameters = []): mixed
    {
        try {
            if (is_array($factory)) {
                $reflector = new ReflectionMethod($factory[0], $factory[1]);
            } elseif (is_string($factory) && strpos($factory, '::') !== false) {
                $reflector = new ReflectionMethod($factory);
            } else {
                $reflector = new ReflectionFunction($factory);
            }

            $args = $args = $this->resolveParameters($parameters, $reflector->getParameters());

            return call_user_func_array($factory, $args);
        } catch (Throwable $e) {
            throw new ContainerException("Failed to call factory for service. Reason: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolve a class via reflection and constructor injection.
     *
     * @param string $class Fully qualified class name.
     * @param array $parameters Optional named parameters to override autowiring.
     * @return mixed Instantiated class.
     *
     * @throws ContainerException If the class cannot be instantiated.
     */
    protected function resolve(string $class, array $parameters = []): mixed
    {
        try {
            $reflector = new ReflectionClass($class);

            if (!$reflector->isInstantiable()) {
                throw new Exception("Class '{$class}' is not instantiable.");
            }

            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                return new $class();
            }

            $args = $this->resolveParameters($parameters, $constructor->getParameters());

            return $reflector->newInstanceArgs($args);
        } catch (Throwable $e) {
            throw new ContainerException("Failed to instantiate class '{$class}'. Reason: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolve constructor or callable parameters via type hint, provided input, or default.
     *
     * @param array $parameters User-supplied named arguments.
     * @param array $reflectedParams Parameters from reflection.
     * @return array List of resolved arguments to pass to callable.
     *
     * @throws ContainerException If any parameter cannot be resolved.
     */
    protected function resolveParameters(array $parameters, array $reflectedParams): array
    {
        $args = [];

        foreach ($reflectedParams as $param) {
            $name = $param->getName();
            $type = $param->getType();

            // Parameter explicitly provided by name
            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
                continue;
            }

            // Parameter has a class/interface type hint
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                // Automatically inject the container itself
                if ($typeName === self::class || $typeName === ContainerInterface::class || is_subclass_of($this, $typeName)) {
                    $args[] = $this;
                    continue;
                }

                try {
                    // Resolve the dependency using the container
                    $args[] = $this->make($typeName);
                    continue;
                } catch (Throwable $e) {
                    if ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                        continue;
                    }
                    throw $e;
                }
            }

            // Use default value if available
            if ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
                continue;
            }

            // Could not resolve the parameter
            throw new ContainerException("Unable to resolve parameter '{$name}' with no default value and no type hint.");
        }

        return $args;
    }
}
