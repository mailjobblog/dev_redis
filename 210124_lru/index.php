<?php
require "LRUCache.php";

$obj = new LRUCache(5);

$obj->put("hello", "world");

$ret_1 = $obj->get("hello");

