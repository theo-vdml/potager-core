<?php

namespace Potager\Console\Commands;

use Potager\Console\Command;
use Potager\Router\Router;

class RouteListCommand extends Command
{
    protected string $name = 'route:list';

    protected string $description = 'List all registered routes';

    public function handle(Router $router): int
    {
        $routes = $router->getRoutes();

        if (empty($routes)) {
            $this->output->warning('  No routes registered.');
            return 0;
        }

        $rows = [];
        foreach ($routes as $route) {
            $action = $route->getAction();

            if (is_array($action) && count($action) === 2) {
                $class = is_object($action[0]) ? get_class($action[0]) : $action[0];
                $actionStr = $class . '@' . $action[1];
            } elseif ($action instanceof \Closure) {
                $actionStr = 'Closure';
            } else {
                $actionStr = (string) $action;
            }

            $rows[] = [
                $route->getMethod(),
                $route->getPath(),
                $route->getName() ?? '',
                $actionStr,
            ];
        }

        $this->output->newLine();
        $this->output->table(['Method', 'Path', 'Name', 'Action'], $rows);
        $this->output->newLine();

        return 0;
    }
}
