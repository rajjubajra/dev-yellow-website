<?php

/**
 * @file
 * Post update hooks for IO.
 */

/**
 * Clear cache to re-regenerate JS files.
 */
function io_post_update_jquery_once_to_vanilla_once() {
  // Empty hook to trigger cache clear.
}

/**
 * Clear cache to downgrade to D8.8, and re-regenerate JS files.
 */
function io_post_update_downgrade_from_d9_2() {
  // Empty hook to trigger cache clear.
}
