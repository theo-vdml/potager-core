<?php

namespace Potager\Console\Pipes;

use Potager\Console\Kernel;
use Potager\Contracts\Console\PipeInterface;
use Potager\Contracts\Console\InputInterface;
use Potager\Console\IO\Output;
use Potager\Console\Exceptions\CommandNotFoundException;
use Potager\Container\Container;

class HelpPipe implements PipeInterface
{
    private Kernel $kernel;
    private Container $container;

    public function __construct(Kernel $kernel, Container $container)
    {
        $this->kernel = $kernel;
        $this->container = $container;
    }

    public function handle(InputInterface $input, Output $output, callable $next): int
    {
        $commandName = $input->getCommandName();

        // 1. Cas : L'utilisateur n'a rien tapé, ou juste `php farmer -h`
        if (!$commandName || ($input->hasRawOption(['--help', '-h']) && !$commandName)) {
            // On réécrit la route console !
            $input->setCommandName('list');
            return $next();
        }

        // 2. Cas : Demande d'aide sur une commande spécifique (ex: php farmer make:model --help)
        if ($input->hasRawOption(['--help', '-h'])) {
            try {
                $command = $this->kernel->resolve($commandName);

                // On prépare la commande juste pour l'affichage de l'aide
                $command->setIO($input, $output);

                if (method_exists($command, 'help')) {
                    $this->container->call([$command, 'help'], ['input' => $input, 'output' => $output]);
                }

                return 0; // Exit early ! On coupe le pipeline ici.

            } catch (CommandNotFoundException $e) {
                // Si la commande n'existe pas, on laisse le flux continuer.
                // Le Kernel crashera proprement en bas avec son propre catch(CommandNotFoundException).
                return $next();
            }
        }

        // 3. Cas normal : Pas de --help
        return $next();
    }
}
