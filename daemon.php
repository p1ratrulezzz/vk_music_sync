<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 24.09.16
 * Time: 14:56
 */

namespace VK;

/**
 * Variables includes from other files
 * @var $settings array
 * @var $controller Controller
 * @var $storage Storage
 */

require_once __DIR__. '/Boot.php';

if (!$controller->checkAuth()) {
  echo "Please, update access token by visiting <a href=\"{$controller->base_path}\">Auth page</a>";
  exit;
}

if (php_sapi_name() != 'cli') {
  die("Please, run this script using cli");
}

/**
 * Default PHP CLI variables
 * @var $argv array
 * @var $argc array
 */

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
  case 'job':
    $state = $storage->loadState();
    $last_time_no_result = false;
    for ($i = 0; $i<=20; $i++) {
      $user = $storage->loadUserByIndex($state['index']++);

      // Save new index immediately
      $storage->saveState($state);

      if (!$user) {
        $state['index'] = 0;
        if (!$last_time_no_result) {
          $last_time_no_result = true;
          continue;
        }
        else {
          // No items to process at all
          exit;
        }
      }

      $last_time_no_result = false;

      $audios = $controller->callVK('audio.get', [
        'owner_id'  => $user['id'],
        'need_user' => 0,
        'offset'    => $user['index'],
        'count'     => $settings['audio_per_request'],
      ]);

      if (isset($audios->response->items)) {
        $user['count'] = $audios->response->count;
        $user['index'] += $settings['audio_per_request'];

        if (empty($audios->response->items)) {
          $user['index'] = 0;
        }

        // Update user with new info
        $storage->updateUser($user);

        // Add all audios to database
        foreach ($audios->response->items as $_audio) {
          $storage->addAudioToUserList($user['id'], $_audio->id);
          $storage->updateAudioRecord((array) $_audio);
        }
      }
    }

    //$storage->saveState($state);
    break;
  case 'cron_operation':
    $task_info = $storage->acquireCronTask();
    break;
}
