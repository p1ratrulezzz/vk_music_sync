<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 17.09.16
 * Time: 12:10
 */

namespace VK;

class Auth {
    const client_id = '4369157';
    const client_secret = 'VMOiU8u2WHf8A1Kz1H79';

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

    protected $base_path = '';
    public function doAuthFlow() {
        $params = [
            'client_id' => static::client_id,
            'redirect_uri' => $this->_redirect_url,
            'scope' => static::access_audio + static::access_offline,
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
        if (isset($_SESSION['access_token'])) {
            $this->access_token = $_SESSION['access_token'];
            return true;
        }

        if ($redirect) {
            $this->action('authFlow');
        }

        return false;
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

            $_SESSION['access_token'] = $response->access_token;
            $this->action('index');
        }
        catch (\Exception $e) {
            // Silence
            die('Can\'t get access_token');
        }
    }

    public function doIndex() {
        $audios = $this->callVK('audio.get');

        var_dump($audios);
    }

    /**
     * @param $action
     * @param array $params
     */
    public function action($action, $params = []) {
        $url = $this->base_path . '/auth.php'; //@fixme: Replace with script name taken from $_SERVER.
        $params['do'] = $action;
        $url .= '?' . http_build_query($params);
        header('Location: ' . $url);
        exit;
    }

    /**
     * @param $name
     * @param null $value
     * @param bool $global
     * @param array $options
     * @return bool
     */
    public static function setCookie($name, $value = null, $options = array()) {
        // Merge defaults
        $options += session_get_cookie_params();
        return setcookie($name, $value, time() + $options['lifetime'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);
    }
}

session_start();
$auth = new Auth();
$action = isset($_GET['do']) ? $_GET['do'] : 'authFlow';
$actionMethod = 'do' . ucfirst($action);
// Check if was already authorized
if ($actionMethod != 'doAuthFlow' && $actionMethod != 'doRefreshToken' && !$auth->checkAuth(true)) {
    $auth->addRedirection('action', ['data' => $action]);
}

// Check if we have any sceduled redirect tasks.
$auth::performRedirectIfExist();
if (!is_callable([$auth, $actionMethod])) {
    throw new \Exception('Can\'t find method named ' . $actionMethod . '');
}

call_user_func([$auth, $actionMethod]);