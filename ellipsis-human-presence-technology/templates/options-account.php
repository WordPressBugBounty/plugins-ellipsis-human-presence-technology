<div id="hp-connect-box" class="postbox md">
	<!-- <h3><span>Do you have and existing Human Presence&trade; account?</span></h3> -->
	<h3>Connecting to Human Presence&trade;<span class="spinner is-active"></span></h3>
	<div class="inside">
		<!-- <form name="hpres_connect_form" method="post" action="<?php echo esc_url( add_query_arg( 'action', 'humanpresence_have_account', human_presence()->admin_url ) ); ?>">
			<?php wp_nonce_field( 'humanpresence_have_account', 'humanpresence_nonce' ); ?>
			<input type="hidden" name="hpres_have_account_submitted" value="Y">

			<p>If you already have an account you can user your Company name and Username to connect or create one now.
				By signing up for a Human Presence account, you agree to the <a class="button-link" href="https://www.humanpresence.io/tos" target="_blank">terms of service</a>.
			</p>

			<div class="action-row">
				<input class="button-secondary" type="submit" name="hpres_have_account" value="I have an account"/>
				<a class="button-primary" href="https://www.humanpresence.io/anti-spam-wordpress-plugin/"
				   target="_blank">Create one now</a>
			</div>
		</form> -->
		<form id="humanpresence_connect_account_form" action="<?php echo esc_url( add_query_arg( 'action', 'humanpresence_connect_account', human_presence()->admin_url ) ); ?>">
			<?php wp_nonce_field( 'humanpresence_connect_account', 'humanpresence_nonce' ); ?>
			<input type="hidden" name="humanpresence_connect_account" value="Y" />
		</form>
	</div> <!-- .inside -->
</div> <!-- .postbox -->
<script type="text/javascript">
jQuery(function($){
	var form = $('#humanpresence_connect_account_form');
	$.ajax({
		url: form[0].action,
		method: 'POST',
		data: form.serialize()
	}).done(function(){
		$('#hp-connect-box .inside').append('<p>Connection successful. Reloading...</p>');
		setTimeout(function(){ window.location.reload() }, 2000);
	}).fail(function(){
		$('#hp-connect-box .inside').append('<p>There was an error connecting to Human Presence. Please reload this page to try again.</p>');
	});
});
</script>