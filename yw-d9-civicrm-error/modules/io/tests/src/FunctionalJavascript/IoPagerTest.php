<?php

namespace Drupal\Tests\io\FunctionalJavascript;

/**
 * Tests Intersection Observer API lazyload ajaxified Views contents.
 *
 * @group io
 */
class IoPagerTest extends IoPagerTestBase {

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
   * Test IO Pager by scrolling down the window.
   */
  public function testIoPagerAutoloadOnScroll() {
    parent::doIoPagerAutoloadOnScroll();
  }

  /**
   * Test IO Pager by manually clicking the fallback link.
   */
  public function testIoPagerManualOnClicking() {
    parent::doIoPagerManualOnClicking();
  }

}
