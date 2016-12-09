<?php
/**
 * @var $model \VK\Model
 */
?>
<!DOCTYPE html>
<html>
<head>
  <?php foreach($model->getStyles() as $url): ?>
  <link rel="stylesheet" type="text/css" href="<?php print $url; ?>">
  <?php endforeach; ?>
</head>

