<?php

namespace Potager\Console\Pipes;

use Potager\Console\IO\Output;
use Potager\Contracts\Console\InputInterface;
use Potager\Contracts\Console\PipeInterface;

class VersionPipe implements PipeInterface
{
    public function handle(InputInterface $input, Output $output, callable $next): int
    {
        if ($input->hasRawOption(['--version', '-v'])) {
            $output->line('Potager Framework version 0.0.0');
            return 0;
        }

        return $next();
    }
}
