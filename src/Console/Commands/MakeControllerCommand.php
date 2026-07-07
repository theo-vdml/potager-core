<?php

namespace Potager\Console\Commands;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputArgument;

class MakeControllerCommand extends Command
{
    protected string $name = 'make:controller';

    protected string $description = 'Create a new controller class';

    protected function getInputDefinition(): array
    {
        return [
            InputArgument::make('name')->description('The name of the controller class to create (e.g. "UserController")'),
        ];
    }

    public function handle(): int
    {
        $name = $this->input->argument('name');

        if (!$name) {
            $this->output->error('  Missing argument: name');
            return 1;
        }

        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $targetPath = App::getInstance()->getBasePath() . '/app/Controllers/' . $name . '.php';

        if (file_exists($targetPath)) {
            $this->output->error("  Controller [{$name}] already exists.");
            return 1;
        }

        $stub = file_get_contents(__DIR__ . '/../Stubs/controller.stub');
        $content = str_replace('{{name}}', $name, $stub);

        $this->ensureDirectory(dirname($targetPath));
        file_put_contents($targetPath, $content);

        $this->output->success("  Controller [{$name}] created successfully.");
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
