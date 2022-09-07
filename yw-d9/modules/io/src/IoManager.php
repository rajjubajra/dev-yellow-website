<?php

namespace Drupal\io;

use Drupal\Core\Url;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\block\BlockInterface;
use Drupal\blazy\BlazyManagerInterface;
use Drupal\io\Plugin\views\pager\IoPager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides IoManager service.
 */
class IoManager implements IoManagerInterface {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Drupal\Core\Block\BlockManagerInterface.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The blazy manager service.
   *
   * @var \Drupal\blazy\BlazyManagerInterface
   */
  protected $blazyManager;

  /**
   * Constructs a BlazyManager object.
   */
  public function __construct(AccountInterface $current_user, RouteMatchInterface $route_match, BlockManagerInterface $block_manager, BlazyManagerInterface $blazy_manager) {
    $this->currentUser = $current_user;
    $this->routeMatch = $route_match;
    $this->blockManager = $block_manager;
    $this->blazyManager = $blazy_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.block'),
      $container->get('blazy.manager')
    );
  }

  /**
   * Returns the current user service.
   */
  public function currentUser() {
    return $this->currentUser;
  }

  /**
   * Returns the current route match service.
   */
  public function routeMatch() {
    return $this->routeMatch;
  }

  /**
   * Returns the plugin block manager service.
   */
  public function blockManager() {
    return $this->blockManager;
  }

  /**
   * Returns the blazy manager.
   */
  public function blazyManager() {
    return $this->blazyManager;
  }

  /**
   * Returns the blazy manager.
   */
  public function loadEntityByUuid($uuid, $entity_type = 'block') {
    return $this->blazyManager->getEntityRepository()->loadEntityByUuid($entity_type, $uuid);
  }

  /**
   * Checks if the block is applicable for IO.
   *
   * @see io_block_presave()
   * @see self::blockFormAlter()
   */
  public function isBlockApplicable(BlockInterface $block) {
    // Excludes from Ultimenu which can ajaxify the entire region instead.
    $region = $block->getRegion();
    $settings = $block->get('settings');

    // Excludes crucial blocks, or those not worth being ajaxified.
    if (in_array($block->getPluginId(), $this->excludedBlockPluginIds())
      || (isset($settings['provider']) && in_array($settings['provider'], $this->excludedBlockProviders()))
      || ($region && strpos($region, 'ultimenu_') !== FALSE)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Checks if user has access to view IO block, including its path visibility.
   *
   * @see Drupal\io\Controller\IoBlockController
   */
  public function isAllowedBlock(BlockInterface $block) {
    $this->checkVisibilityConfig($block);
    $access = $block->access('view', $this->currentUser, TRUE);
    return $access->isAllowed();
  }

  /**
   * Checks IO block visibility config, and includes IO block route.
   */
  public function checkVisibilityConfig(BlockInterface &$block) {
    if ($block && $visibility_config = $block->getVisibility()) {
      if (isset($visibility_config['request_path']) && $request_path = $visibility_config['request_path']) {
        // Include our path into visibility unless sitewide or negated.
        if (!empty($request_path['pages']) && empty($request_path['negate'])) {
          $pages = "/io/block\r\n";
          $pages .= $request_path['pages'];
          $request_path['pages'] = $pages;
          $block->setVisibilityConfig('request_path', $request_path);
        }
      }
    }
  }

  /**
   * Checks if we have a valid view for IO pager.
   */
  public function isIoPager($view) {
    if ($view && $view->ajaxEnabled() && $view->getDisplay()->isPagerEnabled()) {
      $pager = $view->getPager();
      if ($pager && $pager instanceof IoPager) {
        return $pager;
      }
    }
    return FALSE;
  }

  /**
   * Excludes crucial blocks, or those not worth being ajaxified.
   */
  public function excludedBlockPluginIds() {
    $excludes = [
      'help_block',
      'local_tasks_block',
      'node_syndicate_block',
      'page_title_block',
      'search_form_block',
      'system_branding_block',
      'system_breadcrumb_block',
      'system_main_block',
      'system_messages_block',
      'user_login_block',
    ];

    $this->blazyManager->getModuleHandler()->alter('io_excluded_block_plugin_ids', $excludes);
    return array_unique($excludes);
  }

  /**
   * Excludes blocks by modules.
   */
  public function excludedBlockProviders() {
    $excludes = [
      'ultimenu',
      'jumper',
    ];

    $this->blazyManager->getModuleHandler()->alter('io_excluded_block_providers', $excludes);
    return array_unique($excludes);
  }

  /**
   * Returns IO settings.
   */
  public function getIoSettings($type = 'block') {
    $is_block = $type == 'block';
    return [
      // Place loader animation inside link element, suitable for replaceWith
      // method. If using html method, disable inside so the loader will be
      // placed after the triggering element.
      'inside'       => TRUE,
      'addNow'       => TRUE,
      'selector'     => $is_block ? '[data-io-block-trigger]' : '[data-io-pager-trigger]',
      'errorClass'   => $is_block ? 'io__error' : 'pager__error',
      'successClass' => $is_block ? 'io__loaded' : 'pager__loaded',
    ] + (array) $this->blazyManager->getIoSettings();
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  public function blockFormAlter(&$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\block\BlockInterface $block */
    $block = $form_state->getFormObject()->getEntity();
    if (!$this->isBlockApplicable($block)) {
      return;
    }

    // This will automatically be saved in the third party settings.
    $form['third_party_settings']['#tree'] = TRUE;
    $form['third_party_settings']['io']['lazyload'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Lazyload using Intersection Observer'),
      '#description'   => $this->t("Reasonable for non-essential or peripheral blocks below the fold, or at sidebars. Widgets like Facebook, Twitter, Google maps, or other third party, statistics, heavy-logic etc. are good candidates. Do not enable this for <a href=':url'>Ultimenu 2.x</a> regions as it is capable of ajaxifying the entire region instead. Check out more excluded blocks at <b>/admin/help/io</b>.", [':url' => 'https://drupal.org/project/ultimenu']),
      '#default_value' => $block->getThirdPartySetting('io', 'lazyload'),
    ];
  }

  /**
   * Implements hook_blazy_form_element_alter().
   */
  public function blazySettingsFormAlter(array &$form) {
    $settings = $this->blazyManager->configLoad();

    // Hooks into Blazy UI to support Blazy Filter.
    if (isset($settings['admin_css'])) {
      $form['extras']['#access'] = TRUE;
      $form['extras']['io_fallback'] = [
        '#type'          => 'textfield',
        '#title'         => $this->t('IO fallback'),
        '#default_value' => isset($settings['extras']['io_fallback']) ? $settings['extras']['io_fallback'] : '',
        '#description'   => $this->t('Text to display when an AJAX block fails loading its content. Default to: <b>Loading... Click here if it takes longer.</b>'),
      ];

      // Adds relevant IO AJAX description to existing Blazy IO options.
      $description = $form['io']['disconnect']['#description'];
      $form['io']['disconnect']['#description'] = $description . ' ' . $this->t('The same applies to IO ajaxified block and pager observers. The IO must stand-by and be able to watch the next/ subsequent AJAX results. No expensive methods executed on being stand-by. Each item will be unobserved once loaded, instead.');
    }
  }

  /**
   * Implements hook_preprocess_HOOK().
   */
  public function preprocessBlock(&$variables) {
    $uuid = $variables['elements']['#io'];

    // Replace the block content with a fallback, so that we can lazy load it.
    // In case the AJAX fails, the user has a link to load/ click it manually.
    $variables['content'] = [];
    $variables['attributes']['class'][] = 'block--io io';

    // Cannot use regular `use-ajax` class as we need to work out errors.
    $classes = ['io__lazy'];
    if (function_exists('ajaxin')) {
      $classes[] = 'io__loading';
    }
    else {
      $classes[] = 'is-b-loading';
      $variables['#attached']['library'][] = 'blazy/loading';
    }

    $variables['content']['io'] = [
      '#type' => 'link',
      '#title' => [
        '#markup' => '<span class="io__text">' . $this->getFallbackText() . '</span>',
        '#allowed_tags' => ['small', 'span', 'strong'],
      ],
      '#attributes' => [
        'class' => $classes,
        'data-io-block-trigger' => TRUE,
        'rel' => 'nofollow',
      ],
      '#url' => $this->getBlockUrl($uuid),
    ];
  }

  /**
   * Returns the block URL.
   */
  public function getBlockUrl($uuid) {
    return Url::fromRoute('io.block', ['ioid' => $uuid]);
  }

  /**
   * Return the fallback text.
   */
  public function getFallbackText() {
    return $this->t('@text', ['@text' => $this->blazyManager->configLoad('extras.io_fallback') ?: 'Loading... Click here if it takes longer.']);
  }

}
