<?php

/**
 * 使用Hashring实现哈希分布。
 */

namespace Beauty\Cache;

use Beauty\Core\App;
use Beauty\Lib\HashRing;
use Memcached;

class MemcacheClient
{
    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;

    /**
     * 缓存标签
     *
     * @var string
     */
    protected $cacheTag;

    /**
     * 哈希换对象
     *
     * @var HashRing
     */
    private $hashring;

    /**
     * 保存memcached连接
     *
     * @var
     */
    private static $connections;

    /**
     * 单例
     *
     * @var MemcacheClient
     */
    private static $_instance;

    /**
     * MemcacheClient constructor.
     * 初始化memcached配置，生成哈希环对象
     */
    function __construct($config = "memcached")
    {
        $this->config   = App::config()->get('cache');
        $this->prefix   = $this->config[$config]['prefix'];
        $this->hashring = new HashRing();
        $this->hashring->add($this->config[$config]['hosts']);
    }

    /**
     * 生成单例对象
     *
     * @return MemcacheClient
     */
    public static function getInstance()
    {
        if (self::$_instance == NULL) {
            self::$_instance = new MemcacheClient();
        }

        return self::$_instance;
    }

    /**
     * 获取memcache服务器
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

        $memcached = new Memcached();
        $memcached->addServer($host, $port);

        // check memcache connection
        if ($memcached->getVersion() === false) {
            throw new \RuntimeException("Could not establish Memcached connection.");
        }

        self::$connections[$server] = $memcached;

        return self::$connections[$server];
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string $key
     * @param  mix $default
     * @return mixed
     */
    public function get($key, $default = NULL)
    {
        $memcached = $this->connect($key);

        $value = $memcached->get($this->prefix . $key);

        if ($memcached->getResultCode() == Memcached::RES_NOTFOUND) {
            return $default instanceof \Closure ? $default() : $default;
        } elseif ($memcached->getResultCode() == 0) {
            return $value;
        }
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

        $this->connect($key)->set($this->prefix . $key, $data, $seconds);

        $this->saveKey2Tag("set", $this->prefix . $key);

    }

    /**
     * Get an item from the cache, or store the default value.
     *
     * @param  string $key
     * @param  \DateTime|int $minutes
     * @param  \Closure $callback
     * @return mixed
     */
    public function remember($key, $minutes, \Closure $callback)
    {
        // If the item exists in the cache we will just return this immediately
        // otherwise we will execute the given Closure and cache the result
        // of that execution for the given number of minutes in storage.
        if (!is_null($value = $this->get($key))) {
            return $value;
        }

        $this->put($key, $value = $callback(), $minutes);

        return $value;
    }

    /**
     * save key on tags
     *
     * @param $func
     * @param $key
     */
    private function saveKey2Tag($func, $key)
    {
        if ($this->cacheTag) {

            // First get the tags
            $siteTags = $this->get($this->cacheTag, []);

            if (!in_array($key, $siteTags)) {
                $siteTags[] = $key;
            }

            call_user_func_array([$this->connect($this->cacheTag), $func], [$this->prefix . $this->cacheTag, $siteTags]);

            $this->cacheTag = NULL;
        }
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @return mixed
     */
    public function increment($key, $value = 1)
    {
        $this->saveKey2Tag("increment", $this->prefix . $key);

        return $this->connect($key)->increment($this->prefix . $key, $value);

    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string $key
     * @param  mixed $value
     * @return mixed
     */
    public function decrement($key, $value = 1)
    {
        $this->saveKey2Tag("decrement", $this->prefix . $key);

        return $this->connect($key)->decrement($this->prefix . $key, $value);
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
        $this->connect($key)->put($key, $value, 0);
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
        $tagkeys = $this->get($tag);

        foreach ($tagkeys as $key) {
            $this->forget($key);
        }

        $this->forget($tag);

        $this->cacheTag = NULL;
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
}