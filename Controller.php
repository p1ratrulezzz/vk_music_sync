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
    const client_secret = 'VMOiU8u2WHf8A1Kz1H78';

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
    protected $_redirect_url = 'https://p1ratrulezzz.me/vkauth_verify.php';
    protected $_auth_token_url = 'https://oauth.vk.com/access_token';

    /**
     * @var Storage
     */
    protected $_storage = null;

    public $base_path = '';
    protected $templates_dir;

    public function __construct(Storage $storage) {
        $this->_storage = $storage;
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

        if ($response = @file_get_contents($url)) {
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
            'client_secret' => static::client_secret,
            'redirect_uri' => $this->_redirect_url,
            'code' => $code,
        ];
        $url = $this->_auth_token_url . '?' . http_build_query($params);

        try {
            if (!($response = @file_get_contents($url))) {
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

    public function doGenerateZodiakProcess() {
        require_once __DIR__ . '/Processors/Zodiak.php';


        $model = new Model();
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
