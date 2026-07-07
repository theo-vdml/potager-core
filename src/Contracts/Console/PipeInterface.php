<?php

namespace Potager\Contracts\Console;

use Potager\Console\IO\Output;

interface PipeInterface
{
    /**
     * Process the console input/output and optionally invoke the next pipe.
     *
     * @param InputInterface $input  The console input.
     * @param Output         $output The console output.
     * @param callable       $next   The next pipe callable in the pipeline.
     * @return int The exit code (0 for success).
     */
    public function handle(InputInterface $input, Output $output, callable $next): int;
}
