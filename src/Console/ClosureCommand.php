<?php

namespace Potager\Console;

use Potager\Container\Container;

/**
 * A console command defined by a closure.
 *
 * Wraps an arbitrary {@see \Closure} as a named command, providing a fluent
 * interface to attach an input definition and description without requiring
 * a dedicated command class.
 */
class ClosureCommand extends Command
{
    /** @var \Closure The handler invoked when the command runs. */
    private \Closure $handler;

    /** @var array<\Potager\Console\IO\InputArgument|\Potager\Console\IO\InputOption> The input definition for the command. */
    private array $inputDefinition = [];

    /**
     * Returns the input definition for this command.
     *
     * @return array<\Potager\Console\IO\InputArgument|\Potager\Console\IO\InputOption>
     */
    protected function getInputDefinition(): array
    {
        return $this->inputDefinition;
    }

    /**
     * @param string   $name    The name of the command (e.g. "mail:send").
     * @param \Closure $handler The closure to invoke when the command is executed.
     */
    public function __construct(string $name, \Closure $handler)
    {
        $this->name = $name;
        $this->handler = $handler;
    }

    /**
     * Executes the closure handler via the container.
     *
     * The resolved input and output instances are injected into the closure.
     * If the closure does not return an integer, 0 (success) is returned.
     *
     * @param Container $container The application container used to resolve and call the handler.
     * @return int The exit code (0 for success, non-zero for failure).
     */
    public function handle(Container $container): int
    {
        $result = $container->call($this->handler, [
            'input' => $this->input,
            'output' => $this->output,
        ]);

        return is_int($result) ? $result : 0;
    }

    /**
     * Sets the input definition for the command.
     *
     * @param array<\Potager\Console\IO\InputArgument|\Potager\Console\IO\InputOption> $definition
     * @return self
     */
    public function withInputDefinition(array $definition): self
    {
        $this->inputDefinition = $definition;
        return $this;
    }

    /**
     * Sets the description of the command.
     *
     * @param string $description A short description shown in the command list.
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }
}
