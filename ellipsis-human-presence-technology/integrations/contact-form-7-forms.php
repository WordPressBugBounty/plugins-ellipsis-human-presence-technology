<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceCF7Integration extends HumanPresenceIntegration {
	public static $form_prefix = 'cf7';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 20, 1 );
		add_filter( 'wpcf7_spam', array( __CLASS__, 'validate_forms' ), 10, 1 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 20, 1 );

		/**
		 * Auto-protect New Contact Form 7 Forms
		 */
		add_action( 'wpcf7_after_create', array( 'HumanPresenceAutoProtect', 'init' ), 10, 2 );
		add_action( 'wpcf7_after_update', array( 'HumanPresenceAutoProtect', 'init' ), 10, 2 );
	}

	/*
	 * Get Contact Form 7 forms
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( function_exists( 'wpcf7_contact_form' ) ) {
			$additional_forms = array();
			$cf7_form_list    = get_posts( array(
				'post_type'   => 'wpcf7_contact_form',
				'numberposts' => - 1
			) );
			foreach ( $cf7_form_list as $form ) {
				$fid = self::get_form_id( $form->ID );
				// Push to forms list
				$additional_forms[] = array(
					'id'          => $fid,
					'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
					'name'        => $form->post_title,
					'type'        => 'Contact Form 7',
					'submissions' => HumanPresenceForms::get_form_submissions_ct( $fid, 0, true ),
					// cf7 doesn't track submissions
					'activity'    => '<span class="hp-pop">N/A <i class="fa fa-info-circle fa-fw"></i><span class="popover above">This form builder does not support tracking form submissions.</span></span>'
					// cf7 doesn't track submissions
				);
			}
			self::prune_disabled_forms( $additional_forms );
			$forms_list = array_merge( $forms_list, $additional_forms );
		}

		return $forms_list;
	}

	/**
	 * Validate Contact Form 7 forms
	 *
	 * @param Boolean $spam - Boolean value to return true for spammy submissions
	 *
	 * @return Boolean
	 */
	public static function validate_forms( $spam ) {
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		$cf7_id = isset( $_POST['_wpcf7'] ) ? sanitize_text_field( $_POST['_wpcf7'] ) : '';
		$fid    = self::get_form_id( $cf7_id );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = isset( $options['hp_forms'][ $fid ] ) ? $options['hp_forms'][ $fid ]['form_enabled'] : 0;
			if ( $hp_form_enabled ) {
				// If the detection check fails, prevent the form submission and display an error message.
				$validation_failed = HumanPresenceForms::fail_form_validation();
				if ( $validation_failed ) {
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
					// Get error message
					$spam = true; // cf7 doesn't support custom spam error messages
				}
			}
		}

		return $spam;
	}

	/*
	 * This is for the autoprotect process and should enable all forms for validation.
	 * This should be added to the humanpresence_autoprotect_forms action.
	 * This should implement the HumanPresenceForms::enable_forms() static method to enable each form
	 */
	public static function enable_forms( $unattended ) {
		if ( function_exists( 'wpcf7_contact_form' ) ) {
			$cf7_form_list = get_posts( array(
				'post_type'   => 'wpcf7_contact_form',
				'numberposts' => - 1
			) );
			if ( is_array( $cf7_form_list ) ) {
				foreach ( $cf7_form_list as $form ) {
					$fid = self::get_form_id( $form->ID );
					HumanPresenceForms::enable_forms( $fid, 1, $unattended );
				}
			}
		}
	}

}

add_action( 'plugins_loaded', function() { new HumanPresenceCF7Integration(); });
