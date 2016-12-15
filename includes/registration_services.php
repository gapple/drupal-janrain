<?php
/**
 * @file
 * Service definitions for Janrain Module in registration mode.
 */

/**
 * Implements hook_services_resources() for janrain_services_resources().
 *
 * @todo-3.1 add backplane channel sync service endpoint
 */
function _janrain_services_resources() {
  return array(
    'registration' => array(
      'actions' => array(
        'code' => array(
          'help' => 'Push Janrain identity credentials into user session',
          'callback' => '_janrain_registration_code_service_callback',
          'args' => array(
            array(
              'name' => 'code',
              'type' => 'string',
              'description' => 'a Janrain Registration OAuth code',
              'source' => array('data' => 'code'),
              'optional' => FALSE,
            ),
          ),
          'access callback' => 'user_is_anonymous',
          'file' => array('file' => 'includes/registration_services.php', 'module' => 'janrain'),
        ),
        'session_token' => array(
          'help' => 'Returns current session token for logged in user, refreshes token if necessary',
          'callback' => '_janrain_session_callback',
          'args' => array(),
          'access callback' => 'user_is_logged_in',
          'file' => array('file' => 'includes/registration_services.php', 'module' => 'janrain'),
        ),
        'profile_update' => array(
          'help' => 'Updates the profile of the logged in user when user submits Janrain profile form',
          'callback' => '_janrain_registration_profile_callback',
          'args' => array(),
          'access callback' => 'user_is_logged_in',
          'file' => array('file' => 'includes/registration_services.php', 'module' => 'janrain'),
        ),
      ),
    ),
  );
}

/**
 * Handles registration/code.
 */
function _janrain_registration_code_service_callback($code) {
  timer_start(__FUNCTION__);
  global $base_root;
  $sdk = JanrainSdk::instance();

  try {
    $settings = $sdk->getConfig();

    // Where did the login start?
    $login_url = $_SERVER['HTTP_REFERER'];
    // Check referrer is 1st party.
    $login_host_matches = FALSE !== stripos($login_url, $base_root);
    // Sanity check AJAX origin is also 1st party.
    $login_origin_matches = FALSE !== stripos($base_root, $_SERVER['HTTP_ORIGIN']);
    $good_login_url = $login_host_matches && $login_origin_matches;
    if (!$good_login_url) {
      // Bad referer, fallback to session based login_url.
      $login_url = $sdk->getSessionItem('capture.currentUri');
    }
    // Check for login_url.
    if (empty($login_url)) {
      // Something is fishy here, blow up.
      services_error('Login URL is not verifiable, aborting login.');
      return;
    }
    // Everything looks okay, fetch tokens.
    $tokens = $sdk->CaptureApi->fetchTokensFromCode($code, $login_url);
  }
  catch (Exception $e) {
    // Capture call failed for login, this is superbad.
    watchdog_exception('janrain', $e, NULL, array(), WATCHDOG_EMERGENCY);
    // Services_error rethrows.
    services_error('Invalid OAuth code!');
  }

  DrupalAdapter::setSessionItem('accessToken', $tokens['access_token']);
  DrupalAdapter::setSessionItem('refreshToken', $tokens['refresh_token']);
  // Set the token expiration timestamp to be 10 min less than the timeout.
  DrupalAdapter::setSessionItem('tokenExpires', time() + intval($tokens['expires_in']) - 60 * 10);
  $info = timer_stop(__FUNCTION__);
  drupal_add_http_header("X-Janrain-Perf", sprintf("%fms", $info['time']));
  return 'Session enhanced by Janrain Registration! Proceed to login';
}

/**
 * Handles registration/session_token calls.
 *
 * @todo-3.1 move session management into SDK
 */
function _janrain_session_callback() {
  $sdk = JanrainSdk::instance();
  $token_expires = DrupalAdapter::getSessionItem('tokenExpires');
  if ($token_expires && (time() > $token_expires)) {
    try {
      $new_tokens = $sdk->CaptureApi->oauthRefreshToken(DrupalAdapter::getSessionItem('refreshToken'));
      DrupalAdapter::setSessionItem('accessToken', $new_tokens->access_token);
      DrupalAdapter::setSessionItem('refreshToken', $new_tokens->refresh_token);
      DrupalAdapter::setSessionItem('tokenExpires', time() + intval($new_tokens->expires_in) - 60 * 10);
    }
    catch (\Exception $e) {
      // Call to Capture failed, this is something that needs attention.
      watchdog('janrain', $e->getMessage(), array(), WATCHDOG_ALERT);
    }
    return $new_tokens->access_token;
  }
  else {
    return DrupalAdapter::getSessionItem('accessToken');
  }
}

/**
 * Handles registration/profile_update calls.
 */
function _janrain_registration_profile_callback() {
  $sdk = JanrainSdk::instance();
  $access_token = DrupalAdapter::getSessionItem('accessToken');
  $profile = $sdk->CaptureApi->fetchProfileByToken($access_token);
  if (module_exists('rules')) {
    rules_invoke_event_by_args('janrain_data_profile_updated', array('profile' => $profile));
  }
  module_invoke_all('janrain_profile_updated', $profile, $account);
  return t("Drupal's user data updated successfully!");
}
