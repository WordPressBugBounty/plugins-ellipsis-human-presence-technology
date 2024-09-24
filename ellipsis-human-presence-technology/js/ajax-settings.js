if (typeof jQuery !== 'undefined') {
  jQuery(function() {
    jQuery('.wp-human-presence').on('click', '.hp-fm-enable-cb', function(e) {
      var fid = e.target.id.slice(13);
      var formEnabled = e.target.checked ? 1 : 0;
      var humanpresenceNonce = jQuery('#humanpresence_ajax_nonce').val();

      jQuery.ajax({
        url: ajaxConfig.url,
        data: {
          action: 'hpres_form_enabled_change',
          fid: fid,
          formEnabled: formEnabled,
          humanpresence_ajax_nonce: humanpresenceNonce
        },
        type: 'POST',
        success: function(res) {
          // Ajax Error Handling
          var isPrem = Number(ajaxConfig.isPremium);
          var pfc = Number(ajaxConfig.protectedFormsCt);
          if (!isPrem && formEnabled && pfc > 0) {
            // Handle community plan forms limit reached
            jQuery(document).ready(function() {
              setTimeout(function() {
                // Uncheck checkbox
                jQuery('#hp-fm-enable-' + fid).prop('checked', 0);
                // Show error
                jQuery('#protected-forms-table').prepend(
                  '<div class="notice notice-error is-dismissible below-h2"><p>Community version limit reached. Protecting more than one form requires a premium license. <a class="button-primary" style="margin-left: 10px;" target="_blank" href="https://www.humanpresence.io/anti-spam-wordpress-plugin">UPGRADE NOW</a></p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>'
                );
                jQuery('#protected-forms-table .notice-dismiss').click(
                  function() {
                    jQuery('#protected-forms-table .notice').remove();
                  }
                );
              }, 0);
            });
          } else if (
            (!isPrem && pfc === 0) || // Enabling first form
            (!isPrem && !formEnabled && pfc === 1) // Disabling only form
          ) {
            // Handle community limit reached, refresh page so js validation will work properly
            jQuery(document).ready(function() {
              setTimeout(function() {
                location.reload();
              }, 0);
            });
          }
        },
        error: function(errorThrown) {
          console.log(errorThrown);
        }
      });
    });
  });
}
