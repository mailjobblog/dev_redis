# Demo：docker-compose 实现redis集群

# 相关链接

> redis集群演示服务分布图：https://www.kdocs.cn/view/l/sfN4qFXA2SyN

## docker-compose 文件说明

> 该演示示例，使用 `redis6.0.10` 版本进行演示

### 文件中容器对应关系

|  容器名称   | IP  | 客户端连接端口映射 | 集群端口映射  | 预想角色  |
|  ----  | ----  |  ----  | ----  | ----  |
| redis-c1  | 172.31.0.11 | 6301->6379 | 16301->16379  | master |
| redis-c2  | 172.31.0.12 | 6302->6379 | 16302->16379  | master |
| redis-c3  | 172.31.0.13 | 6303->6379 | 16303->16379  | master |
| redis-c4  | 172.31.0.14 | 6304->6379 | 16304->16379  | slave |
| redis-c5  | 172.31.0.15 | 6305->6379 | 16305->16379  | slave |
| redis-c6  | 172.31.0.16 | 6306->6379 | 16306->16379  | slave |
| redis-c7  | 172.31.0.17 | 6307->6379 | 16307->16379  | master （演示集群伸缩中使用） |
| redis-c8  | 172.31.0.18 | 6308->6379 | 16308->16379  | slave （演示集群伸缩中使用） |

### 初始服务分布如下

