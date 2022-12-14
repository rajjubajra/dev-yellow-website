<?php

/**
 * @file
 * User related hooks and helper functions.
 */

use Drupal\Core\Session\AccountInterface;

/**
 * Implements hook_user_login().
 */
function civicrm_user_login(AccountInterface $account) {
  $civicrm = \Drupal::service('civicrm');
  $civicrm->initialize();

  if (\Drupal::hasService('masquerade')) {
    // https://github.com/civicrm/civicrm-drupal-8/pull/31
    // In theory this is harmless whether we masquerade or not, but for now narrowing
    // the scope, in case it causes regressions.
    // Not using isMasquerading() because it does not seem to be
    // initialized at this point (it always returns false).
    \CRM_Core_Session::singleton()->reset(FALSE);
  }

  \Drupal::service('civicrm')->synchronizeUser($account);
}

/**
 * Implements hook_user_insert().
 */
function civicrm_user_insert(AccountInterface $account) {
  $civicrm = \Drupal::service('civicrm');
  $civicrm->initialize();
  civicrm_key_disable();
  $userSystem = CRM_Core_Config::singleton()->userSystem;
  $userSystemID = $userSystem->getBestUFID($account);
  $uniqId = $userSystem->getBestUFUniqueIdentifier($account);
  \CRM_Core_BAO_UFMatch::synchronizeUFMatch($account, $userSystemID, $uniqId, 'Drupal8', NULL, 'Individual', $isLogin = FALSE);

  // As per CRM-7858, the email address in CiviCRM isn't always set
  // with a call to synchronize(). So we force this through.
  $contact_id = \CRM_Core_BAO_UFMatch::getContactId($account->id());
  if (empty($contact_id)) {
    return;
  }
  \CRM_Core_BAO_UFMatch::updateContactEmail($contact_id, $account->getEmail());

  // If already in CiviCRM, we don't do then next bit because it will cause
  // the current request to exit, stopping execution.
  $config = CRM_Core_Config::singleton();
  if ($config->inCiviCRM) {
    return;
  }

  // Process any profile form fields that may have been submitted.
  // In particular, this will pick up form fields that were submitted on the
  // user_registration page.
  if (civicrm_on_user_page()) {
    \CRM_Core_BAO_UFGroup::getEditHTML($contact_id, '', 2, TRUE, FALSE, NULL, FALSE, $civicrm->getCtype());
  }
}

/**
 * Implements hook_user_update().
 */
function civicrm_user_update(AccountInterface $account) {
  \Drupal::service('civicrm')->initialize();

  // Update primary email address of contact if it has changed.
  $contact_id = \CRM_Core_BAO_UFMatch::getContactId($account->id());
  if ($contact_id) {
    $contact_email = \CRM_Contact_BAO_Contact::getPrimaryEmail($contact_id);
    if ($contact_email != $account->getEmail()) {
      \CRM_Core_BAO_UFMatch::updateContactEmail($contact_id, $account->getEmail());
    }
  }

  // @Todo Drupal 7 code cleared navigation menu if it detected a change in roles.
  // \CRM_Core_BAO_Navigation::resetNavigation($contact_id);
}

/**
 * Implements hook_user_delete().
 */
function civicrm_user_delete(AccountInterface $account) {
  \Drupal::service('civicrm')->initialize();
  \CRM_Core_BAO_UFMatch::deleteUser($account->id());
}
