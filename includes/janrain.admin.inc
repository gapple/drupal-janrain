<?php

/**
 * @file janrain/includes/janrain.admin.inc
 * Administrative functions for Janrain.
 */

/**
 * The administrative settings form for Janrain.
 */
function janrain_settings() {
  $form['janrain'] = array(
    '#type' => 'item',
    '#description' => t('These settings are for interfacing the !janrain social networking service with this site.', array('!janrain' => l(t('Janrain'), 'http://janrain.com/', array('attributes' => array('target' => '_blank'))))),
  );
  $form[janrain_variable_name('app_id')] = array(
    '#type' => 'textfield',
    '#title' => t('App ID'),
    '#description' => t('You need to apply for an application at !app and enter the App ID from the application dashboard here.', array('!app' => l(t('Janrain'), 'https://rpxnow.com/relying_parties/', array('attributes' => array('target' => '_blank'))))),
    '#default_value' => janrain_variable_get('app_id'),
  );
  $form['facebook_share_links'] = array(
    '#type' => 'fieldset',
    '#title' => t('Facebook share links'),
    '#collapsible' => TRUE,
    '#collapsed' => FALSE,
  );
  $options = array_map('check_plain', node_get_types('names'));
  $form['facebook_share_links'][janrain_variable_name('facebook_share_link_types')] = array(
    '#type' => 'checkboxes',
    '#title' => t('Facebook share link types'),
    '#description' => t('Any nodes of these types will have a link to share that content with Facebook.'),
    '#default_value' => janrain_variable_get('facebook_share_link_types'),
    '#options' => $options,
  );
  $form['facebook_share_links']['titles'] = array(
    '#type' => 'fieldset',
    '#title' => t('Facebook share link titles'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );
  $form['facebook_share_links']['titles'][janrain_variable_name('facebook_share_default_title')] = array(
    '#type' => 'textfield',
    '#title' => t('Facebook share link types default title'),
    '#description' => t('Any types that do not have their own share link title will use this default instead.'),
    '#default_value' => janrain_variable_get('facebook_share_default_title'),
  );
  foreach ($options as $type => $name) {
    $form['facebook_share_links']['titles'][janrain_variable_name('facebook_share_title_'. $type)] = array(
      '#type' => 'textfield',
      '#title' => t('Title for @type type', array('@type' => $name)),
      '#default_value' => janrain_variable_get('facebook_share_title_'. $type),
    );
  }
  return system_settings_form($form);
}
