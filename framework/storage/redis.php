<?php
namespace Lysine\Storage;

use Lysine\IStorage;
use Lysine\Storage;

/**
 * Redis数据库封装
 * 使用redis extension (https://github.com/owlient/phpredis)
 * 用装饰模式封装，使用方法参考redis extension网站
 *
 * @uses IStorage
 * @package Storage
 * @author yangyi <yangyi.cn.gz@gmail.com>
 */
class Redis implements IStorage {
    private $handler;

    private $config = array(
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0,
        'prefix' => null,
        'persistent' => 0,
     // 'unix_socket' => '/tmp/redis.sock',
     // 'password' => 'your password',
     // 'database' => 0,    // dbindex, the database number to switch to
    );

    public function __construct(array $config) {
        if (!extension_loaded('redis'))
            throw Storage\Error::require_extension('redis');

        if ($config) $this->config = array_merge($this->config, $config);
    }

    public function __call($fn, $args) {
        if (!$this->isConnected()) $this->connect();
        return call_user_func_array(array($this->handler, $fn), $args);
    }

    public function isConnected() {
        return $this->handler && $this->handler instanceof \Redis;
    }

    public function connect() {
        if ($this->isConnected()) return $this;

        $config = $this->config;
        $handler = new \Redis;

        // 优先使用unix socket
        $conn_args = isset($config['unix_socket'])
                   ? array($config['unix_socket'])
                   : array($config['host'], $config['port'], $config['timeout']);

        $conn = $config['persistent']
              ? call_user_func_array(array($handler, 'pconnect'), $conn_args)
              : call_user_func_array(array($handler, 'connect'), $conn_args);

        if (!$conn)
            throw new Storage\Error('Connect redis server failed', Storage\Error::CONNECT_FAILED);

        if (isset($config['password']) && !$handler->auth($config['password']))
            throw new Storage\Error('Invalid password');

        if (isset($config['database']) && !$handler->select($config['database']))
            throw new Storage\Error('Select database['. $config['database'] .'] failed');

        if (isset($config['prefix'])) $handler->setOption(\Redis::OPT_PREFIX, $config['prefix']);

        $this->handler = $handler;
        return $this;
    }

    public function disconnect() {
        $this->handler = null;
        return $this;
    }
}
