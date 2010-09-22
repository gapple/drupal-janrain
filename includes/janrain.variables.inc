<?php
// $Id$

/**
 * @file janrain/includes/janrain.variables.inc
 * Contains the variables and defaults used by Janrain.
 */

/**
 * The variable namespace for Janrain.
 */
define('JANRAIN_NAMESPACE', 'janrain__');

/**
 * Wrapper for variable_get() that uses the Janrain variable registry.
 *
 * @param string $name
 *  The variable name to retrieve. Note that it will be namespaced by
 *  pre-pending JANRAIN_NAMESPACE, as to avoid variable collisions
 *  with other modules.
 * @param unknown $default
 *  An optional default variable to return if the variable hasn't been set
 *  yet. Note that within this module, all variables should already be set
 *  in the janrain_variable_default() function.
 * @return unknown
 *  Returns the stored variable or its default.
 *
 * @see janrain_variable_set()
 * @see janrain_variable_del()
 * @see janrain_variable_default()
 */
function janrain_variable_get($name, $default = NULL) {
  // Allow for an override of the default.
  // Useful when a variable is required (like $path), but namespacing still desired.
  if (!isset($default)) {
    $default = janrain_variable_default($name);
  }
  // Namespace all variables
  $variable_name = JANRAIN_NAMESPACE . $name;
  return variable_get($variable_name, $default);
}

/**
 * Wrapper for variable_set() that uses the Janrain variable registry.
 *
 * @param string $name
 *  The variable name to set. Note that it will be namespaced by
 *  pre-pending JANRAIN_NAMESPACE, as to avoid variable collisions with
 *  other modules.
 * @param unknown $value
 *  The value for which to set the variable.
 * @return unknown
 *  Returns the stored variable after setting.
 *
 * @see janrain_variable_get()
 * @see janrain_variable_del()
 * @see janrain_variable_default()
 */
function janrain_variable_set($name, $value) {
  $variable_name = JANRAIN_NAMESPACE . $name;
  return variable_set($variable_name, $value);
}

/**
 * Wrapper for variable_del() that uses the Janrain variable registry.
 *
 * @param string $name
 *  The variable name to delete. Note that it will be namespaced by
 *  pre-pending JANRAIN_NAMESPACE, as to avoid variable collisions with
 *  other modules.
 *
 * @see janrain_variable_get()
 * @see janrain_variable_set()
 * @see janrain_variable_default()
 */
function janrain_variable_del($name) {
  $variable_name = JANRAIN_NAMESPACE . $name;
  variable_del($variable_name);
}

/**
 * The default variables within the Janrain namespace.
 *
 * @param string $name
 *  Optional variable name to retrieve the default. Note that it has not yet
 *  been pre-pended with the JANRAIN_NAMESPACE namespace at this time.
 * @return unknown
 *  The default value of this variable, if it's been set, or NULL, unless
 *  $name is NULL, in which case we return an array of all default values.
 *
 * @see janrain_variable_get()
 * @see janrain_variable_set()
 * @see janrain_variable_del()
 */
function janrain_variable_default($name = NULL) {
  static $defaults;

  if (!isset($defaults)) {
    $defaults = array(
      'app_id' => '',
      'facebook_share_link_types' => array(),
      'facebook_share_default_title' => 'Share on Facebook',
    );
    foreach (node_get_types('names') as $type => $name) {
      $defaults['facebook_share_title_'. $type] = '';
    }
  }

  if (!isset($name)) {
    return $defaults;
  }

  if (isset($defaults[$name])) {
    return $defaults[$name];
  }
}

/**
 * Return the fully namespace variable name.
 *
 * @param string $name
 *  The variable name to retrieve the namespaced name.
 * @return string
 *  The fully namespace variable name, prepended with
 *  JANRAIN_NAMESPACE.
 */
function janrain_variable_name($name) {
  return JANRAIN_NAMESPACE . $name;
}
