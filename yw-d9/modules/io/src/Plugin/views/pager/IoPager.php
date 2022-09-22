<?php

namespace Drupal\io\Plugin\views\pager;

use Drupal\views\Plugin\views\pager\SqlBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Views pager plugin to handle infinite scrolling using Intersection Observer.
 *
 * @ingroup views_pager_plugins
 *
 * @ViewsPager(
 *   id = "io",
 *   title = @Translation("Intersection Observer"),
 *   help = @Translation("Provides AJAX load more using Intersection Observer."),
 *   theme = "io_pager",
 * )
 */
class IoPager extends SqlBase {

  /**
   * The default selector for views content.
   */
  const CONTENT_SELECTOR = '[data-io-pager]';

  /**
   * The default selector for view pager.
   */
  const PAGER_SELECTOR = '.pager--io';

  /**
   * {@inheritdoc}
   */
  public function render($input) {
    return [
      '#theme' => $this->themeFunctions(),
      '#options' => $this->options['io'],
      '#element' => $this->options['id'],
      '#parameters' => $input,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['io'] = [
      'contains' => [
        'autoload' => ['default' => FALSE],
        'button_text' => ['default' => $this->t('Load more')],
        'end_text' => ['default' => ''],
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $action = $this->options['io']['autoload'] ? $this->t('Automatically load content') : $this->t('Click to load');
    return $this->formatPlural(
      $this->options['items_per_page'],
      '@action, @count item',
      '@action, @count items',
      [
        '@action' => $action,
        '@count' => $this->options['items_per_page'],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $form['tags']['#access'] = FALSE;
    $options = $this->options['io'];

    $form['io'] = [
      '#title' => $this->t('Intersection Observer options'),
      '#description' => $this->t('Note: Requires the <em>Use AJAX</em> setting for this views display.'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#weight' => -100,
      'autoload' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Automatically load content'),
        '#description' => $this->t('Automatically load subsequent pages when scrolling down the window/ screen.'),
        '#default_value' => $options['autoload'],
      ],
      'button_text' => [
        '#type' => 'textfield',
        '#title' => $this->t('Button text'),
        '#default_value' => $options['button_text'],
      ],
      'end_text' => [
        '#type' => 'textfield',
        '#title' => $this->t('End text'),
        '#default_value' => $options['end_text'],
      ],
    ];
  }

}
