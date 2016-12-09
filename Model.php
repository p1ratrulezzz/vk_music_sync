<?php
/***
 * Model class
 */

namespace VK;

class Model extends \stdClass {
  public $styles = [];

  public function getStyles() {
    return $this->styles;
  }
}
