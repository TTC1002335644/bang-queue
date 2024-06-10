<?php
/**
 * This file is part of thinkphp.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    Bang<1002335644@qq.com>
 */

declare (strict_types=1);


namespace bang\queue;

use bang\queue\tool\Reflection;
use think\facade\Config;
use Workerman\Timer;
use Workerman\Worker;

class RedisConnection
{

    /** @var \Redis */
    protected $handler;

    /**
     * @var array
     */
    protected array $config = [];

    /**
     * @var array
     */
    protected array $optionsInit = [
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => '',
        'select' => 0,
        'expire' => 0,
        'prefix' => '',
        'timeout' => 2,
        'ping' => 55,
        'serialize' => [],
        'max_attempts' => 5, // 消费失败后，重试次数
        'retry_seconds' => 5, // 重试间隔，单位秒
    ];


    /**
     * 连接Redis
     * @param array $config
     * @return \Redis
     */
    public function connectWithConfig(array $config = [])
    {
        static $timer;
        if (!empty($config)) {
            $this->config = array_merge($this->optionsInit, $config);
        }

        $this->handler = new \Redis();
        $result = $this->handler->connect($this->config['host'], (int)$this->config['port'], $this->config['timeout'] ?? 2);

        if (false === $result) {
            throw new \RuntimeException("Redis connect {$this->config['host']}:{$this->config['port']} fail.");
        }

        if (!empty($this->config['password'])) {
            $this->handler->auth($this->config['password']);
        }

        if (!empty($this->config['prefix'])) {
            $this->handler->setOption(\Redis::OPT_PREFIX, $this->config['prefix']);
        }

        if (0 != $this->config['select']) {
            $this->handler->select($this->config['select']);
        }

        //最后加一个定时器，来维持redis的生命周期
        if (Worker::getAllWorkers() && !$timer) {
            $timer = Timer::add($this->config['ping'] ?? 55, function () {
                $this->execCommand('ping');
            });
        }

        return $this->handler;
    }

    /**
     * 运行Redis命令
     * @param string $command
     * @param ...$args
     * @return mixed
     * @throws \Throwable
     */
    protected function execCommand(string $command, ...$args)
    {
        try {
            return $this->handler->{$command}(...$args);
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            if ($msg === 'connection lost' || strpos($msg, 'went away')) {
                $this->connectWithConfig();
                return $this->handler->{$command}(...$args);
            }
            throw $e;
        }
    }


    /**
     * 推送任务
     * @param string $queue
     * @param $data
     * @param int $delay
     * @return mixed
     * @throws \Throwable
     */
    public function send(string $queue, $data, $delay = 0)
    {
        $queueWaiting = '{redis-queue}-waiting';
        $queueDelay = '{redis-queue}-delayed';

        //先动态生产一个监听队列
        $config = Config::get('bang_queue', []);

        $now = time();
        $packageStr = json_encode([
            'id' => mt_rand(),
            'time' => $now,
            'delay' => $delay,
            'attempts' => 0,
            'queue' => $queue,
            'data' => $data,
        ]);
        if ($delay) {
            return $this->execCommand('zAdd', $queueDelay, $now + $delay, $packageStr);
        }
        return $this->execCommand('lPush', $queueWaiting . $queue, $packageStr);
    }

    /**
     * 动态监听-并推送任务
     * @param string $class
     * @param $data
     * @param int $delay
     * @return false|mixed
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function sendDynamic(string $class, $data, $delay = 0)
    {
        //先动态生产一个监听队列
        $config = Config::get('bang_queue', []);
        $isDynamic = $config['isDynamic'] ?? false;
        $queue = false;
        if($isDynamic === true && !empty($config['processPort'])){
            $queue = $this->dynamicCreateSubscribe($class, $config['processPort']);
        }

        if($queue === false){
            return false;
        }

        return $this->send($queue, $data, $delay = 0);
    }


    /**
     * 动态监听
     * @param string $class
     * @param int|string $port
     * @return false|mixed
     * @throws \ReflectionException
     */
    protected function dynamicCreateSubscribe(string $class, int|string $port)
    {
        //动态去获取静态属性--队列名称
        $queue = Reflection::getStaticProperties($class,'queue');
        if(empty($queue)){
            $queue = md5($class);
        }

        $client = stream_socket_client('tcp://127.0.0.1:'.$port, $errno, $errstr);
        fwrite($client, json_encode([
            'class' => $class,
            'queue' => $queue,
        ]));
        fclose($client);

        return $queue;
    }

}