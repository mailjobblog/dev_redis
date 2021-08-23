<?php
require __DIR__.'/../../vendor/autoload.php';
require_once "./config.php";

use Shineyork\Redis\MasterAndSlave\RedisMs;
// 在swoole事件中 echo 和 var_dump是输出在 控制台 不是浏览器
$http = new Swoole\Http\Server("0.0.0.0", 9501);

// 设置swoole进程个数
$http->set([
    'worker_num' => 1
]);
// 在创建的时候执行  ； 进程创建的时候触发时候
// 理解为一个构造函数，初始化
$http->on('workerStart', function ($server, $worker_id) use($config){
    global $redisMS;
    $redisMS = new RedisMS($config);
});

// 通过浏览器访问 http://本机ip ：9501会执行的代码
$http->on('request', function ($request, $response) {
    global $redisMS;
    $ret = 'p';
    if ($request->get['type'] == 1) {
        $ret = $redisMS->runCall($request->get['method'], explode(',', $request->get['params']));
    } else {
        // 读
        $ret = $redisMS->runCall('get', [$request->get['params']]);
    }

    $response->end($ret);
});

$http->start();
