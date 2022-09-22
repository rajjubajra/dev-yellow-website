<?php

namespace Drupal\io\EventSubscriber;

use Drupal\views\Ajax\ViewAjaxResponse;
use Drupal\io\Ajax\IoAppendCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Response subscriber to handle AJAX responses.
 */
class IoEventSubscriber implements EventSubscriberInterface {

  /**
   * Renders the ajax commands right before preparing the result.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event, which contains the possible AjaxResponse object.
   */
  public function onResponse(ResponseEvent $event) {
    $response = $event->getResponse();

    // Only alter views ajax responses.
    if ($response instanceof ViewAjaxResponse) {
      $this->onViewAjaxResponse($response);
    }
  }

  /**
   * Modifies AJAX response.
   *
   * @param \Drupal\views\Ajax\ViewAjaxResponse $response
   *   The response object, which contains the commands and strings.
   */
  public function onViewAjaxResponse(ViewAjaxResponse &$response) {
    $view = $response->getView();

    // Only alter commands if the user has selected our pager and it
    // attempting to move beyond page 0.
    if ($view->getPager()->getPluginId() !== 'io' || $view->getCurrentPage() === 0) {
      return;
    }

    $style_plugin = $view->getStyle();
    $commands = &$response->getCommands();
    foreach ($commands as $delta => &$command) {
      // Remove 'viewsScrollTop' command, as jumping to top is unnecessary.
      if ($command['command'] === 'viewsScrollTop') {
        unset($commands[$delta]);
      }
      // The replace should the only one, but just in case, we'll make sure.
      elseif ($command['command'] == 'insert' && $command['selector'] == '.js-view-dom-id-' . $view->dom_id) {
        // Take the data attribute, which is the content of the view,
        // otherwise discard the insert command for the view, we're
        // replacing it with a IoAppendCommand.
        $content = $commands[$delta]['data'];
        unset($commands[$delta]);

        $settings = [
          'style' => $style_plugin->getPluginId(),
          'view_dom_id' => $view->dom_id,
        ];

        // Table has no way to put [data-io-pager] into tbody element, pass it.
        if ($style_plugin->getPluginId() == 'table') {
          $settings['contentSelector'] = '.views-table tbody';
        }

        $response->addCommand(new IoAppendCommand($content, array_filter($settings)));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [KernelEvents::RESPONSE => [['onResponse']]];
  }

}
