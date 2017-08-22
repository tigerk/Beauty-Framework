<?php

/**
 * set handler function
 *
 * @param Throwable $e
 */
function handleException(Throwable $e)
{
    DLog::fatal(var_export($e, true), 0, []);
}

/**
 * 定义路径方法
 */

if (!function_exists('base_path')) {
    function base_path()
    {
        return \Beauty\App::basePath();
    }
}

if (!function_exists('app_path')) {
    function app_path()
    {
        return base_path() . "app/";
    }
}

if (!function_exists('config_path')) {
    function config_path()
    {
        $env = parse_ini_file(base_path() . ".env");

        return base_path() . "config/" . $env['environment'];
    }
}

if (!function_exists('environment')) {
    function environment()
    {
        $env = parse_ini_file(base_path() . ".env");

        return $env['environment'];
    }
}