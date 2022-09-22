<?php

namespace Drupal\minifyjs;

use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Path\PathMatcher;
use Drupal\Core\ProxyClass\File\MimeType\MimeTypeGuesser;
use Drupal\file\Entity\File;
use Drupal\file\FileUsage\DatabaseFileUsageBackend;
use Patchwork\JSqueeze;

/**
 * Minify JS Service.
 */
class MinifyJs {

  /**
   * The file_system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * The file.usage service.
   *
   * @var \Drupal\file\FileUsage\DatabaseFileUsageBackend
   */
  protected $fileUsage;

  /**
   * The patch.matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcher
   */
  protected $pathMatcher;

  /**
   * The file.mime_type.guesser service.
   *
   * @var \Drupal\Core\File\MimeType\MimeTypeGuesser
   */
  protected $mimeTypeGuesser;

  /**
   * Create the MinifyJs Service.
   *
   * @param \Drupal\Core\Path\PathMatcher $pathMatcher
   *   The path.matcher service.
   * @param \Drupal\Core\File\MimeType\MimeTypeGuesser $mimeTypeGuesser
   *   The file.mime_type.guesser service.
   * @param \Drupal\file\FileUsage\DatabaseFileUsageBackend $fileUsage
   *   The file.usage service.
   * @param \Drupal\Core\File\FileSystem $fileSystem
   *   The file_system service.
   */
  public function __construct(PathMatcher $pathMatcher, MimeTypeGuesser $mimeTypeGuesser, DatabaseFileUsageBackend $fileUsage, FileSystem $fileSystem) {
    $this->pathMatcher = $pathMatcher;
    $this->mimeTypeGuesser = $mimeTypeGuesser;
    $this->fileUsage = $fileUsage;
    $this->fileSystem = $fileSystem;
  }

  /**
   * Minify a single file.
   *
   * @param object $file
   *   The file to minify.
   */
  public function minify($file) {
    $result = $this->minifyFile($file, TRUE);

    if ($result === TRUE) {
      \Drupal::messenger()->addMessage(t('File was minified successfully.'));
    }
    else {
      \Drupal::messenger()->addMessage($result, 'error');
    }
  }

  /**
   * Remove the minified version of a single file (restore it).
   *
   * @param object $file
   *   The file to restore.
   */
  public function restore($file) {
    $result = $this->removeMinifiedFile($file, TRUE);

    if ($result === TRUE) {
      \Drupal::messenger()->addMessage(t('File was restored successfully.'));
    }
    else {
      \Drupal::messenger()->addMessage($result, 'error');
    }
  }

  /**
   * Scan for files.
   *
   * Recursively scan the entire doc tree looking for JS files, ignoring based
   * on the exclusion list.
   */
  public function scan() {

    // Recursive scan of the entire doc root to find .js files. Include
    // minified files as well so they can be re-minified (comments removed).
    $directory = new \RecursiveDirectoryIterator(DRUPAL_ROOT);
    $iterator = new \RecursiveIteratorIterator($directory);
    $regex = new \RegexIterator($iterator, '/\.js$/i');

    // Process files.
    $new_files = [];
    $old_files = [];
    $changed_files = [];
    $existing = $this->loadAllFiles();
    $exclusions = \Drupal::config('minifyjs.config')->get('exclusion_list');

    foreach ($regex as $info) {
      $new_absolute = $info->getPathname();
      $new_relative = str_replace(DRUPAL_ROOT . DIRECTORY_SEPARATOR, '', $new_absolute);

      // Skip exclusions.
      if ($this->pathMatcher->matchPath($new_relative, $exclusions)) {
        continue;
      }

      // Loop existing and see if it already exists from previous scans.
      $exists = FALSE;
      foreach ($existing as $file) {
        if ($file->uri == $new_relative) {

          // See if the size and modified time differ from the last time the
          // scan checked this file. If the file has changed (based on those
          // two pieces of data), mark the minified version for removal if a
          // minified version of the file exists.
          if (!empty($file->minified_uri)) {
            $size = filesize($new_absolute);
            $modified = filemtime($new_absolute);
            if ($size != $file->size || $modified != $file->modified) {
              $changed_files[$new_relative] = $file;
            }
          }
          $exists = TRUE;
          $old_files[$new_relative] = TRUE;
          break;
        }
      }

      // File not found in the existing array, so it's new.
      if (!$exists) {
        $new_files[$new_absolute] = TRUE;
      }
    }

    // Build a list of files that currently exist in the minifyjs_file table but
    // no longer exist in the file system. These files should be removed.
    foreach ($existing as $file) {
      if (!isset($old_files[$file->uri])) {
        $this->removeFile($file->uri);
      }
    }

    // Remove changed files.
    foreach ($changed_files as $file_uri => $file) {
      $this->removeFile($file->uri);
      $new_files[$file_uri] = TRUE;
      \Drupal::messenger()->addMessage(t('Original file %file has been modified and was restored.', ['%file' => $file_uri]));
    }

    // Add all new files to the database.
    foreach ($new_files as $file => $junk) {
      \Drupal::database()->insert('minifyjs_file')
        ->fields(
          [
            'uri' => str_replace(DRUPAL_ROOT . DIRECTORY_SEPARATOR, '', $file),
            'size' => filesize($file),
            'modified' => filemtime($file),
          ]
        )
        ->execute();
    }

    // Clear the cache so all of these new files will be picked up.
    \Drupal::cache()->delete(MINIFYJS_CACHE_CID);
  }

