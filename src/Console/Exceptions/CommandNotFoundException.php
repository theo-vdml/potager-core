<?php

namespace Potager\Console\Exceptions;

use RuntimeException;

class CommandNotFoundException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct("Command [{$name}] not found.");
    }
}
