<?php
global $civicrm_paths, $civicrm_setting;
$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);
$civicrm_paths['civicrm.vendor']['path'] = $vendorDir;
$civicrm_setting['domain']['userFrameworkResourceURL'] = '[cms.root]/libraries/civicrm/core';