  /**
   * Load all files.
   *
   * Load all of the minifyjs_file records from cache or directly from the
   * database.
   *
   * @return array
   *   The list of files.
   */
  public function loadAllFiles() {

    // Load files from cache.
    if ($cache = \Drupal::cache()->get(MINIFYJS_CACHE_CID)) {
      return $cache->data;
    }

    // Re-build cache.
    $result = \Drupal::database()->select('minifyjs_file', 'f')
      ->fields('f')
      ->orderBy('uri')
      ->execute();

    $exclusions = \Drupal::config('minifyjs.config')->get('exclusion_list');

    $files = [];
    while ($file = $result->fetchObject()) {

      // Ignore the exclusions.
      if (!$this->pathMatcher->matchPath($file->uri, $exclusions)) {
        $files[$file->fid] = $file;
      }
    }

    // Cache for 1 day.
    \Drupal::cache()->set(MINIFYJS_CACHE_CID, $files, strtotime('+1 day', \Drupal::time()->getRequestTime()));

    return $files;
  }

  /**
   * Minify File.
   *
   * Helper function that sends the JS off to be minified, handles the response,
   * stores the file in the filesystem and stores the file info in the managed
   * file tables.
   *
   * @param int $fid
   *   The file ID of the file to minify.
   * @param bool $reset
   *   Reset the cache or not.
   *
   * @return mixed
   *   Success of a translated string.
   */
  public function minifyFile($fid, $reset = FALSE) {

    // Load the file by fid.
    $files = $this->loadAllFiles();
    $file = $files[$fid];
    $js = file_get_contents(DRUPAL_ROOT . DIRECTORY_SEPARATOR . $file->uri);

    // Minify the JS, if it has a length. 0 byte files should pass by the
    // minification process.
    $minified = $js;
    if (strlen($js)) {
      $minifier = new JSqueeze();
      $minified = $minifier->squeeze($js, TRUE, FALSE);
    }

    // Create the directory tree if it doesn't exist.
    $minifyjs_folder = 'public://minifyjs/' . dirname($file->uri);
    $result = $this->fileSystem->prepareDirectory($minifyjs_folder, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    // Save the file first to the temp folder and then copy to the
    // public filesystem.
    $file_name = str_replace('.js', '.min.js', basename($file->uri));
    $tmp_file = $this->fileSystem->getTempDirectory() . DIRECTORY_SEPARATOR . $file_name;
    $file_uri = $minifyjs_folder . DIRECTORY_SEPARATOR . $file_name;
    if (file_put_contents($tmp_file, $minified) !== FALSE) {
      if (copy($tmp_file, $file_uri)) {

        // Save the file in the managed file table.
        if (empty($file->minified_uri)) {
          $file = File::create(
            [
              'uid' => \Drupal::currentUser()->id(),
              'uri' => $file_uri,
              'filename' => $file_name,
              'filemime' => $this->mimeTypeGuesser->guess($file->uri),
              'status' => FILE_STATUS_PERMANENT,
            ]
          );
          $file->save();
          $this->fileUsage->add($file, 'minifyjs', 'node', 1);
        }

        $filesize = filesize($file_uri);

        // Update the minifyjs table.
        \Drupal::database()->update('minifyjs_file')
          ->fields(
            [
              'minified_uri' => $file_uri,
              'minified_size' => ($filesize) ? $filesize : 0,
              'minified_modified' => \Drupal::time()->getRequestTime(),
            ]
          )
          ->condition('fid', $fid)
          ->execute();

        // Clean up temp folder.
        unlink($tmp_file);

        // Clear the cache so this change will be reflected in
        // loadAllFiles().
        if ($reset) {
          \Drupal::cache()->delete(MINIFYJS_CACHE_CID);
        }

        return TRUE;
      }
      else {
        return t('Could not copy the file from the %tmp folder.', ['%tmp' => $this->fileSystem->getTempDirectory()]);
      }
    }
    else {
      return t('Could not save the file - %file', ['%file' => $this->fileSystem->getTempDirectory() . DIRECTORY_SEPARATOR . $file_name]);
    }
  }

  /**
   * Remove minified file.
   *
   * Helper function removes the file, the entry in the file_managed table and
   * sets the file status as unminified.
   *
   * @param int $fid
   *   The file id of the file remove.
   * @param bool $reset
   *   Reset the cache or not.
   *
   * @return mixed
   *   Success of a translated string.
   */
  public function removeMinifiedFile($fid, $reset = FALSE) {

    // Get minified uri from the minifyjs_file table.
    $query = \Drupal::database()->select('minifyjs_file', 'm')
      ->fields('m', ['minified_uri'])
      ->condition('m.fid', $fid);

    // Make sure that it exists.
    if ($query->countQuery()->execute()->fetchField() > 0) {
      $file = $query->execute()->fetchObject();

      // Get the fid of the minified table.
      $query = \Drupal::database()->select('file_managed', 'f')
        ->fields('f', ['fid'])
        ->condition('f.uri', $file->minified_uri);

      // Make sure that it exists.
      if ($query->countQuery()->execute()->fetchField() > 0) {
        $file = $query->execute()->fetchObject();

        // Remove the file from the file_managed table.
        $file = File::load($file->fid);
        $file->delete();

        // Set the file status to non-minfied.
        \Drupal::database()->update('minifyjs_file')
          ->fields(
            [
              'minified_uri' => '',
              'minified_size' => 0,
              'minified_modified' => 0,
            ]
          )
          ->condition('fid', $fid)
          ->execute();

        // Clear the cache so this change will be reflected in
        // loadAllFiles().
        if ($reset) {
          \Drupal::cache()->delete(MINIFYJS_CACHE_CID);
        }

        return TRUE;
      }
    }

    return t('File not found. Check that the file ID is correct.');
  }

  /**
   * Remove a file.
   *
   * Helper function removes the file, the entry in the file_managed table and
   * the entry in the minifyjs_file.
   *
   * @param string $file_uri
   *   The URI of the file to remove.
   *
   * @return bool
   *   The success of the operation.
   */
  protected function removeFile($file_uri) {

    // Get the fid and minified uri of the file.
    $query = \Drupal::database()->select('minifyjs_file', 'm')
      ->fields('m', ['fid', 'minified_uri'])
      ->condition('m.uri', $file_uri);

    // Make sure that it exists.
    if ($query->countQuery()->execute()->fetchField() > 0) {
      $file = $query->execute()->fetchObject();

      // Handle the minified file, if applicable.
      if (!empty($file->minified_uri)) {

        // Get the fid of the minified file.
        $query = \Drupal::database()->select('file_managed', 'f')
          ->fields('f', ['fid'])
          ->condition('f.uri', $file->minified_uri);
        if ($query->countQuery()->execute()->fetchField() > 0) {
          $minified_file = $query->execute()->fetchObject();

          // Remove the file from the file_managed table.
          $minified_file = File::load($minified_file->fid);
          $minified_file->delete();
        }
      }

      // Remove the file from minifyjs_file table.
      \Drupal::database()->delete('minifyjs_file')
        ->condition('fid', $file->fid)
        ->execute();

      return TRUE;
    }
  }

}
