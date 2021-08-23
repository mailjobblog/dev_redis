<?php
$config = [
    'host' => '',
    'port' => '',
    // 'is_ms' => true,
    // isMs, isSentinel,isCluster, isNormal 正常
    'initType' => 'isCluster',
    'master' => [
        'host' => '192.160.1.150',
        'port' => 6379,
    ],
    'slaves' => [
        'slave1' => [
            'host' => '192.160.1.140',
            'port' => 6379,
        ],
        'slave2' => [
            'host' => '192.160.1.130',
            'port' => 6379,
        ],
    ],
    'sentinels' => [
        'master_name' => 'mymaster', // 指定哨兵监控主节点的别名
        'addr' => [ // 配置的是哨兵的ip port
            [
                'host' => '192.160.1.179',
                'port' => 26379
            ],[
                'host' => '192.160.1.180',
                'port' => 26379
            ],[
                'host' => '192.160.1.181',
                'port' => 26379
            ]
        ]
    ],
    'cluster' => [
        'nodes' => [
            [
              'host' => '192.160.1.200',
              'port' => 6379,
            ],[
              'host' => '192.160.1.201',
              'port' => 6379,
            ],[
              'host' => '192.160.1.202',
              'port' => 6379,
            ],[
              'host' => '192.160.1.203',
              'port' => 6379,
            ],[
              'host' => '192.160.1.204',
              'port' => 6379,
            ],[
              'host' => '192.160.1.205',
              'port' => 6379,
            ],
        ]
    ],
    'runCall' => [
        'isSentinel' => 'getRedisCall',
        'isMs' => 'getRedisCall',
        'isCluster' => 'clusterCommand'
    ]
];
