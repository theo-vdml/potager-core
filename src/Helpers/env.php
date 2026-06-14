<?php

if (!function_exists('env')) {
    /**
     * Get an environment variable, with type coercion and a default fallback.
     *
     * Reads from $_ENV, $_SERVER, then getenv() — in that priority order.
     * Automatically casts the common string representations:
     *   'true' / '(true)'   → true
     *   'false' / '(false)' → false
     *   'null' / '(null)'   → null
     *   'empty' / '(empty)' → ''
     *
     * Usage in config files:
     *   'driver' => env('DB_DRIVER', 'mysql'),
     *   'debug'  => env('APP_DEBUG', false),
     *
     * @param  string $key     The environment variable name.
     * @param  mixed  $default Returned when the variable is not set.
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}
