<?php $options = human_presence()->get_options(); ?>
<?php if(human_presence()->is_account_connected()):?>
<div class="updated below-h2 connected">
	<p>Your site is connected to your Human Presence&trade; account. <strong class="pull-right">Connected<span class="hp-acct-login-date"> - <?php echo esc_html( gmdate( 'm/d/Y', $options['last_updated'] ) ); ?></span></strong>
	</p>
	<?php if(!human_presence()->is_partner()):?>
	<form name="hpres_logout_form" method="post" action="<?php echo esc_url( add_query_arg( 'action', 'humanpresence_logout', human_presence()->admin_url ) ); ?>">
		<?php wp_nonce_field( 'humanpresence_logout', 'humanpresence_nonce' ); ?>
		<input type="hidden" name="hpres_logout_submitted" value="Y">
		<p class="create-hp-acct-text">Connected as <?php echo $options['wp_hp_email'];?>. Not you? <input class="button-link" type="submit" name="hpres_logout_submit" value="Disconnect"></p>
	</form>
	<?php endif;?>
</div>
<?php endif;?>
<?php if ( ! human_presence()->is_premium() ):?>
<div class="postbox">
	<div class="inside upgrade-premium-wrapper">
		<div class="btn-row pull-right">
			<a class="button-primary" href="<?php echo admin_url('admin.php?page=wp-human-presence');?>#hp-license-modal-open">Activate Premium License
				<span class="input-group-addon">
					<i class="fa fa-key fa-fw"></i>
				</span>
			</a>
		</div>
		<h3>Premium Form Protection Disabled</h3>
		<p>As a free Human Presence user, you are currently using the Community version of the Human Presence plugin. Community users may protect 1 form (or your WordPress comments) from bots. Premium users experience <strong>unlimited form protection</strong>. Human Presence supports protection for the following integrations (and the list is growing):</p>
    <div id="integrated-forms-container" style="display: none;">
      <ul class="integrated-forms-list">
        <li>WordPress Comments</li>
        <li>WooCommerce Product Reviews</li>
        <li>Formidable Forms</li>
        <li>Gravity Forms</li>
        <li>Contact Form 7</li>
        <li>Ninja Forms</li>
        <li>WSForm</li>
        <li>weForms</li>
        <li>Quform</li>
        <li>WP Fluent Forms</li>
        <li>Elementor Forms</li>
      </ul>
    </div>
		<p>Upgrade to Premium today to improve your protection.</p>
		<div class="btn-row text-right">
			<a class="button-primary" target="_blank"
			   href="https://www.humanpresence.io/wordpress/">UPGRADE TO PREMIUM</a>
      <a id="learn-more-btn" class="button-secondary" href="#">LEARN MORE</a>
		</div>

		<?php
		// Render license activation modal window
		require_once( human_presence()->plugin_dir . '/templates/license-activation-modal.php' );
		?>
	</div>
</div>
<?php endif;?>
<?php if(!empty($_GET['hp_debug'])):?>
<div class="postbox">
	<div class="inside">
		<h3>Debug Info</h3>
		<pre><?php print_r($options);?></pre>
	</div>
</div>
<?php endif;?>

<div class="postbox">
	<div class="inside">
		<h3>Enable Form Protection
			<a class="button-primary pull-right" href="#hp-modal-open">Settings
				<span class="input-group-addon">
					<i class="fa fa-cog fa-fw"></i>
				</span>
			</a>
		</h3>

		<p>If you are not using the auto-protection setting by default, look through your WordPress forms and choose
			which ones you want to protect with Human Presence. You can sort by name or type for convenience.
			Optionally, you can change protection parameters by clicking the <strong>Settings</strong> button.</p>

		<?php
		// Render protected forms table
		require_once( human_presence()->plugin_dir . '/templates/protected-forms-table.php' );
		// Render settings modal window
		require_once( human_presence()->plugin_dir . '/templates/options-modal.php' );
		?>
	</div> <!-- .inside -->
</div> <!-- .postbox -->

<script>
	// Set '.label-row' widths
	jQuery(document).ready(function () {
		jQuery('.label-row').each(function () {
			var labelRowWidth = jQuery(this).find('.label-danger').outerWidth();
			jQuery(this).outerWidth(labelRowWidth);
		});
		jQuery('#learn-more-btn').on('click', function(event) {
			event.preventDefault();
			jQuery('#integrated-forms-container').toggle();
		});
	});
</script>
