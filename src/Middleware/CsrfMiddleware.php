<?php

namespace Potager\Middleware;

use Potager\App;
use Potager\Contracts\MiddlewareInterface;
use Potager\Exceptions\HttpException;
use Potager\Router\HttpContext;

class CsrfMiddleware implements MiddlewareInterface
{
    protected int $ttl;

    public function __construct(?int $ttl = null)
    {
        $this->ttl = $ttl;
    }

    public function handle(HttpContext $ctx, callable $next): void
    {
        $session = App::useSession();
        $request = $ctx->request();

        $token = $session->pull('_csrf_token');
        $issuedAt = $session->pull('_csrf_token_time');
        $submitted = $request->input('_csrf_token');

        $isValid = $token && $submitted && hash_equals($token, $submitted);
        $isNotExpired = $this->ttl === null || ($issuedAt && (time() - $issuedAt < $this->ttl));

        if (!$isValid || !$isNotExpired) {
            throw new HttpException(419);
        }

        $next();
    }
}
