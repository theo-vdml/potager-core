<?php

namespace Potager\Console\IO;

/**
 * Represents a console command input option (e.g. --verbose or -v).
 *
 * Provides a fluent interface to configure an option's name, shortcut,
 * description, default value, and whether it accepts a value or multiple values.
 */
class InputOption
{

    /** @var string The long name of the option (without leading dashes). */
    private string $name;

    /** @var string|null The optional single-character shortcut (without leading dash). */
    private ?string $shortcut = null;

    /** @var string A short description of the option. */
    private string $description = '';

    /** @var mixed The default value used when the option is not provided. */
    private mixed $default = null;

    /** @var bool Whether the option accepts a value. */
    private bool $acceptValue = false;

    /** @var bool Whether the option requires a value. */
    private bool $valueRequired = false;

    /** @var bool Whether the option can be specified multiple times. */
    private bool $multiple = false;

    /**
     * Leading dashes are stripped from the name automatically.
     *
     * @param string $name The long name of the option.
     */
    public function __construct(string $name)
    {
        $this->name = ltrim($name, '-');
    }

    /**
     * Creates a new InputOption instance.
     *
     * @param string $name The long name of the option.
     * @return self
     */
    static public function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Sets the shortcut character for the option.
     *
     * Leading dashes are stripped automatically.
     *
     * @param string $shortcut The shortcut character (e.g. "v" for -v).
     * @return self
     */
    public function shortcut(string $shortcut): self
    {
        $this->shortcut = ltrim($shortcut, '-');
        return $this;
    }

    /**
     * Sets the description of the option.
     *
     * @param string $description A short description of the option.
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Sets the default value for the option.
     *
     * @param mixed $default The default value to use when the option is not provided.
     * @return self
     */
    public function default(mixed $default): self
    {
        $this->default = $default;
        return $this;
    }

    /**
     * Configures whether the option accepts a value.
     *
     * @param bool $acceptValue Whether the option requires a value. Defaults to true.
     * @return self
     */
    public function acceptValue(bool $acceptValue = true): self
    {
        $this->acceptValue = $acceptValue;
        $this->valueRequired = $acceptValue ? $this->valueRequired : false;
        return $this;
    }

    /**
     * Configures whether the option requires a value.
     *
     * @param bool $requireValue Whether the option requires a value. Defaults to true.
     * @return self
     */
    public function requireValue(bool $requireValue = true): self
    {
        $this->valueRequired = $requireValue;
        $this->acceptValue = $requireValue ? true : $this->acceptValue;
        return $this;
    }

    /**
     * Allows the option to be specified multiple times.
     *
     * Automatically enables value acceptance.
     *
     * @param bool $multiple Whether the option can be repeated. Defaults to true.
     * @return self
     */
    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;
        $this->acceptValue = $multiple ? true : $this->acceptValue;
        return $this;
    }

    /**
     * Configures the option as a boolean flag (no value, not repeatable).
     *
     * @return self
     */
    public function flag(): self
    {
        $this->acceptValue = false;
        $this->multiple = false;
        return $this;
    }

    /**
     * Returns the long name of the option.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the shortcut character of the option, or null if none is set.
     *
     * @return string|null
     */
    public function getShortcut(): ?string
    {
        return $this->shortcut;
    }

    /**
     * Returns the description of the option.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Returns the resolved default value for the option.
     *
     * - For flags: returns a boolean (false if not explicitly set).
     * - For multiple options: returns an array (empty if not explicitly set).
     * - Otherwise: returns the raw default value.
     *
     * @return mixed
     */
    public function getDefault(): mixed
    {
        if (!$this->acceptValue) {
            return is_bool($this->default) ? $this->default : false;
        } else if ($this->multiple) {
            return is_array($this->default) ? $this->default : [];
        }

        return $this->default;
    }

    /**
     * Returns whether the option accepts a value.
     *
     * @return bool
     */
    public function acceptsValue(): bool
    {
        return $this->acceptValue;
    }

    /**
     * Returns whether the option requires a value.
     *
     * @return bool
     */
    public function isValueRequired(): bool
    {
        return $this->acceptsValue() && $this->valueRequired;
    }

    /**
     * Returns whether the option can be specified multiple times.
     *
     * @return bool
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Returns whether the option is a boolean flag (accepts no value).
     *
     * @return bool
     */
    public function isFlag(): bool
    {
        return !$this->acceptValue;
    }
}
