<!-- Render Modal -->
<div id="hp-license-modal-open" class="hp-modal-window" style="display: none;">
	<div>
		<a href="#hp-modal-close" title="Close" class="hp-modal-close">&times;</a>
		<h1>Activate Your License</h1>
		<p>Enter your <a href="https://www.humanpresence.io/my-account/license-keys/" target="_blank">Human Presence license key</a> to activate premium protection on this site.</p>
		<br/>

		<form name="hpres_license_activation_form" method="post" action="<?php echo esc_url( add_query_arg( 'action', 'humanpresence_license_activation', human_presence()->admin_url ) ); ?>">
			<?php wp_nonce_field( 'humanpresence_license_activation', 'humanpresence_nonce' ); ?>
			<input type="hidden" name="hpres_license_activation_change_submitted" value="Y">

			<table class="wp-list-table widefat fixed striped">
				<tbody>
				<tr>
					<td class="dl">
						<p>License Key</p>
					</td>
					<td class="pull-right">
						<?php $options = human_presence()->get_options(); ?>
						<input class="license-key" name="license-key" type="text" placeholder="Your Key" value="<?php echo isset( $options['wp_hp_premium_license_key'] ) ? esc_attr( $options['wp_hp_premium_license_key'] ) : ''; ?>">
					</td>
				</tr>
				</tbody>
			</table>

			<p class="action-row">
				<input class="button-primary" name="hpres_license_activation_activate" type="submit" value="Activate">
			</p>
		</form>

	</div>
</div>
