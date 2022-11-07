<?php

namespace Drupal\Tests\io\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Core\File\FileSystemInterface;

/**
 * Test Intersection Observer API.
 *
 * @group io
 */
abstract class IoTestBase extends WebDriverTestBase {

  use NodeCreationTrait;
  use ContentTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'node',
    'blazy',
    'blazy_test',
    'io',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'bartik';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->testNodeType = 'page';
    $this->testPluginId = 'io';
    $this->testCountNodes = 12;
    $this->root = $this->container->getParameter('app.root');
    $this->fileSystem = $this->container->get('file_system');
    $this->ioManager = $this->container->get('io.manager');

    foreach (['views', 'io'] as $module) {
      $this->container->get('config.installer')->installDefaultConfig('module', $module);
    }

    $this->createContentType([
      'type' => $this->testNodeType,
    ]);

    $this->prepareTestDirectory();

    // Make nodes available for both IO pager and block tests.
    foreach (range(0, $this->testCountNodes) as $i) {
      $this->createNode([
        'status' => TRUE,
        'type' => $this->testNodeType,
        'body' => [
          [
            'value' => $this->getRandomGenerator()->paragraphs(6),
            'format' => filter_default_format(),
          ],
        ],
      ]);
    }
  }

  /**
   * Scroll to a pixel offset, or window bottom if -1.
   *
   * @param int $pixels
   *   The pixel offset to scroll to.
   */
  protected function scrollTo($pixels) {
    $pixels = $pixels == -1 ? 'document.body.scrollHeight' : $pixels;
    $this->getSession()->executeScript("window.scrollTo(0, $pixels);");
  }

  /**
   * Prepares directory to store captured test outputs.
   */
  protected function prepareTestDirectory() {
    $this->testDirPath = $this->root . '/sites/default/files/simpletest/' . $this->testPluginId;
    // Compatibility for 8.7+.
    if (isset($this->fileSystem) && method_exists($this->fileSystem, 'prepareDirectory')) {
      $this->fileSystem->prepareDirectory($this->testDirPath, FileSystemInterface::CREATE_DIRECTORY);
    }
  }

}
