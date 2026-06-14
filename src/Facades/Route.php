<?php

namespace Potager\Facades;

use Potager\Router\Router;
use Potager\Support\Facades\Facade;

/**
 * Facade for the Router service.
 *
 * Provides a static interface to the Router instance registered in the application container.
 * 
 */
class Route extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Router::class;
    }
}
