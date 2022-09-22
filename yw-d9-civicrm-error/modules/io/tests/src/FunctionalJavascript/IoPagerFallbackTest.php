<?php

namespace Drupal\Tests\io\FunctionalJavascript;

/**
 * Tests IO API lazyload Views contents using bLazy library as fallback.
 *
 * @group io
 */
class IoPagerFallbackTest extends IoPagerTestBase {

  /**
   * Test IO Pager by scrolling down the window.
   */
  public function testIoPagerFallbackAutoloadOnScroll() {
    parent::doIoPagerAutoloadOnScroll();
  }

  /**
   * Test IO Pager by manually clicking the fallback link.
   */
  public function testIoPagerFallbackManualOnClicking() {
    parent::doIoPagerManualOnClicking();
  }

}
