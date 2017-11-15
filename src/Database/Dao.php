<?php

/**
 * Mysqli Model wrapper
 *
 * @category  Database Access
 * @package   MysqliDb
 * @author    Alexander V. Butenko <a.butenka@gmail.com>
 * @copyright Copyright (c) 2015
 * @license   http://opensource.org/licenses/gpl-3.0.html GNU Public License
 * @link      http://github.com/joshcam/PHP-MySQLi-Database-Class
 * @version   2.6-master
 *
 * @method int count ()
 * @method Dao ArrayBuilder()
 * @method Dao JsonBuilder()
 * @method Dao ObjectBuilder()
 * @method mixed byId (string $id, mixed $fields)
 * @method mixed get (mixed $limit, mixed $fields)
 * @method mixed getOne (mixed $fields)
 * @method mixed paginate (int $page, array $fields)
 * @method Dao query ($query, $numRows)
 * @method Dao rawQuery ($query, $bindParams, $sanitize)
 * @method Dao join (string $objectName, string $key, string $joinType, string $primaryKey)
 * @method Dao with (string $objectName)
 * @method Dao groupBy (string $groupByField)
 * @method Dao orderBy ($orderByField, $orderbyDirection, $customFields)
 * @method Dao where ($whereProp, $whereValue, $operator)
 * @method Dao orWhere ($whereProp, $whereValue, $operator)
 * @method Dao setQueryOption ($options)
 * @method Dao setTrace ($enabled, $stripPrefix)
 * @method Dao withTotalCount ()
 * @method Dao startTransaction ()
 * @method Dao commit ()
 * @method Dao rollback ()
 * @method Dao ping ()
 * @method string getLastError ()
 * @method string getLastQuery ()
 **/

namespace Beauty\Database;

use Beauty\Database\Connector\MysqlConnector;

abstract class Dao
{
    /**
     * Working instance of MysqliDb created earlier
     *
     * @var MysqlClient
     */
    private $dbClient;
    /**
     * Return type: 'Array' to return results as array, 'Object' as object
     * 'Json' as json string
     *
     * @var string
     */
    public $returnType = 'Array';
    /**
     * Per page limit for pagination
     *
     * @var int
     */
    protected $pageLimit = 20;
    /**
     * Variable that holds total pages count of last paginate() query
     *
     * @var int
     */
    public static $totalPages = 0;

    /**
     * 记录insert/update/select错误
     *
     * @var array
     */
    public $errors = null;

    /**
     * 主键，默认为id
     *
     * @var string
     */
    protected $primaryKey = 'id';
    /**
     * Table name for an object. Class name will be used by default
     *
     * @var string
     */
    protected $dbTable;

    /**
     * 当前连接数据库的配置名称
     *
     * @var string
     */
    protected $connection = "default";
    /**
     * 当前链接数据库的渠道, master or slave
     *
     * @var string
     */
    protected $channel = MysqlConnector::QUERY_SLAVE_CHANNEL;
    /**
     * 查询字段，默认为所有字段
     *
     * @var string
     */
    protected $fields;
    /**
     * The hook event.
     *
     * @var array
     */
    protected static $hooks;

    /**
     * 创建对象必须设置dbTable（表名字）
     *
     * Dao constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        if (empty ($this->dbTable)) {
            throw new \Exception("you must confirm table name.");
        }

        static::booting();

        $this->dbClient = new MysqlClient($this->connection);
    }

    /**
     * 启动钩子的入口函数
     */
    protected static function booting()
    {
    }

    /**
     * 注册插入后的回调函数
     *
     * @param  \Closure|string $callback
     * @return void
     */
    public static function created($callback)
    {
        $name  = get_called_class();
        $event = "created";
        self::addHooks("model.{$name}.{$event}", $callback);
    }

    /**
     * 注册更新后的回调
     *
     * @param  \Closure|string $callback
     * @return void
     */
    public static function updated($callback)
    {
        $name  = get_called_class();
        $event = "updated";
        self::addHooks("model.{$name}.{$event}", $callback);
    }

    /**
     * 注册删除后的回调
     *
     * @param  \Closure|string $callback
     * @return void
     */
    public static function deleted($callback)
    {
        $name  = get_called_class();
        $event = "deleted";
        self::addHooks("model.{$name}.{$event}", $callback);
    }

    /**
     * 添加钩子
     *
     * @param $event
     * @param $callback
     */
    protected static function addHooks($event, $callback)
    {
        self::$hooks[$event] = $callback;
    }

    /**
     * 启动该模型的钩子
     *
     * @param  string $event
     * @param  array $data
     * @return mixed
     */
    protected function fireModelHook($event, $data = [])
    {
        if (!isset(self::$hooks)) {
            return true;
        }

        // We will append the names of the class to the event to distinguish it from
        // other model events that are fired, allowing us to listen on each model
        // event set individually instead of catching event for all the models.
        $event = "model." . get_class($this) . ".{$event}";

        return call_user_func_array(self::$hooks[$event], [$data]);
    }