![](http://img.github.mailjob.net/jefferyjob.github.io/20210208230110.png)

### 集群主要配置文件说明

```
# 端口
port 6379
# 是否开启集群
cluster-enabled yes
# 集群超时时间
cluster-node-timeout 5000
# 更新操作后进行日志记录
appendonly yes
# 集群配置文件
cluster-config-file "/redis/log/nodes.conf"
# 是否开启外部连接
protected-mode no
# redis守护进程
daemonize no
# 本地数据库存放目录
dir "/redis/data"
# redis日志文件
logfile "/redis/log/redis.log"
```

```
daemonize 设置yes或者no区别（默认：no）

daemonize:yes:redis采用的是单进程多线程的模式。当redis.conf中选项daemonize设置成yes时，代表开启守护进程模式。在该模式下，redis会在后台运行，并将进程pid号写入至redis.conf选项pidfile设置的文件中，此时redis将一直运行，除非手动kill该进程。
daemonize:no: 当daemonize选项设置成no时，当前界面将进入redis的命令行界面，exit强制退出或者关闭连接工具(putty,xshell等)都会导致redis进程退出。
```

# 开始搭建集群

### 在宿主机 /data 上传 j_cluster

> 如果上传到了其他目录需要更改 yml 里面的数据卷映射条件

### 启动项目

```
# 进入到项目目录
cd /data/j_cluster

# 启动项目
docker-compose up -d
```

![](http://img.github.mailjob.net/jefferyjob.github.io/20210208132002.png)

### 查看一下各个节点的ip

```
docker container inspect redis-c1 redis-c2 redis-c3 redis-c4 redis-c5 redis-c6 | grep IPv4Address
```

> 和我在 yam 中定义的ip一致

![](http://img.github.mailjob.net/jefferyjob.github.io/20210208134455.png)

# 开始搭建集群

### 这里以进入 redis-c1 为例

```
docker exec -it redis-c1 /bin/bash
```

### 浏览一下redis的集群命令

> 以下命令只有 redis5 以后才有,redis5 以后redis发布了集群搭建命令  
> redis5 以前如果你要搭建的话，可以采用 Ruby 脚本  

```
# 查看集群帮助文档
root@e4d19717bbed:/redis# redis-cli --cluster help

# 集群相关命令如下
Cluster Manager Commands:
  create         host1:port1 ... hostN:portN   #创建集群
                 --cluster-replicas <arg>      #从节点个数
  check          host:port                     #检查集群
                 --cluster-search-multiple-owners #检查是否有槽同时被分配给了多个节点
  info           host:port                     #查看集群状态
  fix            host:port                     #修复集群
                 --cluster-search-multiple-owners #修复槽的重复分配问题
  reshard        host:port                     #指定集群的任意一节点进行迁移slot，重新分slots
                 --cluster-from <arg>          #需要从哪些源节点上迁移slot，可从多个源节点完成迁移，以逗号隔开，传递的是节点的node id，还可以直接传递--from all，这样源节点就是集群的所有节点，不传递该参数的话，则会在迁移过程中提示用户输入
                 --cluster-to <arg>            #slot需要迁移的目的节点的node id，目的节点只能填写一个，不传递该参数的话，则会在迁移过程中提示用户输入
                 --cluster-slots <arg>         #需要迁移的slot数量，不传递该参数的话，则会在迁移过程中提示用户输入。
                 --cluster-yes                 #指定迁移时的确认输入
                 --cluster-timeout <arg>       #设置migrate命令的超时时间
                 --cluster-pipeline <arg>      #定义cluster getkeysinslot命令一次取出的key数量，不传的话使用默认值为10
                 --cluster-replace             #是否直接replace到目标节点
  rebalance      host:port                                      #指定集群的任意一节点进行平衡集群节点slot数量 
                 --cluster-weight <node1=w1...nodeN=wN>         #指定集群节点的权重
                 --cluster-use-empty-masters                    #设置可以让没有分配slot的主节点参与，默认不允许
                 --cluster-timeout <arg>                        #设置migrate命令的超时时间
                 --cluster-simulate                             #模拟rebalance操作，不会真正执行迁移操作
                 --cluster-pipeline <arg>                       #定义cluster getkeysinslot命令一次取出的key数量，默认值为10
                 --cluster-threshold <arg>                      #迁移的slot阈值超过threshold，执行rebalance操作
                 --cluster-replace                              #是否直接replace到目标节点
  add-node       new_host:new_port existing_host:existing_port  #添加节点，把新节点加入到指定的集群，默认添加主节点
                 --cluster-slave                                #新节点作为从节点，默认随机一个主节点
                 --cluster-master-id <arg>                      #给新节点指定主节点
  del-node       host:port node_id                              #删除给定的一个节点，成功后关闭该节点服务
  call           host:port command arg arg .. arg               #在集群的所有节点执行相关命令
  set-timeout    host:port milliseconds                         #设置cluster-node-timeout
  import         host:port                                      #将外部redis数据导入集群
                 --cluster-from <arg>                           #将指定实例的数据导入到集群
                 --cluster-copy                                 #migrate时指定copy
                 --cluster-replace                              #migrate时指定replace
  help           

For check, fix, reshard, del-node, set-timeout you can specify the host and port of any working node in the cluster.

```

> 注意：Redis Cluster最低要求是3个主节点

### 创建集群主从节点

```
redis-cli --cluster create 172.31.0.11:6379  172.31.0.12:6379  172.31.0.13:6379 172.31.0.14:6379 172.31.0.15:6379 172.31.0.16:6379 --cluster-replicas 1
```

```
>>> Performing hash slots allocation on 6 nodes...
Master[0] -> Slots 0 - 5460
Master[1] -> Slots 5461 - 10922
Master[2] -> Slots 10923 - 16383
Adding replica 172.31.0.15:6379 to 172.31.0.11:6379
Adding replica 172.31.0.16:6379 to 172.31.0.12:6379
Adding replica 172.31.0.14:6379 to 172.31.0.13:6379
M: 50fa88c4a01f968df6ab7e8bd02e1bb51c85f13f 172.31.0.11:6379
   slots:[0-5460] (5461 slots) master
M: 04a2118b3f7b7521a55cf77171f1c50fe1a80f4d 172.31.0.12:6379
   slots:[5461-10922] (5462 slots) master
M: b83a282329830e2ea686889cb8aa9eafa3441b8f 172.31.0.13:6379
   slots:[10923-16383] (5461 slots) master
S: 9b0a2284c341efa7055dd2046aec2e1c43ee6f9b 172.31.0.14:6379
   replicates b83a282329830e2ea686889cb8aa9eafa3441b8f
S: 09aca472595a229e7ceda2792aed98f88d757d45 172.31.0.15:6379
   replicates 50fa88c4a01f968df6ab7e8bd02e1bb51c85f13f
S: 2ce485e6a5bc5a3f300347c123ce911e605bf164 172.31.0.16:6379
   replicates 04a2118b3f7b7521a55cf77171f1c50fe1a80f4d
Can I set the above configuration? (type 'yes' to accept): 
```

> --cluster create : 表示创建集群  
> --cluster-replicas 0 : 表示只创建n个主节点，不创建从节点  
> --cluster-replicas 1 : 表示为集群中的每个主节点创建一个从节点（例：master[172.31.0.11:6379] -> slave[172.31.0.14:6379]）  

![](http://img.github.mailjob.net/jefferyjob.github.io/20210208174303.png)

### 查看集群

```
# 查看节点主从关系
127.0.0.1:6379> cluster nodes
b83a282329830e2ea686889cb8aa9eafa3441b8f 172.31.0.13:6379@16379 master - 0 1612778025467 3 connected 10923-16383
04a2118b3f7b7521a55cf77171f1c50fe1a80f4d 172.31.0.12:6379@16379 master - 0 1612778025000 2 connected 5461-10922
50fa88c4a01f968df6ab7e8bd02e1bb51c85f13f 172.31.0.11:6379@16379 myself,master - 0 1612778024000 1 connected 0-5460
9b0a2284c341efa7055dd2046aec2e1c43ee6f9b 172.31.0.14:6379@16379 slave b83a282329830e2ea686889cb8aa9eafa3441b8f 0 1612778024565 3 connected
2ce485e6a5bc5a3f300347c123ce911e605bf164 172.31.0.16:6379@16379 slave 04a2118b3f7b7521a55cf77171f1c50fe1a80f4d 0 1612778024465 2 connected
09aca472595a229e7ceda2792aed98f88d757d45 172.31.0.15:6379@16379 slave 50fa88c4a01f968df6ab7e8bd02e1bb51c85f13f 0 1612778024000 1 connected

# 列出槽和节点信息
127.0.0.1:6379> cluster slots
1) 1) (integer) 10923
   2) (integer) 16383
   3) 1) "172.31.0.13"
      2) (integer) 6379
      3) "b83a282329830e2ea686889cb8aa9eafa3441b8f"
   4) 1) "172.31.0.14"
      2) (integer) 6379
      3) "9b0a2284c341efa7055dd2046aec2e1c43ee6f9b"
2) 1) (integer) 5461
   2) (integer) 10922
   3) 1) "172.31.0.12"
      2) (integer) 6379
      3) "04a2118b3f7b7521a55cf77171f1c50fe1a80f4d"
   4) 1) "172.31.0.16"
      2) (integer) 6379
      3) "2ce485e6a5bc5a3f300347c123ce911e605bf164"
3) 1) (integer) 0
   2) (integer) 5460
   3) 1) "172.31.0.11"
      2) (integer) 6379
      3) "50fa88c4a01f968df6ab7e8bd02e1bb51c85f13f"
   4) 1) "172.31.0.15"
      2) (integer) 6379
      3) "09aca472595a229e7ceda2792aed98f88d757d45"

```

### 数据存储

```
# 直接存储提示槽信息不对
127.0.0.1:6379> set name libin
(error) MOVED 5798 172.31.0.12:6379

# 客户端连接加入 -c 数据可以直接被重定向到槽服务器
root@00bfb4f9402a:/redis# redis-cli -c
127.0.0.1:6379> set name libin
-> Redirected to slot [5798] located at 172.31.0.12:6379
OK

# 存储多个key的时候，由于不同的槽服务器，报错问题
172.31.0.12:6379> mset k1 v1 k2 v2
(error) CROSSSLOT Keys in request don't hash to the same slot

# 加入一个 tag 即可解决
172.31.0.12:6379> mset {r}k1 v1 {r}k2 v2
OK
```

# 集群伸缩

> 1、准备新的redis节点服务器  
> 2、加入到集群中  
> 3、分配数据槽和迁移数据  

### 加入一个新的 master 节点

```
redis-cli -h 172.31.0.17 --cluster add-node 172.31.0.17:6379 172.31.0.11:6379
```

- 这里的新加入的master节点是 172.31.0.17
- 172.31.0.11 代表的事现在存在的集群中的任意一个 master 节点

![](http://img.github.mailjob.net/jefferyjob.github.io/20210208230200.png)

### 为新加入的 master 节点添加一个 slave 节点

```
redis-cli -h 172.31.0.17 --cluster add-node 172.31.0.18:6379 172.31.0.17:6379 --cluster-slave
```

- 172.31.0.17 代表上面行加入的 master 节点
- 172.31.0.18 代表为 master（172.31.0.17）加入的从节点
- --cluster-slave 代表是 slave（从节点）的身份

![](http://img.github.mailjob.net/jefferyjob.github.io/20210208230233.png)

### 删除 slave 节点

```
redis-cli -h 172.31.0.18 --cluster del-node 172.31.0.18:6379 3a12f6b4ed5f26b83525681e73ee23750bcbcfbf
```

- 3a12f6b4ed5f26b83525681e73ee23750bcbcfbf 是通过 `cluster nodes` 命令得到的节点ID
- 如果上面有数据的话，无法删除，需要先迁移数据

### 为节点分配槽

```
# 指定分配
redis-cli -h 172.31.0.11 --cluster reshard 172.31.0.11:6379

# 平均分配
redis-cli -h 172.31.0.11 --cluster rebalance 172.31.0.11:6379
```

- 172.31.0.11 这个只要是随便一个 master 节点都可以操作集群

### 分配槽演示

```
# 可以先看到节点信息

>>> Performing Cluster Check (using node 172.31.0.11:6379)
M: 9af405be84c1d448988117f88f78fa588d49a196 172.31.0.11:6379
   slots:[0-5460] (5461 slots) master
   1 additional replica(s)
S: 3a12f6b4ed5f26b83525681e73ee23750bcbcfbf 172.31.0.18:6379
   slots: (0 slots) slave
   replicates 34a33c9df9d909e69e5cad8965d905b72959c677
S: 7c35cf01519c5747ca262769ed47dbbe44eeb830 172.31.0.14:6379
   slots: (0 slots) slave
   replicates 780d17c7c9ca86f68b6119762f0958f00093702a
M: 780d17c7c9ca86f68b6119762f0958f00093702a 172.31.0.13:6379
   slots:[10923-16383] (5461 slots) master
   1 additional replica(s)
M: 34a33c9df9d909e69e5cad8965d905b72959c677 172.31.0.17:6379
   slots: (0 slots) master
   1 additional replica(s)
S: a4956784b0221598c23d18fbcad844e18eefab63 172.31.0.15:6379
   slots: (0 slots) slave
   replicates 9af405be84c1d448988117f88f78fa588d49a196
M: ddf46bbdf6794d12debe824ce4e30ea96cd70212 172.31.0.12:6379
   slots:[5461-10922] (5462 slots) master
   1 additional replica(s)
S: a2820f32aa18e7ec2d880aedc8d91c9db840dcd9 172.31.0.16:6379
   slots: (0 slots) slave
   replicates ddf46bbdf6794d12debe824ce4e30ea96cd70212
[OK] All nodes agree about slots configuration.
>>> Check for open slots...
>>> Check slots coverage...
[OK] All 16384 slots covered.
```

- 可以看到这里新加入的master `172.31.0.17` 没有数据槽

```
# 询问要迁移的槽的数量（我在这里迁移了500个）
How many slots do you want to move (from 1 to 16384)? 500
# 询问被迁移的槽的ID（我这里迁移的是：172.31.0.11）
What is the receiving node ID? 34a33c9df9d909e69e5cad8965d905b72959c677
# 请输入所有源节点ID
Please enter all the source node IDs.
   # 键入“all”将所有节点用作哈希槽的源节点
  Type 'all' to use all the nodes as source nodes for the hash slots.
   # 输入所有源节点ID后键入“done”。
  Type 'done' once you entered all the source nodes IDs.
# 输入源的ID后，进行迁移
Source node #1: 9af405be84c1d448988117f88f78fa588d49a196
Source node #2: done

```

- 用这个方法，也可以把某一个源的槽都迁移后。那么该槽就可以进行删除了

# 搭建问题

#### 创建集群主从节点报错

> [ERR] Node 172.31.0.11:6379 is not configured as a cluster node
> 查看配置文件的配置问题