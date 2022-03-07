<?php
/**
 * 链表数据结构维护类
 *
 * Class HashList
 */
class HashList{

    // 头节点
    public $head;
    // 尾节点
    public $tail;
    // 节点数量
    public $size;
    // 节点内容
    public $buckets = [];

    public function __construct(Node $head = null, Node $tail = null){
        $this->head = $head;
        $this->tail = $tail;
        $this->size = 0;
    }

    /**
     * 判断指针是否存在
     *
     * @param $key
     * @return bool
     */
    public function checkIndex($key){
        return isset($this->buckets[$key]);
    }

    /**
     * 获取指针的值
     *
     * @param $key
     * @return int
     */
    public function get($key){
        $res = $this->buckets[$key];
        if(!$res) return -1;
        $this->moveToHead($res);
        return $res->val;
    }

    /**
     * 加入新的节点
     *
     * @param $key
     * @param $val
     */
    public function addAsHead($key,$val)
    {
        $node= new Node($val);

        //
        if($this->tail == null && $this->head != null){
            $this->tail = $this->head;
            $this->tail->next = null;
            $this->tail->pre = $node;
        }

        $node->pre = null; // 将新节点头指针置为null
        $node->next = $this->head; // 将新节点的尾指针指向原来的头节点
        $this->head->pre = $node; // 将原来头节点的头节点指向新节点
        $this->head = $node; // 将当前 hashList 的头节点定义为新节点
        $node->key = $key; // 定义新节点的key
        $this->buckets[$key] = $node; // 存储节点数据
        $this->size++; // 节点计数器+1
    }

    /**
     * 移除节点
     * 已存在的键值对或者删除最近最少使用原则
     *
     * @param $key
     */
    public function removeNode($key)
    {
        $current=$this->head;
        for($i=1;$i<$this->size;$i++){
            if($current->key==$key) break;
            $current=$current->next;
        }
        unset($this->buckets[$current->key]);
        //调整指针
        if($current->pre==null){
            $current->next->pre=null;
            $this->head=$current->next;
        }else if($current->next ==null){
            $current->pre->next=null;
            $current=$current->pre;
            $this->tail=$current;
        }else{
            $current->pre->next=$current->next;
            $current->next->pre=$current->pre;
            $current=null;
        }
        $this->size--;

    }

    /**
     * 把对应的节点应到链表头部
     * 最近get或者刚刚put进去的node节点
     *
     * @param Node $node
     */
    public function moveToHead(Node $node)
    {
        if($node === $this->head) return ;
        //调整前后指针指向
        $node->pre->next=$node->next;
        $node->next->pre=$node->pre;
        $node->next=$this->head;
        $this->head->pre=$node;
        $this->head=$node;
        $node->pre=null;
    }
}

/**
 * Class Node
 */
class Node{

    // 数据的key
    public $key;
    // 数据的value
    public $val;
    // 上一个节点的指针
    public $pre;
    // 下一个节点的指针
    public $next;

    public function __construct($val) {
        $this->val = $val;
    }
}
