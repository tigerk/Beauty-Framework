<?php

namespace Beauty\Http;

class Request
{

    const METHOD_HEAD     = 'HEAD';
    const METHOD_GET      = 'GET';
    const METHOD_POST     = 'POST';
    const METHOD_PUT      = 'PUT';
    const METHOD_PATCH    = 'PATCH';
    const METHOD_DELETE   = 'DELETE';
    const METHOD_OPTIONS  = 'OPTIONS';
    const METHOD_OVERRIDE = '_METHOD';

    /**
     * Request paths (physical and virtual) cached per instance
     * @var array
     */
    protected $paths;

    /**
     * 环境对象
     *
     * @var Environment
     */
    protected $env;

    /**
     * pathinfo
     *
     * @var
     */
    protected $segments;

    /**
     * 请求body
     *
     * @var
     */
    protected $body;

    /**
     * 保存get请求参数
     *
     * @var
     */
    protected $getParameters;

    /**
     * 保存post请求参数
     *
     * @var
     */
    protected $postParameters;

    public function __construct(Environment $env)
    {
        $this->env = $env;
    }

    /**
     * Get HTTP method
     *
     * @return string
     * @api
     */
    public function getMethod()
    {
        // Get actual request method
        $method = $this->env->get('REQUEST_METHOD');

        return $method;
    }

    /**
     * 获取pathinfo
     *
     * @return mixed
     */
    public function getPathInfo()
    {
        $paths = $this->parsePaths();

        return $paths['virtual'];
    }

    /**
     * Get query string
     *
     * @return string
     * @api
     */
    public function getQueryString()
    {
        return $this->env->get('QUERY_STRING', '');
    }

    /**
     * Parse the physical and virtual paths from the request URI
     *
     * @return array
     */
    protected function parsePaths()
    {
        $this->paths             = array();
        $this->paths['physical'] = $_SERVER['SCRIPT_NAME'];


        if (strpos($_SERVER['REQUEST_URI'], '?') !== false) {
            $this->paths['virtual'] = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?'));
        } else {
            $this->paths['virtual'] = $_SERVER['REQUEST_URI'];
        }

        return $this->paths;
    }

    /**
     * 解析url片段
     *
     * @return array
     */
    protected function parseSegment()
    {
        $segments = explode('/', $this->getPathInfo());

        $this->segments = array_values(array_filter($segments, function ($v) {
            return $v != '';
        }));

        return $this->segments;
    }

    /**
     * 获取url所有片段
     *
     * @return array
     */
    public function segments()
    {
        if (!is_null($this->segments)) {
            return $this->segments;
        }

        return $this->parseSegment();
    }

    /**
     * 根据偏移量获取片段，没有返回默认值
     *
     * @param $index
     * @param null $default
     * @return mixed|null
     */
    public function segment($index, $default = null)
    {
        $segments = $this->segments();

        if (array_key_exists($index, $segments)) {
            return $segments[$index - 1];
        }

        return $default;
    }

    /**
     * Gets the request body
     *
     * @return string
     */
    public function body()
    {
        // Only get it once
        if (null === $this->body) {
            $this->body = @file_get_contents('php://input');
        }

        return $this->body;
    }

    /**
     * 获取所有的post请求数据
     *
     * @return mixed
     */
    public function posts()
    {
        if (null === $this->postParameters) {
            $this->postParameters = $_POST;
        }

        return $this->postParameters;
    }

    /**
     * Return a request parameter, or $default if it doesn't exist
     *
     * @param string $key The name of the parameter to return
     * @param mixed $default The default value of the parameter if it contains no value
     * @return mixed
     */
    public function post($key, $default = null)
    {
        // Get all of our request params
        $params = $this->posts();

        return isset($params[$key]) ? $params[$key] : $default;
    }

    public function gets()
    {
        if (null === $this->getParameters) {
            $this->getParameters = $_GET;
        }

        return $this->getParameters;
    }

    /**
     * Return a request parameter, or $default if it doesn't exist
     *
     * @param string $key The name of the parameter to return
     * @param mixed $default The default value of the parameter if it contains no value
     * @return mixed
     */
    public function get($key, $default = null)
    {
        // Get all of our request params
        $params = $this->gets();

        return isset($params[$key]) ? $params[$key] : $default;
    }
}