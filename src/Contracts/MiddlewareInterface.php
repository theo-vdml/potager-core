<?php

namespace Potager\Contracts;

use Potager\Router\HttpContext;

/**
 * Interface MiddlewareInterface
 *
 * Defines the contract for middleware classes.
 * Middleware must implement a handle method that
 * receives the HTTP context and a "next" callable.
 */
interface MiddlewareInterface
{
    /**
     * Process the HTTP context and optionally invoke the next middleware.
     *
     * @param HttpContext $ctx  The current HTTP context (request/response).
     * @param callable    $next The next middleware callable in the pipeline.
     */
    public function handle(HttpContext $ctx, callable $next): void;
}
