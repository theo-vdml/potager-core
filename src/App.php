<?php

namespace Potager;

use Potager\Auth\Authenticator;
use Potager\Container\Container;
use Potager\Exceptions\Handler;
use Potager\Grape\Grape;
use Potager\Limpid\Database;
use Potager\Mailer\MailManager;
use Potager\Router\Request;
use Potager\Router\RequestFactory;
use Potager\Router\Router;
use Throwable;

/**
 * Class App
 *
 * The main application bootstrap class. It acts as a central point to manage services,
 * configurations, and commonly used components via dependency injection.
 */
class App
{
    /**
     * Singleton instance of the App.
     *
     * @var ?App
     */
    protected static ?App $instance = null;

    /**
     * Service container instance.
     *
     * @var ?Container
     */
    protected ?Container $container = null;

    /**
     * Application configuration instance.
     *
     * @var Config
     */
    protected Config $config;

    /**
     * App constructor.
     *
     * Initializes the application, including the configuration, service container,
     * and MySQL database connection using Grape.
     *
     * @param Container|null $container Optional custom service container.
     * @throws \Exception If the container is required but not provided.
     */
    public function __construct(?Container $container = null)
    {
        $this->config = new Config();
        $this->container = $container ?? new Container();
        $this->bootstrap();
        $this->registerHandlers();
    }

    /**
     * Register essential application services as singletons if not already present.
     *
     * @return void
     * @throws \Exception If no container is available.
     */
    protected function bootstrap(): void
    {
        if (!$this->container) {
            throw new \Exception("Cannot register services without a container set");
        }

        $this->container->singletonIfNotExists(Router::class);
        $this->container->singletonIfNotExists(Session::class);
        $this->container->singletonIfNotExists(MailManager::class);

        $this->container->singletonIfNotExists(Handler::class, function (Container $container): Handler {
            $environment = $this->config->get('environment', 'production');
            return new Handler($container, null, $environment === 'dev');
        });

        $this->container->singletonIfNotExists(Request::class, function (): Request {
            $request = RequestFactory::fromGlobals();
            return $request;
        });

        $this->container->singletonIfNotExists(Database::class, function (): Database {
            $config = $this->config->get('database');
            return new Database($config);
        });

        $this->container->singletonIfNotExists(Authenticator::class, function (): Authenticator {
            $config = $this->config->get('auth');
            return new Authenticator($config);
        });
    }

    /**
     * Register global error, exception, and shutdown handlers.
     *
     * @return void
     */
    protected function registerHandlers(): void
    {
        set_error_handler(function ($serverity, $message, $file, $line): bool {
            /** @var Handler $handler */
            $handler = $this->container->make(Handler::class);
            return $handler->handlePhpError($serverity, $message, $file, $line);
        });

        set_exception_handler(function (Throwable $throwable): bool {
            /** @var Handler $handler */
            $handler = $this->container->make(Handler::class);
            return $handler->handleUncaughtException($throwable);
        });

        register_shutdown_function(function (): bool {
            /** @var Handler $handler */
            $handler = $this->container->make(Handler::class);
            return $handler->handleFatalShutdown();
        });
    }

    /**
     * Retrieve the singleton instance of the App.
     *
     * @param Container|null $container Optional container to initialize the app with.
     * @return App
     */
    public static function getInstance(?Container $container = null): App
    {
        if (self::$instance === null) {
            self::$instance = new self($container);
        }
        return self::$instance;
    }

    /**
     * Get the service container.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the configuration instance.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Static accessor for the configuration instance.
     *
     * @return Config
     */
    public static function useConfig(): Config
    {
        return static::getInstance()->getConfig();
    }

    /**
     * Static accessor for the Router instance.
     *
     * @return Router
     */
    public static function useRouter(): Router
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Router::class);
    }

    /**
     * Static accessor for the Session instance.
     *
     * @return Session
     */
    public static function useSession(): Session
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Session::class);
    }

    /**
     * Static accessor for the MailManager instance.
     *
     * @return MailManager
     */
    public static function useMailer(): MailManager
    {
        $container = static::getInstance()->getContainer();
        return $container->make(MailManager::class);
    }

    /**
     * Static accessor for the LatteEngine instance.
     *
     * @return LatteEngine
     */
    public static function useLatte(): LatteEngine
    {
        $container = static::getInstance()->getContainer();
        return $container->make(LatteEngine::class);
    }

    /**
     * Static accessor for the Database instance.
     *
     * @return Database
     */
    public static function useDatabase(): Database
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Database::class);
    }

    /**
     * Static accessor for the Authenticator instance.
     *
     * @return Authenticator
     */
    public static function useAuth(): Authenticator
    {
        $container = static::getInstance()->getContainer();
        return $container->make(Authenticator::class);
    }

    /**
     * Magic static method handler for dynamic useXyz service accessors.
     *
     * Allows static calls like App::useCustomService() to resolve services from the container.
     *
     * @param string $method Method name called.
     * @param array $args Arguments passed to the method.
     * @return mixed The resolved service instance.
     * @throws \BadMethodCallException If service or method is undefined.
     */
    public static function __callStatic($method, $args): mixed
    {
        if (str_starts_with($method, 'use')) {
            $service = lcfirst(substr($method, 3));
            $instance = self::getInstance();

            if (!$instance->container->has($service)) {
                throw new \BadMethodCallException("Undefined service: {$service}");
            }

            return $instance->container->make($service, $args);
        }
        throw new \BadMethodCallException("Undefined static method {$method}");
    }
}