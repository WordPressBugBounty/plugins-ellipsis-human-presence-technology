<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceGravityFormsIntegration extends HumanPresenceIntegration {
	public static $form_prefix = 'gf';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 40, 1 );
		add_filter( 'gform_validation', array( __CLASS__, 'validate_forms' ), 10, 1 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 40, 1 );
		add_filter( 'gform_validation_message', array( __CLASS__, 'change_gravity_validation_message' ), 10, 2 );

		/**
		 * Auto-protect New Gravity Forms
		 */
		add_action( 'gform_after_save_form', array( 'HumanPresenceAutoProtect', 'init' ), 10, 2 );
		add_action( 'gform_after_duplicate_form', array( 'HumanPresenceAutoProtect', 'init' ), 10, 2 );
	}

	/*
	 * Get Gravity Forms
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( class_exists( 'GFCommon' ) && class_exists( 'GFAPI' ) ) {
			$additional_forms  = array();
			$gravity_form_list = GFAPI::get_forms();
			if ( is_array( $gravity_form_list ) ) {
				foreach ( $gravity_form_list as $form ) {
					// Assign unique form id
					$fid = self::get_form_id( $form['id'] );
					// Get current form entries count
					$entries = GFAPI::get_entries( $form['id'] );
					// Get last entry date
					$last_entry = array_key_exists( 0, $entries ) ? $entries[0]['date_created'] : null; // Entries are reverse ordered, newest listed first
					// Push to forms list
					$additional_forms[] = array(
						'id'          => $fid,
						'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
						'name'        => $form['title'],
						'type'        => 'Gravity Forms',
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
	 * Validate Gravity forms
	 *
	 * @param Array $validation_result - Array of including the form object properties
	 *
	 * @return Array - $validation_result
	 */
	public static function validate_forms( $validation_result ) {
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		$fid     = self::get_form_id( absint( $validation_result['form']['id'] ) );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];

			if ( $hp_form_enabled ) {
				// If the detection check fails, prevent the form submission and display an error message.
				$validation_failed = HumanPresenceForms::fail_form_validation();
				if ( $validation_failed ) {
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
					// Set GF fail flag
					$validation_result['is_valid'] = false;
				}
			}
		}

		return $validation_result;
	}

	/**
	 * Update Gravity Forms Validation Message
	 *
	 * @param String $message - Validation error message
	 * @param Object $form - Gravity forms object (optional)
	 *
	 * @return String
	 */
	function change_gravity_validation_message( $message, $form ) {
		$specific_msg = '';
		$options      = human_presence()->get_options();
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) ) {
			$fid             = self::get_form_id( $form['id'] );
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];

			if ( $hp_form_enabled ) {
				$specific_msg = HumanPresenceForms::fail_form_error_message();
			}
		}

		return '<div class="validation_error">There was a problem with your submission. Errors have been highlighted below. ' . $specific_msg . '</div>';
	}

	/*
	 * Protect Existing Gravity forms
	 */
	public static function enable_forms( $unattended ) {
		if ( class_exists( 'GFCommon' ) && class_exists( 'GFAPI' ) ) {
			$gravity_form_list = GFAPI::get_forms();
			if ( is_array( $gravity_form_list ) ) {
				foreach ( $gravity_form_list as $form ) {
					// Assign unique form id
					$fid = self::get_form_id( $form['id'] );
					HumanPresenceForms::enable_forms( $fid, 1, $unattended );
				}
			}
		}
	}
}

add_action( 'plugins_loaded', function() { new HumanPresenceGravityFormsIntegration(); });
