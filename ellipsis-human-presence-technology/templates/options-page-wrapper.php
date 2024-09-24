<div class="wp-human-presence wrap">
	<h2>
		<span>
			<img class="header-logo" src="<?php echo esc_url( human_presence()->plugin_url . 'images/hp-shield.svg' ); ?>" alt="Human Presence">
		</span> Human Presence Form Protection

		<span class="dd-btn-row">
			<div class="dropdown">
				<button class="button-secondary">
					Support
					<span class="input-group-addon">
						<i class="fa fa-caret-down fa-fw"></i>
					</span>
				</button>
				<div class="dd-menu">
					<ul>
						<li><a href="mailto:wpsupport@humanpresence.io">Contact Us</a></li>
						<!-- TODO Add support for HP Hosted Beacon page link -->
						<li><a target="_blank" href="https://www.humanpresence.io">Human Presence Website</a></li>
						<li><a>Version <?php echo human_presence()->version;?></a></li>
					</ul>
				</div>
			</div>
			<!-- <a class="button-primary" target="_blank" href="https://dashboard.humanpresence.io">Dashboard</a> -->
		</span>
	</h2>

	<div id="poststuff">
		<div id="post-body" class="metabox-holder columns-3">
			<!-- main content -->
			<div id="post-body-content">
				<div class="meta-box-sortables ui-sortable">
					<?php
					require_once( human_presence()->plugin_dir . '/templates/options-home.php' );
					?>
				</div> <!-- .meta-box-sortables .ui-sortable -->
			</div> <!-- post-body-content -->
		</div> <!-- #post-body .metabox-holder .columns-2 -->

		<br class="clear">
	</div> <!-- #poststuff -->
</div> <!-- .wrap -->
<?php $options = human_presence()->get_options(); ?>
<script>
	jQuery(document).ready(function () {
		// Enable modal style transitions after render
		jQuery('#hp-modal-open, #hp-license-modal-open').removeAttr('style');
		// Update changes to auto-protect status in DOM
		var autoprotectOn = <?php echo isset( $options['wp_hp_autoprotect'] ) && 1 == $options['wp_hp_autoprotect'] ? 'true' : 'false'; ?>;
		if (autoprotectOn) {
			// Disable Bulk Action UI on Auto-protect
			jQuery('#protected-forms-table #bulk-action-selector-top, #protected-forms-table #bulk-action-selector-bottom, #protected-forms-table #doaction, #protected-forms-table #doaction2, #protected-forms-table #cb-select-all-1, #protected-forms-table #cb-select-all-2, #protected-forms-table input[name="bulk-enable[]"]').prop('disabled', true);
		} else {
			// Renable check-toggles on disabling Auto-protect
			jQuery('#protected-forms-table .check-toggle input').prop('disabled', false);
		}
	});
</script>
