<?php
// redisCluster
// 节点
class clusterNode
{
    public $flags;
    public $ping_sent;
    public $fail_reports;
    public $fail_time;
    public $configEpoch;
    public $nodeId;
}


// 集群
class cluster
{
    /**
     * 节点集合
     * @var clusterNode
     */
    public $nodes = [];
    public $size;
    public $failover_auth_time;
    public $failover_auth_rank;
    public $currentEpoch;

    public function __construct()
    {
        // .. 初始化
    }
    /**
     * 根据传递的key计算slot地址
     * uu{uu
     * ppp}oo
     *
     * oo{pp}oo
     * 
     * @param  [type] $key [description]
     * @return [type]      [description]
     */
    public function keyHashSlot($key)
    {
        $s = $e = 0;
        $keylen = strlen($key);
        for ($s = 0; $s < $keylen; $s++)
            if ($key[$s] == '{') break;

        // 根据整个长度计算slot
        if ($s == $keylen) return crc16($key, $keylen) & 0x3FFF;

        for ($e = $s + 1; $e < $keylen; $e++)
            if ($key[$e] == '}') break;

        // 根据整个长度计算slot
        if ($e == $keylen || $e == $s + 1) return crc16($key, $keylen) & 0x3FFF;
        // 根据{  } 内容计算长度
        return crc16($key + $s + 1, $e - $s - 1) & 0x3FFF;
    }
}


class server
{
    /**
     * @var cluster
     */
    protected $cluster;
    protected $cluster_node_timeout;
    protected $numslaves;
    protected $slaveof;
    protected $slaves;
    protected $repl_offset;
    protected $configEpoch;
    protected $nodeId;
    // 当前为主节点
    const CLUSTER_NODE_MYSTER = 1;
    // 主观 下线
    const CLUSTER_NODE_PFALL = 4;

    const CLUSTER_NODE_FALL = 8;

    public function __construct()
    {
        $this->cluster = new cluster();
    }
    // 节点故障检查 -》 主观下线
    public function clusterCron()
    {
        // .. 忽略其他代码
        foreach ($node as $key => $this->cluster->nodes) {
            if ($node->flags == self::CLUSTER_NODE_MYSTER) {
                continue; // 如果是当前自己跳过
            }

            $now = time();
            // 自身节点最后一次与该ping通信的时间差
            $delay = $now - $node->ping_sent;
            // 如果通信时间差超过cluster_node_timeout，将该节点编辑为PFALL(主观下线)
            if ($delay > $this->cluster_node_timeout) {
                $node->flags = self::CLUSTER_NODE_PFALL;
            }
        }
    }
    // 客观下线的流程
    public function markNodeAsFailingIfNeeded(clusterNode $failNode)
    {
        $failures = null;
        // 主观下线节点数必须超过槽节点的数量的一半
        $needed_quorum = ($this->cluster->size / 2) + 1;
        // 统计failNode节点有效的下线报告数量（不包括当前节点）
        $failures = $this->clusterNodeFailureReportsCount($failNode);
        // 如果当前节点是主节点，奖当前节点积累加到failures
        if (nodeIsMaster($this)) {
            $failures++;
        }
        // 下线报告数量不足槽节点的一半退出
        if ($failures < $needed_quorum) {
            return;
        }
        // 将该节点标记为客观下线状态(fail)
        $failNode->flags = self::CLUSTER_NODE_FALL;
        // 更新客观现下线时间
        $failNode->fail_time = time();
        // 如果当前节点为主接地那，向集群广播对应的节点的fail消息
        if (nodeIsMaster($this)) {
            clusterSendFail($failNode);
        }
        $this->clusterDoBeforeSleep();
    }
    // 获取从节点优先级
    public function clusterGetSlaveRank()
    {   // 这是从节点中执行
        $rank = 0;
        // 获取从节点的主节点
        $this->nodeIsSlave($this);
        $master = $this->slaveof;
        // 获取当前节点的复制偏移量
        $myoffset = $this->replicationGetSlaveOffset();
        // 根据其他节点进行复制偏移量计算
        for ($i = 0; $i < $master->numslaves; $i++) {
            // rank 表示当前从接地那在所有从接地那的复制偏移量排名，为0表示最大
            if ($master->slaves[$i] != $this && $master->slaves[$i]->repl_offset > $myoffset) {
                $rank++;
            }
        }
        return $rank;
    }

    public function clusterHandleSlaveFailover()
    {
        // 前面过滤很多代码

        // 默认触发选举时间：发现客观下线后一秒内执行
        $this->cluster->failover_auth_time = time() + 500 + mt_rand(500);
        // 获取当前从接地那排名
        $rank = $this->clusterGetSlaveRank();
        // 使用$rank * 1000时间累加
        $this->cluster->failover_auth_time += $rank * 1000;
        // 更新当前从节点排名
        $this->cluster->failover_auth_rank = $rank;
    }

    public function clusterHandleConfigEpochCollision(clusterNode $sender)
    {
        if (
            $sender->configEpoch != $this->configEpoch ||
            !$this->nodeIsMaster($sender) || !$this->nodeIsMaster($this)
        ) {
            return;
        }
        // 发送节点的nodeId小于自身节点nodeId时忽略|
        if ($sender->nodeId <= $this->nodeId) {
            return;
        }
        // 更新全局和自身配置纪元
        $this->cluster->currentEpoch++;
        $this->configEpoch = $this->cluster->currentEpoch;
    }
    // ------
    public function replicationGetSlaveOffset()
    {
    }
    public function nodeIsSlave($node)
    {
    }
    public function nodeIsMaster($node)
    {
    }
    public function clusterNodeFailureReportsCount()
    {
    }
    public function clusterDoBeforeSleep()
    {
    }
}
