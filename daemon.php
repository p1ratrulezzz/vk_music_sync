<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 24.09.16
 * Time: 14:56
 */

namespace VK;

/**
 * @var $controller Controller
 */
$controller = null;

/**
 * @var $storage Storage
 */
$storage = null;

require_once __DIR__. '/Boot.php';

if (!$controller->checkAuth()) {
  echo "Please, update access token by visiting <a href=\"{$controller->base_path}\">Auth page</a>";
  exit;
}

if (php_sapi_name() != 'cli') {
  die("Please, run this script using cli");
}

$params = [
  'action' => 'job',
];
if ($argc > 1) {
  for ($i=1; $i<$argc; $i++) {
    $ruins = explode('=', $argv[$i]);
    $params[$ruins[0]] = $ruins[1];
  }
}

switch ($params['action']) {
  case 'adduser':
    $users = $params['user_id'];
    $response = $controller->callVK('users.get', [
      'user_ids' => $users,
    ]);

    if (empty($response->response)) {
      throw new \Exception('Can\'t process users ' . $users);
    }

    foreach ($response->response as $item) {
      $storage->scheduleUser($item->id);
    }
    //$storage->
    break;
}
