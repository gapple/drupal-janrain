//<?php
/**
 * @file
 */
//?>

// Initialize plex object jic (no clobber).
'object' != typeof window.janrain && (window.janrain = {});
'object' != typeof window.janrain.plex && (janrain.plex = {});
'object' != typeof window.janrain.settings && (janrain.settings = {});
'object' != typeof janrain.settings.capture && (janrain.settings.capture = {});
'object' != typeof janrain.settings.capture.beforeJanrainCaptureWidgetOnLoad
&& (janrain.settings.capture.beforeJanrainCaptureWidgetOnLoad = []);

janrain.plex.drupalPath = function () {
  var out = Drupal.settings.basePath;
  if (!Drupal.settings.janrain.clean_url) {
    out = Drupal.settings.basePath + '?q=';
  }
  return out;
}

janrain.settings.capture.beforeJanrainCaptureWidgetOnLoad.push(
  function () {
    jQuery.ajax({
      url: janrain.plex.drupalPath() + 'services/session/token',
      error: function (jqxhr, status, error) {console && console.error(error);},
      success: function (drupalToken) {
        if (typeof drupalToken !== "string") console && console.log(drupalToken);
        janrain.plex.csrf = drupalToken;
      }});
  });

janrain.plex.login = function(oauthCode) {
  if (typeof janrain.plex.csrf == "undefined") {
    setTimeout(janrain.plex.login, 0, oauthCode);
    return;
  }
  if (typeof oauthCode !== "string") console && console.error('Unexpected type for oauthCode: ' + oauthCode);
  jQuery.ajax({
      url: janrain.plex.drupalPath() + 'janrain/registration/code.json',
      type:'post',
      cache: false,
      xhrFields:{withCredentials:true},
      beforeSend: function (req) {req.setRequestHeader('X-CSRF-Token', janrain.plex.csrf);},
      error: function (jqxhr, status, error) {console && console.log(error);},
      data:{code:oauthCode},
      success: function (resp) {
        console && console.log(resp);
        document.getElementById('user_login').submit();
      }
  });
}


// do not invoke this outside of janrainCaptureWidgetOnLoad or in beforeJanrain...
janrain.plex.refreshToken = function() {
  if (!Drupal.settings.janrain.user_is_logged_in) {
    return;
  }
  jQuery.ajax({
    url: janrain.plex.drupalPath() + 'services/session/token',
    async:false,
    error: function (jqxhr, status, error) {console && console.error(error);},
    success: function (drupalToken) {
      console && console.log(drupalToken);
      jQuery.ajax({
        url: janrain.plex.drupalPath() + 'janrain/registration/session_token.json',
        type:'post',
        xhrFields:{withCredentials:true},
        beforeSend: function (req) {req.setRequestHeader('X-CSRF-Token', drupalToken);},
        error: function (jqxhr, status, error) {console.error(error);},
        //must synchronize this or widget loads before session started.
        async:false,
        success: function (resp) {
          console && console.log(resp);
          // feed the token to where it needs to go.
          janrain.capture.ui.createCaptureSession(resp[0]);
        }
      });
    }
  });
}

// profile update sync function
janrain.plex.profileUpdate = function (updateEvent) {
  // Skip for update failures and anonymous users.
  if ('success' !== updateEvent.status || !Drupal.settings.janrain.user_is_logged_in) {
    return;
  }
  if (typeof janrain.plex.csrf == "undefined") {
    setTimeout(janrain.plex.profileUpdate, 0, updateEvent);
    return;
  }
  jQuery.ajax({
    url: janrain.plex.drupalPath() + 'janrain/registration/profile_update.json',
    type:'post',
    xhrFields:{withCredentials:true},
    beforeSend: function (req) {req.setRequestHeader('X-CSRF-Token', janrain.plex.csrf);},
    error: function (jqxhr, status, error) {console.error(error);},
    success: function (resp) {
      console && console.log(resp);
    }
  });
}
