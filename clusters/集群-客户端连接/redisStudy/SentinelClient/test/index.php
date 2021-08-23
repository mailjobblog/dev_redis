<?php
require __DIR__.'/../../vendor/autoload.php';


use Shineyork\Redis\SentinelClient\RedisMS;
$http = new Swoole\Http\Server("0.0.0.0", 9501);

$http->set([
    'worker_num' => 1
]);
$http->on('workerStart', function ($server, $worker_id) use($config){
    global $redisMS;
    require_once "./config.php";
    $redisMS = new RedisMS($config);
});

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
