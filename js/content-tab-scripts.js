/**
 * @file
 * Behaviors Layout Builder Blocks content tab scripts.
 */

(function ($, _, Drupal, drupalSettings) {
  "use strict";

  Drupal.behaviors.LayoutBuilderBlocksContentTab = {
    attach: function (context) {
      // Move the original block fields to content tab.
      // form-item
      $('#drupal-off-canvas form.layout-builder-configure-block > .form-item:not(.bs_tab-content .form-item)').each(function() {
        $('#drupal-off-canvas form.layout-builder-configure-block > #bs_ui > #bs_tabContent > .bs_tab-pane--content').append($(this));
      });

      // form-wrapper
      $('#drupal-off-canvas form.layout-builder-configure-block > .form-wrapper:not(#bs_ui.form-wrapper)').each(function() {
        $('#drupal-off-canvas form.layout-builder-configure-block > #bs_ui > #bs_tabContent > .bs_tab-pane--content').append($(this));
      });

    }
  };

})(window.jQuery, window._, window.Drupal, window.drupalSettings);
