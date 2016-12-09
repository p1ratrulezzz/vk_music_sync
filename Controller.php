<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 17.09.16
 * Time: 12:10
 */

namespace VK;

use VK\Processors\Zodiak;

class Controller {
  const client_id = '4369157';

  /*
   * Access bitmasks
   */
  const access_audio = '+8';
  const access_offline = '+65536';

  const api_url = 'https://api.vk.com/method';
  const api_version = '5.53';
  protected $access_token = null;

  /*
   * Urls
   */
  protected $_auth_url = 'https://oauth.vk.com/authorize';
  protected $_redirect_url = null;
  protected $_auth_token_url = 'https://oauth.vk.com/access_token';

  /**
   * @var Storage
   */
  protected $_storage = null;

  protected $settings = null;

  public $base_path = '';
  protected $templates_dir;

  public static function httpGet($url, $get_info = FALSE) {
    //$url = urlencode($url);
    $ch = curl_init();
    $headers = array(
      'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
      'Accept-Charset' => 'windows-1251,utf-8;q=0.7,*;q=0.3',
      'Accept-Encoding' => 'gzip,deflate,sdch',
      'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.6,en;q=0.4',
      'Connection' => 'keep-alive',
    );
    curl_setopt($ch, CURLOPT_URL,            $url);
    curl_setopt($ch, CURLOPT_HEADER,         $get_info);
    curl_setopt($ch, CURLOPT_HTTPGET,         TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    //curl_setopt($ch, CURLINFO_HEADER_OUT, TRUE);
    //curl_setopt($ch, CURLOPT_NOBODY,         true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0; AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.79 Safari/537.1');
    curl_setopt($ch, CURLOPT_TIMEOUT,        30);
    $r = curl_exec($ch);
    $info = curl_getinfo($ch);
    //var_dump($info);
    if ($r === FALSE) {
      //return FALSE;
      //var_dump($info);
    }
    return $get_info === FALSE ? $r : $info;
  }


public function __construct(Storage $storage) {
  global $settings;
  $this->_storage = $storage;
  $this->settings = $settings;

  $this->_redirect_url = $this->settings['vk']['redirect_url'];
  $this->base_path = ltrim(dirname($_SERVER['SCRIPT_NAME']), '/');
  $this->templates_dir = __DIR__ . '/templates';
}

  public function doAuthFlow() {
      $params = [
          'client_id' => static::client_id,
          'redirect_uri' => $this->_redirect_url,
          'scope' => static::access_offline,
          'v' => static::api_version,
          'response_type' => 'code',
      ];

      $url = $this->_auth_url . '?' . http_build_query($params);
      header('Location: ' . $url);
      exit;
  }

  public static function addRedirection($type, array $options) {
      $defaults = [
          'action' => [
              'parameters' => [],
          ],
          'url' => [],
      ];

      // Merge defaults.
      $options += isset($defaults[$type]) ? $defaults[$type] : [];
      $options['type'] = $type;

      if (!isset($_SESSION['redirections'])) {
          $_SESSION['redirections'] = [];
      }

      // Add redirect info to session storage.
      array_push($_SESSION, $options);
  }

  public function doGeneratePlaylist() {
      if (!isset($_GET['id'])) {
          die('Please provide id of a user');
      }

      $list = $this->_storage->getAudioUserListByUserId($_GET['id']);
      if ($list) {
          $i = 1;
          echo "<div>Playlist for user {$_GET['id']}</div>";
          foreach ($list as $item) {
              if ($audio = $this->_storage->loadAudioById($item)) {
                  $audio['track_number'] = $i++;
                  include __DIR__ . '/templates/audio_item.tpl.php';
              }

          }
      }
  }

  public static function performRedirectIfExist() {
      if (!empty($_SESSION['redirections'])) {
          $info = array_pop($_SESSION['redirections']);
          static::peformRedirect($info);
      }

      return false;
  }

  public static function peformRedirect(array $options) {
      switch($options['type']) {
          case 'action':
              static::action($options['data'], $options['parameters']);
              break;
          default:
              throw new \Exception('Can\'t perform redirect');
      }
  }

  public function callVK($method, $params = []) {
      $url = static::api_url . '/' . $method;
      $params['access_token'] = $this->access_token;
      $params['v'] = static::api_version;
      $url .= '?' . http_build_query($params);

      if ($response = $this->httpGet($url)) {
          return json_decode($response);
      }

      return false;
  }

  public function checkAuth($redirect = false) {
      $access_token = $this->_storage->get('access_token', null);
      if ($access_token) {
          $this->access_token = $access_token;
          if ($this->_checkToken()) {
              return TRUE;
          }
      }

      if ($redirect) {
          $this->action('authFlow');
      }

      return false;
  }

  public function _checkToken() {
      $response = $this->callVK('users.get', [
          'name_case' => 'Nom',
      ]);

      if (!isset($response->response[0]->id)) {
          return false;
      }

      return true;
  }

  public function doRefreshToken() {
    $code = $_GET['code'];
    $params = [
        'client_id' => static::client_id,
        'client_secret' => $this->settings['vk']['client_secret'],
        'redirect_uri' => $this->_redirect_url,
        'code' => $code,
    ];
    $url = $this->_auth_token_url . '?' . http_build_query($params);

    try {

        if (!($response = $this->httpGet($url))) {
            throw new \Exception();
        }

        // Parse response from json
        $response = json_decode($response);

        if (!isset($response->access_token)) {
            throw new  \Exception();
        }

        $this->_storage->set('access_token', $response->access_token);
        $this->action('index');
    }
    catch (\Exception $e) {
        // Silence
        die('Can\'t get access_token');
    }
  }

  public function doIndex() {
      if ($this->checkAuth()) {
          echo 'Your access token is OK. Daemon should work fine.';
      }

      // Define variables to be used in input file.
      $controller = $this;
      include __DIR__ . '/templates/index.tpl.php';
  }

  /**
   * @param $action
   * @param array $params
   */
  public function action($action, $params = []) {
      $url = $_SERVER['SCRIPT_NAME'];
      $params['do'] = $action;
      $url .= '?' . http_build_query($params);
      header('Location: ' . $url);
      exit;
  }

  public function doAddSimpleCronTask() {
      $controller = $this;
      include $this->templates_dir. '/add_simple_cron_task.tpl.php';
  }

  public function doGenerateZodiak() {
      $model = new Model();
      $model->processorUrl = '/' . $this->base_path . '/';
      $model->formHelperContent = '
      <input type="hidden" name="do" value="generateZodiakProcess" />
      ';

      $this->pageContentBegin();
      $this->pageIncludeTemplate('generate_zodiak_ui.tpl.php', $model);
      $this->pageContentEnd();
  }

  public function getParam($name, $default = NULL) {
      return isset($_GET[$name]) && ($filtered = filter_var($_GET[$name])) !== FALSE && $filtered != '' ? $filtered : $default;
  }

  public function doGenerateZodiakProcess() {
      require_once __DIR__ . '/Processors/Zodiak.php';
      $model = new Model();

      if (NULL === ($name = $this->getParam('vk_url'))) {
          $model->errors[] = 'error';
      }

      // Preprocess VK account name. Convert to id.
      if (preg_match('/http(?:s)?\:\/\/[^\/]+\/([^\/]+)/i', $name, $reg)) {
          $name = $reg[1];
      }

      $response = $this->callVK('users.get', [
        'user_ids' => $name,
        'fields' => 'domain',
        'name_case' => 'nom',
      ]);

      if (!isset($response->response[0]->id)) {
        $model->errors[] = 'Can\'t process this user name';
      }
      else {
        $user_id = $response->response[0]->id;

        $model->username = "{$response->response[0]->first_name} {$response->response[0]->last_name}";
        $model->user_link = "https://vk.com/{$response->response[0]->domain}";
        $model->user_link = "<a target=\"_blank\" href=\"{$model->user_link}\">{$model->user_link}</a>";

        // @todo: Cache friends
        $response = $this->callVK('friends.get',[
            'user_id' => $user_id,
            'fields' => 'bdate',
            'name_case' => 'nom',
        ]);

        if (!empty($response->response->items)) {
          $friends = $response->response->items;
          // @todo: Handle case when user has more than 5000 friends

          $users_info = [
            'count_correct' => 0,
            'count_incorrect' => 0,
            'count_all' => $response->response->count,
          ];

          foreach ($friends as $friend) {
              if (!empty($friend->bdate)) {
                  $bdate = $friend->bdate;
                  if (preg_match('/^([0-9]+)\.([0-9]+)\.[0-9]+$/i', trim($bdate), $reg)) {
                      $bdate = "{$reg[1]}.{$reg[2]}";
                  }

                  $zodiak = Zodiak::defineZodiakByDate($bdate); // @todo: Support horoscope type

                  if ($zodiak !== NULL) {
                      // Init Zodiak array if not yet exists
                      if (!isset($users_info['correct'][$zodiak])) {
                          $users_info['correct'][$zodiak] = [
                              'count' => 0,
                              'people' => [],
                              'key' => $zodiak,
                              'human_name' => $zodiak, // @todo: Implement human name in different languages
                          ];
                      }

                      $users_info['correct'][$zodiak]['people'][$friend->id] = $friend;
                      $users_info['correct'][$zodiak]['count']++;
                      $users_info['count_correct']++;
                  }
                  else {
                      $users_info['correct']['unknown'][$friend->id] = $friend;
                  }
              }
              else {
                  $users_info['missing_bdate'][$friend->id] = $friend;
                  // @todo: Find birthdate using some algorithm using search
              }
            }

            $users_info['count_incorrect'] = count($users_info['missing_bdate']);

            // Count percentage
            foreach ($users_info['correct'] as &$_info) {
              $_info['percent_of_all'] = sprintf('%.2F', $_info['count'] * 100 / $users_info['count_all']);
              $_info['percent_of_correct'] = sprintf('%.2F', $_info['count'] * 100 / $users_info['count_correct']);
            }

            $users_info['correct_sorted'] = $users_info['correct'];
            uasort($users_info['correct_sorted'], function($a, $b) {
              if ($a['count'] < $b['count']) {
                  return 1;
              }
              elseif ($a['count'] > $b['count']) {
                  return -1;
              }
              else {
                  return 0;
              }
            });

            $model->users_info = $users_info;
          }
          else {
            $model->errors[] = 'Can\'t get friends';
          }
      }

      $this->pageContentBegin();
      $this->pageIncludeTemplate('generate_zodiak_diagram.tpl.php', $model);
      $this->pageContentEnd();
  }

  public function pageIncludeTemplate($path, $model) {
      include $this->templates_dir . '/' . $path;
  }

  public function pageHeader() {
      $model = new Model();
      $model->styles = [
          '/' . $this->base_path . '/css/' . 'styles.css',
      ];

      $this->pageIncludeTemplate('page/header.tpl.php', $model);
  }

  public function pageContentBegin() {
      $model = new Model();
      $this->pageHeader();
      $this->pageIncludeTemplate('page/body_begin.tpl.php', $model);
  }

  public function pageContentEnd() {
      $model = new Model();
      $this->pageIncludeTemplate('page/body_end.tpl.php', $model);
      $this->pageIncludeTemplate('page/footer.tpl.php', $model);
  }
}
