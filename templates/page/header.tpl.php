<?php
/**
 * @var $model \VK\Model
 */
?>
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <?php foreach($model->getStyles() as $url): ?>
  <link rel="stylesheet" type="text/css" href="<?php print $url; ?>">
  <?php endforeach; ?>
</head>

