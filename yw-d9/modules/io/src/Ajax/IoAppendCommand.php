<?php

namespace Drupal\io\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsTrait;
use Drupal\io\Plugin\views\pager\IoPager;

/**
 * Provides an AJAX command for appending new rows in a paged AJAX response.
 *
 * This command is implemented in Drupal.AjaxCommands.prototype.ioAppend().
 */
class IoAppendCommand implements CommandInterface, CommandWithAttachedAssetsInterface {

  use CommandWithAttachedAssetsTrait;

  /**
   * The settings for the command.
   *
   * @var array
   */
  protected $settings;

  /**
   * The content to append.
   *
   * Either a render array or an HTML string.
   *
   * @var string|array
   */
  protected $content;

  /**
   * The caller for the method to reduce deep checks, if known.
   *
   * @var string
   */
  protected $ioCaller;

  /**
   * Constructs a \Drupal\views\Ajax\IoAppendCommand object.
   *
   * @param string|array $content
   *   The newly loaded AJAX content, a render array or an HTML string.
   * @param array $settings
   *   Array with the following keys:
   *   - method
   *   - contentSelector
   *   - pagerSelector.
   * @param string $io_caller
   *   The IO caller.
   */
  public function __construct($content, array $settings = [], $io_caller = 'pager') {
    $this->content = $content;
    $defaults = [
      'method' => 'append',
      'contentSelector' => IoPager::CONTENT_SELECTOR,
      'pagerSelector' => IoPager::PAGER_SELECTOR,
    ];
    $this->settings = array_merge($defaults, $settings);
    $this->ioCaller = $io_caller;
  }

  /**
   * Implements \Drupal\Core\Ajax\CommandInterface::render().
   */
  public function render() {
    return [
      'io' => $this->ioCaller,
      'command' => 'ioAppend',
      'data' => $this->getRenderedContent(),
      'settings' => $this->settings,
    ];
  }

}
