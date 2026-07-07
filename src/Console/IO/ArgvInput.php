<?php

namespace Potager\Console\IO;

use Potager\Support\Arr;

/**
 * Parses console input from the command-line argument vector (argv).
 *
 * Reads tokens from $_SERVER['argv'] by default (with the script name stripped)
 * and parses them against a bound {@see InputDefinition} into arguments and options.
 */
class ArgvInput extends Input
{
    /** @var array<string> Raw tokens from the argument vector. */
    private array $tokens;

    /** @var array<string> Remaining tokens during parsing. */
    private array $parsed;

    /**
     * @param array<string>|null $argv The argument vector to parse. Defaults to $_SERVER['argv'].
     *
     * @throws \InvalidArgumentException If any element of the argv array is not a scalar value.
     */
    public function __construct(?array $argv = null)
    {
        $argv ??= $_SERVER['argv'] ?? [];

        foreach ($argv as $arg) {
            if (!is_scalar($arg)) {
                throw new \InvalidArgumentException('All elements of the argv array are expected to be scalar values.');
            }
        }

        $this->scriptName = array_shift($argv) ?? '';

        foreach ($argv as $i => $arg) {
            if (!str_starts_with($arg, '-')) {
                $this->commandName = $arg;
                unset($argv[$i]);
                break;
            }
        }

        $this->tokens = array_values($argv);
    }

    /**
     * Parses all tokens from the argument vector against the bound definition.
     */
    protected function parseDefinition(): void
    {
        $this->parsed = $this->tokens;
        $shouldParseOptions = true;

        while (null !== $token = array_shift($this->parsed)) {
            $shouldParseOptions = $this->parseToken($token, $shouldParseOptions);
        }
    }

    /**
     * Dispatches a single token to the appropriate parse method.
     *
     * A bare `--` token disables option parsing for all subsequent tokens.
     *
     * @param string $token             The raw token to parse.
     * @param bool   $shouldParseOptions Whether option parsing is still active.
     * @return bool Whether option parsing should remain active after this token.
     */
    public function parseToken(string $token, bool $shouldParseOptions): bool
    {
        if ($shouldParseOptions && '--' === $token) {
            return false;
        } else if ($shouldParseOptions && str_starts_with($token, '--')) {
            $this->parseOption($token);
        } else if ($shouldParseOptions && str_starts_with($token, '-') && strlen($token) > 1) {
            $this->parseShortOption($token);
        } else {
            $this->parseArgument($token);
        }

        return $shouldParseOptions;
    }

    /**
     * Parses a positional argument token and stores its value.
     *
     * Supports multiple-value arguments by appending to an existing entry.
     *
     * @param string $token The raw argument value.
     *
     * @throws \InvalidArgumentException If more positional arguments are provided than defined.
     */
    public function parseArgument(string $token): void
    {
        $count = count($this->arguments);

        if ($this->definition->hasArgument($count)) {
            $argument = $this->definition->getArgument($count);
            $this->addArgument($argument->getName(), $token);
        } else if ($this->definition->hasArgument($count - 1) && $this->definition->getArgument($count - 1)->isMultiple()) {
            $argument = $this->definition->getArgument($count - 1);
            $this->addArgument($argument->getName(), $token);
        } else {
            throw new \InvalidArgumentException(sprintf('Too many arguments'));
        }
    }

    /**
     * Parses a long option token (e.g. `--name` or `--name=value`).
     *
     * @param string $token The raw token, including the leading `--`.
     */
    public function parseOption(string $token): void
    {
        $name = substr($token, 2);

        if (str_contains($name, '=')) {
            [$name, $value] = explode('=', $name, 2);
            $this->addOption($name, $value);
        } else {
            $this->addOption($name, null);
        }
    }

    /**
     * Parses a short option token (e.g. `-v` or `-vvv` or `-f value`).
     *
     * Characters are consumed one at a time. If an option accepts a value,
     * the remaining characters are treated as the value and parsing stops.
     *
     * @param string $token The raw token, including the leading `-`.
     *
     * @throws \InvalidArgumentException If an undefined shortcut is encountered.
     */
    public function parseShortOption(string $token): void
    {
        $name = substr($token, 1);
        $encoding = mb_detect_encoding($name, null, true);
        $chars = mb_str_split($name, 1, $encoding ?: null);

        while (null !== $char = array_shift($chars)) {
            if (!$this->definition->hasShortcut($char)) {
                throw new \InvalidArgumentException(sprintf('The "-%s" option does not exist.', $char));
            }

            $option = $this->definition->getOptionForShortcut($char);

            if ($option->acceptsValue()) {
                $value = count($chars) > 0 ? implode('', $chars) : null;
                $this->addShortOption($char, $value);
                break;
            }

            $this->addShortOption($char, null);
        }
    }

