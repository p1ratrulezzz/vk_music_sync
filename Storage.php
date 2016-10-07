<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 24.09.16
 * Time: 14:54
 */

namespace VK;

class Storage {
  /**
   * @var Storage\AdapterInterface
   */
  protected $_adapter;
  public function __construct($adapter_driver, $adapter_config = []) {
    if ($adapter_driver instanceof Storage\AdapterInterface) {
      $this->_adapter = $adapter_driver;
    }
    else {
      $this->_adapter = $this->_buildStorageAdapter($adapter_driver);
    }

    // Initialize storage with passed configuration
    $this->_adapter->init($adapter_config);
  }

  protected static function _buildStorageAdapter($driver) {
    $adapter_name = ucfirst($driver) . 'Adapter';
    $class_name = 'VK\\Storage\\' . $adapter_name;
    $filename = __DIR__ . DIRECTORY_SEPARATOR . 'Storage' . DIRECTORY_SEPARATOR . $adapter_name . '.php';
    if (file_exists($filename)) {
      require_once $filename;
    }

    if (!class_exists($class_name)) {
      throw new \Exception('Can\'t load driver class ' . $class_name);
    }

    return new $class_name();
  }

  public function loadConfiguration() {
    return $this->_adapter->loadConfiguration();
  }

  public function get($name, $default = null) {
    return $this->_adapter->get($name, $default);
  }

  public function set($name, $value) {
    return $this->_adapter->set($name, $value);
  }

  public function scheduleUser($id) {
    return $this->_adapter->scheduleUser($id);
  }

  public function loadState() {
    $state = $this->_adapter->loadState();

    // Merge defaults
    $state += [
      'index' => 0,
    ];

    return $state;
  }

  public function saveState($state) {
    return $this->_adapter->saveState($state);
  }

  public function loadUserByIndex($index) {
    return $this->_adapter->loadUserByIndex($index);
  }

  public function loadUserById($id) {
    return $this->_adapter->loadUserById($id);
  }

  public function updateUser(array $user) {
    $user['updated'] = gmdate('c');
    return $this->_adapter->updateUser($user);
  }

  public function addAudioToUserList($user_id, $audio_id) {
    return $this->_adapter->addAudioToUserList($user_id, $audio_id);
  }

  public function updateAudioRecord(array $audio) {
    if (!isset($audio['data_saved_as'])) {
      $audio['data_saved_as'] = 'none';
      $audio['data_save_as'] = 'file';
      $audio['data_save_value'] = '';
    }

    return $this->_adapter->updateAudioRecord($audio);
  }

  public function getAudioUserListByUserId($id) {
    return $this->_adapter->getAudioUserListByUserId($id);
  }

  public function loadAudioById($id) {
    return $this->_adapter->loadAudioById($id);
  }
}
