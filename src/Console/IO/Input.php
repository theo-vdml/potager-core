<?php

namespace Potager\Console\IO;

use Potager\Contracts\Console\InputInterface;

/**
 * Base class for console input implementations.
 *
 * Handles binding an {@see InputDefinition}, validating required arguments,
 * and providing access to parsed arguments and options.
 */
abstract class Input implements InputInterface
{
    /** @var string|null The name of the script being executed (e.g. "farmer"). */
    protected ?string $scriptName = null;

    /** @var string|null The name of the command being executed (e.g. "make:model"). */
    protected ?string $commandName = null;

    /** @var InputDefinition|null The bound input definition, or null if not yet bound. */
    protected ?InputDefinition $definition = null;

    /** @var array<string, mixed> Parsed argument values, keyed by argument name. */
    protected array $arguments = [];

    /** @var array<string, mixed> Parsed option values, keyed by option name. */
    protected array $options = [];

    /** @var bool Whether the input is interactive (prompts are allowed). */
    protected bool $interactive = true;

    /**
     * Binds an input definition, parses it, and validates the input.
     *
     * @param InputDefinition $definition The definition describing expected arguments and options.
     */
    public function bindDefinition(InputDefinition $definition): void
    {
        $this->definition = $definition;
        $this->parseDefinition();
    }

    /**
     * Parses raw input against the bound definition.
     *
     * Implementations are responsible for populating {@see $arguments} and {@see $options}.
     */
    abstract protected function parseDefinition(): void;

    /**
     * Validates the parsed input against the bound definition.
     *
     * Fills in default values for missing arguments and options, and throws
     * if a required argument has no value.
     *
     * @throws \LogicException If no definition has been bound.
     * @throws \RuntimeException If a required argument is missing.
     */
    public function validate(): void
    {
        if (!$this->definition) {
            throw new \LogicException('Input definition must be bound before validation.');
        }

        foreach ($this->definition->getArguments() as $argument) {
            $name = $argument->getName();

            if (!array_key_exists($name, $this->arguments)) {
                if ($argument->isRequired()) {
                    throw new \RuntimeException("Missing required argument: $name");
                }
                $this->arguments[$name] = $argument->getDefault();
            }
        }

        foreach ($this->definition->getOptions() as $option) {
            $name = $option->getName();

            if (!array_key_exists($name, $this->options)) {
                $this->options[$name] = $option->getDefault();
            }
        }
    }

    /**
     * Ensures a definition has been bound, throwing if not.
     *
     * @throws \LogicException If no definition has been bound.
     */
    protected function ensureDefinitionBound(): void
    {
        if (!$this->definition) {
            throw new \LogicException('Input definition must be bound before accessing arguments or options.');
        }
    }

    /**
     * Returns the name of the script being executed.
     *
     * @return string|null
     */
    public function getScriptName(): ?string
    {
        return $this->scriptName;
    }

    /**
     * Returns the name of the command being executed.
     *
     * @return string|null
     */
    public function getCommandName(): ?string
    {
        return $this->commandName;
    }

    /**
     * Sets the name of the command being executed.
     *
     * @param string|null $name
     */
    public function setCommandName(?string $name): void
    {
        $this->commandName = $name;
    }

    /**
     * Returns all parsed arguments, keyed by name.
     *
     * @return array<string, mixed>
     *
     * @throws \LogicException If no definition has been bound.
     */
    public function getArguments(): array
    {
        $this->ensureDefinitionBound();

        return $this->arguments;
    }

    /**
     * Returns the value of the named argument.
     *
     * @param string $name The argument name.
     * @return mixed The argument value, or null if not set.
     *
     * @throws \LogicException If no definition has been bound.
     */
    public function argument(string $name): mixed
    {
        $this->ensureDefinitionBound();

        return $this->arguments[$name] ?? null;
    }

    /**
     * Returns whether the named argument has a value.
     *
     * @param string $name The argument name.
     * @return bool
     *
     * @throws \LogicException If no definition has been bound.
     */
    public function hasArgument(string $name): bool
    {
        $this->ensureDefinitionBound();

        return array_key_exists($name, $this->arguments);
    }

    /**
     * Overrides the value of the named argument.
     *
     * @param string $name  The argument name.
     * @param mixed  $value The new value.
     *
     * @throws \LogicException          If no definition has been bound.
     * @throws \InvalidArgumentException If the argument is not defined.
     */
    public function setArgument(string $name, mixed $value): void
    {
        $this->ensureDefinitionBound();

        if (!array_key_exists($name, $this->definition->getArguments())) {
            throw new \InvalidArgumentException("Argument '$name' is not defined.");
        }

        $this->arguments[$name] = $value;
    }

    /**
     * Returns all parsed options, keyed by name.
     *
     * @return array<string, mixed>
     *
     * @throws \LogicException If no definition has been bound.
     */
    public function getOptions(): array
    {
        $this->ensureDefinitionBound();

        return $this->options;
    }

    /**
     * Returns the value of the named option.
     *
     * @param string $name The option name (without leading dashes).
     * @return mixed The option value, or null if not set.
     *
     * @throws \LogicException If no definition has been bound.
     */
    public function option(string $name): mixed
    {
        $this->ensureDefinitionBound();

        return $this->options[$name] ?? null;
    }

    /**
     * Returns whether the named option has a value.
     *
     * @param string $name The option name (without leading dashes).
     * @return bool
     *
     * @throws \LogicException If no definition has been bound.
     */
    public function hasOption(string $name): bool
    {
        $this->ensureDefinitionBound();

        return array_key_exists($name, $this->options);
    }

    /**
     * Overrides the value of the named option.
     *
     * @param string $name  The option name (without leading dashes).
     * @param mixed  $value The new value.
     *
     * @throws \LogicException          If no definition has been bound.
     * @throws \InvalidArgumentException If the option is not defined.
     */
    public function setOption(string $name, mixed $value): void
    {
        $this->ensureDefinitionBound();

        if (!array_key_exists($name, $this->definition->getOptions())) {
            throw new \InvalidArgumentException("Option '$name' is not defined.");
        }

        $this->options[$name] = $value;
    }

    /**
     * Returns whether the input is interactive.
     *
     * @return bool
     */
    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    /**
     * Sets whether the input is interactive.
     *
     * @param bool $interactive Pass false to disable prompts.
     */
    public function setInteractive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }
}
