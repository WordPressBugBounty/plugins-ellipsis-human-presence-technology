<?php
if ( ! defined( 'ABSPATH' ) ) {
	die( 'You are not allowed to call this page directly.' );
}

class HumanPresenceElementorFormsIntegration extends HumanPresenceIntegration {

	public static $form_prefix = 'elf';

	/*
	 * This is where you will add filters and actions
	 */
	public function __construct() {
		add_filter( 'humanpresence_forms_list', array( __CLASS__, 'get_forms' ), 30, 1 );
		add_action( 'elementor_pro/forms/validation', array( __CLASS__, 'validate_forms' ), 10, 2 );
		add_action( 'humanpresence_autoprotect_forms', array( __CLASS__, 'enable_forms' ), 30, 1 );
	}

	/**
	 * Get Formidable Forms
	 * This should return the forms list
	 *
	 * @return Array $forms_list
	 */
	public static function get_forms( $forms_list ) {
		if ( function_exists( '_is_elementor_installed' ) && defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$additional_forms     = array();
			try {
				$submissions = (new ElementorPro\Modules\Forms\Submissions\Database\Query())->get_submissions();
			} catch (\Exception $e) {
				$submissions = [];
			} catch (\Throwable $e) {
				$submissions = [];
			}
			$form_list = [
				array(
					'id' => 'all', 
					'name' => 'All Elementor Forms', 
					'entries' => $submissions,
					'updated_at' => '',
				),
			];
			foreach ( $form_list as $form ) {
				$fid = self::get_form_id( $form['id'] );
				// Get current form entries
				$entries = $form['entries'];
				// Push to forms list
				$additional_forms[] = array(
					'id'          => $fid,
					'enabled'     => HumanPresenceSettings::render_hp_fm_enabled_cb( $fid ),
					'name'        => ( $form['name'] ),
					'type'        => 'Elementor',
					'submissions' => HumanPresenceForms::get_form_submissions_ct( $fid, count( $entries ) ),
					'activity'    => HumanPresenceForms::get_latest_form_activity( $fid, $form['updated_at'] ),
				);
			}
			self::prune_disabled_forms( $additional_forms );
			$forms_list = array_merge( $forms_list, $additional_forms );
		}

		return $forms_list;
	}

	/**
	 * Validate Formidable forms
	 *
	 * @param Object $record - Form Record object
	 * @param Object $ajax_handler - Ajax Handler object
	 *
	 */
	public static function validate_forms( $record, $ajax_handler ) {
		// If HP protection enabled, validate the form
		$options = human_presence()->get_options();
		$fid     = self::get_form_id( 'all' );
		if ( is_array( $options ) && array_key_exists( 'hp_forms', $options ) && isset( $options['hp_forms'][ $fid ] ) ) {
			$hp_form_enabled = $options['hp_forms'][ $fid ]['form_enabled'];
			if ( $hp_form_enabled ) {
				// If the detection check fails, prevent the form submission and display an error message.
				$validation_failed = HumanPresenceForms::fail_form_validation();
				if ( $validation_failed ) {
					// Record suspicious activity
					HumanPresenceForms::handle_suspicious_form_activity( $fid );
					// Add error message
					$field_id = 0;
					$fields = $record->get('fields');
					if(is_array($fields) && !empty($fields)) {
						$field_id = $fields[0]['id'];
					}
					$ajax_handler->add_error( $field_id, HumanPresenceForms::fail_form_error_message() );
					$ajax_handler->add_error_message( HumanPresenceForms::fail_form_error_message() );
				}
			}
		}

	}

	/**
	 * Protect Existing Formidable forms
	 */
	public static function enable_forms( $unattended ) {
		if ( function_exists( '_is_elementor_installed' ) && defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$form_list = [['id' => 'all']];
			foreach ( $form_list as $form ) {
				// Assign unique form id
				$fid = self::get_form_id( $form['id'] );
				HumanPresenceForms::enable_forms( $fid, 1, $unattended );
			}
		}
	}
}

add_action( 'plugins_loaded', function() { new HumanPresenceElementorFormsIntegration(); });
