<?php
/**
 * @var $model \VK\Model All available data
 */
?>
<div class="page-inner zodiak-wrapper">
  <?php if (!empty($model->errors)): ?>
    <div class="form-item item-error">
      <span>Errors occured. Try again with correct data.</span>
      <div class="form-item-wrapper">
        <div class="form-item item-title"></div>
        <div class="form-item item-value">
          <?php print $model->link_back; ?>
        </div>
      </div>
    </div>
  <?php else: ?>
    <?php if ($model->display_headers): ?>
    <div class="form-item-wrapper">
      <div class="form-item item-title">
        Name:
      </div>
      <div class="form-item item-value">
        <?php print $model->username; ?>
      </div>
    </div>
    <div class="form-item-wrapper">
      <div class="form-item item-title">
        VK profile:
      </div>
      <div class="form-item item-value">
        <?php print $model->user_link; ?>
      </div>
    </div>

    <div class="form-item-wrapper">
      <div class="form-item item-title">
        All friends:
      </div>
      <div class="form-item item-value">
        <?php print $model->users_info['count_all']; ?>
      </div>
    </div>

    <div class="form-item-wrapper">
      <div class="form-item item-title">
        Friends with birth date:
      </div>
      <div class="form-item item-value">
        <?php print $model->users_info['count_correct']; ?>
      </div>
    </div>

    <?php if ($model->users_info['count_incorrect'] > 0): ?>
      <div class="form-item-wrapper">
        <div class="form-item item-title">
          Friends without birth date:
        </div>
        <div class="form-item item-value">
          <?php print $model->users_info['count_incorrect']; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="form-item-wrapper">
      <div class="form-item item-title"></div>
      <div class="form-item item-value">
        <?php print $model->link_back; ?>
      </div>
    </div>

    <?php endif; ?>

    <div class="clearfix">&nbsp;</div>
    <div class="table-wrapper">
      <?php $row_index = 0; ?>
      <!-- First row -->
      <div class="table-row first row-<?php print $row_index++; ?>">
        <div class="table-col form-item col-1 first">
          Zodiak sign name
        </div>
        <div class="table-col form-item col-2">
          Percent of all friends with birthdate
        </div>
        <div class="table-col form-item col-3">
          Percent of all friends (including users without birthdates)
        </div>
      </div>

      <!-- Remaining rows -->
      <?php foreach ($model->users_info['correct_sorted'] as $item): ?>
        <div class="table-row row-<?php print $row_index++; ?>">
          <div class="table-col form-item col-1 first">
            <?php print \VK\Processors\Zodiak::translate($item['human_name'], \VK\Processors\Zodiak::LANG_RU); ?>
          </div>
          <div class="table-col form-item col-2">
            <span class="value-container">
              <?php print $item['percent_of_correct']; ?>
            </span>
            <span class="value-suffix">%</span>
          </div>
          <div class="table-col form-item col-3">
            <span class="value-container">
              <?php print $item['percent_of_all']; ?>
            </span>
            <span class="value-suffix">%</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
