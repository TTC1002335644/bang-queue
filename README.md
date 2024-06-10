# bang-queue for ThinkPHP8

## 描述

> 基于 workerman + thinkphp8 + redis，实现的一个任务队列

## 安装

> composer require bang/queue

## 配置

> 配置文件位于 `config/bang_queue.php`

### 配置说明
> 仅支持Redis驱动，没有Redis请自行安装

```bash
[
    'default' => 'default',//默认连接名
    'count'       => 8,//进程数
    'isDynamic'  => true,//是否进行动态监听。慎用，有一定的性能消耗。而且redis请做好持久化
    'processPort'       => 9500,//isDynamic为true。需要用一个端口来监听进程
    'loggerChannel' => 'file',//记录日志的通道名称。具体请看thinkphp8手册的日志处理
    //通用配置
    'config' => [
        'path' => 'workerman_log/queue',//存储日志的目录
        'pidFileName' => 'workerman.pid',//日志文件
        'stdoutFile' => 'stdout.pid',//stdoutFile文件
        'logFile' => 'log.log',//日志文件
    ],
    'options' => [
        'default' => [
            'host'   => '127.0.0.1',
            'port'       => 6379,
            'password'       => '',
            'select'   => 2,
            'prefix'   => '',
            'max_attempts'  => 5, // 消费失败后，重试次数
            'retry_seconds' => 10, // 重试间隔，单位秒
        ]
    ],
    //这里可以定义 队列名 与 消费队列 的映射，优先级最高，动态监听也无法去修改
    'listenList' => [
        //示例格式为
        //'queue1' => \app\queue\test\Consume::class
    ]

];
```

### 配置解释
> `default` ， 对应 `options` 下的连接配置

> `count`，开启的消费者的进程数，按照自己服务器配置来衡量

> `isDynamic` ，是否需要动态监听。懒人必备，这样不必去配置`listenList`了。有一定的性能消耗

> `processPort` ，开启动态监听时，需要启动一个端口来监听。请确保你设置的端口没被占用。默认是 `9500`

> `loggerChannel` ，日志通道。具体请看thinkphp8手册的日志处理。留空则获取它默认的配置通道

> `config` ，是workerman的一个日志、pid、stdoutFile的一些配置。一般来说不需要做过多的修改
* `pidFileName` ，pid文件名
* `path` ，这些配置存储的目录。相对于项目的根目录
* `stdoutFile` ，stdoutFile文件名
* `logFile` ，日志文件名

> `options`，连接列表，里面可以配置各种连接，不过这些必须都是Redis的配置
* `host` ，Redis链接
* `port` ，端口
* `password` ，密码。如没设置，留空即可
* `select` ，select 的 database。可以有效避免和别的业务冲突
* `prefix` ，前缀，一般留空即可
* `max_attempts` ，消费失败后，重试次数
* `retry_seconds` ，重试间隔，单位秒。


### 重试说明
```bash
  重试次数由 `max_attempts` 控制，重试间隔由 `retry_seconds` 和 `max_attempts` 共同控制。
  比如`max_attempts`为5，`retry_seconds`为10。
  第1次重试间隔为1*10秒；
  第2次重试时间间隔为2*10秒；
  第3次重试时间间隔为3*10秒；
  .
  .
  .
  以此类推
```


## 创建任务类
> 任务类 必须 `implements` `bang\queue\Consumer`
> 
> 以下是一个例子


### 下面写两个例子

```php
<?php
declare (strict_types=1);

namespace app\queue\test;

use bang\queue\Consumer;

class Consume implements Consumer
{

    /**
     * 队列名称
     * @var string
     */
    public static string $queue = 'queue1';

    /**
     * 使用的链接
     * @var string
     */
    public static string $connection = 'default';

    /**
     * 消费逻辑
     * @param $data
     * @return void
     * @throws \Throwable
     */
    public function consume($data)
    {
        throw new \Error('抛出异常就会重新执行队列，直到重试次数');
    }
    
    /**
     * 失败回调逻辑
     * @param $data
     * @return void
     * @throws \Throwable
     */
    public function onConsumeFailure(\Throwable $e, $package)
    {
        //这里是每一次执行失败时会调用，你可以写任何的邮件、短信等等通知
        //$package 包含了 attempts 已经重试的次数。data，生产者发布的数据。error，是错误信息。
    }

}

```



## 发布任务
> 第一个，`bang\queue\Queue::send($queueName , $data, $delay = 0)`
> 
> 第二个，`bang\queue\Queue::sendDynamic($class , $data, $delay = 0)`

### 第一个命令说明
> `$queueName`，队列名，优先获取 配置中的 `listenList`的映射
> `$data`，需要传输到消费者中的参数
> `$delay`，意思在n秒后执行，传`0`或不传，就是立即执行

### 第一个命令说明
> `$queueName`，具体的消费者类路径，例子：可以传 `app\queue\test\Consume` 或者 `app\queue\test\Consume::class`。用这种方法，消费者类最好定义好`static $queue`的队列名
> `$data`，需要传输到消费者中的参数
> `$delay`，意思在n秒后执行，传`0`或不传，就是立即执行


## 监听任务并执行

```bash

&> php think bang_queue:work start
```

> 如果需要进程常驻，则可以运行
 &> php think bang_queue:work start -d true

两种，具体的可选参数可以输入命令加 `--help` 查看
