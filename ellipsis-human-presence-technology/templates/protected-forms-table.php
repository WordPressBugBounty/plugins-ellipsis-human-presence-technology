<form id="protected-forms-table" method="get" action="<?php echo esc_url( human_presence()->admin_url ); ?>">
	<?php wp_nonce_field( 'humanpresence_form_enabled_change', 'humanpresence_ajax_nonce' ); ?>
	<input type="hidden" name="page" value="<?php echo isset( $_REQUEST['page'] ) ? esc_attr( sanitize_text_field( $_REQUEST['page'] ) ) : ''; ?>"/>
	<?php HumanPresenceProtectedFormsListTable::display_protected_forms_table(); ?>
</form>
