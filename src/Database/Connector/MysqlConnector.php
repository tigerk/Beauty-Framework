<?php

namespace Beauty\Database\Connector;


use Beauty\Core\App;

class MysqlConnector
{
    CONST QUERY_MASTER_CHANNEL = "master";
    CONST QUERY_SLAVE_CHANNEL  = "slave";

    /**
     * The active connection instances.
     *
     * @var array
     */
    static $connections = [];

    /**
     * The connection config
     *
     * @var array
     */
    protected $connectionsSettings = [];

    function __construct()
    {
        $this->connectionsSettings = App::config()->get('database');
    }

    /**
     * Get a database connection instance.
     *
     * @param null $connectionName
     * @param  string $channel
     * @return object
     * @throws \Exception
     * @internal param string $name
     */
    public function connection($connectionName = null, $channel = self::QUERY_MASTER_CHANNEL)
    {
        // If we haven't created this connection, we'll create it based on the config
        // provided in the application.
        if (isset(self::$connections[$connectionName][$channel]) && (self::$connections[$connectionName][$channel])) {
            return self::$connections[$connectionName][$channel];
        }

        if (!isset($this->connectionsSettings[$connectionName])) {
            throw new \Exception('Connection profile not set');
        }

        $params  = $this->serverPopulate($this->connectionsSettings[$connectionName][$channel]);
        $charset = $params['charset'];

        if (empty($params['host'])) {
            throw new \Exception('MySQL host or socket is not set');
        }

        $mysqli = new \mysqli($params['host'], $params['username'], $params['password'], $params['database'], $params['port']);
        if ($mysqli->connect_error) {
            throw new \Exception('Connect Error ' . $mysqli->connect_errno . ': ' . $mysqli->connect_error, $mysqli->connect_errno);
        }

        if (!empty($charset)) {
            $mysqli->set_charset($charset);
        }

        self::$connections[$connectionName][$channel] = $mysqli;

        return self::$connections[$connectionName][$channel];
    }

    /**
     * A method to disconnect from the database
     *
     * @params string $connection connection name to disconnect
     * @throws \Exception
     * @return void
     */
    public function disconnectAll()
    {
        foreach (self::$connections as $n => $conn) {
            foreach ($conn as $channel => $cn) {
                self::$connections[$n][$channel]->close();
            }
        }

        self::$connections = null;
    }

    /**
     * 用于计算链接的数据库权重
     *
     * @param $settings
     * @return mixed
     */
    protected function serverPopulate($settings)
    {
        // 将所有 server 按照权重整理为一个数组
        $bucket = [];
        foreach ($settings as $server) {
            $replicas = isset($server['weight']) ? $server['weight'] : 1;
            for ($i = 0; $i < $replicas; $i++) {
                $bucket[] = $server;
            }
        }

        $rand = mt_rand(0, count($bucket) - 1);

        return $bucket[$rand];
    }
}