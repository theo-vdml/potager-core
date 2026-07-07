<?php

namespace Potager\Console;

use Potager\Contracts\Console\InputInterface;
use Potager\Console\IO\InputDefinition;
use Potager\Console\IO\Output;

/**
 * Base class for all console commands.
 *
 * Subclasses should override {@see getInputDefinition()} to declare expected
 * arguments and options, and implement a `handle()` method to define the
 * command's behaviour.
 */
abstract class Command
{
    /** @var string The name of the command (e.g. "mail:send"). */
    protected string $name = '';

    /** @var string A short description of the command shown in the command list. */
    protected string $description = '';

    /** @var InputInterface The resolved input instance bound to this command. */
    protected InputInterface $input;

    /** @var Output The output instance bound to this command. */
    protected Output $output;

    /**
     * Returns the raw input definition for this command.
     *
     * Override in subclasses to declare {@see \Potager\Console\IO\InputArgument}
     * and {@see \Potager\Console\IO\InputOption} instances.
     *
     * @return array<\Potager\Console\IO\InputArgument|\Potager\Console\IO\InputOption>
     */
    protected function getInputDefinition(): array
    {
        return [];
    }

    /**
     * Builds and returns the resolved {@see InputDefinition} for this command.
     *
     * @return InputDefinition
     */
    public function getResolvedInputDefinition(): InputDefinition
    {
        return new InputDefinition($this->getInputDefinition());
    }

    /**
     * Sets the input instance for this command.
     *
     * @param InputInterface $input The parsed input.
     */
    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    /**
     * Sets the output instance for this command.
     *
     * @param Output $output The output handler.
     */
    public function setOutput(Output $output): void
    {
        $this->output = $output;
    }

    /**
     * Sets both the input and output instances in one call.
     *
     * @param InputInterface $input  The parsed input.
     * @param Output         $output The output handler.
     */
    public function setIO(InputInterface $input, Output $output): void
    {
        $this->setInput($input);
        $this->setOutput($output);
    }

    /**
     * Returns the name of the command.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the description of the command.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Affiche l'aide générée automatiquement à partir de la définition de la commande.
     */
    public function help(): void
    {
        $definition = $this->getResolvedInputDefinition();

        // 1. Description
        $this->output->newLine();
        $this->output->line($this->output->format('Description:', 'yellow'));
        $this->output->line('  ' . $this->getDescription());
        $this->output->newLine();

        // 2. Usage
        $this->output->line($this->output->format('Usage:', 'yellow'));
        $this->output->line('  ' . $this->getName() . ' [options] [--] [<arguments>]');
        $this->output->newLine();

        // 3. Arguments
        $arguments = $definition->getArguments();
        if (!empty($arguments)) {
            $this->output->line($this->output->format('Arguments:', 'yellow'));
            foreach ($arguments as $arg) {
                $name = $arg->getName();

                // Formatage du nom (ex: "nom (tableau)" ou "nom")
                $displayName = $arg->isMultiple() ? $name . '...' : $name;

                // Affichage du défaut si pertinent
                $default = $arg->getDefault() !== null ? ' [default: ' . json_encode($arg->getDefault()) . ']' : '';

                // On utilise str_pad pour aligner parfaitement les descriptions à 25 caractères
                $this->output->line(
                    '  ' . $this->output->format(str_pad($displayName, 25), 'green') .
                        $arg->getDescription() . $this->output->format($default, 'yellow')
                );
            }
            $this->output->newLine();
        }

        // 4. Options
        $options = $definition->getOptions();
        if (!empty($options)) {
            $this->output->line($this->output->format('Options:', 'yellow'));
            foreach ($options as $opt) {
                // Gestion du raccourci (ex: "-h, " ou "    ")
                $shortcut = $opt->getShortcut() ? '-' . $opt->getShortcut() . ', ' : '    ';

                // Gestion du nom long et de la valeur (ex: "--env=ENV")
                $name = '--' . $opt->getName();
                if ($opt->acceptsValue()) {
                    $name .= '=' . strtoupper($opt->getName());
                }

                $synopsis = $shortcut . $name;

                // On n'affiche pas les défauts vides, null ou les booléens (pour les flags)
                $rawDefault = $opt->getDefault();
                $default = ($rawDefault !== null && $rawDefault !== false && $rawDefault !== [])
                    ? ' [default: ' . json_encode($rawDefault) . ']' : '';

                // Alignement parfait
                $this->output->line(
                    '  ' . $this->output->format(str_pad($synopsis, 25), 'green') .
                        $opt->getDescription() . $this->output->format($default, 'yellow')
                );
            }
            $this->output->newLine();
        }
    }
}
