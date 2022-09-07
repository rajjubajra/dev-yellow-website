<?php

namespace Drupal\Tests\io\FunctionalJavascript;

/**
 * Tests Intersection Observer API to lazyload ajaxified blocks.
 *
 * @group io
 */
abstract class IoBlockTestBase extends IoTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'views',
    'node',
    'blazy',
    'blazy_test',
    'io',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->ioManager = $this->container->get('io.manager');

    $this->container->get('theme_installer')->install(['classy', 'bartik']);
    $this->container->get('config.factory')->getEditable('system.theme')->set('default', 'bartik')->save();

    // Place ajaxified blocks into region content.
    $settings = ['region' => 'content', 'weight' => 10];

    foreach ($this->getAjaxifiedBlocks() as $block_id) {
      $block = $this->drupalPlaceBlock($block_id, $settings);
      $block->setThirdPartySetting('io', 'lazyload', TRUE);
      $block->save();
    }
  }

  /**
   * Test IO Blocks by scrolling down the window.
   */
  public function doIoBlockAutoloadOnScroll() {
    $this->drupalGet('node/1');

    $this->getSession()->resizeWindow(1200, 200);

    // Ensures the markup is added to the blocks.
    $this->assertSession()->elementExists('css', '.io__lazy');

    // Ensures the fallback link to manually load AJAX is available.
    $this->assertSession()->elementExists('css', '[data-io-block-trigger]');

    // Creates a screenshot.
    $this->createScreenshot($this->testDirPath . '/block_auto_1_io_initial.png');

    // Scrolls down the window to trigger IO AJAX.
    $this->getSession()->resizeWindow(1200, 1200);
    $this->scrollTo(-1);
    foreach ($this->getAjaxifiedBlocks() as $key) {
      $this->assertSession()->assertWaitOnAjaxRequest();

      // Ensures the fallbacks are gone, replaced by the IO lazyloaded blocks.
      $this->assertSession()->elementExists('css', '[data-io-block-loaded]');

      // Suppress chatty coder.
      $this->assertEquals(TRUE, !empty($key));
    }

    $this->createScreenshot($this->testDirPath . '/block_auto_2_io_done.png');
  }

  /**
   * Test IO Blocks by manually clicking the fallback links.
   */
  public function doIoBlockManualOnClicking() {
    $this->drupalGet('node/1');

    $this->getSession()->resizeWindow(1200, 1200);

    // Ensures the markup is added to the blocks.
    $this->assertSession()->elementExists('css', '.io__lazy');

    // Ensures the fallback link to manually load AJAX is available.
    $this->assertSession()->elementExists('css', '[data-io-block-trigger]');

    // Creates a screenshot.
    $this->createScreenshot($this->testDirPath . '/block_manual_1_io_initial.png');

    // Manuallly click the fallback link.
    $this->getSession()->getPage()->clickLink($this->ioManager->getFallbackText());

    // Wait a moment.
    foreach ($this->getAjaxifiedBlocks() as $key) {
      $this->assertSession()->assertWaitOnAjaxRequest();
      // Ensures the fallbacks are gone, replaced by the IO lazyloaded blocks.
      $this->assertSession()->elementExists('css', '[data-io-block-loaded]');

      // Suppress chatty coder.
      $this->assertEquals(TRUE, !empty($key));
    }

    $this->createScreenshot($this->testDirPath . '/block_manual_2_io_done.png');
  }

  /**
   * Returns ajaxified block IDs.
   */
  protected function getAjaxifiedBlocks() {
    return [
      'views_block:who_s_online-who_s_online_block',
      'system_powered_by_block',
      'views_block:content_recent-block_1',
    ];
  }

}
