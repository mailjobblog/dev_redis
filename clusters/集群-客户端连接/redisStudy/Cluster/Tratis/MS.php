<?php

namespace Shineyork\Redis\Cluster\Tratis;

use Shineyork\Redis\Cluster\Input;

trait MS
{
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
    //  ----- 初始化操作 -----

    protected function isMsInit()
    {
        $this->connections['master'] = $this->getRedis($this->config['master']['host'], $this->config['master']['port']);

        $this->createConn($this->config['slaves']);

        $this->maintain();
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
                }
            } else {
                unset($this->connections['slaves'][$slaveFlag]);
            }
        }
        $this->createConn($slaves);
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
