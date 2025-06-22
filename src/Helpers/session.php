<?php

use Potager\App;
use Potager\Session;

if (!function_exists('session')) {
    /**
     * Get the current session instance.
     *
     * @return \Potager\Session
     */
    function session()
    {
        return App::useSession();
    }
}

if (!function_exists('flash')) {
    /**
     * Retrieve flash data from the session.
     * If no key is provided, return all flash data.
     *
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    function flash(?string $key = null, mixed $default = null)
    {
        if (!$key)
            return App::useSession()->getAllFlashes();

        return App::useSession()->getFlash($key, $default);
    }
}

if (!function_exists('error')) {
    /**
     * Retrieve error messages stored in flash data.
     * If no key is provided, return all error messages.
     *
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    function error(?string $key = null, mixed $default = null)
    {
        if (!$key)
            return App::useSession()->getFlash('errors', $default);

        return App::useSession()->getFlash("errors.{$key}", $default);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve old input data stored in flash data.
     * If no key is provided, return all old input data.
     *
     * @param string|null $key
     * @param mixed|null $default
     * @return mixed
     */
    function old(?string $key = null, mixed $default = null)
    {
        if (!$key)
            return App::useSession()->getFlash('old', $default);

        return App::useSession()->getFlash("old.{$key}", $default);
    }
}
