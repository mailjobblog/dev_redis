<?php
namespace Shineyork\Redis\MasterAndSlave;

 class RedisMS
 {
     protected $config;

     /**
      * 记录redis连接
      * [
      *     "master" => \\Redis,
      *     "slaves "=> [
      *       'slaveIP1:port' => \Redis
      *       'slaveIP2:port' => \Redis
      *       'slaveIP3:port' => \Redis
      *    ]
      * ]
      */
     protected $connections;
     /**
      * [
      *    "0" => 'slaveIP1:port' ,
      *    "1" => 'slaveIP2:port',
      *    "2" => 'slaveIP3:port',
      * ]
      */
     protected $connSlaveIndexs;

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
         if ($config["is_ms"]) {
             $this->connections['master'] = $this->getRedis($config['master']['host'], $config['master']['port']);

             $this->createSlave($config['slaves']);

             Input::info($this->connections, "这是获取的连接");
             Input::info($this->connSlaveIndexs, "这是连接的下标");

             $this->maintain();

         }
         $this->config = $config;
     }

     // --------------主从维护--------------------

     /**
      * 去维护从节点列表
      *
      * 重整 1台服务器，多个从节点
      */
     protected function maintain()
     {
         /*
         1. 获取主节点连接信息
         2. 获取从节点的偏移量
         3. 获取连接个数
             3.1 偏移量的计算
             3.2 维护列表
          */
          $masterRedis = $this->getMaster();
          swoole_timer_tick(2000, function ($timer_id) use($masterRedis){
               // 得到主节点的连接信息
               $replInfo = $masterRedis->info('replication');
               // Input::info($replInfo, "复制信息");
               // 得到主节点偏移量
               $masterOffset = $replInfo['master_repl_offset'];
               // 记录新增的从节点
               $slaves = [];
               for ($i=0; $i < $replInfo['connected_slaves']; $i++) {
                   // 获取slave的信息
                   $slaveInfo = $this->stringToArr($replInfo['slave'.$i]);
                   $slaveFlag = $this->redisFlag($slaveInfo['ip'], $slaveInfo['port']);
                   // 延迟检测
                   if (($masterOffset - $slaveInfo['offset']) < 100) {
                       // 是正常范围
                       // 如果之前因为网络延迟删除了节点，现在恢复了网络 -》新增
                       // 这是动态新增
                       if (!in_array($slaveFlag, $this->connSlaveIndexs)) {
                           $slaves[$slaveFlag] = [
                               'host' => $slaveInfo['ip'],
                               'port' => $slaveInfo['port']
                           ];
                           Input::info($slaveFlag, "新增从节点");
                       }
                   } else {
                       // 延迟 -> 删除节点
                       Input::info($slaveFlag, "删除节点");
                       unset($this->connections['slaves'][$slaveFlag]);
                   }
               }
               $this->createSlave($slaves);
          });
     }

     protected function stringToArr($str, $flag1 = ',', $flag2 = '=')
     {
         // "ip=192.160.1.130,port=6379,state=online,offset=72574,lag=0"
         $arr = explode($flag1, $str);
         $ret = [];
         // $key ip
         // $value 192.160.1.130
         foreach ($arr as $key => $value) {
             $arr2 = explode($flag2, $value);
             $ret[$arr2[0]] = $arr2[1];
         }
         return $ret;
     }

     // --------------创建主从连接--------------------
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
      */
     private function createSlave($slaves)
     {
         // var_dump($slaves);
         // 这个是用于做负载的时候选择从节点对象
         foreach ($slaves as $key => $slave) {
             $this->connections['slaves'][$this->redisFlag($slave['host'], $slave['port'])] = $this->getRedis($slave['host'], $slave['port']);
         }
         // 记录从节点的下标
         $this->connSlaveIndexs = array_keys($this->connections['slaves']);
     }

     private function redisFlag($host, $port)
     {
         return $host.":".$port;
     }

     public function getRedis($host, $port)
     {
         $redis = new \Redis();
         $redis->pconnect($host, $port);
         return $redis;
     }

     public function getConnSlaveIndexs()
     {
         return $this->connSlaveIndexs;
     }

     // --------------获取主从连接方法--------------------

     public function getMaster()
     {
         return $this->connections['master'];
     }
     public function getSlaves()
     {
         return $this->connections['slaves'];
     }

     public function oneSlave()
     {
         $indexs = $this->connSlaveIndexs;
         $i = mt_rand(0, count($indexs) - 1);
         return $this->connections['slaves'][$indexs[$i]];
         // $slaves = $this->getSlaves();
         // // 对于所有从节点  负载均衡算法
         // $i = mt_rand(0, count($slaves) - 1);
         // return $slaves[$i];
     }

     // --------------执行命令方法--------------------

     public function runCall($command, $params = [])
     {
         try {
             if ($this->config['is_ms']) {
                 // 获取操作的对象（是主还是从）

                 $redis = $this->getRedisCall($command);
                 // var_dump($redis);
                 return $redis->{$command}(...$params);
             }
         } catch (\Exception $e) {}
     }
     /**
      * 判断操作类型
      * @param  [type]  $command [description]
      * @return boolean          [description]
      */
     protected function getRedisCall($command)
     {
         if (in_array($command, $this->call['write'])) {
             return $this->getMaster();
         } else if (in_array($command, $this->call['read'])){
             return $this->oneSlave();
         } else {
             throw new \Exception("不支持");
         }
     }
 }
