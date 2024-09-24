<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceHappyFormsIntegration extends HumanPresenceIntegration {

	public static $form_prefix = 'hpf';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 30, 1 );
		add_filter( 'happyforms_validate_submission', array( __CLASS__, 'validate_forms' ), 10, 3 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 30, 1 );
		// add_filter( 'happyforms_is_registered', function() {return true;}, 10, 1 );
	}

	/**
	 * Get Forms
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( function_exists( 'HappyForms' ) ) {
			$additional_forms     = array();
			$form_list = happyforms_get_form_controller()->get();
			if ( is_array( $form_list ) ) {
				foreach ( $form_list as $form ) {
					$fid = self::get_form_id( $form['ID'] );
					// $entry_count = absint( get_post_meta( $form['ID'], 'wpforms_entries_count', true ) );
					if ( function_exists( 'happyforms_get_message_controller' ) && is_callable( array( 'HappyForms_Message_Controller', 'get_by_form' ) ) ) {
						$entries = happyforms_get_message_controller()->get_by_form( $form['ID'] );
						if( $entries ) {
							$entry_count = count($entries);
							$updated = $entries[0]['post_modified'];
						}
					} else {
						$entry_count = 0;
						$updated = $form['updated_at'];
					}
					// Push to forms list
					$additional_forms[] = array(
						'id'          => $fid,
						'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
						'name'        => $form['post_title'],
						'type'        => 'HappyForms',
						'submissions' => HumanPresenceForms::get_form_submissions_ct( $fid, $entry_count ),
						'activity'    => HumanPresenceForms::get_latest_form_activity( $fid, $updated ),
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
	 * @param Bool $is_valid
	 * @param Array $request - Array of form value strings submitted
	 * @param Array $form - Form object
	 *
	 * @return Bool - valid?
	 */
	public static function validate_forms( $is_valid, $request, &$form ) {
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		$fid     = self::get_form_id( absint( $form['ID'] ) );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];
			if ( $hp_form_enabled ) {
				// If the detection check fails, prevent the form submission and display an error message.
				$validation_failed = HumanPresenceForms::fail_form_validation();
				if ( $validation_failed ) {
					$form_part_id = end((array_values($form['parts'])))['id'];
					$is_valid = false;
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
					// Get error message
					$session = happyforms_get_session();
					// This error never gets read by HappyForms --- Really guys. Come on.
					$session->add_error( $form['ID'], html_entity_decode( HumanPresenceForms::fail_form_error_message() ) );
					$session->add_error( $form['ID'] . '_' . $form_part_id, html_entity_decode( HumanPresenceForms::fail_form_error_message() ) );
				}
			}
		}

		return $is_valid;
	}

	/**
	 * Protect Existing forms
	 */
	public static function enable_forms( $unattended ) {
		if ( function_exists( 'HappyForms' ) ) {
			$form_list = happyforms_get_form_controller()->get();
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

add_action( 'plugins_loaded', function() { new HumanPresenceHappyFormsIntegration(); });
