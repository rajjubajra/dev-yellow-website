<?php

namespace Drupal\io\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\io\IoManagerInterface;
use Drupal\io\Ajax\IoReplaceCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides controller for IO block route.
 *
 * Cannot extend \Drupal\Core\Entity\Controller\EntityViewController due to
 * parameter EntityManagerInterface referring to deprecated methods.
 */
class IoBlockController extends ControllerBase {

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * The context repository interface.
   *
   * @var \Drupal\Core\Plugin\Context\ContextRepositoryInterface
   */
  protected $contextRepository;

  /**
   * The IO manager service.
   *
   * @var \Drupal\io\IoManager
   */
  protected $ioManager;

  /**
   * Constructs a new IoBlockController object.
   */
  public function __construct(ContextHandlerInterface $context_handler, ContextRepositoryInterface $context_repository, IoManagerInterface $io_manager) {
    $this->contextHandler = $context_handler;
    $this->contextRepository = $context_repository;
    $this->ioManager = $io_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('context.handler'),
      $container->get('context.repository'),
      $container->get('io.manager')
    );
  }

  /**
   * Loads and renders a block via AJAX.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return array
   *   Return the requested block based on the given block ID.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *
   * @see http://symfony.com/doc/current/routing.html#required-and-optional-placeholders
   */
  public function load(Request $request) {
    $uuid = $request->query->get('ioid');

    if (isset($uuid) && $uuid) {
      $block = $this->ioManager->loadEntityByUuid($uuid, 'block');

      // Only display block if allowed.
      if ($block && $this->ioManager->isAllowedBlock($block)) {
        // Create response object.
        $response = new AjaxResponse();

        // Will fetch the block content without theme_block() wrapper.
        $block_id = $block->getPluginId();
        $block_plugin = $this->ioManager->blockManager()->createInstance($block_id);
        $block_plugin->setConfiguration($block->get('settings'));

        // Inject context values.
        if ($block instanceof ContextAwarePluginInterface) {
          $contexts = $this->contextRepository->getRuntimeContexts(array_values($block->getContextMapping()));
          $this->contextHandler->applyContextMapping($block, $contexts);
        }

        // Create render context.
        $context = new RenderContext();
        $render = $this->ioManager->blazyManager()->getRenderer()->executeInRenderContext($context, function () use ($block_plugin) {
          // We only need its content without extra markups.
          return $block_plugin->build();
        });

        // Prevents empty render from screwing up the response:
        // The render array has not yet been rendered, hence not all
        // attachments have been collected yet.
        if ($render) {
          // Add metadata.
          if (!$context->isEmpty()) {
            $bubbleable_metadata = $context->pop();
            BubbleableMetadata::createFromRenderArray($render)
              ->merge($bubbleable_metadata)
              ->applyTo($render);
          }

          $selector = 'a[href="' . $this->ioManager->getBlockUrl($uuid)->toString() . '"]';
          $response->addCommand(new IoReplaceCommand($selector, $render, NULL, 'block'));
        }
        return $response;
      }
      throw new AccessDeniedHttpException();
    }
    throw new NotFoundHttpException();
  }

}
