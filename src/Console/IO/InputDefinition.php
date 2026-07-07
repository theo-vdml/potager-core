<?php

namespace Potager\Console\IO;

/**
 * Holds the full definition of a console command's expected input.
 *
 * Aggregates {@see InputArgument} and {@see InputOption} instances and enforces
 * ordering constraints (e.g. required arguments cannot follow optional ones).
 */
class InputDefinition
{
    /** @var string|null The name of the command this definition belongs to. */
    private ?string $commandName = null;

    /** @var array<string, InputArgument> Registered arguments, keyed by name. */
    private array $arguments = [];

    /** @var array<string, InputOption> Registered options, keyed by name. */
    private array $options = [];

    /** @var array<string, string> Map of shortcut characters to option names. */
    private array $shortcuts = [];

    /** @var InputArgument|null Tracks the last optional argument to enforce ordering rules. */
    private ?InputArgument $lastOptionalArgument = null;

    /** @var InputArgument|null Tracks the last multiple-value argument to enforce ordering rules. */
    private ?InputArgument $lastMultipleArgument = null;

    /**
     * @param array<InputArgument|InputOption> $definition An optional list of arguments and options to register.
     *
     * @throws \InvalidArgumentException If any item is not an InputArgument or InputOption.
     */
    public function __construct(array $definition = [])
    {
        foreach ($definition as $item) {
            if ($item instanceof InputArgument) {
                $this->addArgument($item);
            } elseif ($item instanceof InputOption) {
                $this->addOption($item);
            } else {
                throw new \InvalidArgumentException(sprintf('Definition items must be instances of InputArgument or InputOption, got "%s".', get_debug_type($item)));
            }
        }
    }

    /**
     * Registers an argument in the definition.
     *
     * Enforces the following constraints:
     * - Argument names must be unique.
     * - No argument may follow a multiple-value argument.
     * - Required arguments may not follow optional ones.
     *
     * @param InputArgument $argument The argument to register.
     *
     * @throws \LogicException If any ordering or uniqueness constraint is violated.
     */
    public function addArgument(InputArgument $argument): void
    {
        if (isset($this->arguments[$argument->getName()])) {
            throw new \LogicException(sprintf('An argument with name "%s" already exists.', $argument->getName()));
        }

        if (null !== $this->lastMultipleArgument) {
            throw new \LogicException(sprintf('Cannot add argument "%s" after argument "%s", which accepts multiple values.', $argument->getName(), $this->lastMultipleArgument->getName()));
        }

        if (null !== $this->lastOptionalArgument && $argument->isRequired()) {
            throw new \LogicException(sprintf('Cannot add required argument "%s" after optional argument "%s".', $argument->getName(), $this->lastOptionalArgument->getName()));
        }

        if ($argument->isOptional()) {
            $this->lastOptionalArgument = $argument;
        }

        if ($argument->isMultiple()) {
            $this->lastMultipleArgument = $argument;
        }

        $this->arguments[$argument->getName()] = $argument;
    }

    /**
     * Returns all registered arguments, keyed by name.
     *
     * @return array<string, InputArgument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Returns whether an argument with the given name is registered.
     *
     * @param string|int $name The argument name or position.
     * @return bool
     */
    public function hasArgument(string|int $name): bool
    {
        $arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;

        return isset($arguments[$name]);
    }

    /**
     * Returns the argument registered under the given name or position, or null if not found.
     *
     * @param string|int $name The argument name or position.
     * @return InputArgument|null
     */
    public function getArgument(string|int $name): ?InputArgument
    {
        $arguments = is_int($name) ? array_values($this->arguments) : $this->arguments;

        return $arguments[$name] ?? null;
    }

    /**
     * Registers an option in the definition.
     *
     * If the option has a shortcut, it is also indexed for fast lookup.
     *
     * @param InputOption $option The option to register.
     *
     * @throws \LogicException If an option with the same name already exists.
     */
    public function addOption(InputOption $option): void
    {
        if (isset($this->options[$option->getName()])) {
            throw new \LogicException(sprintf('An option with name "%s" already exists.', $option->getName()));
        }

        $this->options[$option->getName()] = $option;

        if ($shortcut = $option->getShortcut()) {
            $this->shortcuts[$shortcut] = $option->getName();
        }
    }

    /**
     * Returns all registered options, keyed by name.
     *
     * @return array<string, InputOption>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns whether an option with the given name is registered.
     *
     * @param string $name The option name (without leading dashes).
     * @return bool
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Returns the option registered under the given name, or null if not found.
     *
     * @param string $name The option name (without leading dashes).
     * @return InputOption|null
     */
    public function getOption(string $name)
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Returns whether a shortcut character is registered for any option.
     *
     * @param string $shortcut The shortcut character (without leading dash).
     * @return bool
     */
    public function hasShortcut(string $shortcut): bool
    {
        return isset($this->shortcuts[$shortcut]);
    }

    /**
     * Resolves a shortcut character to its corresponding option name.
     *
     * @param string $shortcut The shortcut character (without leading dash).
     * @return string The full option name.
     *
     * @throws \LogicException If no option is registered with the given shortcut.
     */
    public function shortcutToName(string $shortcut): string
    {
        if (!$this->hasShortcut($shortcut)) {
            throw new \LogicException(sprintf('No option with shortcut "%s" exists.', $shortcut));
        }

        return $this->shortcuts[$shortcut];
    }

    /**
     * Returns the option associated with the given shortcut character.
     *
     * @param string $shortcut The shortcut character (without leading dash).
     * @return InputOption|null
     *
     * @throws \LogicException If no option is registered with the given shortcut.
     */
    public function getOptionForShortcut(string $shortcut)
    {
        return $this->getOption($this->shortcutToName($shortcut));
    }

    /**
     * Sets the name of the command this definition belongs to.
     *
     * @param string $name The command name.
     */
    public function setCommandName(string $name): void
    {
        $this->commandName = $name;
    }

    /**
     * Returns the name of the command this definition belongs to, or null if not set.
     *
     * @return string|null
     */
    public function getCommandName(): ?string
    {
        return $this->commandName;
    }
}
