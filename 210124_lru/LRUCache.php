<?php
require "HashList.php";

class LRUCache {

    //
    private $capacity;
    private $list;

    /**
     * @param Integer $capacity
     */
    function __construct($capacity) {
        $this->capacity = $capacity;
        $this->list = new HashList();
    }

    /**
     * @param Integer $key
     * @return Integer
     */
    function get($key) {
        if($key<0) return -1;
        return $this->list->get($key);
    }

    /**
     * @param Integer $key
     * @param Integer $value
     */
    function put($key, $value) {
        $size=$this->list->size;
        $isHas=$this->list->checkIndex($key);
        if($isHas || $size+1 > $this->capacity){
            $this->list->removeNode($key);
        }
        $this->list->addAsHead($key,$value);
    }
}
