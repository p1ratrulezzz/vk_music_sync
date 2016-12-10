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
    // @fixme: Move or at least copy settings to defaults settings
    $config += [
      'host' => 'localhost',
      'port' => '6379',
      'dbIndex' => 0,
    ];

    $this->_redis = new \Redis();
    $this->_redis->connect($config['host'], $config['port']);
    $this->_redis->select($config['dbIndex']);
    $status = ($this->_redis->ping() == '+PONG');
    if (!$status) {
      throw new \Exception('Can\'t connect to Redis');
    }
  }

  public function get($name) {
    return $this->_redis->get($name);
  }

  public function set($name, $value, $expire) {
    $state = $this->_redis->set($name, $value);
    if ($state && $expire) {
      $this->_redis->expire($name, $expire);
    }

    return $state;
  }

  /**
   * @param $id
   *
   * @return bool
   * @fixme: Rename to scheduleUserObject and pass object as parameter
   */
  public function scheduleUser($id) {
    $key = 'user:' . $id;
    if ($this->_redis->exists($key)) {
      return false;
    }

    $info = [
      'added' => gmdate('c'),
      'id' => $id,
      'index' => 0,
      'updated' => gmdate('c'),
    ];

    $this->_redis->hMset($key, $info);

    // Add user to list
    $this->_redis->rPush('users', $id);

    return true;
  }

  public function getObject($name) {
    return $this->_redis->hGetAll($name);
  }

  public function setObject($name, $value, $expire = null) {
    $state = $this->_redis->hMset($name, $value);

    if ($state && $expire) {
      $this->_redis->expire($name, $expire);
    }

    return $state;
  }

  public function loadState() {
    if (false === ($state = $this->_redis->hGetAll('state'))) {
      $state = [];
    }

    return $state;
  }

  public function saveState($state) {
    $this->_redis->hMset('state', $state);
    return true;
  }

  public function loadUserByIndex($index) {
     if (($data = $this->_redis->lGet('users', $index)) && $user = $this->loadUserById($data)) {
       return $user;
     }

    return false;
  }

  public function loadUserById($id) {
    return $this->_redis->hGetAll('user:' . $id);
  }

  public function updateUser($user) {
    return $this->_redis->hMset('user:' . $user['id'], $user);
  }

  public function addAudioToUserList($user_id, $audio_id) {
    $key = 'audiolist:' . $user_id;
    $key2 = 'audiolist2:' . $user_id;
//    $latest_item = $this->_redis->zRevRange($key, 0, 0, true);
//    $score = 0;
//    if (!empty($latest_item)) {
//      $score = reset($latest_item) + 1;
//    }

    $this->_redis->sAdd($key, $audio_id);
    $this->_redis->hSet($key2, $audio_id, $audio_id);

    return true;
  }

  public function updateAudioRecord(array $audio) {
    $this->_redis->hMset('audio:' . $audio['id'], $audio);

    return true;
  }

  public function getAudioUserListByUserId($id) {
    $key = 'audiolist:' . $id;
    return $this->_redis->zRange($key, 0, -1);
  }

  public function loadAudioById($id) {
    return $this->_redis->hMGet('audio:' . $id, [
      'artist',
      'title',
    ]);
  }
}
