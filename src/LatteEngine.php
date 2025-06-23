<?php

namespace Potager;

use Latte\Engine;

use Latte\Runtime\Html;
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

        $this->latte->addFunction('csrf', function () {
            $token = csrf();
            return new Html(
                '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token) . '">'
            );
        });
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
        $this->assertViewExists($view);
        $file = $this->getViewFile($view);
        return $this->latte->renderToString($file, $params);
    }

    public function getViewFile($view): string
    {
        if (file_exists($view))
            return $view;
        $path = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.latte';
        return $path;
    }

    public function viewExists(string $view): bool
    {
        $file = $this->getViewFile($view);
        return file_exists($file);
    }

    protected function assertViewExists(string $view): void
    {
        if (!$this->viewExists($view))
            throw new RuntimeException("Latte view [{$view}] not found at [{$this->getViewFile($view)}]. Did you forget to create it?");
    }

}