    /**
     * get master db connection
     */
    public function getMysqlConnection()
    {
        $this->dbClient = new MysqlClient($this->connection);

        return $this->dbClient;
    }

    /**
     * Magic setter function
     *
     * @return mixed
     */
    public function __set($name, $value)
    {
        if (property_exists($this, 'hidden') && array_search($name, $this->hidden) !== false) {
            return;
        }

        $this->data[$name] = $value;
    }

    /**
     * Magic getter function
     *
     * @param $name string name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (property_exists($this, 'hidden') && array_search($name, $this->hidden) !== false) {
            return null;
        }

        if (isset ($this->data[$name]) && $this->data[$name] instanceof Dao) {
            return $this->data[$name];
        }

        if (isset ($this->data[$name])) {
            return $this->data[$name];
        }

        if (property_exists($this->dbClient, $name)) {
            return $this->dbClient->$name;
        }
    }

    public function __isset($name)
    {
        if (isset ($this->data[$name])) {
            return isset ($this->data[$name]);
        }

        if (property_exists($this->dbClient, $name)) {
            return isset ($this->dbClient->$name);
        }
    }

    public function __unset($name)
    {
        unset ($this->data[$name]);
    }

    /**
     * 设置数据库连接
     *
     * @param $connection
     * @return $this
     */
    private function on($connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * 设置数据库的主从
     *
     * @param $channel
     * @return $this
     */
    private function channel($channel)
    {
        $this->channel = $channel;

        return $this;
    }

    /**
     * 设置查询字段
     *
     * @param $fields
     * @return $this
     */
    private function select($fields)
    {
        $this->fields = $fields;

        return $this;
    }

    private function where($whereProp, $whereValue = 'DBNULL', $operator = '=', $cond = 'AND')
    {
        if (is_array($whereProp)) {
            foreach ($whereProp as $k => $v) {
                $this->dbClient->where($k, $v, $operator, $cond);
            }
        } else {
            $this->dbClient->where($whereProp, $whereValue, $operator, $cond);
        }

        return $this;
    }

    /**
     *
     *
     * @param $data
     * @return mixed 插入id or false in case of failure
     */
    public function insert($data)
    {
        if (!empty ($this->timestamps) && in_array("created_at", $this->timestamps)) {
            $data['created_at'] = date("Y-m-d H:i:s");
        }

        /**
         * 连接主库
         */
        $this->dbClient->setQueryChannel(MysqlConnector::QUERY_MASTER_CHANNEL);

        /**
         * 预备数据
         */
        $sqlData = $this->prepareData($data);
        /**
         * 校验数据
         */
        if (!$this->validate($sqlData)) {
            return false;
        }

        $ret = $this->dbClient->insert($this->dbTable, $sqlData);

        if ($ret) {
            $this->fireModelHook('created', $sqlData);
        }

        return $ret;
    }

    /**
     * @param null $data Optional update data to apply to the object
     * @return bool
     */
    public function update($data = null)
    {
        if (empty ($this->dbFields)) {
            return false;
        }

        if (!$data) {
            return false;
        }

        if (!empty ($this->timestamps) && in_array("updated_at", $this->timestamps)) {
            $data['updated_at'] = date("Y-m-d H:i:s");
        }

        $sqlData = $this->prepareData($data);
        if (!$this->validate($sqlData)) {
            return false;
        }

        /**
         * 主库
         */
        $this->dbClient->setQueryChannel(MysqlConnector::QUERY_MASTER_CHANNEL);
        $this->dbClient = $this->getMysqlConnection();
        $ret            = $this->dbClient->update($this->dbTable, $sqlData);

        if ($ret) {
            $this->fireModelHook('updated', $sqlData);
        }

        return $ret;
    }

    /**
     * Delete method. Works only if object primaryKey is defined
     * 只允许使用id，删除
     *
     * @param mixed $id 主id，主键只允许是int
     * @return boolean Indicates success. 0 or 1.
     */
    public function delete(int $id)
    {
        if (empty ($this->primaryKey)) {
            return false;
        }

        if ($id <= 0) {
            return false;
        }
        $this->dbClient->setQueryChannel(MysqlConnector::QUERY_MASTER_CHANNEL);
        $this->dbClient = $this->getMysqlConnection();
        $this->dbClient->where($this->primaryKey, $id);
        /**
         * 主库
         */
        $ret = $this->dbClient->delete($this->dbTable);

        if ($ret) {
            $this->fireModelHook('deleted', $id);
        }

        return $ret;
    }

    /**
     * 根据主键查找对象
     *
     * @access public
     * @param $id int Primary Key
     * @param array|string $fields Array or coma separated list of fields to fetch
     *
     * @return array
     */
    private function find(int $id)
    {
        if ($id <= 0) {
            return [];
        }

        $this->dbClient->where($this->dbTable . '.' . $this->primaryKey, $id);

        return $this->getOne();
    }

    /**
     * convenient function to fetch one object. Mostly will be together with where()
     *
     * @access public
     *
     * @return array
     */
    protected function getOne()
    {
        $this->dbClient->setQueryChannel($this->channel);
        $results = $this->dbClient->arrayBuilder()->getOne($this->dbTable, $this->fields);
        $this->_reset();

        if ($this->dbClient->count == 0) {
            return [];
        }

        return $results;
    }

    /**
     * 获取查询内容，以数组形式返回，数据内为Object。
     *
     * @access public
     * @param integer|array $limit Array to define SQL limit in format Array ($count, $offset)
     *                             or only $count
     *
     * @return mixed Array of Clients
     */
    protected function get($limit = null)
    {
        $this->dbClient->setQueryChannel($this->channel);

        $results = $this->dbClient->arrayBuilder()->get($this->dbTable, $limit, $this->fields);
        $this->_reset();

        if ($this->dbClient->count == 0) {
            return [];
        }

        return $results;
    }

    /**
     * Function to get a total records count
     *
     * @return int
     */
    protected function count()
    {
        $this->dbClient->setQueryChannel($this->channel);

        $res = $this->dbClient->arrayBuilder()->getValue($this->dbTable, "count(*)");
        if (!$res) {
            return 0;
        }

        return $res;
    }

    /**
     * 分页函数
     *
     * @access public
     * @param int $page Page number
     * @param array|string $fields Array or coma separated list of fields to fetch
     * @return array
     */
    private function paginate($page)
    {
        $this->dbClient->pageLimit = $this->pageLimit;
        $res                       = $this->dbClient->paginate($this->dbTable, $page, $this->fields);
        self::$totalPages          = $this->dbClient->totalPages;

        $this->_reset();
        if ($this->dbClient->count == 0) {
            return null;
        }

        return $res;
    }

    /**
     * 校验数据
     *
     * @param $data
     * @return bool
     */
    private function validate($data)
    {
        if (!$this->dbFields) {
            return true;
        }

        foreach ($this->dbFields as $key => $desc) {
            $type     = null;
            $required = false;
            if (isset ($data[$key])) {
                $value = $data[$key];
            } else {
                $value = null;
            }

            if (is_array($value)) {
                continue;
            }

            if (isset ($desc[0])) {
                $type = $desc[0];
            }

            if (isset ($desc[1]) && ($desc[1] == 'required')) {
                $required = true;
            }

            if ($required && strlen($value) == 0) {
                $this->errors[] = Array($this->dbTable . "." . $key => "is required");
                continue;
            }
            if ($value == null) {
                continue;
            }

            $valid = true;
            if ($type == "text") {
                continue;
            } elseif ($type == "double") {
                $valid = is_numeric($value);
            } elseif ($type == "int") {
                $valid = preg_match('/^-?([0-9])+$/i', $value);
            } elseif ($type == "bool") {
                $valid = is_bool($value);
            } elseif ($type == "datetime") {
                $valid = strtotime($value) !== false;
            }

            if (!$valid) {
                $this->errors[] = Array($this->dbTable . "." . $key => "$type validation failed");
                continue;
            }
        }

        return !count($this->errors) > 0;
    }

    /**
     * 准备数据
     *
     * @param $data
     * @return array
     */
    private function prepareData($data)
    {
        $this->errors = [];
        $sqlData      = [];
        if (count($data) == 0) {
            return [];
        }

        if (!$this->dbFields) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $this->dbFields)) {
                continue;
            }

            if (!is_array($value)) {
                $sqlData[$key] = $value;
                continue;
            }
        }

        return $sqlData;
    }

    public function getLastQuery()
    {
        return $this->dbClient->getLastQuery();
    }

    public function getLastError()
    {
        return $this->dbClient->getLastError();
    }

    /**
     * Catches calls to undefined methods.
     *
     * Provides magic access to private functions of the class and native public mysqlidb functions
     *
     * @param string $method
     * @param mixed $arg
     *
     * @return mixed
     */
    public function __call($method, $arg)
    {
        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $arg);
        }

        call_user_func_array(array($this->dbClient, $method), $arg);

        return $this;
    }

    /**
     * Catches calls to undefined static methods.
     *
     * Transparently creating Client class to provide smooth API like name::get() name::orderBy()->get()
     *
     * @param string $method
     * @param mixed $arg
     *
     * @return mixed
     */
    public static function __callStatic($method, $arg)
    {
        $obj    = new static;
        $result = call_user_func_array(array($obj, $method), $arg);
        if (method_exists($obj, $method)) {
            return $result;
        }

        return $obj;
    }

    /**
     * 查询后重置这些内容
     *
     * @access    private
     * @return    void
     */
    function _reset()
    {
        $this->fields = null;
    }
}