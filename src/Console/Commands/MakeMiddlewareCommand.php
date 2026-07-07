<?php

namespace Potager\Console\Commands;

use Potager\App;
use Potager\Console\Command;
use Potager\Console\IO\InputArgument;

class MakeMiddlewareCommand extends Command
{
    protected string $name = 'make:middleware';

    protected string $description = 'Create a new middleware class';

    protected function getInputDefinition(): array
    {
        return [
            InputArgument::make('name')->description('The name of the middleware class to create (e.g. "AuthenticateMiddleware")'),
        ];
    }

    public function handle(): int
    {
        $name = $this->input->argument('name');

        if (!$name) {
            $this->output->error('  Missing argument: name');
            return 1;
        }

        // Append "Middleware" suffix if absent
        if (!str_ends_with($name, 'Middleware')) {
            $name .= 'Middleware';
        }

        $targetPath = App::getInstance()->getBasePath() . '/app/Middleware/' . $name . '.php';

        if (file_exists($targetPath)) {
            $this->output->error("  Middleware [{$name}] already exists.");
            return 1;
        }

        $stub = file_get_contents(__DIR__ . '/../Stubs/middleware.stub');
        $content = str_replace('{{name}}', $name, $stub);

        $this->ensureDirectory(dirname($targetPath));
        file_put_contents($targetPath, $content);

        $this->output->success("  Middleware [{$name}] created successfully.");
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
