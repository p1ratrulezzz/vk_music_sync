<?php
/**
 * Created by PhpStorm.
 * User: p1ratrulezzz
 * Date: 24.09.16
 * Time: 14:57
 */

namespace VK;

// Initialize settings
$settings = [];

// Include settings
require_once __DIR__ . '/settings.php';

// Require interfaces
require_once __DIR__ . '/Storage/AdapterInterface.php';

// Include other classes
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Storage.php';

// Get controller
require_once __DIR__ . DIRECTORY_SEPARATOR . 'Controller.php';

// Initialize storage
$storage = new Storage($settings['storage']['configuration']['driver'], $settings['storage']['configuration']);

$controller = new Controller($storage);
