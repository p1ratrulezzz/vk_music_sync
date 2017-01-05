<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 17.09.16
 * Time: 12:10
 */

namespace VK;

use mikehaertl\wkhtmlto\Image;
use mikehaertl\wkhtmlto\Pdf;
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
  public $base_path = '';

  /*
   * Urls
   */
  protected $access_token = null;
  protected $_auth_url = 'https://oauth.vk.com/authorize';
  protected $_redirect_url = null;
  protected $_auth_token_url = 'https://oauth.vk.com/access_token';
  /**
   * @var Storage
   */
  protected $_storage = null;
  protected $settings = null;
  protected $templates_dir;

public function __construct(Storage $storage) {
  global $settings;
  $this->_storage = $storage;
  $this->settings = $settings;

  $this->_redirect_url = $this->settings['vk']['redirect_url'];
  $this->base_path = ltrim(dirname($_SERVER['SCRIPT_NAME']), '/');
  $this->templates_dir = __DIR__ . '/templates';
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

  public function doAuthFlow() {
      $params = [
          'client_id' => static::client_id,
          'redirect_uri' => $this->_redirect_url,
          'scope' => static::access_offline + static::access_audio,
          'v' => static::api_version,
          'response_type' => 'code',
      ];

      $url = $this->_auth_url . '?' . http_build_query($params);
      header('Location: ' . $url);
      exit;
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
        die('Can\'t get access_token ' . var_export($response, TRUE));
    }
  }

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

  public function doIndex() {
      if ($this->checkAuth()) {
          echo 'Your access token is OK. Daemon should work fine.';
      }

      // Define variables to be used in input file.
      $controller = $this;
      include __DIR__ . '/templates/index.tpl.php';
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

  public function pageContentBegin() {
    $model = new Model();
    $this->pageHeader();
    $this->pageIncludeTemplate('page/body_begin.tpl.php', $model);
  }

  public function pageHeader() {
    $model = new Model();
    $model->styles = [
        '/' . $this->base_path . '/css/' . 'styles.css',
    ];

    $this->pageIncludeTemplate('page/header.tpl.php', $model);
  }

  public function pageIncludeTemplate($path, $model) {
    include $this->templates_dir . '/' . $path;
  }

  public function pageContentEnd() {
    $model = new Model();
    $this->pageIncludeTemplate('page/body_end.tpl.php', $model);
    $this->pageIncludeTemplate('page/footer.tpl.php', $model);
  }

  public function doGenerateZodiakProcess() {
    require_once __DIR__ . '/Processors/Zodiak.php';
    $model = new Model();

    if (NULL === ($name = $this->getParam('vk_url'))) {
        $model->errors[] = 'error';
    }

    $name_unchanged = $name;

    // Preprocess VK account name. Convert to id.
    if (preg_match('/http(?:s)?\:\/\/[^\/]+\/([^\/]+)/i', $name, $reg)) {
        $name = $reg[1];
    }

    $cache_key = 'zodiak:'. $name;
    if (empty($model->errors) &&  FALSE === ($cached = $this->_storage->get($cache_key, FALSE))) {
      $response = $this->callVK('users.get', [
        'user_ids'  => $name,
        'fields'    => 'domain',
        'name_case' => 'nom',
      ]);

      if (!isset($response->response[0]->id)) {
        $model->errors[] = 'Can\'t process this user name';
      } else {
        $user_id = $response->response[0]->id;

        $model->username = "{$response->response[0]->first_name} {$response->response[0]->last_name}";
        $model->user_link = "https://vk.com/{$response->response[0]->domain}";
        $model->user_link = "<a target=\"_blank\" href=\"{$model->user_link}\">{$model->user_link}</a>";

        // @todo: Cache friends
        $response = $this->callVK('friends.get', [
          'user_id'   => $user_id,
          'fields'    => 'sex,bdate,city,country,education,relation,universities',
          'name_case' => 'nom',
        ]);

        if (!empty($response->response->items)) {
          $friends = $response->response->items;
          // @todo: Handle case when user has more than 5000 friends

          $users_info = [
            'count_correct'   => 0,
            'count_incorrect' => 0,
            'count_all'       => $response->response->count,
          ];

          foreach ($friends as $friend) {
            // Search birth date using cache and API search
            // @todo: Search this in background
            if (empty($friend->bdate) && ($bdate = $this->vkSearchUserBdate($friend)) !== FALSE) {
              $friend->bdate = $bdate;
            }

            if (!empty($friend->bdate)) {
              $bdate = $friend->bdate;
              if (preg_match('/^([0-9]+)\.([0-9]+)\.[0-9]+$/i', trim($bdate), $reg)) {
                $bdate = "{$reg[1]}.{$reg[2]}";
              }

              $zodiak = Zodiak::defineZodiakByDate($bdate); // @todo: Support horoscope type

              if ($zodiak !== NULL) {
                // Init Zodiak array if not yet exists
                if (!isset($users_info['correct'][ $zodiak ])) {
                  $users_info['correct'][ $zodiak ] = [
                    'count'      => 0,
                    'people'     => [],
                    'key'        => $zodiak,
                    'human_name' => $zodiak, // @todo: Implement human name in different languages
                  ];
                }

                $users_info['correct'][ $zodiak ]['people'][ $friend->id ] = $friend;
                $users_info['correct'][ $zodiak ]['count']++;
                $users_info['count_correct']++;
              } else {
                $users_info['correct']['unknown'][ $friend->id ] = $friend;
              }
            } else {
              $users_info['missing_bdate'][ $friend->id ] = $friend;
            }
          }

          $users_info['count_incorrect'] = count($users_info['missing_bdate']);

          // Count percentage
          foreach ($users_info['correct'] as &$_info) {
            $_info['percent_of_all'] = sprintf('%.2F', $_info['count'] * 100 / $users_info['count_all']);
            $_info['percent_of_correct'] = sprintf('%.2F', $_info['count'] * 100 / $users_info['count_correct']);
          }

          $users_info['correct_sorted'] = $users_info['correct'];
          uasort($users_info['correct_sorted'], function ($a, $b) {
            if ($a['count'] < $b['count']) {
              return 1;
            } elseif ($a['count'] > $b['count']) {
              return -1;
            } else {
              return 0;
            }
          });

          $model->users_info = $users_info;
        } else {
          $model->errors[] = 'Can\'t get friends';
        }
      }

      $this->_storage->set($cache_key, serialize((array) $model), 12 * 60 * 60);
    }
    elseif (empty($model->errors)) {
      $cached = unserialize($cached);
      foreach ((array) $cached as $param => $value) {
        $model->{$param} = $value;
      }
    }

    ob_start();
    $model->link_back = "<a href=\"/{$this->base_path}/?do=generateZodiak\">Return back</a>";
    $model->display_headers = !boolval($this->getParam('noHeaders', 0));
    $this->pageContentBegin();
    $this->pageIncludeTemplate('generate_zodiak_diagram.tpl.php', $model);
    $this->pageContentEnd();


    if ($as_image = $this->getParam('asImage')) {
      if (!empty($model->errors)) {
        header("HTTP/1.0 404 Not Found");
        exit;
      }

      require_once __DIR__ . '/lib/vendor/autoload.php';
      ob_end_clean();
      $url = (isset($_SERVER['HTTPS']) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . '/' . $this->base_path . '/?do=generateZodiakProcess&vk_url=' . $name_unchanged . '&noHeaders=' . $this->getParam('noHeaders', 0);

      $img = new Image($url);
      $img->setOptions([
        'width' => 410,
        'format' => 'jpg',
      ]);

      $img->send('zodiak_' . $name . '_image.jpg');
    }
    else {
      ob_end_flush();
    }
  }

  public function getParam($name, $default = NULL) {
      return isset($_GET[$name]) && ($filtered = filter_var($_GET[$name])) !== FALSE && $filtered != '' ? $filtered : $default;
  }

  public function vkSearchUserBdate($user, $clear_cache = FALSE) {
    set_time_limit(3600);
    $cache_key = 'zodiak:bdate:' . $user->id;
    if (FALSE === ($cached = $this->_storage->get($cache_key, FALSE))) {
      $this->_storage->setObject('zodiak:bdate:search:' . $user->id, ['id' => $user->id, 'user' => serialize($user)]);
      $this->_storage->set($cache_key, '');

      return FALSE; //@fixme: Implement in background
      $fields = explode(',', 'sex,bdate,city,country,university');
      $searchParams = [
        'q' => $user->first_name . ' ' . $user->last_name,
        'count' => 500,
      ];

      // Build search params according to existing fields
      foreach ($fields as $field) {
        if (!empty($user->{$field})) {
          $value = $user->{$field};
          switch ($field) {
            case 'universities':
              // @todo: Search faculty
              if (!empty($value[0]->id)) {

              }
              break;
            case 'city':
            case 'country':
              $searchParams[$field] = $value->id;
              break;
            default:
              $searchParams[$field] = $value;
              break;
          }
        }
      }

      $month_found = false;
      for ($month = 1; $month <= 12; $month++) {
        $params = $searchParams;
        $params['birth_month'] = $month;

        sleep(1); // Wait 1 second to settle down the api server
        if (($response = $this->callVK('users.search', $params)) && !empty($response->response->count)) {
          foreach ($response->response->items as $item) {
            if ($item->id == $user->id) {
              $month_found = $month;
              break;
            }
          }
        }
        else {
          // @todo: Error handling here
          $a = 1;
        }

        if ($month_found) {
          break;
        }
      }

      $day_found = FALSE;
      if ($month_found) {
        $max_days = $month == 2 ? 28 : (in_array($month, [4, 6, 9, 11]) ? 30 : 31);
        for ($day = 1; $day <= $max_days; $day++) {
          $params = $searchParams;
          $params['birth_month'] = $month_found;
          $params['birth_day'] = $day;

          sleep(1); // Wait 1 second to settle down the api server
          if (($response = $this->callVK('users.search', $params)) && !empty($response->response->count)) {
            foreach ($response->response->items as $item) {
              if ($item->id == $user->id) {
                $day_found = $day;
                break;
              }
            }
          }

          if ($day_found) {
            break;
          }
        }
      }

      $cached = ($month_found && $day_found) ? "{$day_found}.{$month_found}" : '';
      $this->_storage->set($cache_key, $cached, strtotime('6 months') - time());
    }

    return !empty($cached) ? $cached : FALSE;
  }
}
