<?php $options = human_presence()->get_options(); ?>
<!-- Render Modal -->
<div id="hp-modal-open" class="hp-modal-window" style="display: none;">
	<div>
		<a href="#hp-modal-close" title="Close" class="hp-modal-close">&times;</a>
		<h1>Human Presence Settings</h1>
		<p>You can use the following settings to customize the Human Presence configuration on your site.</p>
		<br/>

		<form name="hpres_settings_form" method="post" action="<?php echo esc_url( add_query_arg( 'action', 'humanpresence_change_settings', human_presence()->admin_url ) ); ?>">
			<?php wp_nonce_field( 'humanpresence_change_settings', 'humanpresence_nonce' ); ?>
			<input type="hidden" name="hpres_settings_change_submitted" value="Y">

			<table class="wp-list-table widefat fixed striped">
				<tbody>
				<tr>
					<td class="dl">
						<p>
							Enable Auto-protection of all forms and comments
							<span class="hp-pop">
								<i class="fa fa-info-circle fa-fw"></i>
								<span class="popover above">When enabled, comments and all existing forms, as well as new ones, will be automatically protected instead of choosing to protect them individually.</span>
							</span>
						</p>
					</td>
					<td class="pull-right">
						<div class="check-toggle-container">
							<label class="check-toggle-label"></label>
							<div class="check-toggle">
								<input id="hp-enable-autoprotect" name="autoprotect" type="checkbox" value="1" <?php echo ( ! isset( $options['wp_hp_autoprotect'] ) || ( null == $options['wp_hp_autoprotect'] ) || ( 0 == $options['wp_hp_autoprotect'] ) ) ? '' : 'checked'; ?> />
								<label for="hp-enable-autoprotect"></label>
								<span></span>
							</div>
						</div>
					</td>
				</tr>

				<tr>
					<td class="dl">
						<p>
							Adjust the Human Confidence
							<span class="hp-pop">
								<i class="fa fa-info-circle fa-fw"></i>
								<span class="popover above">The range goes from zero to 100.</span>
							</span>
							<ul class="help-text">
								<li>
								&bull;&nbsp;If you put a lower number, Human Presence will be less strict (less likely to mark submissions as spam). For example, if you set to 5% confidence that means the algorithm will allow any form submissions it deems are 5% or more confident it is a human.</li>
								<li>
								&bull;&nbsp;If you put a higher number, Human Presence will be more strict (more likely to mark submission as spam). For example, if you set to 95% confidence that means the algorithm will only allow form submissions if it is at least 95% confident it is a human.</li>
							</ul>
						</p>
					</td>
					<td class="pull-right">
						<label for="minimal_confidence"></label>
						<input class="minimal-confidence" name="minimal_confidence" type="number" value="<?php echo isset( $options['wp_hp_min_confidence'] ) ? esc_attr( $options['wp_hp_min_confidence'] ) : 100; ?>" />
					</td>
				</tr>
				<tr>
					<td class="dl">
						<p>
							Enable Debug Mode
							<span class="hp-pop">
								<i class="fa fa-info-circle fa-fw"></i>
								<span class="popover above">Only enable if instructed to do so by support.</span>
							</span>
							<?php if( isset( $options['wp_hp_debug'] ) && $options['wp_hp_debug'] == 1 ):?>
							<br />
							<a href="<?php echo get_admin_url();?>?hp_debug_download=1" class="debug-download">Download debug report</a>
							<?php endif;?>
						</p>
					</td>
					<td class="pull-right">
						<div class="check-toggle-container">
							<label class="check-toggle-label"></label>
							<div class="check-toggle">
								<input id="hp-enable-debug" name="debug" type="checkbox" value="1" <?php echo ( ! isset( $options['wp_hp_debug'] ) || ( null == $options['wp_hp_debug'] ) || ( 0 == $options['wp_hp_debug'] ) ) ? '' : 'checked'; ?> />
								<label for="hp-enable-debug"></label>
								<span></span>
							</div>
						</div>
					</td>
				</tr>

				<!-- TODO: Add support for CAPTCHA fallback -->
				</tbody>
			</table>

			<p class="action-row">
				<input class="button-primary" name="hpres_settings_save" type="submit" value="Save changes">
			</p>
		</form>

	</div>
</div>