    /**
     * Resolves a shortcut to its full option name and delegates to {@see addOption()}.
     *
     * @param string      $shortcut The shortcut character (without leading dash).
     * @param string|null $value    The value to assign, or null for flags.
     *
     * @throws \InvalidArgumentException If the shortcut is not defined.
     */
    public function addShortOption(string $shortcut, ?string $value = null): void
    {
        if (!$this->definition->hasShortcut($shortcut)) {
            throw new \InvalidArgumentException(sprintf('The "-%s" option does not exist.', $shortcut));
        }

        $option = $this->definition->getOptionForShortcut($shortcut);
        $this->addOption($option->getName(), $value);
    }

    /**
     * Stores a parsed positional argument value.
     *
     * For multiple-value arguments the value is appended; otherwise it replaces.
     *
     * @param string $name  The argument name as defined in the definition.
     * @param string $value The parsed value.
     *
     * @throws \InvalidArgumentException If the argument name is not defined.
     */
    public function addArgument(string $name, string $value): void
    {
        if (!$this->definition->hasArgument($name)) {
            throw new \InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        $argument = $this->definition->getArgument($name);

        if ($argument->isMultiple()) {
            $this->arguments[$name][] = $value;
        } else {
            $this->arguments[$name] = $value;
        }
    }

    /**
     * Stores a parsed option value.
     *
     * If no inline value is provided and the option accepts one, the next token
     * is consumed from the parsed queue when it does not look like another option.
     *
     * @param string      $name  The option name (without leading dashes).
     * @param string|null $value The inline value, or null if none was provided.
     *
     * @throws \InvalidArgumentException If the option is not defined, if a value is given to a flag,
     *                                   or if a required value is missing.
     */
    public function addOption(string $name, ?string $value = null): void
    {
        if (!$this->definition->hasOption($name)) {
            throw new \InvalidArgumentException(sprintf('The "--%s" option does not exist.', $name));
        }

        $option = $this->definition->getOption($name);

        if (null !== $value && !$option->acceptsValue()) {
            throw new \InvalidArgumentException(sprintf('The "--%s" option does not accept a value.', $name));
        }

        if ($value === null && $option->acceptsValue() && count($this->parsed) > 0) {

            $next = array_shift($this->parsed);

            if ((isset($next[0]) && '-' !== $next[0]) || in_array($next, ['', null], true) || is_numeric($next)) {
                $value = $next;
            } else {
                array_unshift($this->parsed, $next);
            }
        }

        if ($value === null) {
            if ($option->isValueRequired()) {
                throw new \InvalidArgumentException(sprintf('The "--%s" option requires a value.', $name));
            }

            if (!$option->acceptsValue()) {
                $value = true;
            }
        }

        if ($option->isMultiple()) {
            $this->options[$name][] = $value;
        } else {
            $this->options[$name] = $value;
        }
    }

    /**
     * Returns whether any of the given raw option tokens are present in the input.
     *
     * @param string|array<string> $values      The option name(s) or token(s) to check.
     * @param bool                 $onlyParams  Whether to ignore tokens after a bare `--`.
     * @return bool
     */
    public function hasRawOption(string|array $values, bool $onlyParams = true): bool
    {
        $values = Arr::wrap($values);

        foreach ($this->tokens as $token) {

            if ($onlyParams && $token === '--') {
                return false;
            }

            foreach ($values as $value) {

                if ($token === $value) {
                    return true;
                }

                $prefix = str_starts_with($value, '--') ? "$value=" : $value;

                if ($prefix !== '' && str_starts_with($token, $prefix)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Returns the value of a raw option token.
     *
     * @param string|array<string> $options      The option name(s) or token(s) to check.
     * @param mixed                $default      The default value to return if the option is not found.
     * @param bool                 $onlyParams  Whether to ignore tokens after a bare `--`.
     * @return mixed
     */
    public function getRawOption(string|array $options, mixed $default = true, bool $onlyParams = true): mixed
    {
        $tokens = $this->tokens;
        $values = Arr::wrap($options);

        while (0 < count($tokens)) {
            $token = array_shift($tokens);
            if ($onlyParams && '--' === $token) {
                return $default;
            }

            foreach ($values as $value) {
                if ($token === $value) {
                    $next = array_shift($tokens);
                    if (null === $next || str_starts_with($next, '-')) {
                        return true;
                    }
                    return $next;
                }
                $prefix = str_starts_with($value, '--') ? "$value=" : $value;

                if ($prefix !== '' && str_starts_with($token, $prefix)) {
                    return substr($token, strlen($prefix));
                }
            }
        }

        return $default;
    }
}
