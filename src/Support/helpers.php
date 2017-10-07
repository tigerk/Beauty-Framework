<?php

/**
 * set handler function
 *
 * @param Throwable $e
 */
function handleException(Throwable $e)
{
    \Beauty\Log\DLog::fatal(var_export($e, true), 0, []);
}

/**
 * 定义路径方法
 */

if (!function_exists('base_path')) {
    /**
     * 返回项目目录
     *
     * @return string
     */
    function base_path()
    {
        return \Beauty\Core\App::$_basePath;
    }
}

if (!function_exists('app_path')) {
    /**
     * 返回应用目录
     *
     * @return string
     */
    function app_path()
    {
        return base_path() . "app/";
    }
}

if (!function_exists('config_path')) {
    /**
     * 获取配置目录
     *
     * @return string
     */
    function config_path()
    {
        $env = parse_ini_file(base_path() . ".env");

        return base_path() . "config/" . $env['environment'];
    }
}

if (!function_exists('environment')) {
    /**
     * 返回环境参数
     *
     * @return mixed
     */
    function environment()
    {
        $env = parse_ini_file(base_path() . ".env");

        return $env['environment'];
    }
}

if (!function_exists('random_token')) {
    /**
     * 生成随机token，最后转为16进制，输出8位随机数
     *
     * @param int $length
     * @return string
     */
    function random_token($length = 32)
    {
        if (!isset($length) || intval($length) <= 8) {
            $length = 32;
        }
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length));
        }
        if (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length));
        }
        if (function_exists('mcrypt_create_iv')) {
            /**
             * php 7 开始已经废弃
             */
            return bin2hex(mcrypt_create_iv($length, MCRYPT_DEV_URANDOM));
        }
    }
}

if (!function_exists('is_https')) {
    /**
     * 判断是否是https请求
     * @return boolean Returns TRUE if connection is made using HTTPS
     */
    function is_https()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') {
            return true;
        } else {
            return false;
        }
    }
}