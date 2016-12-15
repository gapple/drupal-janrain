<?php
/**
 * @file
 * Profile related functions.
 */

/**
 * Helper to validate login attempts using Janrain.
 */
function janrain_login_validate(&$form, &$form_state) {
  timer_start(__FUNCTION__);
  $token = DrupalAdapter::getSessionItem('accessToken');
  if (!$token) {
    // Native Drupal login, do nothing.
    watchdog('janrain', '{{user}} attempting to login without Janrain.', array('{{user}}' => $form_state['values']['name']), WATCHDOG_WARNING);
    return;
  }

  // Access token found, time for Janrain!
  $sdk = JanrainSdk::instance();
  try {
    $profile = $sdk->CaptureApi->fetchProfileByToken($token);
  }
  catch (Exception $e) {
    // Token found but capture call failed, fail login and log everything.
    watchdog('janrain', "%m\n%t", array(
      $e->getMessage(),
      $e->getTraceAsString(),
    ), WATCHDOG_EMERGENCY);
    form_set_error('janrain', t("Unable to log in."));
    return;
  }

  // Try to login.
  // For capture this should succeed in a single iteration.
  $identifiers = $profile->getIdentifiers();
  foreach ($identifiers as $external_id) {
    $account = user_external_load($external_id);
    if ($account) {
      _janrain_finish_login_helper($account, $form_state, $profile);
      return;
    }
  }

  // Couldn't find existing linked user. Link or register new user.
  // Load up profile data for link/registration logic.
  // Set uuid.
  $uuid = $profile->getFirst('$.uuid');
  // Set display_name.
  $display_name = $profile->getFirst('$.displayName');
  if (empty($display_name)) {
    // Fall back to uuid for default display_name or Drupal will explode.
    $display_name = $uuid;
  }
  // Set email.
  $email = $profile->getFirst('$.email');
  // Detect verified_email.
  $verified_email = FALSE;
  $verified_string = $profile->getFirst('$.emailVerified');
  if ($verified_string && (FALSE !== strtotime($verified_string))) {
    // Verified date exists and translates to a real timestamp.
    $verified_email = $email;
  }

  // Try to find user by verified email.
  // @todo admin should detect this is required and unique in schema.
  $verified_email = valid_email_address($verified_email) ? $verified_email : FALSE;
  $account = $verified_email ? user_load_by_mail($verified_email) : FALSE;
  if ($verified_email && $account) {
    // Found user by trusted email.
    watchdog('janrain', 'Found @name by verified email @email', array(
      '@name' => format_username($account),
      '@email' => filter_xss($verified_email),
    ), WATCHDOG_DEBUG);
    // Link them up, log them in, clear the session.
    _janrain_finish_login_helper($account, $form_state, $profile);
    return;
  }

  // Fallback to insecure unverified email IF DRUPAL CONFIG ALLOWS IT!
  $strict_email = variable_get('user_email_verification', TRUE);
  if (!$strict_email) {
    // Drupal doesn't care if email address are verified #sosecure.
    $email = valid_email_address($email) ? $email : FALSE;
    // Lookup user by unverified email.
    $account = user_load_by_mail($email);
    // Try user login/link but skip admins when email unverified.
    if ($account && ($account->uid != 1)) {
      // Found user, link them up, log them in, clear the session.
      watchdog('janrain', 'Found @name by email @email', array(
        '@name' => format_username($account),
        '@email' => filter_xss($email),
      ), WATCHDOG_DEBUG);
      _janrain_finish_login_helper($account, $form_state, $profile);
      return;
    }
  }

  // User not found by identifiers, verified email, or unverified email
  // create new account.
  // Directly save user and then log them in using login_submit so Drupal post-
  // login hooks fire.
  // n.b. capture is authoritative.
  $account_info = array(
    'name' => $display_name,
    'init' => $uuid,
    'mail' => $email,
    'access' => REQUEST_TIME,
    // Capture disables drupal based registration, so be sure to activate user.
    'status' => 1,
    'pass' => user_password(),
  );
  try {
    $new_user = user_save(drupal_anonymous_user(), $account_info);
    watchdog('janrain', 'Created user {{user}}', array(
      '{{user}}' => format_username($new_user),
    ), WATCHDOG_NOTICE);
    _janrain_finish_login_helper($new_user, $form_state, $profile);
    user_login_submit(array(), $form_state);
  }
  catch (\Exception $e) {
    // User save failed.
    // Should only happen if required data is missing (shouldn't happen).
    // Or if there's a uniqueness violation on display name or email.
    drupal_set_message(t('An error occured with your login. Contact your Drupal site admin to resolve.'), 'error');
    watchdog_exception('janrain', $e, NULL, array(), WATCHDOG_EMERGENCY);
  }
}

/**
 * Helper function to finish login validation and cleanup.
 */
function _janrain_finish_login_helper(&$account, &$form_state, &$profile) {
  // Make sure identifiers are linked so logins are faster next time.
  _janrain_link_identifiers($account, $profile);
  // Tell Drupal who's logging in.
  $form_state['uid'] = $account->uid;
  // Store profile for downstream processors (mapping module).
  $form_state['janrain']['profile'] = $profile;
  // Invoke standard module event API.
  module_invoke_all('janrain_profile_updated', $profile, $account);
  // Clean up the session data. To reduce session loads on DB.
  _janrain_clear_session();
  $perf = timer_stop("janrain_login_validate");
  drupal_add_http_header("X-Janrain-Perf", sprintf("%fms", $perf['time']));
}
