<?php

namespace Potager\Console\Commands;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputArgument;

class MakeCommandCommand extends Command
{
    protected string $name = 'make:command';

    protected string $description = 'Create a new command class';

    protected function getInputDefinition(): array
    {
        return [
            InputArgument::make('name')->description('The name of the command class to create (e.g. "GreetUserCommand")'),
        ];
    }

    public function handle(): int
    {
        $name = $this->input->argument('name');

        if (!$name) {
            $this->output->error('  Missing argument: name');
            return 1;
        }

        // Append "Command" suffix if absent
        if (!str_ends_with($name, 'Command')) {
            $name .= 'Command';
        }

        $targetPath = App::getInstance()->getBasePath() . '/app/Commands/' . $name . '.php';

        if (file_exists($targetPath)) {
            $this->output->error("  Command [{$name}] already exists.");
            return 1;
        }

        $signature = $this->deriveSignature($name);

        $stub = file_get_contents(__DIR__ . '/../Stubs/command.stub');
        $content = str_replace(['{{name}}', '{{signature}}'], [$name, $signature], $stub);

        $this->ensureDirectory(dirname($targetPath));
        file_put_contents($targetPath, $content);

        $this->output->success("  Command [{$name}] created successfully.");
        $this->output->line('  → ' . $this->relativePath($targetPath));
        return 0;
    }

    /**
     * Derive a default command signature from a PascalCase class name.
     *
     * Examples:
     *   GreetUserCommand → greet:user
     *   SendEmailCommand → send:email
     *   MigrateCommand   → migrate
     */
    private function deriveSignature(string $className): string
    {
        // Remove trailing "Command" suffix
        $name = preg_replace('/Command$/', '', $className);

        // Split PascalCase into words
        $words = preg_split('/(?=[A-Z])/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_map('strtolower', $words);

        if (count($words) <= 1) {
            return implode('', $words);
        }

        // First word becomes the namespace, the rest become the sub-command
        $namespace = array_shift($words);
        return $namespace . ':' . implode('-', $words);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function relativePath(string $absolute): string
    {
        $base = App::getInstance()->getBasePath();
        return ltrim(str_replace($base, '', $absolute), '/');
    }
}
