<?php

namespace Potager\Mailer;

use Potager\Contracts\Configuration\RepositoryInterface;
use Potager\Mailer\Transports\SmtpTransport;

class MailManager
{
    protected array $transports = [];

    public function __construct(protected readonly RepositoryInterface $config) {}

    public function use(string $driver): Mailer
    {
        if (!$this->config->get("mail.drivers.$driver")) {
            throw new \InvalidArgumentException("Mail driver [{$driver}] is not configured.");
        }

        if (!isset($this->transports[$driver])) {
            $this->transports[$driver] = $this->createTransport($driver);
        }

        return new Mailer($this->transports[$driver]);
    }

    public function send(\Closure $callback): void
    {
        $default = $this->config->get('mail.default', 'smtp');
        $this->use($default)->send($callback);
    }

    protected function createTransport(string $driver): mixed
    {
        $driverConfig = $this->config->get("mail.drivers.$driver", []);

        return match ($driver) {
            'smtp'  => new SmtpTransport($driverConfig),
            default => throw new \InvalidArgumentException("Unsupported mail driver [{$driver}].")
        };
    }
}
