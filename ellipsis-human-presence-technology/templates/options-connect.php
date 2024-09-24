<div id="hp-connect-box" class="postbox md">

	<h3><span>Do you already have a Human Presence&trade; account?</span></h3>
	<div class="inside">
		<form name="hpres_connect_form" method="post" action="<?php echo esc_url( add_query_arg( 'action', 'humanpresence_have_account', human_presence()->admin_url ) ); ?>">
			<?php wp_nonce_field( 'humanpresence_have_account', 'humanpresence_nonce' ); ?>
			<input type="hidden" name="hpres_connect_submitted" value="Y">

			<p>Connect your Human Presence&trade; account for HP to start analyzing human behavior.</p>

			<p>
				<input name="wp_hp_email" id="wp_hp_email" placeholder="Email" type="text" value="" class="regular-text"/>
				<input name="wp_hp_username" id="wp_hp_username" placeholder="Username" type="text" value="" class="regular-text"/>
			</p>

			<div class="action-row">
				<input class="button-primary" type="submit" name="hpres_username_submit" value="Connect"/>
			</div>
		</form>
	</div> <!-- .inside -->
</div> <!-- .postbox -->
