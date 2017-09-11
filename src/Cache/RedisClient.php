<?php

/**
 * Redis
 */

namespace Beauty\Cache;

use Beauty\Core\App;
use Beauty\Lib\HashRing;

class RedisClient
{
    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;

    /**
     * 哈希换对象
     *
     * @var HashRing
     */
    private $hashring;

    /**
     * 保存redis connection
     *
     * @var
     */
    private static $connections;

    function __construct($config = "redis")
    {
        $this->config   = App::config()->get('cache');
        $this->prefix   = $this->config[$config]['prefix'];
        $this->hashring = new HashRing();
        $this->hashring->add($this->config[$config]['hosts']);
    }

    /**
     * 获取redis服务器
     *
     * @param $key
     * @return mixed
     */
    private function connect($key)
    {
        $server = $this->hashring->get($key);

        if (self::$connections[$server]) {
            return self::$connections[$server];
        }

        list($host, $port) = explode(":", $server);

        $lobjredis = new \Redis();
        $status    = $lobjredis->connect($host, $port);

        // check memcache connection
        if ($status === false) {
            throw new \RuntimeException("Could not establish Redis connection.");
        }

        self::$connections[$server] = $lobjredis;

        return self::$connections[$server];
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     *
     *
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $server = $this->connect($arguments[0]);

        return call_user_func_array([$server, $name], $arguments);
    }

    public function __destruct()
    {
        foreach (self::$connections as $conn) {
            if (!empty($conn)) {
                $conn->close();
            }
        }

        self::$connections = NULL;
    }
}