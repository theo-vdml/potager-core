<?php

namespace Potager;

use RuntimeException;

class Cache
{
    protected static string $cachePath;

    public static function configure(?string $cachePath = null): void
    {
        self::$cachePath = self::resolveCachePath($cachePath, 'cache');
    }

    protected static function resolveCachePath(?string $path, string $namespace): string
    {
        if ($path === null) {
            $path = sys_get_temp_dir() . "/potager-{$namespace}";
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

    public static function set(string $key, mixed $data, int $duration = 86400): void
    {
        $file = self::getCacheFilePath($key);
        $content = [
            'expire' => time() + $duration,
            'data' => $data
        ];

        file_put_contents($file, json_encode($content));
    }

    public static function get(string $key)
    {
        $file = self::getCacheFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $json = json_decode(file_get_contents($file), true);

        if (!$json || ($json['expire'] ?? 0) < time()) {
            @unlink($file);
            return null;
        }

        return $json['data'];
    }

    protected static function getCacheFilePath(string $key): string
    {
        if (!isset(self::$cachePath)) {
            self::configure(); // lazy default to tmp
        }

        return self::$cachePath . "/{$key}.json";
    }
}
