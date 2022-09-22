<?php

namespace Drupal\minifyjs\Commands;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\minifyjs\MinifyJs;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class MinifyJsCommands extends DrushCommands {

  /**
   * The cache.default service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The minifyjs service.
   *
   * @var \Drupal\minifyjs\MinifyJs
   */
  protected $minifyJs;

  /**
   * MinifyJsCommands constructor.
   *
   * @param \Drupal\minifyjs\MinifyJs $minifyJs
   *   The minifyjs service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache.default service.
   */
  public function __construct(MinifyJs $minifyJs, CacheBackendInterface $cache) {
    $this->minifyJs = $minifyJs;
    $this->cache = $cache;
  }

  /**
   * All js files minification.
   *
   * @usage drush minify-js
   *   Js files minification.
   *
   * @command minify-js
   * @aliases minifyjs
   */
  public function minifyJs() {
    $this->output()->writeln('Minifying all JS files...');
    $files = $this->minifyJs->loadAllFiles();
    foreach ($files as $fid => $file) {
      $status = $this->minifyJs->minifyFile($fid);

      // Only output error messages.
      if ($status !== TRUE) {
        $this->output()->writeln($status);
      }
    }

    $this->cache->delete(MINIFYJS_CACHE_CID);

    $this->output()->writeln('Complete!');
  }

  /**
   * Minify all JS files that are not currently minified.
   *
   * @usage drush minify-js-skip
   *   Js files minification.
   *
   * @command minify-js-skip
   * @aliases minifyjslite
   */
  public function minifyJsSkip() {
    $this->output()->writeln('Minifying all JS files not currently minified...');
    $files = $this->minifyJs->loadAllFiles();
    foreach ($files as $fid => $file) {
      if (!empty($file->minified_uri)) {
        $status = $this->minifyJs->minifyFile($fid);

        // Only output error messages.
        if ($status !== TRUE) {
          $this->output()->writeln($status);
        }
      }
    }

    $this->cache->delete(MINIFYJS_CACHE_CID);

    $this->output()->writeln('Complete!');
  }

  /**
   * Drush command to find all JS files.
   *
   * @usage drush scan-js
   *   Drush command to find all JS files.
   *
   * @command scan-js
   * @aliases scanjs
   */
  public function scanJs() {
    $this->output()->writeln('Scanning for JS files...');
    $this->minifyJs->scan();
    $this->output()->writeln('Complete!');
  }

}
