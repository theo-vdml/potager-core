<?php

namespace Potager\Console\IO;

/**
 * Represents a console command input argument.
 *
 * Provides a fluent interface to configure an argument's name, description,
 * default value, and whether it is required or should prompt when missing.
 */
class InputArgument
{
    /** @var string The name of the argument. */
    private string $name;

    /** @var string A short description of the argument. */
    private string $description = '';

    /** @var mixed The default value used when the argument is not provided. */
    private mixed $default = null;

    /** @var bool Whether the argument is required. */
    private bool $required = true;

    /** @var bool Whether the argument can accept multiple values. */
    private bool $multiple = false;

    /** @var bool Whether the user should be prompted when the argument is missing. */
    private bool $promptMissing = false;

    /**
     * @param string $name The name of the argument.
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Creates a new InputArgument instance.
     *
     * @param string $name The name of the argument.
     * @return self
     */
    static public function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Sets the description of the argument.
     *
     * @param string $description A short description of the argument.
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Sets the default value for the argument.
     *
     * @param mixed $default The default value to use when the argument is not provided.
     * @return self
     */
    public function default(mixed $default): self
    {
        $this->default = $default;
        return $this;
    }

    /**
     * Marks the argument as optional (or required).
     *
     * @param bool $optional Whether the argument is optional. Defaults to true.
     * @return self
     */
    public function optional(bool $optional = true): self
    {
        $this->required = !$optional;
        return $this;
    }

    /**
     * Marks the argument as accepting multiple values.
     *
     * @param bool $multiple Whether the argument can accept multiple values. Defaults to true.
     * @return self
     */
    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;
        return $this;
    }

    /**
     * Configures whether the user should be prompted when the argument is missing.
     *
     * @param bool $prompt Whether to prompt for the missing argument. Defaults to true.
     * @return self
     */
    public function promptMissing(bool $prompt = true): self
    {
        $this->promptMissing = $prompt;
        return $this;
    }

    /**
     * Returns the name of the argument.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the description of the argument.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Returns the default value of the argument.
     *
     * @return mixed
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Returns whether the argument is required.
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Returns whether the argument is optional.
     *
     * @return bool
     */
    public function isOptional(): bool
    {
        return !$this->isRequired();
    }

    /**
     * Returns whether the argument can accept multiple values.
     *
     * @return bool
     */
    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Returns whether the user should be prompted when the argument is missing.
     *
     * @return bool
     */
    public function shouldPromptMissing(): bool
    {
        return $this->promptMissing;
    }
}
