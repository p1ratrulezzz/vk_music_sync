<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 24.09.16
 * Time: 14:56
 */

namespace VK\Storage;


class RedisAdapter implements AdapterInterface {
  /**
   * @var \Redis
   */
  protected $_redis = NULL;
  public function init($config = []) {
    $config += [
      'host' => 'localhost',
      'port' => '6379',
      'dbIndex' => 0,
    ];

    $this->_redis = new \Redis();
    $status = $this->_redis->connect($config['host'], $config['port']);
    $this->_redis->select($config['dbIndex']);
    $status = ($this->_redis->ping() == '+PONG');
    if (!$status) {
      var_dump($config);
      throw new \Exception('Can\'t connect to Redis');
    }
  }

  public function get($name, $default = null) {
    return $this->_redis->get($name);
  }

  public function set($name, $value) {
    return $this->_redis->set($name, $value);
  }

  public function scheduleUser($id) {
    $key = 'user:' . $id;
    if ($this->_redis->exists($key)) {
      return false;
    }

    $info = [
      'added' => gmdate('c'),
      'id' => $id,
    ];

    $this->_redis->hMset($key, $info);

    return true;
  }
}
