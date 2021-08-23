<?php


$redis = new \Redis();
$redis->connent('127.0.0.1','6379');

print_r($redis->get('a'));