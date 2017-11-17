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

    /**
     * 缓存标签
     *
     * @var string
     */
    protected $cacheTag;

    /**
     * 单例
     */
    private static $_instances;

    function __construct($config = "redis")
    {
        $this->config   = App::config()->get('cache');
        $this->prefix   = $this->config[$config]['prefix'];
        $this->hashring = new HashRing();
        $this->hashring->add($this->config[$config]['hosts']);
    }

    /**
     * 默认走redis conf
     *
     * @param string $config
     * @return RedisClient
     */
    public static function instance($config = "redis")
    {
        if (!isset(self::$_instances[$config]) || self::$_instances[$config] == NULL) {
            self::$_instances[$config] = new RedisClient($config);
        }

        return self::$_instances[$config];
    }

    /**
     * 获取redis服务器
     *
     * @param $key
     * @return mixed
     */
    public function connect($key)
    {
        $server = $this->hashring->get($key);

        if (self::$connections[$server]) {
            return self::$connections[$server];
        }

        list($host, $port) = explode(":", $server);

        $lobjredis = new \Redis();
        $status    = $lobjredis->pconnect($host, $port);

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
     * 设置tag标签
     *
     * @param $key
     * @return $this
     */
    public function tags($key)
    {
        $this->cacheTag = $key;

        return $this;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public function get($key, $default = NULL)
    {
        $value = $this->connect($key)->get($this->prefix . $key);
        if ($value === false) {
            return $default instanceof \Closure ? $default() : $default;
        } else {
            return $this->unserialize($value);
        }
    }

    /**
     * Store an item in the cache for a given number of minutes.
     *
     * @param  string $key
     * @param  mixed $value
     * @param  int $seconds
     * @return void
     */
    public function put($key, $value, $seconds = 0)
    {
        $data = $value instanceof \Closure ? $value() : $value;

        $this->connect($key)->setEx($this->prefix . $key, (int)$seconds, $this->serialize($data));

        $this->saveKey2Tag($this->prefix . $key);
    }

    /**
     * Get an item from the cache, or store the default value.
     *
     * @param  string $key
     * @param  \DateTime|int $seconds
     * @param  \Closure $callback
     * @return mixed
     */
    public function remember($key, $seconds, \Closure $callback)
    {
        // If the item exists in the cache we will just return this immediately
        // otherwise we will execute the given Closure and cache the result
        // of that execution for the given number of minutes in storage.
        if (!is_null($value = $this->get($key))) {
            return $value;
        }

        $this->put($key, $value = $callback(), $seconds);

        return $value;
    }

    /**
     * Get an item from the cache, or store the default value forever.
     *
     * @param  string $key
     * @param  \Closure $callback
     * @return mixed
     */
    public function rememberForever($key, \Closure $callback)
    {
        // If the item exists in the cache we will just return this immediately
        // otherwise we will execute the given Closure and cache the result
        // of that execution for the given number of minutes in storage.
        if (!is_null($value = $this->get($key))) {
            return $value;
        }

        $this->forever($key, $value = $callback());

        return $value;
    }

    /**
     * save key on tags
     *
     * @param $key
     */
    private function saveKey2Tag($key)
    {
        if ($this->cacheTag) {
            // First get the tags
            $siteTags = $this->get($this->cacheTag, []);

            if (!in_array($key, $siteTags)) {
                $siteTags[] = $key;
            }

            $this->forever($this->cacheTag, $siteTags);
            $this->cacheTag = NULL;
        }
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string $key
     * @param  mixed $value
     * @return void
     */
    public function forever($key, $value)
    {
        $this->connect($key)->set($this->prefix . $key, $this->serialize($value));
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string $key
     * @return void
     */
    public function forget($key)
    {
        $this->connect($key)->delete($this->prefix . $key);
    }

    /**
     * 根据tag清除掉缓存
     *
     * @param $tag
     */
    public function clearTag($tag)
    {
        $tagkeys = $this->get($tag, []);

        foreach ($tagkeys as $key) {
            $this->forget($key);
        }

        $this->forget($tag);

        $this->cacheTag = NULL;
    }

    /**
     * Serialize the value.
     *
     * @param  mixed $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) ? $value : serialize($value);
    }

    /**
     * Unserialize the value.
     *
     * @param  mixed $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : unserialize($value);
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @return int
     */
    public function increment($key, $value = 1)
    {
        return $this->connect($key)->incrBy($this->prefix . $key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @return int
     */
    public function decrement($key, $value = 1)
    {
        return $this->connect($key)->decrBy($this->prefix . $key, $value);
    }

    /**
     * 在对象中调用一个不可访问方法时，__call() 会被调用。
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
        if (is_array(self::$connections)) {
            foreach (self::$connections as $conn) {
                if (!empty($conn)) {
                    $conn->close();
                }
            }
        }

        self::$connections = NULL;
    }
}