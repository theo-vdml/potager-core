<?php

namespace Potager\Console;

use Potager\Console\Commands\CommandListCommand;
use Potager\Console\Commands\MakeCommandCommand;
use Potager\Console\Commands\MakeControllerCommand;
use Potager\Console\Commands\MakeMiddlewareCommand;
use Potager\Console\Commands\MakeModelCommand;
use Potager\Console\Commands\RouteListCommand;
use Potager\Console\Exceptions\CommandNotFoundException;
use Potager\Console\IO\Output;
use Potager\Console\Pipes\HelpPipe;
use Potager\Console\Pipes\VersionPipe;
use Potager\Container\Container;
use Potager\Contracts\Console\InputInterface;

/**
 * Manages console command registration, resolution, and execution.
 *
 * Handles the full lifecycle of console commands: bootstrapping built-in commands,
 * registering custom commands, resolving by name, and executing with input/output.
 * Supports middleware-style pipes for cross-cutting concerns (help, version).
 */
class Kernel
{
    /** @var Container The application container used to resolve command dependencies. */
    private Container $container;

    /** @var array<string, Command> All registered commands, keyed by command name. */
    private array $commands = [];

    /** @var array<class-string> Pipe classes to execute in reverse order before the command. */
    private array $pipes = [
        HelpPipe::class,
        VersionPipe::class,
    ];

    /**
     * @param Container $container The application container.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Bootstraps the kernel by registering all built-in commands.
     */
    public function bootstrap(): void
    {
        $this->registerBuiltins();
    }

    /**
     * Registers all built-in commands provided by the framework.
     */
    public function registerBuiltins(): void
    {
        $builtinCommands = [
            CommandListCommand::class,
            MakeCommandCommand::class,
            MakeControllerCommand::class,
            MakeMiddlewareCommand::class,
            MakeModelCommand::class,
            RouteListCommand::class,
        ];

        foreach ($builtinCommands as $commandClass) {
            $this->register($commandClass);
        }
    }

    /**
     * Registers a command class instance.
     *
     * The class must extend {@see Command}. The command is instantiated
     * and stored under its name.
     *
     * @param class-string<Command> $commandClass The fully-qualified command class name.
     */
    public function register(string $commandClass): void
    {
        /** @var Command $instance */
        $instance = $this->container->make($commandClass);
        $this->commands[$instance->getName()] = $instance;
    }

    /**
     * Registers an inline closure command.
     *
     * Creates a {@see ClosureCommand} instance wrapping the handler and returns
     * it for further fluent configuration (e.g., input definition, description).
     *
     * @param string   $name    The command name (e.g. "mail:send").
     * @param \Closure $handler The closure to invoke when the command is executed.
     * @return ClosureCommand The registered command instance for chaining.
     */
    public function command(string $name, \Closure $handler): ClosureCommand
    {
        $instance = new ClosureCommand($name, $handler);
        $this->commands[$instance->getName()] = $instance;
        return $instance;
    }

    /**
     * Resolves a command by name.
     *
     * @param string $name The command name.
     * @return Command The resolved command instance.
     *
     * @throws CommandNotFoundException If the command is not registered.
     */
    public function resolve(string $name): Command
    {
        if (!$this->has($name)) {
            throw new CommandNotFoundException($name);
        }

        return $this->commands[$name];
    }

    /**
     * Returns whether a command with the given name is registered.
     *
     * @param string $name The command name.
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * Returns all registered commands.
     *
     * @return array<string, Command> Commands keyed by name.
     */
    public function all(): array
    {
        return $this->commands;
    }

    /**
     * Handles the full execution pipeline for a console request.
     *
     * Constructs a middleware pipeline from registered pipes, then executes
     * the command within that pipeline. Handles common errors like command
     * not found or fatal exceptions.
     *
     * @param InputInterface $input  The parsed console input.
     * @param Output         $output The output handler.
     * @return int The exit code (0 for success, non-zero for failure).
     */
    public function handle(InputInterface $input, Output $output): int
    {
        $core = fn(): int => $this->executeCommand($input, $output);

        $pipeline = array_reverse($this->pipes);
        $next = $core;

        foreach ($pipeline as $pipeClass) {
            $prev = $next;
            $next = function () use ($pipeClass, $input, $output, $prev): int {
                $pipe = $this->container->make($pipeClass);
                return $pipe->handle($input, $output, $prev);
            };
        }

        try {
            return $next();
        } catch (CommandNotFoundException $e) {
            $output->error('  ' . $e->getMessage());
            $output->newLine();
            $output->line('  Run ' . $output->format('php farmer list', 'yellow') . ' to see available commands.');
            $output->newLine();
            return 1;
        } catch (\Exception $e) {
            $output->error('  Erreur fatale : ' . $e->getMessage());
            $output->newLine();
            return 1;
        }
    }

    /**
     * Executes a specific command with resolved definition and dependencies.
     *
     * Resolves the command by name, binds its input definition, validates input,
     * and invokes the command's handle method via the container.
     *
     * @param InputInterface $input  The parsed console input.
     * @param Output         $output The output handler.
     * @return int The exit code returned by the command.
     */
    protected function executeCommand(InputInterface $input, Output $output): int
    {
        $commandName = $input->getCommandName();

        if ($commandName === null) {
            throw new CommandNotFoundException('Aucune commande spécifiée.');
        }

        // 1. Résolution
        $command = $this->resolve($commandName);
        $definition = $command->getResolvedInputDefinition();

        $input->bindDefinition($definition);
        $input->validate();
        $command->setIO($input, $output);

        $result = $this->container->call([$command, 'handle'], ['input' => $input, 'output' => $output]);

        return is_int($result) ? $result : 0;
    }
}
