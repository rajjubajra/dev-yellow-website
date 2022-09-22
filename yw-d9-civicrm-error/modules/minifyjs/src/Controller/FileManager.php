<?php

namespace Drupal\minifyjs\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for minifyjs routes.
 */
class FileManager extends ControllerBase {

  /**
   * Minify a single file.
   *
   * @param object $file
   *   The file to minify.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the manage javascript page.
   */
  public function minify($file) {
    \Drupal::service('minifyjs')->minify($file);
    return $this->redirect('minifyjs.manage');
  }

  /**
   * Remove the minified version of a single file (restore it).
   *
   * @param object $file
   *   The file to restore.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the manage javascript page.
   */
  public function restore($file) {
    \Drupal::service('minifyjs')->restore($file);
    return $this->redirect('minifyjs.manage');
  }

  /**
   * Scans the system for javascript.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns a redirect to the manage javascript page.
   */
  public function scan() {
    \Drupal::service('minifyjs')->scan();
    return $this->redirect('minifyjs.manage');
  }

}
