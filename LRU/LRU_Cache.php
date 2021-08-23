<?php
/**
 * LRU是最近最少使用页面置换算法(Least Recently Used),也就是首先淘汰最长时间未被使用的页面
 */
class LRU_Cache
{

    private $array_lru = array();
    private $max_size = 0;

    function __construct($size)
    {
        // 缓存最大存储
        $this->max_size = $size;
    }

    public function set_value($key, $value)
    {
        // 如果存在，则向队尾移动，先删除，后追加
        // array_key_exists() 函数检查某个数组中是否存在指定的键名，如果键名存在则返回true，如果键名不存在则返回false。
        if (array_key_exists($key, $this->array_lru)) {
            // unset() 销毁指定的变量。
            unset($this->array_lru[$key]);
        }
        // 长度检查，超长则删除首元素
        if (count($this->array_lru) > $this->max_size) {
            // array_shift() 函数删除数组中第一个元素，并返回被删除元素的值。
            array_shift($this->array_lru);
        }
        // 队尾追加元素
        $this->array_lru[$key] = $value;
    }

    public function get_value($key)
    {
        $ret_value = false;

        if (array_key_exists($key, $this->array_lru)) {
            $ret_value = $this->array_lru[$key];
            // 移动到队尾
            unset($this->array_lru[$key]);
            $this->array_lru[$key] = $ret_value;
        }

        return $ret_value;
    }

    public function vardump_cache()
    {
        echo "<br>";
        var_dump($this->array_lru);
    }
}

$cache = new LRU_Cache(5);                          // 指定了最大空间 6
$cache->set_value("01", "01");
$cache->set_value("02", "02");
$cache->set_value("03", "03");
$cache->set_value("04", "04");
$cache->set_value("05", "05");
$cache->vardump_cache();
echo "<br>";

$cache->set_value("06", "06");
$cache->vardump_cache();
echo "<br>";

$cache->set_value("03", "03");
$cache->vardump_cache();
echo "<br>";

$cache->set_value("07", "07");
$cache->vardump_cache();
echo "<br>";

$cache->set_value("01", "01");
$cache->vardump_cache();
echo "<br>";

$cache->get_value("04");
$cache->vardump_cache();
echo "<br>";

$cache->get_value("05");
$cache->vardump_cache();
echo "<br>";

$cache->get_value("10");
$cache->vardump_cache();
echo "<br>";