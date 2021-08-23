<?php
namespace Shineyork\Redis\Cluster\Tratis;

use Shineyork\Redis\Cluster\Input;
use Shineyork\Redis\Cluster\CRC16;

/**
 *
 */
trait Cluster
{
    protected $clusterlFlag = 'cluster';

    /**
     * 记录slots 与节点的映射
     * [
     *    'slots-start:slots-end' => [
     *        'm' => [
     *            0 => 'ip:port',
     *            1 => redisObject
     *        ],
     *        's' => [
     *            [
     *              'ip:port' => redisObject
     *            ]
     *        ]
     *    ]
     * ]
     * @var array
     */
    protected $slots;

    public function isClusterInit()
    {
        // 1. 初始化连接
        $this->initClusterNodeConns($this->config['cluster']['nodes']);
        // 2. 初始化槽的映射
        $this->reshardSlotsNode();
    }
    // 初始化连接
    public function initClusterNodeConns($clusters)
    {
        $this->createConn($clusters, $this->clusterlFlag);
    }
    // 初始化槽的映射
    public function reshardSlotsNode()
    {
        // 获取连接
        $cluster = $this->oneConn($this->clusterlFlag);
        // 获取槽映射信息
        $slotsInfo = $cluster->rawCommand('cluster', 'slots');
        // Input::info($slotsInfo, '获取的槽的映射信息');

        foreach ($slotsInfo as $key => $slots) {
            $masterFlag = $this->redisFlag($slots[2][0], $slots[2][1]);
            // $slaveFlag = $this->redisFlag($slots[3][0], $slots[3][1]);
            // 槽的标识
            $slotRang = $slots[0]."-".$slots[1];
            $this->slots[$slotRang] = [
                'm'=>[
                    $masterFlag,
                    $this->oneConn($this->clusterlFlag, $masterFlag)
                ]
                // 's'
            ];
        }
        Input::info($this->slots, '当前节点与槽的结构');
    }
    // 根据传递的key计算slot地址
    public function keyHashSlot($key)
    {
        $s = $e =0;
        $keylen = strlen($key);
        for ($s = 0; $s < $keylen; $s++)
            if ($key[$s] == '{') break;

        // 根据整个长度计算slot
        if ($s == $keylen) return CRC16::redisCRC16($key,$keylen) % 16384;

        for ($e = $s+1; $e < $keylen; $e++)
            if ($key[$e] == '}') break;

        // 根据整个长度计算slot
        if ($e == $keylen || $e == $s+1) return CRC16::redisCRC16($key, $keylen) % 16384;
        // 根据{  } 内容计算长度
        return CRC16::redisCRC16($key + $s + 1, $e - $s - 1) % 16384;
    }
    // 根据槽获取节点
    public function getNodeBySlot($slot)
    {
        // array_walk($arr, function($value, $key){})
        array_walk($this->slots, function($slotInfo, $slotRang) use ($slot, &$node){
            $rang = \explode('-', $slotRang);
            if ($slot >= $rang[0] && $slot <= $rang[1]) {
                $node = $slotInfo;
            }
        });
        return $node;
    }

    public function clusterCommand($command, $params)
    {
        $slot = $this->keyHashSlot($params[0]);
        Input::info($slot, $params[0].'对应的数据槽');
        $nodes = $this->getNodeBySlot($slot);
        Input::info($nodes, '根据slot获取的节点');
        return $nodes['m'][1];
    }
}
