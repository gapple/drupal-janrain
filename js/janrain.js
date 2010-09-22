// $Id$

/**
 * @file janrain/js/janrain.js
 *
 * JavaScript for the Janrain module.
 */

// Add this to the jQuery namespace.
(function ($) {
  Drupal.behaviors.janrainLaunch = function(context) {
    $('.janrain-link-social:not(.janrainLaunch-processed)')
      .addClass('janrainLaunch-processed')
      .each(function() {
        $(this).bind('click', function(e) {
          var _janrain = Drupal.settings.janrain[$(this).attr('id')];
          RPXNOW.loadAndRun(['Social'], function () {
            var activity = new RPXNOW.Social.Activity(
              _janrain.title,
              _janrain.subject,
              _janrain.url);
            RPXNOW.Social.publishActivity(activity);
          });
          return false;
        });
      });
  }
})(jQuery);
