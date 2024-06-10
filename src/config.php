<?php

return [
    'default' => 'default',//默认连接名
    'count'       => 8,//进程数
    'isDynamic'  => true,//是否进行动态监听。慎用，有一定的性能消耗。而且redis请做好持久化
    'processPort'       => 9500,//isDynamic为true。需要用一个端口来监听进程
    'loggerChannel' => 'file',//记录日志的通道名称。具体请看thinkphp8手册的日志处理
    //通用配置
    'config' => [
        'path' => 'workerman_log/queue',//存储日志的目录
        'pidFileName' => 'workerman.pid',//pid文件
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