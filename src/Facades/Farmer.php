<?php

namespace Potager\Facades;

use Potager\Console\ClosureCommand;
use Potager\Console\Kernel;
use Potager\Support\Facades\Facade;

/**
 * Facade for the Console Kernel.
 *
 * Allows registering commands statically in commands/commands.php.
 *
 * @method static void register(string $commandClass)
 * @method static ClosureCommand command(string $name)
 * @method static \Potager\Console\Command resolve(string $name)
 * @method static \Potager\Console\Command[] all()
 */
class Farmer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Kernel::class;
    }
}
