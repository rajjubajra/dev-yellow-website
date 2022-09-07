<?php

namespace Drupal\Tests\io\FunctionalJavascript;

use Drupal\views\Entity\View;

/**
 * Tests Intersection Observer API lazyload ajaxified Views contents.
 *
 * @group io
 */
abstract class IoPagerTestBase extends IoTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'views',
    'node',
    'blazy',
    'blazy_test',
    'io',
  ];

  /**
   * The amount of items to load at once.
   *
   * @var int
   */
  private $itemsPerPage = 4;

  /**
   * Test IO Pager by scrolling down the window.
   */
  public function doIoPagerAutoloadOnScroll() {
    $this->createView('automatic-load', [
      'button_text' => 'Load more',
      'autoload' => TRUE,
    ]);

    $this->getSession()->resizeWindow(1200, 200);
    $this->drupalGet('automatic-load');
    $this->assertTotalNodes($this->itemsPerPage);
    $this->createScreenshot($this->testDirPath . '/pager_auto_1_io_initial.png');

    $this->getSession()->resizeWindow(1200, 600);
    $this->scrollTo(-1);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->wait(3000);
    $this->createScreenshot($this->testDirPath . '/pager_auto_2_io_done.png');
    $this->assertTotalNodes($this->itemsPerPage * 2);
  }

  /**
   * Test IO Pager by manually clicking the fallback link.
   */
  public function doIoPagerManualOnClicking() {
    $this->createView('click-to-load', [
      'button_text' => 'Load more',
      'autoload' => FALSE,
    ]);

    $this->drupalGet('click-to-load');
    $this->assertTotalNodes($this->itemsPerPage);
    $this->createScreenshot($this->testDirPath . '/pager_manual_1_io_initial.png');

    $this->getSession()->getPage()->clickLink('Load more');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertTotalNodes($this->itemsPerPage * 2);
    $this->getSession()->resizeWindow(1200, 1200);
    $this->createScreenshot($this->testDirPath . '/pager_manual_2_io_loaded.png');
  }

  /**
   * Assert how many nodes appear on the page.
   *
   * @param int $total
   *   The expected total nodes on the page.
   */
  protected function assertTotalNodes($total) {
    $this->assertEquals($total, count($this->getSession()->getPage()->findAll('css', '.node')));
  }

  /**
   * Create a view setup for testing Io.
   *
   * @param string $path
   *   The path for the view.
   * @param array $settings
   *   The IO settings.
   */
  protected function createView($path, array $settings) {
    View::create([
      'label' => 'IO Test',
      'id' => $this->randomMachineName(),
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'row' => [
              'type' => 'entity:node',
              'options' => [
                'view_mode' => 'teaser',
              ],
            ],
            'pager' => [
              'type' => $this->testPluginId,
              'options' => [
                'items_per_page' => $this->itemsPerPage,
                'offset' => 0,
                'io' => $settings,
              ],
            ],
            'use_ajax' => TRUE,
          ],
        ],
        'page_1' => [
          'display_plugin' => 'page',
          'id' => 'page_1',
          'display_options' => [
            'path' => $path,
          ],
        ],
      ],
    ])->save();
    \Drupal::service('router.builder')->rebuild();
  }

}
