<?php

namespace Drupal\Tests\io\FunctionalJavascript;

/**
 * Tests Intersection Observer API to lazyload ajaxified blocks.
 *
 * @group io
 */
class IoBlockTest extends IoBlockTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable IO support.
    $this->container->get('config.factory')->getEditable('blazy.settings')->set('io.enabled', TRUE)->save();
    $this->container->get('config.factory')->clearStaticCache();
  }

  /**
   * Test IO Blocks by scrolling down the window.
   */
  public function testIoBlockAutoloadOnScroll() {
    parent::doIoBlockAutoloadOnScroll();
  }

  /**
   * Test IO Blocks by manually clicking the fallback links.
   */
  public function testIoBlockManualOnClicking() {
    parent::doIoBlockManualOnClicking();
  }

}
