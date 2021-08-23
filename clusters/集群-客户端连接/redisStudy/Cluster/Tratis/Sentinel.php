<?php
namespace Shineyork\Redis\Cluster\Tratis;

use Shineyork\Redis\Cluster\Input;

/**
 *
 */
trait Sentinel
{
    protected $masterName = null;

    protected $masterFlag = null;

    protected $sentinelFlag = 'sentinel';

    protected function isSentinelInit()
    {
        $this->setSentinels($this->config['sentinels']['addr']);
        $this->sentinelInit();
    }

    public function sentinelInit()
    {
        // 获取哨兵
        $sentinel = $this->oneConn($this->sentinelFlag);
        // Input::info($sentinel);
        $masterInfo = $sentinel->rawCommand('sentinel', 'get-master-addr-by-name', $this->config['sentinels']['master_name']);
        $newFlag = $this->redisFlag($masterInfo[0], $masterInfo[1]);
        // 判断是否新的主节点
        if ($this->masterFlag == $newFlag) {
            Input::info("主节点没有问题");
            return;
        }
        Input::info($newFlag , "主节点有问题，切换节点");
        $this->masterFlag = $newFlag;

        // 配置维护

        unset($this->config['master']);
        unset($this->config['slaves']);

        $this->config['master'] = [
            'host' => $masterInfo[0],
            'port' => $masterInfo[1]
        ];

        $slavesInfo = $sentinel->rawCommand('sentinel', 'slaves', $this->config['sentinels']['master_name']);
        foreach ($slavesInfo as $key => $slave) {
            $this->config['slaves'][$key] = [
                'host' => $slave[3],
                'port' => $slave[5]
            ];
        }

        Input::info($this->config);
        // 这里做维护即可
        $this->isMsInit();
    }

    public function setSentinels($sentinels)
    {
        $this->createConn($sentinels, $this->sentinelFlag);
    }
}
