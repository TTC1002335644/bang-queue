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

namespace bang\queue\queue;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\facade\Config;
use Workerman\Connection\TcpConnection;
use Workerman\RedisQueue\Client as RedisClient;
use Workerman\Timer;
use Workerman\Worker;
use bang\queue\tool\Reflection;
use bang\queue\Client;

class Work extends Command
{

    /**
     * 初始配置
     * @var array
     */
    protected array $config = [
        'count' => 8,//进程数
        'isDynamic' => false,
        'processPort' => 9500,//需要用一个端口来监听进程
        //通用配置
        'config' => [
            'path' => 'workerman/queue',//存储日志的目录
            'pidFileName' => 'workerman.pid',//日志文件
            'stdoutFile' => 'stdout.pid',//stdoutFile文件
            'logFile' => 'log.log',//日志文件
        ],
        'loggerChannel' => ''
    ];

    private bool $daemonize = false;

    /**
     * 已被订阅的信息
     * @var array
     */
    protected static array $subscribeMap = [];

    /**
     * 命令参数配置
     */
    protected function configure()
    {
        $this->setName('bang_queue:work')
            ->addArgument('mode', Argument::REQUIRED, "命令模式,支持 start,stop,restart,status,reload,connections")
            ->addOption('deamon', 'd', Option::VALUE_OPTIONAL, '是否为守护进程,需要守护进程，传true即可')
            ->setDescription('启动workerman-queue的命令');;
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return false|void
     */
    protected function execute(Input $input, Output $output)
    {
        $this->input = $input;
        $this->output = $output;

        try {
            //检测环境
            $this->check();
            //初始化
            $this->init();

            //启动worker
            $this->runWorker();
        } catch (Exception $e) {
            $this->output->error("出现错误：" . $e->getMessage());
            return false;
        }
        $this->output->info('启动成功');
    }

    /**
     * 检测命令和环境
     */
    private function check()
    {

        $mode = strtolower(trim($this->input->getArgument('mode')));
        $allowModeLists = ['start', 'stop', 'restart', 'status', 'reload', 'connections'];

        if (!in_array($mode, $allowModeLists)) {
            throw new Exception('命令错误，仅支持[ ' . implode(',', $allowModeLists) . ' ]');
        }

        //判断是否安装php_redis扩展
        if (!extension_loaded('redis')) {
            throw new Exception('请安装和开启php-redis扩展');
        }
        $daemonize = $this->input->getOption('deamon');
        if (strtolower((string)$daemonize) === "true") {
            $this->daemonize = true;
        }
    }

    /**
     * 初始化一些事情
     */
    private function init()
    {
        $this->config = array_merge($this->config, Config::get('bang_queue', []));

        //看看日志目录是否存在，不存在则生成
        $dirPath = root_path($this->config['config']['path'] ?? 'workerman_log/queue');

        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0700, true);
        }
        $this->config['config']['path'] = $dirPath;
    }

    /**
     * 将worker启动
     */
    private function runWorker()
    {
        $processCount = $this->config['count'] ?? 1;
        $defaultConnectionName = $this->config['default'] ?? 'default';
        $worker = new Worker();
        $worker->name = "Bang_queue";
        $worker->count = $processCount;
        $worker->onWorkerStart = function (Worker $worker) use ($defaultConnectionName) {
            static $subscribeMap = [];
            $this->output->info('进程ID：' . $worker->id . ',启动成功');

            /**
             * @var RedisClient
             */
            $connection = Client::connection($defaultConnectionName, $this->config['loggerChannel'] ?? null);

            $subscribeList = $this->config['listenList'] ?? [];
            if (!empty($subscribeList)) {
                foreach ($subscribeList as $queueName => $consumeClass) {
                    $consumeConnection = Reflection::getStaticProperties($consumeClass, 'connection');
                    if (empty($consumeConnection)) {
                        //未定义的情况，就用默认连接名
                        $consumeConnection = $defaultConnectionName;
                    }
                    if (!isset($subscribeMap[$queueName])) {
                        $subscribeMap[$queueName] = $consumeClass;
                        $connection->subscribe($queueName, [new $consumeClass, 'consume']);
                    }

                }
            }

            //判断是否需要动态监听
            $isDynamic = $this->config['isDynamic'] ?? false;
            if ($isDynamic === true && !empty($this->config['processPort']) && is_numeric($this->config['processPort'])) {
                $textWorker = new Worker('tcp://0.0.0.0:' . $this->config['processPort']);
                $textWorker->onMessage = function (TcpConnection $tcpConnection, $textData) use (&$subscribeMap, $connection) {
                    $data = json_decode($textData, true);
                    //用class来反射监听
                    if (!empty($data['class']) && !empty($data['queue'])) {
                        $consumeClass = $data['class'];
                        $queueName = $data['queue'];
                        if (!isset($subscribeMap[$queueName])) {
                            $subscribeMap[$queueName] = $consumeClass;
                            $connection->subscribe($queueName, [new $consumeClass, 'consume']);
                        }
                        unset($data, $consumeClass,);
                    }
                };
                $textWorker->listen();
            }

            //监听失败的
            $connection->onConsumeFailure(function (\Throwable $exception, $package) use ($subscribeMap) {
                //获取到队列名
                $queueName = $package['queue'] ?? null;
                if (!empty($queueName) && isset($subscribeMap[$queueName])) {
                    $consumeClass = $subscribeMap[$queueName];
                    method_exists($consumeClass, 'onConsumeFailure') && call_user_func([new $consumeClass, 'onConsumeFailure'], $exception, $package);
                }
                var_dump($package);
            });
        };

        if (!empty($this->config['config']['pidFileName'])) {
            Worker::$pidFile = $this->config['config']['path'] . $this->config['config']['pidFileName'];
        }

        if (!empty($this->config['config']['logFile'])) {
            Worker::$logFile = $this->config['config']['path'] . $this->config['config']['logFile'];
        }

        if (!empty($this->config['config']['stdoutFile'])) {
            Worker::$stdoutFile = $this->config['config']['path'] . $this->config['config']['stdoutFile'];
        }

        //判断是否需要守护进程
        if ($this->daemonize === true) {
            Worker::$daemonize = $this->daemonize;
        }

        $worker->runAll();

    }
}