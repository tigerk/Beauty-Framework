<?php

/**
 * Beauty - a mobile app PHP 7 framework
 *
 * @author  kimhwawoon <kimhwawoon@gmail.com>
 * @version 1.0
 * @package Beauty
 */

namespace Beauty\Core;

use Beauty\Exception\FilterException;
use Beauty\Exception\MethodNotFoundException;
use Beauty\Exception\RouteNotFoundException;
use Beauty\Http\Environment;
use Beauty\Http\Request;
use Beauty\Http\Response;
use Beauty\Router\Router;

class App
{
    /**
     *  The beauty framework version
     */
    const VERSION = '1.0';

    /**
     * the app router
     */
    protected $router;

    /**
     * Has the app response been sent to the client
     * @var bool
     */
    protected $responded = false;

    protected $allowed = true;

    static $_basePath;
    /**
     * App instance
     *
     * @var
     */
    static $_instance;

    /**
     * initialize App
     * @param string $basePath
     */
    public function __construct($basePath)
    {
        /**
         * App中记录base path;
         */
        self::$_basePath   = $basePath;
        $this->config      = new Config();
        $this->environment = new Environment($_SERVER);
        $this->router      = new Router();
        $this->request     = new Request($this->environment);
        $this->response    = new Response();

        $this->initialize();

        /**
         * set this object into static
         */
        $this->instance($this);
    }

    /**
     * config app config init.
     * App初始化操作
     */
    public function initialize()
    {
        $config = $this->config->get('app');

        date_default_timezone_set($config['timezone']);

        if (!$config['debug']) {
            error_reporting(0);
            set_exception_handler("handleException");
        } else {
            error_reporting(E_ALL);
        }
    }

    /**
     * execute action
     */
    public function run()
    {
        if (!$this->allowed) {
            return;
        }

        $callable = $this->dispatchRequest($this->request, $this->response);
        $content  = call_user_func($callable, $this->request);

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $this->response->setContent($content)->send();
    }

    /**
     * Dispatch request and build response
     *
     * @param Request $request
     * @param Response $response
     * @return \Closure|null
     * @throws FilterException
     * @throws RouteNotFoundException
     * @throws \Exception
     */
    protected function dispatchRequest(Request $request, Response $response)
    {
        $route = $this->router->getMatchedRoutes($request->getMethod(), $request->getPathInfo(), false);

        if (is_null($route)) {
            throw new RouteNotFoundException("uri path not found allowed method!");
        }

        $filtered   = true;
        $middleware = $this->router->getCurrentRouteMiddleware();
        if ($middleware instanceof \Closure && is_callable($middleware)) {
            $filtered = call_user_func($middleware, $this->request);
        }

        if (!$filtered) {
            throw new FilterException("uri filter not allowed!");
        }

        /**
         * 支持function直接调用
         */
        $lostrcallable = $this->router->getCurrentRouteCallable();
        if ($lostrcallable instanceof \Closure && is_callable($lostrcallable)) {
            return $lostrcallable;
        }

        $callable = null;
        $matches  = array();
        if (is_string($lostrcallable) && preg_match('!^([\a-zA-Z0-9]+)\@([a-zA-Z0-9]+)$!', $lostrcallable, $matches)) {
            $class  = $matches[1];
            $method = $matches[2];

            $callable = function () use ($class, $method) {
                static $obj = null;
                if ($obj === null) {

                    $obj = new $class;
                }

                return call_user_func_array(array($obj, $method), func_get_args());
            };
        }

        if (!is_callable($callable)) {
            throw new \Exception('Route callable must be callable');
        }

        return $callable;
    }

    /**
     * 支持获取方法名调用
     *
     * @param $method
     * @param $parameters
     * @return mixed
     * @throws MethodNotFoundException
     */
    public static function __callStatic($method, $parameters)
    {
        if (isset(self::$_instance->$method)) {
            return self::$_instance->$method;
        }

        throw new MethodNotFoundException('method not found!');
    }

    /**
     * 设置App instance
     *
     * @param $ins
     */
    public function instance($ins)
    {
        if (!isset(self::$_instance)) {
            self::$_instance = $ins;
        }
    }
}
