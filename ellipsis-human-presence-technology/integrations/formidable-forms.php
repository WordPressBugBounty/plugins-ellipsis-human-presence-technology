<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceFormidableFormsIntegration extends HumanPresenceIntegration {

	public static $form_prefix = 'frm';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 30, 1 );
		add_filter( 'frm_validate_entry', array( __CLASS__, 'validate_forms' ), 10, 2 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 30, 1 );

		/**
		 * Auto-protect New Formidable Forms
		 * After Create - Depends on hpres_autoprotect_scan()
		 * After Duplicate - Depends on hpres_autoprotect_scan()
		 */
		add_action( 'frm_update_form', array( 'HumanPresenceAutoProtect', 'init' ), 10, 2 );
		add_action( 'frm_after_duplicate_form', array( 'HumanPresenceAutoProtect', 'init' ), 10, 2 );
	}

	/**
	 * Get Formidable Forms
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( is_callable( 'FrmAppHelper::plugin_version' ) ) {
			$additional_forms     = array();
			$formidable_form_list = FrmForm::get_published_forms();
			if ( is_array( $formidable_form_list ) ) {
				foreach ( $formidable_form_list as $form ) {
					$fid = self::get_form_id( $form->id );
					// Get current form entries
					$entries = FrmEntry::getAll( array( 'it.form_id' => $form->id ) );
					// Get last entry date
					$entry_ids = array();
					foreach ( $entries as $entry ) {
						$entry_ids[] = $entry->id;
					}
					$last_entry_id = ! empty( $entry_ids ) ? max( $entry_ids ) : 0;
					$last_entry    = array_key_exists( $last_entry_id, $entries ) ? $entries[ $last_entry_id ]->created_at : null;
					// Push to forms list
					$additional_forms[] = array(
						'id'          => $fid,
						'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
						'name'        => ( $form->name ),
						'type'        => 'Formidable',
						'submissions' => HumanPresenceForms::get_form_submissions_ct( $fid, count( $entries ) ),
						'activity'    => HumanPresenceForms::get_latest_form_activity( $fid, $last_entry )
					);
				}
			}
			self::prune_disabled_forms( $additional_forms );
			$forms_list = array_merge( $forms_list, $additional_forms );
		}

		return $forms_list;
	}

	/**
	 * Validate Formidable forms
	 *
	 * @param Array $errors - Array of error messages
	 * @param Array $values - Array of form value strings submitted
	 *
	 * @return Array - $errors
	 */
	public static function validate_forms( $errors, $values ) {
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		$fid     = self::get_form_id( absint( $values['form_id'] ) );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];
			if ( $hp_form_enabled ) {
				// If the detection check fails, prevent the form submission and display an error message.
				$validation_failed = HumanPresenceForms::fail_form_validation();
				if ( $validation_failed ) {
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
					// Get error message
					$errors['spam'] = HumanPresenceForms::fail_form_error_message();
				}
			}
		}

		return $errors;
	}

	/**
	 * Protect Existing Formidable forms
	 */
	public static function enable_forms( $unattended ) {
		if ( is_callable( 'FrmAppHelper::plugin_version' ) ) {
			$formidable_form_list = FrmForm::get_published_forms();
			if ( is_array( $formidable_form_list ) ) {
				foreach ( $formidable_form_list as $form ) {
					// Assign unique form id
					$fid = self::get_form_id( $form->id );
					HumanPresenceForms::enable_forms( $fid, 1, $unattended );
				}
			}
		}
	}
}

add_action( 'plugins_loaded', function() { new HumanPresenceFormidableFormsIntegration(); });
