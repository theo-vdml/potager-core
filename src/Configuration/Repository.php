<?php

namespace Potager\Configuration;

use Potager\Contracts\Configuration\RepositoryInterface;
use Potager\Support\Arr;

/**
 * Repository — central config store
 */
class Repository implements RepositoryInterface
{
    /**
     * Merged configuration data.
     *
     * @var array<string, mixed>
     */
    protected array $items;

    /**
     * @param array<string, mixed> $items Pre-built config array (optional).
     *                                    Pass your data directly to avoid filesystem I/O in tests.
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    // ─── Static factories ─────────────────────────────────────────────────────

    /**
     * Build a Repository from a directory of PHP config files.
     *
     * Each file becomes a top-level key:
     *   config/database.php  →  get('database.driver')
     *   config/mail.php      →  get('mail.default')
     *
     * Silently returns an empty repository when the directory does not exist.
     *
     * @param  string $directory Absolute path to the config/ directory.
     * @return static
     */
    public static function fromDirectory(string $directory): static
    {
        return new static(ConfigurationLoader::fromDirectory($directory));
    }

    /**
     * Build a ConfigRepository from a single flat PHP config file.
     * Useful for backward compatibility with a single config/config.php.
     *
     * @param  string $filePath Absolute path to the file.
     * @return static
     */
    public static function fromFile(string $filePath): static
    {
        return new static(ConfigurationLoader::fromFile($filePath));
    }

    // ─── Interface implementation ─────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->items, $key, $default);
    }

    /**
     * {@inheritdoc}
     *
     * Supports dot notation — intermediate arrays are created automatically.
     * Ideal for overriding values in test setUp() methods.
     */
    public function set(string $key, mixed $value): void
    {
        Arr::set($this->items, $key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return Arr::has($this->items, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->items;
    }
}
