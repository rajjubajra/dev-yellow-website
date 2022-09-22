<?php

namespace Drupal\io\Ajax;

use Drupal\Core\Ajax\HtmlCommand;

/**
 * Extends core HtmlCommand.
 *
 * @ingroup io
 */
class IoHtmlCommand extends HtmlCommand {

  /**
   * The caller for the method to reduce deep checks, if known.
   *
   * @var string
   */
  protected $ioCaller;

  /**
   * Overrides an HtmlCommand object.
   */
  public function __construct($selector, $content, array $settings = NULL, $io_caller = 'block') {
    parent::__construct($selector, $content, $settings);
    $this->ioCaller = $io_caller;
  }

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return [
      'io' => $this->ioCaller,
    ] + parent::render();
  }

}
