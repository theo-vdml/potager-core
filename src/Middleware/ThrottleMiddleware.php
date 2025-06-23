<?php

namespace Potager\Middleware;

use Potager\App;
use Potager\Contracts\MiddlewareInterface;
use Potager\Exceptions\HttpException;
use Potager\Router\HttpContext;

class ThrottleMiddleware implements MiddlewareInterface
{
    protected int $maxAttempts;
    protected int $decaySeconds;
    protected string $keyPrefix;

    public function __construct(?int $maxAttempts = null, ?int $decaySeconds = null, ?string $keyPrefix = null)
    {
        $this->maxAttempts = $maxAttempts ?? 60;
        $this->decaySeconds = $decaySeconds ?? 60;
        $this->keyPrefix = $keyPrefix ?? 'throttle_';
    }

    public function handle(HttpContext $ctx, callable $next): void
    {
        $session = App::useSession();
        $ip = $ctx->request()->ip() ?? 'unknown';
        $key = $this->keyPrefix . sha1($ip);

        $record = $session->get($key, ['count' => 0, 'start' => time()]);
        $now = time();

        if ($now - $record['start'] > $this->decaySeconds) {
            $record = ['count' => 1, 'start' => $now];
        } else {
            if ($record['count'] >= $this->maxAttempts) {
                $retryAfter = $this->decaySeconds - ($now - $record['start']);
                header("Retry-After: {$retryAfter}");
                throw new HttpException(429);
            }
            $record['count']++;
        }

        $session->set($key, $record);

        $next();
    }
}
