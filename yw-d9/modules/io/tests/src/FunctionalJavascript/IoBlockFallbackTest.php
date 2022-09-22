<?php

namespace Drupal\Tests\io\FunctionalJavascript;

/**
 * Tests IO API to lazyload blocks using bLazy library as fallback.
 *
 * @group io
 */
class IoBlockFallbackTest extends IoBlockTestBase {

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
