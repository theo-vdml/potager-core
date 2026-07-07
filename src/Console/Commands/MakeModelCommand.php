<?php

namespace Potager\Console\Commands;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputArgument;

class MakeModelCommand extends Command
{
    protected string $name = 'make:model';

    protected string $description = 'Create a new Limpid model class';

    protected function getInputDefinition(): array
    {
        return [
            InputArgument::make('name')->description('The name of the model class to create (e.g. "User")'),
        ];
    }

    public function handle(): int
    {
        $name = $this->input->argument('name');

        if (!$name) {
            $this->output->error('  Missing argument: name');
            return 1;
        }

        $targetPath = App::getInstance()->getBasePath() . '/app/Models/' . $name . '.php';

        if (file_exists($targetPath)) {
            $this->output->error("  Model [{$name}] already exists.");
            return 1;
        }

        $stub = file_get_contents(__DIR__ . '/../Stubs/model.stub');
        $content = str_replace('{{name}}', $name, $stub);

        $this->ensureDirectory(dirname($targetPath));
        file_put_contents($targetPath, $content);

        $this->output->success("  Model [{$name}] created successfully.");
        $this->output->line('  → ' . $this->relativePath($targetPath));
        return 0;
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
