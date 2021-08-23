<?php

namespace Shineyork\Redis\SentinelClient;

use Shineyork\Redis\SentinelClient\Tratis\Sentinel;

class RedisMS
{
    use Sentinel;

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

    protected $call = [
        'write' => [
            'set',
            'sadd'
        ],
        'read' => [
            'get',
            'smembers'
        ],
    ];

    public function __construct($config)
    {
        $this->config = $config;
        $this->{$this->config['initType'] . "Init"}();
    }

    // ---初始化操作---

    protected function isMsInit()
    {
        $this->connections['master'] = $this->getRedis($this->config['master']['host'], $this->config['master']['port']);

        $this->createConn($this->config['slaves']);

        // Input::info($this->connections, "这是获取的连接");
        // Input::info($this->connIndexs, "这是连接的下标");

        $this->maintain();
    }

    protected function isNormalInit()
    {
        $this->connections['master'] = $this->getRedis($this->config['master']['host'], $this->config['master']['port']);
    }

    /**
     * 去维护从节点列表
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
    // 这是处理从节点延迟问题
    protected function delay()
    {
        try {
            $masterRedis = $this->getMaster(); // 故障迁移之后是使用原有主节点的连接
            $replInfo = $masterRedis->info('replication');
        } catch (\Exception $e) {
            Input::info("哨兵检测");
            return null;
        }

        $masterOffset = $replInfo['master_repl_offset'];
        $slaves = [];
        for ($i = 0; $i < $replInfo['connected_slaves']; $i++) {
            $slaveInfo = $this->stringToArr($replInfo['slave' . $i]);
            $slaveFlag = $this->redisFlag($slaveInfo['ip'], $slaveInfo['port']);
            if (($masterOffset - $slaveInfo['offset']) < 1000) {
                if (!in_array($slaveFlag, $this->connIndexs)) {
                    $slaves[$slaveFlag] = [
                        'host' => $slaveInfo['ip'],
                        'port' => $slaveInfo['port']
                    ];
                    // Input::info($slaveFlag, "新增从节点");
                }
            } else {
                // Input::info($slaveFlag, "删除节点");
                unset($this->connections['slaves'][$slaveFlag]);
            }
        }
        $this->createConn($slaves);
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

    /**
     * $slaves = [
     *   'slave1' => [
     *     'host' => '192.160.1.130',
     *     'port' => 6379
     *    ],
     *   'slave2' => [
     *     'host' => '192.160.1.140',
     *     'port' => 6379
     *   ]
     * ]
     * 
     */
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

    public function oneConn($flag = 'slaves')
    {
        $indexs = $this->connIndexs[$flag];
        $i = mt_rand(0, count($indexs) - 1);

        Input::info($indexs[$i], "选择的连接");

        return $this->connections[$flag][$indexs[$i]];
    }


    public function runCall($command, $params = [])
    {
        try {
            // if ($this->config['is_ms']) {

            $redis = $this->getRedisCall($command);
            return $redis->{$command}(...$params);
            // }
        } catch (\Exception $e) {
        }
    }
    /**
     * 判断操作类型
     * 
     * @param  [type]  $command [description]
     * @return boolean          [description]
     */
    protected function getRedisCall($command)
    {
        if (in_array($command, $this->call['write'])) {
            return $this->getMaster();
        } else if (in_array($command, $this->call['read'])) {
            return $this->oneConn();
        } else {
            throw new \Exception("不支持");
        }
    }
}
