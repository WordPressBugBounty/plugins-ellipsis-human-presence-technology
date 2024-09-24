<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceWPFormsIntegration extends HumanPresenceIntegration {

	public static $form_prefix = 'wpf';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 30, 1 );
		add_filter( 'wpforms_process_initial_errors', array( __CLASS__, 'validate_forms' ), 10, 2 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 30, 1 );

		/**
		 * Auto-protect New Forms
		 * After Create - Depends on hpres_autoprotect_scan()
		 * After Duplicate - Depends on hpres_autoprotect_scan()
		 */
		add_action( 'wpforms_create_form', array( 'HumanPresenceAutoProtect', 'init' ), 10, 0 );
		add_action( 'wpforms_save_form', array( 'HumanPresenceAutoProtect', 'init' ), 10, 0 );
	}

	/**
	 * Get Forms
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( function_exists( 'wpforms' ) ) {
			$additional_forms     = array();
			$form_list = wpforms()->form->get();
			if ( is_array( $form_list ) ) {
				foreach ( $form_list as $form ) {
					$fid = self::get_form_id( $form->ID );
					$entry_count = absint( get_post_meta( $form->ID, 'wpforms_entries_count', true ) );
					// Push to forms list
					$additional_forms[] = array(
						'id'          => $fid,
						'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
						'name'        => ( $form->post_title ),
						'type'        => 'WPForms',
						'submissions' => HumanPresenceForms::get_form_submissions_ct( $fid, $entry_count ),
						'activity'    => 'N/A'
					);
				}
			}
			self::prune_disabled_forms( $additional_forms );
			$forms_list = array_merge( $forms_list, $additional_forms );
		}

		return $forms_list;
	}

	/**
	 * Validate forms
	 *
	 * @param Array $errors - Array of error messages
	 * @param Array $values - Array of form value strings submitted
	 *
	 * @return Array - $errors
	 */
	public static function validate_forms( $errors, $form_data ) {
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		$fid     = self::get_form_id( absint( $form_data['id'] ) );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];
			if ( $hp_form_enabled ) {
				// If the detection check fails, prevent the form submission and display an error message.
				$validation_failed = HumanPresenceForms::fail_form_validation();
				if ( $validation_failed ) {
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
					// Get error message
					$errors[$form_data['id']]['header'] = HumanPresenceForms::fail_form_error_message();
				}
			}
		}

		return $errors;
	}

	/**
	 * Protect Existing forms
	 */
	public static function enable_forms( $unattended ) {
		if ( function_exists( 'wpforms' ) ) {
			$form_list = wpforms()->form->get();
			if ( is_array( $form_list ) ) {
				foreach ( $form_list as $form ) {
					// Assign unique form id
					$fid = self::get_form_id( $form->ID );
					HumanPresenceForms::enable_forms( $fid, 1, $unattended );
				}
			}
		}
	}
}

add_action( 'plugins_loaded', function() { new HumanPresenceWPFormsIntegration(); });
