<?php

namespace Potager\Auth;

use Exception;
use Potager\Auth\Contracts\AuthGuard;

/**
 * Authenticator manages multiple authentication guards and provides methods to interact with them.
 */
class Authenticator
{
    /**
     * Registered authentication guards builders.
     * @var array<string, callable>
     */
    protected array $guards = [];

    /**
     * Cached instances of authentication guards.
     * @var array<string, AuthGuard>
     */
    protected array $cachedGuards = [];

    /**
     * Default guard name.
     * @var string|null
     */
    protected ?string $defaultGuard = null;

    /**
     * The guard to use for the current operation.
     * If not set, the default guard will be used.
     * @var AuthGuard|null
     */
    protected ?AuthGuard $guardToUse = null;

    /**
     * The guard used for authentication.
     * @var AuthGuard|null
     */
    protected ?AuthGuard $authenticatedUsing = null;

    /**
     * The currently authenticated user.
     * This is set after a successful login.
     * @var mixed|null
     */
    protected $authenticatedUser = null;

    /**
     * Create a new Authenticator instance.
     *
     * @param ?array{
     *     guards?: array<string, callable>,
     *     default?: string
     * } $config Configuration array with guards and default guard name.
     */
    public function __construct(?array $config = null)
    {
        $config ??= [];
        if (isset($config['guards']) && is_array($config['guards'])) {
            foreach ($config['guards'] as $name => $guard) {
                if (is_callable($guard)) {
                    $this->registerGuard($name, $guard);
                }
            }
        }
        if (isset($config['default']) && is_string($config['default'])) {
            $this->setDefaultGuard($config['default']);
        }
    }

    /**
     * Get the default guard instance.
     *
     * @return AuthGuard The default guard.
     * @throws \RuntimeException If no default guard is set or if the default guard is not registered.
     */
    protected function getDefaultGuard(): AuthGuard
    {
        if ($this->defaultGuard === null && count($this->guards) === 1) {
            $this->defaultGuard = array_key_first($this->guards);
        }

        if ($this->defaultGuard === null) {
            throw new \RuntimeException('Default guard is not set for Authentication, please set one or specify the guard you want to use.');
        }

        if (!isset($this->guards[$this->defaultGuard])) {
            throw new \RuntimeException("Default guard {$this->defaultGuard} is not a registered guard. Please register it first.");
        }

        return $this->getGuard($this->defaultGuard);
    }

    /**
     * Get a specific guard by name.
     *
     * @param string $name The name of the guard to retrieve.
     * @return AuthGuard The requested guard.
     * @throws \RuntimeException If the guard is not registered.
     */
    public function getGuard(string $name): AuthGuard
    {
        if (isset($this->cachedGuards[$name])) {
            return $this->cachedGuards[$name];
        }

        if (!isset($this->guards[$name])) {
            throw new \RuntimeException("Guard {$name} is not registered.");
        }

        $this->cachedGuards[$name] = $this->guards[$name]();
        return $this->cachedGuards[$name];
    }

    /**
     * Get the guard to use for the current operation.
     *
     * @return AuthGuard The guard to use.
     */
    public function getGuardToUse(): AuthGuard
    {
        $guard = $this->guardToUse ?? $this->getDefaultGuard();
        $this->guardToUse = null; // Reset after use
        return $guard;
    }

    /**
     * Register a new guard.
     *
     * @param string $name The name of the guard.
     * @param callable $guard The guard factory callable.
     * @throws \RuntimeException If the guard is already registered.
     */
    public function registerGuard(string $name, callable $guard): void
    {
        if (isset($this->guards[$name])) {
            throw new \RuntimeException("Guard {$name} is already registered.");
        }

        $this->guards[$name] = $guard;
    }

    /**
     * Set the default guard.
     *
     * @param string $name The name of the guard to set as default.
     * @param bool $force Force the default guard even if not yet registered (default: false)
     * @throws \RuntimeException If the guard is not registered.
     */
    public function setDefaultGuard(string $name, bool $force = false): void
    {
        if (!$force && !isset($this->guards[$name])) {
            throw new \RuntimeException("Guard {$name} is not registered.");
        }

        $this->defaultGuard = $name;
    }

    /**
     * Specify which guard to use for the next operation.
     *
     * @param string $name The name of the guard to use.
     * @return self
     * @throws \RuntimeException If the guard is not registered.
     */
    public function use(string $name): self
    {
        if (!isset($this->guards[$name])) {
            throw new \RuntimeException("Guard {$name} is not registered.");
        }

        $this->guardToUse = $this->getGuard($name);
        return $this;
    }

    /**
     * Log in a user using the selected guard.
     *
     * @param mixed $user The user to log in.
     */
    public function login($user): void
    {
        $guard = $this->getGuardToUse();
        $guard->login($user);
        $this->authenticatedUser = $user;
        $this->authenticatedUsing = $guard;
    }

    /**
     * Log out the current user using the selected guard.
     */
    public function logout(): void
    {
        $guardToUse = $this->authenticatedUsing ?? $this->getGuardToUse();
        $guardToUse->logout();
        $this->authenticatedUser = null;
        $this->authenticatedUsing = null;
    }

    /**
     * Authenticate the user and set the authenticated user.
     *
     * @throws \RuntimeException If no user is authenticated.
     */
    public function authenticate(): void
    {
        $guard = $this->getGuardToUse();
        $user = $guard->user();
        if ($user === null) {
            throw new Exception('Access Denied');
        }
        $this->authenticatedUsing = $guard;
        $this->authenticatedUser = $user;
    }

    /**
     * Attempt to authenticate the user quietly without throwing exceptions.
     *
     * @return bool 
     */
    public function check(): bool
    {
        try {
            $this->authenticate();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the authenticated user.
     *
     * @return mixed|null The authenticated user or null if not authenticated.
     */
    public function user(): mixed
    {
        return $this->authenticatedUser;
    }

    /**
     * Get the authenticated user or fail with an exception if not authenticated.
     *
     * @return mixed The authenticated user.
     * @throws \RuntimeException If not authenticated.
     */
    public function getUserOrFail(): mixed
    {
        if ($this->authenticatedUser === null) {
            throw new \RuntimeException('No user is authenticated.');
        }
        return $this->authenticatedUser;
    }

    /**
     * Check if a user is authenticated.
     *
     * @return bool True if a user is authenticated, false otherwise.
     */
    public function isAuthenticated(): bool
    {
        return $this->authenticatedUser !== null;
    }

    /**
     * Reset the current authentication state.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->authenticatedUser = null;
        $this->authenticatedUsing = null;
    }

    /**
     * Check if a guard is registered.
     *
     * @param string $name The name of the guard.
     * @return bool True if the guard is registered, false otherwise.
     */
    public function hasGuard(string $name): bool
    {
        return isset($this->guards[$name]);
    }
}
