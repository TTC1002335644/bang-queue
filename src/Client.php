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

use think\facade\Log;
use think\facade\Config;
use Workerman\RedisQueue\Client as RedisClient;

class Client extends RedisClient
{

    /**
     * @var Client[]
     */
    protected static $_connections = null;

    /**
     * @param string $name
     * @return RedisClient
     */
    public static function connection($name = 'default', ?string $logger = null)
    {
        if (!isset(static::$_connections[$name])) {
            $config = Config::get('bang_queue.options', []);
            if (!isset($config[$name])) {
                throw new \RuntimeException("RedisQueue connection $name not found");
            }
            $options = $config[$name];

            $host = str_contains('redis://', $options['host']) ? $options['host'] : 'redis://' . $options['host'];

            $host = $host . ":" . $options['port'];

            $redisConfig = [
                'auth' => $options['password'],
                'db' => $options['select'] ?? 0,
                'prefix' => $options['prefix'] ?? 0,
                'retry_seconds' => $options['retry_seconds'] ?? 5,
                'max_attempts' => $options['max_attempts'] ?? 5,
            ];
            $client = new RedisClient($host, $redisConfig);
            $client->logger(Log::channel($logger));
            static::$_connections[$name] = $client;
        }
        return static::$_connections[$name];
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