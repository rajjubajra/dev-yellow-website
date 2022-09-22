<?php

namespace Drupal\minifyjs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Manage files form class.
 *
 * Displays a list of detected javascript files and allows actions to be
 * performed on them.
 */
class ManageFilesForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $files = \Drupal::service('minifyjs')->loadAllFiles();
    $form = [];

    // Statistics.
    $number_of_files = 0;
    $minified_files = 0;
    $unminified_size = 0;
    $minified_size = 0;
    $saved_size = 0;

    // Get search query.
    $session = \Drupal::service('tempstore.private')->get('minifyjs');
    $query = $session->get('query');

    // Filter the files based on query.
    if ($query) {
      $new_files = [];
      foreach ($files as $fid => $file) {
        if (stripos($file->uri, $query) !== FALSE) {
          $new_files[$fid] = $file;
        }
      }
      $files = $new_files;
    }

    // Pager init.
    $limit = 100;
    $start = 0;
    if (isset($_REQUEST['page'])) {
      $start = $_REQUEST['page'] * $limit;
    }
    $total = count($files);
    \Drupal::service('pager.manager')->createPager($total, $limit);

    // Build the rows of the table.
    $rows = [];
    if ($total) {

      // Statistics for all files.
      foreach ($files as $fid => $file) {
        $number_of_files++;
        $unminified_size += $file->size;
        $minified_size += $file->minified_size;
        if ($file->minified_uri) {
          $saved_size += $file->size - $file->minified_size;
          $minified_files++;
        }
      }

      // Build table rows.
      $files_subset = array_slice($files, $start, $limit, TRUE);
      foreach ($files_subset as $fid => $file) {
        $operations = ['#type' => 'operations', '#links' => $this->operations($file)];

        $rows[$fid] = [
          Link::fromTextAndUrl($file->uri, Url::fromUri('base:' . $file->uri, ['attributes' => ['target' => '_blank']])),
          date('Y-m-d', $file->modified),
          $this->formatFilesize($file->size),
          $this->minifiedFilesize($file),
          $this->precentage($file),
          $this->minifiedDate($file),
          $this->minifiedFile($file),
          \Drupal::service('renderer')->render($operations),
        ];
      }
    }

    // Report on statistics.
    \Drupal::messenger()->addMessage(
      t(
        '@files javascript files (@min_files minified). The size of all original files is @size and the size of all of the minified files is @minified for a savings of @diff (@percent% smaller overall)',
        [
          '@files' => $number_of_files,
          '@min_files' => $minified_files,
          '@size' => $this->formatFilesize($unminified_size),
          '@minified' => ($minified_size) ? $this->formatFilesize($minified_size) : 0,
          '@diff' => ($minified_size) ? $this->formatFilesize($saved_size) : 0,
          '@percent' => ($minified_size) ? round($saved_size / $unminified_size * 100, 2) : 0,
        ]
      ),
      'status'
    );

    $form['search'] = [
      '#type' => 'container',
      '#attributes' => ['class' => 'container-inline'],
    ];
    $form['search']['query'] = [
      '#type' => 'textfield',
      '#title' => t('Search'),
      '#title_display' => 'hidden',
      '#default_value' => $query,
    ];
    $form['search']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
      '#submit' => [[$this, 'filterList']],
    ];
    if ($query) {
      $form['search']['reset'] = [
        '#type' => 'submit',
        '#value' => t('Reset'),
        '#submit' => [[$this, 'filterListReset']],
      ];
    }

    // The table.
    $form['files'] = [
      '#type' => 'tableselect',
      '#header' => [
        t('Original File'),
        t('Last Modified'),
        t('Original Size'),
        t('Minified Size'),
        t('Savings'),
        t('Last Minified'),
        t('Minified File'),
        t('Operations'),
      ],
      '#options' => $rows,
      '#empty' => t('No files have been found. Please scan using the action link above.'),
    ];

    $form['pager'] = ['#type' => 'pager'];

    // Bulk minify button.
    if ($total) {
      $form['actions'] = [
        '#type'  => 'container',
        '#attributes' => [
          'class' => ['container-inline'],
        ],
      ];
      $form['actions']['action'] = [
        '#type' => 'select',
        '#options' => [
          'minify' => t('Minify (and re-minify)'),
          'minify_skip' => t('Minify (and skip minified)'),
          'restore' => t('Restore'),
        ],
      ];
      $form['actions']['scope'] = [
        '#type' => 'select',
        '#options' => [
          'selected' => t('Selected files'),
          'all' => t('All files'),
        ],
      ];
      $form['actions']['go'] = [
        '#type' => 'submit',
        '#value' => t('Perform action'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'minifyjs_manage_files';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (count($form_state->getValue('files'))) {
      $files = \Drupal::service('minifyjs')->loadAllFiles();

      // Get the files to process.
      $selected_files = [];
      if ($form_state->getValue('scope') == 'selected') {
        foreach ($form_state->getValue('files') as $fid => $selected) {
          if ($selected) {
            $selected_files[] = $fid;
          }
        }
      }
      else {
        $selected_files = array_keys($files);
      }

      // Build operations.
      $operations = [];
      foreach ($selected_files as $fid) {
        switch ($form_state->getValue('action')) {

          // Minify all files.
          case 'minify':
            $operations[] = ['minifyjs_batch_minify_file_operation', [$fid]];
            break;

          // Minify files that have not yet been minified.
          case 'minify_skip':
            $file = $files[$fid];
            if (!$file->minified_uri) {
              $operations[] = ['minifyjs_batch_minify_file_operation', [$fid]];
            }
            break;

          // Restore un-minified version of a file.
          case 'restore':
            $operations[] = ['minifyjs_batch_remove_minified_file_operation', [$fid]];
            break;
        }
      }

      // Build the batch.
      $batch = [
        'operations' => $operations,
        'file' => drupal_get_path('module', 'minifyjs') . '/minifyjs.module',
        'error_message' => t('There was an unexpected error while processing the batch.'),
        'finished' => 'minifyjs_batch_finished',
      ];
      switch ($form_state->getValue('action')) {
        case 'minify':
          $batch['title'] = t('Minifying Javascript Files.');
          $batch['init_message'] = t('Initializing minify javascript files batch.');
          break;

        case 'restore':
          $batch['title'] = t('Restoring Un-Minified Javascript Files.');
          $batch['init_message'] = t('Initializing restore un-minified javascript files batch.');
          break;

      }

      // Start the batch.
      batch_set($batch);
    }
  }

  /**
   * Filter list submit callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function filterList(array &$form, FormStateInterface $form_state) {
    $session = \Drupal::service('tempstore.private')->get('minifyjs');
    $session->set('query', $form_state->getValue('query'));
  }

  /**
   * Filter list reset submit callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function filterListReset(array &$form, FormStateInterface $form_state) {
    $session = \Drupal::service('tempstore.private')->get('minifyjs');
    $session->set('query', NULL);
  }

  /**
   * Helper function to format the filesize.
   *
   * @param int $size
   *   The size in bytes.
   *
   * @return string|int
   *   The converted size string or 0.
   */
  private function formatFilesize($size) {
    if ($size) {
      $suffixes = ['', 'k', 'M', 'G', 'T'];
      $base = log($size) / log(1024);
      $base_floor = floor($base);

      return round(pow(1024, $base - $base_floor), 2) . $suffixes[$base_floor];
    }

    return 0;
  }

  /**
   * Helper function to format date.
   *
   * @param object $file
   *   The file that has the date to be formatted.
   *
   * @return string
   *   The formatted date.
   */
  private function minifiedDate($file) {
    if ($file->minified_modified > 0) {
      return date('Y-m-d', $file->minified_modified);
    }

    return '-';
  }

  /**
   * Helper function to format the minified filesize.
   *
   * @param object $file
   *   The file that has the filesize to format.
   *
   * @return string|int
   *   The formatted filesize or 0.
   */
  private function minifiedFilesize($file) {
    if ($file->minified_uri) {
      if ($file->minified_size > 0) {
        return $this->formatFilesize($file->minified_size);
      }

      return 0;
    }

    return '-';
  }

  /**
   * Helper function to format the file url.
   *
   * @param object $file
   *   The file to return the URL for.
   *
   * @return string
   *   The URL.
   */
  private function minifiedFile($file) {
    if (!empty($file->minified_uri)) {
      return Link::fromTextAndUrl(basename($file->minified_uri), Url::fromUri(file_create_url($file->minified_uri), ['attributes' => ['target' => '_blank']]));
    }

    return '-';
  }

  /**
   * Helper function to format the savings percentage.
   *
   * @param object $file
   *   The file to generate the percentage for.
   *
   * @return string
   *   The percentage value.
   */
  private function precentage($file) {
    if ($file->minified_uri) {
      if ($file->minified_size > 0) {
        return round(($file->size - $file->minified_size) / $file->size * 100, 2) . '%';
      }

      return 0 . '%';
    }

    return '-';
  }

  /**
   * Helper function to return the operations available for the file.
   *
   * @param object $file
   *   The file to generate operations for.
   *
   * @return array
   *   The list of operations.
   */
  private function operations($file) {
    $operations = [];

    if (empty($file->minified_uri)) {
      $operations['minify'] = [
        'title' => t('Minify'),
        'url' => Url::fromUri('base:/admin/config/development/performance/js/' . $file->fid . '/minify'),
      ];
    }
    else {
      $operations['reminify'] = [
        'title' => t('Re-Minify'),
        'url' => Url::fromUri('base:/admin/config/development/performance/js/' . $file->fid . '/minify'),
      ];
      $operations['restore'] = [
        'title' => t('Restore'),
        'url' => Url::fromUri('base:/admin/config/development/performance/js/' . $file->fid . '/restore'),
      ];
    }

    return $operations;
  }

}
