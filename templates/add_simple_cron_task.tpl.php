<?php
/**
 * @var $controller \VK\Controller
 */

?>

<form action="<?php $controller->base_path; ?>">
  <input type="hidden" name="do" value="addSimpleCronTask" />
  <textarea name="meta_json" rows="10" cols="50"></textarea><br />
  <input type="submit" value="Add task" />
</form>
