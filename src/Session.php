<?php

namespace Potager;

use Potager\Support\Arr;

/**
 * Class Session
 *
 * A session handler class that manages classic session values as well as flash session messages.
 * Flash messages persist only for the next request.
 */
class Session
{
    /**
     * Namespace key used to isolate classic session data.
     * Prevents collisions with flash messages or other session segments like authentication.
     *
     * @var string
     */
    protected string $defaultNamespace;

    /**
     * Namespace key used to isolate flash session data.
     * Ensures flash messages are stored separately from other session data.
     *
     * @var string
     */
    protected string $flashNamespace;

    /**
     * Holds flash data to be saved and made available on the next request.
     * This buffer collects flash messages before they are committed to the session.
     *
     * @var array<string, mixed>
     */
    protected array $stagedFlashes = [];

    /**
     * Flash data retrieved from the previous request and available during the current request lifecycle.
     *
     * @var array<string, mixed>
     */
    protected array $activeFlashes = [];

    /**
     * Flag indicating whether the shutdown function for committing flash data has been registered.
     *
     * @var bool
     */
    protected bool $flashCommitRegistered = false;

    /**
     * Session constructor.
     *
     * Initializes session namespaces and starts the session if not already started.
     * Also loads flash data from the previous request.
     *
     * @param string $defaultNamespace Namespace key for standard session data.
     * @param string $flashNamespace Namespace key for flash session data.
     */
    public function __construct(string $defaultNamespace = '__session', string $flashNamespace = '__flash')
    {
        $this->defaultNamespace = $defaultNamespace;
        $this->flashNamespace = $flashNamespace;

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->rotateFlash();
    }

    /**
     * Store a value in the session using dot notation
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function set(string $key, mixed $value): static
    {
        Arr::set($_SESSION, "$this->defaultNamespace.$key", $value);
        return $this;
    }

    /**
     * Check if a session key exists using dot notation
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return Arr::has($_SESSION, "$this->defaultNamespace.$key");
    }

    /**
     * Retrieve a value from the session using dot notation
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($_SESSION, "$this->defaultNamespace.$key", $default);
    }

    /**
     * Retrieve a value and remove it from the session using dot notation
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->remove($key);
        return $value;
    }

    /**
     * Get all session values in the namespace.
     *
     * @return array
     */
    public function all(): array
    {
        return $_SESSION[$this->defaultNamespace] ?? [];
    }

    /**
     * Remove a key from the session using dot notation
     *
     * @param string $key
     * @return $this
     */
    public function remove(string $key): static
    {
        Arr::forget($_SESSION, "$this->defaultNamespace.$key");
        return $this;
    }

    /**
     * Clear all session data in the namespace.
     *
     * @return void
     */
    public function clear(): void
    {
        unset($_SESSION[$this->defaultNamespace]);
    }

    /**
     * Regenerate the session ID.
     *
     * @param bool $delete_old
     * @return void
     */
    public function regenerate(bool $delete_old = false): void
    {
        session_regenerate_id($delete_old);
    }

    /**
     * Flash a value for the next request.
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function flash(string $key, mixed $value): static
    {
        Arr::set($this->stagedFlashes, $key, $value);
        $this->register();
        return $this;
    }

    /**
     * Retrieve a flash value for the current request.
     *
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->activeFlashes ?? [], $key, $default);
    }

    /**
     * Retrieve all flash values for the current request.
     *
     * @return array
     */
    public function getAllFlashes(): array
    {
        return $this->activeFlashes ?? [];
    }

    /**
     * Preserve flash values for another request cycle.
     * 
     * @param string ...$keys The keys to preserve
     * @return void
     */
    public function preserveFlashes(string ...$keys): void
    {
        if (empty($keys)) {
            $this->stagedFlashes = array_merge($this->stagedFlashes, $this->activeFlashes);
        } else {
            foreach ($keys as $key) {
                if (array_key_exists($key, $this->activeFlashes)) {
                    $this->stagedFlashes[$key] = $this->activeFlashes[$key];
                }
            }
        }
    }

    /**
     * Commit flash buffer to session storage.
     *
     * @return void
     */
    public function commitFlash(): void
    {
        if (!empty($this->stagedFlashes))
            $_SESSION[$this->flashNamespace] = $this->stagedFlashes;
    }

    /**
     * Rotate flash values: make them available and remove from session.
     *
     * @return void
     */
    public function rotateFlash(): void
    {
        $this->activeFlashes = $_SESSION[$this->flashNamespace] ?? [];
        unset($_SESSION[$this->flashNamespace]);
    }

    /**
     * Register a shutdown function to persist flash data.
     *
     * @return void
     */
    protected function register(): void
    {
        if ($this->flashCommitRegistered)
            return;
        register_shutdown_function(fn() => $this->commitFlash());
        $this->flashCommitRegistered = true;
    }
}