<?php
/**
 * @var $model \VK\Model All available data
 */
?>
<div class="page-inner zodiak-wrapper">
  <form action="<?php print $model->processorUrl; ?>" method="GET">
    <?php print $model->formHelperContent; ?>
    <div class="form-item item-title">
      URL to VK profile:
    </div>
    <div class="form-item item-value">
        <input type="text" name="vk_url" />
    </div>

    <div class="form-item item-actions">
      <input type="submit" value="Generate" />
    </div>
  </form>
</div>
