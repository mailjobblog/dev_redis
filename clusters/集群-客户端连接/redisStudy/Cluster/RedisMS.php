<?php

namespace Shineyork\Redis\Cluster;

use Shineyork\Redis\Cluster\Tratis\Sentinel;
use Shineyork\Redis\Cluster\Tratis\MS;
use Shineyork\Redis\Cluster\Tratis\Cluster;

class RedisMS
{
    use Sentinel;
    use Cluster;
    use MS;

    protected $config;

    /**
     * 记录redis连接
     * [
     *     "master" => \\Redis,
     *     "slaves "=> [
     *       'slaveIP1:port' => \Redis
     *       'slaveIP2:port' => \Redis
     *       'slaveIP3:port' => \Redis
     *    ],
     *    'sentinel' => [
     *
     *    ],
     * ]
     */
    protected $connections;

    protected $connIndexs;

    public function __construct($config)
    {
        $this->config = $config;
        $this->{$this->config['initType'] . "Init"}();
    }

    // ---初始化操作---

    protected function isNormalInit()
    {
        $this->connections['master'] = $this->getRedis($this->config['master']['host'], $this->config['master']['port']);
    }

    /**
     * 去维护从节点列表
     * 
     *
     * 重整 1台服务器，多个从节点
     */
    protected function maintain()
    {
        swoole_timer_tick(2000, function ($timer_id) use ($masterRedis) {
            if ($this->config['initType'] == 'isSentinel') {
                Input::info("哨兵检测");
                $this->sentinelInit();
            }
            $this->delay();
        });
    }


    protected function stringToArr($str, $flag1 = ',', $flag2 = '=')
    {
        $arr = explode($flag1, $str);
        $ret = [];
        foreach ($arr as $key => $value) {
            $arr2 = explode($flag2, $value);
            $ret[$arr2[0]] = $arr2[1];
        }
        return $ret;
    }

    protected function createConn($conns, $flag = 'slaves')
    {
        foreach ($conns as $key => $conn) {
            if ($redis = $this->getRedis($conn['host'], $conn['port'])) {
                $this->connections[$flag][$this->redisFlag($conn['host'], $conn['port'])] = $redis;
            }
        }
        $this->connIndexs[$flag] = array_keys($this->connections[$flag]);
    }

    protected function redisFlag($host, $port)
    {
        return $host . ":" . $port;
    }

    public function getRedis($host, $port)
    {
        try {
            $redis = new \Redis();
            $redis->pconnect($host, $port);
            return $redis;
        } catch (\Exception $e) {
            Input::info($this->redisFlag($host, $port), "连接有问题");
            return null;
        }
    }

    public function getconnIndexs()
    {
        return $this->connIndexs;
    }


    public function getMaster()
    {
        return $this->connections['master'];
    }
    public function getSlaves()
    {
        return $this->connections['slaves'];
    }

    public function oneConn($flag = 'slaves', $redisFlag = null)
    {
        if (!empty($redisFlag)) {
            return $this->connections[$flag][$redisFlag];
        }

        $indexs = $this->connIndexs[$flag];
        $i = mt_rand(0, count($indexs) - 1);

        Input::info($indexs[$i], "选择的连接");

        return $this->connections[$flag][$indexs[$i]];
    }


    public function runCall($command, $params = [])
    {
        try {
            // if ($this->config['is_ms']) {
            $redis = $this->{$this->config['runCall'][$this->config['initType']]}($command, $params);
            // $redis = $this->getRedisCall($command, $params);
            return $redis->{$command}(...$params);
            // }
        } catch (\Exception $e) {
        }
    }
}
