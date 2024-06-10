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

use think\facade\Config;

/**
 * 推送信息
 * @method static bool send(string $queue, mixed $data, int $delay=0)
 * @method static bool sendDynamic(string $class, mixed $data, int $delay=0)
 */
class Queue
{
    /**
     * @var RedisConnection[]
     */
    protected static $_connections = [];

    /**
     * @param string $name
     * @return RedisConnection
     */
    public static function connection($name = 'default')
    {
        if (!isset(static::$_connections[$name])) {
            $configs = Config::get('bang_queue.options', []);
            if (!isset($configs[$name])) {
                throw new \RuntimeException("RedisQueue connection $name not found");
            }
            $config = $configs[$name];
            static::$_connections[$name] = static::connect($config);
        }
        return static::$_connections[$name];
    }

    /**
     * @param array $config
     * @return RedisConnection
     */
    protected static function connect(array $config)
    {
        $redis = new RedisConnection();
        $redis->connectWithConfig($config);
        return $redis;
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return static::connection('default')->{$name}(... $arguments);
    }

}