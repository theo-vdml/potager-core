<?php

namespace Potager\Console\Commands;

use Potager\Console\Command;
use Potager\Console\IO\InputOption;
use Potager\Facades\Farmer;

class CommandListCommand extends Command
{
    protected string $name = 'list';

    protected string $description = 'List all registered commands';

    protected function getInputDefinition(): array
    {
        return [
            InputOption::make('help')->description('Display this help message')->shortcut('h'),
        ];
    }

    public function handle(): int
    {
        $this->output->newLine();
        $this->output->line(
            $this->output->format('Potager Framework', 'cyan', 'bold') . ' — ' . $this->output->format('Farmer CLI', 'white')
        );
        $this->output->newLine();
        $this->output->line($this->output->format('Usage:', 'yellow'));
        $this->output->line('  php farmer <command> [arguments] [options]');
        $this->output->newLine();
        $this->output->line($this->output->format('Available Commands:', 'yellow'));

        $commands = Farmer::all();

        if (empty($commands)) {
            $this->output->line($this->output->format('  No commands registered.', 'dim'));
            $this->output->newLine();
            return 0;
        }

        // Group by namespace (part before ':')
        $grouped = [];
        foreach ($commands as $name => $command) {
            $ns = str_contains($name, ':') ? explode(':', $name, 2)[0] : '';
            $grouped[$ns][] = $command;
        }

        ksort($grouped);

        // Print ungrouped commands (no namespace) first
        if (isset($grouped[''])) {
            foreach ($grouped[''] as $cmd) {
                $label = $this->output->format(sprintf('  %-30s', $cmd->getName()), 'green');
                $this->output->line($label . $this->output->format($cmd->getDescription(), 'dim'));
            }
            unset($grouped['']);
        }

        foreach ($grouped as $ns => $cmds) {
            $this->output->newLine();
            $this->output->line(' ' . $this->output->format($ns, 'yellow'));
            foreach ($cmds as $cmd) {
                $label = $this->output->format(sprintf('  %-30s', $cmd->getName()), 'green');
                $this->output->line($label . $this->output->format($cmd->getDescription(), 'dim'));
            }
        }

        $this->output->newLine();
        return 0;
    }
}
