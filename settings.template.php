<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 24.09.16
 * Time: 14:58
 */

$settings['storage']['configuration'] = [
  'driver' => 'redis',
  'host' => '127.0.0.1',
  'port' => '6379',
  'dbIndex' => 5,
];

/**
 * VK related settings
 */
$settings['vk']['redirect_ui'] = 'https://p1ratrulezzz.me/vkauth_verify.php';
$settings['vk']['client_secret'] = 'VMOiU8u2WHf8A1Kz1H76';
