<?php

namespace Potager;

use Latte\Engine;

use RuntimeException;

class LatteEngine
{

    protected Engine $latte;
    protected string $viewsPath;
    protected string $cachePath;

    public function __construct(?string $viewsPath = null, ?string $cachePath = null)
    {
        $this->latte = new Engine();
        $this->viewsPath = rtrim($viewsPath ?? path('/views'), '/');
        $this->cachePath = $this->resolveCachePath($cachePath, 'latte');

        $this->latte->setTempDirectory($this->cachePath);
    }

    protected function resolveCachePath(?string $path, string $namespace): string
    {
        if ($path === null) {
            $path = sys_get_temp_dir() . "/potager/cache-{$namespace}";
            @mkdir($path, 0775, true);
            return $path;
        }

        if (!is_dir($path)) {
            throw new RuntimeException("Provided cache path [$path] does not exist.");
        }

        if (!is_writable($path)) {
            throw new RuntimeException("Provided cache path [$path] is not writable.");
        }

        return rtrim($path, '/');
    }

    public function render(string $view, array $params = []): string
    {
        $file = $this->resolveView($view);
        return $this->latte->renderToString($file, $params);
    }

    protected function resolveView(string $view): string
    {
        $path = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.latte';
        if (!file_exists($path))
            throw new RuntimeException("Latte view [{$view}] not found at [{$path}]. Did you forget to create it?");
        return $path;
    }

}