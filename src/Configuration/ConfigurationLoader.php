<?php

namespace Potager\Configuration;

/**
 * Loads and merges PHP config files from a directory.
 *
 * Each file in the directory becomes a top-level key in the merged array:
 *   config/database.php  →  $config['database'][...]
 *   config/mail.php      →  $config['mail'][...]
 *
 * Files that do not return an array are silently skipped.
 * If the directory does not exist the loader returns an empty array (no crash).
 */
class ConfigurationLoader
{
    /**
     * Load all PHP files from the given directory and merge them.
     *
     * @param  string $directory Absolute path to the config directory.
     * @return array<string, mixed>
     */
    public static function fromDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $config = [];

        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);

            // phpcs:ignore -- intentional dynamic require
            $values = require $file;

            if (is_array($values)) {
                $config[$key] = $values;
            }
        }

        return $config;
    }

    /**
     * Load a single PHP config file.
     * Returns an empty array on missing file or invalid return value (no crash).
     *
     * @param  string $filePath Absolute path to the file.
     * @return array<string, mixed>
     */
    public static function fromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        // phpcs:ignore -- intentional dynamic require
        $values = require $filePath;

        return is_array($values) ? $values : [];
    }
}
