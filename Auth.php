<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 17.09.16
 * Time: 12:10
 */

namespace VK;

/**
 * @var $controller Controller
 */
$controller = null;

// Boot all needed things and build controller
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Boot.php';

session_start();
$action = isset($_GET['do']) ? $_GET['do'] : 'authFlow';
$actionMethod = 'do' . ucfirst($action);
// Check if was already authorized
if ($actionMethod != 'doAuthFlow' && $actionMethod != 'doRefreshToken' && !$controller->checkAuth(true)) {
    $controller->addRedirection('action', ['data' => $action]);
}

// Check if we have any scheduled redirection tasks.
$controller::performRedirectIfExist();
if (!is_callable([$controller, $actionMethod])) {
    throw new \Exception('Can\'t find method named ' . $actionMethod . '');
}

call_user_func([$controller, $actionMethod]);
