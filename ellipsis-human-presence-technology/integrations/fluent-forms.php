<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceFluentFormsIntegration extends HumanPresenceIntegration {

	public static $form_prefix = 'flu';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 30, 1 );
		add_filter( 'fluentform/validation_errors', array( __CLASS__, 'validate_forms' ), 10, 4 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 30, 1 );
	}

	/**
	 * Get Formidable Forms
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( function_exists( 'wpFluent' ) ) {
			$additional_forms     = array();
			$form_list = wpFluent()->table('fluentform_forms')->orderBy('created_at', 'DESC')->get();
			if ( is_array( $form_list ) ) {
				foreach ( $form_list as $form ) {
					$fid = self::get_form_id( $form->id );
					// Get current form entries
					$entries = 	wpFluent()
						            ->table('fluentform_submissions')
						            ->where('form_id', $form->id)
						            ->where('status', '!=', 'trashed')
						            ->get();
					// Get last entry date
					$last_entry_obj = array_pop($entries);
					$last_entry    = is_object($last_entry_obj) ? $last_entry_obj->created_at : null;
					// Push to forms list
					$additional_forms[] = array(
						'id'          => $fid,
						'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
						'name'        => ( $form->title ),
						'type'        => 'Fluent',
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
	 * Validate Fluent forms
	 *
	 * @param Array $errors - Array of error messages
	 * @param Array $values - Array of form value strings submitted
	 *
	 * @return Array - $errors
	 */
	public static function validate_forms( $errors, $form_data, $form, $fields ) {
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		$fid     = self::get_form_id( absint( $form->id ) );
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
	 * Protect Existing Fluent forms
	 */
	public static function enable_forms( $unattended ) {
		if ( class_exists( '\FluentForm\App\Modules\Form\Form' ) ) {
			$form_list = wpFluent()->table('fluentform_forms')->orderBy('created_at', 'DESC')->get();
			if ( is_array( $form_list ) ) {
				foreach ( $form_list as $form ) {
					// Assign unique form id
					$fid = self::get_form_id( $form->id );
					HumanPresenceForms::enable_forms( $fid, 1, $unattended );
				}
			}
		}
	}
}

add_action( 'plugins_loaded', function() { new HumanPresenceFluentFormsIntegration(); });
