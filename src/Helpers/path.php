<?php

// Check if the function already exists to avoid redeclaration errors

use Potager\App;

if (!function_exists('path')) {
    /**
     * Returns an absolute path based on a custom prefix or the project root.
     * 
     * @param string|null $path Relative path with a special prefix or no prefix.
     * @return string The resolved absolute path.
     * @throws RuntimeException If the application is not initialized or the base path is not defined.
     */
    function path(?string $path = null)
    {
        // Retrieve the base path of the project from the App instance
        $app = App::getInstance();
        $basePath = $app->getBasePath();

        // Ensure the base path is defined before proceeding
        if (!$basePath) {
            throw new \RuntimeException("Base path is not defined. Ensure the application is properly initialized.");
        }

        // If no path is provided, return the project root path
        if (!$path)
            return $basePath;

        // Get the first character of the path as a prefix to determine the base folder
        $prefix = $path[0];

        // Get the rest of the path after the prefix
        $relative_path = substr($path, 1);

        // Build the absolute path depending on the prefix used
        switch ($prefix) {
            case '@':
                // '@' prefix corresponds to the '.core' folder inside the project root
                return rtrim($basePath . '/.core/' . ltrim($relative_path, '/'), '/');
            case '#':
                // '#' prefix corresponds to the 'app' folder inside the project root
                return rtrim($basePath . '/app/' . ltrim($relative_path, '/'), '/');
            case '~':
                // '~' prefix corresponds to the 'public' folder inside the project root
                return rtrim($basePath . '/public/' . ltrim($relative_path, '/'), '/');
            default:
                // No recognized prefix, consider the path relative to the project root
                return rtrim($basePath . '/' . ltrim($path, '/'), '/');
        }
    }
}
