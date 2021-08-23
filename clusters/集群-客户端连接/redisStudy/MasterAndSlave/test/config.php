<?php
$config = [
    'host' => '',
    'port' => '',
    'is_ms' => true,
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
    ]
];
